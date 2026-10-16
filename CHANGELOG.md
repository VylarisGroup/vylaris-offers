# Änderungsprotokoll

Format: pro Version ein Abschnitt `## <Version> – <Datum>` mit Unterabschnitten `### Neu`, `### Verbessert`, `### Behoben`, `### Hinweis`. Einträge sind Aufzählungspunkte; `**fett**` und `` `Code` `` sind erlaubt. Die neueste Version steht oben. Der Inhalt wird Mitarbeitenden beim ersten Anmelden nach einem Update angezeigt.

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
