<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

class Template {
    protected string $theme = 'default';
    protected string $base = '';
    protected string $cache = '';
    protected array $blocks = [];
    protected array $slots = [];
    protected array $stack = [];
    protected array $assets = ['css' => [], 'js' => []];

    # Set base template and cache paths for the selected theme
    public function __construct(string $theme = 'default') {
        $this->theme = $this->checkName($theme) ? $theme : 'default';
        $root = defined('BASE_DIR') ? BASE_DIR : dirname(__DIR__, 2);
        $root = str_replace('\\', '/', rtrim($root, '\\/'));
        $this->base = $root.'/templates/'.$this->theme;
        $this->cache = $root.'/storage/cache/templates/'.$this->theme;
    }

    # Return compiled page markup after validation and cache resolution
    public function getHtmlPage(string $name, array $data = [], string $layout = 'app'): string {
        if (!$this->checkName($name) || !$this->checkName($layout)) return '';
        return $this->getPageHtml($name, $data, $layout);
    }

    # Return compiled partial markup after validation and cache resolution
    public function getHtmlPart(string $name, array $data = []): string {
        if (!$this->checkName($name)) return '';
        $this->assets = ['css' => [], 'js' => []];
        $html = $this->getHtml('partials', $name, $data);
        return $this->getAssetMarkup().$html;
    }

    # Return compiled fragment markup after validation and cache resolution
    public function getHtmlFrag(string $name, array $data = []): string {
        if (!$this->checkName($name)) return '';
        $this->assets = ['css' => [], 'js' => []];
        $html = $this->getHtml('fragments', $name, $data);
        return $this->getAssetMarkup().$html;
    }

    /**
     * Render a page with explicit parent layout or fallback auto-layout
     */
    # Render a page with optional parent layout resolution
    protected function getPageHtml(string $name, array $data = [], string $layout = 'app'): string {
        $html = '';
        $this->blocks = [];
        $this->assets = ['css' => [], 'js' => []];
        try {
            $code = $this->getCode('pages', $name);
            if ($code === '') return '';
            $path = $this->getParent($code);
            if ($path !== '') {
                $part = explode('/', $path, 2);
                $file = $this->getFile($part[0], substr($part[1], 0, -5));
                if ($file && $this->checkFile($file)) {
                    $this->blocks = $this->getBlocks($code, $data);
                    return $this->getHtml($part[0], substr($part[1], 0, -5), $this->mergePageAssets($data));
                }
            }
            $file = $this->getFile('layouts', $layout);
            if ($file && $this->checkFile($file)) {
                $body = $this->getHtml('pages', $name, $data);
                $this->blocks = ['content' => $body];
                return $this->getHtml('layouts', $layout, $this->mergePageAssets($data));
            }
            $html = $this->getHtml('pages', $name, $data);
            return $this->getAssetMarkup().$html;
        } finally {
            $this->blocks = [];
            $this->assets = ['css' => [], 'js' => []];
        }
    }

    # Compile the template when needed and return rendered HTML
    protected function getHtml(string $type, string $name, array $data = []): string {
        $data = $this->setData($data);
        $file = $this->getFile($type, $name);
        if (!$file || !$this->checkFile($file)) return '';
        $code = $this->getCode($type, $name);
        if ($code === '') return '';
        $cache = $this->getCache($file);
        if ($cache === '') return '';
        $mdir = dirname($cache);
        if (!is_dir($mdir) && !mkdir($mdir, 0777, true) && !is_dir($mdir)) return '';
        if (!is_file($cache) || filemtime($file) > filemtime($cache)) {
            $code = $this->filterCode($code);
            if (file_put_contents($cache, $code, LOCK_EX) === false) return '';
        }
        return $this->getView($cache, $data);
    }

    /**
     * Render compiled PHP from a cache file or inline source through one shared path
     */
    protected function getView(string $file, array $data = [], bool $iscode = false): string {
        $data = $this->setData($data);
        $path = $file;
        if ($iscode) {
            if ($file === '') return '';
            if (!is_dir($this->cache) && !mkdir($this->cache, 0777, true) && !is_dir($this->cache)) return '';
            $path = $this->cache.'/inline-'.sha1($this->theme.'|'.$file).'.php';
            if (!is_file($path) && file_put_contents($path, $file, LOCK_EX) === false) return '';
        }
        if (!is_file($path)) return '';
        $real = realpath($path);
        if ($real === false) return '';
        if (!$iscode && in_array($real, $this->stack, true)) return '';
        $lev = ob_get_level();
        if (!ob_start()) return '';
        if (!$iscode) $this->stack[] = $real;
        try {
            extract($data, EXTR_SKIP);
            $scope = $this->getScope(get_defined_vars());
            unset($data, $file, $path, $scope);
            $res = include $real;
            if ($res === false) return '';
            $html = ob_get_clean();
            return ($html !== false) ? $html : '';
        } catch (Throwable $err) {
            unset($err);
            return '';
        } finally {
            while (ob_get_level() > $lev) ob_end_clean();
            if (!$iscode && $this->stack !== []) array_pop($this->stack);
        }
    }

    # Resolve and render an include path through the existing template pipeline
    protected function getIncl(string $path, array $data = []): string {
        if (!$this->checkIncl($path)) return '';
        $part = explode('/', $path, 2);
        if (count($part) !== 2) return '';
        $type = $part[0];
        $name = substr($part[1], 0, -5);
        if (!$this->checkType($type) || !$this->checkName($name)) return '';
        return $this->getHtml($type, $name, $data);
    }

    /**
     * Extract one valid top-level parent layout declaration from raw page code
     */
    # Extract the parent layout path from raw template code
    protected function getParent(string $code): string {
        if ($code === '' || !str_contains($code, '{%')) return '';
        if (preg_match_all('/\{%\s*extends\s+\'([^\']+)\'\s*%\}/', $code, $all) !== 1) return '';
        if (!preg_match('/^(?:\s|\{\#.*?\#\})*\{%\s*extends\s+\'([^\']+)\'\s*%\}/s', $code, $one)) return '';
        $path = $one[1] ?? '';
        return $this->checkIncl($path) && str_starts_with($path, 'layouts/') ? $path : '';
    }

    /**
     * Collect flat child blocks and render them before parent layout execution
     */
    # Collect rendered child block content for a parent layout render
    protected function getBlocks(string $code, array $data = []): array {
        $out = [];
        if ($code === '' || !str_contains($code, '{%')) return $out;
        if (preg_match_all('/\{%\s*block\s+([a-z][a-z0-9_]*)\s*%\}(.*?)\{%\s*endblock\s*%\}/s', $code, $all, PREG_SET_ORDER) < 1) {
            return $out;
        }
        foreach ($all as $item) {
            $name = $item[1] ?? '';
            $body = $item[2] ?? '';
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) continue;
            if (str_contains($body, '{% block') || str_contains($body, '{% endblock')) continue;
            $out[$name] = $this->getView($this->filterBody($body), $data, true);
        }
        return $out;
    }

    # Build the absolute template file path for a valid type and name
    protected function getFile(string $type, string $name): string {
        if (!$this->checkType($type) || !$this->checkName($name)) return '';
        return $this->base.'/'.$type.'/'.$name.'.html';
    }

    # Load the raw template code when the resolved file is valid
    protected function getCode(string $type, string $name): string {
        $file = $this->getFile($type, $name);
        if (!$file || !$this->checkFile($file)) return '';
        $code = file_get_contents($file);
        return ($code !== false) ? $code : '';
    }

    # Build a deterministic compiled cache file path for future steps
    protected function getCache(string $file): string {
        $path = realpath($file) ?: str_replace('\\', '/', $file);
        $time = is_file($file) ? (filemtime($file) ?: 0) : 0;
        $hash = sha1($this->theme.'|'.$path.'|'.$time);
        return $this->cache.'/'.$hash.'.php';
    }

    # Normalize the full template source into executable PHP
    protected function filterCode(string $code): string {
        if ($code === '') return '';
        $code = $this->filterBody($code);
        $code = $this->filterBlock($code);
        return $this->filterExt($code);
    }

    # Compile the shared template syntax before block and extends handling
    protected function filterBody(string $code): string {
        if ($code === '') return '';
        $code = $this->filterNote($code);
        $code = $this->filterRaw($code);
        $code = $this->filterEcho($code);
        $code = $this->filterIf($code);
        $code = $this->filterFor($code);
        $code = $this->filterComp($code);
        $code = $this->filterSlot($code);
        return $this->filterUse($code);
    }

    # Compile escaped echo tags with simple variable names
    protected function filterEcho(string $code): string {
        if ($code === '' || !str_contains($code, '{{')) return $code;
        return (string)preg_replace_callback(
            '/(?<!\{)\{\{\s*([_a-z][_a-z0-9]*)\s*\}\}(?!\})/',
            fn(array $data): string => '<?= $this->getSafe($'.$data[1].' ?? null); ?>',
            $code
        );
    }

    # Compile raw echo tags with simple variable names
    protected function filterRaw(string $code): string {
        if ($code === '' || !str_contains($code, '{{{')) return $code;
        return (string)preg_replace_callback(
            '/\{\{\{\s*([_a-z][_a-z0-9]*)\s*\}\}\}/',
            fn(array $data): string => '<?= $this->getRaw($'.$data[1].' ?? null); ?>',
            $code
        );
    }

    # Compile simple if and elseif tags into native PHP conditions
    protected function filterIf(string $code): string {
        if ($code === '' || !str_contains($code, '{%')) return $code;
        return (string)preg_replace_callback(
            '/\{%\s*(if|elseif)\s+(!?)([_a-z][_a-z0-9]*)\s*%\}|\{%\s*(else|endif)\s*%\}/',
            function(array $data): string {
                if (!empty($data[4])) {
                    return ($data[4] === 'else') ? '<?php else: ?>' : '<?php endif; ?>';
                }
                $name = '$'.$data[3];
                $cond = ($data[2] === '!') ? 'empty('.$name.')' : '!empty('.$name.')';
                return ($data[1] === 'if') ? '<?php if ('.$cond.'): ?>' : '<?php elseif ('.$cond.'): ?>';
            },
            $code
        );
    }

    # Compile simple foreach loops with safe iterable fallback
    protected function filterFor(string $code): string {
        if ($code === '' || !str_contains($code, '{%')) return $code;
        return (string)preg_replace_callback(
            '/\{%\s*for\s+([_a-z][_a-z0-9]*)\s+in\s+([_a-z][_a-z0-9]*)\s*%\}|\{%\s*endfor\s*%\}/',
            function(array $data): string {
                if (!empty($data[2])) {
                    $item = '$'.$data[1];
                    $list = '$'.$data[2].' ?? null';
                    $iter = '((is_array('.$list.') || ('.$list.' instanceof Traversable)) ? '.$list.' : [])';
                    return '<?php foreach ('.$iter.' as '.$item.'): ?>';
                }
                return '<?php endforeach; ?>';
            },
            $code
        );
    }

    # Compile static include tags with optional isolated array scope
    protected function filterUse(string $code): string {
        if ($code === '' || !str_contains($code, '{%')) return $code;
        return (string)preg_replace_callback(
            '/\{%\s*include\s+\'([a-z0-9\/.-]+)\'(?:\s+with\s+([_a-z][_a-z0-9]*))?\s*%\}/',
            function(array $data): string {
                $path = $data[1] ?? '';
                if (!str_contains($path, '/') && !str_ends_with($path, '.html')) {
                    $path = 'partials/'.$path.'.html';
                }
                if (!$this->checkIncl($path)) return $data[0];
                $prefix = '<?php $this->addAssetPath(\''.$path.'\'); ?>';
                
                if (!empty($data[2])) {
                    $name = '$'.$data[2].' ?? null';
                    $props = 'is_array('.$name.') ? '.$name.' : []';
                    return $prefix.'<?= $this->getIncl(\''.$path.'\', '.$props.'); ?>';
                }
                return $prefix.'<?= $this->getIncl(\''.$path.'\', $this->getScope(get_defined_vars())); ?>';
            },
            $code
        );
    }

    # Compile component tags with optional isolated props and slot payloads
    protected function filterComp(string $code): string {
        if ($code === '' || !str_contains($code, '{%')) return $code;
        return (string)preg_replace_callback(
            '/\{%\s*component\s+\'([a-z0-9\/.-]+)\'(?:\s+with\s+([_a-z][_a-z0-9]*))?\s*%\}(.*?)\{%\s*endcomponent\s*%\}/s',
            function(array $data): string {
                $path = $data[1] ?? '';
                if (!str_contains($path, '/') && !str_ends_with($path, '.html')) {
                    $path = 'partials/'.$path.'.html';
                }
                $body = $data[3] ?? '';
                if (!$this->checkIncl($path) || !str_starts_with($path, 'partials/')) return $data[0];
                if (str_contains($body, '{% component') || str_contains($body, '{% endcomponent')) return $data[0];
                $prefix = '<?php $this->addAssetPath(\''.$path.'\'); ?>';
                
                $props = '[]';
                if (!empty($data[2])) {
                    $name = '$'.$data[2].' ?? null';
                    $props = 'is_array('.$name.') ? '.$name.' : []';
                }
                return $prefix.'<?= $this->getComp(\''.$path.'\', '.$props.', '.var_export($body, true).', $this->getScope(get_defined_vars())); ?>';
            },
            $code
        );
    }

    # Compile slot placeholders inside component templates
    protected function filterSlot(string $code): string {
        if ($code === '' || !str_contains($code, '{%')) return $code;
        return (string)preg_replace_callback(
            '/\{%\s*slot(?:\s+([_a-z][_a-z0-9]*))?\s*%\}(.*?)\{%\s*endslot\s*%\}/s',
            function(array $data): string {
                $name = $data[1] ?? 'default';
                $raw = $data[2] ?? '';
                if (str_contains($raw, '{% slot') || str_contains($raw, '{% endslot')) return $data[0];
                $body = $this->filterBody($raw);
                return '<?= $this->getSlot(\''.$name.'\', '.var_export($body, true).', $this->getScope(get_defined_vars())); ?>';
            },
            $code
        );
    }

    # Compile layout block tags with override support and default fallback
    protected function filterBlock(string $code): string {
        if ($code === '' || !str_contains($code, '{%')) return $code;
        return (string)preg_replace_callback(
            '/\{%\s*block\s+([a-z][a-z0-9_]*)\s*%\}(.*?)\{%\s*endblock\s*%\}/s',
            function(array $data): string {
                $name = $data[1] ?? '';
                $raw = $data[2] ?? '';
                if (str_contains($raw, '{% block') || str_contains($raw, '{% endblock')) return $data[0];
                $body = $this->filterBody($data[2] ?? '');
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) return $data[0];
                return '<?php if (array_key_exists(\''.$name.'\', $this->blocks)): ?>'
                    .'<?= $this->getRaw($this->blocks[\''.$name.'\'] ?? null); ?>'
                    .'<?php else: ?>'
                    .$body
                    .'<?php endif; ?>';
            },
            $code
        );
    }

    # Remove valid extends tags from compiled output
    protected function filterExt(string $code): string {
        if ($code === '' || !str_contains($code, '{%')) return $code;
        return (string)preg_replace_callback(
            '/\{%\s*extends\s+\'([^\']+)\'\s*%\}/',
            function(array $data): string {
                $path = $data[1] ?? '';
                return ($this->checkIncl($path) && str_starts_with($path, 'layouts/')) ? '' : $data[0];
            },
            $code
        );
    }

    # Remove template note blocks from the source code
    protected function filterNote(string $code): string {
        if ($code === '' || !str_contains($code, '{#')) return $code;
        return (string)preg_replace('/\{\#.*?\#\}/s', '', $code);
    }

    # Escape scalar output for safe HTML rendering
    protected function getSafe(mixed $data): string {
        if ($data === null || is_array($data) || is_object($data) || is_resource($data)) return '';
        return htmlspecialchars((string)$data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    # Return raw scalar output without HTML escaping
    protected function getRaw(mixed $data): string {
        if ($data === null || is_array($data) || is_object($data) || is_resource($data)) return '';
        return (string)$data;
    }

    # Register companion CSS/JS assets for a template include path
    protected function addAssetPath(string $path): void {
        if (!$this->checkIncl($path)) return;
        $base = substr($path, 0, -5);
        $root = $this->base.'/'.$base;
        if ($this->checkFile($root.'.css')) {
            $href = 'templates/'.$this->theme.'/'.$base.'.css';
            $this->assets['css'][$href] = '<link rel="stylesheet" href="'.$href.'">';
        }
        if ($this->checkFile($root.'.js')) {
            $src = 'templates/'.$this->theme.'/'.$base.'.js';
            $this->assets['js'][$src] = '<script src="'.$src.'" defer></script>';
        }
    }

    # Render collected asset tags for fallback standalone output
    protected function getAssetMarkup(): string {
        $css = implode("\n", $this->assets['css']);
        $js = implode("\n", $this->assets['js']);
        if ($css !== '') $css .= "\n";
        if ($js !== '') $js .= "\n";
        return $css.$js;
    }

    # Merge collected assets into page-level links and scripts fields
    protected function mergePageAssets(array $data = []): array {
        $data = $this->setData($data);
        $css = implode("\n", $this->assets['css']);
        $js = implode("\n", $this->assets['js']);
        $links = (string)($data['links'] ?? '');
        $scripts = (string)($data['scripts'] ?? '');
        $data['links'] = ($css !== '') ? trim($links."\n".$css) : $links;
        $data['scripts'] = ($js !== '') ? trim($scripts."\n".$js) : $scripts;
        return $data;
    }

    # Render one component instance with named slots and a default body slot
    protected function getComp(string $path, array $props, string $body, array $scope = []): string {
        if (!$this->checkIncl($path) || !str_starts_with($path, 'partials/')) return '';
        $slots = $this->getSlotData($body, $scope);
        $data = $this->setData($props + $scope);
        $data['slot'] = $slots['default'] ?? '';
        $data['slots'] = $slots;
        $prev = $this->slots;
        $this->slots = $slots;
        try {
            return $this->getIncl($path, $data);
        } finally {
            $this->slots = $prev;
        }
    }

    # Render one slot with fallback content compiled through the shared view path
    protected function getSlot(string $name, string $body, array $scope = []): string {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) return '';
        if (array_key_exists($name, $this->slots)) return $this->getRaw($this->slots[$name]);
        return ($body !== '') ? $this->getView($body, $scope, true) : '';
    }

    # Collect named component slots and the remaining default slot content
    protected function getSlotData(string $body, array $scope = []): array {
        $out = ['default' => ''];
        if ($body === '') return $out;
        $fill = '/\{%\s*slot\s+([a-z][a-z0-9_]*)\s*%\}(.*?)\{%\s*endslot\s*%\}/s';
        if (preg_match_all($fill, $body, $all, PREG_SET_ORDER) > 0) {
            foreach ($all as $item) {
                $name = $item[1] ?? '';
                $code = $item[2] ?? '';
                if ($name === '' || str_contains($code, '{% slot') || str_contains($code, '{% endslot')) continue;
                $out[$name] = $this->getView($this->filterBody($code), $scope, true);
            }
            $body = (string)preg_replace($fill, '', $body);
        }
        if (trim($body) !== '') $out['default'] = $this->getView($this->filterBody($body), $scope, true);
        return $out;
    }

    /**
     * Build a cleaned include scope from current runtime variables
     */
    protected function getScope(array $data = []): array {
        unset($data['this'], $data['GLOBALS'], $data['scope'], $data['res']);
        unset($data['err'], $data['file'], $data['path'], $data['real']);
        unset($data['lev'], $data['iscode']);
        return $this->setData($data);
    }

    # Normalize input data for rendering and cache execution
    protected function setData(array $data): array {
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $sub => $item) {
                    if (is_object($item) || is_resource($item)) $data[$key][$sub] = null;
                }
                continue;
            }
            if (is_object($val) || is_resource($val)) $data[$key] = null;
        }
        return $data;
    }

    # Validate template names and nested template paths
    protected function checkName(string $name): bool {
        if ($name === '' || str_contains($name, "\0")) return false;
        if (str_contains($name, '\\') || str_contains($name, '..')) return false;
        if (str_contains($name, ' ') || str_contains($name, '//')) return false;
        if (str_starts_with($name, '/') || str_ends_with($name, '/')) return false;
        if (pathinfo($name, PATHINFO_EXTENSION) !== '') return false;
        if (preg_match('#^[a-z0-9-]+(?:/[a-z0-9-]+)*$#', $name) !== 1) return false;
        foreach (explode('/', $name) as $part) {
            if ($part === '' || $part === '.' || $part === '..') return false;
        }
        return true;
    }

    # Validate supported template groups for the new engine
    protected function checkType(string $type): bool {
        return in_array($type, ['pages', 'partials', 'fragments', 'layouts'], true);
    }

    # Verify that the target file exists as a regular file inside the theme base path
    protected function checkFile(string $file): bool {
        if ($file === '' || is_link($file) || !file_exists($file) || !is_file($file)) return false;
        $base = realpath($this->base);
        $path = realpath($file);
        if ($base === false || $path === false || is_link($path)) return false;
        $base = rtrim(str_replace('\\', '/', $base), '/');
        $path = str_replace('\\', '/', $path);
        return str_starts_with($path, $base.'/');
    }

    # Validate static include paths inside the current theme
    protected function checkIncl(string $path): bool {
        if ($path === '' || str_contains($path, "\0")) return false;
        if (str_contains($path, '\\') || str_contains($path, '..')) return false;
        if (str_contains($path, ' ') || str_contains($path, '//')) return false;
        if (str_starts_with($path, '/')) return false;
        if (!str_ends_with($path, '.html')) return false;
        if (str_ends_with($path, '.')) return false;
        if (preg_match('#^(partials|fragments|layouts)/[a-z0-9-]+(?:/[a-z0-9-]+)*\.html$#', $path) !== 1) return false;
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') return false;
            if (str_starts_with($part, '.')) return false;
        }
        return true;
    }
}
