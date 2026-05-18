# Performance Analyse: SLAED CMS (Laufzeit-Regression)

**Datum:** 18. Mai 2026
**Problem:** Die Seiten-Generierungszeit (Генерация) ist von ca. `0.054` Sekunden auf `0.504` Sekunden angestiegen.
**Fokus:** Dateisystem-I/O unter Windows (`core/classes/template.php` und `core/security.php`).

## 1. Ursachenanalyse

Die Analyse des Quellcodes und die Performance-Tests zeigen, dass der Flaschenhals hauptsächlich durch exzessive Festplattenzugriffe (Disk I/O) in der Template-Engine (`core/classes/template.php`) verursacht wird. Unter Windows (NTFS) sind Dateisystemabfragen wie `realpath()`, `file_exists()` und `filemtime()` bekanntermaßen besonders langsam.

### 1.1. Redundante `realpath()`-Aufrufe (`core/classes/template.php`)
Die Methode `checkFile()` wird extrem oft aufgerufen (für jede HTML-, CSS- und JS-Datei eines Components oder Includes).
Bei jedem Aufruf wird `realpath($this->base)` ausgeführt, um den absoluten Theme-Pfad zu ermitteln, sowie `realpath($file)` für die zu prüfende Datei. Wenn z. B. 20 News-Beiträge in einer Schleife gerendert werden, führt dies zu dutzenden oder hunderten `realpath()`-Aufrufen auf immer wieder denselben Theme-Ordner innerhalb eines einzigen Requests.

### 1.2. Mehrfache Datei-"Stat"-Prüfungen
Innerhalb von `checkFile()` reihen sich folgende Prüfungen aneinander:
```php
if ($file === '' || is_link($file) || !file_exists($file) || !is_file($file)) return false;
$path = realpath($file);
```
Jede dieser Funktionen zwingt das Betriebssystem zu einem separaten "Stat"-Festplattenzugriff (Auslesen der Datei-Metadaten).

### 1.3. Laufzeit-Overhead im Cache (`filemtime`)
In der Methode `getHtml()` wird geprüft, ob eine Template-Cache-Datei erneuert werden muss:
```php
if (!is_file($cache) || filemtime($file) > filemtime($cache) || filemtime(__FILE__) > filemtime($cache))
```
Hier wird bei *jedem* Template-Rendervorgang (auch innerhalb von Schleifen) die Dateizeit der Template-Engine selbst (`filemtime(__FILE__)`) abgefragt. Da sich diese Zeit während eines aktiven Seitenaufrufs nicht ändert, ist der ständige Dateisystemzugriff unnötiger Overhead.

### 1.4. Auffälligkeiten in `core/security.php`
- Die Funktion `addLog()` wird ausgeführt, wenn das Security-Logging aktiviert ist. Sie ruft `getVariablesInfo()` auf, welches globale Arrays wie `$_SERVER` und `$_POST` mithilfe von `print_r()` serialisiert. Das beansprucht nur wenige Millisekunden, skaliert aber mit der Größe der Request-Arrays und führt pro Aufruf zu Schreibvorgängen (`fopen`, `fwrite`). Dies ist jedoch nicht die Hauptursache der halben Sekunde Verzögerung.

### 1.5. Massive Speicher- und I/O-Last in `core/classes/geoip.php`
Die Klasse `Geoip` implementiert einen eigenen MaxMind-Datenbankleser in nativem PHP. Hier gibt es einen gravierenden Flaschenhals:
- **Komplettes Einlesen in den RAM (`file_get_contents`):** In der Methode `getMmdb()` wird die *gesamte* MaxMind-Datenbank (oft mehrere Megabytes bis hin zu 60MB für City-Datenbanken) bei jedem Request, der ein GeoIP-Feature nutzt (z.B. durch `$conf['alang']` in der `index.php`), komplett in den Arbeitsspeicher geladen.
- **CPU-Overhead durch String-Operationen:** Die IP-Suche (`getMmdbNode`) schneidet in einer Schleife (bis zu 128 Mal für IPv6) mit `substr()` winzige Byteschnipsel aus diesem riesigen String heraus. Das ist bei sehr großen Strings in PHP speicher- und rechenintensiv.
- Wenn zusätzlich ASN-Daten abgefragt werden (`getAsnData`), wird eine zweite große Datenbank komplett in den RAM geladen. Das allokieren von riesigen Strings pro Seitenaufruf belastet den PHP-Garbage-Collector extrem und kostet (gerade unter Windows) sehr viel Zeit.

## 2. Optimierungsvorschläge

Um die Generierungszeit wieder auf das alte Niveau (~0.054 Sekunden) zu senken, müssen die I/O-Zugriffe in der Template-Engine durch In-Memory-Caching (RAM) minimiert werden:

1. **`realpath($this->base)` einmalig cachen:**
   Den absoluten Pfad zum Theme-Verzeichnis nur ein einziges Mal im `__construct()` der `Template`-Klasse ermitteln und als Instanz-Eigenschaft (z. B. `$this->realBase`) speichern.

2. **Geprüfte Dateien cachen (Memory-Cache):**
   Ein statisches Array `$checkedFiles = [];` in der Klasse einführen. Bevor `checkFile()` das Dateisystem abfragt, prüft es, ob das Ergebnis für den Pfad bereits im Array liegt. Somit wird jede HTML-, CSS- oder JS-Datei pro Request exakt nur einmal geprüft.

3. **Dateizeiten cachen (`filemtime`):**
   Den Wert von `filemtime(__FILE__)` in einer statischen Variable einmalig speichern, da er sich zur Laufzeit nicht verändert. Ebenfalls können Cache-Zeiten für geladene Templates im RAM gesichert werden, falls sie in Schleifen mehrfach geladen werden.

4. **GeoIP ressourcenschonend lesen (`core/classes/geoip.php`):**
   Anstatt die gesamte MMDB-Datei per `file_get_contents()` in einen gewaltigen String zu laden, sollte die Datei performant über Datei-Zeiger (`fopen`, `fseek`, `fread`) ausgelesen werden. So wird nur der winzige Datenblock in den RAM geladen, der für die Baum-Traversierung der aktuellen IP auch tatsächlich benötigt wird.

5. **Überflüssige Prüfungen entfernen:**
   Die Kombination aus `is_link()`, `file_exists()` und `is_file()` vor `realpath()` vereinfachen. `realpath()` allein ist in der Lage, die Existenz sicher zu validieren und Symlinks aufzulösen. In den meisten Fällen genügt ein simples `is_file()` als Gatekeeper.

## Fazit
Die Verlängerung der Generierungszeit auf `0.504` Sekunden ist ein klassisches Architektur-Symptom der Template-Engine unter Windows, welche wiederholt I/O-Operationen (`realpath`, `filemtime`) ausführt, anstatt diese im Arbeitsspeicher zu puffern. Sobald die redundanten Dateisystemabfragen gecacht werden, wird die ursprüngliche Performance wiederhergestellt.
