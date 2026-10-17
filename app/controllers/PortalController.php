<?php
declare(strict_types=1);

/** Kundenportal mit Magic-Link-Anmeldung. */
final class PortalController
{
    public function index(): void
    {
        $c = Auth::customer();
        if (!$c) {
            view('portal/login', [], 'public');
        }
        OfferService::sweepExpired();
        $offers = DB::all(
            'SELECT o.*, (SELECT id FROM orders WHERE offer_id = o.id) AS order_id FROM offers o WHERE o.customer_id = ? ORDER BY o.created_at DESC',
            [$c['id']]
        );
        $orders = DB::all('SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC', [$c['id']]);
        $invoices = DB::all("SELECT i.*, o.number AS order_number FROM invoices i JOIN orders o ON o.id = i.order_id WHERE i.customer_id = ? AND i.status <> 'draft' ORDER BY i.issue_date DESC, i.id DESC", [$c['id']]);
        view('portal/index', compact('c', 'offers', 'orders', 'invoices'), 'public');
    }

    public function requestLink(): void
    {
        $email = mb_strtolower(input_str('email', 190));
        if (!valid_email($email)) {
            view('portal/login', ['email' => $email, 'error' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'], 'public');
        }
        $bucket = 'portal-link:' . $email;
        $ipBucket = 'portal-link-ip:' . client_ip();
        if (!RateLimit::tooMany($bucket, 3, 900) && !RateLimit::tooMany($ipBucket, 10, 900)) {
            RateLimit::hit($bucket);
            RateLimit::hit($ipBucket);
            $c = DB::one('SELECT * FROM customers WHERE email = ? AND portal_enabled = 1', [$email]);
            if ($c) {
                Notifier::portalLink($c, Tokens::create('customer', (int) $c['id'], 30));
            }
        }
        // Immer die gleiche Antwort – verrät nicht, ob eine Adresse existiert.
        view('auth/link-sent', ['email' => $email, 'back' => 'portal'], 'public');
    }

    public function confirm(string $token): void
    {
        $row = Tokens::find('customer', $token);
        view($row ? 'auth/confirm' : 'auth/link-invalid', [
            'action' => 'portal/auth/' . $token,
            'back'   => 'portal',
            'area'   => 'Kundenportal',
        ], 'public');
    }

    public function login(string $token): void
    {
        $row = Tokens::find('customer', $token);
        $c = $row ? DB::one('SELECT * FROM customers WHERE id = ? AND portal_enabled = 1', [$row['subject_id']]) : null;
        if (!$row || !$c || !Tokens::consume($row)) {
            view('auth/link-invalid', ['back' => 'portal'], 'public');
        }
        Auth::loginCustomer($c);
        redirect('portal');
    }

    public function logout(): void
    {
        Auth::logoutCustomer();
        flash('success', 'Sie wurden abgemeldet.');
        redirect('portal');
    }

    public function file(string $id, string $fid): void
    {
        $c = Auth::requireCustomer();
        $att = AttachmentService::find((int) $fid);
        $owns = $att && $att['entity_type'] === 'order' && (int) $att['entity_id'] === (int) $id
            && DB::val('SELECT 1 FROM orders WHERE id = ? AND customer_id = ?', [(int) $id, $c['id']]);
        if (!$owns || !$att['customer_visible']) {
            throw new HttpException(404, 'Anhang nicht gefunden.');
        }
        AttachmentService::send($att);
    }

    public function order(string $id): void
    {
        $c = Auth::requireCustomer();
        $order = DB::one(
            'SELECT o.*, f.public_id AS offer_public_id, f.number AS offer_number, p.name AS area_name
             FROM orders o JOIN offers f ON f.id = o.offer_id LEFT JOIN product_areas p ON p.id = o.product_area_id
             WHERE o.id = ? AND o.customer_id = ?',
            [(int) $id, $c['id']]
        );
        if (!$order) {
            throw new HttpException(404, 'Dieser Auftrag wurde nicht gefunden.');
        }
        $history = History::for('order', (int) $order['id'], true);
        $files = AttachmentService::forEntity('order', (int) $order['id'], true);
        view('portal/order', compact('c', 'order', 'history', 'files'), 'public');
    }

    /* ------------------------------------------------------------ Nachrichten */

    public function messages(): void
    {
        $c = Auth::requireCustomer();
        $thread = PortalMessage::thread((int) $c['id']);
        PortalMessage::markRead((int) $c['id'], 'out');
        $refs = PortalMessage::refsFor((int) $c['id']);
        $preset = input_str('ref', 40);
        view('portal/messages', compact('c', 'thread', 'refs', 'preset'), 'public');
    }

    public function sendMessage(): void
    {
        $c = Auth::requireCustomer();
        $bucket = 'portal-msg:' . $c['id'];
        if (RateLimit::tooMany($bucket, 15, 3600)) {
            flash('error', 'Sie haben in kurzer Zeit viele Nachrichten gesendet. Bitte versuchen Sie es später erneut.');
            redirect('portal/messages');
        }
        try {
            [$type, $eid] = PortalMessage::parseRef((int) $c['id'], input_str('ref', 40));
            $id = PortalMessage::add((int) $c['id'], 'in', (string) input('body', ''), customer_name($c), null, $type, $eid);
            RateLimit::hit($bucket);
            $msg = DB::one('SELECT * FROM portal_messages WHERE id = ?', [$id]);
            Notifier::portalMessageToTeam($c, $msg, PortalMessage::refLabel($msg));
            flash('success', 'Nachricht gesendet. Wir melden uns so rasch wie möglich.');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('portal/messages');
    }

    /* ------------------------------------------------------------ Dokumente */

    /** Freigegebene und eigene Dateien des Kunden (Angebote, Aufträge, Rechnungen). */
    private function documents(array $c): array
    {
        return DB::all(
            "SELECT a.*, COALESCE(o.number, r.number, i.number) AS doc_number
             FROM attachments a
             LEFT JOIN offers o ON a.entity_type = 'offer' AND o.id = a.entity_id AND o.customer_id = :c1
             LEFT JOIN orders r ON a.entity_type = 'order' AND r.id = a.entity_id AND r.customer_id = :c2
             LEFT JOIN invoices i ON a.entity_type = 'invoice' AND i.id = a.entity_id AND i.customer_id = :c3 AND i.status <> 'draft'
             WHERE a.customer_visible = 1 AND COALESCE(o.id, r.id, i.id) IS NOT NULL
             ORDER BY a.created_at DESC, a.id DESC",
            ['c1' => $c['id'], 'c2' => $c['id'], 'c3' => $c['id']]
        );
    }

    private function ownedAttachment(array $c, int $id): ?array
    {
        foreach ($this->documents($c) as $a) {
            if ((int) $a['id'] === $id) {
                return $a;
            }
        }
        return null;
    }

    public function documentsPage(): void
    {
        $c = Auth::requireCustomer();
        $files = $this->documents($c);
        $offers = DB::all("SELECT public_id, number, title, status, created_at FROM offers WHERE customer_id = ? AND status <> 'draft' ORDER BY created_at DESC", [$c['id']]);
        $invoices = DB::all("SELECT public_id, number, title, status, issue_date FROM invoices WHERE customer_id = ? AND status <> 'draft' ORDER BY issue_date DESC, id DESC", [$c['id']]);
        $orders = DB::all('SELECT id, number, title FROM orders WHERE customer_id = ? ORDER BY id DESC', [$c['id']]);
        view('portal/documents', compact('c', 'files', 'offers', 'invoices', 'orders'), 'public');
    }

    public function uploadDocument(): void
    {
        $c = Auth::requireCustomer();
        $bucket = 'portal-upload:' . $c['id'];
        if (RateLimit::tooMany($bucket, 20, 3600)) {
            flash('error', 'Zu viele Uploads in kurzer Zeit. Bitte versuchen Sie es später erneut.');
            redirect('portal/documents');
        }
        $order = DB::one('SELECT * FROM orders WHERE id = ? AND customer_id = ?', [(int) input_int('order_id'), $c['id']]);
        if (!$order) {
            flash('error', 'Bitte wählen Sie den Auftrag, zu dem die Datei gehört.');
            redirect('portal/documents');
        }
        try {
            RateLimit::hit($bucket);
            $name = (string) ($_FILES['file']['name'] ?? '');
            AttachmentService::add('order', (int) $order['id'], $_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE], true, $c, true);
            Notifier::portalUpload($c, $order, AttachmentService::cleanName($name));
            flash('success', 'Datei hochgeladen. Vielen Dank!');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('portal/documents');
    }

    public function downloadDocument(string $id): void
    {
        $c = Auth::requireCustomer();
        $att = $this->ownedAttachment($c, (int) $id);
        if (!$att) {
            throw new HttpException(404, 'Datei nicht gefunden.');
        }
        AttachmentService::send($att);
    }

    public function deleteDocument(string $id): void
    {
        $c = Auth::requireCustomer();
        $att = $this->ownedAttachment($c, (int) $id);
        if (!$att || !(int) $att['by_customer']) {
            throw new HttpException(404, 'Datei nicht gefunden.');
        }
        AttachmentService::delete($att, ['id' => $c['id'], 'name' => customer_name($c)]);
        flash('success', 'Datei gelöscht.');
        redirect('portal/documents');
    }

    /* ------------------------------------------------------------ Profil */

    private const PROFILE_FIELDS = ['salutation' => 20, 'first_name' => 100, 'last_name' => 100, 'company' => 190, 'street' => 190, 'address_extra' => 190, 'zip' => 20, 'city' => 120, 'phone' => 50, 'mobile' => 50];

    public function profile(): void
    {
        $c = Auth::requireCustomer();
        view('portal/profile', ['c' => $c, 'errors' => [], 'old' => $c], 'public');
    }

    public function saveProfile(): void
    {
        $c = Auth::requireCustomer();
        $new = [];
        foreach (self::PROFILE_FIELDS as $f => $max) {
            $new[$f] = trim(preg_replace('/\s+/u', ' ', input_str($f, $max)) ?? '');
        }
        $new['country'] = strtoupper(input_str('country', 2));
        $errors = [];
        if ($new['last_name'] === '' && $new['company'] === '') {
            $errors[] = 'Bitte geben Sie einen Namen oder eine Firma an.';
        }
        if ($new['street'] === '' || $new['zip'] === '' || $new['city'] === '') {
            $errors[] = 'Bitte geben Sie Strasse, PLZ und Ort an.';
        }
        if (!isset(countries()[$new['country']])) {
            $new['country'] = (string) $c['country'];
        }
        if ($errors) {
            view('portal/profile', ['c' => $c, 'errors' => $errors, 'old' => array_merge($c, $new)], 'public');
        }
        $changed = [];
        foreach ($new as $f => $v) {
            if ((string) ($c[$f] ?? '') !== $v) {
                $changed[] = $f;
            }
        }
        if ($changed) {
            foreach (['phone', 'mobile', 'company', 'address_extra'] as $nullable) {
                if ($new[$nullable] === '') {
                    $new[$nullable] = null;
                }
            }
            $new['updated_at'] = now();
            DB::update('customers', $new, 'id = :id', [':id' => $c['id']]);
            Audit::log('customer_self_edit', 'Kunde ' . $c['customer_no'] . ' hat sein Profil geändert: ' . implode(', ', $changed), 'customer', (int) $c['id']);
        }
        flash('success', $changed ? 'Ihre Angaben wurden gespeichert. Sie gelten für künftige Dokumente.' : 'Keine Änderungen.');
        redirect('portal/profile');
    }
}
