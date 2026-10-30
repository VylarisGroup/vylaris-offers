# VylarisOffers – Styleguide & Designentscheide

Dieses Dokument beschreibt, **wie die Oberfläche gestaltet ist**, **welche Bibliotheken** dafür verwendet werden und **warum** wichtige Entscheidungen so getroffen wurden. Es richtet sich an alle, die das System weiterentwickeln oder das Erscheinungsbild anpassen. Stand: Version 1.7.0.

> Hinweis: `.md`-Dateien werden vom Webserver nicht ausgeliefert (`.htaccess`). Das Dokument ist nur für Entwickler im Dateisystem bzw. im Repository gedacht.

---

## Inhalt

1. [Leitidee und Prinzipien](#1-leitidee-und-prinzipien)
2. [Technischer Unterbau und Bibliotheken](#2-technischer-unterbau-und-bibliotheken)
3. [Designsystem (Tokens)](#3-designsystem-tokens)
4. [Komponenten](#4-komponenten)
5. [Layouts und Seitentypen](#5-layouts-und-seitentypen)
6. [Bewegung und Interaktion](#6-bewegung-und-interaktion)
7. [JavaScript-Module](#7-javascript-module)
8. [Barrierefreiheit](#8-barrierefreiheit)
9. [Responsives Verhalten](#9-responsives-verhalten)
10. [Druck, PDF und E-Mail](#10-druck-pdf-und-e-mail)
11. [Website-Baukasten und öffentliche Seiten](#11-website-baukasten-und-öffentliche-seiten)
12. [Datenschutz und Sicherheit im Frontend](#12-datenschutz-und-sicherheit-im-frontend)
13. [Wichtige Designentscheide (Begründungen)](#13-wichtige-designentscheide-begründungen)
14. [Erweitern: so geht es richtig](#14-erweitern-so-geht-es-richtig)
15. [Bekannte Grenzen](#15-bekannte-grenzen)
16. [Dateiübersicht](#16-dateiübersicht)

---

## 1. Leitidee und Prinzipien

Die Oberfläche soll sich wie ein **ruhiges, modernes Büro-Werkzeug** anfühlen: viel Weissraum, klare Hierarchie, wenige, dafür konsequent eingesetzte Gestaltungsmittel. Sie ist für den Alltag von kleinen Teams gedacht (Offerten schreiben, Aufträge verfolgen, Rechnungen versenden) – nicht für Spektakel.

| Prinzip | Umsetzung |
|---|---|
| **Eine Markenfarbe, alles andere neutral** | Eine einzige konfigurierbare Farbe (`brand_color`) färbt Buttons, aktive Navigation, Links, Fokus-Ringe, PDF und E-Mails. Alles übrige ist Slate-Grau (Tailwind-Palette). |
| **Farbe trägt Bedeutung** | Statusfarben (Sky, Amber, Emerald, Rose, Indigo, Violet, Slate) haben feste Bedeutungen und stehen zentral in `app/lib/Status.php`. |
| **Konsistenz vor Originalität** | Wenige wiederverwendbare Komponenten (`.btn`, `.card`, `.input`, `.badge`, `.table` …) statt Einzelfall-Styling. |
| **Server-gerendert, wenig JavaScript** | PHP-Templates liefern fertiges HTML. JavaScript ergänzt nur (Dialoge, Live-Berechnung, Website-Editor). Alles funktioniert grundsätzlich auch ohne JS. |
| **Keine externen Anfragen** | Schrift, Icons und CSS-Framework liegen lokal. Es gibt keine CDN-, Google-Fonts- oder Tracking-Aufrufe (DSGVO/DSG). |
| **Mobil gleichwertig** | Jede Ansicht ist für Smartphone-Breite ausgelegt; Tabellen scrollen, die Navigation ist ein Off-Canvas-Menü. |
| **Bewegung ist dezent und abschaltbar** | Kurze Übergänge (150–600 ms) mit weichen Kurven; `prefers-reduced-motion` schaltet fast alles ab. |
| **Sprache** | Durchgehend Deutsch (Schweizer Schreibweise: «ss» statt «ß», Anführungen «…»). Zahlen im Schweizer Format (`1’234.50`). |

---

## 2. Technischer Unterbau und Bibliotheken

Es gibt **keinen Build-Schritt**, kein Node, kein Composer: Dateien hochladen genügt (Plesk-Shared-Hosting).

### 2.1 Frontend

| Bibliothek | Version | Zweck | Lizenz | Ablage |
|---|---|---|---|---|
| **Tailwind CSS** (Browser-Build) | 4.3.3 | Utility-Klassen, Designtokens, Komponenten per `@apply` | MIT | `assets/vendor/tailwind/tailwindcss-browser-4.3.3.js` (≈ 282 kB) |
| **Boxicons** | 2.1.4 | Icon-Schrift (`bx …`, `bxs …`) | MIT (Schrift CC BY 4.0) | `assets/vendor/boxicons/` (woff2 ≈ 116 kB) |
| **Inter** (Variable Font) | via `@fontsource-variable/inter` | Einzige Schrift der Oberfläche | SIL OFL 1.1 | `assets/vendor/inter/` (latin ≈ 48 kB, latin-ext ≈ 85 kB) |

Eigener Code:

- `assets/css/app.css` (≈ 12 kB): Animationen, Dialoge, Dropdowns, Tooltips, Ladezustände, Druck.
- `assets/js/app.js`: globale Interaktionen ohne Abhängigkeiten.
- `assets/js/offer-editor.js`, `website-builder.js`, `passkey.js`: seitenspezifische Module.

Es wird **kein** JavaScript-Framework (React, Vue, Alpine, jQuery …) eingesetzt. Alles ist reines, modernes JavaScript (ES2020+) in Modulen mit Selbstaufruf (IIFE).

### 2.2 Serverseitig für Darstellung

| Bibliothek | Zweck | Lizenz |
|---|---|---|
| **FPDF 1.9** (`app/vendor/fpdf`) | PDF-Erzeugung für Offerten, Rechnungen, Zahlungsbestätigungen | freie Lizenz |
| **chillerlan/php-qrcode 5.0.5** (+ php-settings-container) | QR-Code der Schweizer QR-Rechnung (SwissQR) | MIT / Apache-2.0 |

Ohne Composer eingebunden; Lizenzdateien liegen jeweils im Ordner.

### 2.3 Wie Tailwind eingebunden ist

`app/views/partials/head.php` ist der **gemeinsame `<head>`** aller Seiten (Admin, Anmeldung, Portal, öffentliche Offerte/Rechnung). Er

1. liest die Markenfarbe aus den Einstellungen (nur gültige `#rrggbb`-Werte, sonst Standard `#4f46e5`),
2. setzt sie als CSS-Variable `--brand`,
3. lädt Inter, Boxicons und den Tailwind-Browser-Build,
4. definiert im Block `<style type="text/tailwindcss">` das Theme (`@theme`) und alle Komponenten (`@layer components`),
5. lädt zuletzt `assets/css/app.css`.

Der **Browser-Build** (`@tailwindcss/browser`) kompiliert die verwendeten Klassen zur Laufzeit im Browser. Das spart den Build-Schritt, hat aber Folgen (siehe [Entscheid 13.1](#131-tailwind-browser-build-statt-vorab-kompiliertem-css) und [Grenzen](#15-bekannte-grenzen)).

---

## 3. Designsystem (Tokens)

### 3.1 Markenfarbe und abgeleitete Töne

Es wird nur **eine** Farbe gespeichert (`brand_color`, Standard Indigo `#4f46e5`). Alle Abstufungen werden per CSS `color-mix` in OKLab berechnet, sodass jede beliebige Markenfarbe automatisch ein stimmiges Farbband ergibt:

| Token | Berechnung | Typische Verwendung |
|---|---|---|
| `brand-50` | 6 % Marke + Weiss | Hintergrund aktive Navigation, Badge-Grund |
| `brand-100` | 13 % | Hero-Verlauf, Hover-Flächen |
| `brand-200` | 26 % | dekorative Unschärfe (Anmeldeseite) |
| `brand-300` | 45 % | Link-Unterstreichung |
| `brand-400` | 70 % | Akzente |
| `brand-500` | 88 % | Fokus-Ring (30 % Deckkraft), Eingabefeld-Fokus |
| `brand-600` | **= Marke** | Primär-Button, Icons aktiver Navigation, Logo-Kachel |
| `brand-700` | 84 % Marke + Schwarz | Hover Primär-Button, Link-Text |
| `brand-800` | 68 % + Schwarz | Link-Hover |
| `brand-900` | 50 % + Schwarz | dunkle Akzente |

Die Auswahl im Einstellungsdialog und die Prüfung auf `#rrggbb` stellen sicher, dass nie ungültige Werte im `<style>` landen (Schutz vor CSS-Injection). Dieselbe Farbe wird in **PDF** (`PdfService::rgb()` / `tint()`) und **E-Mails** (inline) verwendet.

### 3.2 Neutrale Farben und Statusfarben

- **Neutral:** Tailwind **Slate** (`slate-50` Seitenhintergrund, `slate-200` Linien/Ringe, `slate-500` Hilfetext, `slate-700/800/900` Text).
- **Weiss** für Karten, Eingabefelder und Dialoge.
- **Statusfarben** (Badges, KPI-Icons) – Bedeutung fest:

| Farbe | Bedeutung | Beispiele |
|---|---|---|
| Sky | offen, wartet auf Reaktion | Offerte *Offen*, Auftrag *Eingegangen*, Rechnung *Offen* |
| Amber | in Arbeit, Aufmerksamkeit | *In Bearbeitung*, *Teilbezahlt*, *Verpackt* |
| Emerald | erledigt, positiv | *Angenommen*, *Bezahlt*, *Zugestellt* |
| Rose | negativ, Fehler, überfällig | *Abgelehnt*, *Überfällig*, *Storniert*, *Retour* |
| Indigo | unterwegs / geliefert | *Geliefert*, *Versendet* |
| Violet | ersetzt / abgeleitet | Offerte *Ersetzt* |
| Slate | neutral, inaktiv | *Entwurf*, *Abgelaufen*, *Gutschrift* |

Die Zuordnung (Text, Farbe, Icon) steht an **einer** Stelle: `app/lib/Status.php` (Konstanten `OFFER`, `ORDER`, `INVOICE`, `SHIPPING`). `Status::badge()` erzeugt das HTML. Neue Status nur dort ergänzen.

Zusätzlich gilt: **Farbe ist nie alleiniger Informationsträger** – jedes Badge hat Text und ein Icon.

### 3.3 Typografie

- **Schrift:** *Inter* (Variable Font, Gewichte 100–900), eingebunden mit `font-display: swap`. Der Stack fällt auf `ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif` zurück.
- **OpenType-Features:** `font-feature-settings: "cv11", "ss01"` (einstöckiges «a», offene Ziffern) – wirkt freundlicher und besser lesbar.
- **Zahlen:** Klasse `.tabular` (`font-variant-numeric: tabular-nums`) für Beträge und Tabellenspalten, damit Ziffern bündig stehen.
- **Grössen:** Basistext 14 px (`text-sm`), Seitentitel `text-2xl` / `sm:text-[28px]` mit `font-bold tracking-tight`, Karten-Titel 15 px `font-semibold`, Hilfetexte 12 px (`.hint`), Tabellenköpfe 12 px, Versalien mit Laufweite (`uppercase tracking-wide`), Bereichs-Labels der Navigation 11 px.
- **Gewichte:** 400 Fliesstext, 500 Labels/Navigation, 600 Buttons/Titel, 700 Seitentitel/Logo-Text.
- **Fallback in PDF:** Helvetica (FPDF-Standardschrift, siehe [Abschnitt 10](#10-druck-pdf-und-e-mail)). **In E-Mails:** Systemschrift-Stack (Webfonts sind in Mail-Clients unzuverlässig).

### 3.4 Abstände, Radien, Schatten

- **Radien:** Eingabefelder/Buttons `rounded-xl` (12 px), kleine Buttons `rounded-lg` (8 px), Karten `rounded-2xl` (16 px), grosse Buttons `rounded-2xl`, Dialoge 1.1 rem, Badges vollrund. Die Rundungen steigen mit der Grösse des Elements.
- **Schatten:** nur zwei Stufen, beide sehr weich und kühl-bläulich (Slate-Schwarz mit geringer Deckkraft):
  - `shadow-soft` – Standardkarte: `0 1px 2px …/.04, 0 4px 16px -4px …/.06`
  - `shadow-lift` – Hover/Toasts: `0 2px 4px …/.04, 0 14px 32px -10px …/.16`
- **Ringe statt Rahmen:** Karten und Eingabefelder nutzen `ring-1 ring-slate-200` statt `border`. Vorteil: kein Layoutversatz beim Hover/Fokus, saubere Abrundung, einfache Farbwechsel.
- **Raster:** Tailwind-Skala (4-px-Schritt). Seitenabstand `px-4 sm:px-6`, Kartenpadding `p-5 sm:p-6`, Abschnittsabstand `space-y-6`.

---

## 4. Komponenten

Alle Komponenten sind in `head.php` unter `@layer components` definiert und per `@apply` aus Tailwind-Klassen zusammengesetzt.

| Klasse | Aussehen / Verhalten |
|---|---|
| `.btn` | Basis: `inline-flex`, `rounded-xl`, `px-4 py-2.5`, `text-sm font-semibold`, `active:scale-[.98]`, sichtbarer Fokus-Ring (`focus-visible:ring-4`), deaktiviert 60 % Deckkraft. |
| `.btn-primary` | Markenfarbe, weisser Text, farbiger Schatten, beim Hover dunkler und 1 px nach oben. |
| `.btn-secondary` | Weiss mit grauem Ring; für zweitrangige Aktionen. |
| `.btn-ghost` | Nur Text; Hover grauer Hintergrund; für tertiäre Aktionen. |
| `.btn-danger` / `.btn-success` | Rose bzw. Emerald; nur für destruktive bzw. abschliessende Aktionen. |
| `.btn-sm`, `.btn-lg`, `.btn-icon` | Grössenvarianten; `btn-icon` ist 36 × 36 px. |
| `.card`, `.card-hover` | Weisse Fläche, `rounded-2xl`, Ring + `shadow-soft`; `card-hover` hebt die Karte leicht an. |
| `.card-header`, `.card-title` | Kopfzeile mit Trennlinie; Titel mit grauem Icon links. |
| `.input`, `select.input`, `textarea.input` | `rounded-xl`, Ring statt Rahmen, Fokus: 2 px Ring in `brand-500`. Select mit eigenem SVG-Pfeil (kein Browser-Standard). `.input-error` färbt rosa. |
| `.label`, `.hint`, `.error-text` | Beschriftung, Hilfetext (12 px, grau), Fehlertext (rosa, mit Icon). |
| `.checkbox` | Native Checkbox mit `accent-color` der Marke. |
| `.badge` + `.badge-{sky,emerald,rose,slate,violet,amber,indigo,brand}` | Pillen mit Ring in passender Farbe; Icon + Text. |
| `.table` | Kopf in Versalien auf hellgrauem Grund, Zeilen mit feiner Trennlinie, Hover-Hinterlegung; `tr.row-link` macht die ganze Zeile klickbar. |
| `.nav-link` (`.is-active`) | Seitenleiste: graues Icon, aktiv = Marken-Hellton + Markenfarbe. |
| `.tab` (`.is-active`) | Segment-Umschalter (weisse Pille auf grauem Grund). |
| `.kpi-icon` | 44-px-Kachel mit Icon für Kennzahlen im Dashboard. |
| `.dropdown-item` | Zeile in Dropdown-Menüs. |
| `.section-label` | Kleine graue Versalien-Überschrift in der Navigation. |
| `.empty-state` | Leerer Zustand: Icon-Kachel, Titel, Text, optional Aktion. |
| `.link` | Textlink in Markenfarbe mit dezenter Unterstreichung. |
| `.dl-row` | Beschriftung links, Wert rechts (Detailansichten). |

Seitenkopf: `partials/page-header.php` (Zurück-Link, Titel, Untertitel, Aktionen) – **jede** Admin-Seite beginnt damit.

**Icons:** ausschliesslich Boxicons (`<i class="bx bx-…">`). Regeln: Icon neben Text hat 18–20 px (`text-lg`/`text-xl`), in Buttons vor dem Text, in Navigation grau (aktiv Markenfarbe). Icons haben nie allein Bedeutung (Text oder `aria-label`/`data-tip` daneben).

---

## 5. Layouts und Seitentypen

| Layout | Datei | Verwendung |
|---|---|---|
| **Admin** | `app/views/layouts/admin.php` | Alle Mitarbeitenden-Seiten. Feststehende Seitenleiste (Desktop, ab 1024 px, 18 rem breit) mit Profilmenü unten; Inhalt in einer Spalte (`max-w-7xl`). Auf dem Smartphone gibt es stattdessen eine schmale, durchscheinende Kopfleiste mit Menü-Knopf, Seitentitel und «Neue Offerte»-Knopf. |
| **Anmeldung** | `layouts/auth.php` | Login, 2FA, Magic-Link, SSO. Zentrierte Karte, weicher Farbfleck (`bg-brand-200/40 blur-3xl`) hinter dem Logo, `noindex`. |
| **Portal** | `layouts/public.php` | Kundenportal. Kopfzeile mit Logo, oben ein dezenter Farbverlauf (`from-brand-100/60`), schmale Inhaltsbreite (`max-w-5xl`). |
| **Minimal** | `layouts/minimal.php` | Fehlerseiten u. Ä. |
| **Öffentliche Seiten** | `views/public/*` | Offerte `/o/{id}`, Rechnung `/r/{id}`, Datenschutz, Startseite (Website-Baukasten). |
| **E-Mail** | `views/emails/layout.php` | Einheitliche Mailvorlage ([Abschnitt 10](#10-druck-pdf-und-e-mail)). |

### 5.1 Admin-Navigation

- Gruppiert in **Hauptbereich** (Dashboard, Offerten, Anfragen, Aufträge, Rechnungen, Abos, Kunden), **Katalog** (Produktbereiche, Positionsvorlagen, Steuervorlagen, Versandarten) und **System** (nur Administratoren: Berichte, Benutzer, Webseite, Einstellungen, **Update**, E-Mail-Protokoll, Audit-Log, Integrationen, Systemstatus).
- Kleine Zähler rechts im Menüpunkt: neue Anfragen, neue Aufträge, überfällige Rechnungen; ausserdem Systemstatus (Anzahl Probleme), Update (`●` wenn eine neue Version bereitsteht).
- Aktiver Punkt: `is-active` (Markentönung).
- Mobil: Seitenleiste gleitet von links herein (`translateX`, 300 ms), abgedunkelter Hintergrund schliesst sie wieder.
- Ganz unten in der Seitenleiste steht der Hinweis «Powered by VylarisOffers» (`Brand::POWERED_BY`) – die einzige feste Kennzeichnung; Firmenname, Logo und Farbe sind sonst vollständig anpassbar.

### 5.2 Typische Seitenmuster

- **Liste:** Seitenkopf → Filterleiste (Suche, Status-Tabs, Besitzerfilter) → Karte mit `.table` → Leerzustand, wenn keine Daten.
- **Detail:** Seitenkopf mit Statusbadge und Aktionen → zweispaltig (`xl:grid-cols-3`: 2/3 Inhalt, 1/3 Seitenspalte mit Zusammenfassung, Verlauf/Timeline).
- **Formular:** Karten pro Thema, Felder im 6-Spalten-Raster (`sm:grid-cols-6`, Felder spannen `sm:col-span-2/3/4/6`), Speichern-Button rechts unten.
- **Dashboard:** Kennzahlen-Kacheln (`kpi-icon` + grosse Zahl), Balkendiagramm als reines HTML/CSS (`.bar` mit Wachstumsanimation), Listen «Neueste Offerten», «Überfällige Rechnungen».
- **Einstellungen:** Tab-Navigation (`?tab=…`: Firma, Offerten, Rechnungen, Zahlungen, Branding, E-Mail, Datenschutz sowie Single Sign-On), pro Tab ein Formular.

---

## 6. Bewegung und Interaktion

Bewegung dient der **Orientierung** (Was ist neu? Was hat sich geändert?) und nie der Dekoration.

### 6.1 Kurven und Zeiten

```css
--ease-out:    cubic-bezier(.2, .7, .2, 1);     /* weiches Abbremsen, Standard */
--ease-spring: cubic-bezier(.34, 1.4, .64, 1);  /* leichtes Überschwingen (Toasts) */
```

| Element | Dauer | Effekt |
|---|---|---|
| Buttons | 200 ms | Hover: dunkler, 1 px hoch; Klick: auf 98 % skaliert |
| Karten (`card-hover`) | 300 ms | Schatten wächst, 2 px hoch |
| Scroll-Einblendung `.reveal` | 600 ms | von unten (14 px) einblenden, beim ersten Sichtbarwerden; gestaffelt per `reveal-delay-1..3` |
| Seitenleiste mobil | 300 ms | Schieben |
| Dropdown | 160–180 ms | Einblenden + leicht skalieren |
| Dialog | 250–300 ms | Hintergrund blendet ein (mit 3 px Weichzeichner), Panel gleitet hoch |
| Toast | 400 ms rein / 250 ms raus | Federnd von oben, seitlich hinaus |
| Positionszeilen im Editor | 320 / 220 ms | rein von oben, raus nach links |
| Ladezustand Button | – | Text verschwindet, Spinner im Button (`.is-loading`) |
| Neue/geänderte Zeile | 1.2 s | Markenfarbener Aufheller, der ausblendet (`flash-row`) |
| Zahl ändert sich | 350 ms | `number-tick` |
| Balken Diagramm | 900 ms | wachsen von unten |
| Auf-/Zuklappen `.fold` | 300 ms | Höhe via `grid-template-rows: 0fr → 1fr` (ohne feste Höhe) |
| Skeleton | 1.4 s Schleife | Shimmer-Verlauf |
| Tooltip | 150 ms (+250 ms Verzögerung) | über `data-tip="…"` |

### 6.2 Reduzierte Bewegung

`@media (prefers-reduced-motion: reduce)` setzt alle Animations- und Übergangsdauern auf ≈ 0, deaktiviert Scroll-Glätten, zeigt `.reveal`-Elemente sofort an. Nur der Ladespinner läuft langsamer weiter, damit ein Ladezustand erkennbar bleibt.

### 6.3 Progressive Enhancement

`head.php` setzt sofort `class="js"` am `<html>`. Nur mit dieser Klasse sind `.reveal`-Elemente anfangs unsichtbar. Ohne JavaScript bleibt alles sichtbar.

---

## 7. JavaScript-Module

Alle Module sind abhängigkeitsfrei und per `defer` geladen.

### `assets/js/app.js` (global)
Scroll-Einblendungen (IntersectionObserver), Toasts, Dialoge (Esc, Hintergrundklick, `body.modal-open` sperrt das Scrollen), Bestätigungsdialog (`data-confirm="…"` an Formular/Button), Ladezustand von Buttons beim Absenden (verhindert Doppelklicks), Dropdowns (`data-dropdown`), mobile Navigation, klickbare Tabellenzeilen (`data-href`), «In Zwischenablage kopieren»-Schaltflächen. Alles per **Event-Delegation** – dynamisch eingefügte Elemente funktionieren ohne Neuregistrierung. Für spätere Nachladungen gibt es `window.appReveal(root)`.

### `assets/js/offer-editor.js`
Dynamische Positionen (hinzufügen, löschen, Vorlagen einfügen), **Live-Berechnung** (Netto, Rabatte, MWST, Total – identische Logik wie `app/lib/Calc.php`), Steuervorschlag anhand der Kundenadresse. Entscheid: *Massgebend ist immer die serverseitige Berechnung* beim Speichern – das JavaScript ist nur Vorschau.

**Positionsbilder:** Pro Zeile ein 44-px-Bildfeld neben dem Titel (`[data-img]`: Platzhalter-Icon `bx-image-add`, Vorschau mit `object-cover`, kleines «×» oben rechts zum Entfernen). Der Upload läuft asynchron (`POST /admin/item-images`, CSRF-Header/-Feld) und liefert einen 32-stelligen Namen, der als `items[ID][image]` mitgesendet wird. In Dokumenten: 80-px-Vorschau mit `rounded-xl` und `ring-slate-200` links neben dem Titel (Desktop), volle Breite (max. 14 rem Höhe) über dem Titel (Mobil); im PDF 17 mm Box links in der Bezeichnungsspalte.

### `assets/js/website-builder.js`
Formularbasierter Editor für die Startseite: Blöcke hinzufügen/sortieren/duplizieren/ausblenden, Entwurf automatisch speichern, Live-Vorschau in einem `iframe` (Scrollposition bleibt beim Neuladen), Geräteumschalter (Desktop 100 % / Smartphone 390 px).

### `assets/js/passkey.js`
WebAuthn-Registrierung und -Anmeldung (Base64url-Hilfsfunktionen, Kommunikation per JSON).

---

## 8. Barrierefreiheit

Umgesetzt:

- **Skip-Link** «Zum Inhalt springen» (sichtbar bei Tastaturfokus) im Admin-Layout.
- **Sichtbarer Fokus** überall: Buttons mit 4-px-Fokusring (`focus-visible`), Eingabefelder mit 2-px-Markenring.
- **Semantisches HTML**: `<header>`, `<main id="main">`, `<nav>`, Formulare mit `<label>`, Tabellen mit `<th>`.
- **ARIA** wo nötig: Toasts in `aria-live="polite"`-Region, Fehler mit `role="alert"`, Icon-Schaltflächen mit `aria-label` (z. B. Geräteumschalter).
- **Farbe nie allein**: Status immer mit Text und Icon.
- **Bewegung reduzierbar** (siehe 6.2).
- **Tastaturbedienung**: Dialoge schliessen mit Esc; klickbare Tabellenzeilen (`tr[data-href]`) sind per Tab fokussierbar und mit Enter auslösbar; der Bestätigungsdialog setzt den Fokus auf die Bestätigen-Schaltfläche.
- **Kontrast**: Fliesstext `slate-700…900` auf Weiss; Hilfetext `slate-500` (≥ 4.5:1 auf Weiss). Primär-Buttons mit weisser Schrift auf der Markenfarbe – bei sehr hellen Markenfarben (z. B. Gelb) muss der Betreiber einen dunkleren Ton wählen.

Noch offen (bewusst nicht behauptet): vollständige Prüfung mit Screenreadern, WCAG-2.2-Audit.

---

## 9. Responsives Verhalten

- **Mobile-first**, Breakpoints von Tailwind: `sm` 640, `md` 768, `lg` 1024, `xl` 1280.
- **Seitenleiste** fix ab `lg`; darunter Off-Canvas mit Menü-Knopf.
- **Tabellen** liegen in Containern mit horizontalem Scroll; sekundäre Spalten werden mit `hidden sm:table-cell` ausgeblendet.
- **Formulare** wechseln von einer Spalte auf das 6-Spalten-Raster ab `sm`.
- **Dialoge** erscheinen mobil als «Bottom-Sheet» (unten ausgerichtet, volle Breite), ab `sm` zentriert.
- **Dynamische Viewport-Höhe** (`100dvh`) für Dialoge, damit mobile Adressleisten nicht abschneiden; `viewport-fit=cover` für Geräte mit Aussparung.
- **Toasts** mobil über die volle Breite oben, ab `sm` rechts oben.
- **Touch**: `-webkit-tap-highlight-color: transparent`, Ziele ≥ 36 px (Icon-Buttons 36 × 36, Standard-Buttons ≥ 40 px hoch).

---

## 10. Druck, PDF und E-Mail

### 10.1 Browser-Druck

`@media print` in `app.css`: weisser Hintergrund, Schatten/Ringe der Karten entfernt, Navigation/Dialoge/Toasts (`.no-print`) ausgeblendet, `.reveal` sofort sichtbar, keine URL-Anhänge hinter Links, Seitenränder 16/14 mm, Tabellenzeilen werden nicht mitten geteilt. Die öffentliche Offerten- und Rechnungsseite sind dadurch «druckfreundlich».

### 10.2 PDF (FPDF)

`app/services/PdfService.php` zeichnet Offerten, Rechnungen und Zahlungsbestätigungen direkt mit FPDF (kein HTML-zu-PDF, keine Browser-Engine, keine Abhängigkeit vom Server).

- A4, Ränder 15–20 mm, Helvetica (eingebaute Schrift; Zeichen werden per `PdfService::t()` nach Windows-1252 umgewandelt, damit Umlaute und «€»/«’» stimmen).
- **Markenfarbe** für Titel, Trennlinien und Summenzeile; helle Tönung (`tint()`) für Tabellenkopf und Summenfläche.
- Kopf mit Logo (sofern vorhanden), Absender- und Empfängerblock, Positionstabelle mit Zeilenumbruch-Berechnung (`lines()` – damit Zeilen nie über Seitenränder laufen), Summenblock, Zahlungsinformationen.
- Fusszeile mit Firmendaten links und «Seite x/y» rechts (`AliasNbPages`).
- Rechnungen enthalten den **Schweizer Einzahlungsschein** mit QR-Code (SwissQR, Kreuz-Logo, gestrichelte Schnittkanten über `SetDash`), in den vorgeschriebenen Massen.

### 10.3 E-Mail

`app/views/emails/layout.php` – eine Vorlage für alle Mails:

- Reines **Tabellenlayout mit Inline-Styles** (zuverlässig in Outlook, Gmail, Apple Mail), Breite max. 560 px.
- Hellgrauer Hintergrund (`#f1f5f9`), weisse Karte mit Rand und Radius 16 px, kurzer Balken in der Markenfarbe über dem Titel.
- Primärer Button in der Markenfarbe (Radius 10 px), darunter der Klartext-Link als Rückfall.
- Systemschriftstack, keine Webfonts, keine externen Bilder ausser dem Firmenlogo.
- Fusszeile mit Firmenadresse.

---

## 11. Website-Baukasten und öffentliche Seiten

Die Startseite (`/`) besteht aus **Bausteinen**, die unter *Webseite* bearbeitet werden: Kopfbereich (Hero), Vorteile, Text, Bild & Text, Ablauf, Häufige Fragen, Handlungsaufruf, Anfrageformular, Kontakt. Definition und Felder: `app/services/SiteBuilder.php`.

Gestaltungsregeln:

- Die Seite übernimmt Designsystem und Markenfarbe der Anwendung (gleiches `head.php`), damit Website, Portal, Offerte und Mails als **eine Marke** auftreten.
- Es gibt bewusst **keine freie Gestaltung** (kein Pixel-Editor): Betreiber füllen Felder aus, das Layout bleibt konsistent und responsiv. Das verhindert kaputte Layouts und Barrierefreiheitsprobleme.
- Icons der Bausteine stammen aus einer festen Boxicons-Auswahlliste.
- **Entwurf/Veröffentlichung:** Änderungen speichern automatisch als Entwurf; erst *Veröffentlichen* macht sie öffentlich, *Verwerfen* stellt zurück, *Vorlage* setzt auf den Standard.
- **Bilder:** nur PNG, JPG, WebP, GIF (≤ 2 MB, ≤ 6000 × 6000 px). **SVG wird abgelehnt** (Skript-Risiko). Ausgeliefert mit `Content-Security-Policy: default-src 'none'; sandbox` und `nosniff`.
- Logo und Favicon werden unter *Einstellungen → Branding* hochgeladen; ohne Logo erscheint eine Kachel in der Markenfarbe mit Icon (`bx-file`/`bx-briefcase-alt-2`) und der Firmenname. Ohne Favicon erzeugt `head.php` ein SVG-Favicon in der Markenfarbe.

---

## 12. Datenschutz und Sicherheit im Frontend

- **Keine Drittanbieter-Anfragen** beim Seitenaufruf: Schrift, Icons, Tailwind lokal. (Einzige bewusste Ausnahme: die Weiterleitung zum Zahlungsanbieter, wenn der Kunde «Online bezahlen» wählt.)
- **CSRF:** Jedes Formular enthält `csrf_field()`; AJAX sendet das Token aus `<meta name="csrf-token">`.
- **Ausgabe immer escaped:** `e()` für jeden dynamischen Wert; Farbwert wird zusätzlich per Regex validiert.
- **Sicherheits-Header** (zentral in `send_security_headers()` in `helpers.php`): `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`, CSP (s. u.), über HTTPS zusätzlich `Strict-Transport-Security` (1 Jahr).
- Interne Seiten (Admin, Anmeldung, Portal) tragen `noindex, nofollow`.
- **Content-Security-Policy** auf allen Seiten: `default-src 'self'`, Bilder von `self`, `data:` und `https:` (Logos per URL), Schrift/Skripte/Verbindungen nur vom eigenen Server, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'self'`. Wegen des Tailwind-Browser-Builds und der Inline-Stylings in `head.php` müssen `style-src` und `script-src` zusätzlich `'unsafe-inline'` erlauben (siehe Grenzen).

---

## 13. Wichtige Designentscheide (Begründungen)

### 13.1 Tailwind-Browser-Build statt vorab kompiliertem CSS
**Entscheid:** Tailwind 4 läuft als Browser-Skript.
**Warum:** Zielumgebung ist Plesk-Shared-Hosting; Betreiber laden Dateien hoch, es gibt kein Node und keinen Build. Ein Browser-Build erlaubt, dass die **Markenfarbe zur Laufzeit** aus den Einstellungen kommt und alle Töne (`brand-50…900`) daraus abgeleitet werden – ohne CSS neu zu erzeugen.
**Preis:** ≈ 282 kB zusätzliches Skript und Kompilierung beim Laden (Millisekunden bis wenige Dutzend ms); kurzes «Aufbauen» möglich. Siehe [Grenzen](#15-bekannte-grenzen) für Alternativen.

### 13.2 Eine Markenfarbe + `color-mix`
**Entscheid:** Nur eine Farbe konfigurierbar, Abstufungen automatisch.
**Warum:** Betreiber wählen eine Farbe und erhalten ein stimmiges System; verhindert unlesbare Kombinationen aus vielen frei gewählten Farben. `color-mix(in oklab, …)` liefert gleichmässigere Abstufungen als HSL-Rechnerei. Dieselbe Farbe in PDF und E-Mail macht die Marke durchgehend erkennbar.

### 13.3 Ringe statt Rahmen, Schatten sehr weich
**Entscheid:** `ring-1` für Karten/Felder, wenige sehr weiche Schatten.
**Warum:** Ringe verschieben das Layout nicht, runden sauber und lassen sich per Utility einfärben (Fokus, Fehler). Weiche Schatten wirken hochwertig, ohne von Inhalten abzulenken.

### 13.4 Inter als einzige Schrift, lokal ausgeliefert
**Entscheid:** Eine variable Schrift in zwei Teilmengen (latin, latin-ext).
**Warum:** Hohe Lesbarkeit bei kleinen Grössen und gute Zifferndarstellung für Beträge. Lokal wegen DSGVO/DSG (Google Fonts würde IP-Adressen an Dritte übertragen). Zwei `unicode-range`-Dateien laden nur, was die Seite braucht; `swap` verhindert unsichtbaren Text.

### 13.5 Boxicons als Icon-Schrift
**Entscheid:** Icon-Font statt SVG-Sprites.
**Warum:** Eine Datei, einfache Verwendung in PHP-Templates (`<i class="bx bx-…">`), grosse Auswahl, Farbe/Grösse per Textklassen. Nachteil: Icons sind eine Schrift (kein Mehrfarben-Icon, ca. 116 kB). Für den Website-Baukasten gibt es dadurch eine einfache, feste Icon-Auswahl.

### 13.6 Kein JS-Framework
**Entscheid:** Server-gerenderte Seiten + kleine Module.
**Warum:** Wenig Angriffsfläche, kein Build, einfache Wartung durch PHP-Entwickler, schnell auf schwachen Geräten. Dynamik gibt es nur dort, wo sie echten Nutzen hat (Live-Berechnung, Editor).

### 13.7 Status zentral in `Status.php`
**Entscheid:** Text, Farbe, Icon pro Status an einer Stelle.
**Warum:** Konsistenz (ein Status sieht überall gleich aus) und einfache Erweiterung; Übersetzungen/Anpassungen an einem Ort.

### 13.9 Zwei Spalten im Detail, Karten als Gliederung
**Entscheid:** Detailseiten: 2/3 Inhalt + 1/3 Seitenspalte; alles in Karten.
**Warum:** Hauptinhalt (Positionen, Texte) bekommt Platz; Kontext (Status, Kunde, Verlauf) bleibt griffbereit. Karten bilden klare Lesezonen, auf Mobilgeräten stapeln sie sich.

### 13.10 Serverseitige Berechnung bleibt massgebend
**Entscheid:** JavaScript zeigt Beträge live, der Server rechnet verbindlich (`Calc.php`).
**Warum:** Steuer- und Rundungsregeln (MWST, 5-Rappen-Rundung) dürfen nicht vom Browser abhängen; die Vorschau darf abweichen, das Dokument nie.

### 13.11 PDF mit FPDF direkt gezeichnet
**Entscheid:** Kein HTML→PDF.
**Warum:** Läuft auf jedem Shared-Hosting ohne Headless-Browser oder Zusatzprogramme, deterministisches Ergebnis, exakte Massgenauigkeit für den QR-Einzahlungsschein. Preis: Layout ist Code, keine CSS-Vorlage; Schrift ist Helvetica statt Inter.

### 13.12 E-Mail als Tabellenlayout mit Inline-Styles
**Entscheid:** Altmodische, robuste Technik.
**Warum:** Mail-Clients (v. a. Outlook) unterstützen modernes CSS nur eingeschränkt. Konsistenz mit der Marke wird über Farbbalken und Button erreicht, nicht über Webfonts.

### 13.13 Keine Dunkelmodus-Variante
**Entscheid:** Nur helle Oberfläche.
**Warum:** Die Dokumente (PDF, Druck, öffentliche Offerte) sind weiss; ein einheitliches Erscheinungsbild vermeidet doppelten Pflegeaufwand. Ein Dunkelmodus liesse sich über die Tokens nachrüsten (siehe 14).

### 13.14 Bewegung nur mit Zweck und abschaltbar
**Entscheid:** Kurze, weiche Übergänge, Respekt vor `prefers-reduced-motion`.
**Warum:** Rückmeldung (Klick, Laden, neue Zeile) ohne Ablenkung; Rücksicht auf Menschen mit Bewegungsempfindlichkeit.

### 13.15 Update-Seiten im gleichen Stil
**Entscheid:** Auch Systembereiche (Update, Systemstatus) nutzen Karten, Badges und Standardbuttons.
**Warum:** Keine «Technikinsel»; Administratoren erkennen Muster wieder. Kritische Aktionen (Update installieren, Gerät abmelden) laufen über den **Bestätigungsdialog** (`data-confirm`).

---

## 14. Erweitern: so geht es richtig

**Neue Seite**
1. View unter `app/views/admin/<bereich>/` anlegen; mit `include APP_DIR . '/views/partials/page-header.php';` beginnen (`$title`, `$subtitle`, optional `$actions`, `$back`).
2. Inhalt in `.card`-Blöcken; Formulare mit `csrf_field()`; Beschriftung mit `.label`, Felder mit `.input`.
3. Leere Zustände mit `.empty-state`.
4. Navigationspunkt in `layouts/admin.php` ergänzen (Icon aus Boxicons).

**Neue Komponente**
- In `head.php` unter `@layer components` mit `@apply` definieren (nicht in `app.css`, sonst fehlen die Theme-Tokens). Animationen/Plain-CSS gehören in `app.css`.

**Neuer Status**
- In `Status.php` Konstante ergänzen (Text, Farbe aus den vorhandenen `badge-*`, Icon). Neue Farbe? Zusätzlich `.badge-<farbe>` in `head.php`.

**Markenfarbe ändern**
- Nur den Einstellungswert `brand_color` – es sind keine Codeänderungen nötig. Standardwert steht in `head.php`, `Settings::DEFAULTS` und im PDF/E-Mail-Code (`#4f46e5`).

**Dunkelmodus nachrüsten (Idee)**
- Neutrale Farben als CSS-Variablen im `@theme` definieren und unter `@media (prefers-color-scheme: dark)` überschreiben; Karten/Eingabefelder/Tabellen nutzen bereits wenige, zentrale Klassen. PDF und E-Mail bleiben hell.

**Tailwind aktualisieren**
- Neue Datei `tailwindcss-browser-<version>.js` nach `assets/vendor/tailwind/` legen und in `head.php` den Pfad ändern; danach alle Seiten visuell prüfen (Tailwind-Hauptversionen ändern Klassennamen). Die Lizenzdatei im Ordner mitaktualisieren.

**Regeln, die eingehalten werden sollen**
- Kein `border` für Karten/Felder – `ring` verwenden.
- Keine festen Hex-Farben in Templates (ausser E-Mail/PDF) – Tokens (`brand-*`, `slate-*`) nutzen.
- Keine externen Skripte, Schriften, Bilder (Datenschutz).
- Jede interaktive Fläche braucht sichtbaren Fokus und eine Textalternative.
- Texte auf Deutsch, Schweizer Schreibweise, Beträge über `money()`, Daten über `fmt_date()`.

---

## 15. Bekannte Grenzen

- **Tailwind-Browser-Build** ist laut Tailwind für Entwicklung/Prototypen gedacht. Er funktioniert stabil, kostet aber Ladezeit (≈ 282 kB, Laufzeit-Kompilierung) und verhindert eine strikte CSP ohne `'unsafe-inline'` für Skripte und Styles. *Alternative bei höherem Verkehr:* CSS einmalig mit der Tailwind-CLI vorab erzeugen (`app.generated.css`) und die Brand-Töne weiterhin als CSS-Variablen belassen.
- **Kein Dunkelmodus** (bewusst, siehe 13.13).
- **PDF-Schrift Helvetica:** kein Inter, nur Windows-1252-Zeichensatz; exotische Zeichen (z. B. kyrillisch) werden ersetzt.
- **Icon-Font:** Wenn die Schrift nicht lädt, erscheinen Icons nicht (Texte bleiben verständlich, da Icons nie allein stehen).
- **Kontrast bei hellen Markenfarben** liegt in der Verantwortung des Betreibers (siehe 8).
- **Nicht getestet:** automatisierte Barrierefreiheits-Audits, sehr alte Browser (benötigt `color-mix`, `:has`-freie Selektoren, `dvh` – aktuelle Chrome/Edge/Firefox/Safari-Versionen).

---

## 16. Dateiübersicht

| Datei | Inhalt |
|---|---|
| `app/views/partials/head.php` | Gemeinsamer `<head>`, Theme (`@theme`), alle Komponenten, Markenfarbe, Favicon |
| `assets/css/app.css` | Animationen, Dialoge, Dropdowns, Tooltips, Ladezustände, Druck, Reduced Motion |
| `assets/js/app.js` | Globale Interaktionen |
| `assets/js/offer-editor.js` | Offerten-Editor mit Live-Berechnung |
| `assets/js/website-builder.js` | Editor des Website-Baukastens |
| `assets/js/passkey.js` | WebAuthn im Browser |
| `assets/vendor/tailwind/` | Tailwind CSS 4.3.3 (Browser-Build) + Lizenz |
| `assets/vendor/boxicons/` | Boxicons 2.1.4 (CSS, Schriftdateien) + Lizenz |
| `assets/vendor/inter/` | Inter Variable (woff2, `inter.css`) + Lizenz |
| `app/views/layouts/*.php` | Admin-, Anmelde-, Portal-, Minimal-Layout |
| `app/views/partials/*.php` | Seitenkopf, Flash/Toasts, Bestätigungsdialog, Timeline, Anhänge … |
| `app/views/emails/layout.php` | E-Mail-Vorlage |
| `app/lib/Status.php` | Status: Text, Farbe, Icon |
| `app/services/PdfService.php` | PDF-Layout (FPDF), SwissQR-Zahlschein |
| `app/services/SiteBuilder.php` | Bausteine und Felder der Startseite |
| `app/services/Brand.php` | Logo, Favicon, Markenname |
| `app/vendor/fpdf`, `app/vendor/chillerlan` | PDF- und QR-Bibliotheken |

## Anhang: Änderungsprotokoll-Fenster

Das Fenster «Neuigkeiten in Version X» (`partials/changelog-modal.php`) nutzt das normale Modal-System (`appModal`), öffnet sich 350 ms nach dem Laden und markiert die Version sofort per `fetch` als gesehen (einmal pro Version). Einträge werden von `partials/changelog-entries.php` gerendert: pro Abschnitt ein Icon mit Farbton (Neu = grün, Verbessert = blau, Behoben = gelb, Hinweis = rot). Text wird escaped; nur `**fett**` und `` `code` `` sind erlaubt.

## Anhang: Seitenleiste und Assistent (ab 1.6.0)

**Ausklappbare Navigation.** Gruppen sind `.nav-group` mit Kopfzeile `.nav-group-btn` und Inhalt `.nav-group-body` (Auf-/Zuklappen per `grid-template-rows: 0fr → 1fr`, 220 ms, bei reduzierter Bewegung ohne Animation). Unterpunkte sind eingerückt (`.nav-sub`, linke Linie) und etwas kompakter (`.nav-link-sub`). Zustand: `aria-expanded`, Merkung pro Gruppe in `localStorage` (`nav:<gruppe>`); die Gruppe mit der aktiven Seite ist immer offen. Zugeklappte Gruppen fassen die Zähler ihrer Einträge als Badge zusammen. Beim Laden läuft keine Animation (`.no-anim`).

**Assistent (`wizard.js`).** Progressive Enhancement: Das Formular bleibt das ursprüngliche; der Assistent blendet nur Teile ein (`.wz-off`). Bedienelemente: Umschalter `.wz-switch` (Segment-Control), Schrittanzeige `.wz-steps` mit Punkten (aktuell = Markenfarbe mit Ring, erledigt = Haken), dünne Fortschrittslinie, Navigation `.wz-nav` (Zurück/Weiter). Auf kleinen Bildschirmen zeigt nur der aktuelle Schritt seine Bezeichnung. Im Assistenten ist das Formular einspaltig (max. 46 rem). Schritte werden über `data-step` (eigene Gruppen) oder `data-wizard="auto"` (jede Eingabe ein Schritt, Kontrollkästchen gebündelt) definiert; leere Schritte (z. B. «Versand» bei digitalen Produkten) werden übersprungen.

## Anhang: Dokumentvorlagen, PWA und Portal (1.7.0)

- **Vorlagen-Karten** (`admin/doc-templates/index`): Miniatur des Layouts aus einfachen Farbflächen (keine Bilddateien), Akzentfarbe als Punkt; Aktionen in einem Drop-down mit `data-dropdown`. Nach unten öffnende Menüs am Kartenrand würden abgeschnitten – daher `bottom-full`.
- **Editor mit Live-Vorschau:** zweispaltig ab `xl` (Einstellungen links, Vorschau rechts, `sticky`). Die Vorschau ist ein `<iframe>` auf eine **gleiche-Herkunft-GET-Adresse** (die CSP erlaubt kein `blob:` für Frames); Änderungen werden entprellt (450 ms). Reglerwerte erscheinen neben der Beschriftung (`data-out`).
- **PWA:** Theme-Farbe = Markenfarbe, Hintergrund `#f8fafc`; Symbole werden serverseitig mit GD gezeichnet (normal und `maskable` mit grösserem Sicherheitsrand). Die Schaltfläche «App installieren» ist standardmässig `hidden` und erscheint nur, wenn der Browser die Installation anbietet.
- **Portal-Navigation:** Reiter (`.tab`) unter der Kopfzeile, horizontal scrollbar auf dem Smartphone, Zähler als kleine Marken-Pille. Nachrichten als Blasen: eigene rechts in Markenfarbe, fremde links in `slate-100`; Bezug als Link unter der Blase.
