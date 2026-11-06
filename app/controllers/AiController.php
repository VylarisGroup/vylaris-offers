<?php
declare(strict_types=1);

/** KI-Assistent (nur Administratoren): OpenAI-kompatibles Gateway, Import von Positionen aus Links. */
final class AiController
{
    public function index(): void
    {
        Auth::requireAdmin();
        $s = [];
        foreach (['ai_enabled', 'ai_base_url', 'ai_model', 'ai_json_mode', 'ai_timeout', 'ai_hint'] as $k) {
            $s[$k] = (string) Settings::get($k, Settings::DEFAULTS[$k] ?? '');
        }
        $s['key_set'] = (string) Settings::get('ai_api_key', '') !== '';
        $imports = DB::all("SELECT created_at, detail FROM audit_log WHERE action = 'ai_import' ORDER BY id DESC LIMIT 8");
        view('admin/ai/index', ['s' => $s, 'imports' => $imports, 'ready' => AiService::enabled()]);
    }

    public function save(): void
    {
        $user = Auth::requireAdmin();
        $base = rtrim(trim(input_str('ai_base_url', 300)), '/');
        $model = input_str('ai_model', 120);
        $enabled = !empty($_POST['ai_enabled']);
        if ($base !== '' && ($e = AiService::validateBaseUrl($base))) {
            flash('error', $e);
            redirect('admin/ai');
        }
        if ($enabled && ($base === '' || $model === '')) {
            flash('error', 'Zum Aktivieren werden Gateway-Adresse und Modellname benötigt.');
            redirect('admin/ai');
        }
        $values = [
            'ai_enabled'   => $enabled ? '1' : '0',
            'ai_base_url'  => $base,
            'ai_model'     => $model,
            'ai_json_mode' => !empty($_POST['ai_json_mode']) ? '1' : '0',
            'ai_timeout'   => (string) max(10, min(180, input_int('ai_timeout') ?: 60)),
            'ai_hint'      => input_str('ai_hint', 600),
        ];
        $key = trim((string) ($_POST['ai_api_key'] ?? ''));
        if (!empty($_POST['ai_key_clear'])) {
            $values['ai_api_key'] = '';
        } elseif ($key !== '' && $key !== '••••••••') {
            $values['ai_api_key'] = $key;
        }
        Settings::set($values);
        Audit::log('ai_settings', ($enabled ? 'aktiviert' : 'deaktiviert') . ' · ' . $base . ' · ' . $model, null, null, $user);
        if (!empty($_POST['test'])) {
            [$ok, $msg] = AiService::test();
            flash($ok ? 'success' : 'error', ($ok ? 'Gespeichert. ' : 'Gespeichert, aber der Test ist fehlgeschlagen: ') . $msg);
        } else {
            flash('success', 'KI-Einstellungen gespeichert.');
        }
        redirect('admin/ai');
    }

    /** AJAX aus dem Offerten-Editor: Position aus einem Link erzeugen. */
    public function importUrl(): void
    {
        $user = Auth::requireUser();
        if (!AiService::enabled()) {
            json_response(['ok' => false, 'error' => 'Die KI-Anbindung ist nicht aktiviert.'], 403);
        }
        $bucket = 'ai-import:' . substr(hash('sha256', session_id()), 0, 16);
        if (RateLimit::tooMany($bucket, 20, 600)) {
            json_response(['ok' => false, 'error' => 'Zu viele Importe in kurzer Zeit. Bitte kurz warten.'], 429);
        }
        RateLimit::hit($bucket);
        $url = input_str('url', 2000);
        if ($url === '') {
            json_response(['ok' => false, 'error' => 'Bitte einen Link angeben.'], 422);
        }
        try {
            $item = UrlImporter::import($url);
        } catch (RuntimeException $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            ErrorHandler::log($e);
            json_response(['ok' => false, 'error' => 'Der Import ist fehlgeschlagen.'], 500);
        }
        Audit::log('ai_import', mb_substr($item['source'], 0, 200), null, null, $user);
        json_response(['ok' => true, 'item' => $item, 'image_url' => ItemImage::url($item['image'] ?: null)]);
    }
}
