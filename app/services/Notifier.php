<?php
/**
 * Automatische E-Mail-Benachrichtigungen. Fehler beim Versand werden protokolliert,
 * unterbrechen aber nie den eigentlichen Ablauf.
 */
declare(strict_types=1);

final class Notifier
{
    public static ?string $lastError = null;

    private static function send(string $to, string $subject, string $title, string $bodyHtml, ?string $ctaUrl = null, ?string $ctaLabel = null, string $context = '', ?string $replyTo = null, array $attachments = []): bool
    {
        self::$lastError = null;
        if (!valid_email($to)) {
            self::$lastError = 'Keine gültige Empfängeradresse.';
            return false;
        }
        if (DB::val('SELECT 1 FROM customers WHERE email = ? AND (restricted_at IS NOT NULL OR anonymized_at IS NOT NULL)', [mb_strtolower($to)])) {
            self::$lastError = 'Die Verarbeitung dieses Kunden ist eingeschränkt (Datenschutz) – es werden keine E-Mails versendet.';
            return false;
        }
        try {
            $html = View::partial('emails/layout', compact('title', 'bodyHtml', 'ctaUrl', 'ctaLabel'));
            (new Mailer())->send($to, $subject, $html, null, $context, $replyTo, $attachments);
            return true;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            ErrorHandler::log($e);
            return false;
        }
    }

    /** PDF-Anhang (falls aktiviert). Schlägt die Erzeugung fehl, wird ohne Anhang gesendet. */
    private static function pdfAttachment(callable $build, string $name): array
    {
        if (Settings::get('mail_attach_pdf') !== '1') {
            return [];
        }
        try {
            return [['name' => $name, 'data' => $build(), 'mime' => 'application/pdf']];
        } catch (Throwable $e) {
            ErrorHandler::log($e);
            return [];
        }
    }

    private static function teamAddress(): string
    {
        return (string) (Settings::get('notify_email') ?: Settings::companyEmail());
    }

    /** Team-Adresse plus – falls abweichend – die E-Mail der zuständigen Person. */
    private static function teamRecipients(int $responsibleId): array
    {
        $out = [];
        $team = self::teamAddress();
        if ($team !== '') {
            $out[] = $team;
        }
        $r = Responsibility::emailOf($responsibleId ?: null);
        if ($r !== '' && !in_array(strtolower($r), array_map('strtolower', $out), true)) {
            $out[] = $r;
        }
        return $out;
    }

    public static function orderAssigned(array $order, array $to, array $actor): void
    {
        $body = '<p>Hallo ' . e($to['name']) . ',</p>'
            . '<p>' . e($actor['name'] ?? 'Ein Teammitglied') . ' hat Ihnen den Auftrag <strong>' . e($order['number']) . '</strong> („' . e($order['title']) . '“) zugewiesen. Sie sind ab jetzt dafür verantwortlich.</p>';
        self::send((string) $to['email'], 'Auftrag zugewiesen: ' . $order['number'], 'Auftrag zugewiesen', $body, url('admin/orders/' . $order['id']), 'Auftrag öffnen', 'order_assigned');
    }

    private static function greeting(array $c): string
    {
        $name = trim(($c['salutation'] ?? '') . ' ' . ($c['last_name'] ?? ''));
        if (in_array($c['salutation'] ?? '', ['Herr', 'Frau'], true) && !empty($c['last_name'])) {
            return ($c['salutation'] === 'Herr' ? 'Sehr geehrter ' : 'Sehr geehrte ') . e($name);
        }
        $first = trim((string) ($c['first_name'] ?? ''));
        return 'Guten Tag' . ($first !== '' ? ' ' . e($first . ' ' . ($c['last_name'] ?? '')) : '');
    }

    /* ---------------------------------------------------------------- Kunde */

    public static function offerSent(array $offer): bool
    {
        $c = $offer['customer'];
        $company = (string) Settings::get('company_name');
        $valid = $offer['valid_until'] ? '<p>Die Offerte ist gültig bis <strong>' . fmt_date($offer['valid_until']) . '</strong>.</p>' : '';
        $body = '<p>' . self::greeting($c) . ',</p>'
            . '<p>gerne senden wir Ihnen unsere Offerte <strong>' . e($offer['number']) . '</strong> – „' . e($offer['title']) . '“ über <strong>'
            . money($offer['total'], $offer['currency']) . '</strong>.</p>'
            . $valid
            . '<p>Über den folgenden Link können Sie die Offerte ansehen und direkt online annehmen oder ablehnen.</p>'
            . (SignatureService::mode($offer) === 'strict'
                ? '<p>Zur Annahme ist die <strong>Unterschrift von ' . e($offer['signer_name']) . '</strong> erforderlich. Die Person bestätigt dazu ihre E-Mail-Adresse mit einem Code. Bitte leiten Sie den Link bei Bedarf weiter.</p>'
                : (SignatureService::mode($offer) === 'open' ? '<p>Zur Annahme ist eine digitale Unterschrift erforderlich.</p>' : ''));
        $ok = self::send($c['email'] ?? '', "Ihre Offerte {$offer['number']} von $company", 'Ihre Offerte ist bereit', $body, OfferService::url($offer), 'Offerte ansehen', 'offer_sent', Settings::companyEmail(), self::pdfAttachment(static fn () => PdfService::offer($offer), 'Offerte-' . $offer['number'] . '.pdf'));
        if ($ok) {
            DB::exec('UPDATE offers SET sent_at = ? WHERE id = ?', [now(), $offer['id']]);
        }
        return $ok;
    }

    /** Einmalcode für die strikte Unterschrift – geht nur an die in der Offerte hinterlegte Person. */
    public static function signatureCode(array $offer, string $code): bool
    {
        $company = (string) Settings::get('company_name');
        $body = '<p>Guten Tag ' . e($offer['signer_name']) . ',</p>'
            . '<p>für die Unterschrift der Offerte <strong>' . e($offer['number']) . '</strong> von ' . e($company) . ' benötigen wir die Bestätigung Ihrer E-Mail-Adresse. Ihr Code lautet:</p>'
            . '<p style="font-size:28px;font-weight:700;letter-spacing:6px;margin:16px 0">' . e($code) . '</p>'
            . '<p>Der Code ist <strong>15 Minuten</strong> gültig und kann einmal verwendet werden. Geben Sie ihn niemandem weiter.</p>'
            . '<p style="color:#64748b;font-size:13px">Falls Sie nicht angefragt haben, unterschreiben zu wollen, ignorieren Sie diese E-Mail.</p>';
        return self::send((string) $offer['signer_email'], 'Ihr Bestätigungscode ' . $code . ' für Offerte ' . $offer['number'], 'Bestätigungscode', $body, null, null, 'signature_code');
    }

    public static function offerAccepted(array $offer, array $order): void
    {
        $c = $offer['customer'];
        $body = '<p>' . self::greeting($c) . ',</p>'
            . '<p>vielen Dank für Ihren Auftrag! Wir haben die Annahme der Offerte <strong>' . e($offer['number']) . '</strong> erhalten '
            . 'und den Auftrag <strong>' . e($order['number']) . '</strong> eröffnet.</p>'
            . '<p>Den Bearbeitungsstand können Sie jederzeit in unserem Kundenportal verfolgen.</p>';
        self::send($c['email'] ?? '', "Auftragsbestätigung {$order['number']}", 'Vielen Dank für Ihren Auftrag', $body, url('portal'), 'Zum Kundenportal', 'offer_accepted_customer', Settings::companyEmail());

        $team = '<p>Die Offerte <strong>' . e($offer['number']) . '</strong> („' . e($offer['title']) . '“) wurde von <strong>'
            . e($offer['response_name'] ?: customer_name($c)) . '</strong> angenommen.</p>'
            . '<p>Betrag: <strong>' . money($offer['total'], $offer['currency']) . '</strong><br>Auftrag: <strong>' . e($order['number']) . '</strong></p>'
            . ($offer['response_note'] ? '<p>Bemerkung des Kunden:<br><em>' . nl($offer['response_note']) . '</em></p>' : '');
        $subject = "Offerte angenommen: {$offer['number']} → {$order['number']}";
        foreach (self::teamRecipients((int) ($order['responsible_id'] ?? 0)) as $to) {
            self::send($to, $subject, 'Offerte angenommen 🎉', $team, url('admin/orders/' . $order['id']), 'Auftrag öffnen', 'offer_accepted_team');
        }
    }

    public static function offerDeclined(array $offer, bool $requestNew): void
    {
        $c = $offer['customer'];
        $what = $requestNew ? 'abgelehnt und eine neue Offerte angefragt' : 'abgelehnt';
        $body = '<p>Die Offerte <strong>' . e($offer['number']) . '</strong> („' . e($offer['title']) . '“) wurde von <strong>'
            . e(customer_name($c)) . "</strong> $what.</p>"
            . ($offer['response_note'] ? '<p>Begründung:<br><em>' . nl($offer['response_note']) . '</em></p>' : '');
        foreach (self::teamRecipients((int) ($offer['created_by'] ?? 0)) as $to) {
            self::send($to, "Offerte $what: {$offer['number']}", $requestNew ? 'Neue Offerte angefragt' : 'Offerte abgelehnt', $body, url('admin/offers/' . $offer['id']), 'Offerte öffnen', 'offer_declined_team');
        }
    }

    public static function orderUpdate(array $order, string $message): bool
    {
        $c = DB::one('SELECT * FROM customers WHERE id = ?', [$order['customer_id']]) ?? [];
        $tracking = '';
        if ($order['area_type'] === 'physical' && $order['tracking_number']) {
            $tracking = '<p>Sendungsnummer: <strong>' . e($order['tracking_number']) . '</strong>'
                . ($order['tracking_url'] ? '<br><a href="' . e($order['tracking_url']) . '">Sendung verfolgen</a>' : '') . '</p>';
        }
        $body = '<p>' . self::greeting($c) . ',</p>'
            . '<p>es gibt Neuigkeiten zu Ihrem Auftrag <strong>' . e($order['number']) . '</strong> – „' . e($order['title']) . '“.</p>'
            . '<div style="background:#f8fafc;border-radius:12px;padding:16px 18px;margin:16px 0">' . nl($message) . '</div>'
            . $tracking;
        return self::send($c['email'] ?? '', "Update zu Ihrem Auftrag {$order['number']}", 'Ihr Auftrag wurde aktualisiert', $body, url('portal/orders/' . $order['id']), 'Auftrag ansehen', 'order_update', Settings::companyEmail());
    }

    public static function invoiceSent(array $inv, bool $reminder = false): bool
    {
        $c = $inv['customer'];
        $company = (string) Settings::get('company_name');
        $due = fmt_date($inv['due_date']);
        if ($reminder) {
            $body = '<p>' . self::greeting($c) . ',</p>'
                . '<p>zu unserer Rechnung <strong>' . e($inv['number']) . '</strong> über <strong>' . money($inv['amount_due'], $inv['currency']) . '</strong> (fällig am ' . $due . ') konnten wir ' . ($inv['paid_total'] > 0 ? 'bisher nur eine Teilzahlung feststellen; offen sind noch <strong>' . money($inv['open_amount'], $inv['currency']) . '</strong>' : 'bisher keinen Zahlungseingang feststellen') . '.</p>'
                . '<p>Bitte überweisen Sie den Betrag in den nächsten Tagen. Sollte sich Ihre Zahlung mit dieser Erinnerung überschnitten haben, betrachten Sie diese E-Mail als gegenstandslos.</p>';
            $subject = "Zahlungserinnerung: Rechnung {$inv['number']}";
            $title = 'Zahlungserinnerung';
            $ctx = 'invoice_reminder';
        } elseif (($inv['kind'] ?? '') === 'credit') {
            $body = '<p>' . self::greeting($c) . ',</p>'
                . '<p>anbei erhalten Sie unsere <strong>Gutschrift ' . e($inv['number']) . '</strong> zur Rechnung ' . e((string) ($inv['credit_of_number'] ?? '')) . ' über <strong>' . money(abs((float) $inv['amount_due']), $inv['currency']) . '</strong>.</p>'
                . '<p>Der Betrag wird mit der Rechnung verrechnet bzw. Ihnen zurückerstattet.</p>';
            $subject = "Ihre Gutschrift {$inv['number']} von $company";
            $title = 'Ihre Gutschrift';
            $ctx = 'invoice_sent';
        } else {
            $body = '<p>' . self::greeting($c) . ',</p>'
                . '<p>vielen Dank für Ihren Auftrag. Anbei erhalten Sie unsere Rechnung <strong>' . e($inv['number']) . '</strong> – „' . e($inv['title']) . '“ über <strong>'
                . money($inv['amount_due'], $inv['currency']) . '</strong>.</p>'
                . ($inv['installments'] ? '<p>Die Zahlung ist in <strong>' . count($inv['installments']) . ' Raten</strong> möglich; die Termine finden Sie in der Rechnung. Nächste Fälligkeit: <strong>' . $due . '</strong>.</p>' : '<p>Zahlbar bis <strong>' . $due . '</strong>. Die Zahlungsinformationen finden Sie in der Rechnung.</p>');
            $subject = "Ihre Rechnung {$inv['number']} von $company";
            $title = 'Ihre Rechnung ist bereit';
            $ctx = 'invoice_sent';
        }
        return self::send($c['email'] ?? '', $subject, $title, $body, InvoiceService::url($inv), 'Rechnung ansehen', $ctx, Settings::companyEmail(), self::pdfAttachment(static fn () => PdfService::invoice($inv), (($inv['kind'] ?? '') === 'credit' ? 'Gutschrift-' : 'Rechnung-') . $inv['number'] . '.pdf'));
    }

    /** Eine Online-Zahlung (ganz oder teilweise) ist eingegangen: Bestätigung an den Kunden, Hinweis ans Team. */
    public static function invoicePaidOnline(array $inv, ?float $amount = null, bool $complete = true, ?int $providerPaymentId = null): void
    {
        $c = $inv['customer'];
        $amount ??= (float) $inv['amount_due'];
        $ledgerId = $providerPaymentId ? (int) DB::val('SELECT id FROM invoice_payments WHERE provider_payment_id = ?', [$providerPaymentId]) : 0;
        $body = '<p>' . self::greeting($c) . ',</p>'
            . '<p>vielen Dank – wir haben Ihre Zahlung von <strong>' . money($amount, $inv['currency']) . '</strong> für die Rechnung <strong>' . e($inv['number']) . '</strong> erhalten.</p>'
            . ($complete ? '<p>Die Rechnung ist damit beglichen.</p>' : '<p>Offen sind noch <strong>' . money($inv['open_amount'], $inv['currency']) . '</strong>.</p>');
        self::send($c['email'] ?? '', 'Zahlungsbestätigung zu Rechnung ' . $inv['number'], 'Zahlung erhalten', $body, InvoiceService::url($inv), 'Rechnung ansehen', 'invoice_paid', Settings::companyEmail(), self::pdfAttachment(static fn () => PdfService::receipt($inv, $ledgerId ?: null), 'Zahlungsbestaetigung-' . $inv['number'] . '.pdf'));
        $resp = !empty($inv['order_id']) ? (int) DB::val('SELECT responsible_id FROM orders WHERE id = ?', [$inv['order_id']]) : 0;
        foreach (self::teamRecipients($resp) as $team) {
            self::send($team, 'Online-Zahlung eingegangen: ' . $inv['number'], 'Online-Zahlung eingegangen',
                '<p>Zur Rechnung <strong>' . e($inv['number']) . '</strong> (' . e(customer_name($c)) . ') ist eine Online-Zahlung eingegangen: <strong>' . money($amount, $inv['currency']) . '</strong>.' . ($complete ? ' Die Rechnung ist vollständig bezahlt.' : ' Noch offen: ' . money($inv['open_amount'], $inv['currency']) . '.') . '</p>',
                url('admin/invoices/' . $inv['id']), 'Rechnung öffnen', 'invoice_paid_team');
        }
    }

    /** Neue Anfrage über das Formular auf der Startseite: Hinweis ans Team. */
    public static function inquiryReceived(array $inq): void
    {
        $body = '<p>Über das Anfrageformular ist eine neue Anfrage eingegangen:</p>'
            . '<div style="background:#f8fafc;border-radius:12px;padding:16px 18px;margin:16px 0">'
            . '<p><strong>' . e($inq['name']) . '</strong>' . ($inq['company'] ? ' · ' . e($inq['company']) : '') . '<br>' . e($inq['email']) . ($inq['phone'] ? ' · ' . e($inq['phone']) : '') . '</p>'
            . ($inq['subject'] ? '<p><strong>' . e($inq['subject']) . '</strong></p>' : '') . '<p>' . nl($inq['message']) . '</p></div>';
        foreach (self::teamRecipients(0) as $to) {
            self::send($to, 'Neue Anfrage von ' . $inq['name'], 'Neue Kundenanfrage', $body, url('admin/inquiries'), 'Anfrage öffnen', 'inquiry_team', (string) $inq['email']);
        }
    }

    /** Eingangsbestätigung an die anfragende Person (ohne den Inhalt zu wiederholen). */
    public static function inquiryConfirmation(array $inq): bool
    {
        $company = (string) Settings::get('company_name');
        $body = '<p>Guten Tag ' . e($inq['name']) . ',</p>'
            . '<p>vielen Dank für Ihre Anfrage bei ' . e($company) . '. Wir haben Ihre Nachricht erhalten und melden uns so rasch wie möglich bei Ihnen.</p>'
            . '<p style="color:#64748b;font-size:13px">Sie haben diese E-Mail erhalten, weil Ihre Adresse im Anfrageformular angegeben wurde. Falls Sie das nicht waren, können Sie diese Nachricht ignorieren.</p>';
        return self::send((string) $inq['email'], 'Ihre Anfrage bei ' . $company, 'Anfrage erhalten', $body, null, null, 'inquiry_confirm', Settings::companyEmail());
    }

    /** Kunde schreibt im Portal → Team-Adresse (und zuständige Person) benachrichtigen. */
    public static function portalMessageToTeam(array $c, array $msg, ?array $ref): void
    {
        $body = '<p><strong>' . e(customer_name($c)) . '</strong> hat über das Kundenportal geschrieben'
            . ($ref ? ' (' . e($ref[0]) . ')' : '') . ':</p>'
            . '<div style="background:#f8fafc;border-radius:12px;padding:16px 18px;margin:16px 0">' . nl($msg['body']) . '</div>';
        foreach (self::teamRecipients(0) as $to) {
            self::send($to, 'Neue Kundennachricht von ' . customer_name($c), 'Neue Nachricht im Kundenportal', $body, url('admin/messages/' . $c['id']), 'Antworten', 'portal_message_team', $c['email'] ?: null);
        }
    }

    /** Team antwortet → Kunde erhält einen Hinweis mit Portal-Link (Inhalt steht nur im Portal, nicht per E-Mail). */
    public static function portalMessageToCustomer(array $c): bool
    {
        $body = '<p>' . self::greeting($c) . ',</p>'
            . '<p>wir haben Ihnen im Kundenportal eine Nachricht geschrieben. Aus Datenschutzgründen steht der Inhalt nur im Portal.</p>';
        return self::send($c['email'] ?? '', 'Neue Nachricht von ' . Settings::get('company_name'), 'Sie haben eine neue Nachricht', $body, url('portal/messages'), 'Nachricht lesen', 'portal_message_customer', Settings::companyEmail());
    }

    /** Kunde lädt im Portal eine Datei hoch. */
    public static function portalUpload(array $c, array $order, string $fileName): void
    {
        $body = '<p><strong>' . e(customer_name($c)) . '</strong> hat im Kundenportal die Datei <strong>' . e($fileName) . '</strong> zum Auftrag <strong>' . e($order['number']) . '</strong> hochgeladen.</p>';
        foreach (self::teamRecipients((int) ($order['responsible_id'] ?? 0)) as $to) {
            self::send($to, 'Neue Datei von ' . customer_name($c), 'Datei im Kundenportal hochgeladen', $body, url('admin/orders/' . $order['id']), 'Auftrag öffnen', 'portal_upload_team');
        }
    }

    public static function portalLink(array $c, string $token): bool
    {
        $body = '<p>' . self::greeting($c) . ',</p>'
            . '<p>Sie haben einen Anmeldelink für das Kundenportal angefordert. Der Link ist <strong>30 Minuten</strong> gültig und kann einmal verwendet werden.</p>'
            . '<p style="color:#64748b;font-size:13px">Falls Sie diese Anmeldung nicht angefordert haben, können Sie diese E-Mail ignorieren.</p>';
        return self::send($c['email'], 'Ihr Anmeldelink für das Kundenportal', 'Anmeldung Kundenportal', $body, url('portal/auth/' . $token), 'Jetzt anmelden', 'portal_link');
    }

    /* ---------------------------------------------------------------- Team */

    public static function staffLink(array $u, string $token, bool $invite = false): bool
    {
        $body = '<p>Hallo ' . e($u['name']) . ',</p>'
            . ($invite
                ? '<p>für Sie wurde ein Zugang zur Administration von <strong>' . e(Settings::get('company_name')) . '</strong> eingerichtet. Mit dem folgenden Link melden Sie sich an. Danach können Sie unter „Mein Profil“ ein eigenes Passwort festlegen. Der Link ist 24 Stunden gültig.</p>'
                : '<p>Mit dem folgenden Link melden Sie sich ohne Passwort an. Der Link ist <strong>30 Minuten</strong> gültig und kann einmal verwendet werden.</p>')
            . '<p style="color:#64748b;font-size:13px">Falls Sie das nicht angefordert haben, ignorieren Sie diese E-Mail.</p>';
        return self::send($u['email'], $invite ? 'Ihr Zugang zur Administration' : 'Ihr Anmeldelink', $invite ? 'Willkommen im Team' : 'Anmeldung Administration', $body, url('admin/auth/' . $token), 'Jetzt anmelden', 'staff_link');
    }

    public static function test(string $to): bool
    {
        $body = '<p>Diese Test-E-Mail bestätigt, dass Ihre E-Mail-Einstellungen funktionieren.</p><p style="color:#64748b;font-size:13px">Gesendet am ' . fmt_datetime(now()) . '</p>';
        return self::send($to, 'Test-E-Mail – ' . Brand::name(), 'E-Mail-Versand funktioniert ✓', $body, url('admin'), 'Zur Administration', 'test');
    }
}
