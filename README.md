# VylarisOffers – Offerten- & Auftragsmanagement

**Version 1.6.0** – Neu in 1.5.x/1.6: Assistenten (Schritt für Schritt), ausklappbare Seitenleiste, Änderungsprotokoll nach Updates, Lizenzprüfung, Single Sign-On (OpenID Connect), Updates aus GitHub-Releases, Schweizer MWST-System, DSGVO/DSG-Werkzeuge. Update-Quelle, Lizenzserver und Lizenz-Schlüssel sind fest vorgegeben.

PHP/MySQL-Anwendung für Offerten, Aufträge, Rechnungen, Kunden und Versand – **ausgelegt auf die Schweiz** (MWST, QR-Rechnung, CHF). Ohne Framework, ohne Composer, ohne Build-Schritt – läuft auf jedem Shared Hosting mit Plesk.

---

## Inhalt

1. [Funktionsüberblick](#1-funktionsüberblick)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation auf Plesk](#3-installation-auf-plesk)
4. [Konfiguration](#4-konfiguration)
5. [Abläufe im Betrieb](#5-abläufe-im-betrieb)
6. [Steuern](#6-steuern)
7. [Sicherheit](#7-sicherheit)
8. [Wartung, Backup, Update](#8-wartung-backup-update)
9. [Fehlerbehebung](#9-fehlerbehebung)
10. [Projektstruktur](#10-projektstruktur)
11. [Lizenz](#11-lizenz)
12. [Single Sign-On (OpenID Connect)](#12-single-sign-on-openid-connect)
13. [Updates aus GitHub](#13-updates-aus-github)

---

## 1. Funktionsüberblick

| Bereich | Funktionen |
|---|---|
| **Offerten** | Freie Positionen (Titel, Beschreibung, Menge, Einheit, Preis), Rabatt pro Position und auf den Gesamtbetrag (% oder Betrag), Steuern pro Position, Versandkosten, optionales Ablaufdatum, Live-Berechnung im Editor. **Nur Erstellen, Ansehen, Löschen** – Änderungen über „Neue Offerte auf Basis dieser“ (alte wird als *ersetzt* markiert). |
| **Whitelabel** | Eigener Systemname, Untertitel, Logo, Favicon und Markenfarbe (Einstellungen → Branding). Kunden sehen Ihr Erscheinungsbild in Portal, Offerten, E-Mails und PDFs. Einzige feste Kennzeichnung: «Powered by VylarisOffers» unten in der Seitenliste der Administration. |
| **Startseite & Website-Builder** | Die Startseite (`/`) besteht aus Bausteinen (Kopfbereich mit optionaler Portal-Anmeldung, Vorteile, Text, Bild & Text, Ablauf, FAQ, Handlungsaufruf, Kontakt), die unter **Webseite** per Formular bearbeitet, sortiert, dupliziert und ausgeblendet werden – mit Live-Vorschau (Desktop/Smartphone), Entwurf/Veröffentlichen und Vorlage. Es wird nie HTML gespeichert, Links und Bilder werden geprüft. |
| **Positionsvorlagen** | Positionen als Vorlage speichern (direkt im Editor) und mit einem Klick einfügen. |
| **Öffentliche Offertenseite** | `https://ihre-domain.ch/o/{ID}` mit 24-stelliger zufälliger ID. Kunde kann **annehmen** (inkl. abweichender Lieferadresse), **ablehnen** oder **neue Offerte anfragen** (alte wird abgelehnt, E-Mail-Programm öffnet sich per `mailto:`). Druck/PDF über den Browser. |
| **Kundenportal** | `https://ihre-domain.ch/portal` – Anmeldung per **Magic-Link** (ohne Passwort). Übersicht Offerten und Aufträge, Fortschritt, Statushistorie, Sendungsverfolgung, digitale Lieferung. |
| **Aufträge** | Werden bei Annahme automatisch in einer Transaktion erstellt. Status: Eingegangen → In Bearbeitung → Versendet/Bereitgestellt → Abgeschlossen (oder Storniert). Notizen mit Sichtbarkeit für Kunden, E-Mail-Benachrichtigung. |
| **Versand** | Kategorie **Physisch**: Versandart, Kosten, Lieferadresse, Transporteur, Sendungsnummer, Tracking-Link, Versandstatus. Kategorie **Digital**: Liefer-E-Mail, Download-/Zugangslink, Zugangsdaten/Lizenzschlüssel (nur im Portal sichtbar). |
| **Produktbereiche** | Frei definierbar, jeweils Kategorie *Digital* oder *Physisch*. Vorinstalliert: „Digital“ und „Physisch“. |
| **Kunden** | Anrede, Vor-/Nachname, Firma, Adresse, Land, E-Mail, Telefon, Mobile, Website, UID (mit Prüfziffer-Kontrolle), interne Notizen, Portalzugang. Eine Steuerbefreiung pro Kunde gibt es bewusst nicht (siehe 6). |
| **E-Mail** | SMTP (STARTTLS/SSL), PHP `mail()` oder Protokoll-Modus. Automatische Mails bei Offertversand, Annahme, Ablehnung, Statusänderungen, Anmeldelinks. Versandprotokoll im Admin. |
| **Administration** | Dashboard mit Kennzahlen, Monatsvolumen und Aktivitäten; Rollen **Administrator** und **Team** (siehe 7.1). |
| **Rechnungen** | Entstehen aus einem Auftrag (Beträge, Steuern und Rabatte aus der Offerte, danach unveränderlich). Lückenlose Nummern, Zahlungsfrist, Zahlungsinformationen, Versand per E-Mail, öffentliche druckfreundliche Seite `/r/{ID}`, Zahlungserinnerung, „Bezahlt“-Markierung, Stornierung, Anzeige im Kundenportal. |
| **QR-Rechnung & PDF** | Offerten und Rechnungen als PDF (Admin, Kundenseite, Portal). Rechnungen mit **Swiss QR-Zahlteil** (QR-IBAN mit QR-Referenz oder IBAN mit Creditor Reference), sobald eine gültige CH/LI-IBAN hinterlegt ist. |
| **MWST (Schweiz)** | Steuerarten pro Position (Inland 8.1 / 2.6 / 3.8 %, Option, Ausfuhr, im Ausland erbracht, Meldeverfahren, ausgenommen, Durchlaufposten) mit Pflichthinweisen, Gültigkeit der Sätze, Plausibilitätsprüfung, Gutschriften/Skonto/Rückerstattung, MWST-Abrechnung mit ESTV-Ziffern, Saldosteuersatz, Eintragungsgrenze. Siehe 6. |
| **Anzahlung & Schlussrechnung** | Anzahlungsrechnungen (in % oder als Betrag) pro Auftrag; die Schlussrechnung zieht bereits gestellte Anzahlungen automatisch ab. |
| **Abos / wiederkehrende Aufträge** | Monatlich, quartalsweise oder jährlich; erzeugt automatisch Rechnungen (optional mit sofortigem Versand). Siehe 5. |
| **Berichte** | Umsatz, MWST-Übersicht, offene Posten, Offerten-Quote; CSV-Export (Rechnungen, MWST, Aufträge, Offerten, Kunden). Nur Administratoren. |
| **Teilzahlungen & Zahlungsplan** | Zahlungseingänge werden pro Rechnung als Journal geführt (Betrag, Datum, Art: Überweisung/Bar/Online). Eine Rechnung ist *offen*, *teilbezahlt* oder *bezahlt*. Optionaler **Zahlungsplan** (2–12 Raten) mit Fälligkeiten; QR-Zahlteil und Online-Zahlung gelten für die nächste Rate. Quittung (PDF) pro Zahlung. Siehe 5. |
| **Anfrageformular** | Baustein im Website-Builder: Interessenten fragen direkt auf der Startseite eine Offerte an. Legt (bzw. verwendet) den Kunden an, benachrichtigt das Team, Spam- und Datenschutz-Schutz. Liste unter **Anfragen**. Siehe 5. |
| **Systemstatus** | Seite **Systemstatus** prüft Umgebung, Sicherheit, Cronjob, Backup, E-Mail, MWST-/Rechnungsdaten und Zahlungsanbieter; Hinweiszähler in der Navigation und Token-geschützter Monitoring-Endpunkt `/health`. Siehe 5. |
| **Zwei-Faktor-Anmeldung** | Optional pro Benutzer (TOTP-App), mit Wiederherstellungscodes; Administratoren können sie zurücksetzen. |
| **Anhänge** | Dateien an Offerten, Aufträgen und Rechnungen (max. 10 MB, Whitelist), pro Datei für den Kunden sichtbar schaltbar. |
| **Digitale Unterschrift** | Pro Offerte wählbar: keine, offene oder strikte Unterschrift (bestimmte Person, E-Mail per Code bestätigt). Unterschrift erscheint im PDF. |
| **Offerte duplizieren / Katalogsuche** | „Duplizieren“ erzeugt eine unabhängige neue Offerte; im Editor schlägt das Titelfeld passende Positionsvorlagen vor. |

| **PDF per E-Mail** | Offerten und Rechnungen werden als PDF angehängt (Einstellungen → E-Mail abschaltbar; ohne Anhang nur Link). |
| **Audit-Log** | Anmeldungen, fehlgeschlagene Anmeldungen/2FA, Benutzer-, Einstellungs- und Steueränderungen, Exporte, Backups, Löschungen, Datenschutzaktionen, API/Webhook-Änderungen. Nur Administratoren. |
| **Updates** | Neue Versionen aus GitHub-Releases per Klick oder automatisch (Cronjob), mit Sicherung und Rückgängig – siehe Abschnitt 13. |
| **Single Sign-On** | Anmeldung der Mitarbeitenden per OpenID Connect – nur die Adresse des Anbieters nötig (Lizenzfunktion `sso`), siehe Abschnitt 12. |
| **Lizenz** | Lizenzpflichtig (TRS License): Aktivierung, signierte Prüfung, Offline-Lizenz, Karenzfrist, Lesemodus, Funktions-Scopes (`features`) – siehe Abschnitt 11. |
| **Datenschutz (DSGVO/DSG)** | Kundenauskunft (JSON), Einschränkung, Anonymisierung, Portal-Selbstauskunft, automatische Aufbewahrungsfristen, Backup, Datenschutzerklärung, keine Drittanbieter-Anfragen. Siehe 7.2. |
| **Integrationen** | Schreibgeschützte JSON-API mit Tokens, signierte Webhooks, Buchungsjournal (CSV). Siehe 5. |

| **Online-Zahlung** | Kunden bezahlen versendete Rechnungen im Portal per Mollie und/oder Stripe (Karte, TWINT, iDEAL, SEPA usw. je nach Ihrem Anbieterkonto). Rechnung wird automatisch als „bezahlt“ markiert. Siehe 5 «Online-Zahlung». |

Nicht enthalten (bewusst): Rückerstattung über den Zahlungsanbieter (wird dort ausgelöst und hier als Rückerstattung erfasst), Gebühren-/Auszahlungsbuchung, automatischer Bankabgleich.

---

## 2. Voraussetzungen

- PHP **8.1 oder neuer** (getestet mit 8.3; aktuelle Versionen empfohlen) mit den Erweiterungen `pdo_mysql`, `openssl`, `mbstring`, `json`, `sodium` (Lizenzprüfung, in PHP enthalten)
- MySQL 5.7+ oder MariaDB 10.3+
- Apache mit `mod_rewrite` (in Plesk Standard: „Apache + nginx“)
- Ausgehende Verbindung zum SMTP-Server
- Optional: PHP-Erweiterungen `curl` (Webhooks), `zip` (Backup inkl. Anhänge), `gd` (Unterschrift-Prüfung).
- Tailwind CSS, Boxicons und die Schrift Inter liegen **lokal** unter `assets/vendor/` (keine CDN-Anfragen, siehe 7.2) – es ist **kein Build-Schritt** nötig.

---

## 3. Installation auf Plesk

### 3.1 Datenbank anlegen
Plesk → **Websites & Domains** → *Datenbanken* → **Datenbank hinzufügen**
Name, Benutzer und Passwort notieren (Host ist meist `localhost`).

### 3.2 Dateien hochladen
Den gesamten Inhalt des Ordners in das Dokumentenstammverzeichnis hochladen (z. B. `httpdocs/` oder eine Subdomain wie `offerten.ihredomain.ch`). Auch ein Unterordner (`httpdocs/offerten/`) funktioniert.

Wichtig: Die versteckten Dateien `.htaccess` (im Hauptordner, in `app/`, `storage/` und `assets/`) müssen mit hochgeladen werden.

### 3.3 PHP-Version und SSL
- Plesk → *PHP-Einstellungen*: aktuelle PHP-Version wählen.
- Plesk → *SSL/TLS-Zertifikate*: Let's Encrypt aktivieren und die **301-Weiterleitung auf HTTPS** einschalten.

### 3.4 Schreibrechte
Der PHP-Benutzer muss in `app/` (einmalig für `config.php`) und in `storage/` schreiben dürfen. In Plesk ist das bei hochgeladenen Dateien normalerweise bereits der Fall.

### 3.5 Installationsassistent
Im Browser `https://ihre-domain.ch/setup.php` aufrufen und durch die Schritte gehen:

1. **System** – prüft PHP-Version, Erweiterungen, Schreibrechte (zwingend) sowie Empfehlungen: HTTPS, cURL, ZipArchive, vollständige `assets/`
2. **Datenbank** – Zugangsdaten mit Button *Verbindung testen*
3. **Firma & System** – Basis-URL (wird automatisch erkannt, `https://` empfohlen), Firmendaten, Systemname (Whitelabel), Markenfarbe, Währung (CHF/EUR), **MWST** (pflichtig ja/nein, UID mit Prüfziffer, Abrechnungsart, optional Saldosteuersatz). Land, Zahlenformat und Zeitzone sind auf die Schweiz fest eingestellt
4. **Dokumente & Zahlung** – Präfixe für Offerten/Aufträge/Rechnungen, Gültigkeit und Zahlungsfrist, IBAN für die QR-Rechnung (wird geprüft), Zahlungsinformationen; optional **Mollie** und/oder **Stripe** (API-Keys werden geprüft und lassen sich mit *Verbindung testen* live probieren; Speicherung verschlüsselt)
5. **E-Mail** – SMTP-Server, Port, Verschlüsselung, Zugangsdaten, Absender, Benachrichtigungsadresse des Teams; optional Test-E-Mail; kann auch später erfolgen
6. **Administrator & Team** – erster Administrator (Passwort mit Argon2id bzw. bcrypt gehasht) und bis zu drei weitere Benutzer (Team oder Administrator) mit optionaler Einladung per E-Mail
7. **Datenschutz & Betrieb** – Kontakt für Datenschutzanfragen, Aufbewahrungsfristen (Vorgabe 10 Jahre nach Art. 958f OR), Ansprechperson für Kunden, PDF-Anhang in E-Mails
8. **Auftritt & Startseite** – Logo und Favicon hochladen (werden wie in der Administration geprüft), Untertitel, Startseite ein/aus, Überschrift, Einleitungstext, Seitentitel, Button in der Kopfzeile, Suchmaschinen-Indexierung, Link «Administration», **Anfrageformular** ein/aus und Eingangsbestätigung; daraus wird die erste veröffentlichte Fassung der Startseite erzeugt (später im Baukasten **Webseite** weiter anpassbar)
9. **Installation** – Zusammenfassung; erstellt alle Tabellen, legt Grunddaten an, übernimmt alle Einstellungen, legt die Benutzer an und schreibt `app/config.php`

Zum Schluss zeigt der Assistent eine **Checkliste der nächsten Schritte** mit Ihren konkreten Werten: `setup.php` löschen, HTTPS aktivieren (falls nötig), Startseite gestalten, Cronjob (Skriptpfad und Befehl), Stripe-Webhook (URL und Ereignisse), Testzahlung, MWST-Hinweis, Adresse für den Systemstatus-Monitoring-Endpunkt, Konto absichern (Passkeys/2FA) sowie den Status der Einladungen.

Danach ist der Installer **automatisch gesperrt** (`app/config.php` + `storage/install.lock`).
**Löschen Sie anschliessend `setup.php` vom Server.**

### 3.6 Erste Schritte
1. `https://ihre-domain.ch/admin` – anmelden
2. *Einstellungen → Firma*: MWST-Status und UID prüfen; *Einstellungen → Branding*: Logo, Farbe; *Webseite*: Startseite anpassen
3. *Einstellungen → E-Mail*: Test-E-Mail senden
4. *Steuervorlagen* prüfen, *Versandarten* anpassen
5. Ersten Kunden anlegen → *Anlegen & Offerte erstellen*

---

## 4. Konfiguration

### 4.0a Startseite / Website-Builder
Unter **Webseite** (nur Administratoren) bearbeiten Sie die Startseite `/`:

- Bausteine hinzufügen, verschieben, duplizieren, ausblenden, löschen (max. 30). Bilder: PNG/JPG/WebP/GIF bis 2 MB, liegen in `storage/uploads/site/`.
- Änderungen werden automatisch als **Entwurf** gespeichert und rechts live angezeigt. **Veröffentlichen** macht sie öffentlich, **Verwerfen** stellt die veröffentlichte Fassung wieder her, **Vorlage** setzt den Entwurf auf den Standard.
- Links: `https://…`, `mailto:…`, `tel:…`, `#anker` oder interne Pfade wie `portal`. Andere Schemata (z. B. `javascript:`) werden verworfen.
- «Startseite aktiv» aus: `/` leitet wie früher zum Kundenportal weiter. Angemeldete Mitarbeitende werden von `/` weiterhin zur Administration geleitet; mit `/?view=1` sehen Sie die Seite.
- Farben, Logo und Name stammen aus dem Branding (siehe unten). Suchmaschinen-Indexierung ist standardmässig aus.

### 4.0 Whitelabel / Branding
Unter **Einstellungen → Branding** (nur Administratoren) legen Sie fest:

- **Systemname** und **Untertitel**: erscheinen in Seitenliste, Browser-Tab, Anmeldung, Authenticator-Apps und Passkeys, im User-Agent ausgehender Anfragen und in Backup-Dateinamen. Leer = Firmenname.
- **Markenfarbe**: Buttons, Akzente, E-Mails, PDF-Dokumente.
- **Logo** und **Favicon**: PNG, JPG, WebP, GIF (Favicon zusätzlich ICO), max. 1 MB. Die Dateien liegen in `storage/uploads/brand/` und werden über `/brand/logo` bzw. `/brand/favicon` ausgeliefert – ohne Anfragen an Drittserver. SVG wird aus Sicherheitsgründen nicht angenommen. Ohne hochgeladenes Logo gilt die frühere Logo-URL der Firma bzw. ein Standardsymbol.
- Der Hinweis «Powered by VylarisOffers» steht fest unten in der Seitenliste der Administration und lässt sich nicht abschalten. Auf Kundenseiten, in PDFs und E-Mails erscheint er nicht.


### 4.1 `app/config.php`
Wird vom Installer erstellt und enthält Datenbankzugang, Basis-URL, App-Schlüssel und Zeitzone.

```php
return [
  'db'  => ['host' => 'localhost', 'port' => 3306, 'name' => '…', 'user' => '…', 'pass' => '…'],
  'app' => ['base_url' => 'https://offerten.ihredomain.ch', 'key' => '…64 Hex-Zeichen…', 'debug' => false, 'timezone' => 'Europe/Zurich'],
];
```

- **`base_url`** – ohne abschliessenden `/`. Bei Domainwechsel hier anpassen.
- **`key`** – verschlüsselt das gespeicherte SMTP-Passwort (AES-256-GCM). Nicht ändern; bei Verlust das SMTP-Passwort in den Einstellungen neu eingeben.
- **`debug`** – nur zur Fehlersuche auf `true` setzen (zeigt technische Details auf der Fehlerseite).

### 4.2 Einstellungen in der Administration

| Reiter | Inhalt |
|---|---|
| **Firma** | Name, Adresse, Land, MWST/UID, Kontakt-E-Mail (Empfänger von „Neue Offerte anfragen“), Telefon, Logo-URL, Markenfarbe (färbt Oberfläche und E-Mails) |
| **Offerten** | Währung, Zahlenformat (1’234.50 / 1.234,50 / 1,234.50), Standard-Gültigkeit in Tagen (0 = unbefristet), Nummernpräfixe, 5-Rappen-Rundung (nur CHF), Standard-Einleitung und -Schlusstext |
| **Rechnungen** | Präfix der Rechnungsnummer, Zahlungsfrist, Zahlungsinformationen (Bankverbindung), Standard-Einleitung und -Schlusstext |
| **E-Mail** | Versandart, SMTP-Daten, Absender, Empfänger für Team-Benachrichtigungen, Test-Versand |

### 4.3 Typische SMTP-Werte (Plesk)
| Verschlüsselung | Port | Server |
|---|---|---|
| STARTTLS | 587 | `mail.ihredomain.ch` oder der Plesk-Hostname |
| SSL/TLS | 465 | wie oben |

Benutzername ist in der Regel die vollständige E-Mail-Adresse. Die Absenderadresse sollte zur Domain des SMTP-Kontos gehören (SPF/DKIM). Bei Zertifikatsfehlern den Hostnamen verwenden, auf den das Zertifikat ausgestellt ist.

Versandart **„Nur protokollieren“** schreibt E-Mails als `.eml` nach `storage/mail/` – praktisch zum Testen, nicht für den Betrieb.

---

## 5. Abläufe im Betrieb

### Offerte erstellen
*Offerten → Neue Offerte*
1. Kunde wählen – die **Steuervorlage wird anhand des Kundenlandes vorgeschlagen** (Badge „Vorschlag“).
2. Produktbereich wählen – bei *Physisch* erscheinen Versandart und Versandkosten. Die **abweichende Lieferadresse** (eigene Karte „Lieferadresse“) ist bei jedem Produktbereich möglich.
3. Positionen erfassen oder per *Aus Vorlage* einfügen; über das Menü ⋮ einer Position: duplizieren, als Vorlage speichern, entfernen; Reihenfolge mit ↑/↓.
4. Optional Gesamtrabatt. **Ablaufdatum**: Datumsfeld mit Schnellwahl (+14/+30/+60/+90 Tage), Vorgabe aus *Standard-Gültigkeit* in den Einstellungen, oder „Ohne Ablaufdatum“.

**Löschen:** Offerten lassen sich jederzeit löschen. Hat die Offerte bereits einen Auftrag, wird dieser mitgelöscht – solange noch **keine Rechnung** dazu existiert. Rechnungen (auch stornierte) bleiben wegen der fortlaufenden Nummerierung und der Aufbewahrungspflicht erhalten; die Software nennt dann den Grund. Aufträge können auch einzeln gelöscht werden (Seitenleiste des Auftrags).
5. *Offerte erstellen* – optional direkt per E-Mail an den Kunden senden.

Die Berechnung im Editor ist eine Vorschau. **Massgebend ist die serverseitige Berechnung**; Steuersätze werden immer aus den Vorlagen gelesen, nie aus dem Formular übernommen.

### Offerte ändern
Offerten sind unveränderlich. Auf der Offerte *Neue Offerte auf Basis dieser* wählen: Alle Daten werden übernommen; beim Speichern wird die alte Offerte (falls offen) als **ersetzt** markiert. Der Kunde sieht auf der alten Seite einen Hinweis mit Link zur neuen Version.

### Reaktion des Kunden
- **Annehmen**: Name und Bestätigung erforderlich, optional eine andere Lieferadresse (bei jedem Produktbereich). Die vom Kunden gewählte Adresse wird **in der Offerte gespeichert** und von Auftrag, Rechnungen und Dokumenten übernommen. In **einer Datenbanktransaktion mit Zeilensperre** wird die Offerte angenommen und der Auftrag erstellt – eine doppelte Annahme ist ausgeschlossen. Kunde erhält eine Auftragsbestätigung, das Team eine Benachrichtigung.
- **Ablehnen**: optional mit Grund, Team wird benachrichtigt.
- **Neue Offerte anfragen**: aktuelle Offerte wird abgelehnt („Neue Offerte angefragt“), danach öffnet sich das E-Mail-Programm mit vorbereiteter Nachricht an die Firmen-E-Mail.
- **Ablauf**: Offerten mit überschrittenem Datum werden automatisch auf *Abgelaufen* gesetzt (beim nächsten Aufruf, kein Cronjob nötig).

### Auftrag bearbeiten
*Aufträge → Auftrag*
- **Status & Notiz**: neuen Status wählen, Notiz optional, „Für Kunden sichtbar“ und „Kunde per E-Mail informieren“.
- **Versand** (physisch): Versandstatus, Transporteur, Sendungsnummer, Tracking-Link, Lieferadresse. Wechsel auf *Versendet* oder *Zugestellt* setzt den Auftrag automatisch auf *Versendet*.
- **Digitale Lieferung**: Download-Link und Zugangsdaten (nur im Portal sichtbar, nie in E-Mails), optional „als bereitgestellt markieren“.
- *Nachricht an Kunden* erscheint prominent im Portal.

### Rechnung erstellen und verwalten
*Aufträge → Auftrag → Karte „Rechnung“* (Rechnungsdatum und Zahlungsfrist wählen) – es entsteht ein **Entwurf**.
- **Entwurf**: Daten, Einleitung, Zahlungsinformationen und Schlusstext sind bearbeitbar; die Beträge stammen aus der Offerte und sind fix. Erst mit *Rechnung senden* wird sie für den Kunden sichtbar (E-Mail mit Link zu `/r/{ID}`).
- **Offen / Überfällig**: Nach der Fälligkeit wechselt die Anzeige automatisch auf *Überfällig* (Dashboard-Kachel, Zähler in der Navigation). *Zahlungserinnerung senden* verschickt eine Mahn-E-Mail und zählt mit.
- **Zahlung erfassen**: Betrag (vorbelegt mit dem offenen Betrag oder der nächsten Rate), Datum, Art, Notiz. Ist der Betrag kleiner als der offene Betrag, gilt die Rechnung als **teilbezahlt** (Anzeige, Berichte, offene Posten rechnen mit dem Rest). Sobald der Betrag gedeckt ist, wird sie *Bezahlt*. Einzelne Zahlungen lassen sich wieder entfernen (Online-Zahlungen nicht – dort beim Zahlungsanbieter zurückerstatten). Stornieren ist erst möglich, wenn keine Zahlung, kein Skonto und keine Gutschrift mehr erfasst ist; versendete Rechnungen korrigieren Sie per Gutschrift (siehe 6.5).
- **Leistungszeitraum**: Im Entwurf kann ein Leistungszeitraum eingetragen werden; ohne Angabe gilt das Rechnungsdatum als Leistungsdatum (Art. 26 MWSTG).
- **Stornieren**: Entwurf oder offene Rechnung; die Nummer bleibt reserviert (lückenlose Nummernfolge), danach kann eine neue Rechnung zum Auftrag erstellt werden. Rechnungen werden nie gelöscht.
- Pro Auftrag gibt es höchstens eine aktive Schlussrechnung (dazu beliebig viele Anzahlungen).

### Teilzahlungen und Zahlungsplan
- **Zahlungsplan** (Karte «Zahlungsplan» einer Entwurfs- oder offenen Rechnung ohne Zahlung): Anzahl Raten (2–12), Abstand in Monaten und erste Fälligkeit wählen – die Raten sind gleich hoch (auf 5 Rappen), der Rest liegt auf der letzten Rate. Raten erscheinen auf Rechnungsseite und PDF; Zahlungen decken die Raten der Reihe nach. Das **Fälligkeitsdatum der Rechnung** folgt der nächsten unbezahlten Rate, damit «Überfällig», Erinnerungen und Dashboard stimmen.
- **Online-Zahlung**: Der Kunde bezahlt die nächste Rate (bzw. den offenen Betrag) oder wahlweise den gesamten Rest. Der QR-Zahlteil lautet auf den nächsten Betrag.
- **Quittung** pro Zahlungseingang (PDF, auch im Kundenbereich), mit bisher bezahltem und offenem Betrag.
- **Buchungsjournal**: Jede Zahlung ist eine eigene Buchung (Bank, Kasse für Barzahlungen, Online-Konto). Berichte: Rechnungsübersicht zeigt Bezahlt/Offen als Summe der Zahlungen.
- Webhook `invoice.partial_payment` bei Teilzahlung, `invoice.paid` bei vollständiger Zahlung. API: `payments`, `paid_total`, `open_amount`, `installments`.

### Anzahlungs- und Schlussrechnung
In der Karte „Rechnungen“ des Auftrags die Art wählen: *Schlussrechnung* oder *Anzahlung* (Prozent oder fester Betrag, Steuern werden anteilig je Steuersatz berechnet). Die Schlussrechnung weist bereits gestellte (nicht stornierte) Anzahlungen als Abzug aus; „Zu zahlen“ ist der Restbetrag. Pro Auftrag gibt es höchstens eine aktive Schlussrechnung; eine Anzahlung kann nicht storniert werden, solange eine Schlussrechnung existiert.

### QR-Rechnung einrichten
*Einstellungen → Firma:* vollständige Adresse (Strasse, PLZ, Ort). *Einstellungen → Rechnungen:* **IBAN** (CH/LI) eintragen. Bei einer QR-IBAN wird eine 27-stellige QR-Referenz erzeugt, sonst eine Creditor Reference (RF…). Der Zahlteil erscheint auf der letzten Seite des Rechnungs-PDFs. Das PDF verwendet Standardschriften (Latin-1/Windows-1252); Zeichen ausserhalb davon werden ersetzt. Vor dem produktiven Einsatz bitte eine Testrechnung mit der E-Banking-App scannen.

### Abos (wiederkehrende Rechnungen)
*Auftrag → Abo einrichten* (Intervall, Starttag, optional Enddatum, Zahlungsfrist, automatischer Versand). Übersicht unter *Abos* (pausieren, fortsetzen, beenden, „Jetzt ausführen“). Fällige Rechnungen werden erzeugt
- per **Cronjob** (empfohlen; erledigt zusätzlich Webhook-Wiederholungen und Aufbewahrungsfristen): in Plesk *Geplante Aufgaben* → PHP-Skript `…/httpdocs/cron.php`, z. B. täglich um 06:00 (`cron.php` läuft nur per Kommandozeile, nicht per Browser),
- ersatzweise automatisch beim Besuch des Admin-Dashboards (höchstens alle 30 Minuten).
Verpasste Läufe werden nachgeholt (max. 24 pro Aufruf). Monatsenden werden korrekt behandelt (Starttag 31. → 28./29./30.).

### Berichte und CSV (nur Administratoren)
*Berichte*: Zeitraum wählen; Kennzahlen, **MWST-Abrechnung mit den Ziffern der ESTV** (siehe 6.6), Monats- und Bereichsübersicht, MWST je Steuersatz (effektiv, d. h. Schlussrechnung abzüglich Anzahlungen; nach **vereinbarten** Entgelten = Rechnungsdatum oder nach **vereinnahmten** Entgelten = Zahlungsdatum, anteilig bei Teilzahlungen – je nach Einstellung; mit Saldosteuersatz zusätzlich die geschuldete Steuer), offene Posten und Offerten-Quote. CSV-Dateien öffnen sich direkt in Excel (UTF-8 mit BOM, Trennzeichen `;`).

### Zuständigkeiten
- **Offerten** zeigen, wer sie ausgestellt hat («Ausgestellt von»; Detailseite, Liste, Dashboard). Der Aussteller ist fest und bleibt auch nach Löschung seines Kontos als Name erhalten. Offerten, die nicht von einer Person erstellt wurden, erscheinen als «System / Kundenanfrage».
- **Aufträge** haben eine verantwortliche Person. Beim Annehmen einer Offerte wird automatisch deren Aussteller eingetragen (sofern sein Konto aktiv ist), sonst bleibt der Auftrag «Nicht zugewiesen». Auf der Auftragsseite (Karte «Zuständigkeit») kann jedes Teammitglied den Auftrag zuweisen oder per Klick übernehmen; die Änderung steht im Verlauf (intern) und im Audit-Log, die neu zugewiesene Person erhält eine E-Mail.
- **Filter:** in den Listen *Offerten* und *Aufträge* nach «Meine», einer Person oder («Nicht zugewiesen») filtern. Das Dashboard zeigt «x bei Ihnen» und wie viele aktive Aufträge ohne Zuständigkeit sind.
- **Benachrichtigungen:** Meldungen zu Annahme/Ablehnung einer Offerte sowie zu Online-Zahlungen gehen an die Team-Adresse und zusätzlich an die zuständige Person (falls abweichend).
- **Kundenansicht:** Der Name der Ansprechperson erscheint auf Offerte, Offerten-PDF und im Kundenportal (nur Name, keine E-Mail). Abschaltbar unter *Einstellungen → Offerten → Ansprechperson für Kunden anzeigen*.
- Wird ein Benutzer gelöscht, werden seine Aufträge «nicht zugewiesen»; bei Deaktivierung bleiben Zuweisungen bestehen, die Person ist aber nicht mehr auswählbar. Die API liefert `created_by` (Offerten) bzw. `responsible_id` (Aufträge).

### Zwei-Faktor-Anmeldung (optional)
Als zweiter Faktor stehen **Passkeys** und/oder eine **Authenticator-App** zur Verfügung (frei kombinierbar, nie Pflicht).

**Passkeys** (*Mein Profil → Passkeys → Passkey hinzufügen*): Fingerabdruck, Gesichtserkennung, Geräte-PIN, Passwort-Manager (z. B. 1Password, iCloud-Schlüsselbund) oder Sicherheitsschlüssel (z. B. YubiKey). Beim Hinzufügen des ersten zweiten Faktors werden 8 Wiederherstellungscodes angezeigt (einmalig, sicher aufbewahren). Bei der Anmeldung erscheint nach Passwort bzw. Anmeldelink «Mit Passkey bestätigen»; alternativ geht jederzeit ein Authenticator- oder Wiederherstellungscode. Technik: WebAuthn ohne externe Bibliothek (ES256/RS256, Attestation «none»); geprüft werden Challenge (einmalig, 5 Min.), Origin, Domain-Bindung, Anwesenheit und Signatur sowie der Signaturzähler (Schutz vor kopierten Schlüsseln). Gespeichert wird nur der öffentliche Schlüssel, keine biometrischen Daten. Entfernen eines Passkeys verlangt einen Code (Authenticator- oder Wiederherstellungscode). **Voraussetzung: HTTPS** (die Basis-URL in `app/config.php` muss mit `https://` beginnen; `localhost` genügt zum Testen). Passkeys sind an die Domain gebunden – bei einem Domainwechsel müssen sie neu registriert werden. Administratoren können 2FA inkl. Passkeys eines Kontos zurücksetzen.

**Authenticator-App** (*Mein Profil → Authenticator-App → Einrichten*): QR-Code mit einer Authenticator-App scannen, Code bestätigen, die 8 Wiederherstellungscodes sicher aufbewahren. Danach folgt auf Passwort- oder Magic-Link-Login die Code-Abfrage. Verloren? Wiederherstellungscode verwenden oder durch einen Administrator unter *Benutzer* zurücksetzen lassen. 

### Anhänge
Auf Offerte, Auftrag und Rechnung können Dateien hochgeladen werden (PDF, Bilder, Office, ZIP, TXT/CSV; max. 10 MB, serverseitig anhand Endung und Inhalt geprüft). Standardmässig nur intern; mit „Für Kunde sichtbar“ erscheinen sie auf der öffentlichen Seite bzw. im Portal. Das Limit hängt zusätzlich von `upload_max_filesize` / `post_max_size` des Hostings ab (die mitgelieferte `.htaccess` setzt 10M/12M, sofern erlaubt).

### Digitale Unterschrift (drei Modi)
Im Offerten-Editor unter *Gültigkeit* wählen Sie pro Offerte:

| Modus | Verhalten |
|---|---|
| **Keine Unterschrift** | Der Kunde nimmt ohne Unterschrift an. |
| **Offene Unterschrift** | Eine Unterschrift ist Pflicht. Jede Person, die den Offertenlink hat, kann Namen eintragen und unterschreiben. |
| **Strikte Unterschrift** | Nur eine festgelegte **natürliche Person** (Vor- und Nachname + E-Mail-Adresse) kann unterschreiben. Bleiben die Felder leer, wird die Kontaktperson des Kunden verwendet. |

So funktioniert die strikte Unterschrift:
1. Auf der Kundenseite ist der Name fest vorgegeben (nicht änderbar); die E-Mail-Adresse wird nur maskiert angezeigt (z. B. `h•••@example.ch`).
2. Die Person fordert einen **6-stelligen Code** an. Er wird ausschliesslich an die in der Offerte hinterlegte Adresse gesendet – nie an eine vom Besucher eingegebene.
3. Annahme nur mit gültiger Unterschrift **und** korrektem Code. Der Code ist 15 Minuten gültig, einmalig, wird nur als Hash gespeichert und nach 5 Fehlversuchen gesperrt; zusätzlich gelten Begrenzungen (1 Code pro Minute, 5 pro Stunde und Offerte, Limits pro IP).
4. Gespeichert werden Name, bestätigte E-Mail-Adresse, Zeitpunkt der Bestätigung, IP und das Unterschriftsbild; sichtbar in der Offerte und im PDF.

Hinweis: Der Offertenlink geht an den Kunden; leitet dieser ihn an die unterschreibende Person weiter, braucht diese nur Zugriff auf ihr Postfach. Wer den Link kennt, aber das Postfach nicht kontrolliert, kann nicht unterschreiben. Das ist eine einfache elektronische Unterschrift, keine qualifizierte (QES). Bestehende Offerten mit „Unterschrift verlangen“ wurden beim Update auf *Offene Unterschrift* umgestellt.

### Integrationen: API, Webhooks, Buchungsjournal (nur Administratoren)
*Integrationen* in der Navigation.

**API-Token:** Name, Bereiche (Rechnungen, Offerten, Aufträge, Kunden) und optional Gültigkeit wählen. Das Token (`omk_…`) wird nur einmal angezeigt und nur als Hash gespeichert; Widerruf jederzeit. Aufruf:
```
curl -H "Authorization: Bearer omk_…" "https://ihre-domain.ch/api/v1/invoices?from=2026-01-01&to=2026-12-31"
```
Endpunkte (nur GET): `/api/v1/ping`, `/invoices`, `/offers`, `/orders`, `/customers`, je auch `/{id}`. Filter: `status`, `from`, `to`, `customer_id`, `page`, `per_page` (max. 100). Rechnungsentwürfe werden nie ausgegeben; Kundendaten nur mit dem Bereich „Kunden“. Limit 120 Anfragen/Minute, Fehlversuche werden pro IP gebremst.

**Webhooks:** Ereignisse `offer.accepted`, `offer.declined`, `order.created`, `invoice.sent`, `invoice.partial_payment`, `invoice.paid`, `invoice.cancelled`. Jeder Aufruf ist ein JSON-POST mit den Headern `X-Offerten-Event`, `X-Offerten-Delivery` und `X-Offerten-Signature: t=<Unix>,v1=<HMAC-SHA256(secret, "<t>.<body>")>`. Empfänger sollten die Signatur prüfen und `t` auf Aktualität (z. B. 5 Minuten) kontrollieren. Das Secret wird einmal angezeigt (erneuerbar). Bei Fehlern wird bis zu 6-mal wiederholt (1 min, 5 min, 30 min, 2 h, 12 h); die Zustellungen werden per Cronjob bzw. beim Aufruf der Administration nachgeführt. Die Nutzdaten sind datensparsam (Kennungen, Nummern, Beträge, Kundenname/-nummer). Schutz vor SSRF: nur https, nur öffentliche IP-Adressen (DNS-Pinning), keine Weiterleitungen.

**Buchungsjournal:** Kontenzuordnung (Debitoren, Ertrag, Bank, Online, Kasse) festlegen und den Zeitraum als CSV herunterladen: Rechnungen als „Debitoren an Ertrag“ je Steuersatz (Betrag brutto mit Steuersatz und -betrag, Schlussrechnungen abzüglich Anzahlungen) und Zahlungseingänge (auch Teilzahlungen) als „Bank/Kasse/Online an Debitoren“. Das Format ist generisch; es ist **kein** softwarespezifischer Export (z. B. Bexio, Abacus), die Spalten werden im Import der Buchhaltungssoftware zugeordnet.

### Online-Zahlung (Mollie / Stripe)
*Einstellungen → Zahlungen* (nur Administratoren): Anbieter aktivieren und Schlüssel hinterlegen (verschlüsselt gespeichert, im Formular nur maskiert sichtbar).
- **Mollie**: API-Key (`live_…` oder `test_…`) aus dem Mollie-Dashboard. Der Webhook wird pro Zahlung automatisch mitgegeben (nur bei öffentlicher HTTPS-Adresse); ohne Webhook wird der Status beim Zurückkehren des Kunden abgefragt.
- **Stripe**: geheimer Schlüssel (`sk_…`/`rk_…`) und Webhook-Secret. Im Stripe-Dashboard einen Webhook-Endpunkt `https://IHRE-DOMAIN/webhooks/stripe` mit den Ereignissen `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired` anlegen und das Signing-Secret (`whsec_…`) eintragen.
- Ablauf: Auf der Rechnungsseite (`/r/{ID}`) erscheint bei Status *Offen/Überfällig* der Button «Online bezahlen». Der Kunde zahlt beim Anbieter und kehrt zur Rechnung zurück. **Der Status wird immer direkt beim Anbieter abgefragt**, Webhook-Inhalte werden nicht vertraut (Stripe-Signatur wird zusätzlich geprüft). Erst bei bestätigter Zahlung im richtigen Betrag/Währung wird sie im Zahlungsjournal verbucht (und die Rechnung bei vollständiger Deckung *Bezahlt*) (Verlauf, Bestätigungs-E-Mail mit **Zahlungsbestätigung als PDF** (Quittung) an den Kunden – diese ist auch bei manuell als bezahlt markierten Rechnungen auf der Rechnungsseite und im Admin («Quittung») abrufbar; Benachrichtigung ans Team, Webhook `invoice.paid`).
- Abweichender Betrag, Doppelzahlung oder Zahlung auf stornierte Rechnung werden **nicht** automatisch verbucht, sondern im Verlauf und in der Karte «Online-Zahlungen» der Rechnung vermerkt (Rückerstattung im Anbieter-Dashboard prüfen). «Status abfragen» in dieser Karte holt den Stand manuell.
- Testmodus: Mit Test-Schlüsseln wird «Testmodus» angezeigt; Test- und Live-Schlüssel nicht mischen. Buchungsjournal: Online-Zahlungen laufen auf das Konto «Online-Zahlungen» (Standard 1021, unter *Integrationen*).
- Teilzahlung: Bei Rechnungen mit Zahlungsplan wird die nächste Rate bezahlt, sonst der offene Betrag; der Kunde kann auch den gesamten Rest wählen. Die Rechnung wird erst *Bezahlt*, wenn der gesamte Betrag gedeckt ist.
- Grenzen: nur CHF/EUR u. ä. Währungen mit 2 Dezimalstellen, Anbietergebühren und Auszahlungen werden nicht verbucht. Die Anbieter-Anbindung wurde gegen simulierte Schnittstellen getestet, nicht gegen die echten Dienste – bitte zuerst im Testmodus mit einer echten Testzahlung prüfen.
- Datenschutz: Mollie bzw. Stripe sind Zahlungsdienstleister (Vertrag/AVV mit dem Anbieter prüfen, ggf. Drittlandtransfer); an sie gehen Betrag, Rechnungsnummer und ggf. die Kunden-E-Mail. Kartendaten gelangen nie auf Ihren Server. Die Datenschutzerklärungs-Vorlage nennt die Anbieter.

### Anfrageformular (Startseite)
Im Website-Builder den Baustein **Anfrageformular** hinzufügen (Titel, Text, optionale Felder Firma/Telefon/Betreff, Button-Text). Der Button «Offerte anfragen» in der Kopfzeile springt automatisch zum Formular, wenn kein anderes Ziel gesetzt ist. Unter *Anfragen* (Navigation, Zähler für neue Anfragen) erscheinen alle Eingänge.
- **Ablauf**: Eine Anfrage legt – nach bestandenen Prüfungen – einen **Kunden** an (nur wenn die E-Mail noch nicht existiert; bestehende Kunden werden nicht verändert; ohne Portalzugang, Adresse ist zu ergänzen) und benachrichtigt das Team per E-Mail. Die anfragende Person erhält optional eine Eingangsbestätigung (*Einstellungen*-unabhängig im Setup wählbar). In der Anfrage führt **Offerte erstellen** in den Offerten-Editor mit vorbelegtem Kunden und Titel und zeigt die Anfrage oben an; nach dem Speichern gilt sie als erledigt und ist mit der Offerte verknüpft. (Es wird bewusst kein leerer Offertenentwurf angelegt, damit keine Nummern ungenutzt bleiben.)
- **Schutz**: CSRF-Token, verstecktes Honeypot-Feld, Mindest-Ausfüllzeit von 3 Sekunden, Begrenzung pro IP (5/Stunde) und pro E-Mail (3/Tag), Anfragen mit mehr als zwei Links werden als *Spam* abgelegt (ohne Kunde und Benachrichtigung). Kein Captcha und keine Drittanbieter.
- **Datenschutz**: ohne Einwilligung (Häkchen mit Link zur Datenschutzerklärung) wird nichts gespeichert; Daten sparsam (Name, E-Mail, Nachricht, optional Firma/Telefon/Betreff); IP-Adresse wird nach der IP-Frist entfernt, Anfragen werden nach 180 Tagen automatisch gelöscht. Die Kundenauskunft enthält Anfragen, die Anonymisierung löscht sie.
- Ausschalten: Setting «Anfrageformular» im Setup bzw. Baustein ausblenden; ohne aktiviertes Formular wird `/anfrage` abgelehnt.

### Systemstatus (Health-Check)
*Systemstatus* (nur Administratoren) prüft: PHP-Version und -Erweiterungen, Speicherplatz, Schreibrechte, Fehlerprotokoll der letzten 24 Stunden, HTTPS, Debug-Modus, Dateirechte von `config.php`, verbliebene `setup.php`, 2FA/Passkey der Administratoren, Datenbankschema, **Cronjob-Lauf** (Heartbeat durch `cron.php`), fehlgeschlagene Webhooks, **Backup** (zuletzt heruntergeladen), E-Mail-Versand (Transport, Fehlerquote), Firmendaten, **UID/MWST-Nummer**, IBAN, Standard-Steuersatz, Test-/Live-Modus der Zahlungsanbieter und liegengebliebene Anfragen. Jede Prüfung nennt, was zu tun ist. In der Navigation zeigt ein Zähler offene Hinweise (alle 5 Minuten aktualisiert).
- **Monitoring**: «Monitoring-Adresse erzeugen» liefert `https://ihre-domain.ch/health?token=…` für Überwachungsdienste (z. B. UptimeRobot). Die Antwort ist JSON mit Gesamtstatus und je Prüfung nur ID und Status (keine Details), HTTP **503** bei einem Problem, sonst 200. Ohne Token antwortet der Endpunkt mit 404; der Token lässt sich erneuern oder widerrufen. Begrenzung 60 Anfragen/Minute und IP.

### Kundenportal
Der Kunde gibt unter `/portal` seine E-Mail-Adresse ein und erhält einen Link (30 Min. gültig, einmalig). Die Antwort ist immer gleich – es wird nicht verraten, ob eine Adresse existiert. Aus der Kundenansicht kann das Team einen Link mit 24 h Gültigkeit senden.

### Benutzer
*Benutzer* (nur Administratoren): Name, E-Mail, Rolle, optional Passwort. Ohne Passwort erhält die Person eine Einladung per Magic-Link und kann danach unter *Mein Profil* ein Passwort setzen. Alle Mitarbeitenden können sich auch per Anmeldelink einloggen (dient zugleich als „Passwort vergessen“).

---

## 6. Steuern

Das System ist auf die **Schweizer Mehrwertsteuer (MWST)** ausgelegt. Die Steuerart wird **pro Position** gewählt (nie pro Kunde) und bestimmt Berechnung, Pflichthinweis auf dem Beleg und Ziffer der MWST-Abrechnung.

### 6.1 Steuerarten

| Steuerart | Beispiel | Steuer | Hinweis auf dem Beleg | Ziffer der Abrechnung |
|---|---|---|---|---|
| **Steuerpflichtig (Inland)** | Beratung, Software, Waren in der Schweiz/Liechtenstein | 8.1 % Normalsatz · 2.6 % reduziert (Lebensmittel, Medikamente, Bücher, Zeitungen) · 3.8 % Beherbergung | Satz und Betrag je Satz | 200, 303 / 313 / 343 |
| **Freiwillig versteuert (Option, Art. 22)** | Vermietung/Leistung, die an sich ausgenommen ist, aber freiwillig versteuert wird | mit Satz | wie steuerpflichtig | 200, **205**, 303 ff. |
| **Ausfuhr, steuerbefreit (Art. 23)** | Waren, die nachweislich ins Ausland gelangen | 0 % (Vorsteuerabzug bleibt) | «Von der Steuer befreite Leistung (Ausfuhr, Art. 23 MWSTG)» | 200, **220** |
| **Im Ausland erbracht (Art. 8)** | Dienstleistungen an Empfänger im Ausland (Empfängerortsprinzip) | 0 % | «Leistung im Ausland erbracht (Art. 8 Abs. 1 MWSTG) … Reverse Charge» | 200, **221** |
| **Meldeverfahren (Art. 38)** | Übertragung eines Geschäftsvermögens bei Umstrukturierung | 0 % | «Übertragung im Meldeverfahren (Art. 38 MWSTG)» | 200, **225** |
| **Ausgenommen (Art. 21)** | Versicherung, Bildung, Gesundheit, Vermietung, Kultur … | 0 %, kein Vorsteuerabzug | «Von der Steuer ausgenommene Leistung (Art. 21 MWSTG)» | 200, **230** |
| **Durchlaufposten (Art. 24 Abs. 6)** | im Namen und auf Rechnung des Kunden weiterverrechnete Auslagen | 0 % | «Durchlaufposten … nicht Teil des steuerbaren Entgelts» | nicht im Umsatz (Information im Bericht) |
| **Ohne MWST** | nur wenn Sie **nicht** MWST-pflichtig sind | 0 % | «Nicht MWST-pflichtig» | – |

Unter *Steuervorlagen* legen Sie Vorlagen je Steuerart an. Bei den Sonderarten sind Satz (0 %) und Kennzeichnung fest, damit sich die Art auch aus bereits ausgestellten Belegen sicher bestimmen lässt.

**Satzänderungen**: Den alten Satz mit «Gültig bis» befristen, den neuen mit «Gültig ab» anlegen. Abgelaufene Sätze erscheinen in neuen Belegen nicht mehr; bestehende Belege behalten ihren Satz. Massgebend ist der Zeitpunkt der Leistung – die Plausibilitätsprüfung warnt, wenn ein Satz am Rechnungsdatum nicht gültig ist (z. B. 7.7 % im Jahr 2026). Der Systemstatus warnt bei Sätzen, die von 8.1 / 2.6 / 3.8 % abweichen (Tippfehler).

### 6.2 MWST-pflichtig oder nicht

(*Einstellungen → Firma*, Setup Schritt 3)
- **Pflichtig**: MWST wird je Satz ausgewiesen; auf Rechnungen erscheinen Ihre UID im Format «CHE-123.456.789 MWST», Name und Adresse beider Parteien, Leistungsdatum bzw. -zeitraum, Leistungsart, Entgelt sowie Satz und Betrag (Art. 26 MWSTG). Ohne gültige UID oder ohne Kundenadresse wird die Rechnung nicht versendet.
- **Nicht pflichtig**: keine MWST-Zeilen, Hinweis «Nicht MWST-pflichtig», nur die Steuerart «Ohne MWST». Wer MWST ausweist, schuldet sie (Art. 27 Abs. 2 MWSTG) – deshalb sperrt das System das Ausweisen.
- **Eintragungsgrenze**: Der Bericht und der Systemstatus zeigen für Nicht-Pflichtige den steuerbaren Weltumsatz (Kalenderjahr bzw. letzte 12 Monate) gegen die Grenze von CHF 100’000 (Warnung ab 80 %, Problem ab 100 % – dann Eintragung bei der ESTV beantragen, Art. 10 Abs. 2 MWSTG). Ausgenommene Leistungen und Durchlaufposten zählen nicht mit.
- **Abrechnungsart**: *vereinbarte Entgelte* (Steuer entsteht mit der Rechnung) oder *vereinnahmte Entgelte* (mit dem Zahlungseingang, bei Teilzahlungen anteilig, Rückerstattungen mindern).
- **Abrechnungsperiode**: vierteljährlich (effektive Methode), halbjährlich (üblich beim Saldosteuersatz) oder jährlich; im MWST-Bericht springt «Letzte Abrechnungsperiode» auf den zuletzt abgeschlossenen Zeitraum.
- **Saldosteuersatz** (optional, nur mit Bewilligung der ESTV): Rechnungen weisen weiterhin den Normalsatz aus; die geschuldete Steuer berechnet sich mit dem Saldosatz auf den **Brutto**-Entgelten der steuerbaren Leistungen (Ausfuhr, Ausland und ausgenommene Leistungen sind nicht Teil der Basis).

### 6.3 Inland, Ausland, Liechtenstein

- **Liechtenstein** gehört zum Schweizer Mehrwertsteuergebiet und gilt als Inland.
- **Kunde im Ausland**: Dienstleistungen gelten am Sitz des Empfängers als erbracht (Art. 8 Abs. 1 MWSTG) → «Im Ausland erbracht», keine Schweizer MWST. Warenlieferungen ins Ausland → «Ausfuhr, steuerbefreit» mit Ausfuhrnachweis (Zollbeleg/Versanddokumente, 10 Jahre aufbewahren). Der Offerten-Editor schlägt beim Wählen eines Auslandkunden die passende Art vor.
- **Ausnahmen des Leistungsorts** (Art. 8 Abs. 2): Leistungen an anwesende Personen, Kultur/Sport/Unterricht, Gastgewerbe und Grundstücke werden dort besteuert, wo sie erbracht werden bzw. das Grundstück liegt. Hier wählen Sie die Steuerart manuell; die Plausibilitätsprüfung warnt bei Schweizer MWST für Auslandkunden und umgekehrt.
- **Bezugsteuer** (Leistungen, die Sie von ausländischen Anbietern beziehen) sowie Vorsteuer sind **nicht** Teil dieses Systems (Verkaufs-/Rechnungssystem) – Ziff. 382/383 und 400 ff. der Abrechnung tragen Sie selbst ein.

### 6.4 Plausibilitätsprüfung

Auf Offerten und Rechnungsentwürfen erscheinen Hinweise («MWST-Prüfung»), z. B. bei: Schweizer MWST für Auslandkunden, Ausfuhr/Ausland bei Inlandkunden, Warenlieferung als Dienstleistung, ungültigem Satz am Rechnungsdatum, Positionen ohne Steuerart. **Blockierend** (Rechnung kann nicht versendet werden): fehlende eigene UID bei MWST-Pflicht, fehlender Kundenname/-adresse, MWST bei nicht Pflichtigen. Die Prüfung ersetzt keine Beratung.

### 6.5 Korrekturen: Gutschrift, Skonto, Rückerstattung

Nachträgliche Entgeltsminderungen mindern die geschuldete MWST (Art. 41 MWSTG).
- **Gutschrift**: *Rechnung → Korrekturen → Gutschrift erstellen* (ganze Rechnung oder Teilbetrag inkl. MWST). Es entsteht ein Entwurf mit eigener Nummer und negativen Beträgen; die Steuerarten und -sätze folgen anteilig der Rechnung. Beim Ausstellen wird sie mit der Rechnung verrechnet (die Rechnung wird entsprechend weniger offen bzw. bei bezahlten Rechnungen entsteht ein Guthaben). Gutschriften sind danach unveränderlich.
- **Skonto / Preisnachlass**: ohne eigenen Beleg erfassbar; reduziert den offenen Betrag und die MWST anteilig. Eigenes Journalkonto (*Integrationen → Buchungsjournal*, Standard 3800).
- **Rückerstattung**: erfasst die Rückzahlung an den Kunden (als negative Zahlung).
- **Stornieren** versendeter Rechnungen ist nur ohne Zahlungen, Skonto und Gutschriften möglich; bei Rechnungen aus früheren Abrechnungsperioden korrigieren Sie per Gutschrift, damit die bereits deklarierte Periode nicht rückwirkend verändert wird.

### 6.6 MWST-Abrechnung (Bericht)

*Berichte → MWST-Abrechnung* bereitet die Ziffern des ESTV-Formulars für den gewählten Zeitraum vor (auch als CSV):

| Ziff. | Inhalt |
|---|---|
| 200 | Total der vereinbarten bzw. vereinnahmten Entgelte (weltweit, ohne MWST) |
| 205 | davon Leistungen mit Option |
| 220 / 221 / 225 / 230 | Ausfuhr / im Ausland erbracht / Meldeverfahren / ausgenommene Leistungen |
| 235 | Entgeltsminderungen (Gutschriften, Skonto, Rabatte) |
| 289 · 299 | Total Abzüge · steuerbarer Gesamtumsatz (200 − 289) |
| 303 · 313 · 343 | Leistungen und Steuer zu 8.1 · 2.6 · 3.8 % |
| 399 | Total geschuldete Steuer (bei Saldosteuersatz: Saldosatz × Brutto-Entgelte) |

Der Bericht prüft sich selbst: Stimmt Ziff. 299 nicht mit der Summe der Leistungen je Satz überein, oder gibt es Positionen ohne Steuerart, erscheint eine Warnung. **Nicht enthalten:** Vorsteuer (Ziff. 400 ff.), Bezugsteuer (382/383), Eigenverbrauch, Einlageentsteuerung, Margenbesteuerung, Steueranrechnungen sowie die Einreichung (SuisseTax) – diese tragen Sie selbst ein.

### 6.7 Berechnung

- Der Standardsatz der Offerte gilt für alle Positionen und kann pro Position überschrieben werden.
- **Rabatte**: Positionsrabatte reduzieren die Position; der Gesamtrabatt wird anteilig auf die Steuergruppen verteilt (die Steuerbasis ist das Entgelt nach Rabatt).
- **Versandkosten** werden mit dem Standardsatz der Offerte besteuert.
- **Rundung**: Steuern pro Satz kaufmännisch auf 0.01; bei CHF optional Gesamtbetrag auf 0.05 (Differenz wird als Rundungsdifferenz ausgewiesen und ist nicht Teil der Steuerbasis).
- **Anzahlungen**: Die Schlussrechnung zieht Anzahlungen samt der darin enthaltenen Steuer je Satz ab.

### 6.8 Tests

`php tests/run.php` prüft ohne Datenbank Berechnung, Rundung, Gutschrift-Vorzeichen, UID-Prüfziffer, Steuerarten, Pflichthinweise, Abrechnungsziffern und IBAN (39 Fälle). Vor jedem Update ausführen.

> Die Software unterstützt die korrekte Rechnungsstellung und die Vorbereitung der Abrechnung, ersetzt aber keine Steuerberatung und keine Abrechnung mit der Eidgenössischen Steuerverwaltung (ESTV). Sätze, Pflicht, Steuerart und Abrechnungsart bitte mit Ihrer Treuhandstelle abstimmen.

---

## 7. Sicherheit

- **Datenbank**: ausschliesslich PDO mit nativen Prepared Statements.
- **Passwörter**: `password_hash` mit Argon2id (Fallback bcrypt), automatisches Rehashing.
- **Magic-Links**: 256-Bit-Zufallstoken, in der Datenbank nur als SHA-256-Hash, einmalig verwendbar, 30 Min. (Einladungen 24 h). Die Anmeldung erfordert einen Klick auf „Jetzt anmelden“ (POST) – E-Mail-Scanner, die Links vorab öffnen, verbrauchen das Token nicht.
- **CSRF**: Token auf allen POST-Anfragen, inkl. öffentlicher Offertenseite und AJAX.
- **Sessions**: `HttpOnly`, `SameSite=Lax`, `Secure` bei HTTPS, Strict Mode, neue Session-ID bei Anmeldung, Inaktivitäts-Timeout (Team 8 h, Kunden 4 h).
- **Brute-Force-Schutz**: Begrenzung von Login-Versuchen und Link-Anforderungen pro E-Mail und IP.
- **Rollen**: Benutzerverwaltung nur für Administratoren (serverseitig geprüft).
- **Offerten-IDs**: 24 Zeichen aus 57 Symbolen (~140 Bit) – nicht erratbar, Seiten mit `noindex`.
- **Kundendaten**: Portal zeigt nur eigene Daten; Zugriffe auf fremde Aufträge ergeben 404.
- **Ausgabe**: konsequentes HTML-Escaping; Links (Tracking/Download) nur mit `http(s)://`.
- **Dateien**: `app/` und `storage/` per `.htaccess` gesperrt, Konfiguration mit Rechten 0640, SMTP-Passwort verschlüsselt.
- **Installer**: sperrt sich nach der Installation selbst – trotzdem löschen.
- **Header**: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, HSTS bei HTTPS.

- **Content-Security-Policy**: Ressourcen nur vom eigenen Server (`default-src 'self'`), Formulare nur an die eigene Domain.
- **Audit-Log** und optionale **2FA** (siehe 5); **API-Tokens** und Webhook-Secrets werden nur gehasht bzw. verschlüsselt gespeichert.

### 7.1 Rollen und heikle Einstellungen

| Bereich | Administrator | Team |
|---|:-:|:-:|
| Offerten, Aufträge, Rechnungen, Kunden, Positionsvorlagen, Produktbereiche, Versandarten | ✓ | ✓ |
| Einstellungen (Firma, Offerten, Rechnungen, E-Mail/SMTP), Test-E-Mail | ✓ | – |
| Steuervorlagen | ✓ | – |
| E-Mail-Protokoll, Audit-Log | ✓ | – |
| Integrationen (API, Webhooks, Journal), Backup | ✓ | – |
| Datenauskunft (Export) eines Kunden | ✓ | ✓ |
| Datenschutz: Einschränken / Anonymisieren, Aufbewahrungsfristen | ✓ | – |
| Benutzerverwaltung, 2FA-Reset anderer Benutzer | ✓ | – |
| Berichte und CSV-Export | ✓ | – |
| Eigenes Profil / Passwort / eigene 2FA | ✓ | ✓ |
| Abos, Anhänge, PDF-Export | ✓ | ✓ |

Die Sperre gilt serverseitig (HTTP 403 auch bei direktem Aufruf oder POST), nicht nur in der Navigation. Team-Mitglieder können die Steuersätze weiterhin in Offerten verwenden, aber nicht verändern.


### 7.2 Datenschutz (DSGVO / Schweizer DSG)

Die Software unterstützt Sie bei der Umsetzung; die rechtliche Verantwortung (Verzeichnis der Verarbeitungstätigkeiten, Auftragsverarbeitungsverträge, Datenschutzerklärung, Meldepflichten) liegt beim Betreiber. Die mitgelieferte Datenschutzerklärung (`/datenschutz`) ist eine **Vorlage** und ersetzt keine Rechtsberatung – bitte prüfen und anpassen (eigener Text unter *Einstellungen → Datenschutz*).

**Technische und organisatorische Massnahmen (Art. 32 DSGVO)**
- Keine Drittanbieter-Anfragen (ausser der Weiterleitung zum Zahlungsanbieter, wenn der Kunde bewusst «Online bezahlen» wählt): Tailwind, Boxicons und Inter werden lokal ausgeliefert; die CSP erlaubt nur den eigenen Server. Es gibt **kein Tracking und keine Analyse**, nur ein technisch notwendiges Sitzungs-Cookie (kein Cookie-Banner nötig). Hinweis: Eine Logo-URL auf fremdem Server würde dessen Betreiber die IP der Besucher übermitteln – Logo besser auf der eigenen Domain ablegen.
- Zugriffsschutz: Rollen, optionale 2FA, Brute-Force-Bremse, gehashte Passwörter, verschlüsselte Geheimnisse, Audit-Log.
- Datenminimierung: Webhooks/API liefern nur die nötigen Felder; Kundendaten nur mit eigenem Token-Bereich.
- Vertraulichkeit der Übertragung: HTTPS (HSTS), Magic-Links einmalig und kurzlebig. PDF-Anhänge enthalten personenbezogene Daten – bei Bedarf abschaltbar.

**Betroffenenrechte pro Kunde** (*Kundenansicht → Datenschutz*)
- **Auskunft / Übertragbarkeit (Art. 15, 20)**: JSON mit Stammdaten, Offerten (inkl. Positionen, Unterschrift), Aufträgen, Rechnungen, Verlauf, Anhangsliste und E-Mail-Protokoll. Kunden können dies selbst im Portal herunterladen.
- **Einschränkung (Art. 18)**: löscht Telefon, Website, Notizen, Zugangsdaten und IP-Adressen, sperrt das Portal und verhindert neue Offerten und E-Mails. Rechnungsdaten bleiben gesperrt gespeichert.
- **Anfragen** über das Formular werden in die Auskunft aufgenommen und bei der Anonymisierung gelöscht.
- **Löschung (Art. 17)** durch unumkehrbare **Anonymisierung** von Stammdaten, Offerten-/Rechnungs-Snapshots, Lieferadressen, Unterschriften, Anhängen, Verlaufsnotizen und E-Mail-Protokoll. Sie ist erst möglich, wenn keine offenen Offerten, laufenden Aufträge/Abos oder unbezahlten Rechnungen bestehen **und** die gesetzliche Aufbewahrungsfrist der Rechnungen abgelaufen ist (Standard 10 Jahre, einstellbar). Bis dahin gilt Art. 17 Abs. 3 lit. b DSGVO (rechtliche Verpflichtung); die Software nennt die Gründe und den frühestmöglichen Zeitpunkt.

**Automatische Aufbewahrungsfristen** (*Einstellungen → Datenschutz*, täglich per Cronjob bzw. Administration): IP-Adressen (Standard 90 Tage), E-Mail-Protokoll (365 Tage), Audit-Log (365 Tage), Anfragen (180 Tage); Einmalcodes, abgelaufene Anmeldelinks und Webhook-Zustellungen werden nach wenigen Tagen gelöscht.

**Datensicherung**: *Einstellungen → Datenschutz → Datensicherung* lädt die Datenbank (`.sql.gz`) bzw. zusätzlich die Anhänge (`.zip`) herunter. Der App-Schlüssel (`app/config.php`) ist **nicht** enthalten und muss separat gesichert werden. Backups enthalten personenbezogene Daten: verschlüsselt ablegen und nach Gebrauch löschen. Gelöschte/anonymisierte Kunden können durch ältere Backups wieder auftauchen – Backups deshalb nach Ihrer Aufbewahrungsrichtlinie rotieren.

**Verzeichnis der Verarbeitungstätigkeiten (Art. 30)**: *Einstellungen → Datenschutz → Verzeichnis der Verarbeitungstätigkeiten* erzeugt aus Ihren aktuellen Einstellungen (E-Mail-Anbieter, Mollie/Stripe, Webhooks/API, Fristen) eine druckbare Tabelle. Sie ist eine Vorlage und von Ihnen zu prüfen und zu ergänzen.

**Vollständigkeit**: Auskunft und Anonymisierung erfassen auch Zahlungseingänge (Referenz/Notiz), Skonto-/Korrekturbuchungen, Lieferadressen auf Rechnungen und Aufträgen, Einmalcodes der Unterschrift und die gespeicherte Unterschrift. Beim Löschen einer Offerte wird die Unterschriftsdatei mitgelöscht.

**Pflichten des Betreibers (Checkliste)**: Auftragsverarbeitungsvertrag mit Hoster, E-Mail-Anbieter und (falls aktiviert) Mollie/Stripe; Standort/Drittlandtransfer des Hostings prüfen; Verzeichnis der Verarbeitungstätigkeiten führen; Datenschutzerklärung prüfen/verlinken (Footer der Kundenseiten enthält den Link); Prozess für Auskunfts- und Löschanfragen und für Datenpannen (Meldung innerhalb 72 h) festlegen.

---

## 8. Wartung, Backup, Update

**Backup** – in Plesk unter *Sichern & Wiederherstellen* regelmässig Datenbank **und** Dateien sichern. Unverzichtbar: die Datenbank und `app/config.php` (App-Schlüssel).

**Protokolle**
- `storage/logs/app.log` – Fehler mit Fehlernummer (wird Benutzern angezeigt, z. B. `#81BD35D2`)
- `storage/logs/php-error.log` – PHP-Meldungen
- *Administration → E-Mail-Protokoll* – jeder Versand mit Fehlermeldung des Servers
- *Administration → Audit-Log* – sicherheitsrelevante Ereignisse

**Update** – neue Dateien hochladen, dabei `app/config.php` und `storage/` **nicht überschreiben**. `setup.php` nicht erneut hochladen. Neue Tabellen und Spalten (Rechnungen, Abos, Anhänge, 2FA, Unterschrift, Audit-Log, API/Webhooks, Datenschutz, Online-Zahlungen, Passkeys, Zuständigkeiten, SSO-Verknüpfung – aktuell Schema-Version 12). Programm-Updates können auch aus GitHub bezogen werden (Abschnitt 13). Neue Tabellen und Spalten werden beim ersten Seitenaufruf automatisch angelegt (`schema_version`).

**Neuinstallation** – `app/config.php` und `storage/install.lock` löschen, `setup.php` hochladen und aufrufen. Bestehende Tabellen bleiben erhalten (`CREATE TABLE IF NOT EXISTS`); existiert die Admin-E-Mail bereits, wird deren Passwort neu gesetzt.

**Domainwechsel** – `base_url` in `app/config.php` anpassen. Bereits versendete Offerten-Links zeigen dann auf die alte Domain (ggf. Weiterleitung einrichten).

---

## 9. Fehlerbehebung

| Problem | Lösung |
|---|---|
| Alle Seiten ausser `/` ergeben 404 | `.htaccess` fehlt oder `mod_rewrite` inaktiv. In Plesk *Apache & nginx-Einstellungen*: „Proxymodus“ aktiv lassen bzw. Apache nicht deaktivieren. |
| Nur nginx (ohne Apache) | Unter *Apache & nginx-Einstellungen → Zusätzliche nginx-Anweisungen* eintragen: `location ~ ^/(app\|storage)/ { deny all; }` und `location / { try_files $uri $uri/ /index.php?$query_string; }` |
| Weiterleitung zu `setup.php` | `app/config.php` fehlt bzw. ist nicht lesbar. |
| „Sitzung abgelaufen“ (419) | Seite neu laden. Tritt es dauernd auf: prüfen, ob `base_url` exakt zur aufgerufenen Adresse passt (http/https, www). |
| E-Mails kommen nicht an | *Einstellungen → E-Mail → Test senden*, dann *E-Mail-Protokoll* prüfen. Häufig: falscher Port/Verschlüsselung, Absender ≠ SMTP-Konto, Zertifikat lautet auf anderen Hostnamen. |
| Seite ungestylt | `assets/vendor/` wurde nicht (vollständig) hochgeladen, oder die `Content-Security-Policy` wurde durch eine Serverregel überschrieben. Browser-Konsole prüfen. |
| Webhook „nicht auflösbar“ | Es sind nur öffentliche https-Adressen erlaubt (kein localhost/Intranet). |
| Fehlerseite mit Nummer | Nummer in `storage/logs/app.log` suchen. |

---

## 10. Projektstruktur

```
├── index.php              Front-Controller und Routen
├── cron.php               Cronjob (nur CLI): Lizenzprüfung, Updates, Abos, abgelaufene Offerten
├── style.md               Styleguide: Design, Bibliotheken, Designentscheide
├── tests/run.php          Regressionstests für Steuer- und Rechenlogik (php tests/run.php)
├── setup.php              Installationsassistent (nach Installation löschen)
├── .htaccess              Schöne URLs, Zugriffsschutz, Header
├── assets/
│   ├── vendor/            Tailwind 4, Boxicons 2.1.4, Inter (lokal, MIT/OFL)
│   ├── css/app.css        Animationen, Modals, Tooltips, Druck, prefers-reduced-motion
│   └── js/
│       ├── app.js         Scroll-Einblendung, Dropdowns, Modals, Bestätigungen, Ladezustände, Toasts
│       └── offer-editor.js  Offerten-Editor mit Live-Berechnung
├── app/                   (per .htaccess gesperrt)
│   ├── bootstrap.php      Autoloader, Fehlerbehandlung, Session
│   ├── config.php         (vom Installer erstellt)
│   ├── lib/               DB, Auth, Router, View, Mailer (SMTP), Calc, Schema, Crypto, Totp, WebAuthn, …
│   ├── vendor/            FPDF 1.9 und chillerlan/php-qrcode 5 (beide MIT, ohne Composer eingebunden)
│   ├── services/          License, OfferService, OrderService, InvoiceService, InquiryService, HealthService, SubscriptionService, PdfService, Brand, SiteBuilder, SwissQr, ReportService, AttachmentService, TaxService, Notifier, History
│   ├── controllers/       Admin, Portal, öffentliche Offerte
│   └── views/             Layouts, Partials, Seiten, E-Mail-Vorlage
└── storage/               (per .htaccess gesperrt) logs/, mail/, uploads/ (Anhänge, Unterschriften), install.lock
```

**Design**: Tailwind CSS v4 (Browser-Build, lokal ausgeliefert) mit Designsystem-Komponenten in `app/views/partials/head.php` (`.btn`, `.card`, `.input`, `.badge` …). Die Markenfarbe wird zur Laufzeit aus den Einstellungen in alle Abstufungen (`brand-50` … `brand-900`) umgerechnet. Icons: Boxicons 2.1.4.

**Routen**

| Pfad | Zweck |
|---|---|
| `/o/{id}` | öffentliche Offerte |
| `/r/{id}` | öffentliche Rechnung (nicht für Entwürfe) |
| `/o/{id}/pdf`, `/r/{id}/pdf` | PDF der Offerte bzw. Rechnung |
| `/portal`, `/portal/orders/{id}` | Kundenportal |
| `/admin` … | Administration |


**Drittsoftware**: Tailwind CSS 4 (MIT), Boxicons 2.1.4 (MIT/CC BY 4.0) und Inter (SIL OFL 1.1) liegen unter `assets/vendor/` mit ihren Lizenzdateien. FPDF 1.9 (Olivier Plathey, freie Lizenz) und chillerlan/php-qrcode 5.0.5 inkl. chillerlan/php-settings-container (MIT bzw. Apache-2.0) liegen unter `app/vendor/` mit ihren Lizenzdateien.

---

## 11. Lizenz

VylarisOffers ist **lizenzpflichtig** und prüft die Lizenz über die TRS-License-Prüf-API (`https://licence.vylaris.ch`, Dokumentation 1.3).

**Einrichten**: im Installationsassistenten (Schritt «Firma», Abschnitt *Lizenz*) oder später unter *Lizenz* in der Administration: Lizenzschlüssel eintragen, *Speichern & aktivieren*. Lizenzserver (`https://licence.vylaris.ch`), Produkt-Kennung (`vylarisoffers`) und der öffentliche Ed25519-Schlüssel zur Prüfung der Serverantworten sind fest im Programm hinterlegt (`License::DEFAULT_SERVER`, `DEFAULT_PRODUCT`, `DEFAULT_PUBLIC_KEY`) und weder für Benutzer noch für Administratoren änderbar. Unter *Lizenz → Erweitert* lässt sich nur die Karenzfrist einstellen. Bestehende Installationen laufen nach dem Update im Lesemodus, bis ein Schlüssel hinterlegt ist.

**Ablauf**: Die Aktivierung bindet die Installation an einen Geräteplatz; die **Geräte-ID** besteht aus Domain und zufälliger Installations-ID (Domainwechsel → erneut aktivieren). Danach wird alle 6 Stunden geprüft (beim Aufruf der Administration und per Cronjob), nach Fehlschlägen alle 15 Minuten. Jede Serverantwort wird mit dem fest hinterlegten öffentlichen Schlüssel auf ihre **Signatur** geprüft (libsodium); ausgewertet werden nur die signierten Daten (`payload`), und die gespeicherten Beweise werden bei jeder Anfrage neu verifiziert. *Gerät abmelden* gibt den Geräteplatz frei.

**Server nicht erreichbar**: Es gilt zuerst die automatisch geholte **Offline-Lizenz** (signiert, an die Geräte-ID gebunden, bis zu 30 Tage gültig), danach eine einstellbare **Karenzfrist** (Standard 3 Tage). Eine Sperrung oder ein Ablauf wirkt nach der nächsten Prüfung sofort.

**Lesemodus** (keine, ungültige, gesperrte oder abgelaufene Lizenz): Alles bleibt les- und exportierbar (Offerten, Aufträge, Rechnungen, PDF, CSV, Datenschutz-Auskunft, Backup, Einstellungen, Benutzer). Gesperrt sind neue Einträge und Änderungen bei Offerten, Aufträgen, Rechnungen, Abos, Kunden, Katalog, Webseite und Integrationen, die Annahme von Offerten, das Anfrageformular und die automatische Erzeugung von Abo-Rechnungen. Zahlungseingänge von Mollie/Stripe werden weiterhin verbucht.

### Funktions-Scopes (`features` der Lizenz)

Welche Funktionen freigeschaltet sind, bestimmt das Feld **`features`** der Lizenz bzw. des Tarifs im Lizenz-Panel. Offerten, Aufträge, Kunden, Katalog, Einstellungen, Benutzer, Audit-Log und Datenschutz sind immer enthalten. Nicht enthaltene Funktionen verschwinden aus der Navigation; ihre Seiten liefern HTTP 403 mit Hinweis (API: JSON `license`).

| Scope | Funktion | Setzt voraus |
|---|---|---|
| `invoices` | Rechnungen, QR-Rechnung, Gutschriften, Skonto, Mahnungen, MWST-Prüfung | – |
| `subscriptions` | Abos (wiederkehrende Rechnungen) | `invoices` |
| `payments` | Online-Zahlung per Mollie/Stripe | `invoices` |
| `reports` | Berichte, MWST-Abrechnung, Buchungsjournal, CSV-Export | – |
| `api` | Lesende REST-API, API-Tokens | – |
| `webhooks` | Webhooks an externe Systeme | – |
| `portal` | Kundenportal | – |
| `website` | Website-Baukasten, Anfrageformular, Anfragen | – |
| `signatures` | Digitale Unterschrift bei der Annahme (sonst immer «Keine Unterschrift») | – |
| `sso` | Single Sign-On per OpenID Connect (Abschnitt 12) | – |

Neue Scopes: in `License::FEATURES` (Bezeichnung, Beschreibung, Abhängigkeiten) und `License::ROUTE_FEATURES` (Pfad → Scope) ergänzen; Ansichten prüfen mit `License::has('scope')`.

**Datenschutz**: An den Lizenzserver gehen Lizenzschlüssel, Geräte-ID (Domain + Installations-ID), Gerätename (Firmenname) und die IP-Adresse – keine Kundendaten. Der Lizenzserver erscheint im *Verzeichnis der Verarbeitungstätigkeiten*.

**Hinweis**: Eine Lizenzprüfung in PHP-Code, der beim Kunden liegt, ist eine vertragliche und organisatorische Hürde, kein technisch unumgehbarer Schutz. Die Signaturprüfung verhindert gefälschte Serverantworten und manipulierte Lizenzdaten in der Datenbank, nicht aber das Entfernen der Prüfung aus dem Code.

---

## 12. Single Sign-On (OpenID Connect)

Mitarbeitende können sich mit einem zentralen Konto anmelden (z. B. **Vylaris-Login** unter `https://vylaris.tech`). Voraussetzung: Die Lizenz enthält die Funktion **`sso`**. Ohne sie ist SSO inaktiv, die lokale Anmeldung gilt unverändert.

**Einrichten – nur eine Adresse**: *Einstellungen → Single Sign-On*, bei *Adresse des Anbieters* `https://vylaris.tech` eintragen (auch `vylaris.tech` oder die volle `…/.well-known/openid-configuration`-Adresse werden erkannt), *Verbindung testen*, Haken bei *Aktivieren*, speichern. Die Endpunkte werden über die Discovery-Adresse `…/.well-known/openid-configuration` gelesen. Unterstützt der Anbieter die dynamische Client-Registrierung (RFC 7591), wird die Anwendung automatisch angemeldet; Client-ID und Secret werden gespeichert (Secret verschlüsselt). Andernfalls die **Redirect-URI** `<Basis-URL>/admin/sso/callback` beim Anbieter eintragen und unter *Erweitert* Client-ID und Secret ergänzen. Im Installationsassistenten (Schritt «Firma») kann die Adresse bereits angegeben werden.

**Ablauf**: Authorization-Code-Flow mit PKCE (S256), `state` und `nonce`. Das ID-Token wird gegen die Schlüssel des Anbieters (JWKS) geprüft (RS256/384/512, ES256, EdDSA; `none` und HMAC werden abgelehnt) sowie `iss`, `aud`/`azp`, `exp`, `iat`, `nbf`, `nonce` und `sub`. Eine zweite Anmeldestufe (2FA/Passkey) übernimmt der Anbieter; die lokale 2FA wird bei SSO nicht erneut abgefragt.

**Zuordnung zu Benutzern**: zuerst über Anbieter + `sub` (stabile Kennung), beim ersten Login über die **verifizierte** E-Mail-Adresse (`email_verified=false` wird abgelehnt); danach ist das Konto fest verknüpft. Ein bereits mit einer anderen Kennung verknüpftes Konto wird nicht übernommen. Optional: Liste erlaubter E-Mail-Domains; *Konten automatisch anlegen* (Rolle «Team»; Administrator nur, wenn der Anbieter einen Gruppen-/Rollen-Claim liefert, der der eingestellten *Admin-Gruppe* entspricht).

**Nur SSO**: Mit *Nur Single Sign-On erlauben* verschwinden Passwort- und Magic-Link-Anmeldung. **Notfallzugang** bei Ausfall des Anbieters: `/admin/login?local=1`. Wird die Lizenzfunktion `sso` entzogen, gilt automatisch wieder die lokale Anmeldung.

**Datenschutz**: Der Anbieter erhält die Anmelde-Anfrage (Browser-Weiterleitung); übermittelt werden E-Mail, Name und Kennung (`sub`) der Mitarbeitenden. Der Anbieter steht im *Verzeichnis der Verarbeitungstätigkeiten*. Protokolliert werden `sso_login`, `sso_failed` und `sso_settings` im Audit-Log.

**Nicht enthalten**: SSO für Kundenportal-Zugänge sowie Abmeldung beim Anbieter (RP-initiated Logout).

---

## 13. Updates aus GitHub

Die Software kann sich aus einem GitHub-Repository aktualisieren (*Update* in der Administration, nur Administratoren).

**Repository vorbereiten**: Der Programmcode (mit `index.php`, `app/`, `assets/` …) liegt im Repository – im Hauptverzeichnis oder in einem Unterordner. Ein Update wird als **Release** veröffentlicht (GitHub → Releases → *Draft a new release*), Tag z. B. `v1.1.0`. Wichtig: In `app/bootstrap.php` muss `APP_VERSION` der Versionsnummer des Tags entsprechen (`'1.1.0'`), sonst bricht das Update ab. Vorabversionen (*pre-release*) und Entwürfe werden ignoriert.
- Ohne Anhang wird der automatisch erzeugte Quellcode-Download des Tags verwendet.
- Optional hängen Sie ein fertiges ZIP an (z. B. `vylarisoffers.zip`) – dieses hat Vorrang. Zusätzlich kann eine Datei `vylarisoffers.zip.sha256` (SHA-256 als Hex) angehängt werden; sie wird vor dem Einspielen geprüft.

**Update-Quelle**: Die Quelle (`VylarisGroup/vylaris-offers`) ist im Programm fest hinterlegt (`UpdateService::DEFAULT_REPO`) und über die Oberfläche nicht änderbar; Benutzer und Administratoren können sie nicht umstellen. Unter *Update* lässt sich nur die Automatik ein- und ausschalten. Ist das Repository **privat**, trägt der Betreiber ein Lesetoken (*Fine-grained personal access token*, nur dieses Repository, *Contents: Read-only*) serverseitig in `app/config.php` ein:
```php
'update' => ['token' => 'github_pat_…'],
```
Das Token wird nur an GitHub gesendet, nie an Weiterleitungsziele.

**Ablauf einer Aktualisierung**: neuestes Release lesen → ZIP laden → Prüfsumme, Versionsnummer und Dateipfade prüfen → **Sicherung** (Datenbank und alle zu überschreibenden Programmdateien nach `storage/update/`, die letzten 3 bleiben) → Dateien einzeln atomar ersetzen. Schlägt etwas fehl, wird automatisch zurückgesetzt. Neue Tabellen/Spalten legt das System beim nächsten Aufruf selbst an (Schema-Migration). *Auf Version x zurücksetzen* stellt die Programmdateien der letzten Sicherung wieder her (die Datenbank bleibt unverändert).

**Automatisch**: Mit *Updates automatisch installieren* prüft und installiert der Cronjob (`cron.php`) neue Versionen derselben Hauptversion selbstständig; Hauptversions-Sprünge (z. B. 1.x → 2.0) werden immer manuell installiert. Ohne Cronjob gibt es keine Automatik. Ergebnis und Fehler stehen im Audit-Log, in der Cron-Ausgabe und im Systemstatus. Geprüft wird höchstens alle 6 Stunden.

**Nie überschrieben werden**: `app/config.php`, `install.lock`, `setup.php` und alles unter `storage/` (Uploads, Logs, Sicherungen). In neueren Versionen entfernte Dateien bleiben liegen (harmlos).

**Voraussetzungen**: PHP-Erweiterungen `zip` und `curl`; der PHP-Benutzer muss die Programmverzeichnisse beschreiben dürfen (bei Plesk meist der Fall; sonst Dateirechte des Abonnements prüfen).

**Sicherheit**: Wer ins Repository bzw. Release schreiben kann, kann Programmcode einspielen. GitHub-Konto mit 2FA schützen, Token nur lesend und nur für dieses Repository. Das Archiv wird gegen Pfad-Tricks (`..`, absolute Pfade) und überdimensionierte Inhalte geprüft; das Token wird nie an Weiterleitungsziele (Download-Server) gesendet.

## 14. Änderungsprotokoll

Nach einem Update erscheint beim ersten Aufruf des Admin-Bereichs ein Fenster «Neuigkeiten in Version X» (pro Benutzer einmal; Feld `users.changelog_seen`). Die Historie ist jederzeit unter `/admin/changelog` erreichbar (Link bei der Versionsnummer unten in der Navigation). Neu installierte Systeme und neu angelegte Benutzer sehen kein Fenster.

Quelle ist `CHANGELOG.md` im Hauptordner: pro Version ein Abschnitt `## <Version> – <Datum>` mit `### Neu`, `### Verbessert`, `### Behoben` oder `### Hinweis` und Aufzählungspunkten (`**fett**`, `` `Code` ``). **Bei jedem Release muss `APP_VERSION` (in `app/bootstrap.php` und `setup.php`) erhöht und ein Eintrag oben in `CHANGELOG.md` ergänzt werden** – ein Test prüft, dass die aktuelle Version im Protokoll steht.

## 15. Seitenleiste und Assistenten

**Seitenleiste.** Die Navigation ist in ausklappbare Gruppen gegliedert: *Verkauf* (Offerten, Aufträge, Rechnungen, Abos), *Kunden* (Anfragen, Kunden), *Katalog*, *Verwaltung* (Berichte, Benutzer, Webseite, Integrationen; nur Administratoren) und *System* (Einstellungen, Update, Lizenz, Systemstatus, E-Mail-Protokoll, Audit-Log; nur Administratoren). Die Gruppe der aktuellen Seite ist immer offen; sonst gilt die zuletzt gewählte Einstellung (pro Browser gemerkt, `localStorage`), standardmässig sind *Verkauf* und *Kunden* offen. Zugeklappte Gruppen zeigen offene Hinweise (neue Aufträge, überfällige Rechnungen …) als Zähler. Neben «Neue Offerte» öffnet der Pfeil ein Schnellmenü.

**Assistenten.** Formulare für Kunde, Offerte, Benutzer, Produktbereich, Positionsvorlage, Steuervorlage und Versandart haben oben einen Umschalter **Formular | Assistent**. Der Assistent führt Schritt für Schritt durch dieselben Felder (Fortschrittsanzeige, Prüfung der Pflichtfelder pro Schritt, bei Offerten zusätzlich «mindestens eine Position», Zusammenfassung zum Schluss). Das normale Formular bleibt der Standard und unverändert nutzbar; der Assistent sendet an dieselbe Adresse. Direktlinks: `…/admin/offers/new?wizard=1`, `…/admin/customers/new?wizard=1`. Bei Fehlern springt der Assistent zum betroffenen Schritt. Technisch: `assets/js/wizard.js` – Formulare werden mit `data-wizard` (eigene Schritte via `data-step`) oder `data-wizard="auto"` (eine Frage pro Schritt) markiert; Details im Kopf der Datei und in `style.md`.
