# Dokumentation TYPO3 Extension wazum/sluggi

## Allgemeines

### Begriffe

**Slug** ist Teil einer URL - nach dem Domainnamen.

**Slug-Segment** oder **URL-Segment** ist Teil des Slugs, referenziert eine Seite im Seitenbaum.

Ein Slug kann aus mehreren Segmenten bestehen.

### Vorgeschichte und Core-Verhalten

Sprechende URLs und Slugs wurden mit TYPO3 Core Version 9 eingeführt.
Davor hat man eine Extension von Dritten (z.b. __dmitryd/typo3-realurl__)  benötigt, um sprechende URLs zu erzeugen.
Ein Slug wird beim Anlegen einer neuen Seite automatisch aus dem Titel berechnet.
Ändert man den Titel und möchte den Slug neu berechnen lassen, muss man das im Backend in den Seiteneigenschaften
händisch anstoßen.
Dann werden Slugs für diese Seite und neue Subseiten neu berechnet.
Außerdem kann man – auch als Redakteur – händisch den gesamten Slug einer Seite ändern.
Seit der Version 10 werden für die Seiten mit geänderten Slugs automatisch Redirects eingerichtet, sofern nichts
gegenteiliges konfiguriert ist.

## Sluggi

TYPO3 Extension __wazum/sluggi__ bringt einige Konfigurations-Möglichkeiten und erweiterte Behandlung der Slugs/URL-Segmente mit.

### Für Developer/Integratoren/Administratoren:

#### Installation

`composer require wazum/sluggi`

#### Site Configuration

Möchte man beispielsweise das automatische Erzeugen der Redirects ausschalten (oder andere Anpassungen bezüglich
Redirects machen), kann man das via Site Configuration im config.yaml machen:

Beispiel: Redirects ausschalten:

```yaml
   settings:
     redirects:
       # Automatically update slugs of all sub pages
       # (default: true)
       autoUpdateSlugs: true
       # Automatically create redirects for pages with a new slug (works only in LIVE workspace)
       # (default: true)
       autoCreateRedirects: false
```

Doku dazu: https://docs.typo3.org/c/typo3/cms-redirects/main/en-us/Setup/Index.html#site-configuration

### Für Backend-Administratoren:

#### Extension Konfiguration

Via **Backend / Einstellungen / Settings / Extension Configuration** kann man folgendes konfigurieren:

##### Backend user group ID list with extra permissions

`basic.whitelist (string)`

Liste der Backend User Gruppen, getrennt durch Beistrich, z.B.:
`2,13,678`

Wenn `basic.last_segment_only` (siehe weiter unten) gesetzt ist, dürfen die Redakteure nur das Slug-Segment bearbeiten, das
sich auf die aktuelle Seite bezieht.
Gehört ein Redakteur zur Backend User Gruppe, die in die Whitelist eingetragen ist, darf er den ganzen Slug bearbeiten,
sofern die automatische Synchronisation des Slugs für diese Seite nicht aktiv ist.

##### Replace / in slug segment with -

`basic.slash_replacement (boolean)`

_default: false_

Schrägstrich in den händisch eingetragenen Slugs (z.b. im Fall von Copy-Paste) wird automatisch durch den Bindestrich
ersetzt.

##### Use the following page fields for slug generation (valid JSON array!)

`basic.pages_fields (string)`

_default: [["nav_title","title"]]_

Liste der Felder, die zum automatischen Aufbau des Slugs herangezogen werden.
Die Reihenfolge entspricht der Priorität.
Das zuerst eingetragene Feld zieht zuerst, wenn es keinen Wert enthält, dann wird nach Inhalte im nächsten Feld
gesucht.
Die Liste ist beliebig erweiterbar. Siehe auch erweiterte
Konfigurationsmöglichkeiten: https://docs.typo3.org/m/typo3/reference-tca/main/en-us/ColumnsConfig/Type/Slug/Properties/GeneratorOptions.html

##### Enable synchronization of slug segment with configured page fields

`basic.synchronize (boolean)`

_default: true_

Setzt man die Eigenschaft auf __false__, werden die Slugs der Seite und Subseiten beim Umbenennen der Seite nicht
automatisch neu berechnet => das Verhalten entspricht dem Original Core-Verhalten.
Man kann weiterhin das neu Berechnen der Slugs händisch anstoßen.

##### Allow standard editors only editing of the last segment of the URL (single page)

`basic.last_segment_only (boolean)`

_default: false_

Default Core-Verhalten ist: alle Backend-User dürfen den gesamten Slug einer Seite komplett umschreiben.
Aktivieren dieser Einstellung der Extension verhindert es, dass Redakteure den gesamten Slug manipulieren können und
erlaubt es ihnen nur, das Slug-Segment, das sich auf die Seite, die gerade bearbeitet wird, zu ändern. 
Admins und User aus der Whitelist dürfen immer den gesamten Slug bearbeiten.

#### Felder im Backend und Berechtigungen für Backend-User:

Im Backend-Modul "Backend users", unter "Access Lists" kann man Freigabe folgender Felder konfigurieren:

**Allowed excludefields**`[non_exclude_fields]`

* **URL Segment (slug)** `[pages:slug]`
    * Core-Feld. **Muss** für alle freigegeben sein, sonst können Redakteure gar keine Slugs erzeugen.
* **Lock URL Segment (tx_sluggi_lock)** `[pages:tx_sluggi_lock]`
    * _Restrict editing of the URL Segment_
    * Sperrt das Bearbeiten des URL-Segments für diese Seite. Sperre gilt für alle, auch für Admins. Die Einstellung
      greift insofern auf die Subseiten, dass dieser Teil des Slugs auch auf Unterseiten gesperrt bleibt.
    * Soll der Administration vorbehalten sein. Kann geschulten Redakteuren zur verfügung gestellt werden.
* **Synchronize URL Segment (tx_sluggi_sync)** `[pages:tx_sluggi_sync]`
    * _Synchronize URL Segment with the configured fields_
    * Synchronisiert - beim Speichern einer Seite - den Slug automatisch mit den in Extension Configuration
      konfigurierten Feldern. Dadurch darf niemand den Slug dieser Seite direkt manipulieren.

#### Gängige Fallbeispiele

1. Alle Slugs sollen immer automatisch synchronisiert werden:
   => Default Einstellungen

2. Slugs sollen nicht automatisch synchronisiert werden, aber Synchronisation soll getriggert werden können:
   Abweichend vom Default:
   Extension Configuration: `basic.synchronize = 0`

3. Alle Slugs sollen immer automatisch synchronisiert werden, aber bei bestimmten Seiten soll der Slug beim Umbenennen der
   Seite gleich bleiben:

   3.1. Entweder bei der betroffenen Seite das URL Segment sperren:

       Synchronize URL Segment[tx_sluggi_sync]

   3.2 Oder für die Slugs `nav_title` oder ein eigenes Feld `custom_slug_field` verwenden (ggf. das Feld nicht den
   Redakteuren zur Bearbeitung freigeben) und bei betroffener Seite als Admin das Feld ausfüllen:

        basic.pages_fields = [["nav_title","title"]]

## Weiterführende Links:

URL-Management im TYPO3 10: https://b13.com/de/blog/auto-slug-update-typo3-v10

Beschreibung vom Core-Verhalten vs. _wazum/sluggi_: https://typo3.uni-koeln.de/hilfe-fuer-redakteure/diverses/url-segmente-slugs

Einführung in _wazum/sluggi_ durch Wolfgang Wagner https://www.youtube.com/watch?v=8IchK1y0jnU

Sluggi Extension im GitHub: https://github.com/wazum/sluggi

Sluggi Extension im TYPO3 TER: https://extensions.typo3.org/extension/sluggi

