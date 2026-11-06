<?php
/** Anbindung an ein OpenAI-kompatibles Gateway (POST {Basis-URL}/chat/completions). */
declare(strict_types=1);

final class AiService
{
    public static function enabled(): bool
    {
        return Settings::get('ai_enabled') === '1' && self::baseUrl() !== '' && (string) Settings::get('ai_model') !== '';
    }

    public static function baseUrl(): string
    {
        $u = rtrim(trim((string) Settings::get('ai_base_url', '')), '/');
        return preg_replace('#/chat/completions$#', '', $u) ?? $u;
    }

    public static function validateBaseUrl(string $url): ?string
    {
        $p = parse_url($url);
        if (!$p || empty($p['host']) || !in_array($p['scheme'] ?? '', ['http', 'https'], true) || isset($p['user']) || isset($p['pass'])) {
            return 'Die Gateway-Adresse muss mit http:// oder https:// beginnen (ohne Zugangsdaten in der URL).';
        }
        return null;
    }

    /**
     * Sendet eine Chat-Anfrage und gibt den Text der Antwort zurück.
     * @throws RuntimeException mit verständlicher Fehlermeldung
     */
    public static function chat(array $messages, bool $json = false, int $maxTokens = 1200): string
    {
        $useJson = $json && Settings::get('ai_json_mode') === '1';
        [$http, $resp, $err] = self::request($messages, $useJson, $maxTokens);
        if ($useJson && $http === 400) {
            [$http, $resp, $err] = self::request($messages, false, $maxTokens);   // Gateway kennt response_format nicht
        }
        if ($err) {
            throw new RuntimeException('KI-Gateway nicht erreichbar: ' . $err);
        }
        $data = json_decode((string) $resp, true);
        if ($http >= 300) {
            $msg = is_array($data) ? ($data['error']['message'] ?? $data['message'] ?? $data['detail'] ?? null) : null;
            throw new RuntimeException('KI-Gateway antwortet mit HTTP ' . $http . (is_string($msg) ? ': ' . mb_substr($msg, 0, 200) : '.'));
        }
        $text = $data['choices'][0]['message']['content'] ?? null;
        if (is_array($text)) {   // Teile-Format
            $text = implode('', array_map(static fn($p) => is_array($p) ? (string) ($p['text'] ?? '') : (string) $p, $text));
        }
        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException('Das KI-Gateway lieferte keine Antwort im erwarteten Format (choices[0].message.content).');
        }
        return trim($text);
    }

    /** @return array{0:int,1:string|null,2:string|null} */
    private static function request(array $messages, bool $json, int $maxTokens): array
    {
        $body = ['model' => (string) Settings::get('ai_model'), 'messages' => $messages, 'temperature' => 0.2, 'max_tokens' => $maxTokens];
        if ($json) {
            $body['response_format'] = ['type' => 'json_object'];
        }
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'User-Agent: ' . Brand::userAgent()];
        $key = (string) Settings::get('ai_api_key', '');
        if ($key !== '') {
            $headers[] = 'Authorization: Bearer ' . $key;
        }
        $ch = curl_init(self::baseUrl() . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => max(10, min(180, (int) Settings::get('ai_timeout', 60))),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $resp = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);
        return [$http, is_string($resp) ? $resp : null, $err];
    }

    /** Verbindungstest: Rückgabe [ok, Meldung]. */
    public static function test(): array
    {
        try {
            $t0 = microtime(true);
            $a = self::chat([['role' => 'user', 'content' => 'Antworte nur mit dem Wort OK.']], false, 20);
            return [true, 'Verbindung erfolgreich (' . round((microtime(true) - $t0) * 1000) . ' ms). Antwort: «' . mb_substr($a, 0, 60) . '»'];
        } catch (RuntimeException $e) {
            return [false, $e->getMessage()];
        }
    }

    /** Entfernt Codezäune und liest das erste JSON-Objekt aus einer Antwort. */
    public static function parseJson(string $text): ?array
    {
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)) ?? $text);
        $d = json_decode($text, true);
        if (!is_array($d) && ($a = strpos($text, '{')) !== false && ($b = strrpos($text, '}')) !== false) {
            $d = json_decode(substr($text, $a, $b - $a + 1), true);
        }
        return is_array($d) ? $d : null;
    }
}
