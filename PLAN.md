# Plan: Feedly OPDS-Server

## Ziel

Ein OPDS-Server, der Artikel aus dem Feedly-"Read Later"-Feed (Saved-Tag) als OPDS-2-Katalog
bereitstellt, sodass die gespeicherten Artikel auch unterwegs in OPDS-fähigen Readern
(z. B. KOReader, Marvin) gelesen werden können. Für den Volltextabruf der Original-URLs wird
die Instaparser-API genutzt.

Merkmale:

- Kein Multi-User, keine Benutzerverwaltung.
- Kein Frontend bis auf eine kurze Projekt-Erklärung auf der Startseite `/`.
- Jegliche Konfiguration erfolgt ausschließlich über `.env`.
- Zugriff auf den OPDS-Feed ist durch ein statisches Token geschützt.

---

## Architekturüberblick

Schlanke Laravel-App als OPDS-Katalog-Proxy vor Feedly + Instaparser:

```
Reader-App  ──HTTP (+ Token)──>  Laravel OPDS-Server
                                  │
                                  ├── FeedlyClient  ──> Feedly API (v3)
                                  │       (Developer Token + Refresh-Flow)
                                  │
                                  └── InstaparserClient ──> Instaparser API
                                          (Volltext/HTML der Original-URL)
```

- OPDS-2-Acquisition-Feed (Atom + OPDS-Namespaces).
- Volltext wird nur im **Detail-Entry** (`/opds/entry/{entryId}`) als
  `<content type="xhtml">` eingebettet; der Listen-Feed (`/opds/saved`) liefert
  ausschließlich `<summary>` und Links (schneller, Rate-Limit-schonend).
- Token-Auth via Middleware: `Authorization: Basic <token>:` bzw. `?token=...`.
- Granulares Caching (Cache-Keys pro Feed-Seite / pro Instaparser-URL), TTLs via `.env`.

---

## `.env`-Einstellungen (neu)

```ini
# ── Feedly ──────────────────────────────────────────────────────────────
FEEDLY_DEVELOPER_TOKEN=          # Access Token (Bootstrap; i. d. R. abgelaufen nach erstem Refresh)
FEEDLY_REFRESH_TOKEN=            # Refresh-Token für /v3/auth/token
FEEDLY_CLIENT_ID=                # vom Developer-Portal (für Refresh-Flow, falls erforderlich)
FEEDLY_CLIENT_SECRET=            # dito
FEEDLY_USER_ID=                  # UUID OHNE user/-Präfix (Code ergänzt das Präfix)
FEEDLY_SAVED_TAG=saved           # nur Tag-Name; ergibt streamId user/<uuid>/tag/global.<tag>

# ── Instaparser ────────────────────────────────────────────────────────
INSTAPARSER_API_KEY=             # API-Key der Instaparser-Cloud-API
INSTAPARSER_BASE_URL=https://www.instaparser.com/api

# ── OPDS / Zugriffsschutz ──────────────────────────────────────────────
OPDS_AUTH_TOKEN=                 # statisches Token für OPDS-Zugriff
OPDS_TITLE="Feedly Read Later"
OPDS_AUTHOR="Thomas Off"
OPDS_AUTHOR_URI=

# ── Caching (Sekunden) ─────────────────────────────────────────────────
CACHE_FEED_TTL=300               # Feedly Stream-Contents
CACHE_ARTICLE_TTL=86400          # Instaparser HTML
```

### Token-Refresh-Strategie

- Neue Access-Tokens (nach Refresh) werden im **Laravel-Cache** persistiert
  (`Cache::put('feedly.access_token', …)`, `Cache::put('feedly.token_expires_at', …)`).
- `.env`-Token dient nur als Bootstrap. Bei Cache-Miss wird der `.env`-Token probiert;
  führt er zu 401 oder ist nah am Ablauf, wird der Refresh-Flow angestoßen.
- Wird der Cache geleert, fällt der Server auf den `.env`-Token zurück und refreshed
  ggf. neu (idempotenter Vorgang).
- Falls Feedly den Refresh ohne Client-ID/Secret zulässt, werden diese optional behandelt
  (nur gesetzt, wenn in `.env` vorhanden) — sonst werden sie mitgesendet.

---

## Verzeichnis-/Klassenstruktur

```
config/
  feedly.php                     # liest env, stellt Defaults & base URL https://cloud.feedly.com/v3/
  instaparser.php                # analog
  opds.php                       # Title/Author/TTLs/Auth-Token

app/Services/
  Feedly/
    FeedlyClient.php             # HTTP-Client (Laravel Http), Refresh-Flow, Streams/Contents
    Dtos/
      Entry.php                  # Entry DTO (id, title, url, summary, originUrl, published, ...)
  Instapaper/
    InstaparserClient.php        # Auth via API-Key, ruft Instaparser-Endpoint auf
    ArticleContent.php           # geparstes/säuberes HTML
  Opds/
    CatalogAssembler.php         # baut Root-Navigation + Acquisition-Feed
    FeedBuilder.php              # Acquisition-Feed (Atom + OPDS-NS)
    EntryBuilder.php             # einzelner OPDS-Eintrag; Inline-Content nur im Detail-Modus

app/Http/
  Middleware/
    EnsureOpdsToken.php          # Token-Check (Basic Auth oder ?token=)
  Controllers/
    OpdsController.php           # Endpunkte (catalog, saved, entry, download)

routes/
  web.php                         # / bleibt Welcome-View
  opds.php                         # OPDS-Routen ( prefixed mit /opds, Middleware opds.token)

resources/views/
  welcome.blade.php               # kurze Projekt-Erklärung (vorhandene anpassen)
  opds/
    navigation.blade.php          # Root-Navigations-Feed (Atom XML)
    feed.blade.php                # Acquisition-Feed (Atom XML)
    entry.blade.php               # Detail-Entry (Atom XML, mit content type="xhtml")
```

---

## Endpunkte

```php
Route::prefix('opds')->middleware('opds.token')->group(function () {
    Route::get('/',                    [OpdsController::class, 'catalog'])->name('opds.catalog');
    Route::get('/saved',               [OpdsController::class, 'saved'])->name('opds.saved');
    Route::get('/entry/{entryId}',     [OpdsController::class, 'entry'])->name('opds.entry');
    Route::get('/download/{entryId}',  [OpdsController::class, 'download'])->name('opds.download');
});

Route::get('/', fn () => view('welcome'))->name('home');
```

| Route                        | Zweck                                                | Cache-Key (Beispiel)                  |
|------------------------------|------------------------------------------------------|---------------------------------------|
| `GET /opds`                  | Root-Navigation ("Read Later" verweisen)             | `opds.catalog`                        |
| `GET /opds/saved`            | Acquisition-Feed aller gespeicherten Artikel         | `feedly.saved.page.{n}`               |
| `GET /opds/entry/{entryId}` | Detail-Entry mit `<content type="xhtml">` (Instaparser) | `instaparser.{sha256(url)}` + `feedly.entry.{id}` |
| `GET /opds/download/{entryId}` | Proxy: liefert Instaparser-HTML als Download         | `instaparser.{sha256(url)}`          |

### Inhaltsstrategie (per Endpunkt)

- `/opds/saved`: nur `<summary>`, `rel="http://opds-spec.org/acquisition"` (Download-Link)
  und `rel="alternate"` (Original-URL). **Kein Inline-Content** → Listen-Aufruf schnell.
- `/opds/entry/{entryId}`: `<content type="xhtml">` mit Instaparser-HTML,
  synchron geladen + cached (TTL `CACHE_ARTICLE_TTL`). Bei Instaparser-Fehler
  Fallback auf Feedly-Summary + Alternate-Link.
- `/opds/download/{entryId}`: gleicher Instapaper-Inhalt als `text/html`-Download,
  Clients ohne Inline-Content-Rendering können ihn abrufen.

---

## Request-Fluss

1. Reader ruft `/opds` → Navigations-Feed mit einem Link "Read Later".
2. Reader ruft `/opds/saved` → `OpdsController::saved`:
   - `FeedlyClient->streamContents(streamId, page)` (cached `CACHE_FEED_TTL`).
   - Mapping auf `Entry`-DTOs via `EntryBuilder` (Liste, ohne Instapaper).
3. Reader öffnet Detail-Entry `/opds/entry/{entryId}` oder Download `/opds/download/{entryId}`:
   - Lookup der Entry-Metadaten (Cache oder Feedly).
   - `InstaparserClient->article($originUrl)` (cached `CACHE_ARTICLE_TTL`).
   - Render als Atom `<entry>` mit eingebettetem XHTML bzw. als HTML-Download.

### Token-Refresh Timing im FeedlyClient

```
request →
  read cached access_token + expires_at
  if missing || expires_at < now + 60s:
      refresh via /v3/auth/token
      Cache::put('feedly.access_token', …)
  do request
  if 401:
      force refresh (einmalig)
      retry request
```

---

## Feedly-Stream-IDs (Beispiel)

- `FEEDLY_USER_ID` = `a1b2c3d4-…`
- `FEEDLY_SAVED_TAG` = `saved`
- Ergibt `streamId = user/a1b2c3d4-…/tag/global.saved`
- Endpoint: `GET /v3/streams/contents?streamId=...&count=50&unreadOnly=false&ranked=newest`

---

## Tests (Pest)

```
tests/Feature/Opds/
  AuthenticationTest.php       — ohne Token 401, falsch Token 401, korrekt 200
                                 (Basic + ?token=)
  CatalogTest.php              — Root zeigt Navigation-Link mit href opds.saved
  SavedFeedTest.php            — Http::fake Feedly → valides Atom-XML,
                                 Namespaces (xmlns:opds),OPDS-2-Konformität,
                                 KEIN <content> in Listen-Entry
  EntryDetailTest.php          — Http::fake Feedly + Instaparser → <content type="xhtml">
                                 Instapaper in Cache → kein zweiter HTTP-Call
                                 Instapaper 500 → Fallback auf Summary + Alternate
  DownloadTest.php             — Content-Type text/html, Cache wird genutzt,
                                 404 bei unbekannter entryId

tests/Unit/Services/
  Feedly/
    EntryDtoTest.php
    FeedlyClientTest.php       — Refresh-Flow: 401 → Refresh → Retry; Cache wird geschrieben
  Opds/
    EntryBuilderTest.php       — stabile IDs, Namespaces, korrekte Links
```

Verifikation nach Implementierung:

- `php artisan test --compact` (Feature + Unit)
- `vendor/bin/pint --dirty --format agent`
- `phpstan analyse` (Larastan)
- manuell: `curl -H "Authorization: Basic <token>:" http://localhost:8000/opds`

---

## Setup-Schritte nach Implementierung

1. Neue Configfiles anlegen (`config/feedly.php`, `config/instaparser.php`, `config/opds.php`).
2. `.env.example` um alle obrigen Variablen erweitern.
3. Middleware registrieren (`bootstrap/app.php` bzw. `app/Http/Kernel.php` Alias `opds.token`).
4. Routen-Datei `routes/opds.php` anlegen und in `bootstrap/app.php` einbinden.
5. Views (Atom XML) als Blade-Templates anlegen; `welcome.blade.php` anpassen.
6. `composer test` (Pint + Larastan + Pest) laufen lassen.
7. Manuelle Verifikation via `curl` und mit einem OPDS-Reader.

---

## Offene Punkte / Annahmen

- **Feedly Client-ID/Secret:** Die Angabe, ob der Refresh-Flow Client-ID/Secret benötigt,
  ist nicht 100 % geklärt. Die Implementierung behandelt sie als **optional** — wenn in `.env`
  vorhanden, werden sie mitgesendet, andernfalls nur Refresh-Token. Falls Feedly das
  ablehnt, erscheint eine klare Fehlermeldung im Log, und der Nutzer kann die Werte
  nachtragen.
- **Cache-Reset-Verhalten:** Bei geleertem Cache fällt der Server auf den `.env`-Token zurück
  und triggert ggf. einen Refresh. Akzeptiert, da idempotent.
- **Perspektivisch (nicht Teil dieses Plans):** Pagination im Saved-Feed, Mark-as-read
  via `POST /v3/markers`, dedizierter EPUB-Acquisition-Typ (`application/epub+zip`).
