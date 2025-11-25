<?php
$root = __DIR__;
$skip = [
  'setup'.DIRECTORY_SEPARATOR,
  'plugins'.DIRECTORY_SEPARATOR,
  'uploads'.DIRECTORY_SEPARATOR,
  'config'.DIRECTORY_SEPARATOR,
];
$langDirs = [
  'language'.DIRECTORY_SEPARATOR,
  'admin'.DIRECTORY_SEPARATOR.'language'.DIRECTORY_SEPARATOR,
  'modules'.DIRECTORY_SEPARATOR,
];
$files = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($rii as $f) {
  if ($f->isDir()) continue;
  $p = $f->getPathname();
  $rel = str_replace($root.DIRECTORY_SEPARATOR, '', $p);
  if (pathinfo($p, PATHINFO_EXTENSION) !== 'php') continue;
  foreach ($skip as $s) { if (stripos($rel, $s) === 0 || str_contains($rel, $s)) continue 2; }
  $files[] = $p;
}
$langFiles = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($rii as $f) {
  if ($f->isDir()) continue;
  $p = $f->getPathname();
  $rel = str_replace($root.DIRECTORY_SEPARATOR, '', $p);
  if (pathinfo($p, PATHINFO_EXTENSION) !== 'php') continue;
  $isLang = false;
  if (str_starts_with($rel, 'language'.DIRECTORY_SEPARATOR)) $isLang = true;
  if (str_starts_with($rel, 'admin'.DIRECTORY_SEPARATOR.'language'.DIRECTORY_SEPARATOR)) $isLang = true;
  if (str_starts_with($rel, 'modules'.DIRECTORY_SEPARATOR) && str_contains($rel, DIRECTORY_SEPARATOR.'language'.DIRECTORY_SEPARATOR)) $isLang = true;
  if ($isLang) $langFiles[] = $p;
}
$consts = [];
foreach ($langFiles as $p) {
  $c = @file_get_contents($p);
  if ($c === false) continue;
  if (preg_match_all('/define\s*\(\s*[\'"](_[A-Z][A-Z0-9_]*)[\'"]\s*,/u', $c, $m)) {
    foreach ($m[1] as $k) $consts[$k] = true;
  }
}
$unused = [];
foreach (array_keys($consts) as $k) {
  $count = 0;
  foreach ($files as $p) {
    $c = @file_get_contents($p);
    if ($c === false) continue;
    $count += preg_match_all('/\b'.preg_quote($k,'/').'\b/u', $c);
    if ($count > 0) break;
  }
  if ($count === 0) $unused[] = $k;
}
sort($unused);
file_put_contents('unused_language_constants.txt', implode(PHP_EOL, $unused));
echo "Done. See unused_language_constants.txt\n";