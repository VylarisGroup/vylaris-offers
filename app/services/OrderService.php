<?php
/**
 * Aufträge: Erstellung aus angenommener Offerte, Statuswechsel, Versand und digitale Lieferung.
 */
declare(strict_types=1);

final class OrderService
{
    public static function find(int $id): ?array
    {
        return DB::one(
            'SELECT o.*, c.first_name, c.last_name, c.company, c.email AS customer_email, c.customer_no,
                    f.public_id AS offer_public_id, f.number AS offer_number, p.name AS area_name, p.icon AS area_icon,
                    COALESCE(ru.name, o.responsible_name) AS responsible_label, COALESCE(iu.name, f.created_by_name) AS issuer_label
             FROM orders o
             JOIN customers c ON c.id = o.customer_id
             JOIN offers f ON f.id = o.offer_id
             LEFT JOIN users ru ON ru.id = o.responsible_id
             LEFT JOIN users iu ON iu.id = f.created_by
             LEFT JOIN product_areas p ON p.id = o.product_area_id
             WHERE o.id = ?',
            [$id]
        );
    }

    /** Wird innerhalb der Annahme-Transaktion aufgerufen. */
    public static function createFromOffer(array $offer, ?array $delivery, array $actor): int
    {
        $customer = json_decode((string) $offer['customer_snapshot'], true) ?: [];
        $offerDelivery = $offer['delivery_address'] ? json_decode((string) $offer['delivery_address'], true) : null;
        $addr = $delivery ?: $offerDelivery;

        $data = [
            'number'          => Counter::orderNumber(),
            'offer_id'        => (int) $offer['id'],
            'customer_id'     => (int) $offer['customer_id'],
            'product_area_id' => $offer['product_area_id'],
            'area_type'       => $offer['area_type'],
            'title'           => $offer['title'],
            'currency'        => $offer['currency'],
            'status'          => 'new',
            'net_total'       => $offer['net_total'],
            'tax_total'       => $offer['tax_total'],
            'total'           => $offer['total'],
            'created_at'      => now(),
        ] + Responsibility::defaultFor($offer);

        if ($offer['area_type'] === 'physical') {
            $data += [
                'shipping_method' => $offer['shipping_method'],
                'shipping_cost'   => $offer['shipping_cost'],
                'shipping_status' => 'pending',
                'ship_company'    => $addr ? ($addr['company'] ?? null) : ($customer['company'] ?? null),
                'ship_name'       => $addr ? ($addr['name'] ?? null) : trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
                'ship_street'     => $addr['street'] ?? ($customer['street'] ?? null),
                'ship_extra'      => $addr ? ($addr['address_extra'] ?? null) : ($customer['address_extra'] ?? null),
                'ship_zip'        => $addr['zip'] ?? ($customer['zip'] ?? null),
                'ship_city'       => $addr['city'] ?? ($customer['city'] ?? null),
                'ship_country'    => $addr['country'] ?? ($customer['country'] ?? null),
            ];
        } else {
            $data['delivery_email'] = $customer['email'] ?? null;
            if ($addr) {   // abweichende Adresse (Offerte oder Kunde bei Annahme) auch bei digitalen Aufträgen festhalten
                $data += ['ship_company' => $addr['company'] ?? null, 'ship_name' => $addr['name'] ?? null, 'ship_street' => $addr['street'] ?? null,
                          'ship_extra' => $addr['address_extra'] ?? null, 'ship_zip' => $addr['zip'] ?? null, 'ship_city' => $addr['city'] ?? null,
                          'ship_country' => $addr['country'] ?? null];
            }
        }

        $id = DB::insert('orders', $data);
        History::add('order', $id, 'created', null, 'new', 'Aus Offerte ' . $offer['number'], true, $actor, 'customer');
        return $id;
    }

    /** Löscht einen Auftrag samt Verlauf und Anhängen – nur ohne Rechnungen (Nummernfolge/Aufbewahrungspflicht). */
    public static function delete(int $id): void
    {
        DB::tx(static function () use ($id): void {
            self::deleteInTx($id);
        });
    }

    public static function deleteInTx(int $id): void
    {
        $o = DB::one('SELECT id, number FROM orders WHERE id = ? FOR UPDATE', [$id]);
        if (!$o) {
            throw new HttpException(404, 'Der Auftrag existiert nicht.');
        }
        if (DB::val('SELECT 1 FROM invoices WHERE order_id = ? LIMIT 1', [$id]) || DB::val('SELECT 1 FROM subscriptions WHERE order_id = ? LIMIT 1', [$id])) {
            throw new ValidationException('Zu Auftrag ' . $o['number'] . ' existieren Rechnungen oder Abos. Rechnungen müssen wegen der fortlaufenden Nummerierung und der Aufbewahrungspflicht erhalten bleiben – stornieren Sie stattdessen den Auftrag.');
        }
        AttachmentService::purge('order', $id);
        DB::exec("DELETE FROM status_history WHERE entity_type = 'order' AND entity_id = ?", [$id]);
        DB::exec('DELETE FROM orders WHERE id = ?', [$id]);
    }

    public static function setStatus(array $order, string $status, ?string $note, bool $visible, array $user): bool
    {
        if (!isset(Status::ORDER[$status])) {
            throw new ValidationException('Unbekannter Status.');
        }
        return DB::tx(static function () use ($order, $status, $note, $visible, $user): bool {
            $cur = DB::one('SELECT status, area_type FROM orders WHERE id = ? FOR UPDATE', [$order['id']]);
            if ($cur['status'] === $status) {
                if ($note) {
                    History::add('order', (int) $order['id'], 'note', null, null, $note, $visible, $user);
                }
                return false;
            }
            $upd = ['status' => $status, 'updated_at' => now()];
            $upd['completed_at'] = $status === 'completed' ? now() : null;
            if ($status === 'fulfilled' && $cur['area_type'] === 'digital') {
                $upd['delivered_at'] = now();
            }
            DB::update('orders', $upd, 'id = :id', [':id' => $order['id']]);
            History::add('order', (int) $order['id'], 'status', $cur['status'], $status, $note, $visible, $user);
            return true;
        });
    }

    /**
     * Aktualisiert Versand- bzw. Lieferdaten. Gibt eine Beschreibung der für den Kunden
     * relevanten Änderung zurück (oder null).
     */
    public static function updateFulfillment(array $order, array $in, array $user): ?string
    {
        return DB::tx(static function () use ($order, $in, $user): ?string {
            $cur = DB::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$order['id']]);
            $upd = ['updated_at' => now()];
            $customerNote = null;

            if ($cur['area_type'] === 'physical') {
                foreach (['shipping_method' => 120, 'ship_company' => 190, 'ship_name' => 190, 'ship_street' => 190, 'ship_extra' => 190,
                          'ship_zip' => 20, 'ship_city' => 120, 'carrier' => 80, 'tracking_number' => 120] as $f => $max) {
                    $upd[$f] = mb_substr(trim((string) ($in[$f] ?? '')), 0, $max) ?: null;
                }
                $upd['ship_country'] = strtoupper(substr(trim((string) ($in['ship_country'] ?? 'CH')), 0, 2)) ?: null;
                $url = trim((string) ($in['tracking_url'] ?? ''));
                if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                    throw new ValidationException('Der Tracking-Link muss mit http:// oder https:// beginnen.');
                }
                $upd['tracking_url'] = $url ?: null;
                $upd['shipping_cost'] = max(0, parse_number($in['shipping_cost'] ?? $cur['shipping_cost']));

                $ship = (string) ($in['shipping_status'] ?? $cur['shipping_status']);
                if (!isset(Status::SHIPPING[$ship])) {
                    throw new ValidationException('Unbekannter Versandstatus.');
                }
                $upd['shipping_status'] = $ship;
                if ($ship !== $cur['shipping_status']) {
                    if ($ship === 'shipped' && !$cur['shipped_at']) {
                        $upd['shipped_at'] = now();
                    }
                    if ($ship === 'delivered') {
                        $upd['delivered_at'] = now();
                    }
                    $note = $upd['tracking_number'] ? 'Sendungsnummer: ' . $upd['tracking_number'] . ($upd['carrier'] ? ' (' . $upd['carrier'] . ')' : '') : null;
                    History::add('order', (int) $cur['id'], 'shipping', $cur['shipping_status'], $ship, $note, true, $user);
                    $customerNote = 'Versandstatus: ' . Status::label('shipping', $ship) . ($note ? "\n" . $note : '');

                    // Auftragsstatus automatisch nachführen.
                    if (in_array($ship, ['shipped', 'delivered'], true) && in_array($cur['status'], ['new', 'in_progress'], true)) {
                        $upd['status'] = 'fulfilled';
                        History::add('order', (int) $cur['id'], 'status', $cur['status'], 'fulfilled', null, true, $user);
                    }
                } elseif ($upd['tracking_number'] && $upd['tracking_number'] !== $cur['tracking_number']) {
                    History::add('order', (int) $cur['id'], 'note', null, null, 'Sendungsnummer: ' . $upd['tracking_number'], true, $user);
                    $customerNote = 'Neue Sendungsnummer: ' . $upd['tracking_number'];
                }
            } else {
                $email = trim((string) ($in['delivery_email'] ?? ''));
                if ($email !== '' && !valid_email($email)) {
                    throw new ValidationException('Bitte geben Sie eine gültige Liefer-E-Mail-Adresse ein.');
                }
                $url = trim((string) ($in['download_url'] ?? ''));
                if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                    throw new ValidationException('Der Download-Link muss mit http:// oder https:// beginnen.');
                }
                $upd['delivery_email'] = $email ?: null;
                $upd['download_url'] = mb_substr($url, 0, 500) ?: null;
                $upd['access_info'] = mb_substr(trim((string) ($in['access_info'] ?? '')), 0, 10000) ?: null;

                $changed = $upd['download_url'] !== $cur['download_url'] || $upd['access_info'] !== $cur['access_info'];
                if ($changed && ($upd['download_url'] || $upd['access_info'])) {
                    History::add('order', (int) $cur['id'], 'delivery', null, null, null, true, $user);
                    $customerNote = 'Ihre digitale Lieferung steht im Kundenportal bereit.';
                }
                if (!empty($in['mark_delivered']) && in_array($cur['status'], ['new', 'in_progress'], true)) {
                    $upd['status'] = 'fulfilled';
                    $upd['delivered_at'] = now();
                    History::add('order', (int) $cur['id'], 'status', $cur['status'], 'fulfilled', null, true, $user);
                    $customerNote = 'Ihre Bestellung wurde bereitgestellt. Die Zugangsdaten finden Sie im Kundenportal.';
                }
            }

            $msg = trim((string) ($in['customer_message'] ?? ''));
            $upd['customer_message'] = mb_substr($msg, 0, 5000) ?: null;
            $upd['internal_note'] = mb_substr(trim((string) ($in['internal_note'] ?? '')), 0, 10000) ?: null;

            DB::update('orders', $upd, 'id = :id', [':id' => $cur['id']]);
            return $customerNote;
        });
    }
}
