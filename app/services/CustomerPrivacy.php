<?php
/**
 * Betroffenenrechte pro Kunde:
 *  - export():    Auskunft (Art. 15) und Datenübertragbarkeit (Art. 20) als JSON
 *  - restrict():  Einschränkung der Verarbeitung (Art. 18) bzw. Teil-Löschung, wenn Aufbewahrungspflichten bestehen
 *  - anonymize(): Löschung durch unumkehrbare Anonymisierung (Art. 17), sofern keine Aufbewahrungspflicht entgegensteht
 * Rechnungen/Offerten bleiben bis zum Ende der gesetzlichen Aufbewahrungsfrist unverändert erhalten (Art. 17 Abs. 3 lit. b).
 */
declare(strict_types=1);

final class CustomerPrivacy
{
    private const ANON_NAME = 'Anonymisiert';

    public static function email(int $customerId): string
    {
        return "anonymisiert-$customerId@anonymisiert.invalid";
    }

    public static function retentionCutoff(): string
    {
        $years = max(1, (int) (Settings::get('retention_years') ?: 10));
        return date('Y-m-d', strtotime("-$years years"));
    }

    /** Prüft, ob vollständige Anonymisierung möglich ist, und nennt sonst die Gründe. */
    public static function assess(int $id): array
    {
        $reasons = [];
        if ($n = (int) DB::val("SELECT COUNT(*) FROM offers WHERE customer_id = ? AND status = 'open'", [$id])) {
            $reasons[] = "$n offene Offerte(n) – bitte zuerst ablehnen/ablaufen lassen";
        }
        if ($n = (int) DB::val("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status NOT IN ('completed','cancelled')", [$id])) {
            $reasons[] = "$n laufende(r) Auftrag/Aufträge";
        }
        if ($n = (int) DB::val("SELECT COUNT(*) FROM invoices WHERE customer_id = ? AND status IN ('draft','sent')", [$id])) {
            $reasons[] = "$n offene(r) Rechnungsentwurf/Rechnung(en)";
        }
        if ($n = (int) DB::val("SELECT COUNT(*) FROM subscriptions WHERE customer_id = ? AND status <> 'ended'", [$id])) {
            $reasons[] = "$n aktive(s) Abo(s)";
        }
        $cut = self::retentionCutoff();
        $years = max(1, (int) (Settings::get('retention_years') ?: 10));
        $locked = (int) DB::val("SELECT COUNT(*) FROM invoices WHERE customer_id = ? AND status <> 'draft' AND issue_date >= ?", [$id, $cut]);
        if ($locked) {
            $last = (string) DB::val("SELECT MAX(issue_date) FROM invoices WHERE customer_id = ? AND status <> 'draft'", [$id]);
            $reasons[] = "$locked Rechnung(en) unterliegen der gesetzlichen Aufbewahrungspflicht ($years Jahre; frühestens am " . fmt_date(date('Y-m-d', strtotime("$last +$years years +1 day"))) . ' möglich)';
        }
        return ['can_anonymize' => !$reasons, 'reasons' => $reasons, 'locked_invoices' => $locked];
    }

    /* ---------------------------------------------------------------- Auskunft */

    public static function export(int $id): array
    {
        $c = DB::one('SELECT * FROM customers WHERE id = ?', [$id]);
        if (!$c) {
            throw new HttpException(404, 'Kunde nicht gefunden.');
        }
        $strip = static function (array $row): array {
            unset($row['public_id']);
            return $row;
        };
        $data = [
            'export_info' => [
                'erstellt_am'  => date('c'),
                'verantwortlich' => Settings::get('company_name'),
                'hinweis'      => 'Auskunft nach Art. 15 DSGVO / Art. 25 DSG und Datenübertragbarkeit nach Art. 20 DSGVO.',
            ],
            'kunde' => $c,
            'anfragen' => DB::all('SELECT created_at, name, company, email, phone, subject, message, status FROM inquiries WHERE customer_id = ? OR email = ? ORDER BY id', [$id, $c['email']]),
            'nachrichten' => DB::all('SELECT created_at, direction, author, entity_type, entity_id, body FROM portal_messages WHERE customer_id = ? ORDER BY id', [$id]),
            'offerten' => [], 'auftraege' => [], 'rechnungen' => [], 'abos' => [], 'verlauf' => [], 'anhaenge' => [], 'e_mails' => [],
        ];
        foreach (DB::all('SELECT * FROM offers WHERE customer_id = ? ORDER BY id', [$id]) as $o) {
            $o = $strip($o);
            $o['positionen'] = DB::all('SELECT * FROM offer_items WHERE offer_id = ? ORDER BY position', [$o['id']]);
            if ($f = FileStore::signaturePath((string) $o['signature_file'])) {
                $o['unterschrift_png_base64'] = base64_encode((string) file_get_contents($f));
            }
            $o['einmalcodes'] = DB::all('SELECT email, attempts, expires_at, used_at, created_at FROM signature_codes WHERE offer_id = ? ORDER BY id', [$o['id']]);
            $data['offerten'][] = $o;
            self::collect($data, 'offer', (int) $o['id']);
        }
        foreach (DB::all('SELECT * FROM orders WHERE customer_id = ? ORDER BY id', [$id]) as $o) {
            $data['auftraege'][] = $strip($o);
            self::collect($data, 'order', (int) $o['id']);
        }
        foreach (DB::all('SELECT * FROM invoices WHERE customer_id = ? ORDER BY id', [$id]) as $i) {
            $i = $strip($i);
            $i['positionen'] = DB::all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY position', [$i['id']]);
            $i['zahlungseingaenge'] = DB::all('SELECT paid_on, amount, method, reference, note, created_by_name, created_at FROM invoice_payments WHERE invoice_id = ? ORDER BY id', [$i['id']]);
            $i['skonto_und_korrekturen'] = DB::all('SELECT * FROM invoice_adjustments WHERE invoice_id = ? ORDER BY id', [$i['id']]);
            $i['online_zahlungen'] = DB::all('SELECT provider, status, amount, currency, method, created_at, paid_at FROM payments WHERE invoice_id = ? ORDER BY id', [$i['id']]);
            $data['rechnungen'][] = $i;
            self::collect($data, 'invoice', (int) $i['id']);
        }
        $data['abos'] = DB::all('SELECT * FROM subscriptions WHERE customer_id = ?', [$id]);
        $data['e_mails'] = DB::all('SELECT created_at, subject, status, context FROM mail_log WHERE recipient = ? ORDER BY id', [$c['email']]);
        return $data;
    }

    private static function collect(array &$data, string $type, int $entityId): void
    {
        foreach (DB::all('SELECT created_at, event, status_from, status_to, note, customer_visible, actor_type, actor_name FROM status_history WHERE entity_type = ? AND entity_id = ? ORDER BY id', [$type, $entityId]) as $h) {
            $data['verlauf'][] = ['objekt' => "$type #$entityId"] + $h;
        }
        foreach (DB::all('SELECT original_name, mime, size, customer_visible, created_at FROM attachments WHERE entity_type = ? AND entity_id = ?', [$type, $entityId]) as $a) {
            $data['anhaenge'][] = ['objekt' => "$type #$entityId"] + $a;
        }
    }

    public static function sendExport(int $id, string $filenamePrefix): never
    {
        $data = self::export($id);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filenamePrefix . '-' . $data['kunde']['customer_no'] . '-' . date('Ymd') . '.json"');
        header('Cache-Control: private, no-store');
        header('Content-Length: ' . strlen((string) $json));
        echo $json;
        exit;
    }

    /* ---------------------------------------------------------------- Einschränkung */

    /**
     * Einschränkung: Daten, die nicht aufbewahrt werden müssen, werden gelöscht; Rechnungsdaten bleiben gesperrt
     * gespeichert. Der Kunde wird für neue Offerten, E-Mails und das Portal gesperrt.
     */
    public static function restrict(int $id, ?array $user): void
    {
        DB::tx(static function () use ($id): void {
            $c = DB::one('SELECT * FROM customers WHERE id = ? FOR UPDATE', [$id]);
            if (!$c || $c['anonymized_at']) {
                throw new ValidationException('Der Kunde wurde nicht gefunden oder ist bereits anonymisiert.');
            }
            DB::update('customers', [
                'phone' => null, 'mobile' => null, 'website' => null, 'notes' => null,
                'portal_enabled' => 0, 'restricted_at' => $c['restricted_at'] ?: now(), 'updated_at' => now(),
            ], 'id = :id', [':id' => $id]);
            DB::exec("DELETE FROM login_tokens WHERE subject_type = 'customer' AND subject_id = ?", [$id]);
            DB::exec('UPDATE orders SET access_info = NULL, download_url = NULL WHERE customer_id = ?', [$id]);
            DB::exec('UPDATE offers SET response_ip = NULL WHERE customer_id = ?', [$id]);
            DB::exec('DELETE c FROM signature_codes c JOIN offers o ON o.id = c.offer_id WHERE o.customer_id = ?', [$id]);
            DB::exec('DELETE FROM mail_log WHERE recipient = ?', [$c['email']]);
            DB::exec('DELETE FROM inquiries WHERE customer_id = ? OR email = ?', [$id, $c['email']]);
            DB::exec('DELETE FROM portal_messages WHERE customer_id = ?', [$id]);
        });
        Audit::log('customer_anonymized', 'Eingeschränkt (Aufbewahrungspflicht)', 'customer', $id, $user);
    }

    /* ---------------------------------------------------------------- Anonymisierung */

    public static function anonymize(int $id, ?array $user): void
    {
        $check = self::assess($id);
        if (!$check['can_anonymize']) {
            throw new ValidationException('Anonymisierung nicht möglich: ' . implode('; ', $check['reasons']) . '.');
        }
        $files = [];
        DB::tx(static function () use ($id, &$files): void {
            $c = DB::one('SELECT * FROM customers WHERE id = ? FOR UPDATE', [$id]);
            if (!$c || $c['anonymized_at']) {
                throw new ValidationException('Der Kunde wurde nicht gefunden oder ist bereits anonymisiert.');
            }
            $snap = json_encode(['id' => $id, 'customer_no' => $c['customer_no'], 'last_name' => self::ANON_NAME, 'country' => $c['country']], JSON_UNESCAPED_UNICODE);

            DB::update('customers', [
                'salutation' => null, 'first_name' => '', 'last_name' => self::ANON_NAME, 'company' => null,
                'street' => '', 'address_extra' => null, 'zip' => '', 'city' => '',
                'email' => self::email($id), 'phone' => null, 'mobile' => null, 'website' => null, 'vat_id' => null,
                'notes' => null, 'portal_enabled' => 0,
                'restricted_at' => $c['restricted_at'] ?: now(), 'anonymized_at' => now(), 'updated_at' => now(),
            ], 'id = :id', [':id' => $id]);
            DB::exec("DELETE FROM login_tokens WHERE subject_type = 'customer' AND subject_id = ?", [$id]);
            DB::exec('DELETE FROM portal_messages WHERE customer_id = ?', [$id]);

            foreach (DB::all('SELECT id, signature_file FROM offers WHERE customer_id = ?', [$id]) as $o) {
                if ($o['signature_file']) {
                    $files[] = $o['signature_file'];
                }
            }
            DB::exec('UPDATE offers SET customer_snapshot = ?, delivery_address = NULL, response_name = IF(response_name IS NULL, NULL, ?), response_note = NULL, response_ip = NULL,
                      signer_name = NULL, signer_email = NULL, signer_verified_email = NULL, signature_file = NULL WHERE customer_id = ?', [$snap, self::ANON_NAME, $id]);
            DB::exec('UPDATE orders SET ship_company = NULL, ship_name = NULL, ship_street = NULL, ship_extra = NULL, ship_zip = NULL, ship_city = NULL, ship_country = NULL,
                      delivery_email = NULL, access_info = NULL, download_url = NULL, tracking_number = NULL, tracking_url = NULL WHERE customer_id = ?', [$id]);
            DB::exec('UPDATE invoices SET customer_snapshot = ?, delivery_address = NULL, payment_note = NULL WHERE customer_id = ?', [$snap, $id]);
            DB::exec('UPDATE invoice_payments p JOIN invoices i ON i.id = p.invoice_id SET p.reference = NULL, p.note = NULL WHERE i.customer_id = ?', [$id]);
            DB::exec('UPDATE invoice_adjustments a JOIN invoices i ON i.id = a.invoice_id SET a.note = NULL WHERE i.customer_id = ?', [$id]);
            DB::exec('UPDATE payments p JOIN invoices i ON i.id = p.invoice_id SET p.note = NULL WHERE i.customer_id = ?', [$id]);
            DB::exec('DELETE c FROM signature_codes c JOIN offers o ON o.id = c.offer_id WHERE o.customer_id = ?', [$id]);
            DB::exec("UPDATE subscriptions SET title = 'Anonymisiert' WHERE customer_id = ?", [$id]);

            foreach (['offer' => 'offers', 'order' => 'orders', 'invoice' => 'invoices'] as $type => $table) {
                $ids = DB::all("SELECT id FROM $table WHERE customer_id = ?", [$id]);
                foreach ($ids as $row) {
                    AttachmentService::purge($type, (int) $row['id']);
                    DB::exec("UPDATE status_history SET note = NULL WHERE entity_type = ? AND entity_id = ? AND actor_type = 'customer'", [$type, $row['id']]);
                }
            }
            DB::exec("UPDATE status_history SET actor_name = ? WHERE actor_type = 'customer' AND actor_id = ?", [self::ANON_NAME, $id]);
            DB::exec('DELETE FROM mail_log WHERE recipient = ?', [$c['email']]);
        });
        foreach ($files as $f) {
            FileStore::deleteSignature($f);
        }
        Audit::log('customer_anonymized', 'Vollständig anonymisiert', 'customer', $id, $user);
    }

    public static function isBlocked(?array $customer): bool
    {
        return !empty($customer['restricted_at']) || !empty($customer['anonymized_at']);
    }
}
