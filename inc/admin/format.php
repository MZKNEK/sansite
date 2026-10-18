<?php
    // What the panel's pages write: labels, times, numbers, meters, the cells of
    // the accounts and their rights. Part of the panel, loaded by admin/index.php.

    const LIST_LABELS = [
        'galleryAdmins' => 'administratorzy galerii',
        'galleryViewers' => 'oglądający galerię',
        'galleryUploaders' => 'zablokowany własny folder w galerii',
        'galleryPrivate' => 'prywatny folder w galerii',
        'apiViewers' => 'dostęp do API',
        'vipAccounts' => 'LV.2 VIP'
    ];

    // title and description of the card of each list
    const LIST_CARDS = [
        'galleryAdmins' => ['Administratorzy galerii', 'Oglądają galerię i dodają, przenoszą oraz usuwają pliki.'],
        'galleryViewers' => ['Oglądający galerię', 'Tylko oglądają galerię.'],
        'galleryUploaders' => ['Zablokowane własne foldery', 'Każde konto z rolą na serwerze bota (od user w górę), także administratorzy galerii, ma własny folder i/' . USERS_DIR . '/ID-nick: dodaje zdjęcia tylko tam i bez innego dostępu tylko go widzi. Do '
            . USER_FILES_DEFAULT . ' zdjęć, limit zmienia się w profilu konta; każde do ' . USER_FILE_MAX_BYTES / 1048576 . ' MB, razem do ' . USER_TOTAL_MAX_BYTES / 1048576
            . ' MB, zapisywane jako WebP, gdy wychodzi mniejszy; to, co z niego usunie, czeka w koszu do ' . USER_TRASH_MAX_BYTES / 1048576 . ' MB. Konta z tej listy mają go zablokowanego, a konto bez roli go nie ma; folder i pliki zostają.'],
        'galleryPrivate' => ['Prywatny folder galerii', 'Widzą i/' . PRIVATE_DIR . '. Administratorzy panelu zawsze, reszta z tej listy. Pliki nie otwierają się bezpośrednim linkiem.'],
        'apiViewers' => ['Dostęp do API', 'Czytają dokumentację API w api/. Administratorzy panelu mają ją zawsze, a z ról na serwerze bota dev, admin, semi-admin i tester.'],
        'vipAccounts' => ['LV.2 VIP', 'Poziom nadawany tutaj, tylko kontom z rolą na serwerze bota (od user w górę); bez roli przestaje działać, a wpis zostaje. Daje WAP (wap/), którego wyższa rola na serwerze nie zastępuje; administratorzy panelu mają go zawsze. Na odznace widać wyższą rolę, gdy konto ją ma.']
    ];

    // name of an account from the recent logins, or its ID
    function accountLabel($id, $logins)
    {
        return isset($logins[$id]['name']) ? $logins[$id]['name'] . ' (' . $id . ')' : $id;
    }

    function dataError()
    {
        return 'Nie udało się zapisać danych w inc/data. PHP musi mieć tam prawo zapisu, np.: '
            . 'mkdir -p ' . __DIR__ . '/../data && chown www-data:www-data ' . __DIR__ . '/../data';
    }

    // pictures and films a folder in the trash shows at most
    const TRASH_PREVIEW_MAX = 60;
    const REPO_URL = 'https://github.com/MZKNEK/sansite';
    const NOTICE_LENGTH = 300;

    // a time from a datetime-local field ("2026-10-04T22:00", Polish time), or null
    function noticeTime($value)
    {
        $value = trim((string)$value);
        $time = $value === '' ? false : strtotime($value);

        return $time === false ? null : $time;
    }

    // a time for a datetime-local field
    function fieldTime($time)
    {
        return $time ? date('Y-m-d\TH:i', $time) : '';
    }
    const CRON_LATE = 300;

    // share of the whole for the bar behind a row, as a CSS percentage
    function share($part, $whole)
    {
        return $whole > 0 ? round(100 * $part / $whole, 1) . '%' : '0%';
    }

    // a number with a Polish decimal comma, "0,52"
    function decimal($number, $digits = 1)
    {
        return number_format((float)$number, $digits, ',', '');
    }

    // a resource bar of the server card: yellow from 75%, red from 90%
    function meter($title, $part, $whole, $value, $details)
    {
        $percent = $whole > 0 ? 100 * $part / $whole : 0;
        $level = $percent >= 90 ? ' critical' : ($percent >= 75 ? ' high' : '');

        return '<div class="meter' . $level . '">'
            . '<div class="meter-head"><span>' . e($title) . '</span><b>' . $value . '</b></div>'
            . '<div class="meter-bar"><span style="width: ' . share($part, $whole) . '"></span></div>'
            . '<p>' . $details . '</p></div>';
    }

    // The server's clock for the bot card: now in ms, the time zone of the site,
    // the system's own zone and whether NTP keeps the clock right (null when
    // systemd-timesyncd does not run).
    function serverClock()
    {
        $system = trim((string)@file_get_contents('/etc/timezone'));
        if ($system === '' && ($link = @readlink('/etc/localtime')) !== false)
            $system = preg_replace('~^.*zoneinfo/~', '', $link);
        $ntp = is_file('/run/systemd/timesync/synchronized') ? true : (is_dir('/run/systemd/timesync') ? false : null);

        return ['ms' => (int)round(microtime(true) * 1000), 'zone' => date_default_timezone_get(), 'system' => $system, 'ntp' => $ntp];
    }

    // Why an address must not be blocked, or null: it is the one this admin
    // uses now, the bot's, or one an account of the panel came from. $owners from accountsAtAddress().
    function protectedAddress($ip, $owners, $logins)
    {
        if (sameAddress($ip, $_SERVER['REMOTE_ADDR'] ?? ''))
            return 'to twój obecny adres';
        if (botIsAddress($ip))
            return 'to adres bota (z niego przychodzi heartbeat)';
        foreach ($owners as $id => $owner)
            if (realPanelAdminId($id))
                return 'z tego adresu korzysta konto panelu ' . ($logins[$id]['name'] ?? $id);

        return null;
    }

    // what the lists of addresses mark: scanners, blocked in Cloudflare (null when
    // blocking is not set up), the accounts that came from an address
    function diagMarks($scanners, $cfItems)
    {
        return [
            'scanners' => array_map(function ($scanner) { return $scanner['reason']; }, $scanners),
            'blocked' => $cfItems === null ? null : array_column($cfItems, null, 'ip'),
            'addresses' => readData('addresses'),
            'logins' => readData('logins')
        ];
    }

    // the columns of the recent logins: what an account may do on this site
    const ACCESS_COLUMNS = ['gallery' => 'Galeria', 'folder' => 'Folder', 'panel' => 'Panel', 'api' => 'API'];

    // where an account has a right of a list from: "inc/config.php", "panel,
    // 05.10.2026, nadał(a) Sniku: „note”"
    function accessFrom($list, $id, $logins)
    {
        $config = configList(ACCESS_LISTS[$list]);
        if ($config === true)
            return 'każdy zalogowany (inc/config.php)';
        if (in_array($id, $config, true))
            return 'inc/config.php';
        $entry = panelList($list)[$id] ?? null;
        if ($entry === null)
            return '';

        return 'panel' . (!empty($entry['added']) ? ', ' . date('j.m.Y', $entry['added']) : '')
            . (!empty($entry['by']) ? ', nadał(a) ' . ($logins[$entry['by']]['name'] ?? $entry['by']) : '')
            . (($entry['note'] ?? '') !== '' ? ': „' . $entry['note'] . '”' : '');
    }

    // What an account may do, for its row of the recent logins: [cells, details].
    // A cell per ACCESS_COLUMNS as [text, level ('strong': does more than look,
    // 'on', '' for nothing), title]; the details, shown when the row is opened,
    // as [label, text], where each right comes from.
    function accessColumns($id, $logins, $galleryDir, $serverRoles)
    {
        $cells = [];
        $details = [];
        $join = function ($what, $from) { return $what . ($from !== '' ? ' (' . $from . ')' : ''); };

        if (isGalleryAdminId($id)) {
            $cells['gallery'] = ['zarządza', 'strong', 'Admin galerii: ogląda i zarządza plikami'];
            $details[] = ['Galeria', $join('zarządza', accessFrom('galleryAdmins', $id, $logins))];
        } else if (canViewGalleryId($id)) {
            $cells['gallery'] = ['ogląda', 'on', 'Ogląda galerię'];
            $details[] = ['Galeria', $join('ogląda', accessFrom('galleryViewers', $id, $logins))];
        } else {
            $cells['gallery'] = ['—', '', 'Nie widzi galerii'];
        }

        if (canSeePrivateGalleryId($id))
            $details[] = ['Prywatny folder', $join('widzi i/' . PRIVATE_DIR, isPanelAdminId($id) ? 'właściciel strony (panel)' : accessFrom('galleryPrivate', $id, $logins))];

        $folder = userFolder($galleryDir, $id);
        // counted as the limits count it: subfolders included
        [$files, $bytes] = $folder !== null ? treeUse($galleryDir . '/' . $folder) : [0, 0];
        if (isGalleryUploaderId($id)) {
            $limit = userFilesLimit($id);
            $use = $files . ' z ' . $limit . ' ' . plural($limit, 'zdjęcia', 'zdjęć', 'zdjęć') . ', ' . formatSize($bytes) . ' z ' . formatSize(USER_TOTAL_MAX_BYTES)
                . ', kosz ' . formatSize(userTrashBytes($id)) . ' z ' . formatSize(USER_TRASH_MAX_BYTES);
            $cells['folder'] = [$files . '/' . $limit, 'on', 'Własny folder: ' . $use];
            $details[] = ['Własny folder', ($folder !== null ? displayPath($folder) . ', ' : 'jeszcze nie założony, ') . $use];
        } else {
            $from = accessFrom('galleryUploaders', $id, $logins);
            $blocked = $from !== '' ? $join('zablokowany', $from) : 'bez roli na serwerze bota';
            $cells['folder'] = [$folder !== null ? 'zostaje' : 'blok', '', 'Własny folder ' . $blocked . ($folder !== null ? ', folder został: ' . $files . ' ' . plural($files, 'plik', 'pliki', 'plików') : '')];
            $details[] = ['Własny folder', $blocked . ($folder !== null ? ', ' . displayPath($folder) . ' został, ' . $files . ' ' . plural($files, 'plik', 'pliki', 'plików') : '')];
        }

        if (isPanelAdminId($id)) {
            $cells['panel'] = ['tak', 'strong', 'Panel administratora'];
            $details[] = ['Panel', 'tak (inc/config.php, PANEL_ADMINS)'];
        } else {
            $cells['panel'] = ['—', '', 'Bez panelu'];
        }

        if (isPanelAdminId($id)) {
            $cells['api'] = ['przez panel', 'on', 'Dokumentacja API, jak dla każdego z panelu'];
        } else if (inAccessList('apiViewers', $id, true)) {
            $cells['api'] = ['z listy', 'on', 'Dokumentacja API z listy dostępu'];
            $details[] = ['API', $join('z listy', accessFrom('apiViewers', $id, $logins))];
        } else if (hasBotRole($id, API_ROLES)) {
            $cells['api'] = ['przez rolę', 'on', 'Dokumentacja API przez rolę na serwerze bota'];
            $details[] = ['API', 'przez rolę na serwerze bota'];
        } else {
            $cells['api'] = ['—', '', 'Bez dokumentacji API'];
        }

        if (inAccessList('vipAccounts', $id, false))
            $details[] = ['LV.2 VIP', $join(hasServerRole($id) ? 'daje WAP' : 'bez roli na serwerze bota, nie działa', accessFrom('vipAccounts', $id, $logins))];

        $details[] = ['Role na serwerze bota', $serverRoles === null ? 'nieznane (bot jeszcze nie pytany)' : (roleNames($serverRoles) ? implode(', ', roleNames($serverRoles)) : (empty($serverRoles['onGuild']) ? 'poza serwerem' : 'brak'))];

        return [$cells, $details];
    }

    // avatar and name of an account, from its latest login when there was one
    function accountCell($id, $logins)
    {
        $known = $logins[$id] ?? null;
        $avatar = $known['avatar'] ?? 'https://cdn.discordapp.com/embed/avatars/' . (((int)$id >> 22) % 6) . '.png';
        $name = $known['name'] ?? 'nieznane konto';

        return '<img src="' . e($avatar) . '" alt="" width="28" height="28" loading="lazy" />'
            . '<span class="who"><a class="who-name" href="?konto=' . e($id) . '" title="Profil konta">' . e($name) . '</a><code>' . e($id) . '</code></span>';
    }

    // what a change of the Cloudflare list sent from the panel came to (cloudflarePending())
    function cloudflareOpStatus($op)
    {
        switch ($op['status']) {
            case 'pending': return 'Oczekuje na potwierdzenie';
            case 'completed': return 'Zrobione';
            default: return $op['message'];
        }
    }

    // The changes of the Cloudflare list sent from the panel, waiting or just
    // finished, above every page of the panel; js/admin.js asks ?cloudflare
    // about the waiting ones and writes in what they came to.
    function cloudflarePendingCard($ops)
    {
        if (!$ops)
            return '';
        $html = '    <div class="panel-grid cf-pending-grid">' . "\n"
            . '      <section class="card wide cf-pending" id="cf-pending" aria-live="polite">' . "\n"
            . '        <h2><i>!</i>Zmiany w Cloudflare</h2>' . "\n"
            . '        <ul class="diag-blocked">' . "\n";
        foreach ($ops as $op)
            $html .= '          <li data-id="' . e($op['id']) . '" data-status="' . e($op['status']) . '"><code>' . e($op['text']) . '</code>'
                . '<span class="cf-op-status">' . e(cloudflareOpStatus($op)) . '</span>'
                . '<span class="muted">' . e(date('H:i', (int)$op['sent'])) . '</span></li>' . "\n";

        return $html . "        </ul>\n      </section>\n    </div>\n";
    }

    // How an account answers the panel's search (inc/panel-search.php) by its
    // ID, name or Discord name: [rank, why], or null when it does not. Rank 0
    // is the whole of it, 1 its start, 2 anywhere in it. "@name", as the
    // search's hint puts it, is the Discord name, kept without the @; the
    // name shown only after every Discord name (ranks 3 to 5), for an @ put
    // before that one.
    function accountSearchMatch($query, $id, $login)
    {
        $query = trim((string)$query);
        $handle = $query !== '' && $query[0] === '@';
        $needle = lower(ltrim($query, '@'));
        if ($needle === '')
            return null;

        $rank = function ($text) use ($needle) {
            $text = lower((string)$text);
            if ($text === '' || ($at = strpos($text, $needle)) === false)
                return null;
            return $text === $needle ? 0 : ($at === 0 ? 1 : 2);
        };
        $username = (string)($login['username'] ?? '');
        $checks = $handle
            ? [[$username, '@' . $username, 0], [$login['name'] ?? '', 'nazwa', 3]]
            : [[ctype_digit($needle) ? (string)$id : '', 'ID', 0], [$login['name'] ?? '', 'nazwa', 0], [$username, '@' . $username, 0]];
        $best = null;
        foreach ($checks as [$text, $why, $after])
            if (($found = $rank($text)) !== null && ($best === null || $found + $after < $best[0]))
                $best = [$found + $after, $why];

        return $best;
    }
