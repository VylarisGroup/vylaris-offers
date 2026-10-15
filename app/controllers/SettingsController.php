<?php
declare(strict_types=1);

final class SettingsController
{
    private const KEYS = [
        'company_name', 'company_street', 'company_zip', 'company_city', 'company_email',
        'company_phone', 'company_website', 'company_vat_id', 'company_logo_url', 'brand_color',
        'currency', 'mwst_method', 'mwst_saldo_rate', 'mwst_period', 'offer_prefix', 'order_prefix', 'offer_validity_days', 'offer_intro', 'offer_footer',
        'invoice_prefix', 'invoice_payment_days', 'invoice_intro', 'invoice_footer', 'invoice_payment_info', 'invoice_iban', 'notify_email', 'mail_transport', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'mail_from_email', 'mail_from_name',
        'audit_retention_days', 'ip_retention_days', 'mail_log_retention_days', 'retention_years', 'privacy_contact', 'privacy_text',
    ];

    public function index(): void
    {
        Auth::requireAdmin();
        $s = Settings::load();
        foreach (['smtp_pass', 'mollie_api_key', 'stripe_secret_key', 'stripe_webhook_secret'] as $secret) {
            $s[$secret . '_set'] = ($s[$secret] ?? '') !== '';
            $s[$secret] = ($s[$secret] ?? '') !== '' ? '••••••••' : '';
        }
        view('admin/settings/index', ['s' => $s, 'tab' => input_str('tab', 20) ?: 'company']);
    }

    public function save(): void
    {
        Auth::requireAdmin();
        $tab = input_str('tab', 20) ?: 'company';
        $values = [];
        $errors = [];
        foreach (self::KEYS as $k) {
            if (array_key_exists($k, $_POST)) {
                $values[$k] = mb_substr(trim((string) $_POST[$k]), 0, 10000);
            }
        }
        if ($tab === 'company') {
            $values['mwst_liable'] = !empty($_POST['mwst_liable']) ? '1' : '0';
            $values['company_country'] = 'CH';
            if (isset($values['company_vat_id']) && $values['company_vat_id'] !== '') {
                if (preg_match('/^CHE/i', $values['company_vat_id'])) {
                    if (!TaxService::validUid($values['company_vat_id'])) {
                        $errors[] = 'Die UID ist ungültig (Format CHE-123.456.789, mit gültiger Prüfziffer).';
                    } else {
                        $values['company_vat_id'] = TaxService::formatUid($values['company_vat_id'], false);
                    }
                } else {
                    $errors[] = 'Die UID muss mit CHE beginnen (z. B. CHE-123.456.789).';
                }
            }
            if ($values['mwst_liable'] === '1' && ($values['company_vat_id'] ?? Settings::get('company_vat_id', '')) === '') {
                $errors[] = 'MWST-pflichtige Unternehmen müssen ihre UID/MWST-Nummer angeben.';
            }
            if (isset($values['mwst_method']) && !in_array($values['mwst_method'], ['agreed', 'received'], true)) {
                $values['mwst_method'] = 'agreed';
            }
            if (isset($values['mwst_period']) && !in_array($values['mwst_period'], ['quarter', 'semester', 'year'], true)) {
                $values['mwst_period'] = 'quarter';
            }
            if (isset($values['mwst_saldo_rate'])) {
                $r = str_replace(',', '.', $values['mwst_saldo_rate']);
                if ($r === '') {
                    $values['mwst_saldo_rate'] = '';
                } elseif (!is_numeric($r) || (float) $r <= 0 || (float) $r > 10) {
                    $errors[] = 'Der Saldosteuersatz muss zwischen 0.1 und 10 % liegen.';
                } else {
                    $values['mwst_saldo_rate'] = rtrim(rtrim(number_format((float) $r, 1, '.', ''), '0'), '.');
                }
            }
        }
        if ($tab === 'offers') {
            $values['number_style'] = 'ch';
            $values['round_005'] = !empty($_POST['round_005']) ? '1' : '0';
            $values['show_contact_person'] = !empty($_POST['show_contact_person']) ? '1' : '0';
        }
        if ($tab === 'payments') {
            $values['pay_mollie_enabled'] = !empty($_POST['pay_mollie_enabled']) ? '1' : '0';
            $values['pay_stripe_enabled'] = !empty($_POST['pay_stripe_enabled']) ? '1' : '0';
            $rules = [
                'mollie_api_key'        => ['/^(live|test)_[A-Za-z0-9]{20,}$/', 'Der Mollie-API-Key muss mit live_ oder test_ beginnen.'],
                'stripe_secret_key'     => ['/^(sk|rk)_(live|test)_[A-Za-z0-9_]{20,}$/', 'Der Stripe-Schlüssel muss mit sk_live_/sk_test_ (oder rk_…) beginnen.'],
                'stripe_webhook_secret' => ['/^whsec_[A-Za-z0-9]{16,}$/', 'Das Stripe-Webhook-Secret muss mit whsec_ beginnen.'],
            ];
            foreach ($rules as $k => [$re, $msg]) {
                $v = trim((string) ($_POST[$k] ?? ''));
                if (!empty($_POST[$k . '_clear'])) {
                    $values[$k] = '';
                } elseif ($v !== '' && $v !== '••••••••') {
                    preg_match($re, $v) ? $values[$k] = $v : $errors[] = $msg;
                }
            }
        }
        if ($tab === 'mail') {
            $values['mail_attach_pdf'] = !empty($_POST['mail_attach_pdf']) ? '1' : '0';
        }
        $pass = (string) ($_POST['smtp_pass'] ?? '');
        if ($pass !== '' && $pass !== '••••••••') {
            $values['smtp_pass'] = $pass;
        }
        if (!empty($_POST['smtp_pass_clear'])) {
            $values['smtp_pass'] = '';
        }

        foreach (['company_email', 'notify_email', 'mail_from_email'] as $k) {
            if (($values[$k] ?? '') !== '' && !valid_email($values[$k])) {
                $errors[] = 'Ungültige E-Mail-Adresse: ' . $values[$k];
            }
        }
        if (isset($values['brand_color']) && !preg_match('/^#[0-9a-fA-F]{6}$/', $values['brand_color'])) {
            $errors[] = 'Die Markenfarbe muss als Hex-Wert angegeben werden (z. B. #4f46e5).';
        }
        if (isset($values['currency']) && !in_array($values['currency'], ['CHF', 'EUR'], true)) {
            $errors[] = 'Die Währung muss CHF oder EUR sein.';
        }
        if (isset($values['smtp_port']) && ((int) $values['smtp_port'] < 1 || (int) $values['smtp_port'] > 65535)) {
            $errors[] = 'Ungültiger SMTP-Port.';
        }
        if (isset($values['mail_transport']) && !in_array($values['mail_transport'], ['smtp', 'phpmail', 'log'], true)) {
            $values['mail_transport'] = 'smtp';
        }
        if (isset($values['smtp_encryption']) && !in_array($values['smtp_encryption'], ['tls', 'ssl', 'none'], true)) {
            $values['smtp_encryption'] = 'tls';
        }
        if (isset($values['offer_validity_days'])) {
            $values['offer_validity_days'] = (string) max(0, min(365, (int) $values['offer_validity_days']));
        }
        if (isset($values['invoice_iban'])) {
            $values['invoice_iban'] = strtoupper(preg_replace('/\s+/', '', $values['invoice_iban']) ?? '');
            if ($values['invoice_iban'] !== '' && !SwissQr::validIban($values['invoice_iban'])) {
                $errors[] = 'Die IBAN ist ungültig (erwartet: CH oder LI, 21 Zeichen, gültige Prüfziffer).';
            }
        }
        if (isset($values['invoice_payment_days'])) {
            $values['invoice_payment_days'] = (string) max(0, min(365, (int) $values['invoice_payment_days']));
        }
        foreach (['audit_retention_days' => [30, 3650], 'ip_retention_days' => [1, 365], 'mail_log_retention_days' => [30, 3650], 'retention_years' => [1, 30]] as $k => [$lo, $hi]) {
            if (isset($values[$k])) {
                $values[$k] = (string) max($lo, min($hi, (int) $values[$k]));
            }
        }
        if (isset($values['privacy_contact']) && $values['privacy_contact'] !== '' && !valid_email($values['privacy_contact'])) {
            $errors[] = 'Ungültige E-Mail-Adresse für Datenschutzanfragen.';
        }
        foreach (['offer_prefix' => 'OF', 'order_prefix' => 'AU', 'invoice_prefix' => 'RE'] as $k => $fallback) {
            if (isset($values[$k])) {
                $values[$k] = preg_replace('/[^A-Za-z0-9]/', '', $values[$k]) ?: $fallback;
            }
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            Settings::set($values);
            Audit::log('settings_saved', 'Tab ' . $tab . ': ' . implode(', ', array_diff(array_keys($values), ['smtp_pass'])) . (isset($values['smtp_pass']) ? ', smtp_pass' : ''));
            flash('success', 'Einstellungen gespeichert.');
        }
        redirect('admin/settings?tab=' . rawurlencode($tab));
    }

    /** Whitelabel: Systemname, Untertitel, Farbe, Logo und Favicon der Administration. */
    public function branding(): void
    {
        Auth::requireAdmin();
        $values = [
            'brand_name' => mb_substr(trim(input_str('brand_name', 60)), 0, 60),
            'brand_tagline' => mb_substr(trim(input_str('brand_tagline', 60)), 0, 60),
        ];
        $color = trim(input_str('brand_color', 7));
        $errors = [];
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $errors[] = 'Die Markenfarbe muss als Hex-Wert angegeben werden (z. B. #4f46e5).';
        } else {
            $values['brand_color'] = $color;
        }
        foreach (['logo', 'favicon'] as $kind) {
            $key = Brand::settingKey($kind);
            try {
                if (!empty($_POST[$kind . '_remove'])) {
                    Brand::remove($kind);
                    $values[$key] = '';
                } elseif (isset($_FILES[$kind]) && (int) ($_FILES[$kind]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $values[$key] = Brand::store($kind, $_FILES[$kind]);
                }
            } catch (ValidationException $ex) {
                $errors[] = $ex->getMessage();
            }
        }
        // Teilweise gespeicherte Änderungen (z. B. Logo ok, Favicon fehlerhaft) bleiben erhalten.
        Settings::set($values);
        Audit::log('settings_saved', 'Tab branding: ' . implode(', ', array_keys($values)));
        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            flash('success', 'Branding gespeichert.');
        }
        redirect('admin/settings?tab=branding');
    }

    public function testMail(): void
    {
        $user = Auth::requireAdmin();
        $to = input_str('to', 190) ?: $user['email'];
        Audit::log('test_mail', null);
        if (Notifier::test($to)) {
            flash('success', "Test-E-Mail an $to wurde erfolgreich übergeben.");
        } else {
            flash('error', 'Test fehlgeschlagen: ' . Notifier::$lastError);
        }
        redirect('admin/settings?tab=mail');
    }

    public function backup(): void
    {
        $user = Auth::requireAdmin();
        $withFiles = !empty($_POST['files']);
        Audit::log('backup', $withFiles ? 'inkl. Anhänge' : 'nur Datenbank', null, null, $user);
        BackupService::download($withFiles);
    }

    /** Verzeichnis der Verarbeitungstätigkeiten (Art. 30 DSGVO) – aus den aktuellen Einstellungen erzeugt, druckbar. */
    public function privacyRegister(): void
    {
        Auth::requireAdmin();
        $cfg = [
            'mail'    => Settings::get('mail_transport', 'smtp') === 'smtp' ? ('SMTP-Server ' . (Settings::get('smtp_host') ?: '(nicht konfiguriert)')) : 'PHP mail() des Hosters',
            'mollie'  => Settings::get('pay_mollie_enabled') === '1',
            'stripe'  => Settings::get('pay_stripe_enabled') === '1',
            'hooks'   => (int) DB::val('SELECT COUNT(*) FROM webhooks WHERE active = 1'),
            'api'     => (int) DB::val('SELECT COUNT(*) FROM api_tokens WHERE revoked_at IS NULL'),
            'years'   => max(1, (int) (Settings::get('retention_years') ?: 10)),
            'ip'      => max(1, (int) (Settings::get('ip_retention_days') ?: 90)),
            'mailLog' => max(30, (int) (Settings::get('mail_log_retention_days') ?: 365)),
            'audit'   => max(30, (int) (Settings::get('audit_retention_days') ?: 365)),
            'inq'     => max(30, (int) (Settings::get('inquiry_retention_days') ?: 180)),
            'contact' => Settings::get('privacy_contact') ?: Settings::companyEmail(),
        ];
        view('admin/settings/privacy-register', compact('cfg'));
    }

    public function mailLog(): void
    {
        Auth::requireAdmin();
        $logs = DB::all('SELECT * FROM mail_log ORDER BY created_at DESC, id DESC LIMIT 200');
        view('admin/settings/mail-log', compact('logs'));
    }
}
