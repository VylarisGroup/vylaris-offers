<?php
declare(strict_types=1);

final class CustomerController
{
    private const FIELDS = [
        'salutation' => 20, 'first_name' => 100, 'last_name' => 100, 'company' => 190, 'street' => 190,
        'address_extra' => 190, 'zip' => 20, 'city' => 120, 'country' => 2, 'email' => 190, 'phone' => 50,
        'mobile' => 50, 'website' => 190, 'vat_id' => 50, 'notes' => 10000,
    ];

    public function index(): void
    {
        Auth::requireUser();
        $q = input_str('q', 100);
        $params = [];
        $where = '1=1';
        if ($q !== '') {
            $where = search_clause(['c.company', 'c.first_name', 'c.last_name', 'c.email', 'c.customer_no', 'c.city', "CONCAT(c.first_name, ' ', c.last_name)"], $q, $params);
        }
        $customers = DB::all(
            "SELECT c.*,
                (SELECT COUNT(*) FROM offers o WHERE o.customer_id = c.id) AS offer_count,
                (SELECT COUNT(*) FROM orders r WHERE r.customer_id = c.id) AS order_count,
                (SELECT COALESCE(SUM(total),0) FROM orders r WHERE r.customer_id = c.id AND r.status <> 'cancelled') AS revenue
             FROM customers c WHERE $where ORDER BY COALESCE(NULLIF(c.company,''), c.last_name), c.first_name LIMIT 500",
            $params
        );
        view('admin/customers/index', compact('customers', 'q'));
    }

    public function create(): void
    {
        Auth::requireUser();
        view('admin/customers/form', ['customer' => ['country' => Settings::get('company_country', 'CH'), 'portal_enabled' => 1], 'errors' => []]);
    }

    public function store(): void
    {
        $user = Auth::requireUser();
        [$data, $errors] = $this->validate($_POST);
        if ($errors) {
            view('admin/customers/form', ['customer' => $data, 'errors' => $errors]);
        }
        $data['customer_no'] = Counter::customerNumber();
        $data['created_at'] = now();
        $id = DB::insert('customers', $data);
        flash('success', 'Kunde „' . customer_name($data) . '“ wurde angelegt.');
        if (input('then') === 'offer') {
            redirect('admin/offers/new?customer=' . $id);
        }
        redirect('admin/customers/' . $id);
    }

    public function show(string $id): void
    {
        Auth::requireUser();
        $customer = $this->find((int) $id);
        $offers = DB::all('SELECT * FROM offers WHERE customer_id = ? ORDER BY created_at DESC', [$customer['id']]);
        $orders = DB::all('SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC', [$customer['id']]);
        $tax = TaxService::defaultTemplate();
        $privacy = CustomerPrivacy::assess((int) $customer['id']);
        view('admin/customers/show', compact('customer', 'offers', 'orders', 'tax', 'privacy'));
    }

    public function edit(string $id): void
    {
        Auth::requireUser();
        view('admin/customers/form', ['customer' => $this->find((int) $id), 'errors' => []]);
    }

    public function update(string $id): void
    {
        Auth::requireUser();
        $customer = $this->find((int) $id);
        [$data, $errors] = $this->validate($_POST, (int) $customer['id']);
        if ($errors) {
            view('admin/customers/form', ['customer' => array_merge($customer, $data), 'errors' => $errors]);
        }
        $data['updated_at'] = now();
        DB::update('customers', $data, 'id = :id', [':id' => $customer['id']]);
        flash('success', 'Die Kundendaten wurden gespeichert.');
        redirect('admin/customers/' . $customer['id']);
    }

    public function destroy(string $id): void
    {
        Auth::requireUser();
        $customer = $this->find((int) $id);
        if (DB::val('SELECT 1 FROM offers WHERE customer_id = ? LIMIT 1', [$customer['id']])) {
            flash('error', 'Dieser Kunde hat Offerten oder Aufträge und kann nicht gelöscht werden. Sie können stattdessen den Portalzugang deaktivieren.');
            redirect('admin/customers/' . $customer['id']);
        }
        DB::exec("DELETE FROM login_tokens WHERE subject_type = 'customer' AND subject_id = ?", [$customer['id']]);
        DB::exec('DELETE FROM customers WHERE id = ?', [$customer['id']]);
        Audit::log('customer_deleted', null, 'customer', (int) $customer['id']);
        flash('success', 'Der Kunde wurde gelöscht.');
        redirect('admin/customers');
    }

    /** Auskunft/Datenübertragbarkeit (Art. 15/20 DSGVO) als JSON-Download. */
    public function privacyExport(string $id): void
    {
        $user = Auth::requireUser();
        $customer = $this->find((int) $id);
        Audit::log('customer_export', null, 'customer', (int) $customer['id'], $user);
        CustomerPrivacy::sendExport((int) $customer['id'], 'datenauskunft');
    }

    public function restrict(string $id): void
    {
        $user = Auth::requireAdmin();
        $customer = $this->find((int) $id);
        try {
            CustomerPrivacy::restrict((int) $customer['id'], $user);
            flash('success', 'Die Verarbeitung wurde eingeschränkt: Zusatzdaten gelöscht, Portal gesperrt, keine neuen Offerten oder E-Mails mehr. Rechnungsdaten bleiben bis zum Ende der Aufbewahrungsfrist gespeichert.');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/customers/' . $customer['id']);
    }

    public function anonymize(string $id): void
    {
        $user = Auth::requireAdmin();
        $customer = $this->find((int) $id);
        try {
            CustomerPrivacy::anonymize((int) $customer['id'], $user);
            flash('success', 'Der Kunde wurde unumkehrbar anonymisiert.');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/customers/' . $customer['id']);
    }

    public function sendPortalLink(string $id): void
    {
        Auth::requireUser();
        $c = $this->find((int) $id);
        if (!$c['portal_enabled']) {
            flash('error', 'Der Portalzugang dieses Kunden ist deaktiviert.');
        } elseif (Notifier::portalLink($c, Tokens::create('customer', (int) $c['id'], 60 * 24))) {
            flash('success', 'Ein Anmeldelink für das Kundenportal wurde an ' . $c['email'] . ' gesendet.');
        } else {
            flash('error', 'Die E-Mail konnte nicht gesendet werden: ' . Notifier::$lastError);
        }
        redirect('admin/customers/' . $c['id']);
    }

    private function find(int $id): array
    {
        $c = DB::one('SELECT * FROM customers WHERE id = ?', [$id]);
        if (!$c) {
            throw new HttpException(404, 'Der Kunde wurde nicht gefunden.');
        }
        return $c;
    }

    private function validate(array $in, ?int $ignoreId = null): array
    {
        $data = [];
        foreach (self::FIELDS as $f => $max) {
            $data[$f] = mb_substr(trim((string) ($in[$f] ?? '')), 0, $max);
        }
        $data['email'] = mb_strtolower($data['email']);
        $data['country'] = strtoupper($data['country'] ?: 'CH');
        $data['portal_enabled'] = !empty($in['portal_enabled']) ? 1 : 0;

        $errors = [];
        if ($data['company'] === '' && ($data['first_name'] === '' || $data['last_name'] === '')) {
            $errors['last_name'] = 'Bitte geben Sie Vor- und Nachname oder einen Firmennamen ein.';
        }
        if (!valid_email($data['email'])) {
            $errors['email'] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        } elseif (DB::val('SELECT id FROM customers WHERE email = ? AND id <> ?', [$data['email'], $ignoreId ?? 0])) {
            $errors['email'] = 'Diese E-Mail-Adresse ist bereits einem anderen Kunden zugeordnet.';
        }
        foreach (['street' => 'Strasse', 'zip' => 'PLZ', 'city' => 'Ort'] as $f => $label) {
            if ($data[$f] === '') {
                $errors[$f] = "$label ist erforderlich.";
            }
        }
        if (!isset(countries()[$data['country']])) {
            $errors['country'] = 'Bitte wählen Sie ein Land.';
        }
        if ($data['website'] !== '' && !preg_match('#^https?://#i', $data['website'])) {
            $data['website'] = 'https://' . $data['website'];
        }
        if ($data['vat_id'] !== '' && preg_match('/^CHE/i', $data['vat_id'])) {
            if (!TaxService::validUid($data['vat_id'])) {
                $errors['vat_id'] = 'Die UID ist ungültig (Format CHE-123.456.789, mit gültiger Prüfziffer).';
            } else {
                $data['vat_id'] = TaxService::formatUid($data['vat_id'], stripos($data['vat_id'], 'MWST') !== false || stripos($data['vat_id'], 'TVA') !== false || stripos($data['vat_id'], 'IVA') !== false);
            }
        }
        $data['tax_exempt'] = 0;
        $data['tax_exempt_reason'] = null;
        foreach (['salutation', 'company', 'address_extra', 'phone', 'mobile', 'website', 'vat_id', 'notes'] as $nullable) {
            $data[$nullable] = $data[$nullable] === '' ? null : $data[$nullable];
        }
        return [$data, $errors];
    }
}
