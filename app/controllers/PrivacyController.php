<?php
declare(strict_types=1);

/** Öffentliche Datenschutzerklärung (/datenschutz) und Selbstauskunft für Portal-Kunden. */
final class PrivacyController
{
    public function show(): void
    {
        $custom = trim((string) Settings::get('privacy_text'));
        $contact = Settings::get('privacy_contact') ?: Settings::companyEmail();
        $ret = [
            'years' => max(1, (int) (Settings::get('retention_years') ?: 10)),
            'ip'    => max(1, (int) (Settings::get('ip_retention_days') ?: 90)),
            'mail'  => max(30, (int) (Settings::get('mail_log_retention_days') ?: 365)),
        ];
        view('public/privacy', compact('custom', 'contact', 'ret'), 'public');
    }

    /** Eingeloggte Portal-Kunden laden ihre eigenen Daten herunter (Art. 15/20 DSGVO). */
    public function selfExport(): void
    {
        $c = Auth::requireCustomer();
        if (RateLimit::tooMany('selfexport:' . $c['id'], 5, 3600)) {
            flash('error', 'Bitte versuchen Sie es später erneut.');
            redirect('portal');
        }
        RateLimit::hit('selfexport:' . $c['id']);
        Audit::log('customer_export', 'Selbstauskunft im Portal', 'customer', (int) $c['id'], []);
        CustomerPrivacy::sendExport((int) $c['id'], 'meine-daten');
    }
}
