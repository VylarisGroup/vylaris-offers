<?php
/**
 * Regressionstests für Steuer- und Rechenlogik – ohne Datenbank, nur CLI:  php tests/run.php
 * Prüft MWST-Berechnung, Rundung, UID, Steuerarten, Pflichthinweise und Abrechnungsziffern.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', APP_ROOT . '/app');
define('APP_VERSION', preg_match("/define\('APP_VERSION', '([^']+)'\)/", (string) file_get_contents(APP_DIR . '/bootstrap.php'), $mv) ? $mv[1] : '0');
spl_autoload_register(static function (string $class): void {
    foreach (['lib', 'services'] as $dir) {
        $f = APP_DIR . "/$dir/$class.php";
        if (is_file($f)) {
            require $f;
            return;
        }
    }
});
require APP_DIR . '/lib/helpers.php';

$pass = 0;
$fail = 0;
$eq = static function (string $name, mixed $got, mixed $want) use (&$pass, &$fail): void {
    $ok = is_float($want) || is_float($got) ? abs((float) $got - (float) $want) < 0.0001 : $got === $want;
    if ($ok) {
        $pass++;
        return;
    }
    $fail++;
    echo "FAIL  $name\n      erwartet: " . json_encode($want, JSON_UNESCAPED_UNICODE) . "\n      erhalten: " . json_encode($got, JSON_UNESCAPED_UNICODE) . "\n";
};

/* ---- Berechnung */
$c = Calc::compute([
    ['quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 8.1, 'tax_label' => 'MWST 8.1 %'],
    ['quantity' => 1, 'unit_price' => 500, 'tax_rate' => 2.6, 'tax_label' => 'MWST 2.6 %'],
    ['quantity' => 1, 'unit_price' => 300, 'tax_rate' => 0, 'tax_label' => 'Ausgenommen (Art. 21)'],
], []);
$eq('Steuer 8.1 + 2.6', $c['tax_total'], 94.0);
$eq('Total', $c['total'], 1894.0);
$c = Calc::compute([['quantity' => 3, 'unit_price' => 33.33, 'tax_rate' => 8.1, 'tax_label' => 'MWST 8.1 %']], ['round_005' => true]);
$eq('5-Rappen-Rundung', $c['total'], 108.10);   // 99.99 + 8.10 = 108.09 → 108.10
$c = Calc::compute([['quantity' => 1, 'unit_price' => 100, 'tax_rate' => 8.1, 'tax_label' => 'MWST 8.1 %', 'discount_type' => 'percent', 'discount_value' => 10]], []);
$eq('Positionsrabatt vor Steuer', $c['tax_total'], 7.29);
$c = Calc::compute([['quantity' => 1, 'unit_price' => -500, 'tax_rate' => 8.1, 'tax_label' => 'MWST 8.1 %']], []);
$eq('Gutschrift: Steuer negativ', $c['tax_total'], -40.5);
$eq('Gutschrift: Total negativ', $c['total'], -540.5);
$c = Calc::compute([
    ['quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 8.1, 'tax_label' => 'MWST 8.1 %'],
    ['quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 2.6, 'tax_label' => 'MWST 2.6 %'],
], ['discount_type' => 'percent', 'discount_value' => 10]);
$eq('Gesamtrabatt anteilig auf Steuergruppen', $c['tax_total'], 8.1 * 0.9 * 10 + 2.6 * 0.9 * 10);

/* ---- UID (Modulo 11) */
$eq('UID gültig', TaxService::validUid('CHE-100.000.006'), true);
$eq('UID mit MWST-Zusatz', TaxService::validUid('CHE-100.000.006 MWST'), true);
$eq('UID Prüfziffer falsch', TaxService::validUid('CHE-100.000.007'), false);
$eq('UID Format falsch', TaxService::validUid('DE123456789'), false);
$eq('UID Formatierung', TaxService::formatUid('CHE100000006'), 'CHE-100.000.006 MWST');
$eq('UID ohne Zusatz', TaxService::formatUid('CHE100000006', false), 'CHE-100.000.006');

/* ---- Steuerarten */
$eq('Art 8.1', TaxService::classOfGroup(8.1, 'MWST 8.1 %'), 'taxable');
$eq('Art Option', TaxService::classOfGroup(8.1, TaxService::CLASSES['option']['label']), 'option');
foreach (['export', 'abroad', 'reporting', 'exempt', 'passthrough'] as $k) {
    $eq("Art $k aus Kennzeichnung", TaxService::classOfGroup(0, TaxService::CLASSES[$k]['label']), $k);
    $eq("Art $k hat Pflichthinweis", !empty(TaxService::CLASSES[$k]['note']), true);
}
$eq('Art unbekannt bei 0 %', TaxService::classOfGroup(0, 'Irgendwas'), 'unclassified');
$labels = array_column(TaxService::CLASSES, 'label');
$eq('Kennzeichnungen eindeutig', count($labels), count(array_unique($labels)));

/* ---- Hinweise auf Belegen */
$notes = TaxService::notes([
    ['rate' => 8.1, 'label' => 'MWST 8.1 %', 'base' => 100, 'tax' => 8.1],
    ['rate' => 0, 'label' => TaxService::CLASSES['abroad']['label'], 'base' => 200, 'tax' => 0],
    ['rate' => 0, 'label' => TaxService::CLASSES['export']['label'], 'base' => 0, 'tax' => 0],   // ohne Betrag: kein Hinweis
]);
$eq('Nur benötigte Hinweise', count($notes), 1);
$eq('Hinweis Ausland nennt Art. 8', str_contains($notes[0], 'Art. 8'), true);

/* ---- Ziffern, Länder */
$eq('Ziffer 8.1', TaxService::rateZiff(8.1), '303');
$eq('Ziffer 2.6', TaxService::rateZiff(2.6), '313');
$eq('Ziffer 3.8', TaxService::rateZiff(3.8), '343');
$eq('Ziffer alt 7.7', TaxService::rateZiff(7.7), '302');
$eq('Ziffer unbekannt', TaxService::rateZiff(5.0), null);
$eq('Liechtenstein ist Inland', TaxService::domesticCountry('LI'), true);
$eq('Schweiz ist Inland', TaxService::domesticCountry('ch'), true);
$eq('Deutschland ist Ausland', TaxService::domesticCountry('DE'), false);

/* ---- QR-Rechnung */
$eq('IBAN CH gültig', SwissQr::validIban('CH9300762011623852957'), true);
$eq('IBAN CH ungültig', SwissQr::validIban('CH9300762011623852958'), false);


/* ---- Lizenzsystem (Signatur, Bewertung, Funktions-Scopes) */
$kp = sodium_crypto_sign_keypair();
$sk = sodium_crypto_sign_secretkey($kp);
$pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('302a300506032b6570032100') . sodium_crypto_sign_publickey($kp)), 64, "\n") . "-----END PUBLIC KEY-----\n";
$signed = static function (array $d) use ($sk): array {
    $p = json_encode($d, JSON_UNESCAPED_SLASHES);
    return ['payload' => $p, 'signature' => base64_encode(sodium_crypto_sign_detached($p, $sk))];
};
$now = 1_800_000_000;
$mk = static fn (array $over = [], array $lic = []) => array_replace(['valid' => true, 'code' => 'OK', 'ts' => $now - 60,
    'license' => $lic + ['features' => ['invoices', 'payments', 'reports'], 'plan' => 'Pro', 'expiresAt' => null, 'trial' => false]], $over);
$base = ['key' => 'VO-1', 'pem' => $pem, 'hwid' => 'h#1', 'net_error' => false, 'grace_days' => 3, 'offline' => null];
$p = $signed($mk());
$eq('Signatur gültig', License::verify($p['payload'], $p['signature'], $pem), true);
$eq('Signatur manipuliert', License::verify($p['payload'] . ' ', $p['signature'], $pem), false);
$eq('Falscher Schlüssel', License::verify($p['payload'], $p['signature'], str_replace('MCow', 'MCoz', $pem)), false);
$eq('PEM ungültig', License::pemToRaw('-----BEGIN PUBLIC KEY-----AAAA-----END PUBLIC KEY-----'), null);
$ev = License::evaluate($base + ['proof' => $p], $now);
$eq('Lizenz aktiv', [$ev['status'], $ev['mode']], ['active', 'full']);
$eq('Features übernommen', $ev['features'], ['invoices', 'payments', 'reports']);
$eq('Ohne Schlüssel → Lesemodus', License::evaluate(['key' => ''] + $base + ['proof' => $p], $now)['mode'], 'readonly');
$eq('Ohne Beweis → Lesemodus', License::evaluate($base + ['proof' => null], $now)['status'], 'unverified');
$forged = ['payload' => $p['payload'], 'signature' => base64_encode(str_repeat('x', 64))];
$eq('Gefälschter Beweis', License::evaluate($base + ['proof' => $forged], $now)['mode'], 'readonly');
$eq('Fremder Schlüssel im Beweis', License::evaluate(['pem' => ''] + $base + ['proof' => $p], $now)['mode'], 'readonly');
foreach (['BLOCKED' => 'blocked', 'EXPIRED' => 'expired', 'NOT_FOUND' => 'not_found', 'WRONG_PRODUCT' => 'wrong_product', 'DEVICE_LIMIT' => 'device_limit', 'NOT_ACTIVATED' => 'not_activated'] as $code => $st) {
    $e = License::evaluate($base + ['proof' => $signed($mk(['valid' => false, 'code' => $code]))], $now);
    $eq("Code $code", [$e['status'], $e['mode']], [$st, 'readonly']);
}
$eq('Ablaufdatum vergangen', License::evaluate($base + ['proof' => $signed($mk([], ['expiresAt' => gmdate('c', $now - 10)]))], $now)['status'], 'expired');
$eq('Ablaufdatum Zukunft', License::evaluate($base + ['proof' => $signed($mk([], ['expiresAt' => gmdate('c', $now + 99999)]))], $now)['mode'], 'full');
$old = $signed($mk(['ts' => $now - 2 * 86400]));
$eq('2 Tage alt → Karenzfrist', License::evaluate($base + ['proof' => $old], $now)['status'], 'grace');
$eq('Server nicht erreichbar → Karenzfrist', License::evaluate(['net_error' => true] + $base + ['proof' => $p], $now)['status'], 'grace');
$eq('Karenzfrist abgelaufen', License::evaluate(['grace_days' => 1] + $base + ['proof' => $old], $now)['status'], 'unreachable');
$off = static fn (array $o) => $signed($o + ['v' => 1, 'type' => 'offline', 'key' => 'VO-1', 'hwid' => 'h#1', 'validUntil' => gmdate('c', $now + 86400), 'licenseExpiresAt' => null]);
$eq('Offline-Lizenz gültig', License::evaluate(['grace_days' => 0, 'offline' => $off([])] + $base + ['proof' => $old], $now)['status'], 'offline');
$eq('Offline-Lizenz falsches Gerät', License::evaluate(['grace_days' => 0, 'offline' => $off(['hwid' => 'h#2'])] + $base + ['proof' => $old], $now)['status'], 'unreachable');
$eq('Offline-Lizenz abgelaufen', License::evaluate(['grace_days' => 0, 'offline' => $off(['validUntil' => gmdate('c', $now - 5)])] + $base + ['proof' => $old], $now)['status'], 'unreachable');
$eq('Offline-Lizenz anderer Schlüssel', License::evaluate(['grace_days' => 0, 'offline' => $off(['key' => 'VO-2'])] + $base + ['proof' => $old], $now)['status'], 'unreachable');
$eq('Gesperrt trotz Offline-Lizenz', License::evaluate(['net_error' => true, 'offline' => $off([])] + $base + ['proof' => $signed($mk(['valid' => false, 'code' => 'BLOCKED']))], $now)['status'], 'blocked');
$eq('Scope vorhanden', License::featureIn(['invoices'], 'invoices'), true);
$eq('Scope fehlt', License::featureIn(['invoices'], 'api'), false);
$eq('Scope Abhängigkeit (Abos brauchen Rechnungen)', License::featureIn(['subscriptions'], 'subscriptions'), false);
$eq('Scope Abhängigkeit erfüllt', License::featureIn(['subscriptions', 'invoices'], 'subscriptions'), true);
$eq('Unbekannter Scope', License::featureIn(['x'], 'x'), false);
$eq('Kern immer', License::featureIn([], 'core'), true);
$full = ['mode' => 'full', 'features' => ['invoices']];
$ro = ['mode' => 'readonly', 'features' => ['invoices', 'api']];
$eq('Route Rechnungen → invoices', License::featureForPath('/admin/invoices/5'), 'invoices');
$eq('Route Abo-Anlage → subscriptions', License::featureForPath('/admin/orders/9/subscription'), 'subscriptions');
$eq('Route API → api', License::featureForPath('/api/v1/invoices'), 'api');
$eq('Route API ping frei', License::featureForPath('/api/v1/ping'), null);
$eq('Route Offerten frei', License::featureForPath('/admin/offers'), null);
$eq('Funktion fehlt → gesperrt', License::decide('GET', '/admin/subscriptions', $full), ['reason' => 'feature', 'feature' => 'subscriptions']);
$eq('Funktion vorhanden → erlaubt', License::decide('GET', '/admin/invoices', $full), null);
$eq('Lesemodus: POST Offerte gesperrt', License::decide('POST', '/admin/offers', $ro)['reason'] ?? null, 'readonly');
$eq('Lesemodus: GET Offerte erlaubt', License::decide('GET', '/admin/offers/3', $ro), null);
$eq('Lesemodus: DSGVO-Export erlaubt', License::decide('POST', '/admin/customers/4/privacy-export', $ro), null);
$eq('Lesemodus: Backup erlaubt', License::decide('POST', '/admin/backup', $ro), null);
$eq('Lesemodus: Lizenz-Seite erlaubt', License::decide('POST', '/admin/license', ['mode' => 'readonly', 'features' => []]), null);
$eq('Lesemodus: Annahme gesperrt', License::decide('POST', '/o/abc/accept', $ro)['reason'] ?? null, 'readonly');
$eq('Webhook Zahlungsanbieter nie gesperrt', License::decide('POST', '/webhooks/stripe', ['mode' => 'readonly', 'features' => []]), null);
$eq('Login nie gesperrt', License::decide('POST', '/admin/login', ['mode' => 'readonly', 'features' => []]), null);

// ---------------------------------------------------------------- SSO / OpenID Connect
$b = static fn (string $x) => Oidc::b64uEncode($x);
$jwt = static function (array $h, array $c, callable $sign) use ($b): string {
    $in = $b(json_encode($h)) . '.' . $b(json_encode($c));
    return $in . '.' . $b($sign($in));
};
$rsa = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
$rd = openssl_pkey_get_details($rsa)['rsa'];
$rsaJwks = ['keys' => [['kty' => 'RSA', 'kid' => 'r1', 'use' => 'sig', 'n' => $b($rd['n']), 'e' => $b($rd['e'])]]];
$rsaSign = static function (string $in) use ($rsa): string { openssl_sign($in, $s, $rsa, OPENSSL_ALGO_SHA256); return $s; };
$claims = ['iss' => 'https://idp.example', 'aud' => 'cid', 'sub' => 'u1', 'nonce' => 'n1', 'exp' => 2000, 'iat' => 1000];
$t = $jwt(['alg' => 'RS256', 'kid' => 'r1'], $claims, $rsaSign);
$eq('JWT RS256 gültig', Oidc::verifyJwt($t, $rsaJwks)[1]['sub'] ?? null, 'u1');
[$h64, $c64, $s64] = explode('.', $t);
$eq('JWT manipulierte Claims', Oidc::verifyJwt($h64 . '.' . $b(json_encode(['sub' => 'admin'] + $claims)) . '.' . $s64, $rsaJwks), null);
$eq('JWT falsche kid', Oidc::verifyJwt($jwt(['alg' => 'RS256', 'kid' => 'x'], $claims, $rsaSign), $rsaJwks), null);
$eq('JWT alg none abgelehnt', Oidc::verifyJwt($b('{"alg":"none"}') . '.' . $c64 . '.' . $b('x'), $rsaJwks), null);
$eq('JWT HS256 abgelehnt (Algorithmus-Verwechslung)', Oidc::verifyJwt($jwt(['alg' => 'HS256'], $claims, static fn ($in) => hash_hmac('sha256', $in, $rd['n'], true)), $rsaJwks), null);
$other = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
$eq('JWT fremder Schlüssel', Oidc::verifyJwt($jwt(['alg' => 'RS256', 'kid' => 'r1'], $claims, static function (string $in) use ($other): string { openssl_sign($in, $s, $other, OPENSSL_ALGO_SHA256); return $s; }), $rsaJwks), null);
$eq('JWT Müll', Oidc::verifyJwt('a.b', $rsaJwks), null);
$ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
$ed = openssl_pkey_get_details($ec)['ec'];
$ecJwks = ['keys' => [['kty' => 'EC', 'crv' => 'P-256', 'kid' => 'e1', 'x' => $b(str_pad($ed['x'], 32, "\0", STR_PAD_LEFT)), 'y' => $b(str_pad($ed['y'], 32, "\0", STR_PAD_LEFT))]]];
$ecSign = static function (string $in) use ($ec): string { openssl_sign($in, $s, $ec, OPENSSL_ALGO_SHA256); return Oidc::derToRaw($s); };
$eq('JWT ES256 gültig', Oidc::verifyJwt($jwt(['alg' => 'ES256', 'kid' => 'e1'], $claims, $ecSign), $ecJwks)[1]['sub'] ?? null, 'u1');
$kp = sodium_crypto_sign_keypair();
$edJwks = ['keys' => [['kty' => 'OKP', 'crv' => 'Ed25519', 'x' => $b(sodium_crypto_sign_publickey($kp))]]];
$eq('JWT EdDSA gültig', Oidc::verifyJwt($jwt(['alg' => 'EdDSA'], $claims, static fn ($in) => sodium_crypto_sign_detached($in, sodium_crypto_sign_secretkey($kp))), $edJwks)[1]['sub'] ?? null, 'u1');
$eq('Claims gültig', Oidc::checkClaims($claims, 'https://idp.example/', 'cid', 'n1', 1500), null);
$eq('Claims falscher Aussteller', Oidc::checkClaims(['iss' => 'https://evil'] + $claims, 'https://idp.example', 'cid', 'n1', 1500) !== null, true);
$eq('Claims falsche Zielgruppe', Oidc::checkClaims(['aud' => 'other'] + $claims, 'https://idp.example', 'cid', 'n1', 1500) !== null, true);
$eq('Claims mehrere aud ohne azp', Oidc::checkClaims(['aud' => ['cid', 'x']] + $claims, 'https://idp.example', 'cid', 'n1', 1500) !== null, true);
$eq('Claims mehrere aud mit azp', Oidc::checkClaims(['aud' => ['cid', 'x'], 'azp' => 'cid'] + $claims, 'https://idp.example', 'cid', 'n1', 1500), null);
$eq('Claims abgelaufen', Oidc::checkClaims($claims, 'https://idp.example', 'cid', 'n1', 3000) !== null, true);
$eq('Claims falscher nonce', Oidc::checkClaims($claims, 'https://idp.example', 'cid', 'zz', 1500) !== null, true);
$eq('Claims leerer nonce', Oidc::checkClaims(['nonce' => ''] + $claims, 'https://idp.example', 'cid', '', 1500) !== null, true);
$eq('Claims ohne sub', Oidc::checkClaims(['sub' => ''] + $claims, 'https://idp.example', 'cid', 'n1', 1500) !== null, true);
$eq('Claims iat in der Zukunft', Oidc::checkClaims(['iat' => 9000] + $claims, 'https://idp.example', 'cid', 'n1', 1500) !== null, true);
$eq('Adresse normalisiert', Oidc::normalizeIssuer('https://vylaris.tech/.well-known/openid-configuration'), 'https://vylaris.tech');
$eq('Adresse Slash', Oidc::normalizeIssuer('https://vylaris.tech/'), 'https://vylaris.tech');
$eq('Adresse ohne Schema', Oidc::normalizeIssuer('vylaris.tech'), 'https://vylaris.tech');
$eq('Adresse http extern abgelehnt', Oidc::normalizeIssuer('http://vylaris.tech'), null);
$eq('Adresse http localhost ok', Oidc::normalizeIssuer('http://localhost:8096'), 'http://localhost:8096');
$eq('PKCE RFC 7636', Oidc::pkceChallenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'), 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');
$eq('Eingebauter Lizenz-Schlüssel gültig', strlen((string) License::pemToRaw(License::DEFAULT_PUBLIC_KEY)), 32);
$eq('Scope sso vorhanden', License::featureIn(['sso'], 'sso'), true);
$eq('Scope sso fehlt', License::featureIn(['invoices'], 'sso'), false);
$eq('Route SSO → sso', License::featureForPath('/admin/sso/login'), 'sso');

// ---------------------------------------------------------------- Update (GitHub-Releases)
$eq('Repo owner/repo', UpdateService::parseRepo('vylaris/offers'), 'vylaris/offers');
$eq('Repo URL', UpdateService::parseRepo('https://github.com/vylaris/offers.git'), 'vylaris/offers');
$eq('Repo URL mit Pfad', UpdateService::parseRepo('https://github.com/vylaris/offers/releases/tag/v1'), 'vylaris/offers');
$eq('Repo ssh', UpdateService::parseRepo('git@github.com:vylaris/offers.git'), 'vylaris/offers');
$eq('Standard-Repository gültig', UpdateService::parseRepo(UpdateService::DEFAULT_REPO), 'VylarisGroup/vylaris-offers');
$eq('Repo ungültig', UpdateService::parseRepo('nur-ein-name'), null);
$eq('Repo Injektion', UpdateService::parseRepo('a/b; rm -rf'), null);
$eq('Tag v1.2.0', UpdateService::normalizeVersion('v1.2.0'), '1.2.0');
$eq('Tag ungültig', UpdateService::normalizeVersion('latest'), null);
$eq('Version neuer', UpdateService::isNewer('1.10.0', '1.9.0'), true);
$eq('Version gleich', UpdateService::isNewer('1.0.0', '1.0.0'), false);
$eq('Version älter (kein Downgrade)', UpdateService::isNewer('0.9.0', '1.0.0'), false);
$eq('Hauptversion gleich', UpdateService::sameMajor('1.5.0', '1.0.0'), true);
$eq('Hauptversion verschieden', UpdateService::sameMajor('2.0.0', '1.9.0'), false);
$eq('Pfad normal', UpdateService::targetPath('app/lib/DB.php'), 'app/lib/DB.php');
$eq('Pfad Zip-Slip', UpdateService::targetPath('../evil.php'), null);
$eq('Pfad Zip-Slip mitten', UpdateService::targetPath('app/../../evil.php'), null);
$eq('Pfad absolut', UpdateService::targetPath('/etc/passwd'), null);
$eq('Pfad Backslash', UpdateService::targetPath('app\\x.php'), null);
$eq('Pfad Konfiguration geschützt', UpdateService::targetPath('app/config.php'), null);
$eq('Pfad install.lock geschützt', UpdateService::targetPath('install.lock'), null);
$eq('Pfad setup.php geschützt', UpdateService::targetPath('setup.php'), null);
$eq('Pfad storage geschützt', UpdateService::targetPath('storage/uploads/a.pdf'), null);
$eq('Pfad .git übersprungen', UpdateService::targetPath('.git/config'), null);
$eq('Root im Unterordner', UpdateService::detectRoot(['o-r-1/index.php', 'o-r-1/app/bootstrap.php', 'x']), 'o-r-1/');
$eq('Root ohne Unterordner', UpdateService::detectRoot(['index.php', 'app/bootstrap.php']), '');
$eq('Root fehlt', UpdateService::detectRoot(['readme.md']), null);
$eq('Version aus bootstrap', UpdateService::versionInBootstrap("define('APP_VERSION', '2.3.4');"), '2.3.4');
$tmpRoot = sys_get_temp_dir() . '/upd_' . bin2hex(random_bytes(3));
mkdir($tmpRoot . '/app', 0775, true);
file_put_contents($tmpRoot . '/index.php', 'old');
$zf = $tmpRoot . '/t.zip';
$z = new ZipArchive();
$z->open($zf, ZipArchive::CREATE);
$z->addFromString('r/index.php', 'new');
$z->addFromString('r/app/bootstrap.php', "<?php define('APP_VERSION', '9.9.9');");
$z->close();
$eq('Update: falsche Version abgelehnt', UpdateService::installZip($zf, '1.2.3', 't', $tmpRoot, false)['ok'], false);
$eq('Update: Datei unverändert nach Abbruch', file_get_contents($tmpRoot . '/index.php'), 'old');
@unlink($zf);
@unlink($tmpRoot . '/index.php');
@rmdir($tmpRoot . '/app');
@rmdir($tmpRoot);

// ---------------------------------------------------------------- Änderungsprotokoll
$md = "# Titel\n\n## 1.5.0 – 2026-10-16\n### Neu\n- **A** neu\n- B mit `code`\n  und Fortsetzung\n### Behoben\n- C\n\n## 1.4.0 – 2026-09-01\n### Neu\n- D\n\n## 1.0.0\n- E\n";
$cl = Changelog::parse($md);
$eq('Changelog: 3 Versionen', count($cl), 3);
$eq('Changelog: Version/Datum', [$cl[0]['version'], $cl[0]['date']], ['1.5.0', '2026-10-16']);
$eq('Changelog: Abschnitte', array_keys($cl[0]['sections']), ['Neu', 'Behoben']);
$eq('Changelog: Fortsetzungszeile', $cl[0]['sections']['Neu'][1], 'B mit `code` und Fortsetzung');
$eq('Changelog: ohne Unterabschnitt → Neu', $cl[2]['sections']['Neu'][0], 'E');
$eq('Changelog neu seit 1.4.0', array_column(Changelog::pending('1.4.0', $cl, '1.5.0'), 'version'), ['1.5.0']);
$eq('Changelog neu seit 1.0.0', array_column(Changelog::pending('1.0.0', $cl, '1.5.0'), 'version'), ['1.5.0', '1.4.0']);
$eq('Changelog aktuell → nichts', Changelog::pending('1.5.0', $cl, '1.5.0'), []);
$eq('Changelog ohne Stand → nur aktuelle', array_column(Changelog::pending(null, $cl, '1.5.0'), 'version'), ['1.5.0']);
$eq('Changelog: Zukunftsversion nicht anzeigen', array_column(Changelog::pending('1.0.0', $cl, '1.4.0'), 'version'), ['1.4.0']);
$eq('Changelog: HTML maskiert', str_contains(Changelog::inline('<script>x</script> **f**'), '<script>'), false);
$eq('Changelog: fett', str_contains(Changelog::inline('**f**'), '<strong'), true);
$eq('Changelog: echte Datei lesbar', count(Changelog::all()) >= 1, true);
$eq('Changelog: aktuelle Version enthalten', in_array(APP_VERSION, array_column(Changelog::all(), 'version'), true), true);
// Assistent / Seitenleiste (ab 1.6.0)
$wzJs = (string) @file_get_contents(APP_ROOT . '/assets/js/wizard.js');
$eq('Assistent-Skript vorhanden', str_contains($wzJs, 'data-wizard') && str_contains($wzJs, 'wizard:validate'), true);
foreach (['customers/form', 'offers/form'] as $v) {
    $src = (string) file_get_contents(APP_DIR . '/views/admin/' . $v . '.php');
    $eq("Assistent-Schritte in $v", str_contains($src, 'data-wizard') && substr_count($src, 'data-step=') >= 4, true);
}
foreach (['users/index', 'catalog/areas', 'catalog/templates', 'catalog/taxes', 'catalog/shipping'] as $v) {
    $eq("Dialog-Assistent in $v", str_contains((string) file_get_contents(APP_DIR . '/views/admin/' . $v . '.php'), 'data-wizard="auto"'), true);
}
$eq('Seitenleiste mit Gruppen', str_contains((string) file_get_contents(APP_DIR . '/views/layouts/admin.php'), 'data-nav-group'), true);

// Dokumentvorlagen, PWA, Portal (ab 1.7.0)
$c = DocTemplate::sanitize(['accent' => 'rot', 'font' => 'comic', 'font_scale' => 500, 'margin' => 1, 'logo_height' => 99, 'table_style' => 'x', 'logo_pos' => 'diag', 'company_block' => 'y']);
$eq('Vorlage: ungültige Farbe → leer', $c['accent'], '');
$eq('Vorlage: ungültige Schrift → helvetica', $c['font'], 'helvetica');
$eq('Vorlage: Werte begrenzt', [$c['font_scale'], $c['margin'], $c['logo_height']], [110, 15, 30]);
$eq('Vorlage: ungültige Auswahl → Standard', [$c['table_style'], $c['logo_pos'], $c['company_block']], ['filled', 'left', 'full']);
$eq('Vorlage: Farbe normalisiert', DocTemplate::sanitize(['accent' => '#0D9488'])['accent'], '#0d9488');
$pwaSrc = (string) file_get_contents(APP_DIR . '/services/Pwa.php');
$eq('PWA: Service Worker cached keine Seiten', str_contains($pwaSrc, "req.mode === 'navigate'") && str_contains($pwaSrc, 'caches.match(OFFLINE)'), true);
$eq('PWA: Scope und Start-URL im Manifest', str_contains($pwaSrc, "'portal/' : 'admin/'"), true);
$eq('PWA: Symbole maskable', str_contains($pwaSrc, "'purpose' => 'maskable'"), true);
$eq('Portal: Nachricht leer → Fehler', (function () { try { PortalMessage::add(1, 'in', '  ', 'x'); return false; } catch (ValidationException) { return true; } })(), true);
$eq('Portal: Bezug mit Unsinn → allgemein', PortalMessage::parseRef(1, 'order:1;DROP'), ['general', null]);
foreach (['messages', 'documents', 'profile'] as $v) {
    $eq("Portal-Ansicht $v vorhanden", is_file(APP_DIR . "/views/portal/$v.php"), true);
}
$eq('Admin: Dokumentvorlagen im Menü', str_contains((string) file_get_contents(APP_DIR . '/views/layouts/admin.php'), 'admin/doc-templates'), true);

$eq('Portal-Verwaltung vorhanden', is_file(APP_DIR . '/views/admin/portal/index.php') && is_file(APP_DIR . '/controllers/PortalAdminController.php'), true);
$eq('Lizenz: Admin-Nachrichten brauchen portal', License::featureForPath('/admin/messages/3'), 'portal');
$eq('Lizenz: Portalverwaltung bleibt erreichbar', License::featureForPath('/admin/portal'), null);

$wm = Brand::watermark();
$eq('Watermark: Text und Link', str_contains($wm, 'Powered by') && str_contains($wm, 'href="https://vylaris.ch"') && str_contains($wm, 'VylarisOffers'), true);
$eq('Watermark: Platzhalter wird ersetzt', substr_count(Brand::enforceWatermark('<aside>x' . Brand::WATERMARK_SLOT . '</aside></body>'), 'data-powered-by'), 1);
$eq('Watermark: ohne Platzhalter fest eingefügt', str_contains(Brand::enforceWatermark('<html><body>x</body></html>'), 'position:fixed'), true);
$eq('Watermark: manipulierte Variante ersetzt', substr_count(Brand::enforceWatermark('<aside><div data-powered-by style="display:none">Powered by X</div>' . Brand::WATERMARK_SLOT . '</aside></body>'), 'data-powered-by'), 1);
$eq('Watermark: Layout nutzt Platzhalter', str_contains((string) file_get_contents(APP_DIR . '/views/layouts/admin.php'), 'Brand::WATERMARK_SLOT'), true);
$eq('Watermark: View erzwingt sie für das Admin-Layout', str_contains((string) file_get_contents(APP_DIR . '/lib/View.php'), 'enforceWatermark'), true);

echo "\n$pass bestanden, $fail fehlgeschlagen\n";
exit($fail ? 1 : 0);
