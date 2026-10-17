<?php
declare(strict_types=1);

/** Dokumentvorlagen (PDF-Layout) – nur Administratoren. */
final class DocTemplateController
{
    public function index(): void
    {
        Auth::requireAdmin();
        $templates = DocTemplate::all();
        $usage = [];
        foreach ($templates as $t) {
            $usage[(int) $t['id']] = DocTemplate::usage((int) $t['id']);
        }
        view('admin/doc-templates/index', compact('templates', 'usage'));
    }

    public function create(): void
    {
        Auth::requireAdmin();
        $copy = ($cid = input_int('copy')) ? DocTemplate::find($cid) : null;
        $tpl = ['id' => null, 'name' => $copy ? 'Kopie von ' . $copy['name'] : '', 'doc_type' => $copy['doc_type'] ?? 'all', 'is_default_offer' => 0, 'is_default_invoice' => 0];
        view('admin/doc-templates/form', ['tpl' => $tpl, 'cfg' => DocTemplate::config($copy), 'error' => null]);
    }

    public function edit(string $id): void
    {
        Auth::requireAdmin();
        $tpl = DocTemplate::find((int) $id);
        if (!$tpl) {
            throw new HttpException(404, 'Vorlage nicht gefunden.');
        }
        view('admin/doc-templates/form', ['tpl' => $tpl, 'cfg' => DocTemplate::config($tpl), 'error' => null]);
    }

    public function store(): void
    {
        $this->persist(null);
    }

    public function update(string $id): void
    {
        $this->persist((int) $id);
    }

    private function persist(?int $id): void
    {
        Auth::requireAdmin();
        $cfg = DocTemplate::sanitize($_POST);
        $name = input_str('name', 120);
        $type = (string) input('doc_type', 'all');
        try {
            $newId = DocTemplate::save($id, $name, $type, $cfg);
        } catch (ValidationException $e) {
            $tpl = ($id ? DocTemplate::find($id) : null) ?? ['id' => null, 'is_default_offer' => 0, 'is_default_invoice' => 0];
            $tpl['name'] = $name;
            $tpl['doc_type'] = $type;
            view('admin/doc-templates/form', ['tpl' => $tpl, 'cfg' => $cfg, 'error' => $e->getMessage()]);
        }
        Audit::log('doc_template_saved', $name, 'doc_template', $newId);
        flash('success', 'Dokumentvorlage „' . $name . '“ gespeichert.');
        redirect(!empty($_POST['stay']) ? 'admin/doc-templates/' . $newId : 'admin/doc-templates');
    }

    public function delete(string $id): void
    {
        Auth::requireAdmin();
        try {
            DocTemplate::delete((int) $id);
            Audit::log('doc_template_deleted', null, 'doc_template', (int) $id);
            flash('success', 'Dokumentvorlage gelöscht. Dokumente, die sie verwendeten, nutzen jetzt die Standardvorlage.');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/doc-templates');
    }

    public function makeDefault(string $id, string $type): void
    {
        Auth::requireAdmin();
        try {
            DocTemplate::setDefault((int) $id, $type === 'invoice' ? 'invoice' : 'offer');
            flash('success', 'Standardvorlage für ' . ($type === 'invoice' ? 'Rechnungen' : 'Offerten') . ' festgelegt.');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/doc-templates');
    }

    /** PDF-Vorschau mit Beispieldaten. Entweder gespeicherte Vorlage (`id`) oder die Formularwerte aus der Adresszeile. */
    public function preview(): void
    {
        Auth::requireAdmin();
        $kind = input('kind') === 'invoice' ? 'invoice' : 'offer';
        $id = input_int('id');
        $cfg = $id && ($t = DocTemplate::find($id)) ? DocTemplate::config($t) : DocTemplate::sanitize($_GET);
        PdfService::$forceConfig = $cfg;
        try {
            $doc = $kind === 'invoice' ? SamplePdf::invoice() : SamplePdf::offer();
            $pdf = $kind === 'invoice' ? PdfService::invoice($doc) : PdfService::offer($doc);
        } finally {
            PdfService::$forceConfig = null;
        }
        PdfService::send($pdf, 'Vorschau.pdf', true);
    }
}
