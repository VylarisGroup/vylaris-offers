<?php
declare(strict_types=1);

/** Offerten: nur Erstellen, Lesen, Löschen (CRD). Änderungen nur über eine neue Offerte. */
final class OfferController
{
    public function index(): void
    {
        Auth::requireUser();
        OfferService::sweepExpired();
        $status = input_str('status', 20);
        $q = input_str('q', 100);
        $where = ['1=1'];
        $params = [];
        $owner = (string) Responsibility::filter(input_str('owner', 12), 'o.created_by', $where, $params, false);
        if (isset(Status::OFFER[$status])) {
            $where[] = 'o.status = :status';
            $params[':status'] = $status;
        }
        if ($q !== '') {
            $where[] = search_clause(['o.number', 'o.title', 'c.company', 'c.last_name', 'c.first_name'], $q, $params);
        }
        $offers = DB::all(
            'SELECT o.*, c.company, c.first_name, c.last_name, p.name AS area_name, p.icon AS area_icon, COALESCE(u.name, o.created_by_name) AS issuer_label
             FROM offers o JOIN customers c ON c.id = o.customer_id LEFT JOIN product_areas p ON p.id = o.product_area_id LEFT JOIN users u ON u.id = o.created_by
             WHERE ' . implode(' AND ', $where) . ' ORDER BY o.created_at DESC LIMIT 500',
            $params
        );
        $counts = ['' => (int) DB::val('SELECT COUNT(*) FROM offers')];
        foreach (DB::all('SELECT status, COUNT(*) n FROM offers GROUP BY status') as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }
        $staff = Responsibility::staff();
        view('admin/offers/index', compact('offers', 'status', 'q', 'counts', 'owner', 'staff'));
    }

    public function create(): void
    {
        Auth::requireUser();
        $from = null;
        $form = [];
        if ($fromId = input_int('from')) {
            $from = OfferService::find($fromId);
            if (!$from) {
                throw new HttpException(404, 'Die Ausgangsofferte wurde nicht gefunden.');
            }
            $form = OfferService::toFormData($from);
            if (input('copy') === '1') {
                // Duplizieren: unabhängige neue Offerte (keine Versionierung, Original bleibt unverändert).
                $this->editor($form, [], null, null, $from);
            }
        } else {
            $days = (int) Settings::get('offer_validity_days', 30);
            $form = [
                'customer_id' => input_int('customer'),
                'inquiry_id'  => input_int('inquiry'),
                'title'       => ($inq = input_int('inquiry') ? DB::one('SELECT subject, name FROM inquiries WHERE id = ?', [input_int('inquiry')]) : null) ? ($inq['subject'] ?: 'Anfrage von ' . $inq['name']) : '',
                'intro'       => DocTemplate::textFor('offer', null, 'intro') ?? Settings::get('offer_intro', ''),
                'footer'      => DocTemplate::textFor('offer', null, 'closing') ?? Settings::get('offer_footer', ''),
                'valid_until' => $days > 0 ? date('Y-m-d', strtotime("+$days days")) : '',
                'no_expiry'   => $days > 0 ? '' : '1',
                'items'       => [],
            ];
        }
        $this->editor($form, [], $from);
    }

    public function store(): void
    {
        $user = Auth::requireUser();
        $parentId = input_int('parent_id');
        try {
            $built = OfferService::fromInput($_POST);
        } catch (ValidationException $e) {
            $parent = $parentId ? OfferService::find($parentId) : null;
            $this->editor($_POST, $e->errors, $parent, $e->getMessage());
        }

        $id = OfferService::create($built['offer'], $built['items'], $user, $parentId ?: null);
        $offer = OfferService::find($id);
        if ($inqId = input_int('inquiry_id')) {
            DB::exec("UPDATE inquiries SET offer_id = ?, status = 'handled', handled_at = ? WHERE id = ? AND customer_id = ? AND offer_id IS NULL", [$id, now(), $inqId, $offer['customer_id']]);
        }

        if (!empty($_POST['send_now'])) {
            if (Notifier::offerSent($offer)) {
                History::add('offer', $id, 'sent', null, null, 'An ' . $offer['customer']['email'], false, $user);
                flash('success', "Offerte {$offer['number']} wurde erstellt und an {$offer['customer']['email']} gesendet.");
            } else {
                flash('warning', "Offerte {$offer['number']} wurde erstellt, die E-Mail konnte aber nicht gesendet werden: " . Notifier::$lastError);
            }
        } else {
            flash('success', "Offerte {$offer['number']} wurde erstellt.");
        }
        redirect('admin/offers/' . $id);
    }

    public function pdf(string $id): void
    {
        Auth::requireUser();
        $offer = OfferService::find((int) $id);
        if (!$offer) {
            throw new HttpException(404, 'Die Offerte wurde nicht gefunden.');
        }
        PdfService::send(PdfService::offer($offer), 'Offerte-' . $offer['number'] . '.pdf');
    }

    public function signature(string $id): void
    {
        Auth::requireUser();
        $f = FileStore::signaturePath((string) DB::val('SELECT signature_file FROM offers WHERE id = ?', [(int) $id]));
        if (!$f) {
            throw new HttpException(404);
        }
        header('Content-Type: image/png');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($f);
        exit;
    }

    public function show(string $id): void
    {
        Auth::requireUser();
        OfferService::sweepExpired();
        $offer = OfferService::find((int) $id);
        if (!$offer) {
            throw new HttpException(404, 'Die Offerte wurde nicht gefunden.');
        }
        $history = History::for('offer', (int) $offer['id']);
        $order = DB::one('SELECT * FROM orders WHERE offer_id = ?', [$offer['id']]);
        $parent = $offer['parent_id'] ? DB::one('SELECT id, number, status FROM offers WHERE id = ?', [$offer['parent_id']]) : null;
        $children = DB::all('SELECT id, number, status, created_at FROM offers WHERE parent_id = ? ORDER BY id', [$offer['id']]);
        $area = $offer['product_area_id'] ? DB::one('SELECT * FROM product_areas WHERE id = ?', [$offer['product_area_id']]) : null;
        $creator = $offer['created_by'] ? DB::one('SELECT name FROM users WHERE id = ?', [$offer['created_by']]) : null;
        $issuer = Responsibility::label($creator['name'] ?? null, $offer['created_by_name'] ?? null);
        $review = array_values(array_filter(
            TaxService::review($offer['customer'], $offer['taxes'], date('Y-m-d'), (string) ($offer['area_type'] ?? 'digital'), false),
            static fn($r) => $r['level'] !== 'error'
        ));
        view('admin/offers/show', compact('offer', 'history', 'order', 'parent', 'children', 'area', 'creator', 'issuer', 'review'));
    }

    public function send(string $id): void
    {
        $user = Auth::requireUser();
        $offer = OfferService::find((int) $id);
        if (!$offer) {
            throw new HttpException(404);
        }
        if ($offer['status'] !== 'open') {
            flash('error', 'Nur offene Offerten können versendet werden.');
        } elseif (Notifier::offerSent($offer)) {
            History::add('offer', (int) $offer['id'], 'sent', null, null, 'An ' . $offer['customer']['email'], false, $user);
            flash('success', 'Die Offerte wurde an ' . $offer['customer']['email'] . ' gesendet.');
        } else {
            flash('error', 'Die E-Mail konnte nicht gesendet werden: ' . Notifier::$lastError);
        }
        redirect('admin/offers/' . $offer['id']);
    }

    public function destroy(string $id): void
    {
        $user = Auth::requireUser();
        try {
            OfferService::delete((int) $id, $user);
            Audit::log('offer_deleted', null, 'offer', (int) $id, $user);
            flash('success', 'Die Offerte wurde gelöscht.');
            redirect('admin/offers');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
            redirect('admin/offers/' . (int) $id);
        }
    }

    private function editor(array $form, array $errors, ?array $from, ?string $error = null, ?array $copyOf = null): never
    {
        $customers = DB::all('SELECT id, customer_no, company, first_name, last_name, email, street, zip, city, country FROM customers ORDER BY COALESCE(NULLIF(company,\'\'), last_name), first_name');
        $customerData = [];
        foreach ($customers as $c) {
            $customerData[$c['id']] = [
                'name'     => customer_name($c),
                'country'  => $c['country'],
                'email'    => $c['email'],
                'address'  => implode(', ', array_filter([$c['street'], trim($c['zip'] . ' ' . $c['city']), $c['country']])),
            ];
        }
        $areas = DB::all('SELECT * FROM product_areas WHERE active = 1 ORDER BY sort, name');
        $taxes = TaxService::all();
        $shipping = DB::all('SELECT * FROM shipping_methods WHERE active = 1 ORDER BY sort, name');
        $templates = DB::all(
            'SELECT t.*, p.name AS area_name FROM item_templates t LEFT JOIN product_areas p ON p.id = t.product_area_id WHERE t.active = 1 ORDER BY t.title'
        );
        if ($error) {
            flash('error', $error);
        }
        $docTemplates = DocTemplate::forType('offer');
        view('admin/offers/form', compact('form', 'errors', 'from', 'copyOf', 'customers', 'customerData', 'areas', 'taxes', 'shipping', 'templates', 'docTemplates'));
    }
}
