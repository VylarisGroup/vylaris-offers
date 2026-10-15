<?php
declare(strict_types=1);

/** Öffentliche Offertenseite /o/{public_id}. */
final class PublicOfferController
{
    private function load(string $id): array
    {
        OfferService::sweepExpired();
        $offer = OfferService::findPublic($id);
        if (!$offer) {
            throw new HttpException(404, 'Diese Offerte existiert nicht oder wurde entfernt. Bitte prüfen Sie den Link.');
        }
        header('X-Robots-Tag: noindex, nofollow');
        return $offer;
    }

    public function show(string $id): void
    {
        $offer = $this->load($id);
        if (!Auth::user()) {
            OfferService::markViewed($offer);
        }
        $order = DB::one('SELECT id, number, status, area_type FROM orders WHERE offer_id = ?', [$offer['id']]);
        $successor = DB::one('SELECT public_id, number FROM offers WHERE parent_id = ? ORDER BY id DESC LIMIT 1', [$offer['id']]);
        $mailto = null;
        if (($_GET['mailto'] ?? '') === '1' && $offer['status'] === 'declined') {
            $mailto = $this->mailto($offer);
        }
        $files = AttachmentService::forEntity('offer', (int) $offer['id'], true);
        view('public/offer', compact('offer', 'order', 'successor', 'mailto', 'files'), 'public');
    }

    public function file(string $id, string $fid): void
    {
        $offer = $this->load($id);
        $att = AttachmentService::find((int) $fid);
        if (!$att || $att['entity_type'] !== 'offer' || (int) $att['entity_id'] !== (int) $offer['id'] || !$att['customer_visible']) {
            throw new HttpException(404, 'Anhang nicht gefunden.');
        }
        AttachmentService::send($att);
    }

    public function pdf(string $id): void
    {
        $offer = $this->load($id);
        PdfService::send(PdfService::offer($offer), 'Offerte-' . $offer['number'] . '.pdf');
    }

    public function accept(string $id): void
    {
        $offer = $this->load($id);
        $name = input_str('name', 190);
        $note = input_str('note', 2000) ?: null;
        $mode = SignatureService::mode($offer);
        if ($mode === 'strict') {
            $name = (string) $offer['signer_name'];   // Name ist fest vorgegeben
        }
        $errors = [];
        if ($name === '') {
            $errors[] = 'Bitte geben Sie Ihren Namen an.';
        }
        if (empty($_POST['confirm'])) {
            $errors[] = 'Bitte bestätigen Sie die Annahme der Offerte.';
        }

        $delivery = null;
        if (!empty($_POST['alt_delivery'])) {
            $delivery = [];
            foreach (['company', 'name', 'street', 'address_extra', 'zip', 'city', 'country'] as $f) {
                $delivery[$f] = mb_substr(trim((string) ($_POST['delivery'][$f] ?? '')), 0, 190);
            }
            $delivery['country'] = strtoupper(substr($delivery['country'] ?: 'CH', 0, 2));
            if ($delivery['name'] === '' || $delivery['street'] === '' || $delivery['zip'] === '' || $delivery['city'] === '') {
                $errors[] = 'Bitte füllen Sie die Lieferadresse vollständig aus.';
            }
        }

        $signatureFile = null;
        $sig = (string) ($_POST['signature'] ?? '');
        if ($sig !== '') {
            $signatureFile = FileStore::saveSignature($sig);
            if ($signatureFile === null) {
                $errors[] = 'Die Unterschrift konnte nicht gelesen werden. Bitte unterschreiben Sie erneut.';
            }
        } elseif ($mode !== 'none') {
            $errors[] = 'Für die Annahme dieser Offerte ist eine Unterschrift erforderlich.';
        }

        if (!$errors && $mode === 'strict') {
            // Zuletzt prüfen, damit der Einmalcode nur bei sonst gültigen Angaben verbraucht wird.
            try {
                SignatureService::consumeCode($offer, input_str('code', 20));
            } catch (ValidationException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors) {
            FileStore::deleteSignature($signatureFile);
            flash('error', implode(' ', $errors));
            redirect('o/' . $offer['public_id'] . '#annehmen');
        }

        try {
            $res = OfferService::accept($offer['public_id'], $name, $note, $delivery, $signatureFile, $mode === 'strict');
        } catch (ValidationException $e) {
            FileStore::deleteSignature($signatureFile);
            flash('error', $e->getMessage());
            redirect('o/' . $offer['public_id']);
        } catch (Throwable $e) {
            FileStore::deleteSignature($signatureFile);
            throw $e;
        }

        $fresh = OfferService::find($res['offer_id']);
        $order = DB::one('SELECT * FROM orders WHERE id = ?', [$res['order_id']]);
        Notifier::offerAccepted($fresh, $order);
        Webhooks::dispatch('offer.accepted', Webhooks::offerData($fresh));
        Webhooks::dispatch('order.created', Webhooks::orderData($order));

        flash('success', 'Vielen Dank! Ihre Annahme wurde erfasst. Auftrag ' . $order['number'] . ' wurde eröffnet.');
        redirect('o/' . $offer['public_id']);
    }

    /** Fordert den E-Mail-Bestätigungscode für die strikte Unterschrift an (JSON). */
    public function sendCode(string $id): void
    {
        $offer = $this->load($id);
        [$ok, $msg] = SignatureService::sendCode($offer);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function decline(string $id): void
    {
        $this->doDecline($id, false);
    }

    public function requestNew(string $id): void
    {
        $this->doDecline($id, true);
    }

    private function doDecline(string $id, bool $requestNew): void
    {
        $offer = $this->load($id);
        try {
            OfferService::decline($offer['public_id'], input_str('name', 190), input_str('reason', 2000) ?: null, $requestNew);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
            redirect('o/' . $offer['public_id']);
        }
        Notifier::offerDeclined($declined = OfferService::find((int) $offer['id']), $requestNew);
        Webhooks::dispatch('offer.declined', Webhooks::offerData($declined));

        if ($requestNew) {
            flash('success', 'Die bisherige Offerte wurde abgelehnt. Ihr E-Mail-Programm öffnet sich nun für Ihre Anfrage.');
            redirect('o/' . $offer['public_id'] . '?mailto=1');
        }
        flash('success', 'Die Offerte wurde abgelehnt. Vielen Dank für Ihre Rückmeldung.');
        redirect('o/' . $offer['public_id']);
    }

    private function mailto(array $offer): ?string
    {
        $to = Settings::companyEmail();
        if (!valid_email($to)) {
            return null;
        }
        $subject = 'Anfrage neue Offerte – Bezug ' . $offer['number'];
        $body = "Guten Tag\n\nIch bitte Sie um eine neue Offerte als Ersatz für die Offerte {$offer['number']} („{$offer['title']}“).\n\n"
            . "Meine Wünsche / Änderungen:\n\n\n"
            . "Freundliche Grüsse\n" . customer_person($offer['customer']) . "\n" . ($offer['customer']['company'] ?? '');
        return 'mailto:' . rawurlencode($to) . '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode(trim($body));
    }
}
