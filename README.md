# Dynamic QR Code

TYPO3-Extension für dynamische QR-Codes als SVG mit stabilem Resolver-Link, internem Scan-Tracking, Backend-Analytics, CSV-Export und Live-Preview für das Styling.

Die Extension ist dafür gebaut, dass ein QR-Code im Druck unverändert bleiben kann, auch wenn sich das Ziel später ändert. Der QR-Code verweist deshalb nicht direkt auf die finale Ziel-URL, sondern auf eine kurze Resolver-URL wie `/q/{uid}/{hash}`. Diese URL kann dauerhaft gleich bleiben, während `target_url` im Backend geändert wird.

## Inhaltsverzeichnis

- [Ziel der Extension](#ziel-der-extension)
- [Feature-Überblick](#feature-überblick)
- [Technischer Gesamtaufbau](#technischer-gesamtaufbau)
- [Request-Flows](#request-flows)
- [Verzeichnisstruktur](#verzeichnisstruktur)
- [Datenmodell](#datenmodell)
- [Backend-Aufbau](#backend-aufbau)
- [Live-Preview und Styling](#live-preview-und-styling)
- [Analytics und CSV-Export](#analytics-und-csv-export)
- [Resolver und Redirect-Konzept](#resolver-und-redirect-konzept)
- [Installation](#installation)
- [Konfiguration und Betrieb](#konfiguration-und-betrieb)
- [Entwicklerhinweise](#entwicklerhinweise)
- [Bekannte Grenzen](#bekannte-grenzen)
- [Weiterentwicklungsideen](#weiterentwicklungsideen)

## Ziel der Extension

Die Extension löst drei Probleme gleichzeitig:

1. QR-Codes sollen sauber als SVG erzeugt und gestaltet werden können.
2. Gedruckte QR-Codes sollen stabil bleiben, auch wenn sich die Zielseite später ändert.
3. Scans sollen intern messbar sein, ohne ausschließlich auf clientseitiges Analytics-JavaScript angewiesen zu sein.

Das Kernprinzip lautet daher:

- Der QR-Code enthält normalerweise eine Resolver-URL.
- Der Resolver leitet auf `target_url` weiter.
- Vor der Weiterleitung wird serverseitig getrackt.

## Feature-Überblick

- QR-Code-Erzeugung als SVG auf Basis von `endroid/qr-code`
- dynamische Resolver-URLs `/q/{uid}/{hash}`
- serverseitiges Scan-Tracking am Resolver
- aggregierte Analytics direkt im QR-Datensatz
- CSV-Export der Scan-Rohdaten
- Live-Preview im Backend ohne Speichern
- Style-Presets und manuelle Gestaltung
- Dot-Styles, Eye-Styles, Verlauf, Logo, Shadow, Farben, Größe, Margin
- TYPO3-FormEngine-Integration mit eigenem Preview- und Analytics-Renderer
- TYPO3-Redirect-Synchronisierung beim Speichern des Datensatzes

## Technischer Gesamtaufbau

Die Extension besteht im Wesentlichen aus sechs Bausteinen:

1. Datenmodell
- QR-Datensätze in `tx_dynamicqrcode_domain_model_qrcode`
- Scan-Rohdaten in `tx_dynamicqrcode_domain_model_scan`

2. QR-Rendering
- `QrCodeService` erzeugt das SVG aus TCA-/Form-Werten
- zusätzliche SVG-Nachbearbeitung für Gradient, Eye-Styling, Dot-Styling, Logo-Hintergrund und Shadow

3. Resolver
- `QrResolverMiddleware` fängt `/q/...` ab
- validiert HMAC
- lädt QR-Datensatz
- trackt den Hit
- leitet per `302` auf `target_url` weiter

4. Backend-UX
- `QrPreviewElement` rendert die Vorschau
- `LivePreviewController` liefert aktualisierte SVGs per Backend-Route
- `LivePreview.js` verbindet Form-Felder und Preview live

5. Analytics
- `ScanTrackingService` schreibt Rohdaten und Aggregate
- `ScanAnalyticsService` berechnet Kennzahlen, Tagesverläufe, Referer und Unique-Scans
- `QrAnalyticsElement` zeigt diese Daten direkt im Datensatz

6. Redirect-Synchronisierung
- `DataHandlerHook` reagiert auf Änderungen am QR-Datensatz
- `RedirectService` legt TYPO3-Redirects in `sys_redirect` an oder aktualisiert sie

## Request-Flows

### 1. Editor öffnet einen QR-Datensatz im Backend

1. TYPO3 rendert den TCA-Datensatz.
2. `preview_field` wird über `QrPreviewElement` gerendert.
3. `analytics_field` wird über `QrAnalyticsElement` gerendert.
4. `QrPreviewElement` registriert das JavaScript-Modul `LivePreview.js`.
5. Änderungen an Styling-Feldern senden Form-Werte an die Backend-Route `/dynamic-qrcode/live-preview`.
6. `LivePreviewController` ruft `QrCodeService::svgFromConfig()` auf und liefert neues SVG zurück.

### 2. Scanner ruft einen QR-Code auf

1. Scan öffnet `/q/{uid}/{hash}`.
2. `QrResolverMiddleware` fängt den Request vor normalem TYPO3-Rendering ab.
3. Die Middleware validiert UID und HMAC.
4. Der QR-Datensatz wird geladen.
5. `ScanTrackingService` speichert Rohdaten und aktualisiert Aggregate.
6. Die Middleware antwortet mit `302` auf `target_url`.

### 3. Editor exportiert Analytics

1. Im Analytics-Tab wird auf `CSV exportieren` geklickt.
2. TYPO3 ruft `/dynamic-qrcode/export-scans?qrUid=...&range=...` auf.
3. `ScanExportController` zieht die Daten aus `ScanAnalyticsService`.
4. CSV wird als Download ausgeliefert.

## Verzeichnisstruktur

```text
packages/dynamic_qrcode
├── Classes
│   ├── Controller
│   │   ├── LivePreviewController.php
│   │   ├── QrCodeController.php
│   │   └── ScanExportController.php
│   ├── Domain
│   │   ├── Model/QrCode.php
│   │   └── Repository/QrCodeRepository.php
│   ├── Form/Element
│   │   ├── QrAnalyticsElement.php
│   │   └── QrPreviewElement.php
│   ├── Hooks
│   │   └── DataHandlerHook.php
│   ├── Middleware
│   │   ├── QrResolverMiddleware.php
│   │   └── SvgMiddleware.php
│   └── Service
│       ├── QrCodeService.php
│       ├── RedirectService.php
│       ├── ScanAnalyticsService.php
│       ├── ScanTrackingService.php
│       └── SvgStorageService.php
├── Configuration
│   ├── Backend/Routes.php
│   ├── JavaScriptModules.php
│   ├── RequestMiddlewares.php
│   ├── Services.yaml
│   ├── TCA/tx_dynamicqrcode_domain_model_qrcode.php
│   └── TypoScript
├── Resources
│   ├── Private
│   └── Public/JavaScript/LivePreview.js
├── ext_localconf.php
├── ext_tables.php
├── ext_tables.sql
└── composer.json
```

## Datenmodell

### Tabelle `tx_dynamicqrcode_domain_model_qrcode`

Zentrale Fach-Tabelle der Extension.

Wichtige Felder:

- `title`: interner Name des QR-Codes
- `target_url`: finale Ziel-URL
- `style_preset`: Preset-Auswahl wie `custom`, `dotted_modern`, `soft_medical`
- `scan_count`: Gesamtanzahl aller Resolver-Treffer
- `first_scan_at`: Zeitpunkt des ersten Scans
- `last_scan_at`: Zeitpunkt des letzten Scans
- `fg_color`, `bg_color`: Vorder- und Hintergrundfarbe
- `fg_gradient_from`, `fg_gradient_to`, `fg_gradient_angle`: Verlauf
- `error_correction`: `L`, `M`, `Q`, `H`
- `size`, `margin`: Grundgeometrie
- `logo_file`, `logo_scale`: optionales Logo
- `logo_bg`, `logo_bg_color`, `logo_bg_radius`, `logo_bg_padding`: Logo-Kachel
- `rounded_modules`: Rundungsmodus für Module
- `dot_style`, `dot_intensity`: Modulform
- `eye_style`, `eye_radius`: Finder-Pattern-Form
- `drop_shadow`: Shadow-Effekt

### Tabelle `tx_dynamicqrcode_domain_model_scan`

Diese Tabelle speichert einzelne Scan-Ereignisse.

Wichtige Felder:

- `qr_code`: Referenz auf QR-Datensatz
- `crdate`: Scan-Zeitpunkt
- `target_url`: Ziel-URL zum Zeitpunkt des Scans
- `resolved_path`: aufgerufener Resolver-Pfad
- `site_host`: Host des Requests
- `referer`: Referer, falls vorhanden
- `user_agent`: User-Agent, falls vorhanden
- `ip_hash`: gehashte IP, nicht die Roh-IP
- `is_bot`: Bot-Heuristik

Das Datenmodell trennt bewusst:

- Aggregate im QR-Datensatz für schnelle Anzeige
- Rohdaten in der Scan-Tabelle für spätere Auswertung

## Backend-Aufbau

Der Datensatz hat drei Tabs:

### 1. `General`

- `title`
- `target_url`
- `style_preset`

### 2. `Design & Preview`

- Preview oben im Tab
- darunter die Style-Felder
- im laufenden Backend wird diese Struktur per `LivePreview.js` in ein Zweispalten-Layout umgebaut:
  - Styling links
  - Preview rechts sticky

Style-Felder in diesem Tab:

- Farben
- Error Correction
- Größe und Margin
- Logo und Logo-Hintergrund
- Gradient
- Dot- und Eye-Styling
- `rounded_modules`
- Shadow

### 3. `Analytics`

- Analytics-Block mit KPIs und Tabellen
- readonly Felder `scan_count`, `first_scan_at`, `last_scan_at`

## Live-Preview und Styling

### Kernkomponenten

- TCA-Tab und Felder: `Configuration/TCA/tx_dynamicqrcode_domain_model_qrcode.php`
- Preview-Renderer: `Classes/Form/Element/QrPreviewElement.php`
- Backend-Route: `Configuration/Backend/Routes.php`
- Backend-Controller: `Classes/Controller/LivePreviewController.php`
- Browser-Logik: `Resources/Public/JavaScript/LivePreview.js`
- Rendering-Logik: `Classes/Service/QrCodeService.php`

### Funktionsweise

1. `QrPreviewElement` rendert ein initiales SVG.
2. Das Element registriert `LivePreview.js` als JavaScript-Modul.
3. Das Skript beobachtet relevante Form-Felder.
4. Änderungen werden per POST an `/dynamic-qrcode/live-preview` gesendet.
5. `LivePreviewController` baut daraus ein Konfigurationsarray.
6. `QrCodeService::svgFromConfig()` liefert das SVG zurück.
7. Das Preview-Bild wird sofort aktualisiert.

### Presets

Aktuell definierte Presets:

- `custom`
- `dotted_modern`
- `soft_medical`
- `dark_tech`
- `sunrise_gradient`

Wichtig:

- Ein Preset setzt mehrere Style-Werte gleichzeitig.
- Sobald ein preset-gesteuertes Feld manuell geändert wird, setzt das Backend-JS das Preset auf `custom`.
- So überschreibt das Preset die manuelle Feinanpassung nicht wieder.

### Unterstützte Gestaltungsoptionen

- Vordergrund- und Hintergrundfarbe
- Vordergrundsverlauf
- Dot-Styles
  - `square`
  - `rounded`
  - `dots`
  - `circles`
  - `bubble`
  - `diamond`
  - `softsquare`
- Eye-Styles
  - `square`
  - `rounded`
  - `circular`
  - `softsquare`
  - `diamond`
- abgerundete Module
- Logo
- Logo-Background
- Shadow

### Hinweise zur Rendering-Architektur

Die Extension nutzt `endroid/qr-code` als Basis, ergänzt das Ergebnis aber durch SVG-Nachbearbeitung. Das ist nötig, weil nicht alle gewünschten Styles in allen Library-Versionen nativ gleich unterstützt werden.

Wichtige Nachbearbeitungen in `QrCodeService`:

- Gradient-Einbau über `<defs><linearGradient>`
- Dot-Styling auf Block-Definitionen
- Eye-Styling über Rekonstruktion der Finder-Patterns
- Logo-Hintergrund als zusätzliche Kachel
- Shadow per Filter

## Analytics und CSV-Export

### Tracking

Tracking passiert serverseitig in `QrResolverMiddleware` über `ScanTrackingService`.

Vorteil:

- funktioniert auch ohne JavaScript
- funktioniert auch bei QR-Scans aus Kamera-Apps, Messenger-Previews oder externen Browsern
- zählt den tatsächlichen Resolver-Einstieg

### Analytics-Block im Backend

Gerendert von `QrAnalyticsElement`.

Angezeigt werden:

- Total Scans
- Unique Scans
- Human Scans
- Bot Scans
- First Scan
- Last Scan
- Tagesverlauf im gewählten Zeitraum
- Top-Referer
- letzte Scans

### Zeitfilter

Unterstützte Bereiche:

- `7d`
- `30d`
- `90d`
- `all`

Der Filter wirkt auf:

- Kennzahlen
- Tagesverlauf
- Referer
- Recent Scans
- CSV-Export

### Unique-Scans

Die Extension berechnet Unique-Scans heuristisch als:

`ip_hash + sha1(user_agent) + Kalendertag`

Das ist bewusst nicht als absolut exakte Personenzahl zu verstehen, sondern als pragmatische 24h-Heuristik.

### CSV-Export

Backend-Route:

`/dynamic-qrcode/export-scans`

Parameter:

- `qrUid`
- `range`

Exportierte Daten:

- QR UID
- QR-Titel
- Scan-Zeitpunkt
- Target URL
- Resolver-Pfad
- Host
- Referer
- User-Agent
- IP-Hash
- Bot-Flag

## Resolver und Redirect-Konzept

### Resolver-URL

Die Resolver-URL sieht so aus:

```text
/q/{uid}/{hash}
```

Beispiel:

```text
/q/123/1a2b3c4d
```

### Warum dieser Resolver wichtig ist

- gedruckte QR-Codes bleiben stabil
- Ziel-URL kann später geändert werden
- Tracking findet an einem festen Einstiegspunkt statt

### Middleware

Die Resolver-Logik liegt in:

- `Configuration/RequestMiddlewares.php`
- `Classes/Middleware/QrResolverMiddleware.php`

Die Middleware:

1. erkennt Pfade `/q/...`
2. validiert optionalen HMAC
3. lädt den QR-Datensatz
4. trackt den Hit
5. sendet einen `302`-Redirect

### TYPO3 Redirects

Zusätzlich pflegt die Extension `sys_redirect` über:

- `Classes/Hooks/DataHandlerHook.php`
- `Classes/Service/RedirectService.php`

Das ist nützlich, weil TYPO3-Redirects weiterhin als technische Infrastruktur zur Verfügung stehen. Das eigentliche fachliche Tracking läuft aber nicht nur über `sys_redirect.hitcount`, sondern über die eigene Scan-Tabelle.

## Installation

### Voraussetzungen

- PHP `>= 8.1`
- TYPO3 `^13.0`
- `endroid/qr-code ^5.0`

### Composer

```bash
composer require vendor/dynamic-qrcode
```

### TYPO3

Nach der Installation:

1. Extension aktivieren
2. Datenbankschema übernehmen
3. TYPO3-Caches leeren

Wichtig bei Updates der Extension:

- nach neuen DB-Feldern oder neuen Tabellen immer Schema vergleichen
- nach Änderungen an Backend-JS oder TCA den Backend-Cache leeren und das Backend hart neu laden

## Konfiguration und Betrieb

### Wichtige Registrierungen

- Plugin-Registrierung: `ext_tables.php`, `ext_localconf.php`
- FormEngine Nodes: `ext_localconf.php`
- Backend-Routen: `Configuration/Backend/Routes.php`
- Middleware: `Configuration/RequestMiddlewares.php`
- JavaScript-Module: `Configuration/JavaScriptModules.php`
- Services: `Configuration/Services.yaml`

### Site-Kontext

Die Resolver-URL wird in `QrCodeService` mit Site-Kontext bzw. Fallback-Base-URL aufgebaut. Wenn keine Site eindeutig gefunden wird, existiert ein Fallback-Verhalten.

### Caching

Wenn sich Live-Preview oder TCA-Struktur scheinbar nicht ändern:

1. TYPO3-Caches leeren
2. Backend komplett neu laden
3. bei JS-Problemen ggf. Browser-Cache hart leeren

## Entwicklerhinweise

### Zentrale Klassen

#### `QrCodeService`

Verantwortlich für:

- Sanitizing von TCA-/Form-Werten
- Anwenden von Presets
- Aufbau des QR-Code-Objekts
- SVG-Erzeugung
- SVG-Nachbearbeitung

Wichtigste Methode:

```php
svgFromConfig(array $config): string
```

#### `ScanTrackingService`

Verantwortlich für:

- Speichern eines Scan-Events
- Pflegen von `scan_count`, `first_scan_at`, `last_scan_at`
- Ableitung von Bot-/Request-Metadaten

#### `ScanAnalyticsService`

Verantwortlich für:

- Zeitraumfilter
- Tagesaggregation
- Referer-Auswertung
- Unique-Scan-Heuristik
- Exportdaten für CSV

#### `QrPreviewElement`

Verantwortlich für:

- initiale Backend-Vorschau
- Link zum Resolver
- Download-Link
- Registrierung des Live-Preview-JavaScripts

#### `QrAnalyticsElement`

Verantwortlich für:

- HTML-Ausgabe des Analytics-Tabs
- Filter-Buttons
- CSV-Export-Link

### Neue Styling-Felder ergänzen

Wenn ein neues Style-Feld ergänzt werden soll, müssen normalerweise diese Stellen beachtet werden:

1. `ext_tables.sql`
2. `Configuration/TCA/tx_dynamicqrcode_domain_model_qrcode.php`
3. `Classes/Domain/Model/QrCode.php`, falls das Feld im Modell benötigt wird
4. `Classes/Controller/LivePreviewController.php`
5. `Resources/Public/JavaScript/LivePreview.js`
6. `Classes/Service/QrCodeService.php`

### Neue Analytics-Metriken ergänzen

Typischer Ablauf:

1. Rohdatenstruktur in `tx_dynamicqrcode_domain_model_scan` prüfen
2. Berechnung in `ScanAnalyticsService` ergänzen
3. Darstellung in `QrAnalyticsElement` ergänzen
4. bei Bedarf Export in `fetchExportRows()` erweitern

## Bekannte Grenzen

- Die visuelle Freiheit ist höher als bei Standard-QR-Codes, aber Scanbarkeit bleibt wichtiger als maximale Gestaltung.
- Nicht jede Funktion von `endroid/qr-code` ist in jeder installierten Library-Version identisch verfügbar.
- Einige Styles werden deshalb nicht nativ, sondern als SVG-Fallback gelöst.
- Unique-Scans sind nur eine Heuristik.
- Matomo oder andere clientseitige Tracker sehen Resolver-Hits nicht automatisch, weil der Resolver keine normale HTML-Seite mit Tracking-Script rendert.

## Weiterentwicklungsideen

- Preset-Auswahl als visuelle Buttons
- kleine Balkencharts statt reiner Tabellen im Analytics-Tab
- Ziel-Historie pro QR-Code
- Cleanup-Command für alte Scan-Rohdaten
- optionale serverseitige Übergabe an Matomo
- CSV-Filter für Human-only oder Custom-Zeiträume
- zusätzliche Scanability-Warnungen im Styling-Tab

## Lizenz

MIT

## Autor

Yannick Aister / Medartis AG
