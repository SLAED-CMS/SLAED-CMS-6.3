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
    protected static array $templateErrors = [];
    protected static ?bool $devMode = null;

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
        if (!$this->checkName($name)) {
            $this->reportTemplateError('pages', $name, 'Invalid page name');
            return $this->getTemplateDebugComment('pages', $name, 'invalid page name');
        }
        if (!$this->checkName($layout)) {
            $this->reportTemplateError('layouts', $layout, 'Invalid layout name');
            return $this->getTemplateDebugComment('layouts', $layout, 'invalid layout name');
        }
        return $this->getPageHtml($name, $data, $layout);
    }

    # Return compiled partial markup after validation and cache resolution
    public function getHtmlPart(string $name, array $data = []): string {
        if (!$this->checkName($name)) {
            $this->reportTemplateError('partials', $name, 'Invalid partial name');
            return $this->getTemplateDebugComment('partials', $name, 'invalid partial name');
        }
        $this->assets = ['css' => [], 'js' => []];
        $html = $this->getHtml('partials', $name, $data);
        return $this->getAssetMarkup().$html;
    }

    # Return compiled fragment markup after validation and cache resolution
    public function getHtmlFrag(string $name, array $data = []): string {
        if (!$this->checkName($name)) {
            $this->reportTemplateError('fragments', $name, 'Invalid fragment name');
            return $this->getTemplateDebugComment('fragments', $name, 'invalid fragment name');
        }
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
                $this->reportTemplateError($part[0] ?? 'layouts', $part[1] ?? $path, 'Missing parent layout');
                return $this->getTemplateDebugComment($part[0] ?? 'layouts', $part[1] ?? $path, 'missing parent layout');
            }
            $file = $this->getFile('layouts', $layout);
            if ($file && $this->checkFile($file)) {
                $body = $this->getHtml('pages', $name, $data);
                $this->blocks = ['content' => $body];
                return $this->getHtml('layouts', $layout, $this->mergePageAssets($data));
            }
            $this->reportTemplateError('layouts', $layout, 'Missing fallback layout');
            if ($this->isDevMode()) {
                $body = $this->getHtml('pages', $name, $data);
                return $this->getTemplateDebugComment('layouts', $layout, 'missing fallback layout').$this->getAssetMarkup().$body;
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
        if (!$file || !$this->checkFile($file)) {
            $this->reportTemplateError($type, $name, 'Template file not found');
            return $this->getTemplateDebugComment($type, $name, 'template file not found');
        }
        $code = $this->getCode($type, $name);
        if ($code === '') return '';
        $cache = $this->getCache($file);
        if ($cache === '') return '';
        $mdir = dirname($cache);
        if (!is_dir($mdir) && !mkdir($mdir, 0777, true) && !is_dir($mdir)) return '';
        if (!is_file($cache) || filemtime($file) > filemtime($cache) || filemtime(__FILE__) > filemtime($cache)) {
            $code = $this->filterCode($code);
            if (file_put_contents($cache, $code, LOCK_EX) === false) return '';
        }
        return $this->getView($cache, $data, false, $type, $name);
    }

    /**
     * Render compiled PHP from a cache file or inline source through one shared path
     */
    protected function getView(string $file, array $data = [], bool $iscode = false, string $sourceType = '', string $sourceName = ''): string {
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
            $type = ($sourceType !== '') ? $sourceType : ($iscode ? 'inline' : 'view');
            $name = ($sourceName !== '') ? $sourceName : basename($real);
            $this->reportTemplateError($type, $name, 'Template render failed: '.$err->getMessage());
            return $this->getTemplateDebugComment($type, $name, 'render failed: '.$err->getMessage());
        } finally {
            while (ob_get_level() > $lev) ob_end_clean();
            if (!$iscode && $this->stack !== []) array_pop($this->stack);
        }
    }

    # Resolve and render an include path through the existing template pipeline
    protected function getIncl(string $path, array $data = []): string {
        if (!$this->checkIncl($path)) {
            $this->reportTemplateError('include', $path, 'Invalid include path');
            return $this->getTemplateDebugComment('include', $path, 'invalid include path');
        }
        $part = explode('/', $path, 2);
        if (count($part) !== 2) return '';
        $type = $part[0];
        $name = substr($part[1], 0, -5);
        if (!$this->checkType($type) || !$this->checkName($name)) {
            $this->reportTemplateError($type, $name, 'Invalid include target');
            return $this->getTemplateDebugComment($type, $name, 'invalid include target');
        }
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

    # Build the absolute template file path within the theme directory
    protected function getFile(string $type, string $name): string {
        if (!$this->checkType($type) || !$this->checkName($name)) return '';
        return $this->base.'/'.$type.'/'.$name.'.html';
    }

    # Load the raw template code when the resolved file is valid
    protected function getCode(string $type, string $name): string {
        $file = $this->getFile($type, $name);
        if (!$file || !$this->checkFile($file)) {
            $this->reportTemplateError($type, $name, 'Template source not found');
            return '';
        }
        $code = file_get_contents($file);
        if ($code === false) {
            $this->reportTemplateError($type, $name, 'Template source read failed');
            return '';
        }
        return $code;
    }

    # Log one template problem per request so missing files do not fail silently
    protected function reportTemplateError(string $type, string $name, string $reason): void {
        $type = ($type !== '') ? $type : 'unknown';
        $name = ($name !== '') ? $name : '[empty]';
        $key = $this->theme.'|'.$type.'|'.$name.'|'.$reason;
        if (isset(self::$templateErrors[$key])) return;
        self::$templateErrors[$key] = true;
        $msg = sprintf('[Template] %s: theme=%s type=%s name=%s', $reason, $this->theme, $type, $name);
        if (!defined('LOGS_DIR')) {
            error_log($msg);
            return;
        }
        $log = rtrim(LOGS_DIR, '\\/').'/error_php.log';
        $norm = preg_replace(['/\d+/', '/\s+/'], ['#', ' '], substr($msg, 0, 200));
        $fingerprint = substr(sha1('template|'.$type.'|'.$name.'|'.$norm), 0, 8);
        $row = [
            'ts' => date('c'),
            'level' => 'warning',
            'type' => 'php',
            'msg' => $msg,
            'php_errno' => 512,
            'php_err' => 'USER_WARNING',
            'fingerprint' => $fingerprint,
            'ex_class' => null,
            'theme' => $this->theme,
            'template_type' => $type,
            'template_name' => $name,
            'template_reason' => $reason,
            'file' => null,
            'line' => null,
            'req_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['UNIQUE_ID'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'mem_mb' => round(memory_get_usage(true) / 1048576, 2),
            'mem_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ];
        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line === false || file_put_contents($log, $line.PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            error_log($msg);
        }
    }

    # Return a visible dev-only template error block with one safe fallback
    protected function getTemplateDebugComment(string $type, string $name, string $reason): string {
        if (!$this->isDevMode()) return '';
        $type = ($type !== '') ? $type : 'unknown';
        $name = ($name !== '') ? $name : '[empty]';
        $path = 'templates/'.$this->theme.'/'.$type;
        $file = basename($name).'.html';
        if (str_contains($name, '/')) $path .= '/'.dirname($name);
        if (str_starts_with($reason, 'render failed:')) {
            $text = 'Template render failed: '.$path.'/'.$file.' ('.substr($reason, 15).')';
        } else {
            $text = defined('_TPLMISS')
                ? sprintf(_TPLMISS, $path, basename($name))
                : 'Template file not found: '.$path.'/'.$file;
        }
        $file = $this->getFile('fragments', 'alert');
        if ($file && $this->checkFile($file)) {
            $code = $this->getCode('fragments', 'alert');
            if ($code !== '') return $this->getView($this->filterCode($code), ['is_warn' => true, 'text' => $this->getSafe($text)], true);
        }
        return $this->getSafe($text);
    }

    # Cache dev_mode so template diagnostics stay cheap
    protected function isDevMode(): bool {
        if (self::$devMode !== null) return self::$devMode;
        self::$devMode = false;
        if (!function_exists('getConfig')) return self::$devMode;
        $conf = getConfig();
        self::$devMode = !empty($conf['dev_mode']);
        return self::$devMode;
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

    # Compile escaped echo tags with simple variable names or array paths
    protected function filterEcho(string $code): string {
        if ($code === '' || !str_contains($code, '{{')) return $code;
        return (string)preg_replace_callback(
            '/(?<!\{)\{\{\s*([_a-z][_a-z0-9]*(?:\.[_a-z][_a-z0-9]*)*)\s*\}\}(?!\})/',
            function(array $data): string {
                $path = explode('.', $data[1]);
                $expr = '$'.array_shift($path);
                foreach ($path as $key) $expr .= '[\''.$key.'\']';
                return '<?= $this->getSafe('.$expr.' ?? null); ?>';
            },
            $code
        );
    }

    # Compile raw echo tags with simple variable names or array paths
    protected function filterRaw(string $code): string {
        if ($code === '' || !str_contains($code, '{{{')) return $code;
        return (string)preg_replace_callback(
            '/\{\{\{\s*([_a-z][_a-z0-9]*(?:\.[_a-z][_a-z0-9]*)*)\s*\}\}\}/',
            function(array $data): string {
                $path = explode('.', $data[1]);
                $expr = '$'.array_shift($path);
                foreach ($path as $key) $expr .= '[\''.$key.'\']';
                return '<?= $this->getRaw('.$expr.' ?? null); ?>';
            },
            $code
        );
    }

    # Compile simple if and elseif tags into native PHP conditions
    protected function filterIf(string $code): string {
        if ($code === '' || !str_contains($code, '{%')) return $code;
        return (string)preg_replace_callback(
            '/\{%\s*(if|elseif)\s+(.+?)\s*%\}|\{%\s*(else|endif)\s*%\}/',
            function(array $data): string {
                if (!empty($data[3])) {
                    return ($data[3] === 'else') ? '<?php else: ?>' : '<?php endif; ?>';
                }
                $cond = $this->parseIfCondition(trim($data[2] ?? ''));
                if ($cond === '') return $data[0];
                return ($data[1] === 'if') ? '<?php if ('.$cond.'): ?>' : '<?php elseif ('.$cond.'): ?>';
            },
            $code
        );
    }

    # Parse a small safe template condition grammar: !var, var.path, joined by and/or
    protected function parseIfCondition(string $expr): string {
        if ($expr === '') return '';
        if (!preg_match('/^!?[_a-z][_a-z0-9]*(?:\.[_a-z][_a-z0-9]*)*(?:\s+(?:and|or)\s+!?[_a-z][_a-z0-9]*(?:\.[_a-z][_a-z0-9]*)*)*$/', $expr)) return '';
        $parts = preg_split('/\s+(and|or)\s+/', $expr, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false || $parts === []) return '';
        $compiled = [];
        foreach ($parts as $idx => $part) {
            if ($idx % 2 === 1) {
                $compiled[] = ($part === 'and') ? '&&' : '||';
                continue;
            }
            $neg = str_starts_with($part, '!');
            $path = explode('.', ltrim($part, '!'));
            $expr = '$'.array_shift($path);
            foreach ($path as $key) $expr .= '[\''.$key.'\']';
            $compiled[] = $neg ? 'empty('.$expr.')' : '!empty('.$expr.')';
        }
        return implode(' ', $compiled);
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
        unset($data['sourceType'], $data['sourceName']);
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

    # Verify that the target file exists inside the theme base path
    protected function checkFile(string $file): bool {
        if ($file === '' || is_link($file) || !file_exists($file) || !is_file($file)) return false;
        $path = realpath($file);
        if ($path === false || is_link($path)) return false;
        $path = str_replace('\\', '/', $path);
        $base = realpath($this->base);
        if ($base === false) return false;
        $base = rtrim(str_replace('\\', '/', $base), '/');
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
