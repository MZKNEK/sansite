<?php
    // Bot status for the home page: online, idle or offline.
    // The bot serves the API, so getting the command list back means it is up.
    // The API is asked at most once a minute however many people visit, and
    // every check goes to a 24 h history. A bot that is up now but was
    // available in less than 99% of the checks shows as idle.
    const CACHE_TTL = 60;
    const HISTORY_SPAN = 86400;
    const MIN_UPTIME = 99.0;

    $cacheFile = sys_get_temp_dir() . '/sanakan-status.json';
    $historyFile = sys_get_temp_dir() . '/sanakan-status-history.txt';

    function checkBot()
    {
        $opts = [
            "http" => [
                "method" => "GET",
                "timeout" => 5
            ]
        ];
        $context = stream_context_create($opts);
        $data = @json_decode(@file_get_contents('https://api.sanakan.pl/api/Info/commands', false, $context), true);

        return !empty($data['modules']);
    }

    // Adds the check to the history (one "time state" line per check, older
    // than 24 h dropped) and returns the percent of online checks in it.
    function recordCheck($file, $online, $now)
    {
        $history = [];
        $fp = @fopen($file, 'c+');
        $locked = $fp !== false && flock($fp, LOCK_EX);

        if ($locked) {
            while (($line = fgets($fp)) !== false) {
                $parts = explode(' ', trim($line));
                if (count($parts) == 2 && (int)$parts[0] > $now - HISTORY_SPAN)
                    $history[] = [(int)$parts[0], $parts[1] === '1'];
            }
        }

        $history[] = [$now, $online];

        if ($locked) {
            ftruncate($fp, 0);
            rewind($fp);
            foreach ($history as $entry)
                fwrite($fp, $entry[0] . ' ' . ($entry[1] ? '1' : '0') . "\n");
            flock($fp, LOCK_UN);
        }
        if ($fp !== false)
            fclose($fp);

        $up = 0;
        foreach ($history as $entry)
            if ($entry[1])
                $up++;

        return 100 * $up / count($history);
    }

    $now = time();
    $status = null;
    if (is_file($cacheFile) && $now - filemtime($cacheFile) < CACHE_TTL)
        $status = @json_decode(@file_get_contents($cacheFile), true);

    if (!is_array($status) || !isset($status['status'], $status['uptime'], $status['checked'])) {
        $online = checkBot();
        $uptime = recordCheck($historyFile, $online, $now);

        if (!$online)
            $state = 'offline';
        else if ($uptime < MIN_UPTIME)
            $state = 'idle';
        else
            $state = 'online';

        $status = [
            'status' => $state,
            // rounded down, so 98.96 is not shown as 99 next to the idle status
            'uptime' => floor($uptime * 10) / 10,
            'checked' => $now
        ];

        // write a temp file and rename it, so a parallel request never reads half a file
        $tmp = $cacheFile . '.' . getmypid();
        if (@file_put_contents($tmp, json_encode($status)) !== false)
            @rename($tmp, $cacheFile);
    }

    // browsers keep the answer until the server cache expires
    $maxAge = max(0, CACHE_TTL - ($now - $status['checked']));
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=' . $maxAge);

    echo json_encode([
        'status' => $status['status'],
        'uptime' => $status['uptime']
    ]);
