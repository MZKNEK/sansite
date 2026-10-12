<?php
    // Where the accounts log in from: their addresses and devices. Part of the
    // login, loaded by inc/auth.php.

    // ---- Addresses of the accounts ------------------------------------------------
    // Which addresses a logged-in account comes from, so the panel can tell whose
    // an address in the traffic is and never blocks its own admins. Kept in
    // inc/data/addresses.json as [id => [ip => [first, last, times, country, user agent]]].

    // Notes the address of the logged-in account, and the session as one of its
    // devices, at most every ADDRESS_NOTE_EVERY seconds per session and address.
    function noteAccountAddress($id)
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $seen = $_SESSION['address_seen'] ?? null;
        if ($ip === '' || (is_array($seen) && $seen[0] === $ip && time() - $seen[1] < ADDRESS_NOTE_EVERY))
            return;
        $_SESSION['address_seen'] = [$ip, time()];

        $now = time();
        $id = (string)$id;
        $cc = cutText((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''), 2);
        $agent = cutText((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 160);

        updateDataFile('addresses.json', function ($data) use ($id, $ip, $now, $cc, $agent) {
            $known = $data[$id][$ip] ?? [$now, $now, 0, '', ''];
            $data[$id][$ip] = [$known[0], $now, $known[2] + 1, $cc !== '' ? $cc : $known[3], $agent !== '' ? $agent : $known[4]];

            // only the recent ones, the newest first
            foreach ($data as $account => $addresses) {
                $addresses = array_filter($addresses, function ($address) use ($now) { return $address[1] >= $now - ADDRESS_KEEP_DAYS * 86400; });
                uasort($addresses, function ($a, $b) { return $b[1] <=> $a[1]; });
                $data[$account] = array_slice($addresses, 0, ADDRESS_PER_ACCOUNT, true);
                if (!$data[$account])
                    unset($data[$account]);
            }

            return $data;
        });

        // [id => [session key => [logged in, last seen, ip, country, user agent,
        // generation (sessionGeneration())]]]
        $key = sessionKey(session_id());
        $login = (int)($_SESSION['login_time'] ?? $now);
        $generation = $_SESSION['generation'] ?? null;
        updateDataFile('devices.json', function ($data) use ($id, $key, $login, $ip, $now, $cc, $agent, $generation) {
            $data[$id][$key] = [$login, $now, $ip, $cc, $agent, $generation];
            foreach ($data as $account => $devices) {
                $devices = array_filter($devices, function ($device) use ($now) { return $device[1] >= $now - SESSION_DAYS * 86400; });
                uasort($devices, function ($a, $b) { return $b[1] <=> $a[1]; });
                $data[$account] = array_slice($devices, 0, DEVICES_PER_ACCOUNT, true);
                if (!$data[$account])
                    unset($data[$account]);
            }

            return $data;
        });
    }

    // A session in the list of devices: a hash of its ID, never the ID itself,
    // which would let anyone holding inc/data (a backup) log in as that account.
    function sessionKey($sessionId)
    {
        return substr(hash('sha256', (string)$sessionId), 0, 20);
    }

    // The devices an account is still logged in on, the latest first, as
    // [key => [logged in, last seen, ip, country, user agent, generation]]: their
    // session is still on the server, younger than SESSION_DAYS and of the
    // generation of now (one noted before the generations: none of the "log
    // out" came after the login).
    function accountDevices($id)
    {
        $devices = readData('devices')[(string)$id] ?? [];
        $files = sessionFiles();
        $generation = sessionGeneration($id);
        $since = max(sessionsValidSince(), accountSessionsSince($id));
        $oldest = time() - SESSION_DAYS * 86400;

        return array_filter($devices, function ($device, $key) use ($files, $generation, $since, $oldest) {
            return isset($files[$key]) && $device[0] >= $oldest
                && (isset($device[5]) ? $device[5] === $generation : $device[0] >= $since);
        }, ARRAY_FILTER_USE_BOTH);
    }

    // the session files of the site by their key: [key => path]
    function sessionFiles()
    {
        $files = [];
        foreach (glob(dataDir() . '/sessions/sess_*') ?: [] as $file)
            $files[sessionKey(substr(basename($file), 5))] = $file;

        return $files;
    }

    // "Firefox 130 · Windows" from a user agent
    function deviceName($agent)
    {
        $browser = 'przeglądarka';
        foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'Version' => 'Safari'] as $token => $name)
            if (preg_match('~' . $token . '/(\d+)~', $agent, $match)) {
                $browser = $name . ' ' . $match[1];
                break;
            }
        $system = '';
        foreach (['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS X' => 'macOS', 'Linux' => 'Linux'] as $token => $name)
            if (strpos($agent, $token) !== false) {
                $system = $name;
                break;
            }

        return $agent === '' ? 'nieznane urządzenie' : $browser . ($system !== '' ? ' · ' . $system : '');
    }

    // ends one session of an account, by its key: whether there was one
    function endDevice($id, $key)
    {
        $file = sessionFiles()[$key] ?? null;
        $ended = $file !== null && @unlink($file);
        updateDataFile('devices.json', function ($data) use ($id, $key) {
            unset($data[(string)$id][$key]);
            if (empty($data[(string)$id]))
                unset($data[(string)$id]);

            return $data;
        });

        return $ended;
    }

    // The two addresses are one: the same IPv4 address, or the same /64 of IPv6,
    // which one machine usually has to itself (and Cloudflare blocks whole).
    function sameAddress($a, $b)
    {
        $a = @inet_pton((string)$a);
        $b = @inet_pton((string)$b);
        if ($a === false || $b === false || strlen($a) !== strlen($b))
            return false;

        return strlen($a) === 4 ? $a === $b : substr($a, 0, 8) === substr($b, 0, 8);
    }

    // the accounts that came from this address (or its /64), as [id => [ip, last seen]]
    function accountsAtAddress($ip, $addresses)
    {
        $accounts = [];
        foreach ($addresses as $id => $known)
            foreach ($known as $address => $info)
                if (sameAddress($ip, $address) && ($info[1] > ($accounts[(string)$id][1] ?? 0)))
                    $accounts[(string)$id] = [(string)$address, $info[1]];

        return $accounts;
    }
