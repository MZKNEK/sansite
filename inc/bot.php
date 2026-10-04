<?php
    // Shared access to the bot API, used by status.php and cmd/index.php.
    // The bot serves the API, so getting the command list back means it is up.
    // The API is asked at most once a minute however many people visit, every
    // check goes to a 24 h history, and the last command list the bot returned
    // is kept, so the commands page still works while the bot is down.
    // A bot that is up now but answered less than 99% of the checks is idle.
    const BOT_API_URL = 'https://api.sanakan.pl/api/Info/commands';
    const BOT_CACHE_TTL = 60;
    const BOT_HISTORY_SPAN = 86400;
    const BOT_MIN_UPTIME = 99.0;

    function botFile($name)
    {
        return sys_get_temp_dir() . '/sanakan-' . $name;
    }

    // writes a temp file and renames it, so a parallel request never reads half a file
    function botWriteFile($file, $content)
    {
        $tmp = $file . '.' . getmypid();
        if (@file_put_contents($tmp, $content) !== false)
            @rename($tmp, $file);
    }

    // Adds the check to the history (one "time state" line per check, older
    // than 24 h dropped) and returns the percent of online checks in it.
    function botRecordCheck($online, $now)
    {
        $history = [];
        $fp = @fopen(botFile('status-history.txt'), 'c+');
        $locked = $fp !== false && flock($fp, LOCK_EX);

        if ($locked) {
            while (($line = fgets($fp)) !== false) {
                $parts = explode(' ', trim($line));
                if (count($parts) == 2 && (int)$parts[0] > $now - BOT_HISTORY_SPAN)
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

    // the checks of the last 24 h as [time, online], oldest first
    function botHistory()
    {
        $history = [];
        $lines = @file(botFile('status-history.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $parts = explode(' ', trim($line));
            if (count($parts) == 2 && (int)$parts[0] > time() - BOT_HISTORY_SPAN)
                $history[] = [(int)$parts[0], $parts[1] === '1'];
        }

        return $history;
    }

    // ['status' => online|idle|offline, 'uptime' => percent, 'checked' => time],
    // asks the API only when the cached state is older than a minute
    function botState()
    {
        static $state = null;
        if ($state !== null)
            return $state;

        $now = time();
        $file = botFile('status.json');
        if (is_file($file) && $now - filemtime($file) < BOT_CACHE_TTL)
            $state = @json_decode(@file_get_contents($file), true);

        if (!is_array($state) || !isset($state['status'], $state['uptime'], $state['checked'])) {
            $opts = [
                "http" => [
                    "method" => "GET",
                    "timeout" => 5
                ]
            ];
            $context = stream_context_create($opts);
            $json = @file_get_contents(BOT_API_URL, false, $context);
            $data = $json === false ? null : @json_decode($json, true);

            $online = !empty($data['modules']);
            if ($online)
                botWriteFile(botFile('commands.json'), $json);

            $uptime = botRecordCheck($online, $now);

            if (!$online)
                $status = 'offline';
            else if ($uptime < BOT_MIN_UPTIME)
                $status = 'idle';
            else
                $status = 'online';

            $state = [
                'status' => $status,
                // rounded down, so 98.96 is not shown as 99 next to the idle status
                'uptime' => floor($uptime * 10) / 10,
                'checked' => $now
            ];
            botWriteFile($file, json_encode($state));
        }

        return $state;
    }

    // the last command list the bot returned (it may be old when the bot is offline), or null
    function botCommands()
    {
        botState();

        $file = botFile('commands.json');
        $data = is_file($file) ? @json_decode(@file_get_contents($file), true) : null;

        return is_array($data) ? $data : null;
    }
