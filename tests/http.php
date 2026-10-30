<?php
    // HTTP smoke test: starts the site with `php -S` and a canned bot API on a
    // second server (tests/bot-stub.php), then asks every page for its status and
    // that its body has no PHP error. The data folder is pointed at a temporary
    // place, so the run needs no network and touches nothing on disk. It is not
    // sent to the server (tests/ is export-ignore); CI runs it after the unit
    // tests.
    //   php tests/http.php

    function rmtree($path)
    {
        if (is_link($path) || is_file($path))
            return @unlink($path);
        foreach (@scandir($path) ?: [] as $name)
            if ($name !== '.' && $name !== '..')
                rmtree($path . '/' . $name);

        return @rmdir($path);
    }

    // one GET: [status, body, header lines]; $accept as a browser or a script sends it
    function get($url, $accept = '*/*')
    {
        $fp = @fopen($url, 'r', false, stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true, 'header' => 'Accept: ' . $accept]]));
        if ($fp === false)
            return [0, '', []];
        $body = stream_get_contents($fp);
        // as httpRaw() does it ($http_response_header is deprecated from PHP 8.5)
        $headers = stream_get_meta_data($fp)['wrapper_data'] ?? [];
        fclose($fp);
        $status = 0;
        foreach (is_array($headers) ? $headers : [] as $line)
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $match))
                $status = (int)$match[1];

        return [$status, (string)$body, is_array($headers) ? $headers : []];
    }

    // Starts php -S with a router; returns the process resource or null. The
    // command is an array, so it is opened directly and Windows' shell quoting is
    // not in the way; the server's own output goes to the null device, since
    // reading its pipe blocks on Windows.
    function startServer($php, $port, $router, $cwd, $env)
    {
        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $server = proc_open(
            // every notice shown in the page, so a deprecated call fails it too
            [$php, '-d', 'error_reporting=-1', '-d', 'display_errors=1', '-S', '127.0.0.1:' . $port, $router],
            [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes, $cwd, $env
        );

        return is_resource($server) ? $server : null;
    }

    $root = str_replace('\\', '/', dirname(__DIR__));
    $data = sys_get_temp_dir() . '/sanakan-smoke-' . getmypid() . '-' . bin2hex(random_bytes(3));
    mkdir($data, 0700, true);
    // the moderator and debug commands, as the bot check keeps them (inc/bot.php)
    file_put_contents($data . '/bot-commands-private.json', json_encode(['modules' => [['name' => 'Mod', 'subModules' => [['prefix' => 'm',
        'commands' => [['name' => 'ban', 'description' => 'mod', 'aliases' => ['ban'], 'attributes' => [], 'example' => '']]]]]]]));

    // two different ports, so the site and its canned API never clash
    $apiPort = random_int(20000, 30000);
    $sitePort = random_int(30001, 40000);
    $api = startServer(PHP_BINARY, $apiPort, $root . '/tests/bot-stub.php', $root, null);
    $site = startServer(PHP_BINARY, $sitePort, $root . '/tests/smoke-router.php', $root,
        array_merge(getenv() ?: [], ['SANAKAN_SMOKE_DATA' => $data, 'SANAKAN_SMOKE_API' => 'http://127.0.0.1:' . $apiPort]));

    $stop = function () use (&$api, &$site) {
        foreach ([$api, $site] as $server)
            if (is_resource($server)) {
                proc_terminate($server);
                proc_close($server);
            }
    };

    if ($api === null || $site === null) {
        $stop();
        rmtree($data);
        fwrite(STDERR, "Nie można uruchomić php -S.\n");
        exit(1);
    }

    $base = 'http://127.0.0.1:' . $sitePort;
    $up = false;
    for ($i = 0; $i < 50 && !$up; $i++) {
        [$status] = get($base . '/status.php');
        $up = $status !== 0;
        if (!$up)
            usleep(100000);
    }
    if (!$up) {
        $stop();
        rmtree($data);
        fwrite(STDERR, "Serwer nie wystartował.\n");
        exit(1);
    }

    // [path, accepted statuses, optionally a header line the answer must have]
    $pages = [
        ['/', [200]],
        ['/404.html', [200]],
        ['/403.html', [200]],
        ['/401.html', [200]],
        ['/500.html', [200]],
        ['/privacy/', [200]],
        ['/terms/', [200]],
        ['/og.php?p=terms', [200, 302]],
        ['/status.php', [200]],
        ['/palette.php', [200]],
        ['/megastructure.php', [200]],
        ['/offline.html', [200]],
        ['/sw.js', [200]],
        ['/account.php?finds', [200], 'Cache-Control: no-store, private'],
        ['/state/', [200]],
        ['/state/versions/', [200]],
        // the old address, followed to the new one
        ['/state/wersje/?v=1.0', [200], 'Location: ../versions/?v=1.0'],
        ['/state/og.php', [200, 302]],
        ['/hunters/', [200]],
        ['/cmd/', [200]],
        ['/cmd/changes/', [200]],
        ['/cmd/zmiany/', [200], 'Location: ../changes/'],
        // a short link to a command: on to the page, or for a moderator one a
        // login, here (no Discord login set up) a refusal
        ['/cmd/daily', [200], 'Location: /cmd/#daily'],
        ['/cmd/nic-takiego', [200], 'Location: /cmd/#nic-takiego'],
        ['/cmd/m-ban', [403]],
        ['/cmd/ban', [403]],
        ['/skalpel/', [200]],
        ['/uskalpel/', [200]],
        ['/profilator/', [200]],
        ['/og.php?p=cmd', [200, 302]],
        ['/og.php?p=versions', [200, 302]],
        ['/og.php?p=wersje', [200, 302]],
        ['/og.php?p=versions&v=1.0', [200, 302]],
        ['/og.php?p=skalpel', [200, 302]],
        ['/og.php?p=uskalpel', [200, 302]],
        ['/i/', [200, 503]],
        ['/i/?pick', [401]],
        // a thumbnail depends on the session, so no cache may keep it, a refusal neither
        ['/i/?thumb=a.png&v=1&c=2', [403, 404], 'Cache-Control: private, no-store'],
        ['/account/', [200, 503]],
        ['/auth/discord/', [503], 'Cache-Control: no-store, private'],
        ['/admin/', [200, 503]],
        ['/api/', [200, 503]],
        ['/inc/data/access.json', [403]],
        // the failure the Safeguard's terminal stages: the 500 page
        ['/megastructure.php?awaria', [500]],
    ];

    $failures = 0;
    foreach ($pages as $page) {
        [$path, $ok] = $page;
        [$status, $body, $headers] = get($base . $path);
        $error = (bool)preg_match('~Fatal error|Parse error|Uncaught|Deprecated:~', $body);
        $missing = isset($page[2]) && !in_array(strtolower($page[2]), array_map('strtolower', $headers), true);
        if (!in_array($status, $ok, true) || $error || $missing) {
            $failures++;
            echo 'FAIL ', $path, ' -> ', $status, ($error ? ' (PHP error)' : ''), ($missing ? ' (no "' . $page[2] . '")' : ''), "\n", substr($body, 0, 300), "\n\n";
        } else {
            echo 'ok   ', $path, ' (', $status, ")\n";
        }
    }

    // a page that fails, by an exception or by a fatal error of PHP: a browser
    // gets the site's 500 page instead of what the page had written so far, a
    // script the JSON it reads (tests/smoke-router.php makes them fail)
    foreach (['/__exception', '/__fatal'] as $path)
        foreach (['text/html,application/xhtml+xml,*/*;q=0.8' => 'Awaria Megastruktury', '*/*' => '"ok":false'] as $accept => $expected) {
            [$status, $body] = get($base . $path, $accept);
            if ($status !== 500 || strpos($body, $expected) === false || strpos($body, 'half a page') !== false) {
                $failures++;
                echo 'FAIL ', $path, ' (Accept: ', $accept, ') -> ', $status, "
", substr($body, 0, 300), "

";
            } else {
                echo 'ok   ', $path, ' (Accept: ', $accept, ', ', $status, ")
";
            }
        }

    require __DIR__ . '/megastructure-http.php';

    $stop();
    rmtree($data);

    if ($failures) {
        fwrite(STDERR, $failures . " stron nie przeszło\n");
        exit(1);
    }
    fwrite(STDOUT, "HTTP smoke OK\n");
