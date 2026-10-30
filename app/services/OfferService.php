<?php
/**
 * Offerten: Erstellen (inkl. „neue Offerte auf Basis der alten“), Lesen, Löschen,
 * Annehmen und Ablehnen. Offerten sind nach dem Erstellen unveränderlich.
 */
declare(strict_types=1);

final class OfferService
{
    public const DISCOUNT_TYPES = ['none', 'percent', 'amount'];

    /* ---------------------------------------------------------------- Lesen */

    public static function find(int $id): ?array
    {
        return self::hydrate(DB::one('SELECT * FROM offers WHERE id = ?', [$id]));
    }

    public static function findPublic(string $publicId): ?array
    {
        if (!preg_match('/^[A-Za-z0-9]{24}$/', $publicId)) {
            return null;
        }
        return self::hydrate(DB::one('SELECT * FROM offers WHERE public_id = ?', [$publicId]));
    }

    private static function hydrate(?array $o): ?array
    {
        if (!$o) {
            return null;
        }
        $o['customer'] = json_decode((string) $o['customer_snapshot'], true) ?: [];
        $o['delivery'] = $o['delivery_address'] ? (json_decode((string) $o['delivery_address'], true) ?: null) : null;
        $o['taxes'] = json_decode((string) $o['tax_breakdown'], true) ?: [];
        $o['items'] = DB::all('SELECT * FROM offer_items WHERE offer_id = ? ORDER BY position', [$o['id']]);
        return $o;
    }

    public static function url(array $offer): string
    {
        return url('o/' . $offer['public_id']);
    }

    public static function isExpired(array $o): bool
    {
        return $o['valid_until'] !== null && $o['valid_until'] < date('Y-m-d');
    }

    /** Markiert abgelaufene, offene Offerten als „abgelaufen“ (mit Historie). */
    public static function sweepExpired(): void
    {
        $ids = DB::all("SELECT id FROM offers WHERE status = 'open' AND valid_until IS NOT NULL AND valid_until < ?", [date('Y-m-d')]);
        foreach ($ids as $row) {
            DB::tx(static function () use ($row): void {
                $n = DB::exec("UPDATE offers SET status = 'expired' WHERE id = ? AND status = 'open'", [$row['id']]);
                if ($n === 1) {
                    History::add('offer', (int) $row['id'], 'status', 'open', 'expired', 'Gültigkeitsdatum überschritten', true);
                }
            });
        }
    }

    /* ---------------------------------------------------------------- Erstellen */

    /**
     * Validiert und berechnet eine Offerte aus Formulardaten.
     * Steuersätze werden serverseitig aus den Vorlagen bestimmt, nie aus dem Formular übernommen.
     */
    public static function fromInput(array $in): array
    {
        $errors = [];

        $customer = null;
        $customerId = (int) ($in['customer_id'] ?? 0);
        if ($customerId > 0) {
            $customer = DB::one('SELECT * FROM customers WHERE id = ?', [$customerId]);
        }
        if (!$customer) {
            $errors['customer_id'] = 'Bitte wählen Sie einen Kunden.';
        }

        $area = null;
        $areaId = (int) ($in['product_area_id'] ?? 0);
        if ($areaId > 0) {
            $area = DB::one('SELECT * FROM product_areas WHERE id = ?', [$areaId]);
            if (!$area) {
                $errors['product_area_id'] = 'Der Produktbereich existiert nicht.';
            }
        } else {
            $errors['product_area_id'] = 'Bitte wählen Sie einen Produktbereich.';
        }
        $areaType = $area['type'] ?? 'digital';

        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Bitte geben Sie einen Titel ein.';
        } elseif (mb_strlen($title) > 190) {
            $errors['title'] = 'Der Titel darf höchstens 190 Zeichen lang sein.';
        }

        $validUntil = null;
        if (empty($in['no_expiry']) && trim((string) ($in['valid_until'] ?? '')) === '') {
            $errors['valid_until'] = 'Bitte ein Ablaufdatum wählen oder «Ohne Ablaufdatum» aktivieren.';
        }
        if (empty($in['no_expiry']) && trim((string) ($in['valid_until'] ?? '')) !== '') {
            $d = DateTime::createFromFormat('Y-m-d', (string) $in['valid_until']);
            if (!$d || $d->format('Y-m-d') !== $in['valid_until']) {
                $errors['valid_until'] = 'Ungültiges Datum.';
            } elseif ($in['valid_until'] < date('Y-m-d')) {
                $errors['valid_until'] = 'Das Ablaufdatum darf nicht in der Vergangenheit liegen.';
            } else {
                $validUntil = $in['valid_until'];
            }
        }

        if ($customer && CustomerPrivacy::isBlocked($customer)) {
            $errors['customer_id'] = 'Für diesen Kunden ist die Verarbeitung eingeschränkt (Datenschutz). Es können keine neuen Offerten erstellt werden.';
        }

        // Steuern
        $defaultTax = TaxService::find((int) ($in['tax_template_id'] ?? 0));
        if (!$defaultTax) {
            $errors['tax_template_id'] = 'Bitte wählen Sie eine Steuervorlage.';
        }
        $resolveTax = static function (?array $tpl) use ($defaultTax): array {
            $tpl ??= $defaultTax;
            return $tpl ? [(int) $tpl['id'], (float) $tpl['rate'], TaxService::display($tpl)] : [null, 0.0, ''];
        };

        // Positionen
        $items = [];
        $rawItems = is_array($in['items'] ?? null) ? array_values($in['items']) : [];
        foreach ($rawItems as $i => $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $itTitle = trim((string) ($raw['title'] ?? ''));
            $desc = trim((string) ($raw['description'] ?? ''));
            $qtyVal = parse_number($raw['quantity'] ?? 1);
            $price = parse_number($raw['unit_price'] ?? 0);
            if ($itTitle === '' && $desc === '' && $price == 0.0) {
                continue; // leere Zeile ignorieren
            }
            $n = count($items) + 1;
            if ($itTitle === '') {
                $errors["items.$i.title"] = "Position $n: Titel fehlt.";
            }
            if ($qtyVal <= 0) {
                $errors["items.$i.quantity"] = "Position $n: Die Menge muss grösser als 0 sein.";
            }
            if (abs($price) > 9999999999) {
                $errors["items.$i.unit_price"] = "Position $n: Preis ist zu hoch.";
            }
            $dType = (string) ($raw['discount_type'] ?? 'none');
            $dType = in_array($dType, self::DISCOUNT_TYPES, true) ? $dType : 'none';
            $dVal = max(0.0, parse_number($raw['discount_value'] ?? 0));
            if ($dType === 'percent' && $dVal > 100) {
                $errors["items.$i.discount_value"] = "Position $n: Rabatt höchstens 100 %.";
            }
            if ($dVal == 0.0) {
                $dType = 'none';
            }
            $taxSel = (string) ($raw['tax'] ?? 'default');
            $tpl = $taxSel === 'default' ? null : TaxService::find((int) $taxSel);
            [$taxId, $taxRate, $taxLabel] = $resolveTax($tpl);

            $items[] = [
                'position'        => $n,
                'title'           => mb_substr($itTitle, 0, 190),
                'description'     => mb_substr($desc, 0, 5000),
                'quantity'        => round($qtyVal, 3),
                'unit'            => mb_substr(trim((string) ($raw['unit'] ?? '')), 0, 30),
                'unit_price'      => Calc::r2($price),
                'discount_type'   => $dType,
                'discount_value'  => Calc::r2($dVal),
                'tax_template_id' => $taxId,
                'tax_rate'        => $taxRate,
                'tax_label'       => $taxLabel,
                'image'           => ItemImage::clean($raw['image'] ?? null),
            ];
        }
        if (!$items) {
            $errors['items'] = 'Bitte erfassen Sie mindestens eine Position.';
        }

        // Rabatt gesamt
        $discType = (string) ($in['discount_type'] ?? 'none');
        $discType = in_array($discType, self::DISCOUNT_TYPES, true) ? $discType : 'none';
        $discVal = max(0.0, parse_number($in['discount_value'] ?? 0));
        if ($discType === 'percent' && $discVal > 100) {
            $errors['discount_value'] = 'Der Rabatt darf höchstens 100 % betragen.';
        }
        if ($discVal == 0.0) {
            $discType = 'none';
        }

        // Versand / Lieferung
        $shippingMethod = null;
        $shippingCost = 0.0;
        $delivery = null;
        if ($areaType === 'physical') {
            $shippingMethod = mb_substr(trim((string) ($in['shipping_method'] ?? '')), 0, 120) ?: null;
            $shippingCost = max(0.0, parse_number($in['shipping_cost'] ?? 0));
        }
        {
            if (!empty($in['alt_delivery'])) {
                $delivery = [];
                foreach (['company', 'name', 'street', 'address_extra', 'zip', 'city', 'country'] as $f) {
                    $delivery[$f] = mb_substr(trim((string) ($in['delivery'][$f] ?? '')), 0, 190);
                }
                $delivery['country'] = strtoupper(substr($delivery['country'] ?: 'CH', 0, 2));
                if ($delivery['name'] === '' || $delivery['street'] === '' || $delivery['zip'] === '' || $delivery['city'] === '') {
                    $errors['delivery'] = 'Bitte füllen Sie die abweichende Lieferadresse vollständig aus (Name, Strasse, PLZ, Ort).';
                }
            }
        }
        $deliveryInfo = mb_substr(trim((string) ($in['delivery_info'] ?? '')), 0, 255) ?: null;

        $sig = SignatureService::fromInput($in, $customer ?: [], $errors);

        if ($errors) {
            throw new ValidationException('Bitte prüfen Sie die markierten Felder.', $errors);
        }

        [, $shipRate, $shipLabel] = $resolveTax(null);
        $calc = Calc::compute($items, [
            'discount_type'      => $discType,
            'discount_value'     => $discVal,
            'shipping_cost'      => $shippingCost,
            'shipping_tax_rate'  => $shipRate,
            'shipping_tax_label' => $shipLabel,
            'round_005'          => Settings::get('currency') === 'CHF' && Settings::get('round_005') === '1',
        ]);

        $snapshot = array_intersect_key($customer, array_flip([
            'id', 'customer_no', 'salutation', 'first_name', 'last_name', 'company', 'street', 'address_extra',
            'zip', 'city', 'country', 'email', 'phone', 'vat_id',
        ]));

        return [
            'offer' => $sig + [
                'customer_id'        => (int) $customer['id'],
                'product_area_id'    => $area ? (int) $area['id'] : null,
                'area_type'          => $areaType,
                'title'              => $title,
                'template_id'        => DocTemplate::validId('offer', $in['template_id'] ?? null),
                'intro'              => mb_substr(trim((string) ($in['intro'] ?? '')), 0, 10000),
                'footer'             => mb_substr(trim((string) ($in['footer'] ?? '')), 0, 10000),
                'currency'           => (string) Settings::get('currency', 'CHF'),
                'valid_until'        => $validUntil,
                'customer_snapshot'  => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'tax_template_id'    => $defaultTax['id'] ?? null,
                'tax_exempt'         => 0,
                'tax_exempt_reason'  => null,
                'discount_type'      => $discType,
                'discount_value'     => Calc::r2($discVal),
                'shipping_method'    => $shippingMethod,
                'shipping_cost'      => $calc['shipping_cost'],
                'shipping_tax_rate'  => $shipRate,
                'shipping_tax_label' => $shipLabel ?: null,
                'delivery_info'      => $deliveryInfo,
                'delivery_address'   => $delivery ? json_encode($delivery, JSON_UNESCAPED_UNICODE) : null,
                'subtotal'           => $calc['subtotal'],
                'discount_amount'    => $calc['discount_amount'],
                'net_total'          => $calc['net_total'],
                'tax_total'          => $calc['tax_total'],
                'rounding'           => $calc['rounding'],
                'total'              => $calc['total'],
                'tax_breakdown'      => json_encode($calc['tax_breakdown'], JSON_UNESCAPED_UNICODE),
            ],
            'items'    => $calc['items'],
            'customer' => $customer,
        ];
    }

    /** Speichert eine neue Offerte. Ist $parentId gesetzt, wird die alte (offene) Offerte als „ersetzt“ markiert. */
    public static function create(array $data, array $items, array $user, ?int $parentId = null): int
    {
        return DB::tx(static function () use ($data, $items, $user, $parentId): int {
            $offer = $data;
            $offer['number'] = Counter::offerNumber();
            do {
                $offer['public_id'] = random_id(24);
            } while (DB::val('SELECT 1 FROM offers WHERE public_id = ?', [$offer['public_id']]));
            $offer['status'] = 'open';
            $offer['created_by'] = (int) $user['id'];
            $offer['created_by_name'] = mb_substr((string) ($user['name'] ?? ''), 0, 120) ?: null;
            $offer['created_at'] = now();
            $offer['parent_id'] = null;

            $parent = null;
            if ($parentId) {
                $parent = DB::one('SELECT id, number, status FROM offers WHERE id = ? FOR UPDATE', [$parentId]);
                $offer['parent_id'] = $parent ? (int) $parent['id'] : null;
            }

            $id = DB::insert('offers', $offer);
            foreach ($items as $it) {
                DB::insert('offer_items', [
                    'offer_id'        => $id,
                    'position'        => $it['position'],
                    'title'           => $it['title'],
                    'description'     => $it['description'] ?: null,
                    'quantity'        => $it['quantity'],
                    'unit'            => $it['unit'] ?: null,
                    'unit_price'      => $it['unit_price'],
                    'discount_type'   => $it['discount_type'],
                    'discount_value'  => $it['discount_value'],
                    'tax_template_id' => $it['tax_template_id'],
                    'tax_rate'        => $it['tax_rate'],
                    'tax_label'       => $it['tax_label'],
                    'image'           => $it['image'] ?? null,
                    'line_subtotal'   => $it['line_subtotal'],
                    'line_discount'   => $it['line_discount'],
                    'line_total'      => $it['line_total'],
                ]);
            }

            $note = $parent ? 'Auf Basis von ' . $parent['number'] : null;
            History::add('offer', $id, 'created', null, 'open', $note, false, $user);

            if ($parent && $parent['status'] === 'open') {
                DB::exec("UPDATE offers SET status = 'superseded' WHERE id = ?", [$parent['id']]);
                History::add('offer', (int) $parent['id'], 'status', 'open', 'superseded', 'Ersetzt durch ' . $offer['number'], true, $user);
            }
            return $id;
        });
    }

    /** Formularwerte, um eine neue Offerte auf Basis einer bestehenden vorzubereiten. */
    public static function toFormData(array $o): array
    {
        $items = [];
        foreach ($o['items'] as $it) {
            $items[] = [
                'title'          => $it['title'],
                'description'    => $it['description'],
                'quantity'       => qty($it['quantity']),
                'unit'           => $it['unit'],
                'unit_price'     => number_format((float) $it['unit_price'], 2, '.', ''),
                'image'          => $it['image'] ?? '',
                'discount_type'  => $it['discount_type'],
                'discount_value' => (float) $it['discount_value'] ? number_format((float) $it['discount_value'], 2, '.', '') : '',
                'tax'            => $it['tax_template_id'] && $it['tax_template_id'] != $o['tax_template_id'] ? (string) $it['tax_template_id'] : 'default',
            ];
        }
        $validDays = (int) Settings::get('offer_validity_days', 30);
        return [
            'customer_id'     => $o['customer_id'],
            'product_area_id' => $o['product_area_id'],
            'title'           => $o['title'],
            'template_id'     => $o['template_id'] ?? null,
            'intro'           => $o['intro'],
            'footer'          => $o['footer'],
            'valid_until'     => $validDays > 0 ? date('Y-m-d', strtotime("+$validDays days")) : '',
            'no_expiry'       => $o['valid_until'] === null ? '1' : '',
            'signature_mode'  => $o['signature_mode'] ?? 'none',
            'signer_name'     => $o['signer_name'] ?? '',
            'signer_email'    => $o['signer_email'] ?? '',
            'tax_template_id' => $o['tax_template_id'],
            'discount_type'   => $o['discount_type'],
            'discount_value'  => (float) $o['discount_value'] ? number_format((float) $o['discount_value'], 2, '.', '') : '',
            'shipping_method' => $o['shipping_method'],
            'shipping_cost'   => number_format((float) $o['shipping_cost'], 2, '.', ''),
            'delivery_info'   => $o['delivery_info'],
            'alt_delivery'    => $o['delivery'] ? '1' : '',
            'delivery'        => $o['delivery'] ?: [],
            'items'           => $items,
        ];
    }

    /* ---------------------------------------------------------------- Löschen */

    public static function delete(int $id, array $user): void
    {
        $sigFile = null;
        DB::tx(static function () use ($id, &$sigFile): void {
            $o = DB::one('SELECT id, number, signature_file FROM offers WHERE id = ? FOR UPDATE', [$id]);
            if (!$o) {
                throw new HttpException(404, 'Die Offerte existiert nicht.');
            }
            $orderId = (int) DB::val('SELECT id FROM orders WHERE offer_id = ?', [$id]);
            if ($orderId) {
                OrderService::deleteInTx($orderId);   // wirft, falls bereits Rechnungen existieren
            }
            $sigFile = $o['signature_file'] ?: null;
            AttachmentService::purge('offer', $id);
            DB::exec("DELETE FROM status_history WHERE entity_type = 'offer' AND entity_id = ?", [$id]);
            DB::exec('DELETE FROM offers WHERE id = ?', [$id]);   // Einmalcodes (signature_codes) fallen per CASCADE weg
        });
        if ($sigFile) {
            FileStore::deleteSignature($sigFile);   // Unterschrift ist personenbezogen – nicht als Datei zurücklassen
        }
    }

    /* ---------------------------------------------------------------- Kundenreaktionen */

    public static function markViewed(array $o): void
    {
        if ($o['viewed_at'] === null) {
            $n = DB::exec('UPDATE offers SET viewed_at = ? WHERE id = ? AND viewed_at IS NULL', [now(), $o['id']]);
            if ($n === 1) {
                History::add('offer', (int) $o['id'], 'viewed', null, null, null, false, null);
            }
        }
    }

    /**
     * Annahme einer Offerte inkl. Auftragserstellung – vollständig in einer Transaktion
     * mit Zeilensperre, damit eine Offerte nie doppelt angenommen werden kann.
     */
    public static function accept(string $publicId, string $name, ?string $note, ?array $delivery, ?string $signatureFile = null, bool $emailVerified = false): array
    {
        return DB::tx(static function () use ($publicId, $name, $note, $delivery, $signatureFile, $emailVerified): array {
            $o = DB::one('SELECT * FROM offers WHERE public_id = ? FOR UPDATE', [$publicId]);
            if (!$o) {
                throw new HttpException(404, 'Die Offerte wurde nicht gefunden.');
            }
            self::assertOpen($o);
            $mode = SignatureService::mode($o);
            if ($mode === 'strict') {
                if (!$emailVerified || !$signatureFile) {
                    throw new ValidationException('Die Annahme erfordert die bestätigte Unterschrift von ' . $o['signer_name'] . '.');
                }
                $name = (string) $o['signer_name'];
            } elseif ($mode === 'open' && !$signatureFile) {
                throw new ValidationException('Für die Annahme dieser Offerte ist eine Unterschrift erforderlich.');
            }

            $deliveryJson = $delivery ? json_encode($delivery, JSON_UNESCAPED_UNICODE) : $o['delivery_address'];
            $o['delivery_address'] = $deliveryJson;
            DB::update('offers', [
                'delivery_address' => $deliveryJson,
                'signer_verified_at'    => $mode === 'strict' ? now() : null,
                'signer_verified_email' => $mode === 'strict' ? $o['signer_email'] : null,
                'status'        => 'accepted',
                'responded_at'  => now(),
                'response_name' => mb_substr($name, 0, 190),
                'response_note' => $note ?: null,
                'response_ip'   => client_ip(),
                'signature_file' => $signatureFile,
            ], 'id = :id', [':id' => $o['id']]);

            $actor = ['id' => $o['customer_id'], 'name' => $name];
            History::add('offer', (int) $o['id'], 'status', 'open', 'accepted', $note ? 'Bemerkung: ' . $note : null, true, $actor, 'customer');

            $orderId = OrderService::createFromOffer($o, $delivery, $actor);
            return ['offer_id' => (int) $o['id'], 'order_id' => $orderId];
        });
    }

    public static function decline(string $publicId, string $name, ?string $reason, bool $requestNew): array
    {
        return DB::tx(static function () use ($publicId, $name, $reason, $requestNew): array {
            $o = DB::one('SELECT * FROM offers WHERE public_id = ? FOR UPDATE', [$publicId]);
            if (!$o) {
                throw new HttpException(404, 'Die Offerte wurde nicht gefunden.');
            }
            self::assertOpen($o);
            $note = $requestNew ? 'Neue Offerte angefragt' . ($reason ? ': ' . $reason : '') : ($reason ?: null);
            DB::update('offers', [
                'status'        => 'declined',
                'responded_at'  => now(),
                'response_name' => mb_substr($name, 0, 190) ?: null,
                'response_note' => $note,
                'response_ip'   => client_ip(),
            ], 'id = :id', [':id' => $o['id']]);
            $actor = ['id' => $o['customer_id'], 'name' => $name ?: 'Kunde'];
            History::add('offer', (int) $o['id'], 'status', 'open', 'declined', $note, true, $actor, 'customer');
            return $o;
        });
    }

    private static function assertOpen(array $o): void
    {
        if ($o['status'] === 'open' && self::isExpired($o)) {
            // Der Status wird vor der Transaktion durch sweepExpired() gespeichert.
            throw new ValidationException('Diese Offerte ist am ' . fmt_date($o['valid_until']) . ' abgelaufen und kann nicht mehr angenommen werden.');
        }
        if ($o['status'] !== 'open') {
            throw new ValidationException(match ($o['status']) {
                'accepted'   => 'Diese Offerte wurde bereits angenommen.',
                'declined'   => 'Diese Offerte wurde bereits abgelehnt.',
                'expired'    => 'Diese Offerte ist abgelaufen.',
                'superseded' => 'Diese Offerte wurde durch eine neuere Version ersetzt.',
                default      => 'Diese Offerte kann nicht mehr bearbeitet werden.',
            });
        }
    }
}

