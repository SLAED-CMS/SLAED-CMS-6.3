<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

# The modes of tests/Support/route_probe.php shared by the classes of the Node routes, split so that ParaTest runs the modes side by side
abstract class NodeRouteCase extends TestCase
{
    # Run one mode of the probe once and memoize it: secure - the public form, tree - the document tree, modes - the display modes
    # The mode seo covers the canonical addresses, the head and the feeds
    # Each mode builds its own disposable database and server; its raw answer is kept before the checks, so a failed mode is not started again
    protected function getMode(string $mode): array
    {
        static $runs = [];
        static $outs = [];
        if (!isset($runs[$mode])) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_'.$mode;
            $out = $outs[$mode] ??= (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' '.$mode.' 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The routes wrote PHP or SQL errors');
            $runs[$mode] = $data['runs'][$mode];
        }
        return $runs[$mode];
    }
}
