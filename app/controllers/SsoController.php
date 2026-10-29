<?php
declare(strict_types=1);

/** Single Sign-On (OpenID Connect) für Mitarbeitende: Anmeldung, Rückkehr (Callback) und Einstellungen. */
final class SsoController
{
    /** Ist SSO eingerichtet und eingeschaltet? */
    public static function active(): bool
    {
        return Settings::get('sso_enabled') === '1' && Settings::get('sso_issuer', '') !== '';
    }

    /** Lokale Anmeldung gesperrt (nur über SSO)? */
    public static function required(): bool
    {
        return self::active() && Settings::get('sso_required') === '1';
    }

    private function fail(string $msg, ?string $detail = null): never
    {
        Audit::log('sso_failed', $detail ?? $msg, null, null, null);
        flash('error', $msg);
        redirect('admin/login');
    }

    /* ---------------------------------------------------------------- Anmeldung */

    public function start(): void
    {
        if (!self::active()) {
            throw new HttpException(404);
        }
        $d = Oidc::discover((string) Settings::get('sso_issuer'));
        if (!$d['ok']) {
            $this->fail('Die SSO-Anmeldung ist derzeit nicht erreichbar. Bitte melden Sie sich anders an.', $d['error']);
        }
        $clientId = $this->ensureClient($d['doc']);
        if ($clientId === null) {
            $this->fail('Die SSO-Anmeldung ist nicht fertig eingerichtet (Client-ID fehlt).');
        }
        redirect(Oidc::authorizeUrl($d['doc'], $clientId, trim((string) Settings::get('sso_scopes')) ?: 'openid email profile'));
    }

    public function callback(): void
    {
        $sess = $_SESSION['sso'] ?? null;
        unset($_SESSION['sso']);
        if (!self::active() || !is_array($sess) || time() - (int) $sess['at'] > 600) {
            $this->fail('Die SSO-Anmeldung ist abgelaufen. Bitte versuchen Sie es erneut.');
        }
        if (isset($_GET['error'])) {
            $this->fail('Der Anbieter hat die Anmeldung abgebrochen: ' . mb_substr((string) $_GET['error'], 0, 80), 'Anbieterfehler ' . mb_substr((string) $_GET['error'], 0, 80));
        }
        if (!hash_equals((string) $sess['state'], (string) ($_GET['state'] ?? '')) || (string) ($_GET['code'] ?? '') === '') {
            $this->fail('Ungültige Antwort des Anbieters (state).');
        }
        $d = Oidc::discover((string) Settings::get('sso_issuer'));
        if (!$d['ok']) {
            $this->fail('Die SSO-Anmeldung ist derzeit nicht erreichbar.', $d['error']);
        }
        $r = Oidc::complete($d['doc'], $d['issuer'], (string) Settings::get('sso_client_id'), (string) Settings::get('sso_client_secret'), (string) $_GET['code'], $sess);
        if (!$r['ok']) {
            $this->fail('Die Anmeldung konnte nicht bestätigt werden. ' . $r['error'], $r['error']);
        }
        $user = $this->resolveUser($r['claims'], $d['issuer']);
        $_SESSION['sso_login'] = true;
        Auth::loginUser($user);   // Zwei-Faktor/Passkey übernimmt der Anbieter
        Audit::log('sso_login', parse_url($d['issuer'], PHP_URL_HOST) ?: null, 'user', (int) $user['id'], $user);
        flash('success', 'Willkommen, ' . $user['name'] . '!');
        $intended = (string) ($_SESSION['intended'] ?? '');
        unset($_SESSION['intended']);
        $base = base_path() . '/admin';
        if ($intended !== '' && str_starts_with($intended, $base) && !str_contains($intended, '//') && !str_contains($intended, '/admin/sso') && !str_contains($intended, '/admin/auth')) {
            redirect(rtrim((string) preg_replace('#^(https?://[^/]+).*$#', '$1', (string) app_config('app.base_url')), '/') . $intended);
        }
        redirect('admin');
    }

    /** Benutzer zu den Claims finden (sub → E-Mail) oder – falls erlaubt – anlegen. */
    private function resolveUser(array $c, string $issuer): array
    {
        $sub = (string) $c['sub'];
        $email = mb_strtolower(trim((string) ($c['email'] ?? '')));
        $user = DB::one('SELECT * FROM users WHERE sso_issuer = ? AND sso_sub = ?', [$issuer, $sub]);
        if (!$user) {
            if (!valid_email($email) || ($c['email_verified'] ?? true) === false) {
                $this->fail('Der Anbieter hat keine bestätigte E-Mail-Adresse geliefert.', 'E-Mail fehlt/unbestätigt');
            }
            $allowed = array_filter(array_map(static fn ($d) => ltrim(mb_strtolower(trim($d)), '@'), explode(',', (string) Settings::get('sso_allowed_domains', ''))));
            if ($allowed && !in_array(substr(strrchr($email, '@'), 1), $allowed, true)) {
                $this->fail('Diese E-Mail-Domain ist für die Anmeldung nicht zugelassen.', 'Domain nicht zugelassen: ' . $email);
            }
            $user = DB::one('SELECT * FROM users WHERE email = ?', [$email]);
            if ($user && $user['sso_sub'] && ($user['sso_sub'] !== $sub || $user['sso_issuer'] !== $issuer)) {
                $this->fail('Dieses Konto ist mit einer anderen SSO-Identität verknüpft.', 'Konto bereits verknüpft: ' . $email);
            }
            if (!$user) {
                if (Settings::get('sso_auto_create') !== '1') {
                    $this->fail('Für diese E-Mail-Adresse besteht kein Konto. Bitten Sie eine Administratorin um eine Einladung.', 'Kein Konto: ' . $email);
                }
                $role = 'team';
                $grp = trim((string) Settings::get('sso_admin_group', ''));
                if ($grp !== '' && in_array($grp, array_map('strval', array_merge((array) ($c['groups'] ?? []), (array) ($c['roles'] ?? []))), true)) {
                    $role = 'admin';
                } elseif (Settings::get('sso_default_role') === 'admin') {
                    $role = 'team';   // Administratoren werden nie allein über die Standardrolle angelegt
                }
                $name = trim((string) ($c['name'] ?? '')) ?: trim(($c['given_name'] ?? '') . ' ' . ($c['family_name'] ?? '')) ?: (string) strstr($email, '@', true);
                $id = DB::insert('users', ['name' => mb_substr($name, 0, 120), 'email' => $email, 'password_hash' => Auth::hashPassword(bin2hex(random_bytes(24))),
                    'role' => $role, 'active' => 1, 'created_at' => now()]);
                $user = DB::one('SELECT * FROM users WHERE id = ?', [$id]);
                Audit::log('user_created', 'Per SSO angelegt (' . $role . ')', 'user', $id, null);
            }
            DB::exec('UPDATE users SET sso_issuer = ?, sso_sub = ? WHERE id = ?', [$issuer, $sub, $user['id']]);
        }
        if (!$user['active']) {
            $this->fail('Dieses Konto ist deaktiviert.', 'Konto deaktiviert: ' . $user['email']);
        }
        return $user;
    }

    /** Client-ID/-Secret: vorhanden oder per Registrierung (RFC 7591) beschafft. */
    private function ensureClient(array $doc): ?string
    {
        $id = trim((string) Settings::get('sso_client_id', ''));
        if ($id !== '') {
            return $id;
        }
        $reg = Oidc::register($doc, Oidc::redirectUri(), (string) Settings::get('company_name', 'VylarisOffers'));
        if (!$reg['ok']) {
            return null;
        }
        Settings::set(['sso_client_id' => $reg['client_id'], 'sso_client_secret' => $reg['client_secret']]);
        return $reg['client_id'];
    }

    /* ---------------------------------------------------------------- Einstellungen */

    public function save(): void
    {
        $user = Auth::requireAdmin();
        $issuerIn = trim((string) ($_POST['sso_issuer'] ?? ''));
        $enabled = !empty($_POST['sso_enabled']);
        $values = [
            'sso_enabled' => $enabled ? '1' : '0',
            'sso_label' => mb_substr(trim((string) ($_POST['sso_label'] ?? '')) ?: 'Vylaris-Login', 0, 40),
            'sso_scopes' => preg_replace('/[^A-Za-z0-9_.: -]/', '', (string) ($_POST['sso_scopes'] ?? '')) ?: 'openid email profile',
            'sso_auto_create' => !empty($_POST['sso_auto_create']) ? '1' : '0',
            'sso_default_role' => 'team',
            'sso_admin_group' => mb_substr(trim((string) ($_POST['sso_admin_group'] ?? '')), 0, 100),
            'sso_allowed_domains' => mb_substr(trim((string) ($_POST['sso_allowed_domains'] ?? '')), 0, 300),
            'sso_required' => !empty($_POST['sso_required']) ? '1' : '0',
        ];
        if (!str_contains(' ' . $values['sso_scopes'] . ' ', ' openid ')) {
            $values['sso_scopes'] = 'openid ' . $values['sso_scopes'];
        }
        $issuer = $issuerIn === '' ? '' : Oidc::normalizeIssuer($issuerIn);
        if ($issuerIn !== '' && $issuer === null) {
            flash('error', 'Bitte eine gültige https://-Adresse angeben, z. B. https://vylaris.tech.');
            redirect('admin/settings?tab=sso');
        }
        if ($issuer !== (string) Settings::get('sso_issuer', '')) {
            $values += ['sso_discovery' => '', 'sso_jwks' => '', 'sso_client_id' => '', 'sso_client_secret' => ''];   // neuer Anbieter → neue Registrierung
        }
        $values['sso_issuer'] = (string) $issuer;
        if (trim((string) ($_POST['sso_client_id'] ?? '')) !== '') {
            $values['sso_client_id'] = trim((string) $_POST['sso_client_id']);
            if (trim((string) ($_POST['sso_client_secret'] ?? '')) !== '') {
                $values['sso_client_secret'] = trim((string) $_POST['sso_client_secret']);
            }
        }
        if ($values['sso_required'] === '1' && !$enabled) {
            $values['sso_required'] = '0';
        }
        Settings::set($values);
        Audit::log('sso_settings', ($enabled ? 'aktiv' : 'aus') . ($values['sso_required'] === '1' ? ', ausschliesslich' : ''), null, null, $user);

        if ($enabled && $issuer) {
            $d = Oidc::discover($issuer, true);
            if (!$d['ok']) {
                flash('error', 'Gespeichert, aber: ' . $d['error']);
                redirect('admin/settings?tab=sso');
            }
            if (trim((string) Settings::get('sso_client_id', '')) === '') {
                if ($this->ensureClient($d['doc']) === null) {
                    flash('error', 'Gespeichert. Der Anbieter bietet keine automatische Registrierung an – bitte Client-ID und Client-Secret unter «Erweitert» eintragen und diese Redirect-URI beim Anbieter hinterlegen: ' . Oidc::redirectUri());
                    redirect('admin/settings?tab=sso');
                }
                flash('success', 'SSO eingerichtet. Der Client wurde automatisch beim Anbieter registriert.');
                redirect('admin/settings?tab=sso');
            }
        }
        flash('success', 'SSO-Einstellungen gespeichert.');
        redirect('admin/settings?tab=sso');
    }

    public function test(): void
    {
        Auth::requireAdmin();
        $d = Oidc::discover((string) ($_POST['sso_issuer'] ?? '') ?: (string) Settings::get('sso_issuer'), true);
        if (!$d['ok']) {
            flash('error', $d['error']);
        } else {
            $doc = $d['doc'];
            flash('success', 'Verbindung erfolgreich: ' . $d['issuer'] . ' · Anmeldung ' . parse_url((string) $doc['authorization_endpoint'], PHP_URL_HOST)
                . (isset($doc['registration_endpoint']) ? ' · automatische Registrierung möglich' : ' · Client-ID/-Secret manuell eintragen'));
        }
        redirect('admin/settings?tab=sso');
    }
}
