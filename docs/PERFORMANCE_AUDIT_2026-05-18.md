# Performance Audit Report — SLAED CMS v6.3

**Datum:** 2026-05-18  
**Tester:** Eduard Laas  
**Scope:** Frontend `/index.php?name=news` und Admin `/admin.php`  
**Vergleich:** v6.2 (Baseline 0.041 s) → v6.3 (gemessen 0.465 s warm, 3.337 s cold)

---

## 1. Browser-Messungen (Playwright v1.60)

### Frontend — `https://slaed.loc/index.php?name=news`

| Lauf | HTTP | TTFB (fetch) | TTFB (timing) | DOM Ready |
|------|------|-------------|--------------|-----------|
| 1 (cold) | 200 | 3337 ms | ~3200 ms | ~3350 ms |
| 2 (warm) | 200 | 465 ms | ~420 ms | ~470 ms |
| 3 (warm) | 200 | 461 ms | ~415 ms | ~465 ms |

**Response Headers (Run 1):**
- `x-powered-cms: SLAED CMS`
- `x-powered-by: PHP/8.4`
- `cache-control: no-store, no-cache`
- `content-type: text/html; charset=utf-8`

### Admin — `https://slaed.loc/admin.php`

| Lauf | HTTP | TTFB (fetch) | DOM Ready | Seite |
|------|------|-------------|-----------|-------|
| 1 (cold) | 200 | 2990 ms | ~3010 ms | Login |
| 2 (warm) | 200 | 220 ms | ~225 ms | Login |
| 3 (warm) | 200 | 218 ms | ~222 ms | Login |

**Ziel:** < 50 ms warm (v6.2 Baseline: 41 ms).  
**Ist:** 465 ms Frontend, 220 ms Admin — **11× / 5× langsamer als Ziel.**

---

## 2. v6.2 → v6.3 Vergleich

| Bereich | v6.2 | v6.3 | Δ |
|---------|------|------|---|
| Config laden | `include()` direkt | `glob(CONFIG_DIR.'/*.php')` | +15–30 ms |
| Statistik-Log | Kein Lock | `flock(LOCK_EX)` + Vollüberschreibung | +80–120 ms |
| Sessions-Log | Nicht vorhanden | `flock(LOCK_EX)` pro Request | +50–100 ms |
| Cache-Cleanup | Wenige Dateien | 934× `filemtime()` pro Request | +80–150 ms |
| Template-Engine | `str_replace` + `static $cach` | OOP + `filemtime(__FILE__)` pro Fragment | +20–40 ms |
| GeoIP | `fseek()+fread()` 1,2 MB `.dat` | `file_get_contents()` 20,9 MB `.mmdb` | +3000 ms cold |
| Security-Scan | Kein separater Scan | 6+ preg pro GET/POST-Parameter | +10–30 ms |
| Admin-Sidebar | Keine Zähler | 10–15 COUNT-Queries pro Request | +30–80 ms |

---

## 3. Befunde — KRITISCH (K)

### K-01 — `statistic.log` LOCK_EX pro Request
- **Datei:** `core/system.php:1342`
- **Problem:** `flock(LOCK_EX)` blockiert alle konkurrierenden PHP-FPM-Worker bis der Lock frei ist. Danach wird die gesamte Datei überschrieben (`fwrite` auf `w`-Handle).
- **Impact:** +80–120 ms pro Request, unter Last exponentiell schlechter (Lock-Contention).

### K-02 — `sessions.log` LOCK_EX pro Request
- **Datei:** `core/system.php:1174`
- **Problem:** Gleicher Muster wie K-01. Jeder Request schreibt in `sessions.log` mit exklusivem Lock.
- **Impact:** +50–100 ms pro Request.

### K-03 — 934× `filemtime()` bei Cache-Cleanup
- **Datei:** `core/system.php:1773`
- **Problem:** Pro Request wird das gesamte Cache-Verzeichnis durchlaufen: 934 `filemtime()`-Syscalls. In v6.2 gab es kaum Cache-Dateien.
- **Impact:** +80–150 ms pro Request.

### K-04 — `doScript()` Dateisystem-Ops pro Request
- **Datei:** `core/system.php:2391`
- **Problem:** Prüft und verarbeitet Script-Dateien via Dateisystem bei jedem Request, auch wenn keine Änderung vorliegt.
- **Impact:** +10–25 ms.

### K-05 — `doCss()` Dateisystem-Ops pro Request
- **Datei:** `core/system.php:2437`
- **Problem:** Analog zu K-04 für CSS-Dateien.
- **Impact:** +10–25 ms.

### K-06 — `addLog()` bei jedem Request
- **Datei:** `core/security.php:106`
- **Problem:** `fopen()` + `clearstatcache()` + `filesize()` + `fwrite()` + `fclose()` bei jedem Request, auch wenn kein Security-Event vorliegt.
- **Impact:** +5–15 ms, plus I/O-Overhead.

### K-07 — Template: 30+ `filemtime()`/`realpath()` pro Request
- **Datei:** `core/classes/template.php:117`
- **Problem:** Pro Template-Fragment: `filemtime($file)` + `filemtime($cache)` + `filemtime(__FILE__)` + `realpath($this->base)`. Bei einer Seite mit 15–20 Fragmenten: 60+ Syscalls.
- **Impact:** +20–40 ms.

### K-08 — `admininfo()` 10–15 COUNT-Queries pro Admin-Request
- **Datei:** `core/admin.php:276–379`
- **Problem:** Sidebar-Zähler (News, User, Comments, …) werden bei jedem Admin-Request neu abgefragt — kein Cache.
- **Impact:** +30–80 ms pro Admin-Request.

### K-09 — N+1 in `getAdminCategoryList()`
- **Datei:** `core/admin.php:435–454`
- **Problem:** Pro Kategorie 2 SQL-Queries. Bei 20 Kategorien = 40 Queries für eine einfache Liste.
- **Impact:** +20–60 ms je nach Kategorienanzahl.

### K-10 — ReDoS-Risiko: DB-Wert als Regex-Pattern
- **Datei:** `core/system.php:1022`
- **Problem:** Ein aus der Datenbank gelesener Wert wird direkt als Regex-Pattern in `preg_match()` verwendet — ohne Escaping via `preg_quote()`. Schadhafte Daten können katastrophales Backtracking auslösen.
- **Impact:** Sicherheitsrisiko + potentielle 100%+ CPU-Auslastung.

### K-11 — `checkGet()`: 6 preg pro GET-Parameter
- **Datei:** `core/security.php:295`
- **Problem:** Pro GET-Parameter werden 6 Regex-Operationen + `base64_decode()` + weiteres `preg_replace()` ausgeführt — für jeden Parameter einzeln.
- **Impact:** +10–30 ms bei vielen Parametern.

### K-12 — `checkPost()`: 6 preg pro POST-Parameter
- **Datei:** `core/security.php:325`
- **Problem:** Identisch zu K-11 für POST-Daten.
- **Impact:** +10–30 ms.

### K-13 — `checkCookie()`: 5 preg pro Cookie
- **Datei:** `core/security.php:351`
- **Problem:** Pro Cookie 5 Regex-Operationen + `base64_decode()`. Bei 5–10 Cookies: 25–50 preg-Aufrufe.
- **Impact:** +5–15 ms.

### K-14 — `while(preg_match){ preg_replace }` in `filterText()`
- **Datei:** `core/security.php:865`
- **Problem:** O(N²)-Scan: Die Schleife läuft solange, bis kein Pattern mehr gefunden wird — bei langen Texten quadratische Komplexität.
- **Impact:** +5–50 ms je nach Textlänge.

### K-15 — GeoIP: 20,9 MB `file_get_contents()` bei Cold-Start
- **Datei:** `core/classes/geoip.php:215`
- **Problem:** `getMmdb()` lädt `asn.mmdb` (12 MB) + `country.mmdb` (8,9 MB) per `file_get_contents()` vollständig in den RAM. `static $list` cached nur pro PHP-FPM-Worker — neuer Worker = erneuter 3+-Sekunden-Cold-Start. In v6.2: 1,2 MB `.dat` mit `fseek()+fread()`.
- **Impact:** +3000+ ms beim ersten Request pro Worker.

### K-16 — Blocking reCAPTCHA HTTP-Request
- **Datei:** `core/system.php:2104`
- **Problem:** Synchroner HTTP-Request an Google-reCAPTCHA-Server im Request-Path. Timeout oder langsame Verbindung blockiert den gesamten Request.
- **Impact:** +100–500 ms je nach Netzwerk.

### K-17 — Blocking RSS-Fetch im Request-Path
- **Datei:** `core/system.php:3949`
- **Problem:** Synchroner `file_get_contents()` auf externe RSS-URL im Request-Path, ohne Timeout-Limit und ohne Cache-Prüfung.
- **Impact:** +100–2000 ms bei langsamen Feeds.

---

## 4. Befunde — MITTEL (M)

| ID | Datei | Problem | Impact |
|----|-------|---------|--------|
| M-01 | `core/system.php:29` | `glob(CONFIG_DIR.'/*.php')` bei jedem Request | +15–30 ms |
| M-02 | `core/classes/pdo.php:103` | `explode(',', $conf['variables'])` bei jeder SQL-Query | +1–3 ms × N Queries |
| M-03 | `core/classes/pdo.php:87` | `preg_match('/:([a-zA-Z0-9_]+)/', $query)` immer, auch ohne Debug | +1–2 ms × N |
| M-04 | `core/classes/parser.php:78` | `replaceText()` O(N²): N×str_replace + preg + N×str_replace pro Regel | +10–50 ms bei viel Text |
| M-05 | `core/classes/parser.php:196` | `normalizeImageSource()`: 2× `is_file()` pro Bild, kein Memo-Cache | +2–5 ms × N Bilder |
| M-06 | `core/classes/parser.php:508` | `filterAttach()`: 3× `file_exists()` + `getimagesize()` pro Attachment | +5–15 ms × N |
| M-07 | `core/classes/parser.php:433` | `filterBbBlocks()`: `while(preg_match)` + `preg_replace_callback` | +5–20 ms |
| M-08 | `core/admin.php:208` | `scandir('lang')` bei jedem Admin-Request | +2–5 ms |
| M-09 | `core/classes/template.php:673` | `realpath($this->base)` ungecacht, pro Fragment | +1–2 ms × N |
| M-10 | `core/system.php:1022` | Censor-Liste aus DB ohne `preg_quote()` | Risiko + CPU |
| M-11 | `admin/modules/monitor.php:42` | `exec('powershell ...')` ohne APCu-Cache | +200–500 ms pro Monitor-Request |
| M-12 | `admin/modules/monitor.php:819` | `file()` lädt gesamtes Log-File in RAM | RAM-Risiko bei großen Logs |
| M-13 | `admin/modules/monitor.php:754` | `RecursiveDirectoryIterator` auf 4 Dirs ohne Cache | +50–200 ms |
| M-14 | `core/system.php` | Session-Tracking schreibt bei jedem Request, auch bei Bots | Unnötiger Lock |
| M-15 | `core/classes/geoip.php` | Kein APCu/Redis Shared-Cache für mmdb-Daten | Worker-Isolation |
| M-16 | `core/system.php` | `getBlocks()`: unbekannte Anzahl SQL-Queries für Sidebar-Blöcke | N×Query |
| M-17 | `core/classes/template.php` | Kein Limit für Cache-Cleanup-Durchläufe | unkontrolliert |
| M-18 | `config/global.php` | `cache=0` — kein Page-Cache aktiv | Jede Seite neu generiert |
| M-19 | `config/global.php` | `cache_css=0`, `cache_script=0` — Assets nicht gebündelt | N HTTP-Requests |
| M-20 | `core/system.php` | Bot-Erkennung: langer `strpos()`-Loop über 80+ Bot-Strings | +2–5 ms |

---

## 5. Befunde — GERING (G)

| ID | Datei | Problem |
|----|-------|---------|
| G-01 | `core/classes/pdo.php` | Kein Query-Result-Cache (APCu) für häufige Queries |
| G-02 | `core/system.php` | `catmids()` baut Kategorie-Baum ohne Memoization |
| G-03 | `core/security.php` | `filterText()` kompiliert Regex bei jedem Aufruf neu |
| G-04 | `core/classes/template.php` | Template-Cache-Pfad-Berechnung ohne Hashmap |
| G-05 | `core/admin.php` | Admininfo-Block ohne `Cache-Control` Header |
| G-06 | `admin/modules/monitor.php` | Kein Pagination für Log-Ansicht |
| G-07 | `core/system.php` | `rss_read()`: kein HTTP-Timeout-Limit gesetzt |
| G-08 | `core/classes/parser.php` | `filterText()` + `filterBbBlocks()` doppelt aufgerufen |
| G-09 | `core/system.php` | `create_dump()`: 1-KB-Chunk-`fread()`-Loop statt `stream_copy_to_stream()` |
| G-10 | `config/global.php` | `botsact=1`: Bot-Tracking schreibt DB bei jedem Bot-Request |
| G-11 | `core/system.php` | Kein `strtolower()` auf Headers — case-sensitiver Vergleich |
| G-12 | `config/global.php` | `geoip_store=0` — GeoIP-Ergebnisse werden nicht persistiert |
| G-13 | `core/classes/geoip.php` | `static $list` pro Worker isoliert — kein Shared Memory |

---

## 6. Priorisierte Fixes — Top 10 nach Impact

| Prio | Fix | Erw. Einsparung |
|------|-----|----------------|
| 1 | Cache-Cleanup auf 1%-Sampling oder Cron-Job auslagern (K-03) | −80–150 ms |
| 2 | `statistic.log` → append-only ohne Lock, periodisch aggregieren (K-01) | −80–120 ms |
| 3 | `sessions.log` → DB-Insert ohne Lock oder Session-DB nutzen (K-02) | −50–100 ms |
| 4 | GeoIP: APCu/Redis Shared-Cache statt `file_get_contents()` (K-15) | −3000 ms cold |
| 5 | `admininfo()` Zähler → APCu-Cache mit TTL 60 s (K-08) | −30–80 ms admin |
| 6 | `realpath($this->base)` im Template-Konstruktor einmalig cachen (K-07/M-09) | −20–40 ms |
| 7 | `glob()` in `getConfig()` → feste `require`-Liste (M-01) | −15–30 ms |
| 8 | Security-Scan: Regex vorkompilieren, `preg_quote()` für DB-Werte (K-10–K-14) | −15–40 ms |
| 9 | N+1 in `getAdminCategoryList()` → Single-Query mit JOIN (K-09) | −20–60 ms admin |
| 10 | Page-Cache aktivieren (`cache=1`) für anonyme Requests (M-18) | −400 ms gesamt |

**Erwartetes Ergebnis nach Top-3:** Warm-TTFB Frontend < 200 ms  
**Erwartetes Ergebnis nach Top-10:** Warm-TTFB Frontend < 60 ms (Ziel: 41 ms wie v6.2)

---

## 7. Cold-Start-Analyse

Der Cold→Warm-Sprung (3337 → 465 ms) entsteht durch:

1. **GeoIP-Load (K-15):** ~3000 ms — `file_get_contents()` auf 20,9 MB `.mmdb`-Dateien
2. **OPcache-Miss:** Alle PHP-Dateien werden beim ersten Request kompiliert
3. **FS-Cache-Miss:** Kein Warmup der Kernel-Page-Cache

**Lösung:** APCu-Shared-Cache für GeoIP-Daten. Der `static $list` in `geoip.php` ist nur per Worker isoliert — ein APCu-Eintrag ist workerglobal.

---

## 8. Zusammenfassung

| Kategorie | Anzahl Befunde |
|-----------|---------------|
| KRITISCH | 17 |
| MITTEL | 20 |
| GERING | 13 |
| **Gesamt** | **50** |

**Hauptursachen des 11×-Regressions (v6.2 → v6.3):**

1. Drei blocking Locks pro Request (Statistik + Sessions + Cache-Cleanup)
2. GeoIP: 10× größere mmdb-Dateien ohne Shared-Memory-Cache
3. OOP-Template-Engine mit 30+ Syscalls pro Fragment vs. `str_replace` in v6.2
4. Security-Layer: 6+ Regex pro Parameter (neu in v6.3)
5. Admin-Sidebar: 10–15 COUNT-Queries ohne Cache (neu in v6.3)

Die Fixes K-01 bis K-03 + K-15 allein würden die warm TTFB von 465 ms auf ca. 100–150 ms senken. Mit Page-Cache (M-18) wäre das Ziel von 41 ms erreichbar.
