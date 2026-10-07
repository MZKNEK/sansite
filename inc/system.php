<?php
    // The server's resources for the admin panel, read from Linux's /proc when
    // the panel is opened: load, memory and swap, the processes using the most
    // memory, uptime, OPcache and PHP limits. The processor use is not read here
    // (it would mean waiting 250 ms): the panel takes it from the background
    // check (inc/diag.php), which reads /proc/stat every 10 seconds anyway.
    // Where /proc cannot be read (another system, or a locked down PHP) the
    // parts come back null and the panel says so.

    const SYSTEM_TOP_PROCESSES = 8;

    // the cpu line of /proc/stat as [busy, idle, iowait, steal, total] jiffies, or null
    function systemCpuTimes()
    {
        $line = strtok((string)@file_get_contents('/proc/stat'), "\n");
        if (!preg_match('/^cpu\s+(.+)$/', (string)$line, $match))
            return null;

        // user nice system idle iowait irq softirq steal (guest time is already in user)
        $t = array_map('intval', preg_split('/\s+/', trim($match[1])));
        $t = array_pad($t, 8, 0);
        $total = array_sum(array_slice($t, 0, 8));

        return ['idle' => $t[3], 'iowait' => $t[4], 'steal' => $t[7], 'total' => $total];
    }

    // the number of processors and their model, from /proc/cpuinfo
    function systemCpuInfo()
    {
        $info = (string)@file_get_contents('/proc/cpuinfo');
        $cores = preg_match_all('/^processor\s*:/m', $info);
        $model = preg_match('/^model name\s*:\s*(.+)$/m', $info, $match) ? trim($match[1]) : null;

        return ['cores' => $cores ?: null, 'model' => $model];
    }

    // /proc/meminfo in bytes: total, available, swap total and free, or null
    function systemMemory()
    {
        $info = (string)@file_get_contents('/proc/meminfo');
        if (!preg_match_all('/^(\w+):\s+(\d+) kB/m', $info, $matches))
            return null;

        $kb = array_combine($matches[1], $matches[2]);
        $bytes = function ($key) use ($kb) { return isset($kb[$key]) ? (int)$kb[$key] * 1024 : null; };
        if ($bytes('MemTotal') === null)
            return null;

        return [
            'total' => $bytes('MemTotal'),
            // older kernels have no MemAvailable: free memory and the page cache instead
            'available' => $bytes('MemAvailable') ?? ($bytes('MemFree') + $bytes('Cached') + $bytes('Buffers')),
            'cached' => ($bytes('Cached') ?? 0) + ($bytes('Buffers') ?? 0),
            'swapTotal' => $bytes('SwapTotal') ?? 0,
            'swapFree' => $bytes('SwapFree') ?? 0
        ];
    }

    // Memory of the running programs, the workers of one program counted
    // together: [name => ['count', 'bytes']], the biggest first.
    function systemProcesses()
    {
        $programs = [];
        foreach (glob('/proc/[0-9]*', GLOB_ONLYDIR) ?: [] as $dir) {
            $status = @file_get_contents($dir . '/status');
            if ($status === false || !preg_match('/^VmRSS:\s+(\d+) kB/m', $status, $rss))
                continue;  // gone meanwhile, or a kernel thread
            $name = preg_match('/^Name:\s+(.+)$/m', $status, $match) ? trim($match[1]) : '?';
            // "php-fpm8.1" and its "php-fpm: pool www" workers are one program
            $name = preg_replace('/^(php-fpm)[\d.]*.*$/', '$1', $name);

            $programs[$name]['count'] = ($programs[$name]['count'] ?? 0) + 1;
            $programs[$name]['bytes'] = ($programs[$name]['bytes'] ?? 0) + (int)$rss[1] * 1024;
        }
        uasort($programs, function ($a, $b) { return $b['bytes'] <=> $a['bytes']; });

        return array_slice($programs, 0, SYSTEM_TOP_PROCESSES, true);
    }

    // seconds since the server started, or null
    function systemUptime()
    {
        $uptime = (string)@file_get_contents('/proc/uptime');

        return $uptime === '' ? null : (int)floatval($uptime);
    }

    // the name of the system, e.g. "Ubuntu 22.04.4 LTS", and its kernel
    function systemName()
    {
        $release = (string)@file_get_contents('/etc/os-release');
        $name = preg_match('/^PRETTY_NAME="?([^"\n]+)"?$/m', $release, $match) ? $match[1] : php_uname('s');

        return $name . ' · jądro ' . php_uname('r');
    }

    // OPcache: ['used', 'size', 'hits' in percent, 'scripts'], or null when it is off
    function systemOpcache()
    {
        $status = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        if (!is_array($status) || empty($status['opcache_enabled']))
            return null;

        $memory = $status['memory_usage'];
        $stats = $status['opcache_statistics'];
        $asked = $stats['hits'] + $stats['misses'];

        return [
            'used' => $memory['used_memory'] + $memory['wasted_memory'],
            'size' => $memory['used_memory'] + $memory['wasted_memory'] + $memory['free_memory'],
            'hits' => $asked ? round(100 * $stats['hits'] / $asked, 1) : null,
            'scripts' => $stats['num_cached_scripts'],
            'full' => !empty($status['cache_full'])
        ];
    }

    // everything above at once, for the panel
    function systemStats()
    {
        $load = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;

        return [
            'cpuInfo' => systemCpuInfo(),
            'load' => is_array($load) ? $load : null,
            'memory' => systemMemory(),
            'processes' => systemProcesses(),
            'uptime' => systemUptime(),
            'name' => systemName(),
            'opcache' => systemOpcache()
        ];
    }
