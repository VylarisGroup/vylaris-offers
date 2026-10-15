<?php
declare(strict_types=1);

/**
 * Schweizer Mehrwertsteuer (MWST): Steuervorlagen (Normalsatz 8.1 %, reduzierter Satz 2.6 %, Sondersatz Beherbergung 3.8 %,
 * seit 1.1.2024) sowie die Einstellung, ob das Unternehmen überhaupt MWST-pflichtig ist.
 * Nicht MWST-pflichtige Unternehmen (Umsatz unter CHF 100'000) dürfen keine MWST ausweisen – dann steht nur „Ohne MWST“ zur Verfügung.
 */
final class TaxService
{
    private static ?array $cache = null;

    /**
     * Steuerarten. „ziff“ = Ziffer der ESTV-MWST-Abrechnung, „taxed“ = es wird Steuer berechnet.
     * Die Kurzbezeichnung (label) ist bei den Sonderarten fest vorgegeben; so lässt sich die Art auch aus bereits
     * ausgestellten Belegen (Momentaufnahme von Satz und Bezeichnung) sicher bestimmen.
     */
    public const CLASSES = [
        'taxable'     => ['name' => 'Steuerpflichtig (Inland)', 'label' => 'MWST', 'ziff' => null, 'taxed' => true, 'note' => null],
        'option'      => ['name' => 'Freiwillig versteuert – Option (Art. 22 MWSTG)', 'label' => 'MWST (Option)', 'ziff' => '205', 'taxed' => true, 'note' => null],
        'export'      => ['name' => 'Ausfuhr – von der Steuer befreit (Art. 23 MWSTG)', 'label' => 'Ausfuhr, steuerbefreit', 'ziff' => '220', 'taxed' => false,
            'note' => 'Von der Steuer befreite Leistung (Ausfuhr, Art. 23 MWSTG). Nachweis: Zoll-/Versanddokumente.'],
        'abroad'      => ['name' => 'Im Ausland erbracht (Art. 8 MWSTG)', 'label' => 'Im Ausland erbracht', 'ziff' => '221', 'taxed' => false,
            'note' => 'Leistung im Ausland erbracht (Art. 8 Abs. 1 MWSTG), in der Schweiz nicht steuerbar. Eine allfällige Steuer im Empfängerland ist vom Leistungsempfänger zu deklarieren (Reverse Charge).'],
        'reporting'   => ['name' => 'Übertragung im Meldeverfahren (Art. 38 MWSTG)', 'label' => 'Meldeverfahren', 'ziff' => '225', 'taxed' => false,
            'note' => 'Übertragung im Meldeverfahren (Art. 38 MWSTG) – Formular 764.'],
        'exempt'      => ['name' => 'Von der Steuer ausgenommen (Art. 21 MWSTG)', 'label' => 'Ausgenommen (Art. 21)', 'ziff' => '230', 'taxed' => false,
            'note' => 'Von der Steuer ausgenommene Leistung (Art. 21 MWSTG); es wird keine MWST ausgewiesen.'],
        'passthrough' => ['name' => 'Durchlaufposten (Art. 24 Abs. 6 MWSTG)', 'label' => 'Durchlaufposten', 'ziff' => null, 'taxed' => false,
            'note' => 'Durchlaufposten (Art. 24 Abs. 6 MWSTG): im Namen und auf Rechnung des Kunden weiterverrechnet, nicht Teil des steuerbaren Entgelts.'],
        'none'        => ['name' => 'Ohne MWST (nicht steuerpflichtig)', 'label' => 'Ohne MWST', 'ziff' => null, 'taxed' => false, 'note' => null],
    ];

    /** Ziffer der Steuerberechnung (Leistung/Steuer) je Satz, ESTV-Formular ab 2024 (früher 302/312/342). */
    private const RATE_ZIFF = ['8.1' => '303', '2.6' => '313', '3.8' => '343', '7.7' => '302', '2.5' => '312', '3.7' => '342'];

    public static function rateZiff(float $rate): ?string
    {
        return self::RATE_ZIFF[rtrim(rtrim(number_format($rate, 3, '.', ''), '0'), '.')] ?? null;
    }

    /** Steuerart einer Steuergruppe (aus Satz und Kurzbezeichnung eines Belegs). */
    public static function classOfGroup(float $rate, ?string $label): string
    {
        $label = trim((string) $label);
        if ($rate > 0) {
            return $label === self::CLASSES['option']['label'] ? 'option' : 'taxable';
        }
        foreach (self::CLASSES as $k => $c) {
            if ($c['taxed'] === false && $label === $c['label']) {
                return $k;
            }
        }
        return 'unclassified';
    }

    /** Pflichthinweise für die Steuerarten, die in den Steuergruppen eines Belegs vorkommen. */
    public static function notes(array $groups): array
    {
        $out = [];
        foreach ($groups as $g) {
            if (abs((float) ($g['base'] ?? 0)) < 0.005 && abs((float) ($g['tax'] ?? 0)) < 0.005) {
                continue;
            }
            $c = self::classOfGroup((float) $g['rate'], $g['label'] ?? '');
            $note = self::CLASSES[$c]['note'] ?? null;
            if ($note) {
                $out[$c] = $note;
            }
        }
        return array_values($out);
    }

    /** Ist das Unternehmen MWST-pflichtig (im MWST-Register eingetragen)? */
    public static function liable(): bool
    {
        return (string) Settings::get('mwst_liable', '1') !== '0';
    }

    /** @return array<string,string> Abrechnungsmethoden */
    public static function methods(): array
    {
        return ['agreed' => 'Nach vereinbarten Entgelten (Rechnungsdatum)', 'received' => 'Nach vereinnahmten Entgelten (Zahlungseingang)'];
    }

    public static function method(): string
    {
        $m = (string) Settings::get('mwst_method', 'agreed');
        return isset(self::methods()[$m]) ? $m : 'agreed';
    }

    /** Saldosteuersatz in % (null = effektive Abrechnung). */
    public static function saldoRate(): ?float
    {
        $v = trim((string) Settings::get('mwst_saldo_rate', ''));
        return $v !== '' && is_numeric($v) && (float) $v > 0 ? (float) $v : null;
    }

    public static function all(bool $activeOnly = true): array
    {
        if ($activeOnly && self::$cache !== null) {
            return self::$cache;
        }
        $today = date('Y-m-d');
        $rows = DB::all('SELECT * FROM tax_templates' . ($activeOnly ? " WHERE active = 1 AND (valid_from IS NULL OR valid_from <= '$today') AND (valid_to IS NULL OR valid_to >= '$today')" : '') . ' ORDER BY (rate = 0), sort, rate DESC');
        foreach ($rows as &$r) {
            $r['rate'] = (float) $r['rate'];
            $r['display'] = self::display($r);
        }
        unset($r);
        if ($activeOnly) {
            if (!self::liable()) {   // nicht MWST-pflichtig: ausschliesslich „Ohne MWST“
                $none = array_values(array_filter($rows, static fn($t) => ($t['tax_class'] ?? '') === 'none'));
                $rows = $none ?: array_values(array_filter($rows, static fn($t) => $t['rate'] == 0.0));
                if ($rows) {
                    $rows[0]['is_default'] = 1;
                }
            } else {   // steuerpflichtig: „Ohne MWST“ gibt es nicht – jede Position braucht eine Steuerart
                $rows = array_values(array_filter($rows, static fn($t) => ($t['tax_class'] ?? '') !== 'none'));
                if ($rows && !array_filter($rows, static fn($t) => $t['is_default'] && $t['rate'] > 0)) {
                    foreach ($rows as &$d) {
                        $d['is_default'] = 0;
                    }
                    unset($d);
                    foreach ($rows as &$d) {
                        if ($d['rate'] > 0 && ($d['tax_class'] ?? 'taxable') === 'taxable') {
                            $d['is_default'] = 1;
                            break;
                        }
                    }
                    unset($d);
                }
            }
            self::$cache = $rows;
        }
        return $rows;
    }

    public static function find(?int $id): ?array
    {
        foreach (self::all() as $t) {
            if ((int) $t['id'] === $id) {
                return $t;
            }
        }
        return null;
    }

    /** Kennzeichnung auf Belegen, z. B. „MWST 8.1 %“. */
    public static function display(array $t): string
    {
        if ((float) $t['rate'] == 0.0) {
            return (string) $t['label'];
        }
        return trim($t['label'] . ' ' . qty($t['rate']) . ' %');
    }

    public static function zeroTemplate(): ?array
    {
        foreach (self::all() as $t) {
            if ((float) $t['rate'] == 0.0) {
                return $t;
            }
        }
        return null;
    }

    /** Standard-Vorlage für neue Positionen: Normalsatz (MWST-pflichtig) bzw. „Ohne MWST“. */
    public static function defaultTemplate(): ?array
    {
        foreach (self::all() as $t) {
            if ($t['is_default']) {
                return $t;
            }
        }
        return self::all()[0] ?? null;
    }

    /** Prüft eine UID: CHE-123.456.789 (optional mit Zusatz MWST/TVA/IVA), inkl. Prüfziffer (Modulo 11). */
    public static function validUid(string $uid): bool
    {
        if (!preg_match('/^CHE[-. ]?(\d{3})[. ]?(\d{3})[. ]?(\d{3})(?:\s?(MWST|TVA|IVA|HR|VAT))?$/i', trim($uid), $m)) {
            return false;
        }
        $d = $m[1] . $m[2] . $m[3];
        $w = [5, 4, 3, 2, 7, 6, 5, 4];
        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $sum += (int) $d[$i] * $w[$i];
        }
        $check = 11 - ($sum % 11);
        return $check !== 10 && ($check === 11 ? 0 : $check) === (int) $d[8];
    }

    /** Eigene UID für Dokumente: mit Zusatz „MWST“ nur, wenn steuerpflichtig. */
    public static function companyUid(): string
    {
        $u = trim((string) Settings::get('company_vat_id', ''));
        if ($u === '') {
            return '';
        }
        return self::validUid($u) ? self::formatUid($u, self::liable()) : $u;
    }

    /** Normalisierte Schreibweise „CHE-123.456.789 MWST“. */
    public static function formatUid(string $uid, bool $withSuffix = true): string
    {
        if (!preg_match('/(\d{3})[. ]?(\d{3})[. ]?(\d{3})/', $uid, $m)) {
            return trim($uid);
        }
        return 'CHE-' . $m[1] . '.' . $m[2] . '.' . $m[3] . ($withSuffix ? ' MWST' : '');
    }

    /** Länder, die für die MWST zum Inland zählen (Liechtenstein gehört zum Schweizer Mehrwertsteuergebiet). */
    public static function domesticCountry(?string $country): bool
    {
        $c = strtoupper(trim((string) $country));
        return $c === '' || $c === 'CH' || $c === 'LI';
    }

    /** Am Stichtag gültige Steuersätze (> 0 %) der aktiven Vorlagen, inkl. ausgelaufener, die am Stichtag noch galten. */
    public static function ratesOn(string $date): array
    {
        $rows = DB::all('SELECT rate FROM tax_templates WHERE rate > 0 AND (valid_from IS NULL OR valid_from <= ?) AND (valid_to IS NULL OR valid_to >= ?)', [$date, $date]);
        return array_map(static fn($r) => (float) $r['rate'], $rows);
    }

    /**
     * Plausibilitätsprüfung eines Belegs (Offerte oder Rechnung). Keine Steuerberatung – sie fängt die häufigsten Fehler ab.
     * @param array $customer Kundendaten (Land, Adresse, UID)
     * @param array $groups   Steuergruppen [rate,label,base,tax]
     * @return array<int, array{level:string,text:string}> level: error | warn | info
     */
    public static function review(array $customer, array $groups, string $date, string $areaType = 'digital', bool $invoice = true): array
    {
        $out = [];
        $add = static function (string $level, string $text) use (&$out): void {
            $out[md5($text)] = ['level' => $level, 'text' => $text];
        };
        $liable = self::liable();
        $domestic = self::domesticCountry($customer['country'] ?? '');
        $country = strtoupper(trim((string) ($customer['country'] ?? '')));
        $known = $liable ? self::ratesOn($date) : [];

        if ($invoice) {
            if ($liable && trim((string) Settings::get('company_vat_id', '')) === '') {
                $add('error', 'Ihre UID-Nummer fehlt (Einstellungen → Firma). Eine MWST-konforme Rechnung muss sie mit dem Zusatz «MWST» enthalten (Art. 26 Abs. 2 MWSTG).');
            }
            if (customer_name($customer) === '' || trim((string) ($customer['city'] ?? '')) === '' || trim((string) ($customer['zip'] ?? '')) === '') {
                $add('error', 'Name oder Adresse (PLZ/Ort) des Kunden fehlt. Eine MWST-konforme Rechnung muss Namen und Adresse des Leistungsempfängers enthalten (Art. 26 Abs. 2 MWSTG).');
            }
        }

        foreach ($groups as $g) {
            $base = (float) ($g['base'] ?? 0);
            if (abs($base) < 0.005) {
                continue;
            }
            $rate = (float) $g['rate'];
            $class = self::classOfGroup($rate, $g['label'] ?? '');

            if (!$liable && $rate > 0) {
                $add('error', 'Das Unternehmen ist nicht MWST-pflichtig, der Beleg weist aber MWST aus. Wer MWST ausweist, schuldet sie (Art. 27 Abs. 2 MWSTG).');
            }
            if ($liable && in_array($class, ['taxable', 'option'], true)) {
                if (!$domestic) {
                    $add('warn', 'Kunde im Ausland (' . ($country ?: '?') . '): Dienstleistungen an Empfänger im Ausland gelten am Empfängerort als erbracht und sind in der Schweiz nicht steuerbar (Art. 8 Abs. 1 MWSTG); Lieferungen ins Ausland sind steuerbefreit (Art. 23 MWSTG). Schweizer MWST nur bei Ausnahmen (Grundstück in der Schweiz, Veranstaltung/Gastgewerbe in der Schweiz, Leistung an anwesende Personen). Bitte Steuerart prüfen – zu Unrecht ausgewiesene MWST muss abgeliefert werden (Art. 27 Abs. 2 MWSTG).');
                }
                if ($known && !in_array(round($rate, 3), array_map(static fn($r) => round($r, 3), $known), true)) {
                    $add('warn', 'Der Steuersatz ' . qty($rate) . ' % ist am ' . fmt_date($date) . ' in keiner Steuervorlage gültig (Gültigkeit der Sätze prüfen; massgebend ist der Zeitpunkt der Leistung).');
                }
            }
            if ($liable && $domestic && $class === 'export') {
                $add('warn', 'Steuerbefreite Ausfuhr bei Kunde in der Schweiz/Liechtenstein: Die Befreiung gilt nur, wenn die Ware nachweislich ins Ausland gelangt (Art. 23 MWSTG). Sonst MWST ausweisen.');
            }
            if ($liable && $domestic && $class === 'abroad') {
                $add('warn', '«Im Ausland erbracht» bei Kunde in der Schweiz/Liechtenstein: nur zulässig bei Ausnahmen des Leistungsorts (z. B. Veranstaltung, Grundstück oder Leistung an anwesende Personen im Ausland). Sonst MWST ausweisen.');
            }
            if ($liable && !$domestic && $class === 'abroad' && $areaType === 'physical') {
                $add('warn', 'Warenlieferung ins Ausland: Verwenden Sie «Ausfuhr, steuerbefreit» (Art. 23 MWSTG) mit Ausfuhrnachweis statt «Im Ausland erbracht».');
            }
            if ($liable && !$domestic && $class === 'export' && $areaType === 'digital') {
                $add('info', 'Ausfuhr bezieht sich auf Waren. Reine Dienstleistungen an Kunden im Ausland werden mit «Im Ausland erbracht» (Art. 8 MWSTG) abgerechnet.');
            }
            if ($liable && $class === 'abroad' && trim((string) ($customer['vat_id'] ?? '')) === '') {
                $add('info', 'Für Leistungen ins Ausland empfiehlt sich die Steuer-/UID-Nummer des Kunden auf dem Beleg (Nachweis des Leistungsorts bei Unternehmen).');
            }
            if ($liable && in_array($class, ['unclassified', 'none'], true)) {
                $add('warn', 'Eine Position hat keine Steuerart («' . trim((string) ($g['label'] ?? '')) . '» ohne Zuordnung). Wählen Sie ausgenommen, Ausfuhr, im Ausland erbracht, Meldeverfahren oder Durchlaufposten.');
            }
        }
        return array_values($out);
    }

    /** Ist das Unternehmen nahe an/über der Eintragungsgrenze von CHF 100'000? (nur für nicht Pflichtige relevant) */
    public static function registrationStatus(): array
    {
        $to = date('Y-m-d');
        $from12 = date('Y-m-d', strtotime('-12 months +1 day'));
        $yearStart = date('Y-01-01');
        $turn = static function (string $from) use ($to): float {
            $sum = 0.0;
            foreach (ReportService::invoices($from, $to) as $r) {
                foreach ($r['groups'] as $g) {
                    $c = self::classOfGroup((float) $g['rate'], $g['label'] ?? '');
                    if (in_array($c, ['exempt', 'passthrough'], true)) {
                        continue;   // zählen nicht zum für die Pflicht massgebenden Umsatz
                    }
                    $sum += (float) $g['base'];
                }
            }
            return round($sum, 2);
        };
        $t12 = $turn($from12);
        $ty = $turn($yearStart);
        $max = max($t12, $ty);
        return ['turnover_12m' => $t12, 'turnover_year' => $ty, 'max' => $max, 'limit' => 100000.0, 'pct' => $max / 100000 * 100];
    }
}
