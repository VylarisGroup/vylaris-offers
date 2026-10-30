<?php
declare(strict_types=1);

/** Produktbereiche, Positionsvorlagen, Steuervorlagen und Versandarten. */
final class CatalogController
{
    /* ---------------------------------------------------------------- Produktbereiche */

    public function areas(): void
    {
        Auth::requireUser();
        $areas = DB::all(
            'SELECT p.*, (SELECT COUNT(*) FROM offers o WHERE o.product_area_id = p.id) AS offer_count,
                    (SELECT COUNT(*) FROM item_templates t WHERE t.product_area_id = p.id) AS template_count
             FROM product_areas p ORDER BY p.sort, p.name'
        );
        view('admin/catalog/areas', compact('areas'));
    }

    public function saveArea(): void
    {
        Auth::requireUser();
        $id = input_int('id');
        $data = [
            'name'        => input_str('name', 120),
            'type'        => input('type') === 'physical' ? 'physical' : 'digital',
            'description' => input_str('description', 2000) ?: null,
            'icon'        => preg_match('/^bx[sl]?-[a-z0-9-]+$/', input_str('icon', 60)) ? input_str('icon', 60) : 'bx-category',
            'active'      => !empty($_POST['active']) ? 1 : 0,
            'sort'        => (int) input('sort', 0),
        ];
        if ($data['name'] === '') {
            flash('error', 'Bitte geben Sie einen Namen ein.');
            redirect('admin/areas');
        }
        if ($id) {
            if ($data['type'] !== DB::val('SELECT type FROM product_areas WHERE id = ?', [$id]) && DB::val('SELECT 1 FROM offers WHERE product_area_id = ? LIMIT 1', [$id])) {
                flash('error', 'Die Kategorie (Digital/Physisch) kann nicht mehr geändert werden, da bereits Offerten existieren.');
                redirect('admin/areas');
            }
            DB::update('product_areas', $data, 'id = :id', [':id' => $id]);
        } else {
            $data['created_at'] = now();
            DB::insert('product_areas', $data);
        }
        flash('success', 'Produktbereich „' . $data['name'] . '“ gespeichert.');
        redirect('admin/areas');
    }

    public function deleteArea(string $id): void
    {
        Auth::requireUser();
        if (DB::val('SELECT 1 FROM offers WHERE product_area_id = ? LIMIT 1', [(int) $id])) {
            flash('error', 'Dieser Produktbereich wird in Offerten verwendet. Deaktivieren Sie ihn stattdessen.');
        } else {
            DB::exec('DELETE FROM product_areas WHERE id = ?', [(int) $id]);
            flash('success', 'Produktbereich gelöscht.');
        }
        redirect('admin/areas');
    }

    /* ---------------------------------------------------------------- Positionsvorlagen */

    public function templates(): void
    {
        Auth::requireUser();
        $templates = DB::all(
            'SELECT t.*, p.name AS area_name, p.icon AS area_icon, x.label AS tax_label, x.rate AS tax_rate, x.country AS tax_country
             FROM item_templates t LEFT JOIN product_areas p ON p.id = t.product_area_id LEFT JOIN tax_templates x ON x.id = t.tax_template_id
             ORDER BY t.title'
        );
        $areas = DB::all('SELECT * FROM product_areas ORDER BY sort, name');
        $taxes = TaxService::all();
        view('admin/catalog/templates', compact('templates', 'areas', 'taxes'));
    }

    private function templateData(): array
    {
        $area = input_int('product_area_id');
        $tax = input_int('tax_template_id');
        return [
            'title'           => input_str('title', 190),
            'description'     => input_str('description', 5000) ?: null,
            'unit'            => input_str('unit', 30) ?: 'Stk.',
            'unit_price'      => Calc::r2(parse_number(input('unit_price', 0))),
            'product_area_id' => $area && DB::val('SELECT 1 FROM product_areas WHERE id = ?', [$area]) ? $area : null,
            'tax_template_id' => $tax && TaxService::find($tax) ? $tax : null,
            'image'           => ItemImage::clean($_POST['image'] ?? null),
        ];
    }

    public function saveTemplate(): void
    {
        Auth::requireUser();
        $id = input_int('id');
        $data = $this->templateData();
        $data['active'] = !empty($_POST['active']) ? 1 : 0;
        if ($data['title'] === '') {
            flash('error', 'Bitte geben Sie einen Titel ein.');
            redirect('admin/templates');
        }
        if ($id) {
            $data['updated_at'] = now();
            DB::update('item_templates', $data, 'id = :id', [':id' => $id]);
        } else {
            $data['created_at'] = now();
            DB::insert('item_templates', $data);
        }
        flash('success', 'Vorlage „' . $data['title'] . '“ gespeichert.');
        redirect('admin/templates');
    }

    /** AJAX aus dem Offerten-Editor: Position als Vorlage speichern. */
    public function quickTemplate(): void
    {
        Auth::requireUser();
        $data = $this->templateData();
        if ($data['title'] === '') {
            json_response(['ok' => false, 'error' => 'Die Position braucht einen Titel.'], 422);
        }
        $data['active'] = 1;
        $data['created_at'] = now();
        $id = DB::insert('item_templates', $data);
        json_response(['ok' => true, 'template' => array_merge($data, ['id' => $id])]);
    }

    public function deleteTemplate(string $id): void
    {
        Auth::requireUser();
        DB::exec('DELETE FROM item_templates WHERE id = ?', [(int) $id]);
        flash('success', 'Vorlage gelöscht.');
        redirect('admin/templates');
    }

    /* ---------------------------------------------------------------- Steuervorlagen */

    public function taxes(): void
    {
        Auth::requireAdmin();
        $taxes = TaxService::all(false);
        view('admin/catalog/taxes', compact('taxes'));
    }

    public function saveTax(): void
    {
        Auth::requireAdmin();
        $id = input_int('id');
        $class = (string) input('tax_class', 'taxable');
        if (!isset(TaxService::CLASSES[$class])) {
            $class = 'taxable';
        }
        $c = TaxService::CLASSES[$class];
        $vf = InvoiceService::validDate(input_str('valid_from', 10));
        $vt = InvoiceService::validDate(input_str('valid_to', 10));
        $data = [
            'country'    => 'CH',
            'name'       => input_str('name', 120),
            'label'      => $class === 'taxable' ? input_str('label', 40) : $c['label'],   // Sonderarten: feste Kennzeichnung
            'rate'       => $c['taxed'] ? round(parse_number(input('rate', 0)), 3) : 0.0,
            'tax_class'  => $class,
            'valid_from' => $vf,
            'valid_to'   => $vt,
            'is_default' => !empty($_POST['is_default']) && $class === 'taxable' ? 1 : 0,
            'active'     => !empty($_POST['active']) ? 1 : 0,
            'sort'       => (int) input('sort', 0),
        ];
        if ($data['name'] === '' || $data['label'] === '') {
            flash('error', 'Bitte geben Sie Bezeichnung und Kurzbezeichnung ein.');
            redirect('admin/taxes');
        }
        if ($c['taxed'] && ($data['rate'] <= 0 || $data['rate'] > 30)) {
            flash('error', 'Der Steuersatz muss grösser als 0 % und höchstens 30 % sein.');
            redirect('admin/taxes');
        }
        if ($class === 'taxable' && $data['label'] === TaxService::CLASSES['option']['label']) {
            flash('error', 'Diese Kurzbezeichnung ist der Steuerart «Option» vorbehalten.');
            redirect('admin/taxes');
        }
        if ($vf && $vt && $vf > $vt) {
            flash('error', '«Gültig ab» darf nicht nach «Gültig bis» liegen.');
            redirect('admin/taxes');
        }
        DB::tx(static function () use ($id, $data): void {
            if ($data['is_default']) {
                DB::exec('UPDATE tax_templates SET is_default = 0 WHERE id <> ?', [$id]);
            }
            if ($id) {
                DB::update('tax_templates', $data, 'id = :id', [':id' => $id]);
            } else {
                DB::insert('tax_templates', $data);
            }
        });
        Audit::log('tax_saved', $data['name'] ?? null, 'tax', $id ?: null);
        flash('success', 'Steuervorlage gespeichert. Bestehende Offerten bleiben unverändert.');
        redirect('admin/taxes');
    }

    public function deleteTax(string $id): void
    {
        Auth::requireAdmin();
        DB::exec('DELETE FROM tax_templates WHERE id = ?', [(int) $id]);
        Audit::log('tax_deleted', null, 'tax', (int) $id);
        flash('success', 'Steuervorlage gelöscht. Bestehende Offerten bleiben unverändert.');
        redirect('admin/taxes');
    }

    /* ---------------------------------------------------------------- Versandarten */

    public function shipping(): void
    {
        Auth::requireUser();
        $methods = DB::all('SELECT * FROM shipping_methods ORDER BY sort, name');
        view('admin/catalog/shipping', compact('methods'));
    }

    public function saveShipping(): void
    {
        Auth::requireUser();
        $id = input_int('id');
        $data = [
            'name'        => input_str('name', 120),
            'description' => input_str('description', 255) ?: null,
            'cost'        => max(0, Calc::r2(parse_number(input('cost', 0)))),
            'active'      => !empty($_POST['active']) ? 1 : 0,
            'sort'        => (int) input('sort', 0),
        ];
        if ($data['name'] === '') {
            flash('error', 'Bitte geben Sie einen Namen ein.');
            redirect('admin/shipping');
        }
        $id ? DB::update('shipping_methods', $data, 'id = :id', [':id' => $id]) : DB::insert('shipping_methods', $data);
        flash('success', 'Versandart „' . $data['name'] . '“ gespeichert.');
        redirect('admin/shipping');
    }

    public function deleteShipping(string $id): void
    {
        Auth::requireUser();
        DB::exec('DELETE FROM shipping_methods WHERE id = ?', [(int) $id]);
        flash('success', 'Versandart gelöscht.');
        redirect('admin/shipping');
    }
}
