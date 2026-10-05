<?php
    // Why the site is sometimes out of reach, for the "Dostępność strony" card of
    // the admin panel. Cloudflare answers 522 when it cannot connect to the
    // server, so every 10 seconds inc/check-site.php (cron) asks the site twice:
    // through Cloudflare, as a visitor does, and straight on the server
    // (127.0.0.1), past Cloudflare; the wiki through Cloudflare for comparison.
    // A failure only through Cloudflare points at the way to the server (a full
    // accept queue, a firewall), one also on the server at nginx or PHP.
    //
    // Every round also notes what could explain a failure: the kernel's TCP
    // counters (connections dropped on a full accept queue), the connections of
    // nginx, the PHP-FPM pool (busy workers, waiting requests), and the requests
    // since the round before from the site's nginx log, with the addresses that
    // sent most of them. Kept DIAG_KEEP_DAYS days in inc/data/diag/, one JSON
    // line per round, one file per day.
    require_once __DIR__ . '/bot.php';
    require_once __DIR__ . '/system.php';

    const DIAG_SITE = 'https://sanakan.pl';
    const DIAG_WIKI = 'https://wiki.sanakan.pl';
    const DIAG_INTERVAL = 10;
    const DIAG_ROUNDS = 6;  // per cron run, one a minute
    const DIAG_KEEP_DAYS = 3;
    // Cloudflare gives up on the server after about 19 s and answers 522, so
    // the probes through it wait longer to see that answer
    const DIAG_TIMEOUT_PUBLIC = 25;
    const DIAG_TIMEOUT_LOCAL = 20;
    // a probe answering slower than this is slow
    const DIAG_SLOW_MS = 3000;
    const DIAG_TOP = 5;
    // the probes say who they are, so their requests are left out of the traffic
    const DIAG_USER_AGENT = 'SanakanDiag/1 (+https://sanakan.pl)';
    // at most this much of the log per round; more is skipped
    const DIAG_LOG_READ_MAX = 8 << 20;

    // the same ranges as set_real_ip_from in server/nginx/sanakan.conf
    const CLOUDFLARE_RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32'
    ];

    // the probes in the order the panel shows them: [name, through Cloudflare]
    const DIAG_PROBE_NAMES = [
        'pub' => ['strona przez Cloudflare', true],
        'pubphp' => ['PHP przez Cloudflare', true],
        'loc' => ['strona na serwerze', false],
        'locphp' => ['PHP na serwerze', false],
        'wiki' => ['wiki przez Cloudflare', true]
    ];

    // what an answer of Cloudflare or nginx means
    const DIAG_STATUS_LABELS = [
        502 => 'nginx nie dostał odpowiedzi od PHP',
        504 => 'nginx nie doczekał się PHP',
        520 => 'serwer zwrócił pustą lub błędną odpowiedź',
        521 => 'serwer odrzucił połączenie',
        522 => 'Cloudflare nie połączył się z serwerem',
        523 => 'Cloudflare nie znalazł drogi do serwera',
        524 => 'serwer nie odpowiedział w 100 s'
    ];

    // curl errors of a probe that got no answer at all
    const DIAG_CURL_ERRORS = [
        6 => 'nie znaleziono adresu',
        7 => 'połączenie odrzucone',
        28 => 'brak odpowiedzi w czasie',
        35 => 'błąd TLS',
        52 => 'pusta odpowiedź',
        56 => 'zerwane połączenie'
    ];

    // settings that inc/config.php may change
    function diagSetting($name, $default)
    {
        authConfigured();

        return defined($name) ? constant($name) : $default;
    }

    // where the server answers itself: Cloudflare talks plain http to it
    function diagLocalOrigin()
    {
        return diagSetting('DIAG_LOCAL_ORIGIN', 'http://127.0.0.1');
    }

    function diagAccessLog()
    {
        return diagSetting('DIAG_ACCESS_LOG', '/var/log/nginx/sanakan-access.log');
    }

    function diagSlowLog()
    {
        return diagSetting('DIAG_SLOW_LOG', '/var/log/php8.1-fpm.slow.log');
    }

    function diagDir()
    {
        $dir = dataDir() . '/diag';
        if (!is_dir($dir))
            @mkdir($dir, 0750, true);

        return $dir;
    }

    function diagDayFile($time)
    {
        return diagDir() . '/' . date('Y-m-d', $time) . '.jsonl';
    }

    function diagAvailable()
    {
        return function_exists('curl_multi_init');
    }

    // ---- One round ------------------------------------------------------------

    // [name => [url, curl options, timeout]] of one round; the local ones go to
    // DIAG_LOCAL_ORIGIN with the site's name, so nginx picks the site's server block
    function diagRequests($stamp)
    {
        $local = parse_url(diagLocalOrigin());
        $scheme = $local['scheme'] ?? 'http';
        $port = $local['port'] ?? ($scheme === 'https' ? 443 : 80);
        $host = parse_url(DIAG_SITE, PHP_URL_HOST);
        $base = $scheme . '://' . $host . ':' . $port;
        $localOptions = [
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . ($local['host'] ?? '127.0.0.1')],
            // a new connection each time, so every probe tests that nginx takes one
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FORBID_REUSE => true,
            // the server's certificate may be one only Cloudflare trusts
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ];

        // a query Cloudflare has never seen, so it asks the server and not its cache
        $query = '?diag=' . $stamp;

        return [
            'pub' => [DIAG_SITE . '/robots.txt' . $query, [], DIAG_TIMEOUT_PUBLIC],
            'pubphp' => [DIAG_SITE . '/status.php' . $query, [], DIAG_TIMEOUT_PUBLIC],
            'loc' => [$base . '/robots.txt' . $query, $localOptions, DIAG_TIMEOUT_LOCAL],
            'locphp' => [$base . '/status.php' . $query, $localOptions, DIAG_TIMEOUT_LOCAL],
            'wiki' => [DIAG_WIKI . '/robots.txt' . $query, [], DIAG_TIMEOUT_PUBLIC],
            'nginx' => [$base . '/nginx-status', $localOptions, 5],
            'fpm' => [$base . '/fpm-status?json', $localOptions, 5]
        ];
    }

    // a key for a curl handle: an object since PHP 8, a resource before
    function diagHandleId($curl)
    {
        return is_object($curl) ? spl_object_id($curl) : (int)$curl;
    }

    function diagCurl($url, $options, $timeout)
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_USERAGENT => DIAG_USER_AGENT
        ]);

        return $curl;
    }

    // the kernel's TCP state: dropped connections (counters since boot), the
    // accept queue of the web ports, half-open and open connections to them
    function diagTcp()
    {
        $tcp = [];
        $lines = @file('/proc/net/netstat', FILE_IGNORE_NEW_LINES) ?: [];
        for ($i = 0; $i + 1 < count($lines); $i += 2) {
            if (strpos($lines[$i], 'TcpExt:') !== 0)
                continue;
            $counters = array_combine(preg_split('/\s+/', trim($lines[$i])), preg_split('/\s+/', trim($lines[$i + 1])));
            // ListenOverflows: the accept queue was full; ListenDrops: dropped for any reason
            $tcp['lo'] = (int)($counters['ListenOverflows'] ?? 0);
            $tcp['ld'] = (int)($counters['ListenDrops'] ?? 0);
            $tcp['sc'] = (int)($counters['SyncookiesSent'] ?? 0);
        }

        // st 0A listening (rx = connections waiting for nginx to accept them),
        // 03 half-open (SYN received), 01 open
        $queue = 0;
        $syn = 0;
        $open = 0;
        foreach (['/proc/net/tcp', '/proc/net/tcp6'] as $file) {
            $handle = @fopen($file, 'r');
            if ($handle === false)
                continue;
            fgets($handle);
            while (($line = fgets($handle)) !== false) {
                $f = preg_split('/\s+/', trim($line));
                if (count($f) < 5)
                    continue;
                $port = hexdec(substr($f[1], strrpos($f[1], ':') + 1));
                if ($port !== 80 && $port !== 443)
                    continue;
                if ($f[3] === '0A')
                    $queue = max($queue, hexdec(substr($f[4], strpos($f[4], ':') + 1)));
                else if ($f[3] === '03')
                    $syn++;
                else if ($f[3] === '01')
                    $open++;
            }
            fclose($handle);
        }
        $tcp += ['q' => $queue, 'syn' => $syn, 'est' => $open];

        // a full connection tracking table drops new connections too
        $count = @file_get_contents('/proc/sys/net/netfilter/nf_conntrack_count');
        $max = @file_get_contents('/proc/sys/net/netfilter/nf_conntrack_max');
        if ($count !== false && $max !== false)
            $tcp += ['ct' => (int)$count, 'ctm' => (int)$max];

        return $tcp;
    }

    // stub_status of nginx: open connections, accepted and handled since start
    // (fewer handled means worker_connections ran out), reading, writing, waiting
    function diagParseNginx($body)
    {
        if (!preg_match('/Active connections:\s*(\d+).*?(\d+)\s+(\d+)\s+(\d+)\s*Reading:\s*(\d+)\s*Writing:\s*(\d+)\s*Waiting:\s*(\d+)/s', (string)$body, $m))
            return null;

        return ['a' => (int)$m[1], 'acc' => (int)$m[2], 'hnd' => (int)$m[3], 'r' => (int)$m[5], 'w' => (int)$m[6], 'k' => (int)$m[7]];
    }

    // the pool's status page: busy and idle workers, requests waiting for one,
    // how often all were busy and how many requests ran slow (since start)
    function diagParseFpm($body)
    {
        $s = json_decode((string)$body, true);
        if (!is_array($s) || !isset($s['active processes']))
            return null;

        return [
            'act' => (int)$s['active processes'],
            'tot' => (int)$s['total processes'],
            'q' => (int)($s['listen queue'] ?? 0),
            'mcr' => (int)($s['max children reached'] ?? 0),
            'slow' => (int)($s['slow requests'] ?? 0)
        ];
    }

    // the path of a request without its query, cut to a length a list can show
    function diagPath($uri)
    {
        $path = (string)parse_url((string)$uri, PHP_URL_PATH);

        return cutText($path === '' ? (string)$uri : $path, 100);
    }

    // The requests logged since the round before: count, PHP ones (they keep a
    // pool worker busy), 4xx and 5xx, the slowest in ms, the addresses with
    // most requests as [ip, requests, PHP, country, user agent, top path] and
    // the most asked paths as [path, requests]. null when the log cannot be read.
    function diagReadLog()
    {
        $file = diagAccessLog();
        $handle = @fopen($file, 'r');
        if ($handle === false)
            return null;
        $lock = @fopen(diagDir() . '/log.lock', 'c');
        if ($lock !== false)
            flock($lock, LOCK_EX);

        $stat = fstat($handle);
        $stateFile = diagDir() . '/log-state.json';
        $state = json_decode((string)@file_get_contents($stateFile), true);
        if (!is_array($state))
            $pos = $stat['size'];  // the first time only what comes from now on
        else if (($state['ino'] ?? null) !== $stat['ino'] || $state['pos'] > $stat['size'])
            $pos = 0;  // logrotate made a new file
        else
            $pos = (int)$state['pos'];
        $skipped = max(0, $stat['size'] - $pos - DIAG_LOG_READ_MAX);
        $pos += $skipped;

        fseek($handle, $pos);
        $chunk = $stat['size'] > $pos ? (string)fread($handle, $stat['size'] - $pos) : '';
        fclose($handle);
        $end = strrpos($chunk, "\n");
        $chunk = $end === false ? '' : substr($chunk, 0, $end);
        // a cut first line after a skip is dropped
        if ($skipped && ($start = strpos($chunk, "\n")) !== false)
            $chunk = substr($chunk, $start + 1);
        botWriteFile($stateFile, json_encode(['ino' => $stat['ino'], 'pos' => $pos + ($end === false ? 0 : $end + 1)]));
        if ($lock !== false)
            fclose($lock);

        $stats = ['n' => 0, 'php' => 0, 's4' => 0, 's5' => 0, 'rt' => 0, 'ips' => [], 'paths' => []];
        $ips = [];
        $paths = [];
        foreach ($chunk === '' ? [] : explode("\n", $chunk) as $line) {
            $r = json_decode($line, true);
            if (!is_array($r) || ($r['ua'] ?? '') === DIAG_USER_AGENT)
                continue;
            $status = (int)($r['s'] ?? 0);
            $php = ($r['ut'] ?? '') !== '' && ($r['ut'] ?? '') !== '-';
            $path = diagPath($r['u'] ?? '');
            $ip = (string)($r['ip'] ?? '?');

            $stats['n']++;
            $stats['php'] += (int)$php;
            $stats['s4'] += (int)($status >= 400 && $status < 500);
            $stats['s5'] += (int)($status >= 500);
            $stats['rt'] = max($stats['rt'], (int)round(1000 * (float)($r['rt'] ?? 0)));
            $paths[$path] = ($paths[$path] ?? 0) + 1;

            $ips[$ip] = $ips[$ip] ?? ['n' => 0, 'php' => 0, 'cc' => (string)($r['cc'] ?? ''), 'ua' => cutText((string)($r['ua'] ?? ''), 140), 'paths' => []];
            $ips[$ip]['n']++;
            $ips[$ip]['php'] += (int)$php;
            $ips[$ip]['paths'][$path] = ($ips[$ip]['paths'][$path] ?? 0) + 1;
        }

        uasort($ips, function ($a, $b) { return $b['n'] <=> $a['n']; });
        foreach (array_slice($ips, 0, DIAG_TOP, true) as $ip => $info) {
            arsort($info['paths']);
            $stats['ips'][] = [(string)$ip, $info['n'], $info['php'], $info['cc'], $info['ua'], (string)key($info['paths'])];
        }
        arsort($paths);
        foreach (array_slice($paths, 0, DIAG_TOP, true) as $path => $n)
            $stats['paths'][] = [(string)$path, $n];
        if ($skipped)
            $stats['skipped'] = $skipped;

        return $stats;
    }

    // A minute of rounds, one every DIAG_INTERVAL seconds, each written once all
    // its probes have answered or given up. The probes of one round run at the
    // same time, and a new round does not wait for a slow one before it. The
    // last round may end after the next cron run has started; both only append.
    function diagRun()
    {
        $multi = curl_multi_init();
        $rounds = [];
        $owners = [];
        $launched = 0;
        $next = microtime(true);

        while (true) {
            if ($launched < DIAG_ROUNDS && microtime(true) >= $next) {
                $now = time();
                $round = ['t' => $now, 'p' => [], 'pending' => 0];
                foreach (diagRequests($now) as $name => [$url, $options, $timeout]) {
                    $curl = diagCurl($url, $options, $timeout);
                    curl_multi_add_handle($multi, $curl);
                    $owners[diagHandleId($curl)] = [$launched, $name, $curl];
                    $round['pending']++;
                }
                // taken while the probes are on their way, what the server is going through
                $round['tcp'] = diagTcp();
                $load = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;
                $round['ld'] = is_array($load) ? round($load[0], 2) : null;
                $memory = systemMemory();
                $round['mem'] = $memory ? (int)round($memory['available'] / 1048576) : null;
                $round['log'] = diagReadLog();
                $rounds[$launched++] = $round;
                $next += DIAG_INTERVAL;
            }
            if (!$owners) {
                if ($launched >= DIAG_ROUNDS)
                    break;
                usleep((int)max(10000, 1000000 * ($next - microtime(true))));
                continue;
            }

            curl_multi_exec($multi, $running);
            while ($info = curl_multi_info_read($multi)) {
                [$index, $name, $curl] = $owners[diagHandleId($info['handle'])];
                unset($owners[diagHandleId($info['handle'])]);
                $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                $ms = (int)round(1000 * curl_getinfo($curl, CURLINFO_TOTAL_TIME));
                $body = curl_multi_getcontent($curl);
                curl_multi_remove_handle($multi, $curl);
                curl_close($curl);

                $result = [$status, $ms];
                if ($info['result'] !== CURLE_OK)
                    $result[] = $info['result'];
                $rounds[$index]['p'][$name] = $result;
                if ($name === 'nginx')
                    $rounds[$index]['ng'] = $status === 200 ? diagParseNginx($body) : null;
                else if ($name === 'fpm')
                    $rounds[$index]['fpm'] = $status === 200 ? diagParseFpm($body) : null;

                if (--$rounds[$index]['pending'] === 0) {
                    $round = $rounds[$index];
                    unset($round['pending'], $rounds[$index]);
                    @file_put_contents(diagDayFile($round['t']), json_encode($round, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
                }
            }
            // -1 right away on some systems, which would spin
            if (curl_multi_select($multi, 0.2) === -1)
                usleep(100000);
        }
        curl_multi_close($multi);

        diagPrune();
    }

    // drops the days older than DIAG_KEEP_DAYS
    function diagPrune()
    {
        $oldest = date('Y-m-d', time() - DIAG_KEEP_DAYS * 86400);
        foreach (glob(diagDir() . '/*.jsonl') ?: [] as $file)
            if (basename($file, '.jsonl') < $oldest)
                @unlink($file);
    }

    // ---- Reading back ---------------------------------------------------------

    // the rounds since $since, oldest first
    function diagRounds($since)
    {
        $rounds = [];
        for ($day = strtotime(date('Y-m-d', $since)); $day <= time(); $day += 86400) {
            $lines = @file(diagDayFile($day), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $round = json_decode($line, true);
                if (is_array($round) && ($round['t'] ?? 0) >= $since)
                    $rounds[] = $round;
            }
        }
        usort($rounds, function ($a, $b) { return $a['t'] <=> $b['t']; });

        return $rounds;
    }

    // ok, slow or fail for one probe of a round, null when it was not asked
    function diagProbeState($probe)
    {
        if (!is_array($probe))
            return null;
        if ($probe[0] === 0 || $probe[0] >= 500)
            return 'fail';

        return $probe[1] >= DIAG_SLOW_MS ? 'slow' : 'ok';
    }

    // what went wrong with a probe, e.g. "522: Cloudflare nie połączył się z serwerem"
    function diagProbeText($probe)
    {
        if ($probe[0] === 0) {
            $error = $probe[2] ?? null;

            return 'bez odpowiedzi' . ($error !== null ? ': ' . (DIAG_CURL_ERRORS[$error] ?? 'błąd curl ' . $error) : '');
        }
        if ($probe[0] >= 500)
            return $probe[0] . (isset(DIAG_STATUS_LABELS[$probe[0]]) ? ': ' . DIAG_STATUS_LABELS[$probe[0]] : '');

        return $probe[0] . ', ' . milliseconds($probe[1]);
    }

    // a round where the site failed through Cloudflare or on the server
    function diagRoundFailed($round)
    {
        foreach (['pub', 'pubphp', 'loc', 'locphp'] as $name)
            if (diagProbeState($round['p'][$name] ?? null) === 'fail')
                return true;

        return false;
    }

    // [state, rounds, failed, slow] of the 96 quarter hours of the last 24 h for
    // the probes in $names: fail when any failed, warn when any was slow
    function diagTimeline($rounds, $names)
    {
        $start = (int)(floor(time() / 900) * 900) - 95 * 900;
        $parts = [];
        for ($i = 0; $i < 96; $i++)
            $parts[$i] = ['from' => $start + $i * 900, 'rounds' => 0, 'fail' => 0, 'slow' => 0, 'requests' => 0, 'peak' => 0];

        foreach ($rounds as $round) {
            $i = intdiv($round['t'] - $start, 900);
            if ($i < 0 || $i > 95)
                continue;
            $states = [];
            foreach ($names as $name)
                if (($state = diagProbeState($round['p'][$name] ?? null)) !== null)
                    $states[] = $state;
            if ($states) {
                $parts[$i]['rounds']++;
                $parts[$i]['fail'] += (int)in_array('fail', $states, true);
                $parts[$i]['slow'] += (int)(!in_array('fail', $states, true) && in_array('slow', $states, true));
            }
            $requests = $round['log']['n'] ?? 0;
            $parts[$i]['requests'] += $requests;
            $parts[$i]['peak'] = max($parts[$i]['peak'], $requests);
        }

        foreach ($parts as &$part)
            $part['state'] = !$part['rounds'] ? null : ($part['fail'] ? 'fail' : ($part['slow'] ? 'warn' : 'ok'));

        return $parts;
    }

    // the requests of several rounds together, as diagReadLog gives them for one
    function diagMergeTraffic($rounds, $top = DIAG_TOP)
    {
        $total = ['n' => 0, 'php' => 0, 's4' => 0, 's5' => 0, 'rt' => 0, 'ips' => [], 'paths' => [], 'peak' => 0];
        $ips = [];
        $paths = [];
        foreach ($rounds as $round) {
            $log = $round['log'] ?? null;
            if (!$log)
                continue;
            foreach (['n', 'php', 's4', 's5'] as $key)
                $total[$key] += $log[$key];
            $total['rt'] = max($total['rt'], $log['rt']);
            $total['peak'] = max($total['peak'], $log['n']);
            foreach ($log['ips'] as [$ip, $n, $php, $cc, $ua, $path]) {
                if (!isset($ips[$ip]) || $n > $ips[$ip]['best'])
                    $ips[$ip] = ['cc' => $cc, 'ua' => $ua, 'path' => $path, 'best' => $n] + ($ips[$ip] ?? []);
                $ips[$ip]['n'] = ($ips[$ip]['n'] ?? 0) + $n;
                $ips[$ip]['php'] = ($ips[$ip]['php'] ?? 0) + $php;
            }
            foreach ($log['paths'] as [$path, $n])
                $paths[$path] = ($paths[$path] ?? 0) + $n;
        }
        uasort($ips, function ($a, $b) { return $b['n'] <=> $a['n']; });
        arsort($paths);
        foreach (array_slice($ips, 0, $top, true) as $ip => $info)
            $total['ips'][] = ['ip' => (string)$ip] + $info;
        foreach (array_slice($paths, 0, $top, true) as $path => $n)
            $total['paths'][] = [(string)$path, $n];

        return $total;
    }

    // how much a counter grew from the round before $first to the last one
    function diagGrowth($before, $rounds, $group, $key)
    {
        $from = $before[$group][$key] ?? null;
        $to = null;
        foreach ($rounds as $round)
            if (isset($round[$group][$key]))
                $to = $round[$group][$key];
        if ($from === null) {
            foreach ($rounds as $round)
                if (isset($round[$group][$key])) {
                    $from = $round[$group][$key];
                    break;
                }
        }

        return $from === null || $to === null ? null : max(0, $to - $from);
    }

    // the highest value of a field over the rounds, or null
    function diagPeak($rounds, $group, $key)
    {
        $peak = null;
        foreach ($rounds as $round)
            if (isset($round[$group][$key]))
                $peak = max($peak ?? 0, $round[$group][$key]);

        return $peak;
    }

    // Every run of failed rounds in $rounds, newest first, with what the server
    // went through meanwhile and the traffic of the minute before and during it.
    function diagEpisodes($rounds)
    {
        $episodes = [];
        $current = null;
        foreach ($rounds as $i => $round) {
            if (!diagRoundFailed($round))
                continue;
            // one more failure within two rounds belongs to the same episode
            if ($current !== null && $round['t'] - $rounds[$current['last']]['t'] <= 2 * DIAG_INTERVAL + 5) {
                $current['last'] = $i;
                continue;
            }
            if ($current !== null)
                $episodes[] = $current;
            $current = ['first' => $i, 'last' => $i];
        }
        if ($current !== null)
            $episodes[] = $current;

        $result = [];
        foreach (array_reverse($episodes) as $episode) {
            $during = array_slice($rounds, $episode['first'], $episode['last'] - $episode['first'] + 1);
            $before = $rounds[$episode['first'] - 1] ?? null;
            $from = $during[0]['t'];
            $to = end($during)['t'] + DIAG_INTERVAL;

            // what each probe answered meanwhile: [text => times]
            $answers = [];
            foreach (DIAG_PROBE_NAMES as $name => $label) {
                $answers[$name] = [];
                foreach ($during as $round) {
                    $probe = $round['p'][$name] ?? null;
                    if (is_array($probe)) {
                        $text = diagProbeState($probe) === 'fail' ? diagProbeText($probe) : 'działała';
                        $answers[$name][$text] = ($answers[$name][$text] ?? 0) + 1;
                    }
                }
            }

            // the traffic from a minute before the first failure to the end
            $traffic = array_values(array_filter($rounds, function ($round) use ($from, $to) {
                return $round['t'] >= $from - 60 && $round['t'] < $to;
            }));

            $result[] = [
                'from' => $from,
                'to' => $to,
                'ongoing' => $episode['last'] === count($rounds) - 1 && time() - $to < 2 * DIAG_INTERVAL,
                'answers' => $answers,
                'publicOnly' => diagOnlyPublic($during),
                'phpOnly' => diagOnlyPhp($during),
                'overflows' => diagGrowth($before, $during, 'tcp', 'lo'),
                'drops' => diagGrowth($before, $during, 'tcp', 'ld'),
                'queue' => diagPeak($during, 'tcp', 'q'),
                'syn' => diagPeak($during, 'tcp', 'syn'),
                'nginxActive' => diagPeak($during, 'ng', 'a'),
                'nginxUnhandled' => diagUnhandled($before, $during),
                'fpmActive' => diagPeak($during, 'fpm', 'act'),
                'fpmTotal' => diagPeak($during, 'fpm', 'tot'),
                'fpmQueue' => diagPeak($during, 'fpm', 'q'),
                'fpmFull' => diagGrowth($before, $during, 'fpm', 'mcr'),
                'load' => max(array_map(function ($round) { return $round['ld'] ?? 0; }, $during)),
                'traffic' => diagMergeTraffic($traffic)
            ];
        }

        return $result;
    }

    // the site failed only through Cloudflare, while on the server it answered
    function diagOnlyPublic($rounds)
    {
        foreach ($rounds as $round)
            foreach (['loc', 'locphp'] as $name)
                if (diagProbeState($round['p'][$name] ?? null) === 'fail')
                    return false;

        return true;
    }

    // on the server the files came but PHP did not
    function diagOnlyPhp($rounds)
    {
        $php = false;
        foreach ($rounds as $round) {
            if (diagProbeState($round['p']['loc'] ?? null) === 'fail')
                return false;
            $php = $php || diagProbeState($round['p']['locphp'] ?? null) === 'fail';
        }

        return $php;
    }

    // connections nginx accepted but could not handle (worker_connections ran out)
    function diagUnhandled($before, $rounds)
    {
        $accepted = diagGrowth($before, $rounds, 'ng', 'acc');
        $handled = diagGrowth($before, $rounds, 'ng', 'hnd');

        return $accepted === null || $handled === null ? null : max(0, $accepted - $handled);
    }

    // the address is one of Cloudflare's: nginx does not take the visitor's own
    function diagIsCloudflare($ip)
    {
        $address = @inet_pton($ip);
        if ($address === false)
            return false;
        foreach (CLOUDFLARE_RANGES as $range) {
            [$net, $bits] = explode('/', $range);
            $net = inet_pton($net);
            if (strlen($net) !== strlen($address))
                continue;
            $bytes = intdiv((int)$bits, 8);
            $rest = (int)$bits % 8;
            if (substr($address, 0, $bytes) !== substr($net, 0, $bytes))
                continue;
            if ($rest === 0)
                return true;
            $mask = (0xFF << (8 - $rest)) & 0xFF;
            if ((ord($address[$bytes]) & $mask) === (ord($net[$bytes]) & $mask))
                return true;
        }

        return false;
    }

    // the last $count entries of PHP-FPM's slow log (a stack of every request
    // that ran longer than request_slowlog_timeout), newest first; null when it
    // cannot be read
    function diagSlowEntries($count = 5)
    {
        $file = diagSlowLog();
        $handle = @fopen($file, 'r');
        if ($handle === false)
            return null;
        $size = filesize($file);
        fseek($handle, max(0, $size - 65536));
        $text = (string)stream_get_contents($handle);
        fclose($handle);

        $entries = preg_split('/\n\s*\n/', trim($text));
        // a cut first entry is dropped
        if ($size > 65536)
            array_shift($entries);

        return array_reverse(array_slice(array_values(array_filter($entries, 'strlen')), -$count));
    }
