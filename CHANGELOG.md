# Änderungsprotokoll

Format: pro Version ein Abschnitt `## <Version> – <Datum>` mit Unterabschnitten `### Neu`, `### Verbessert`, `### Behoben`, `### Hinweis`. Einträge sind Aufzählungspunkte; `**fett**` und `` `Code` `` sind erlaubt. Die neueste Version steht oben. Der Inhalt wird Mitarbeitenden beim ersten Anmelden nach einem Update angezeigt.

## 1.7.2 – 2026-10-24

### Verbessert
- **Hinweis «Powered by VylarisOffers»** steht jetzt unten links in der Seitenleiste der Administration, gut lesbar und dezent, mit Link auf vylaris.ch. Er ist fest eingebaut und lässt sich weder über Einstellungen noch über das Layout ausblenden oder ändern.

## 1.7.1 – 2026-10-22

### Neu
- **Kundenportal verwalten:** Neue Seite *Kunden → Kundenportal* mit Kennzahlen, **Systemprüfung** (Lizenz-Funktion «Kundenportal», E-Mail-Versand, Basis-Adresse, Kunden mit Zugang), **Anmeldung eines Kunden prüfen** (zeigt, warum kein Anmeldelink ankommt: Zugang aus, Sperre, Anforderungs-Limit, fehlgeschlagener Versand) sowie Liste aller Kunden mit Zugang an/aus und Anmeldelink senden.

### Behoben
- Nachrichten im Admin-Bereich richten sich nach der Lizenz-Funktion «Kundenportal» (Navigation und Zugriff).

## 1.7.0 – 2026-10-17

### Neu
- **Dokumentvorlagen:** Das PDF-Layout von Offerten und Rechnungen ist jetzt einstellbar – Akzentfarbe, Schrift und Schriftgrösse, Seitenrand, Logo (Position und Grösse), Firmenangaben, Tabellenstil, Gesamtbetrag-Feld, Fusszeile mit Platzhaltern und Seitenzahlen. Unter «Katalog → Dokumentvorlagen» mit **Live-Vorschau** (Beispieldaten). Drei Vorlagen sind mitgeliefert (Standard, Elegant, Kompakt); je Offerten und Rechnungen lässt sich eine Standardvorlage festlegen. Gewählt wird die Vorlage in der Offerte (Abschnitt «Texte») bzw. im Rechnungsentwurf. Vorlagen können auch eigene Standardtexte für Einleitung und Schluss mitbringen.
- **PWA:** Administration und Kundenportal lassen sich als App installieren (Smartphone, Tablet, Desktop) – mit eigenem Symbol aus Logo und Markenfarbe, Vollbild und Offline-Hinweisseite. Es werden nur Design-Dateien zwischengespeichert, nie Dokumente oder Kundendaten. Ein- und ausschaltbar sowie Kurzname unter «Einstellungen → Branding».
- **Kundenportal erweitert:** «Nachrichten» (Kunden schreiben dem Team, optional mit Bezug auf Offerte, Auftrag oder Rechnung; Antworten des Teams mit E-Mail-Hinweis), «Dokumente» (alle Offerten-/Rechnungs-PDFs und freigegebenen Dateien an einem Ort, Datei-Upload zu Aufträgen), «Meine Angaben» (Kunden pflegen Adresse und Telefon selbst). Im Admin-Bereich gibt es dazu den Posteingang «Kunden → Nachrichten» mit Zähler.

### Verbessert
- Vom Kunden hochgeladene Dateien sind bei den Anhängen des Auftrags gekennzeichnet; das Team wird per E-Mail benachrichtigt.
- Datenschutz: Nachrichten sind im Datenexport enthalten und werden beim Einschränken, Anonymisieren und Löschen eines Kunden entfernt.

### Hinweis
- Schema-Version 13 (Tabellen `doc_templates`, `portal_messages`; Spalten `template_id` und `by_customer`) – wird beim ersten Aufruf automatisch angelegt.
- Der Zahlteil der QR-Rechnung bleibt wegen der Schweizer Vorgaben unabhängig von der Vorlage unverändert.

## 1.6.1 – 2026-10-16

### Behoben
- Formulare mit Assistenten (Kunde, Offerte): Im normalen Formular-Modus war das Layout verschoben, weil der Umschalter «Formular | Assistent» eine Spalte belegte.
- Assistent in Dialogen: Die Wahl «Assistent» wurde fälschlich für alle Dialoge gemeinsam gemerkt (Benutzer, Bereiche, Vorlagen …); sie gilt jetzt pro Dialog.
- Schrittanzeige in Dialogen und bei vielen Schritten: nur der aktuelle Schritt zeigt seine Bezeichnung, die übrigen sind abgekürzte Punkte (vorher abgeschnittene Buchstaben).

## 1.6.0 – 2026-10-16

### Neu
- **Assistent (Schritt für Schritt):** Kunde, Offerte, Benutzer, Produktbereich, Positionsvorlage, Steuervorlage und Versandart lassen sich jetzt auch Schritt für Schritt erfassen – mit Fortschrittsanzeige, Prüfung pro Schritt und Zusammenfassung vor dem Speichern. Oben im Formular zwischen **Formular** und **Assistent** umschalten; das bisherige Formular bleibt unverändert verfügbar.
- **Schnellzugriff:** Neben «Neue Offerte» öffnet der Pfeil ein Menü mit den Assistenten für Offerte und Kunde.

### Verbessert
- **Seitenleiste mit ausklappbaren Gruppen** (Verkauf, Kunden, Katalog, Verwaltung, System). Die Gruppe der aktuellen Seite ist immer offen, die Auswahl wird pro Browser gemerkt, zugeklappte Gruppen zeigen offene Hinweise als Zähler.

## 1.5.1 – 2026-10-16

### Neu
- **Änderungsprotokoll:** Nach einem Update zeigt das System beim ersten Anmelden im Admin-Bereich, was neu ist. Die vollständige Historie steht unter *Änderungsprotokoll* (Link bei der Versionsnummer in der Navigation).

## 1.5.0 – 2026-10-16

### Neu
- **Single Sign-On** per OpenID Connect: Anmeldung mit dem Vylaris-Login, Einrichtung unter *Einstellungen → Single Sign-On* (Lizenzfunktion `sso`).
- **Updates aus GitHub:** Neue Versionen lassen sich unter *Update* per Klick oder automatisch installieren – mit Sicherung und Rückgängig-Funktion.
- **Lizenzsystem** mit Funktionsumfang pro Tarif, Lesemodus, Offline-Lizenz und Karenzfrist.
- **Styleguide** (`style.md`) für Entwickler.

### Verbessert
- Lizenzserver, Update-Quelle und Public-Key sind fest vorgegeben und nicht mehr änderbar.
- Schweizer MWST-System und Werkzeuge für Datenschutz (DSG/DSGVO) erweitert.

### Behoben
- Abweichende Lieferadresse bei der Offerten-Erstellung, Löschen von Offerten, Ablaufdatum und vom Kunden gewählte Lieferadresse bei der Annahme.

## 1.0.0 – 2026-09-01

### Neu
- Erste Version: Offerten, Aufträge, Rechnungen mit QR-Rechnung, Abos, Kundenportal, Website-Baukasten und API.
