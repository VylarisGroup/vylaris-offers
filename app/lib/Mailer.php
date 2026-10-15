<?php
/**
 * E-Mail-Versand ohne externe Abhängigkeiten.
 * Transporte: smtp (STARTTLS/SSL, AUTH LOGIN/PLAIN), phpmail (mail()), log (Datei, für Tests).
 */
declare(strict_types=1);

final class Mailer
{
    /** @var resource|null */
    private $socket = null;
    private array $cfg;
    private array $transcript = [];

    public function __construct(?array $cfg = null)
    {
        $this->cfg = $cfg ?? [
            'transport'  => Settings::get('mail_transport', 'smtp'),
            'host'       => Settings::get('smtp_host', ''),
            'port'       => (int) Settings::get('smtp_port', 587),
            'encryption' => Settings::get('smtp_encryption', 'tls'),
            'user'       => Settings::get('smtp_user', ''),
            'pass'       => Settings::get('smtp_pass', ''),
            'from_email' => Settings::get('mail_from_email', '') ?: Settings::companyEmail(),
            'from_name'  => Settings::get('mail_from_name', '') ?: Settings::get('company_name', ''),
        ];
    }

    /** Sendet eine E-Mail und protokolliert das Ergebnis. Wirft bei Fehlern eine Exception. */
    public function send(string $to, string $subject, string $html, ?string $text = null, string $context = '', ?string $replyTo = null, array $attachments = []): void
    {
        $text ??= self::htmlToText($html);
        try {
            if (!valid_email($to)) {
                throw new RuntimeException("Ungültige Empfängeradresse: $to");
            }
            if (!valid_email((string) $this->cfg['from_email'])) {
                throw new RuntimeException('Es ist keine gültige Absenderadresse konfiguriert (Einstellungen → E-Mail).');
            }
            $message = $this->buildMessage($to, $subject, $html, $text, $replyTo, $attachments);

            match ($this->cfg['transport']) {
                'log'     => $this->sendLog($to, $subject, $message),
                'phpmail' => $this->sendPhpMail($to, $subject, $message),
                default   => $this->sendSmtp($to, $message),
            };
            self::log($to, $subject, 'sent', null, $context);
        } catch (Throwable $e) {
            self::log($to, $subject, 'failed', $e->getMessage(), $context);
            throw $e;
        }
    }

    public function transcript(): array
    {
        return $this->transcript;
    }

    private static function log(string $to, string $subject, string $status, ?string $error, string $context): void
    {
        try {
            DB::insert('mail_log', [
                'recipient'  => mb_substr($to, 0, 190),
                'subject'    => mb_substr($subject, 0, 255),
                'status'     => $status,
                'error'      => $error ? mb_substr($error, 0, 2000) : null,
                'context'    => mb_substr($context, 0, 60),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Protokollierung darf den Versand nicht verhindern.
        }
    }

    /** @param array<int,array{name:string,data:string,mime:string}> $attachments */
    private function buildMessage(string $to, string $subject, string $html, string $text, ?string $replyTo, array $attachments = []): array
    {
        $boundary = 'b_' . bin2hex(random_bytes(12));
        $fromEmail = (string) $this->cfg['from_email'];
        $domain = substr((string) strrchr($fromEmail, '@'), 1) ?: 'localhost';

        $headers = [
            'Date'                      => date('r'),
            'From'                      => self::address($fromEmail, (string) $this->cfg['from_name']),
            'To'                        => self::address($to),
            'Subject'                   => self::encodeHeader($subject),
            'Message-ID'                => '<' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'MIME-Version'              => '1.0',
            'Content-Type'              => 'multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer'                  => Brand::userAgent(),
        ];
        if ($replyTo && valid_email($replyTo)) {
            $headers['Reply-To'] = self::address($replyTo);
        }

        $body = "This is a multi-part message in MIME format.\r\n\r\n"
            . "--$boundary\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text), 76, "\r\n")
            . "\r\n--$boundary\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html), 76, "\r\n")
            . "\r\n--$boundary--\r\n";

        if ($attachments) {
            $outer = 'm_' . bin2hex(random_bytes(12));
            $headers['Content-Type'] = 'multipart/mixed; boundary="' . $outer . '"';
            $mixed = "This is a multi-part message in MIME format.\r\n\r\n--$outer\r\n"
                . 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n\r\n"
                . substr($body, strlen("This is a multi-part message in MIME format.\r\n\r\n")) . "\r\n";
            foreach ($attachments as $a) {
                $fn = preg_replace('/[^A-Za-z0-9._\- ]/', '_', (string) $a['name']);
                $mixed .= "--$outer\r\n"
                    . 'Content-Type: ' . $a['mime'] . '; name="' . $fn . "\"\r\n"
                    . "Content-Transfer-Encoding: base64\r\n"
                    . 'Content-Disposition: attachment; filename="' . $fn . "\"\r\n\r\n"
                    . chunk_split(base64_encode($a['data']), 76, "\r\n") . "\r\n";
            }
            $body = $mixed . "--$outer--\r\n";
        }

        return ['headers' => $headers, 'body' => $body, 'from' => $fromEmail];
    }

    private static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value)
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }

    private static function address(string $email, string $name = ''): string
    {
        $email = str_replace(["\r", "\n", '<', '>'], '', $email);
        if ($name === '') {
            return $email;
        }
        $name = str_replace(["\r", "\n", '"'], '', $name);
        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    private static function headerString(array $headers, array $skip = []): string
    {
        $out = '';
        foreach ($headers as $k => $v) {
            if (!in_array($k, $skip, true)) {
                $out .= "$k: $v\r\n";
            }
        }
        return $out;
    }

    private function sendLog(string $to, string $subject, array $m): void
    {
        $dir = APP_ROOT . '/storage/mail';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $file = $dir . '/' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.eml';
        file_put_contents($file, self::headerString($m['headers']) . "\r\n" . $m['body']);
    }

    private function sendPhpMail(string $to, string $subject, array $m): void
    {
        $headers = self::headerString($m['headers'], ['To', 'Subject']);
        $ok = mail($to, $m['headers']['Subject'], $m['body'], rtrim($headers), '-f' . escapeshellarg($m['from']));
        if (!$ok) {
            throw new RuntimeException('PHP mail() konnte die Nachricht nicht übergeben.');
        }
    }

    /* ---------------------------------------------------------------- SMTP */

    private function sendSmtp(string $to, array $m): void
    {
        $host = trim((string) $this->cfg['host']);
        if ($host === '') {
            throw new RuntimeException('Es ist kein SMTP-Server konfiguriert (Einstellungen → E-Mail).');
        }
        $port = (int) $this->cfg['port'] ?: 587;
        $enc = (string) $this->cfg['encryption'];
        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;

        $ctx = stream_context_create(['ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
            'peer_name'        => $host,
            'SNI_enabled'      => true,
        ]]);

        $errno = 0;
        $errstr = '';
        $this->socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->socket) {
            throw new RuntimeException("Verbindung zu $host:$port fehlgeschlagen: $errstr ($errno)");
        }
        stream_set_timeout($this->socket, 20);

        try {
            $this->expect([220]);
            $ehloHost = parse_url((string) app_config('app.base_url', ''), PHP_URL_HOST) ?: 'localhost';
            $caps = $this->ehlo($ehloHost);

            if ($enc === 'tls') {
                if (!str_contains($caps, 'STARTTLS')) {
                    throw new RuntimeException('Der Server unterstützt kein STARTTLS. Bitte Verschlüsselung prüfen (SSL/Port 465?).');
                }
                $this->cmd('STARTTLS', [220]);
                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                if (!@stream_socket_enable_crypto($this->socket, true, $crypto)) {
                    throw new RuntimeException('TLS-Verschlüsselung konnte nicht aufgebaut werden (Zertifikat?).');
                }
                $caps = $this->ehlo($ehloHost);
            }

            $user = (string) $this->cfg['user'];
            if ($user !== '') {
                if (preg_match('/AUTH[ =][^\r\n]*PLAIN/i', $caps) && !preg_match('/AUTH[ =][^\r\n]*LOGIN/i', $caps)) {
                    $this->cmd('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $this->cfg['pass']), [235], true);
                } else {
                    $this->cmd('AUTH LOGIN', [334]);
                    $this->cmd(base64_encode($user), [334], true);
                    $this->cmd(base64_encode((string) $this->cfg['pass']), [235], true);
                }
            }

            $this->cmd('MAIL FROM:<' . $m['from'] . '>', [250]);
            $this->cmd('RCPT TO:<' . $to . '>', [250, 251]);
            $this->cmd('DATA', [354]);

            $data = self::headerString($m['headers']) . "\r\n" . $m['body'];
            $data = preg_replace('/(?<!\r)\n/', "\r\n", $data);
            $data = preg_replace('/^\./m', '..', $data); // Dot-Stuffing
            fwrite($this->socket, $data . "\r\n.\r\n");
            $this->expect([250]);
            $this->cmd('QUIT', [221, 250], false, true);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    private function ehlo(string $host): string
    {
        return $this->cmd('EHLO ' . $host, [250]);
    }

    private function cmd(string $command, array $expect, bool $secret = false, bool $lenient = false): string
    {
        $this->transcript[] = 'C: ' . ($secret ? '[geschützt]' : $command);
        fwrite($this->socket, $command . "\r\n");
        try {
            return $this->expect($expect);
        } catch (RuntimeException $e) {
            if ($lenient) {
                return '';
            }
            throw $e;
        }
    }

    private function expect(array $codes): string
    {
        $response = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $this->transcript[] = 'S: ' . trim($response);
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            $meta = stream_get_meta_data($this->socket);
            if ($response === '' && !empty($meta['timed_out'])) {
                throw new RuntimeException('Zeitüberschreitung bei der Kommunikation mit dem SMTP-Server.');
            }
            throw new RuntimeException('SMTP-Fehler: ' . (trim($response) ?: 'keine Antwort'));
        }
        return $response;
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $html);
        $html = preg_replace('#<(br|/p|/div|/tr|/h\d|/li)[^>]*>#i', "\n", (string) $html);
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        return trim((string) preg_replace("/\n\s*\n\s*\n+/", "\n\n", (string) $text));
    }
}
