<?php
# CPU load analyzer with cache in seconds (Windows 10/11, Linux/macOS)
function getCpuLoad($tcache = 2) {
	static $cache = ['time' => 0, 'cpu' => 'N/A', 'info' => 'N/A'];

	$start = microtime(true); // start timing (includes cache check)

	// Return cached value if still valid (return elapsed ms too)
	if (time() - $cache['time'] < $tcache) {
		$elapsed = round((microtime(true) - $start) * 1000, 2);
		return [$cache['cpu'], $cache['info'], $elapsed];
	}

	$load = '';
	$percent = null;

	if (stristr(PHP_OS, 'WIN')) {
		// Windows: PowerShell query (stderr redirected inside PS command)
		$out = [];
		$cmd = 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_Processor -ErrorAction SilentlyContinue | Measure-Object -Property LoadPercentage -Average).Average"';
		@exec($cmd, $out);
		if (!empty($out)) {
			$val = trim($out[0]);
			$val = str_replace(',', '.', $val);                 // normalize decimal comma
			if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $val, $m)) $percent = (float)$m[1];
		}

	} else {
		$raw = null;

		// 1) sys_getloadavg()
		if (function_exists('sys_getloadavg')) {
			$tmp = sys_getloadavg();
			if (isset($tmp[0]) && is_numeric($tmp[0])) $raw = (float)$tmp[0];
		}

		// 2) /proc/loadavg
		if ($raw === null && file_exists('/proc/loadavg')) {
			$tmp = explode(' ', file_get_contents('/proc/loadavg'));
			if (isset($tmp[0]) && is_numeric($tmp[0])) $raw = (float)$tmp[0];
		}

		// 3) uptime fallback
		if ($raw === null) {
			$tmp = [];
			@exec('uptime 2>/dev/null', $tmp);
			if ($tmp && preg_match('#averages?:\s*([0-9.]+),#i', $tmp[0], $match)) {
				if (isset($match[1]) && is_numeric($match[1])) $raw = (float)$match[1];
			}
		}

		// convert raw loadavg to percent using logical CPU count
		if (is_numeric($raw)) {
			$nproc = 0;
			$nout = [];

			@exec('nproc 2>/dev/null', $nout);
			if (!empty($nout) && is_numeric(trim($nout[0]))) $nproc = (int) trim($nout[0]);

			if ($nproc <= 0) {
				@exec('getconf _NPROCESSORS_ONLN 2>/dev/null', $nout);
				if (!empty($nout) && is_numeric(trim($nout[0]))) $nproc = (int) trim($nout[0]);
			}

			if ($nproc <= 0) {
				@exec('sysctl -n hw.ncpu 2>/dev/null', $nout);
				if (!empty($nout) && is_numeric(trim($nout[0]))) $nproc = (int) trim($nout[0]);
			}

			if ($nproc <= 0 && file_exists('/proc/cpuinfo')) {
				$info = file_get_contents('/proc/cpuinfo');
				if ($info !== false) {
					preg_match_all('/^processor\s*:/m', $info, $matches);
					if (!empty($matches[0])) $nproc = count($matches[0]);
				}
			}

			if ($nproc <= 0) $nproc = 1;
			$percent = ($raw / $nproc) * 100.0;
		}
	}

	// normalize and clamp numeric percent
	if (is_numeric($percent)) {
		$cpu = round((float)$percent, 2);
		if ($cpu < 0) $cpu = 0.0;
		if ($cpu > 100) $cpu = 100.0;
		$info = 'Load...';
	} else {
		$cpu = $info = 'N/A';
	}

	// Update cache
	$cache = ['time' => time(), 'cpu' => $cpu, 'info' => $info];

	$elapsed = round((microtime(true) - $start) * 1000, 2); // ms
	return [$cpu, $info, $elapsed];
}

list($cpu, $info, $ms) = getCpuLoad(4);
echo "CPU: $cpu % (took {$ms} ms)<br>\n";

// Test
for ($i = 0; $i < 8; $i++) {
	list($cpu, $info, $ms) = getCpuLoad(4);
	echo "$i - CPU: $cpu %, Info: $info (took {$ms} ms)<br>\n";
	sleep(1);
}