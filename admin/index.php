<?php
    // Admin panel: who may use the gallery and the API, the bot status, the
    // roles the bot reports for the recent logins, and server tools. The home page links here for the accounts that may open it. The
    // Discord login is the gallery's (inc/auth.php, one session for all); only
    // the accounts in PANEL_ADMINS (inc/config.php) get in.
    require __DIR__ . '/../inc/bot.php';
    require __DIR__ . '/../inc/services.php';
    require __DIR__ . '/../inc/gallery.php';
    require __DIR__ . '/../inc/status-card.php';
    require_once __DIR__ . '/../inc/system.php';
    require __DIR__ . '/../inc/diag.php';
    require __DIR__ . '/../inc/panel-stats.php';
    require __DIR__ . '/../inc/cloudflare.php';
    require __DIR__ . '/../inc/autoblock.php';
    require __DIR__ . '/../inc/meta.php';

    $galleryDir = str_replace('\\', '/', dirname(__DIR__)) . '/i';
    $thumbsDir = thumbsDir();

    const LIST_LABELS = [
        'galleryAdmins' => 'administratorzy galerii',
        'galleryViewers' => 'oglądający galerię',
        'galleryUploaders' => 'własny folder w galerii',
        'galleryPrivate' => 'prywatny folder w galerii',
        'apiViewers' => 'dostęp do API'
    ];

    // title and description of the card of each list
    const LIST_CARDS = [
        'galleryAdmins' => ['Administratorzy galerii', 'Oglądają galerię i dodają, przenoszą oraz usuwają pliki.'],
        'galleryViewers' => ['Oglądający galerię', 'Tylko oglądają galerię.'],
        'galleryUploaders' => ['Własne foldery w galerii', 'Dodają zdjęcia tylko do swojego folderu i/' . USERS_DIR . '/ID-nick i tylko jego widzą (oprócz nich administratorzy galerii). Do '
            . USER_FILES_DEFAULT . ' zdjęć, limit zmienia się w profilu konta; każde do ' . USER_FILE_MAX_BYTES / 1048576 . ' MB, razem do ' . USER_TOTAL_MAX_BYTES / 1048576
            . ' MB, zapisywane jako WebP, gdy wychodzi mniejszy. Folder zostaje po odebraniu dostępu.'],
        'galleryPrivate' => ['Prywatny folder galerii', 'Widzą i/' . PRIVATE_DIR . '. Administratorzy panelu zawsze, reszta z tej listy. Pliki nie otwierają się bezpośrednim linkiem.'],
        'apiViewers' => ['Dostęp do API', 'Czytają dokumentację API w api/. Administratorzy panelu mają ją zawsze, a z ról na serwerze bota dev, admin, semi-admin i tester.']
    ];

    // name of an account from the recent logins, or its ID
    function accountLabel($id, $logins)
    {
        return isset($logins[$id]['name']) ? $logins[$id]['name'] . ' (' . $id . ')' : $id;
    }

    function dataError()
    {
        return 'Nie udało się zapisać danych w inc/data. PHP musi mieć tam prawo zapisu, np.: '
            . 'mkdir -p ' . __DIR__ . '/../inc/data && chown www-data:www-data ' . __DIR__ . '/../inc/data';
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

    // ---- Availability of the site (inc/diag.php) -----------------------------------

    // "40 s", or minutes and hours as duration() gives them
    function diagSeconds($seconds)
    {
        return $seconds < 60 ? max(1, (int)$seconds) . ' s' : duration($seconds);
    }

    // a 24 h bar of quarter hours for the probes in $names
    function diagBar($title, $rounds, $names)
    {
        $parts = diagTimeline($rounds, $names);
        $checked = array_sum(array_column($parts, 'rounds'));
        $failed = array_sum(array_column($parts, 'fail'));
        $html = '<div class="bar"><div class="bar-head"><span>' . e($title) . '</span><b>'
            . ($checked ? ($failed ? $failed . ' z ' . formatCount($checked) . ' bez odpowiedzi' : 'zawsze odpowiadała') : '–') . '</b></div>'
            . '<div class="timeline" aria-label="' . e($title) . ' w ostatnich 24 godzinach, po 15 minut">';
        foreach ($parts as $part) {
            $label = !$part['rounds'] ? 'brak pomiarów'
                : ($part['fail'] ? $part['fail'] . ' z ' . $part['rounds'] . ' bez odpowiedzi' : 'odpowiadała')
                    . ($part['slow'] ? ', ' . $part['slow'] . ' wolno (ponad ' . milliseconds(DIAG_SLOW_MS) . ')' : '');
            $html .= '<span class="' . ($part['state'] ?? 'none') . '" title="' . e(date('H:i', $part['from']) . '-' . date('H:i', $part['from'] + 900) . ': ' . $label) . '"></span>';
        }

        return $html . '</div></div>';
    }

    // requests per quarter hour from the nginx log, the peak of 10 s in the title
    function diagTrafficChart($rounds)
    {
        $parts = diagTimeline($rounds, []);
        $scale = max(1, max(array_column($parts, 'requests')));
        $total = array_sum(array_column($parts, 'requests'));
        $html = '<div class="bar"><div class="bar-head"><span>Ruch na stronie, 24 godziny</span><b>' . formatCount($total) . ' ' . plural($total, 'zapytanie', 'zapytania', 'zapytań')
            . ' &middot; najwięcej ' . formatCount(max(array_column($parts, 'peak'))) . ' w 10 s</b></div>'
            . '<div class="response-chart" aria-label="Zapytania do strony w ostatnich 24 godzinach, po 15 minut">';
        foreach ($parts as $part)
            $html .= '<span title="' . e(date('H:i', $part['from']) . '-' . date('H:i', $part['from'] + 900) . ': ' . formatCount($part['requests']) . ' zapytań, najwięcej ' . formatCount($part['peak']) . ' w 10 s') . '">'
                . ($part['requests'] ? '<i style="height: ' . max(4, round(100 * $part['requests'] / $scale)) . '%"></i>' : '') . '</span>';

        return $html . '</div><div class="bar-ends"><span>24 h temu</span><span>teraz</span></div></div>';
    }

    // A 24 h line chart for the server card from diagUsage(): [average, most,
    // title] per part in percent, null where nothing was measured; $top is the
    // percent at the top of the chart. The average is a line, with a band up to
    // the most of the part ('band', for the processor, whose short peaks
    // matter) or a fill below it ('area', for memory, which changes slowly).
    // Every part has a column with its title for the mouse; $axis labels the
    // top and middle.
    function usageChart($title, $summary, $kind, $points, $top, $axis)
    {
        $count = count($points);
        $x = function ($i) use ($count) { return round(($i + 0.5) * 1000 / $count, 1); };
        $y = function ($value) use ($top) { return round(100 - min(100, max(0, 100 * $value / $top)), 1); };

        // runs of measured parts: the line breaks where nothing was measured
        $runs = [];
        $run = [];
        foreach ($points as $i => $point) {
            if ($point[0] !== null) {
                $run[] = $i;
                continue;
            }
            if ($run)
                $runs[] = $run;
            $run = [];
        }
        if ($run)
            $runs[] = $run;

        $line = '';
        $fill = '';
        foreach ($runs as $run) {
            $average = '';
            foreach ($run as $n => $i)
                $average .= ($n ? 'L' : 'M') . $x($i) . ' ' . $y($points[$i][0]);
            $line .= $average;
            if ($kind === 'band') {
                foreach ($run as $n => $i)
                    $fill .= ($n ? 'L' : 'M') . $x($i) . ' ' . $y($points[$i][1]);
                foreach (array_reverse($run) as $i)
                    $fill .= 'L' . $x($i) . ' ' . $y($points[$i][0]);
                $fill .= 'Z';
            } else
                $fill .= $average . 'L' . $x(end($run)) . ' 100L' . $x($run[0]) . ' 100Z';
        }

        $html = '<div class="bar"><div class="bar-head"><span>' . e($title) . '</span><b>' . $summary . '</b></div>'
            . '<div class="usage-plot" role="img" aria-label="' . e($title . ', ' . html_entity_decode($summary)) . '">'
            . '<svg viewBox="0 0 1000 100" preserveAspectRatio="none" aria-hidden="true">'
            . '<path class="usage-grid" d="M0 0H1000M0 50H1000" />'
            . ($kind === 'area'
                ? '<defs><linearGradient id="usage-fade" x1="0" y1="0" x2="0" y2="1"><stop offset="0" /><stop offset="1" /></linearGradient></defs>'
                    . '<path d="' . $fill . '" fill="url(#usage-fade)" />'
                : '<path class="usage-band" d="' . $fill . '" />')
            . '<path class="usage-line" d="' . $line . '" /></svg>'
            . '<span class="usage-axis">' . e($axis[0]) . '</span><span class="usage-axis middle">' . e($axis[1]) . '</span>'
            . '<div class="usage-columns">';
        foreach ($points as $point)
            $html .= '<span title="' . e($point[2]) . '"></span>';

        return $html . '</div></div><div class="bar-ends"><span>24 h temu</span><span>teraz</span></div></div>';
    }

    // "14:05-14:10: " for a part of diagUsage()
    function usageTime($part, $parts)
    {
        return date('H:i', $part['from']) . '-' . date('H:i', $part['from'] + intdiv(86400, count($parts))) . ': ';
    }

    // processor use of the last 24 h from the rounds of inc/diag.php
    function cpuChart($parts)
    {
        $points = [];
        foreach ($parts as $part)
            $points[] = $part['cpu'] === null ? [null, null, usageTime($part, $parts) . 'brak pomiarów'] : [$part['cpu'], $part['cpuPeak'],
                usageTime($part, $parts) . 'średnio ' . decimal($part['cpu']) . '%, najwięcej ' . decimal($part['cpuPeak']) . '% w 10 s'
                    . ' · czekanie na dysk ' . decimal($part['io']) . '% · zabrane przez hosta ' . decimal($part['st']) . '%'
                    . ($part['load'] !== null ? ' · obciążenie do ' . decimal($part['load'], 2) : '')];
        $measured = array_filter(array_column($parts, 'cpu'), 'is_numeric');
        $most = max(array_column($parts, 'cpuPeak')) ?? 0;
        $summary = $measured ? 'średnio ' . decimal(array_sum($measured) / count($measured)) . '% &middot; najwięcej '
            . decimal($most) . '% w 10 s' : 'brak pomiarów';

        // a quiet server uses a few percent, which would lie flat at the bottom
        // of 100%: the top is the first of these at or above the most of the day
        $top = 100;
        foreach ([5, 10, 20, 25, 50] as $step)
            if ($most <= $step) {
                $top = $step;
                break;
            }
        $percent = function ($value) { return str_replace('.', ',', (string)round($value, 1)) . '%'; };

        return '<div class="usage-cpu">' . usageChart('Procesor, 24 godziny', $summary, 'band', $points, $top, [$percent($top), $percent($top / 2)]) . '</div>';
    }

    // memory in use of the last 24 h from the rounds of inc/diag.php
    function memoryChart($parts, $total)
    {
        $points = [];
        foreach ($parts as $part)
            $points[] = $part['mem'] === null ? [null, null, usageTime($part, $parts) . 'brak pomiarów'] : [100 * $part['mem'] / $total, null,
                usageTime($part, $parts) . 'średnio ' . formatSize($part['mem']) . ', najwięcej ' . formatSize($part['memPeak']) . ' z ' . formatSize($total)];
        $measured = array_filter(array_column($parts, 'mem'), 'is_numeric');
        $summary = $measured ? 'średnio ' . e(formatSize(array_sum($measured) / count($measured))) . ' &middot; najwięcej '
            . e(formatSize(max(array_column($parts, 'memPeak')))) . ' z ' . e(formatSize($total)) : 'brak pomiarów';

        return '<div class="usage-mem">' . usageChart('Pamięć RAM, 24 godziny', $summary, 'area', $points, 100, [formatSize($total), formatSize($total / 2)]) . '</div>';
    }

    // An address in a list: a lookup in AbuseIPDB, the country, whether it is a
    // scanner, the accounts that came from it (links to their profiles), and
    // whether Cloudflare blocks it, or a button to block it there. Never a
    // button for the admin's own address or one of an account of the panel.
    // $marks from diagMarks().
    function diagIpCell($ip, $cc, $note, $marks)
    {
        $html = '<span class="diag-ip"><a href="https://www.abuseipdb.com/check/' . e(rawurlencode($ip)) . '" target="_blank" rel="noopener" title="Sprawdź adres w AbuseIPDB">' . e($ip) . '</a>'
            . ($cc !== '' ? ' <span class="muted">' . e($cc) . '</span>' : '');
        if (isset($marks['scanners'][$ip]))
            $html .= ' <span class="role scanner" title="' . e($marks['scanners'][$ip]) . '">skaner</span>';
        $owners = accountsAtAddress($ip, $marks['addresses']);
        foreach ($owners as $id => $owner)
            $html .= ' <a class="role account" href="?konto=' . e($id) . '" title="' . e('Konto logowało się z ' . ($owner[0] === $ip ? 'tego adresu' : 'tej samej sieci /64') . ', ostatnio ' . ago($owner[1])) . '">'
                . e($marks['logins'][$id]['name'] ?? $id) . '</a>';

        $target = cloudflareTarget($ip);
        if ($target !== null && !diagIsCloudflare($ip) && $marks['blocked'] !== null) {
            $protected = protectedAddress($ip, $owners, $marks['logins']);
            if (isset($marks['blocked'][$target]))
                $html .= ' <span class="role blocked" title="' . e($target) . ' jest na liście blokad w Cloudflare">zablokowany</span>';
            else if ($protected !== null)
                $html .= ' <span class="role protected" title="' . e('Nie da się zablokować: ' . $protected) . '">chroniony</span>';
            else {
                $names = array_map(function ($id) use ($marks) { return $marks['logins'][$id]['name'] ?? $id; }, array_keys($owners));
                $html .= ' <button type="button" class="admin-btn small danger" data-action="cf-block" data-ip="' . e($ip) . '" data-note="' . e($note) . '"'
                    . ' data-confirm="' . e('Zablokować ' . $target . ' w Cloudflare? Jego zapytania przestaną docierać do strony.'
                        . ($names ? ' Logowało się z niego konto: ' . implode(', ', $names) . '.' : '')) . '">Zablokuj</button>';
            }
        }

        return $html . '</span>';
    }

    // the addresses that sent most requests: address, requests and those of
    // them to PHP, user agent and most asked path
    function diagIpList($ips, $marks)
    {
        if (!$ips)
            return '<p class="nobody">Brak zapytań w dzienniku.</p>';

        $most = $ips[0]['n'];
        $html = '<ul class="diag-ips">';
        foreach ($ips as $ip) {
            $html .= '<li style="--share: ' . share($ip['n'], $most) . '">'
                . diagIpCell($ip['ip'], $ip['cc'], $marks['scanners'][$ip['ip']] ?? ($ip['ua'] !== '' ? $ip['ua'] : $ip['path']), $marks)
                . '<span class="diag-count">' . formatCount($ip['n']) . ($ip['php'] ? ' <span class="muted">PHP ' . formatCount($ip['php']) . '</span>' : '') . '</span>'
                . '<span class="diag-agent"><span title="' . e($ip['ua']) . '">' . e($ip['ua'] !== '' ? $ip['ua'] : 'bez user agenta') . '</span>'
                . '<code title="' . e($ip['path']) . '">' . e($ip['path']) . '</code></span>'
                . (diagIsCloudflare($ip['ip']) ? '<b class="warn">adres Cloudflare, nie odwiedzającego</b>' : '')
                . '</li>';
        }

        return $html . '</ul>';
    }

    // the scanners of the last days: address, requests, why and when, user
    // agent and the paths they asked for most
    function diagScannerList($scanners, $marks)
    {
        $most = $scanners ? reset($scanners)['n'] : 0;
        $html = '<ul class="diag-ips">';
        foreach ($scanners as $scanner) {
            $paths = array_keys($scanner['paths']);
            $sameDay = date('Y-m-d', $scanner['first']) === date('Y-m-d', $scanner['last']);
            $html .= '<li style="--share: ' . share($scanner['n'], $most) . '">'
                . diagIpCell($scanner['ip'], $scanner['cc'], $scanner['reason'], $marks)
                . '<span class="diag-count">' . formatCount($scanner['n']) . '</span>'
                . '<span class="diag-agent"><span>' . e($scanner['reason'])
                    . (count($scanner['probes']) > 1 ? ' <span class="muted" title="' . e(implode(' ', $scanner['probes'])) . '">(' . count($scanner['probes']) . ' takich ścieżek)</span>' : '')
                    . ' &middot; ' . e(date('d.m H:i', $scanner['first']))
                    . ($scanner['last'] - $scanner['first'] >= 60 ? '–' . e(date($sameDay ? 'H:i' : 'd.m H:i', $scanner['last'])) : '') . '</span>'
                . '<span title="' . e($scanner['ua']) . '">' . e($scanner['ua'] !== '' ? $scanner['ua'] : 'bez user agenta') . '</span>'
                . '<code title="' . e(implode(' ', $paths)) . '">' . e(implode(' ', array_slice($paths, 0, 3))) . '</code></span>'
                . '</li>';
        }

        return $html . '</ul>';
    }

    // what most likely went wrong in an episode, from what the server noted meanwhile
    function diagVerdict($episode)
    {
        if ($episode['publicOnly']) {
            $text = 'Na serwerze strona odpowiadała, a połączenia z Cloudflare nie dochodziły';
            if ($episode['overflows'])
                return $text . ': jądro odrzuciło ' . formatCount($episode['overflows']) . ' ' . plural($episode['overflows'], 'połączenie', 'połączenia', 'połączeń')
                    . ', bo nginx nie nadążał ich przyjmować (pełna kolejka).';
            if ($episode['nginxUnhandled'])
                return $text . ': nginx nie obsłużył ' . formatCount($episode['nginxUnhandled']) . ' połączeń, zabrakło mu worker_connections.';

            return $text . '. Jądro żadnych nie odrzuciło, więc to raczej zapora, sieć hosta albo sam Cloudflare.';
        }
        if ($episode['phpOnly']) {
            $text = 'nginx odpowiadał, PHP nie';
            if ($episode['fpmFull'] || $episode['fpmQueue'])
                return $text . ': wszystkie procesy PHP-FPM były zajęte'
                    . ($episode['fpmActive'] !== null ? ' (' . $episode['fpmActive'] . ' z ' . $episode['fpmTotal'] . ')' : '')
                    . ($episode['fpmQueue'] ? ', w kolejce czekało do ' . $episode['fpmQueue'] . ' zapytań' : '') . '.';

            return $text . '. Wolne zapytania pokaże dziennik slowlog niżej.';
        }

        return 'Strona nie odpowiadała także na serwerze: nginx nie przyjmował połączeń albo serwer stanął.';
    }

    // ---- Changes ------------------------------------------------------------

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!authConfigured())
            reply(false, 'Logowanie nie jest włączone na serwerze.', 503);

        $user = siteUser();
        if (!$user)
            reply(false, 'Trzeba się zalogować.', 401);
        if (!checkCsrf())
            reply(false, 'Sesja wygasła, odśwież stronę.', 403);

        $action = (string)($_POST['action'] ?? '');
        if (!isPanelAdminId($user['id']))
            reply(false, 'To konto nie ma dostępu do panelu.', 403);

        $logins = readData('logins');
        $list = (string)($_POST['list'] ?? '');
        $id = trim((string)($_POST['id'] ?? ''));

        switch ($action) {
            case 'grant':
                if (!isset(ACCESS_LISTS[$list]))
                    reply(false, 'Nieznana lista.', 400);
                if (!preg_match('/^\d{17,20}$/', $id))
                    reply(false, 'ID konta Discord to 17-20 cyfr.', 400);

                $config = configList(ACCESS_LISTS[$list]);
                if ($config !== true && in_array($id, $config, true))
                    reply(false, 'To konto jest już na tej liście w konfiguracji.', 409);

                $access = readData('access');
                if (isset($access[$list][$id]))
                    reply(false, 'To konto jest już na tej liście.', 409);

                $access[$list][$id] = [
                    'note' => cutText(trim((string)($_POST['note'] ?? '')), 60),
                    'added' => time(),
                    'by' => $user['id']
                ];
                if (!writeData('access', $access))
                    reply(false, dataError(), 500);
                // a request of this account is answered now
                $for = $list === 'apiViewers' ? 'api' : 'gallery';
                $requests = readData('requests');
                if (isset($requests[$for][$id])) {
                    unset($requests[$for][$id]);
                    writeData('requests', $requests);
                }
                done('access', 'Dodano ' . accountLabel($id, $logins) . ': ' . LIST_LABELS[$list] . '.');

            case 'revoke':
                $access = readData('access');
                if (!isset(ACCESS_LISTS[$list]) || !isset($access[$list][$id]))
                    reply(false, 'Nie ma takiego wpisu (wpisy z konfiguracji zmienia się w inc/config.php).', 404);

                unset($access[$list][$id]);
                if (!writeData('access', $access))
                    reply(false, dataError(), 500);
                done('access', 'Usunięto ' . accountLabel($id, $logins) . ': ' . LIST_LABELS[$list] . '.');

            case 'upload-limit':
                if (!preg_match('/^\d{17,20}$/', $id))
                    reply(false, 'ID konta Discord to 17-20 cyfr.', 400);
                $limit = (int)($_POST['limit'] ?? 0);
                if ($limit < 1 || $limit > USER_FILES_MAX)
                    reply(false, 'Limit to od 1 do ' . USER_FILES_MAX . ' zdjęć.', 400);
                $access = readData('access');
                if ($limit === USER_FILES_DEFAULT)
                    unset($access['uploadLimits'][$id]);
                else
                    $access['uploadLimits'][$id] = $limit;
                if (!writeData('access', $access))
                    reply(false, dataError(), 500);
                done('access', 'Limit zdjęć we własnym folderze ' . accountLabel($id, $logins) . ': ' . $limit . '.');

            case 'share-revoke':
                $token = (string)($_POST['token'] ?? '');
                $shares = readData('shares');
                if (!isset($shares[$token]['rel']))
                    reply(false, 'Tego linku już nie ma, odśwież stronę.', 404);
                $rel = (resolvePath($galleryDir, $shares[$token]['rel'], true) ?? [null, $shares[$token]['rel']])[1];
                unset($shares[$token]);
                if (!writeData('shares', $shares))
                    reply(false, dataError(), 500);
                done('share', 'Wyłączono link do ' . galleryPath($rel) . '.');

            case 'test-rights':
                $panel = (string)($_POST['panel'] ?? '');
                $gallery = (string)($_POST['gallery'] ?? '');
                $private = (string)($_POST['private'] ?? '');
                $api = (string)($_POST['api'] ?? '');
                $role = (string)($_POST['role'] ?? '');
                $minutes = (int)($_POST['minutes'] ?? 0);
                if (!in_array($panel, ['', '0', '1'], true) || ($gallery !== '' && !isset(TEST_GALLERY[$gallery])) || !in_array($private, ['', '0', '1'], true)
                        || !in_array($api, ['', '0', '1'], true) || ($role !== '' && $role !== TEST_ROLE_OUT && !isset(BOT_ROLES[$role])) || !in_array($minutes, TEST_MINUTES, true))
                    reply(false, 'Złe ustawienie podglądu, odśwież stronę.', 400);
                $rights = array_filter([
                    'panel' => $panel === '' ? null : $panel === '1',
                    'gallery' => $gallery === '' ? null : $gallery,
                    'private' => $private === '' ? null : $private === '1',
                    'api' => $api === '' ? null : $api === '1',
                    'role' => $role === '' ? null : $role
                ], function ($value) { return $value !== null; });
                if (!$rights)
                    reply(false, 'Wybierz, co zmienić.', 400);
                setTestRights($rights, $minutes);
                done('test', 'Podgląd z innymi uprawnieniami na ' . $minutes . ' min: ' . testRightsText($rights) . '.',
                    'Podgląd włączony do ' . date('H:i', time() + $minutes * 60) . '. Pasek na dole każdej strony przywraca twoje uprawnienia.');

            case 'test-off':
                setTestRights(null);
                done('test', 'Koniec podglądu z innymi uprawnieniami.', 'Wróciły twoje prawdziwe uprawnienia.');

            case 'request-dismiss':
                $for = (string)($_POST['for'] ?? '');
                $requests = readData('requests');
                if (!isset(REQUEST_LABELS[$for], $requests[$for][$id]))
                    reply(false, 'Tej prośby już nie ma, odśwież stronę.', 404);
                unset($requests[$for][$id]);
                if (!writeData('requests', $requests))
                    reply(false, dataError(), 500);
                done('access', 'Odrzucono prośbę o dostęp do ' . REQUEST_LABELS[$for] . ': ' . accountLabel($id, $logins) . '.');

            case 'backup':
                // a plain form: the answer is the ZIP itself, a problem comes back as a message
                $withGallery = ($_POST['gallery'] ?? '') === '1';
                // without the logins of the moment (sessions), which would only log people
                // in again, the thumbnails, which are made again by themselves, and the
                // availability checks of the last days
                $roots = [[dataDir(), 'data', ['sessions', 'thumbs', 'diag']]];
                if ($withGallery)
                    $roots[] = [$galleryDir, 'i', ['index.php']];
                addHistory('backup', 'Pobrano kopię danych' . ($withGallery ? ' z galerią' : '') . '.');
                sendZip($roots, 'sanakan-kopia-' . date('Y-m-d-His') . '.zip', './', null);

            case 'logout-all':
                $now = time();
                if (!writeData('sessions', ['since' => $now]))
                    reply(false, dataError(), 500);
                // the one who asked for it stays logged in
                $_SESSION['login_time'] = $now;
                done('sessions', 'Wylogowano wszystkich z galerii i panelu (poza sobą).');

            case 'logout-account':
                if (!preg_match('/^\d{17,20}$/', $id))
                    reply(false, 'ID konta Discord to 17-20 cyfr.', 400);
                if ($id === $user['id'])
                    reply(false, 'Siebie wyloguj z menu konta w rogu strony.', 400);
                $sessions = readData('sessions');
                $sessions['accounts'][$id] = time();
                if (!writeData('sessions', $sessions))
                    reply(false, dataError(), 500);
                done('sessions', 'Wylogowano ' . accountLabel($id, $logins) . ' na wszystkich urządzeniach.');

            case 'logout-session':
                $key = (string)($_POST['session'] ?? '');
                if (!preg_match('/^\d{17,20}$/', $id) || !preg_match('/^[0-9a-f]{20}$/', $key))
                    reply(false, 'Nieznana sesja, odśwież stronę.', 400);
                if ($key === sessionKey(session_id()))
                    reply(false, 'To ta sesja, z której teraz korzystasz. Wyloguj się z menu konta.', 400);
                if (!endDevice($id, $key))
                    reply(false, 'Tej sesji już nie ma, odśwież stronę.', 404);
                done('sessions', 'Wylogowano ' . accountLabel($id, $logins) . ' na jednym urządzeniu.');

            case 'notice':
                $text = cutText(trim(str_replace("\r", '', (string)($_POST['text'] ?? ''))), NOTICE_LENGTH);
                if ($text === '')
                    reply(false, 'Wpisz treść ogłoszenia.', 400);

                $from = noticeTime($_POST['from'] ?? '');
                $to = noticeTime($_POST['to'] ?? '');
                $maintenance = null;
                if (($_POST['maintenance'] ?? '') === '1') {
                    $from = $from ?? time();
                    if ($to === null)
                        reply(false, 'Przerwa techniczna potrzebuje godziny końca.', 400);
                    if ($to <= $from)
                        reply(false, 'Koniec przerwy musi być po jej początku.', 400);
                    $maintenance = ['from' => $from, 'to' => $to];
                } else if ($to !== null && $to <= time()) {
                    reply(false, 'Czas zniknięcia ogłoszenia już minął.', 400);
                }

                $notice = ['text' => $text, 'to' => $to, 'maintenance' => $maintenance, 'by' => $user['id'], 'set' => time()];
                botSaveNotice($notice);
                done('notice', 'Ustawiono ogłoszenie: ' . noticeText($notice));

            case 'notice-clear':
                botSaveNotice(null);
                done('notice', 'Usunięto ogłoszenie.');

            case 'quality':
                $settings = readData('settings');
                $parts = [];
                foreach (['jpg' => 'JPG', 'png' => 'PNG', 'gif' => 'GIF', 'avif' => 'AVIF'] as $kind => $label) {
                    $value = max(WEBP_QUALITY_MIN, min(WEBP_QUALITY_MAX, (int)($_POST['quality' . ucfirst($kind)] ?? 0)));
                    $settings['quality' . ucfirst($kind)] = $value;
                    $parts[] = $label . ' ' . $value;
                }
                if (!writeData('settings', $settings))
                    reply(false, dataError(), 500);
                done('quality', 'Zmieniono jakość konwersji na WebP: ' . implode(', ', $parts) . '.');

            case 'clear-thumbs':
                $removed = 0;
                foreach (glob($thumbsDir . '/*') ?: [] as $file)
                    if (is_file($file) && @unlink($file))
                        $removed++;
                done('cache', 'Usunięto ' . $removed . ' ' . plural($removed, 'miniaturę', 'miniatury', 'miniatur') . ' z cache; utworzą się od nowa przy oglądaniu galerii.');

            case 'refresh-spec':
                @unlink(botFile('swagger.json'));
                done('cache', 'Odświeżono specyfikację API; zostanie pobrana przy następnym wejściu do API.');

            case 'refresh-stats':
                panelStats($galleryDir, true);
                done('cache', 'Przeliczono galerię od nowa.');

            case 'restore':
                $item = trashItems()[(string)($_POST['item'] ?? '')] ?? null;
                if (!$item)
                    reply(false, 'Tego już nie ma w koszu, odśwież stronę.', 404);
                $where = restoreFromTrash($galleryDir, (string)$_POST['item']);
                if ($where === null)
                    reply(false, 'Nie udało się przywrócić ' . $item['name'] . '.', 500);
                done('restore', 'Przywrócono z kosza ' . galleryPath($where) . '.');

            case 'trash-delete':
                $item = trashItems()[(string)($_POST['item'] ?? '')] ?? null;
                if (!$item || !deleteFromTrash((string)$_POST['item']))
                    reply(false, 'Tego już nie ma w koszu, odśwież stronę.', 404);
                done('purge', 'Usunięto na zawsze ' . galleryPath($item['from']) . ' (z kosza).');

            case 'cf-block':
                if (!cloudflareConfigured())
                    reply(false, 'Blokowanie w Cloudflare nie jest ustawione.', 503);
                $ip = trim((string)($_POST['ip'] ?? ''));
                $target = cloudflareTarget($ip);
                if ($target === null || diagIsCloudflare($ip))
                    reply(false, 'Tego adresu nie da się zablokować: to adres lokalny albo Cloudflare.', 400);
                $protected = protectedAddress($ip, accountsAtAddress($ip, readData('addresses')), $logins);
                if ($protected !== null)
                    reply(false, 'Nie blokuję: ' . $protected . '.', 409);
                $note = trim((string)($_POST['note'] ?? ''));
                $error = cloudflareBlock($ip, 'Panel, ' . $user['name'] . ($note !== '' ? ': ' . $note : ''));
                if ($error !== null)
                    reply(false, $error, 502);
                done('cloudflare', 'Zablokowano w Cloudflare ' . $target . ($note !== '' ? ' (' . cutText($note, 80) . ')' : '') . '.');

            case 'cf-unblock':
                if (!cloudflareConfigured())
                    reply(false, 'Blokowanie w Cloudflare nie jest ustawione.', 503);
                $item = (string)($_POST['item'] ?? '');
                if (!preg_match('/^[0-9a-f]{32}$/', $item))
                    reply(false, 'Nieznana pozycja listy, odśwież stronę.', 400);
                $error = cloudflareUnblock($item);
                if ($error !== null)
                    reply(false, $error, 502);
                // not blocked again by the automatic blocking
                $ip = cutText(trim((string)($_POST['ip'] ?? '')), 60);
                if ($ip !== '')
                    autoBlockReleased($ip);
                done('cloudflare', 'Odblokowano w Cloudflare ' . $ip . '.');

            case 'auto-block':
                if (!cloudflareConfigured())
                    reply(false, 'Blokowanie w Cloudflare nie jest ustawione.', 503);
                $settings = readData('settings');
                $settings['autoBlock'] = ($_POST['on'] ?? '') === '1';
                if (!writeData('settings', $settings))
                    reply(false, dataError(), 500);
                done('cloudflare', $settings['autoBlock'] ? 'Włączono automatyczne blokowanie skanerów w Cloudflare.' : 'Wyłączono automatyczne blokowanie skanerów w Cloudflare.');

            case 'trash-empty':
                $removed = 0;
                foreach (array_keys(trashItems()) as $item)
                    if (deleteFromTrash($item))
                        $removed++;
                done('purge', 'Opróżniono kosz: ' . countLabel($removed) . ' usunięte na zawsze.');
        }

        reply(false, 'Nieznana akcja.', 400);
    }

    // ---- Page -----------------------------------------------------------------

    handleLoginRequest(siteRoot() . 'admin/');

    $user = siteUser();
    $allowed = $user !== null && isPanelAdminId($user['id']);

    // ?kosz=ID shows what is in the trash: the file itself, &plik=... a file
    // of a folder, &pliki the pictures and films of a folder
    if (isset($_GET['kosz'])) {
        if (!$allowed) {
            http_response_code(403);
            exit;
        }
        if (isset($_GET['pliki'])) {
            list($files, $count) = trashFolderMedia((string)$_GET['kosz'], TRASH_PREVIEW_MAX);
            reply(true, '', 200, ['files' => array_map(function ($file) {
                return ['path' => $file[0], 'size' => formatSize($file[1]), 'video' => isVideo($file[0])];
            }, $files), 'count' => $count]);
        }

        $file = trashFile((string)$_GET['kosz'], (string)($_GET['plik'] ?? ''));
        if ($file)
            sendMedia($file[0], 'private, max-age=3600');
        else
            http_response_code(404);
        exit;
    }

    $flash = takeFlash();
    // the login page itself is a 200, as Discord shows no link preview for a 401
    if (!$allowed)
        http_response_code(!authConfigured() ? 503 : ($user ? 403 : 200));

    // ?konto=ID shows the profile of an account instead (inc/panel-account.php),
    // ?szukaj=... what the search found (inc/panel-search.php)
    $profileId = isset($_GET['konto']) && preg_match('/^\d{17,20}$/', (string)$_GET['konto']) ? (string)$_GET['konto'] : null;
    $searchQuery = $profileId === null && isset($_GET['szukaj']) && trim((string)$_GET['szukaj']) !== '' ? cutText(trim((string)$_GET['szukaj']), 100) : null;
    if ($allowed && $profileId !== null)
        require __DIR__ . '/../inc/panel-account.php';
    if ($allowed && $searchQuery !== null)
        require __DIR__ . '/../inc/panel-search.php';

    if ($allowed && $profileId === null && $searchQuery === null) {
        $autoChecks = checksLastHour(botHistory());

        $logins = readData('logins');

        // both gallery lists, the config entries first; config "true" means everyone
        $lists = [];
        foreach (ACCESS_LISTS as $list => $constant) {
            $entries = [];
            $config = configList($constant);
            foreach ($config === true ? [] : $config as $id)
                $entries[] = ['id' => (string)$id, 'note' => '', 'config' => true];
            foreach (panelList($list) as $id => $entry)
                $entries[] = ['id' => (string)$id, 'note' => $entry['note'] ?? '', 'config' => false, 'added' => $entry['added'] ?? 0];
            $lists[$list] = ['everyone' => $config === true, 'entries' => $entries];
        }

        // accounts that read the API through their role on the bot's server, as the bot said it last
        $knownRoles = readData('roles');
        $apiByRole = [];
        foreach ($knownRoles as $id => $known) {
            if (realPanelAdminId($id))
                continue;
            foreach (API_ROLES as $role) {
                if (!empty($known['roles'][$role])) {
                    $apiByRole[(string)$id] = $role;
                    break;
                }
            }
        }
        foreach ($lists['apiViewers']['entries'] as $entry)
            unset($apiByRole[$entry['id']]);

        $panelAdmins = configList('PANEL_ADMINS');
        $notice = botNotice();

        // requests for access, newest first; ones of accounts let in meanwhile go away
        $stored = readData('requests');
        $kept = [];
        $requests = [];
        foreach (REQUEST_LABELS as $for => $label) {
            foreach ($stored[$for] ?? [] as $id => $request) {
                $id = (string)$id;
                if ($for === 'gallery' ? canViewGalleryId($id) : canViewApiId($id))
                    continue;
                $kept[$for][$id] = $request;
                $requests[] = $request + ['id' => $id, 'for' => $for];
            }
        }
        if ($kept != $stored)
            writeData('requests', $kept);
        usort($requests, function ($a, $b) { return $b['time'] <=> $a['time']; });

        // what deploy.sh put on the server: commit, its date and subject
        $deployed = null;
        $deployFile = dataDir() . '/deployed-info';
        if (is_file($deployFile)) {
            $lines = explode("\n", trim((string)file_get_contents($deployFile)));
            $deployed = ['hash' => $lines[0], 'date' => strtotime($lines[1] ?? '') ?: null, 'subject' => $lines[2] ?? '', 'at' => filemtime($deployFile)];
        } else if (is_file(dataDir() . '/deployed-commit')) {
            $deployed = ['hash' => trim((string)file_get_contents(dataDir() . '/deployed-commit')), 'date' => null, 'subject' => '', 'at' => filemtime(dataDir() . '/deployed-commit')];
        }
        $panelStats = panelStats($galleryDir);
        $stats = $panelStats['gallery'];
        $dataBytes = $panelStats['data']['bytes'];
        $dataRoom = $panelStats['data'];
        $thumbs = $panelStats['thumbs'];
        $statsAgo = $panelStats['time'];
        $dataHeavy = ($dataRoom['trash'] + $dataRoom['webp']) >= PANEL_DATA_WARN_BYTES;
        $diskFree = @disk_free_space($galleryDir);
        $diskTotal = @disk_total_space($galleryDir);
        $sessionsSince = sessionsValidSince();
        $cronLast = botCronLast();
        $cronLate = $cronLast === null || time() - $cronLast > CRON_LATE;
        $cronCommand = "echo '* * * * * www-data php " . str_replace('\\', '/', realpath(__DIR__ . '/../inc/check-bot.php'))
            . " > /dev/null 2>&1' > /etc/cron.d/sanakan-status";
        $thumbFiles = $thumbs['files'];
        $thumbBytes = $thumbs['bytes'];
        $specFile = botFile('swagger.json');
        $privateTime = botPrivateCommandsTime();
        $heartbeatLast = botHeartbeatTime();
        $system = systemStats();

        // availability of the site (inc/diag.php): the rounds of 24 h, failures, traffic of the last hour
        $now = time();
        $diagRounds = diagRounds($now - 86400);
        $diagLast = $diagRounds ? end($diagRounds) : null;
        // the processor use comes from the background check, so opening the panel
        // does not read /proc/stat and wait 250 ms for a second reading
        $cpuNow = diagCpuNow($diagRounds);
        $diagEpisodes = diagEpisodes($diagRounds);
        $diagHour = diagMergeTraffic(array_filter($diagRounds, function ($round) use ($now) { return $round['t'] >= $now - 3600; }), 10);
        $diagSlow = diagSlowEntries();
        $diagScanners = diagScanners();
        $diagPages = diagPages();
        // what Cloudflare blocks, read when blocking is set up
        $cfItems = null;
        $cfError = null;
        if (cloudflareConfigured())
            [$cfItems, $cfError] = cloudflareBlocked();
        $diagMarks = diagMarks($diagScanners, $cfItems);
        $autoBlock = readData('autoblock');
        $diagCron = "echo '* * * * * www-data php " . str_replace('\\', '/', realpath(__DIR__ . '/../inc/check-site.php'))
            . " > /dev/null 2>&1' > /etc/cron.d/sanakan-site";

        purgeTrash();
        $trash = trashItems();
        $shares = activeShares();
        uasort($shares, function ($a, $b) { return $b['created'] <=> $a['created']; });
        $history = historyEntries(100);
    }

    $csrf = $user ? siteCsrf() : '';
    $root = siteRoot();

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

        return 'panel' . (!empty($entry['added']) ? ', ' . date('d.m.Y', $entry['added']) : '')
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
        [$files, $bytes] = $folder !== null ? folderUse($galleryDir . '/' . $folder) : [0, 0];
        if (isGalleryUploaderId($id)) {
            $limit = userFilesLimit($id);
            $use = $files . ' z ' . $limit . ' ' . plural($limit, 'zdjęcia', 'zdjęć', 'zdjęć') . ', ' . formatSize($bytes) . ' z ' . formatSize(USER_TOTAL_MAX_BYTES);
            $cells['folder'] = [$files . '/' . $limit, 'on', 'Własny folder: ' . $use];
            $details[] = ['Własny folder', $join(($folder !== null ? displayPath($folder) . ', ' : 'jeszcze nie założony, ') . $use, accessFrom('galleryUploaders', $id, $logins))];
        } else if ($folder !== null) {
            $cells['folder'] = ['zostaje', '', 'Dostęp odebrany, folder został: ' . $files . ' ' . plural($files, 'plik', 'pliki', 'plików')];
            $details[] = ['Własny folder', displayPath($folder) . ' został po odebraniu dostępu, ' . $files . ' ' . plural($files, 'plik', 'pliki', 'plików')];
        } else {
            $cells['folder'] = ['—', '', 'Bez własnego folderu'];
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
?>
<!DOCTYPE html>
<html lang="pl"<?=hudHtmlAttributes($user)?>>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex" />
<?=metaTags('Panel', 'Panel administracyjny strony Sanakan, tylko dla administracji.', '/admin/', 'admin')?>
  <title><?=$allowed && $profileId !== null ? e($profile['name']) . ' &middot; Konto &middot; Panel' : ($allowed && $searchQuery !== null ? 'Szukaj &middot; Panel' : 'Panel')?> &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=ec855414d1" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=a6863ef8aa" type="text/css" rel="stylesheet" />
  <link href="../css/status.css?v=998eb1311f" type="text/css" rel="stylesheet" />
  <link href="../css/admin.css?v=415f163d30" type="text/css" rel="stylesheet" />
</head>

<body class="admin-page" data-csrf="<?=e($csrf)?>">
  <main class="content">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
<?php if ($user): ?>
        <?=accountMenuHtml($user, siteRoles(), $_SERVER['REQUEST_URI'] ?? '')?>
<?php endif; ?>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title"><?=$allowed && $profileId !== null ? 'Konto' : ($allowed && $searchQuery !== null ? 'Szukaj' : 'Panel')?></h1>
<?php if ($allowed): ?>
      <form class="panel-search" method="get" action="./" role="search">
        <input type="search" name="szukaj" value="<?=e($searchQuery ?? '')?>" placeholder="Konto, ID albo adres IP" aria-label="Szukaj konta albo adresu IP" />
        <button type="submit" class="admin-btn">Szukaj</button>
      </form>
<?php endif; ?>
    </header>

<?php if (!$allowed): ?>
    <section class="locked hud-corners">
      <svg class="lock-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="1.5" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
<?php if (!authConfigured()): ?>
      <h2>Panel jest wyłączony</h2>
      <p>Logowanie nie jest jeszcze skonfigurowane na serwerze.</p>
<?php elseif ($user): ?>
      <h2>Brak dostępu</h2>
      <p>Konto <?=e($user['name'])?> nie ma dostępu do panelu. Wyloguj się, jeśli chcesz użyć innego konta.</p>
<?php else: ?>
      <h2>Panel administratora</h2>
      <p>Zaloguj się kontem Discord, które ma dostęp do panelu.</p>
      <a class="admin-btn primary" href="?login">Zaloguj przez Discord</a>
<?php endif; ?>
    </section>
<?php elseif ($profileId !== null): ?>
<?php profilePage($profile); ?>
<?php elseif ($searchQuery !== null): ?>
<?php searchPage($search); ?>
<?php else: ?>
    <div class="admin-layout">
      <nav class="admin-nav" id="admin-nav" aria-label="Sekcje panelu"></nav>
      <div class="panel-grid">

<?php $alertCount = count($requests ?: []) + ($cronLate ? 1 : 0) + ($dataHeavy ? 1 : 0); ?>
<?php if ($alertCount): ?>
      <h2 class="panel-section" data-section="wymaga" data-label="Wymaga uwagi" data-count="<?=$alertCount?>">Wymaga uwagi</h2>
<?php endif; ?>

<?php if ($cronLate): ?>
      <section class="card wide alarm" role="alert" data-state="warn" data-sum="<?=e($cronLast === null ? 'brak' : ago($cronLast))?>">
        <h2><i>!</i>Cron nie sprawdza bota</h2>
        <p><?=$cronLast === null
            ? 'Automatyczne sprawdzanie jeszcze ani razu nie zadziałało.'
            : 'Ostatnie automatyczne sprawdzenie było ' . e(ago($cronLast)) . ' (' . e(date('d.m H:i', $cronLast)) . ').'?>
          Bez niego historia dostępności ma dziury, a awarie, gdy nikt nie odwiedza strony, nie są zapisywane.</p>
        <p class="hint">Zadanie cron (na serwerze, jako root): <code><?=e($cronCommand)?></code><br />Czy cron działa: <code>systemctl status cron</code></p>
      </section>
<?php endif; ?>

<?php if ($requests): ?>
      <section class="card wide requests" data-state="warn" data-sum="<?=count($requests)?>">
        <h2><i>+</i>Prośby o dostęp</h2>
        <p class="hint">Konta, które zalogowały się do galerii albo do API bez dostępu i o niego poprosiły.</p>
        <ul class="people">
<?php foreach ($requests as $request): $id = $request['id']; ?>
          <li>
            <?=accountCell($id, $logins)?>
            <span class="role"><?=$request['for'] === 'api' ? 'API' : 'galeria'?></span>
<?php if (($request['note'] ?? '') !== ''): ?>
            <span class="note">„<?=e($request['note'])?>”</span>
<?php endif; ?>
            <span class="muted"><?=e(ago($request['time']))?></span>
            <span class="login-actions">
<?php if ($request['for'] === 'api'): ?>
              <button type="button" class="admin-btn small" data-action="grant" data-list="apiViewers" data-id="<?=e($id)?>">+ Dostęp do API</button>
<?php else: ?>
              <button type="button" class="admin-btn small" data-action="grant" data-list="galleryViewers" data-id="<?=e($id)?>">+ Oglądający</button>
              <button type="button" class="admin-btn small" data-action="grant" data-list="galleryUploaders" data-id="<?=e($id)?>">+ Własny folder</button>
              <button type="button" class="admin-btn small" data-action="grant" data-list="galleryAdmins" data-id="<?=e($id)?>">+ Admin galerii</button>
<?php endif; ?>
              <button type="button" class="admin-btn small danger" data-action="request-dismiss" data-for="<?=e($request['for'])?>" data-id="<?=e($id)?>" data-confirm="Odrzucić prośbę <?=e(accountLabel($id, $logins))?> o dostęp do <?=e(REQUEST_LABELS[$request['for']])?>?">Odrzuć</button>
            </span>
          </li>
<?php endforeach; ?>
        </ul>
      </section>
<?php endif; ?>

<?php if ($dataHeavy): ?>
      <section class="card wide alarm" role="alert" data-state="warn" data-sum="<?=e(formatSize($dataRoom['trash'] + $dataRoom['webp']))?>">
        <h2><i>!</i>Dużo danych w inc/data</h2>
        <p>Kosz zajmuje <b><?=e(formatSize($dataRoom['trash']))?></b>, a podglądy WebP <b><?=e(formatSize($dataRoom['webp']))?></b> &mdash; razem <?=e(formatSize($dataRoom['trash'] + $dataRoom['webp']))?> z <?=e(formatSize(PANEL_DATA_WARN_BYTES))?>. Kosz opróżnia się po <?=TRASH_DAYS?> dniach, podglądy po dwóch godzinach; możesz je też usunąć w kartach niżej.</p>
      </section>
<?php endif; ?>

      <h2 class="panel-section" data-section="przeglad" data-label="Przegląd">Przegląd</h2>

<?php $botStateNow = botState(); $botStatus = shownStatus($botStateNow); ?>
      <section class="card wide" data-clock="1" data-state="<?=$botStatus === 'online' ? 'ok' : ($botStatus === 'offline' ? 'bad' : 'warn')?>">
        <h2><i>01</i>Status bota</h2>
<?=statusSummary()?>
<?php $clock = serverClock(); ?>
        <p class="server-clock" data-server-ms="<?=$clock['ms']?>" data-zone="<?=e($clock['zone'])?>">
          Czas serwera <b class="server-clock-now"><?=e(date('H:i:s'))?></b>
          <span class="muted">(<?=e($clock['zone'])?>, UTC<?=e(date('P'))?><?=$clock['system'] !== '' && $clock['system'] !== $clock['zone'] ? '; system: ' . e($clock['system']) : ''?>)</span>
<?php if ($clock['ntp'] !== null): ?>
          &middot; <?=$clock['ntp'] ? 'NTP: zsynchronizowany' : '<b class="warn">NTP: niezsynchronizowany</b>'?>
<?php endif; ?>
          &middot; <span class="server-clock-drift"></span>
        </p>
        <p class="hint status-more">Bota sprawdza cron co minutę. Wykresy, czasy odpowiedzi i awarie: <a href="<?=e($root)?>state/">strona statusu</a>.</p>

        <form class="notice-form" data-action="notice">
          <h3>Ogłoszenie</h3>
          <p class="hint">Widać je na stronie statusu. W czasie przerwy technicznej status bota (także kropka na stronie głównej) pokazuje „nie przeszkadzać”, a awarie są oznaczane jako planowane.</p>
<?php if ($notice): ?>
          <p class="notice-now">Teraz widać: <b><?=e(noticeText($notice))?></b><?=!empty($notice['to']) ? ' <span class="muted">(do ' . e(date('j.m H:i', $notice['to'])) . ')</span>' : ''?></p>
<?php endif; ?>
          <textarea name="text" rows="2" maxlength="<?=NOTICE_LENGTH?>" placeholder="Np. Dziś wieczorem aktualizacja bota, przez chwilę może nie odpowiadać." aria-label="Treść ogłoszenia"><?=e($notice['text'] ?? '')?></textarea>
          <div class="notice-fields">
            <label class="admin-check"><input type="checkbox" name="maintenance" value="1"<?=!empty($notice['maintenance']) ? ' checked' : ''?> /> Przerwa techniczna</label>
            <span class="notice-time">Od <input type="datetime-local" name="from" aria-label="Od" value="<?=e(fieldTime($notice['maintenance']['from'] ?? null))?>" /></span>
            <span class="notice-time">Do <input type="datetime-local" name="to" aria-label="Do" value="<?=e(fieldTime($notice['to'] ?? null))?>" /></span>
          </div>
          <p class="hint">„Od” liczy się tylko dla przerwy (puste: od teraz). „Do” to koniec przerwy, a bez przerwy czas, kiedy ogłoszenie zniknie (puste: zostaje, aż się je usunie).</p>
          <div class="notice-actions">
            <button type="submit" class="admin-btn primary">Zapisz ogłoszenie</button>
<?php if ($notice): ?>
            <button type="button" class="admin-btn danger" data-action="notice-clear" data-confirm="Usunąć ogłoszenie?">Usuń</button>
<?php endif; ?>
          </div>
        </form>
      </section>

      <h2 class="panel-section" data-section="dostep" data-label="Dostęp i konta">Dostęp i konta</h2>

<?php $number = 2; $test = testRights($user['id']); ?>
      <section class="card wide" data-sum="<?=$test ? 'włączony' : 'wyłączony'?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Podgląd z innymi uprawnieniami</h2>
        <p class="hint">Na chwilę zmienia twoje uprawnienia na całej stronie, żeby zobaczyć ją na żywo tak, jak widzi ją konto z takimi. Tylko twoje konto i tylko w tej sesji; pasek na dole każdej strony pokazuje, co jest zmienione, i przywraca twoje. Panel daje też API, więc API przez rolę na serwerze sprawdzisz bez panelu.</p>
<?php if ($test): ?>
        <p class="test-now">Teraz: <b><?=e(testRightsText($test))?></b> do <?=e(date('H:i', $test['until']))?>
          <button type="button" class="admin-btn small" data-action="test-off">Wróć do swoich</button></p>
<?php endif; ?>
        <form class="test-form" data-action="test-rights">
          <label>Panel
            <select name="panel"><option value="">bez zmian</option><option value="1">z panelem</option><option value="0" selected>bez panelu</option></select>
          </label>
          <label>Galeria
            <select name="gallery"><option value="">bez zmian</option>
<?php foreach (TEST_GALLERY as $key => $label): ?>
              <option value="<?=e($key)?>"<?=$key === 'none' ? ' selected' : ''?>><?=e($label)?></option>
<?php endforeach; ?>
            </select>
          </label>
          <label>Prywatna galeria
            <select name="private"><option value="">bez zmian</option><option value="1">tak</option><option value="0" selected>nie</option></select>
          </label>
          <label>Lista API
            <select name="api"><option value="">bez zmian</option><option value="1">na liście</option><option value="0" selected>poza listą</option></select>
          </label>
          <label>Rola na serwerze bota
            <select name="role"><option value="">bez zmian</option>
<?php foreach (BOT_ROLES as $key => [$level, $badgeLabel, $name]): ?>
              <option value="<?=e($key)?>"<?=$key === 'user' ? ' selected' : ''?>><?=e($name)?> (LV.<?=$level?>)</option>
<?php endforeach; ?>
              <option value="<?=TEST_ROLE_OUT?>">poza serwerem</option>
            </select>
          </label>
          <label>Na
            <select name="minutes">
<?php foreach (TEST_MINUTES as $minutes): ?>
              <option value="<?=$minutes?>"<?=$minutes === 15 ? ' selected' : ''?>><?=$minutes < 60 ? $minutes . ' min' : ($minutes / 60) . ' godz.'?></option>
<?php endforeach; ?>
            </select>
          </label>
          <button type="submit" class="admin-btn primary">Włącz podgląd</button>
        </form>
      </section>

      <section class="card" data-sum="<?=$panelAdmins === true ? 'wszyscy' : count($panelAdmins)?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Dostęp do panelu</h2>
        <ul class="people">
<?php foreach ($panelAdmins === true ? [] : $panelAdmins as $id): ?>
          <li><?=accountCell($id, $logins)?><span class="badge-config" title="Ustawione w inc/config.php">config</span></li>
<?php endforeach; ?>
        </ul>
        <p class="hint">Listę zmienia się w <code>inc/config.php</code> (<code>PANEL_ADMINS</code>), panel nie może nadawać dostępu do samego siebie.</p>
      </section>

<?php foreach ($lists as $list => $info): ?>
      <section class="card" data-sum="<?=$info['everyone'] ? 'wszyscy' : count($info['entries'])?>">
        <h2><i><?=sprintf('%02d', $number++)?></i><?=e(LIST_CARDS[$list][0])?></h2>
        <p class="hint"><?=e(LIST_CARDS[$list][1])?></p>
<?php if ($info['everyone']): ?>
        <p class="everyone">Każde konto Discord (<code><?=e(ACCESS_LISTS[$list])?> = true</code> w konfiguracji).</p>
<?php endif; ?>
        <ul class="people">
<?php foreach ($info['entries'] as $entry): ?>
          <li>
            <?=accountCell($entry['id'], $logins)?>
<?php if ($entry['note'] !== ''): ?>
            <span class="note"><?=e($entry['note'])?></span>
<?php endif; ?>
<?php if ($entry['config']): ?>
            <span class="badge-config" title="Ustawione w inc/config.php">config</span>
<?php else: ?>
            <button type="button" class="admin-btn danger small" data-action="revoke" data-list="<?=e($list)?>" data-id="<?=e($entry['id'])?>" data-confirm="Odebrać dostęp: <?=e(accountLabel($entry['id'], $logins))?>?">Usuń</button>
<?php endif; ?>
          </li>
<?php endforeach; ?>
<?php if ($list === 'apiViewers'): foreach ($apiByRole as $id => $role): ?>
          <li>
            <?=accountCell($id, $logins)?>
            <span class="role bot role-<?=e($role)?>" title="Rola na serwerze Sanakana, według bota">rola: <?=e(BOT_ROLES[$role][2])?></span>
          </li>
<?php endforeach; endif; ?>
<?php if (!$info['entries'] && !$info['everyone'] && ($list !== 'apiViewers' || !$apiByRole)): ?>
          <li class="nobody">Nikogo jeszcze nie ma.</li>
<?php endif; ?>
        </ul>
        <form class="grant" data-action="grant">
          <input type="hidden" name="list" value="<?=e($list)?>" />
          <input type="text" name="id" inputmode="numeric" pattern="\d{17,20}" placeholder="ID konta Discord" aria-label="ID konta Discord" required />
          <input type="text" name="note" maxlength="60" placeholder="Notatka (opcjonalnie)" aria-label="Notatka" />
          <button type="submit" class="admin-btn primary">Dodaj</button>
        </form>
      </section>
<?php endforeach; ?>

      <section class="card wide" data-sum="<?=count($logins)?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Ostatnie logowania</h2>
        <p class="hint">Każdy, kto zalogował się przez Discord w galerii, w API albo w panelu, także bez dostępu. Stąd najłatwiej komuś go nadać. Kolorowy poziom to najwyższa rola na serwerze bota (wszystkie w dymku), odświeżana, gdy konto odwiedza stronę; galerii nie daje. Kolumny to dostępy na stronie, jaśniejsze mocniejsze; strzałka na końcu wiersza pokazuje, skąd są, i przyciski do nadania nowych.</p>
<?php if (!$logins): ?>
        <p class="nobody">Nikt się jeszcze nie logował.</p>
<?php else: ?>
        <p class="logins-all"><button type="button" class="admin-btn small" id="logins-toggle" aria-expanded="false">Rozwiń wszystkie</button></p>
        <div class="logins">
          <div class="login-head" aria-hidden="true">
            <span>Konto</span><span>Ostatnio</span><span>Rola</span>
<?php foreach (ACCESS_COLUMNS as $label): ?>
            <span><?=e($label)?></span>
<?php endforeach; ?>
            <span></span>
          </div>
<?php foreach ($logins as $id => $login):
        $id = (string)$id;
        // the highest role on the bot's server, the others in its title
        $serverRoles = $knownRoles[$id]['roles'] ?? null;
        $badge = roleBadge($serverRoles);
        [$cells, $details] = accessColumns($id, $logins, $galleryDir, $serverRoles);
?>
          <div class="login-row">
            <span class="login-who"><?=accountCell($id, $logins)?></span>
            <span class="login-when"><?=e(ago($login['last'] ?? 0))?> &middot; <?=(int)($login['count'] ?? 0)?>&times;</span>
            <span class="login-lv">
<?php if ($badge): ?>
              <span class="lv role-<?=e($badge['key'])?>" title="<?=e(roleNames($serverRoles) ? 'Role na serwerze Sanakana: ' . implode(', ', roleNames($serverRoles)) : $badge['title'])?>">LV.<?=$badge['level']?> <?=e($badge['name'])?></span>
<?php endif; ?>
            </span>
<?php foreach (ACCESS_COLUMNS as $column => $label): [$text, $level, $title] = $cells[$column]; ?>
            <span class="access-cell<?=$level !== '' ? ' ' . $level : ''?>" data-column="<?=e($label)?>" title="<?=e($title)?>"><?=e($text)?></span>
<?php endforeach; ?>
            <button type="button" class="login-more" aria-expanded="false" title="Skąd te uprawnienia i nadawanie nowych">
              <svg class="account-icon" viewBox="0 0 24 24" aria-hidden="true"><?=ACCOUNT_ICONS['caret']?></svg><span class="visually-hidden">Szczegóły</span>
            </button>
            <div class="login-details" hidden>
              <dl class="server">
<?php foreach ($details as [$label, $text]): ?>
                <dt><?=e($label)?></dt>
                <dd><?=e($text)?></dd>
<?php endforeach; ?>
              </dl>
              <div class="login-actions">
<?php if (!canViewGalleryId($id)): ?>
                <button type="button" class="admin-btn small" data-action="grant" data-list="galleryViewers" data-id="<?=e($id)?>">+ Oglądający</button>
<?php endif; ?>
<?php if (!isGalleryAdminId($id) && !isGalleryUploaderId($id)): ?>
                <button type="button" class="admin-btn small" data-action="grant" data-list="galleryUploaders" data-id="<?=e($id)?>">+ Własny folder</button>
<?php endif; ?>
<?php if (!isGalleryAdminId($id)): ?>
                <button type="button" class="admin-btn small" data-action="grant" data-list="galleryAdmins" data-id="<?=e($id)?>">+ Admin galerii</button>
<?php endif; ?>
<?php if (!canSeePrivateGalleryId($id)): ?>
                <button type="button" class="admin-btn small" data-action="grant" data-list="galleryPrivate" data-id="<?=e($id)?>">+ Prywatny folder</button>
<?php endif; ?>
<?php if (!canViewApiId($id)): ?>
                <button type="button" class="admin-btn small" data-action="grant" data-list="apiViewers" data-id="<?=e($id)?>">+ API</button>
<?php endif; ?>
                <a class="admin-btn small" href="?konto=<?=e($id)?>">Profil konta, odbieranie</a>
              </div>
            </div>
          </div>
<?php endforeach; ?>
        </div>
<?php endif; ?>
        <p class="logout-all">
          <span class="hint"><?=$sessionsSince ? 'Ostatnio wylogowano wszystkich ' . e(ago($sessionsSince)) . '.' : 'Po odebraniu komuś dostępu można też zakończyć wszystkie sesje.'?></span>
          <button type="button" class="admin-btn small danger" data-action="logout-all" data-confirm="Wylogować wszystkich z galerii i panelu? Ty zostaniesz zalogowany, inni muszą zalogować się jeszcze raz.">Wyloguj wszystkich</button>
        </p>
      </section>

      <h2 class="panel-section" data-section="galeria" data-label="Galeria">Galeria</h2>

      <section class="card wide" data-sum="<?=count($trash)?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Kosz</h2>
        <p class="hint">Usunięte w galerii pliki i foldery leżą tu <?=TRASH_DAYS?> dni, potem znikają same. Przywrócone wracają do swojego folderu.</p>
<?php if (!$trash): ?>
        <p class="nobody">Kosz jest pusty.</p>
<?php else: ?>
        <div class="trash trash-list" id="trash-list" data-keep-scroll>
<?php foreach ($trash as $id => $item):
        $daysLeft = max(0, (int)ceil((($item['deleted'] ?? 0) + TRASH_DAYS * 86400 - time()) / 86400));
        $kind = !empty($item['folder']) ? 'folder' : (isImage($item['name']) ? 'image' : (isVideo($item['name']) ? 'video' : null));
?>
          <div class="trash-row">
            <span class="trash-name">
              <b><?=e($item['name'])?><?=!empty($item['folder']) ? '/' : ''?></b>
              <code><?=e(galleryPath($item['from']))?></code>
            </span>
            <span class="trash-info"><?=e(formatSize($item['size'] ?? 0))?> &middot; usunięte <?=e(ago($item['deleted'] ?? 0))?><?=empty($item['by']) ? '' : ' przez ' . e($item['by'])?> &middot; zostało <?=$daysLeft?> <?=plural($daysLeft, 'dzień', 'dni', 'dni')?><?=empty($item['hidden']) ? '' : ' &middot; <b>użytkownik usunął je ze swojego kosza</b>'?></span>
            <span class="login-actions">
<?php if ($kind): ?>
              <button type="button" class="admin-btn small trash-peek" data-item="<?=e($id)?>" data-kind="<?=$kind?>" aria-expanded="false">Podgląd</button>
<?php endif; ?>
              <button type="button" class="admin-btn small" data-action="restore" data-item="<?=e($id)?>">Przywróć</button>
              <button type="button" class="admin-btn small danger" data-action="trash-delete" data-item="<?=e($id)?>" data-confirm="Usunąć na zawsze <?=e(galleryPath($item['from']))?>? Tego nie da się cofnąć.">Usuń na zawsze</button>
            </span>
<?php if ($kind): ?>
            <div class="trash-preview" hidden></div>
<?php endif; ?>
          </div>
<?php endforeach; ?>
        </div>
        <p class="trash-all"><button type="button" class="admin-btn small danger" data-action="trash-empty" data-confirm="Usunąć na zawsze wszystko z kosza (<?=e(countLabel(count($trash)))?>)? Tego nie da się cofnąć.">Opróżnij kosz</button></p>
<?php endif; ?>
      </section>

      <section class="card wide" data-sum="<?=count($shares)?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Udostępnione linki</h2>
        <p class="hint">Foldery galerii udostępnione linkiem: każdy, kto go ma, ogląda je i pobiera bez logowania. Tworzy się je w galerii przyciskiem „Udostępnij” w folderze.</p>
<?php if (!$shares): ?>
        <p class="nobody">Żaden folder nie jest udostępniony.</p>
<?php else: ?>
        <div class="trash">
<?php foreach ($shares as $token => $share): $shareUrl = SITE_URL . $root . 'i/?s=' . $token; $shared = resolvePath($galleryDir, $share['rel'], true); ?>
          <div class="trash-row">
            <span class="trash-name">
              <a href="<?=e($root . 'i/' . folderUrl($shared[1] ?? $share['rel']))?>"><b><?=e(displayPath($shared[1] ?? $share['rel']))?></b></a>
              <code class="share-link"><?=e($shareUrl)?></code>
            </span>
            <span class="trash-info">od <?=e(ago($share['created']))?><?=($share['name'] ?? '') !== '' ? ', ' . e($share['name']) : ''?> &middot; <?=$share['expires'] === null ? 'bez końca' : 'do ' . e(date('d.m.Y H:i', $share['expires']))?><?=$shared ? '' : ' &middot; <b class="warn">folderu już nie ma</b>'?></span>
            <span class="login-actions">
              <button type="button" class="admin-btn small danger" data-action="share-revoke" data-token="<?=e($token)?>" data-confirm="<?=e('Wyłączyć link do ' . displayPath($shared[1] ?? $share['rel']) . '? Kto go ma, przestanie widzieć folder.')?>">Wyłącz</button>
            </span>
          </div>
<?php endforeach; ?>
        </div>
<?php endif; ?>
      </section>

      <section class="card wide" data-sum="<?=$stats['files']?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Galeria w liczbach</h2>
        <p class="hint">Policzone <?=e(ago($statsAgo))?>; odświeża się w tle raz na godzinę (cron), więc otwarcie panelu nie czeka na przejście galerii.
          <button type="button" class="admin-btn small" data-action="refresh-stats" data-confirm="Przeliczyć galerię od nowa? Przy dużej galerii to chwilę zajmie.">Przelicz teraz</button></p>
        <div class="stats-summary">
          <span><b><?=$stats['files']?></b> <?=plural($stats['files'], 'plik', 'pliki', 'plików')?></span>
          <span><b><?=e(formatSize($stats['bytes']))?></b> razem</span>
          <span><b><?=count($stats['folders'])?></b> <?=plural(count($stats['folders']), 'folder', 'foldery', 'folderów')?> w i/</span>
        </div>
<?php if ($diskTotal): $diskUsed = $diskTotal - $diskFree; $diskLow = $diskFree < $diskTotal * 0.1; ?>
        <div class="disk<?=$diskLow ? ' low' : ''?>">
          <div class="bar-head"><span>Dysk serwera<?=$diskLow ? ': <b class="warn">mało miejsca</b>' : ''?></span><b>wolne <?=e(formatSize($diskFree))?> z <?=e(formatSize($diskTotal))?></b></div>
          <div class="disk-bar" title="Zajęte <?=e(formatSize($diskUsed))?>, w tym galeria <?=e(formatSize($stats['bytes']))?>">
            <span class="gallery" style="width: <?=share($stats['bytes'], $diskTotal)?>"></span><span class="used" style="width: <?=share(max(0, $diskUsed - $stats['bytes']), $diskTotal)?>"></span>
          </div>
          <div class="timeline-legend"><span><i class="gallery"></i>galeria <i class="used"></i>reszta serwera <i class="none"></i>wolne</span></div>
        </div>
<?php endif; ?>
<?php if ($stats['files']): ?>
        <div class="stats-grid">
          <div>
            <h3>Foldery</h3>
            <ul class="stat-list">
<?php foreach ($stats['folders'] as $name => $folder): ?>
              <li style="--share: <?=share($folder['bytes'], $stats['bytes'])?>"><a href="<?=e($root . 'i/' . folderUrl((string)$name))?>"><?=e($name === '' ? 'i/ (bez folderu)' : 'i/' . $name)?></a><span><?=$folder['files']?> &middot; <?=e(formatSize($folder['bytes']))?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
          <div>
            <h3>Typy plików</h3>
            <ul class="stat-list">
<?php foreach ($stats['types'] as $type => $group): ?>
              <li style="--share: <?=share($group['bytes'], $stats['bytes'])?>"><span><?=e((string)$type)?></span><span><?=$group['files']?> &middot; <?=e(formatSize($group['bytes']))?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
          <div>
            <h3>Największe pliki</h3>
            <ol class="stat-list">
<?php foreach ($stats['largest'] as [$rel, $size]): ?>
              <li style="--share: <?=share($size, $stats['largest'][0][1])?>"><a href="<?=e($root . 'i/' . fileUrl($rel))?>" target="_blank" rel="noopener" title="<?=e(galleryPath($rel))?>"><?=e(galleryPath($rel))?></a><span><?=e(formatSize($size))?></span></li>
<?php endforeach; ?>
            </ol>
          </div>
        </div>
<?php else: ?>
        <p class="nobody">Galeria jest pusta.</p>
<?php endif; ?>
      </section>

      <section class="card wide" data-sum="<?=webpQuality('jpg')?> JPG">
        <h2><i><?=sprintf('%02d', $number++)?></i>Jakość konwersji na WebP</h2>
        <p class="hint">Z jaką jakością (<?=WEBP_QUALITY_MIN?>–<?=WEBP_QUALITY_MAX?>) zdjęcia zapisują się jako WebP. Niżej = mniejszy plik i słabsza jakość; wynik zapisuje się tylko, gdy wyjdzie mniejszy. Ręczna zmiana w galerii ma własny suwak.</p>
        <form class="quality-form" data-action="quality">
          <label>JPG <input type="number" name="qualityJpg" min="<?=WEBP_QUALITY_MIN?>" max="<?=WEBP_QUALITY_MAX?>" value="<?=webpQuality('jpg')?>" /></label>
          <label>PNG <input type="number" name="qualityPng" min="<?=WEBP_QUALITY_MIN?>" max="<?=WEBP_QUALITY_MAX?>" value="<?=webpQuality('png')?>" /></label>
          <label>GIF <input type="number" name="qualityGif" min="<?=WEBP_QUALITY_MIN?>" max="<?=WEBP_QUALITY_MAX?>" value="<?=webpQuality('gif')?>" /></label>
          <label>AVIF/HEIC <input type="number" name="qualityAvif" min="<?=WEBP_QUALITY_MIN?>" max="<?=WEBP_QUALITY_MAX?>" value="<?=webpQuality('avif')?>" /></label>
          <button type="submit" class="admin-btn primary">Zapisz</button>
        </form>
      </section>

      <section class="card wide" data-sum="<?=$history ? e(ago($history[0]['time'] ?? 0)) : 'brak'?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Historia zmian</h2>
        <p class="hint">Ostatnie zmiany w galerii i w panelu: kto, kiedy i co.</p>
<?php if (!$history): ?>
        <p class="nobody">Jeszcze nic się nie zmieniło.</p>
<?php else: ?>
        <ol class="history">
<?php foreach ($history as $entry): ?>
          <li>
            <time datetime="<?=e(date('c', $entry['time'] ?? 0))?>"><?=e(date('d.m H:i', $entry['time'] ?? 0))?></time>
            <span class="history-who"><?=e($entry['name'] ?: $entry['id'])?></span>
            <span class="history-text"><?=e($entry['text'] ?? '')?></span>
          </li>
<?php endforeach; ?>
        </ol>
<?php endif; ?>
      </section>

      <h2 class="panel-section" data-section="serwer" data-label="Serwer">Serwer</h2>

      <?php $diagCard = sprintf('%02d', $number++); $diagSub = 0; $diagLetter = function () use (&$diagSub, $diagCard) { return $diagCard . chr(64 + ++$diagSub); }; ?>
      <section class="card wide diag" data-sum="<?=count($diagEpisodes)?>">
        <h2><i><?=$diagCard?></i>Dostępność strony</h2>
        <p class="hint">Co 10 sekund serwer pyta stronę przez Cloudflare, tak jak odwiedzający, i bezpośrednio u siebie, z pominięciem Cloudflare, a dla porównania wiki. Obok zapisuje ruch z dziennika nginx. Gdy strona nie działa tylko przez Cloudflare (522), połączenia nie dochodzą do serwera. Gdy nie działa też na serwerze, zatyka się nginx albo PHP. Pomiary z <?=DIAG_KEEP_DAYS?> dni, pokazane 24 godziny.</p>
<?php if (!$diagRounds): ?>
        <p class="nobody">Jeszcze nie ma pomiarów. Ustawienie serwera opisuje lista niżej.</p>
<?php else:
        $ngNow = $diagLast['ng'] ?? null;
        $fpmNow = $diagLast['fpm'] ?? null;
        $tcpNow = $diagLast['tcp'] ?? [];
?>
<?=diagBar('Przez Cloudflare', $diagRounds, ['pub', 'pubphp'])?>
<?=diagBar('Na serwerze, bez Cloudflare', $diagRounds, ['loc', 'locphp'])?>
<?=diagBar('Wiki przez Cloudflare', $diagRounds, ['wiki'])?>
        <div class="bar-ends"><span>24 h temu</span><span>teraz</span></div>
        <div class="timeline-legend">
          <span><i class="ok"></i>odpowiadała <i class="warn"></i>wolno <i class="fail"></i>bez odpowiedzi <i class="none"></i>brak pomiarów</span>
        </div>

<?=diagTrafficChart($diagRounds)?>

        <div class="stats-summary diag-now" title="Ostatni pomiar, <?=e(date('H:i:s', $diagLast['t']))?>">
<?php if ($ngNow): ?>
          <span><b><?=$ngNow['a']?></b> <?=plural($ngNow['a'], 'połączenie', 'połączenia', 'połączeń')?> nginx</span>
<?php endif; ?>
<?php if ($fpmNow): ?>
          <span><b><?=$fpmNow['act']?>/<?=$fpmNow['tot']?></b> zajętych PHP-FPM<?=$fpmNow['q'] ? ', <b class="warn">' . $fpmNow['q'] . '</b> w kolejce' : ''?></span>
          <span><b><?=formatCount($fpmNow['mcr'])?></b> &times; pula pełna <span class="muted">od startu PHP-FPM</span></span>
<?php endif; ?>
<?php if (isset($tcpNow['lo'])): ?>
          <span><b><?=formatCount($tcpNow['lo'])?></b> odrzuconych połączeń <span class="muted">od startu serwera</span></span>
<?php endif; ?>
<?php if (isset($tcpNow['ct'])): ?>
          <span><b><?=formatCount($tcpNow['ct'])?></b> z <?=formatCount($tcpNow['ctm'])?> w conntrack</span>
<?php endif; ?>
        </div>

        <h3 class="diag-title"><i><?=$diagLetter()?></i>Awarie w ostatnich 24 godzinach<?=$diagEpisodes ? ' <span class="muted">' . count($diagEpisodes) . '</span>' : ''?></h3>
<?php if (!$diagEpisodes): ?>
        <p class="nobody">Strona cały czas odpowiadała.</p>
<?php else: ?>
        <div class="diag-episodes">
<?php foreach (array_slice($diagEpisodes, 0, 20) as $i => $episode): ?>
          <details class="diag-episode<?=$episode['ongoing'] ? ' ongoing' : ''?>"<?=$i === 0 ? ' open' : ''?>>
            <summary>
              <time><?=e(date(date('Y-m-d', $episode['from']) === date('Y-m-d') ? 'H:i:s' : 'd.m H:i:s', $episode['from']))?></time>
              <b><?=$episode['ongoing'] ? 'trwa' : e(diagSeconds($episode['to'] - $episode['from']))?></b>
              <span><?=e(diagVerdict($episode))?></span>
            </summary>
            <dl class="server">
<?php foreach (DIAG_PROBE_NAMES as $name => [$label]): if (!$episode['answers'][$name]) continue; ?>
              <dt><?=e(ucfirst($label))?></dt>
              <dd><?=e(implode(', ', array_map(function ($text, $times) { return $text . ($times > 1 ? ' ×' . $times : ''); }, array_keys($episode['answers'][$name]), $episode['answers'][$name])))?></dd>
<?php endforeach; ?>
              <dt>Połączenia TCP</dt>
              <dd><?=$episode['overflows'] === null ? 'brak danych' : ($episode['overflows'] ? '<b class="warn">' . formatCount($episode['overflows']) . ' odrzuconych</b> (pełna kolejka)' : 'żadne nie odrzucone')?><?=$episode['queue'] !== null ? ' · kolejka do ' . $episode['queue'] . ' · półotwartych do ' . $episode['syn'] : ''?></dd>
              <dt>nginx</dt>
              <dd><?=$episode['nginxActive'] === null ? 'brak danych' : 'do ' . $episode['nginxActive'] . ' połączeń' . ($episode['nginxUnhandled'] ? ' · <b class="warn">' . formatCount($episode['nginxUnhandled']) . ' nieobsłużonych</b> (worker_connections)' : '')?></dd>
              <dt>PHP-FPM</dt>
              <dd><?=$episode['fpmActive'] === null ? 'brak danych' : 'zajętych do ' . $episode['fpmActive'] . ' z ' . $episode['fpmTotal'] . ($episode['fpmQueue'] ? ' · <b class="warn">do ' . $episode['fpmQueue'] . ' w kolejce</b>' : '') . ($episode['fpmFull'] ? ' · <b class="warn">pula pełna ' . $episode['fpmFull'] . '×</b>' : '')?></dd>
              <dt>Obciążenie</dt>
              <dd><?=decimal($episode['load'], 2)?></dd>
              <dt>Ruch</dt>
              <dd><?=formatCount($episode['traffic']['n'])?> zapytań od minuty przed awarią do jej końca, w tym <?=formatCount($episode['traffic']['php'])?> do PHP · najwięcej <?=formatCount($episode['traffic']['peak'])?> w 10 s · błędy 4xx <?=formatCount($episode['traffic']['s4'])?>, 5xx <?=formatCount($episode['traffic']['s5'])?></dd>
            </dl>
            <?=diagIpList($episode['traffic']['ips'], $diagMarks)?>

          </details>
<?php endforeach; ?>
        </div>
<?php endif; ?>

        <h3 class="diag-title"><i><?=$diagLetter()?></i>Ostatnia godzina <span class="muted"><?=formatCount($diagHour['n'])?> zapytań, <?=formatCount($diagHour['php'])?> do PHP, najwięcej <?=formatCount($diagHour['peak'])?> w 10 s</span></h3>
        <?=diagIpList($diagHour['ips'], $diagMarks)?>

<?php if ($diagHour['paths']): ?>
        <div class="stats-grid">
          <div>
            <h3>Najczęstsze adresy stron</h3>
            <ul class="stat-list">
<?php foreach ($diagHour['paths'] as [$path, $n]): ?>
              <li style="--share: <?=share($n, $diagHour['paths'][0][1])?>"><span title="<?=e($path)?>"><?=e($path)?></span><span><?=formatCount($n)?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
        </div>
<?php endif; ?>
<?php endif; ?>

        <h3 class="diag-title"><i><?=$diagLetter()?></i>Skanery w ostatnich <?=DIAG_KEEP_DAYS?> dniach<?=$diagScanners ? ' <span class="muted">' . count($diagScanners) . ' ' . plural(count($diagScanners), 'adres', 'adresy', 'adresów') . ', ' . formatCount(array_sum(array_column($diagScanners, 'n'))) . ' zapytań</span>' : ''?></h3>
        <p class="hint">Adresy, które pytały o to, czego na tej stronie nie ma (<code>/.env</code>, <code>/wp-*</code>, obce pliki <code>.php</code>, kopie zapasowe), albo przedstawiły się jako narzędzie do skanowania. Liczone są wszystkie ich zapytania od pierwszego takiego.</p>
<?php if (cloudflareConfigured()): $autoOn = autoBlockEnabled(); ?>
        <form class="auto-block" data-action="auto-block">
          <label class="admin-check"><input type="checkbox" name="on" value="1"<?=$autoOn ? ' checked' : ''?> /> Blokuj skanery w Cloudflare automatycznie</label>
          <button type="submit" class="admin-btn small">Zapisz</button>
        </form>
        <p class="hint">Co minutę blokowany jest adres, który w ciągu doby zapytał o co najmniej <?=AUTO_BLOCK_PROBES?> różne ścieżki, o które nikt tu nie pyta; sam user agent skanera nie wystarcza. Nigdy: adresy, z których logowały się konta, Cloudflare i adresy lokalne, prawdziwe roboty wyszukiwarek i znane skanery badawcze (Google, Bing, Censys, Shodan i inne, sprawdzone w DNS w obie strony, więc podszywający się pod nie odpadają). Adres odblokowany ręcznie nie wraca na listę sam. Automatyczne blokady znikają po <?=AUTO_BLOCK_DAYS?> dniach.</p>
<?php if ($autoBlock): $autoBlocked = $autoBlock['blocked'] ?? []; $autoTrusted = $autoBlock['trusted'] ?? []; ?>
        <p class="hint">
          Zablokowane automatycznie: <b><?=count($autoBlocked)?></b><?=$autoBlocked ? ', ostatnio ' . e(ago(max(array_column($autoBlocked, 0)))) : ''?>
<?php if ($autoTrusted): ?>
          &middot; pominięte jako zaufane: <span title="<?=e(implode(', ', array_map(function ($target, $entry) { return $target . ' ' . $entry[0]; }, array_keys($autoTrusted), $autoTrusted)))?>"><?=count($autoTrusted)?> (<?=e(implode(', ', array_slice(array_unique(array_map(function ($entry) { return preg_replace('/^.*?([^.]+\.[^.]+)$/', '$1', $entry[0]); }, $autoTrusted)), 0, 4)))?>)</span>
<?php endif; ?>
<?php if (!empty($autoBlock['error']) && $autoBlock['error'][0] > time() - 86400): ?>
          &middot; <b class="warn">ostatni błąd <?=e(ago($autoBlock['error'][0]))?>: <?=e($autoBlock['error'][1])?></b>
<?php endif; ?>
        </p>
<?php endif; ?>
<?php endif; ?>
<?php if (!$diagScanners): ?>
        <p class="nobody">Żadnych skanerów.</p>
<?php else: ?>
        <div class="diag-scroll">
          <?=diagScannerList(array_slice($diagScanners, 0, 50, true), $diagMarks)?>

        </div>
<?php endif; ?>
<?php if ($cfItems): ?>

        <h3 class="diag-title"><i><?=$diagLetter()?></i>Zablokowane w Cloudflare <span class="muted"><?=count($cfItems)?> na liście <?=e(CLOUDFLARE_LIST)?></span></h3>
        <div class="diag-scroll">
          <ul class="diag-blocked">
<?php foreach ($cfItems as $item): ?>
            <li>
              <code><?=e($item['ip'])?></code>
              <span><?=e($item['comment'])?><?=$item['created'] ? ' <span class="muted">' . e(date('d.m.Y H:i', $item['created'])) . '</span>' : ''?></span>
              <button type="button" class="admin-btn small" data-action="cf-unblock" data-item="<?=e($item['id'])?>" data-ip="<?=e($item['ip'])?>" data-confirm="<?=e('Odblokować ' . $item['ip'] . '?')?>">Odblokuj</button>
            </li>
<?php endforeach; ?>
          </ul>
        </div>
<?php endif; ?>
<?php if ($diagPages): ?>

        <h3 class="diag-title"><i><?=$diagLetter()?></i>Czas PHP według stron <span class="muted">24 godziny, najwięcej czasu w sumie u góry</span></h3>
        <ul class="stat-list diag-pages">
<?php foreach ($diagPages as [$path, $n, $average, $slowest, $total]): ?>
          <li style="--share: <?=share($total, $diagPages[0][4])?>"><span title="<?=e($path)?>"><?=e($path)?></span><span><?=formatCount($n)?> &times; średnio <?=e(milliseconds($average))?> &middot; najdłużej <?=e(milliseconds($slowest))?></span></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
<?php if ($diagSlow): ?>

        <h3 class="diag-title"><i><?=$diagLetter()?></i>Wolne zapytania PHP <span class="muted">ostatnie z dziennika slowlog</span></h3>
        <div class="diag-scroll">
<?php foreach ($diagSlow as $entry): ?>
          <pre class="diag-slow"><?=e($entry)?></pre>
<?php endforeach; ?>
        </div>
<?php endif; ?>

        <h3 class="diag-title"><i><?=$diagLetter()?></i>Ustawienie</h3>
<?php
        $nginxProbe = $diagLast['p']['nginx'] ?? null;
        $fpmProbe = $diagLast['p']['fpm'] ?? null;
        $logReadable = is_readable(diagAccessLog());
        $cloudflareInLog = false;
        foreach ($diagHour['ips'] as $ip)
            $cloudflareInLog = $cloudflareInLog || diagIsCloudflare($ip['ip']);
?>
        <dl class="server">
          <dt>Pomiary</dt>
          <dd><?=!diagAvailable()
              ? '<b class="warn">brak rozszerzenia curl</b>: <code>apt-get install -y php8.1-curl &amp;&amp; systemctl restart php8.1-fpm</code>'
              : ($diagLast && time() - $diagLast['t'] < 180
                  ? 'działają: ostatni ' . e(ago($diagLast['t']))
                  : '<b class="warn">nie działają</b>' . ($diagLast ? ': ostatni ' . e(ago($diagLast['t'])) : '') . '. Zadanie cron: <code>' . e($diagCron) . '</code>')?></dd>

          <dt>Dziennik nginx</dt>
          <dd><?=!$logReadable
              ? '<b class="warn">nie można czytać</b> <code>' . e(diagAccessLog()) . '</code>: <code>server/nginx/sanakan-log.conf</code> do <code>/etc/nginx/conf.d/</code> i nowy <code>sanakan.conf</code> (README)'
              : 'czytany: <code>' . e(diagAccessLog()) . '</code>' . ($cloudflareInLog ? ' · <b class="warn">pokazuje adresy Cloudflare</b>: brak <code>set_real_ip_from</code> z <code>sanakan.conf</code>' : '')?></dd>

          <dt>Stan nginx</dt>
          <dd><?=$nginxProbe === null ? 'jeszcze nie sprawdzony' : (!empty($diagLast['ng']) ? 'czytany' : '<b class="warn">brak</b> (' . e($nginxProbe[0] === 200 ? 'odpowiedź to nie stub_status' : diagProbeText($nginxProbe)) . '): <code>location = /nginx-status</code> z <code>sanakan.conf</code>')?></dd>

          <dt>Stan PHP-FPM</dt>
          <dd><?=$fpmProbe === null ? 'jeszcze nie sprawdzony' : (!empty($diagLast['fpm']) ? 'czytany' : '<b class="warn">brak</b> (' . e($fpmProbe[0] === 200 ? 'odpowiedź to nie status puli' : diagProbeText($fpmProbe)) . '): <code>pm.status_path = /fpm-status</code> w <code>/etc/php/8.1/fpm/pool.d/www.conf</code> i <code>location = /fpm-status</code> z <code>sanakan.conf</code>')?></dd>

          <dt>Wolne zapytania</dt>
          <dd><?=$diagSlow === null
              ? '<b class="warn">nie można czytać</b> <code>' . e(diagSlowLog()) . '</code>: <code>request_slowlog_timeout = 5s</code> i <code>slowlog</code> w puli PHP-FPM (README)'
              : 'dziennik <code>' . e(diagSlowLog()) . '</code>' . ($diagSlow ? '' : ', pusty')?></dd>

          <dt>Blokowanie w Cloudflare</dt>
          <dd><?=!cloudflareConfigured()
              ? 'wyłączone: <code>CLOUDFLARE_API_TOKEN</code>, <code>CLOUDFLARE_ACCOUNT_ID</code> i <code>CLOUDFLARE_LIST</code> w <code>inc/config.php</code> (README)'
              : ($cfError !== null ? '<b class="warn">' . e($cfError) . '</b>' : 'lista <code>' . e(CLOUDFLARE_LIST) . '</code>, ' . count($cfItems) . ' ' . plural(count($cfItems), 'pozycja', 'pozycje', 'pozycji'))?></dd>
        </dl>
      </section>

<?php
$serverSum = [];
if ($cpuNow)
    $serverSum[] = decimal($cpuNow['busy']) . '% CPU';
if ($system['memory'])
    $serverSum[] = formatSize($system['memory']['total'] - $system['memory']['available']) . ' RAM';
?>
      <section class="card wide" data-sum="<?=$serverSum ? implode(' · ', $serverSum) : 'brak danych'?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Zasoby serwera</h2>
        <p class="hint">Pamięć i procesy z chwili otwarcia panelu, odśwież stronę po nowy. Procesor i wykresy są z pomiarów w tle co <?=DIAG_INTERVAL?> s (karta „Dostępność strony”): linia to średnia z 5 minut, a jaśniejsze pasmo nad nią przy procesorze sięga najbardziej zajętych 10 sekund z tego czasu. <?=e($system['name'])?><?=$system['uptime'] !== null ? ' · serwer działa od ' . e(duration($system['uptime'])) : ''?>.</p>
<?php if ($cpuNow === null && $system['memory'] === null): ?>
        <p class="nobody">Brak danych: serwer nie ma <code>/proc</code> (to nie Linux) albo PHP nie może go czytać (<code>open_basedir</code>).</p>
<?php else:
        $cores = $system['cpuInfo']['cores'];
        $load = $system['load'];
        $memory = $system['memory'];
        $opcache = $system['opcache'];
?>
        <div class="meters">
<?php if ($cpuNow): $cpu = $cpuNow; ?>
          <?=meter('Procesor', $cpu['busy'], 100, decimal($cpu['busy']) . '%',
              ($load ? 'obciążenie ' . decimal($load[0], 2) . ' · ' . decimal($load[1], 2) . ' · ' . decimal($load[2], 2) . ' (1, 5, 15 min)'
                  . ($cores && $load[1] > $cores ? ' <b class="warn">więcej niż rdzeni</b>' : '') . '<br />' : '')
              . ($cores ? $cores . ' ' . plural($cores, 'rdzeń', 'rdzenie', 'rdzeni') : '')
              . ($system['cpuInfo']['model'] ? ' · ' . e($system['cpuInfo']['model']) : '') . '<br />'
              . 'czekanie na dysk ' . decimal($cpu['iowait']) . '%' . ($cpu['iowait'] >= 10 ? ' <b class="warn">dysk nie nadąża</b>' : '')
              . ' · zabrane przez hosta ' . decimal($cpu['steal']) . '%' . ($cpu['steal'] >= 10 ? ' <b class="warn">host jest przeciążony</b>' : ''))?>

<?php endif; ?>
<?php if ($memory): $used = $memory['total'] - $memory['available']; ?>
          <?=meter('Pamięć RAM', $used, $memory['total'], e(formatSize($used)) . ' z ' . e(formatSize($memory['total'])),
              'wolne do użycia ' . e(formatSize($memory['available'])) . '<br />w tym cache dysku ' . e(formatSize($memory['cached'])) . ', które system odda, gdy zabraknie')?>

<?php $swapUsed = $memory['swapTotal'] - $memory['swapFree']; ?>
          <?=$memory['swapTotal'] > 0
              ? meter('Swap', $swapUsed, $memory['swapTotal'], e(formatSize($swapUsed)) . ' z ' . e(formatSize($memory['swapTotal'])),
                  $swapUsed > $memory['swapTotal'] * 0.5 ? '<b class="warn">mocno używany</b>: brakuje RAM-u, wszystko zwalnia' : 'pamięć na dysku, gdy brakuje RAM-u')
              : meter('Swap', 0, 1, 'brak', 'bez swapu, gdy zabraknie RAM-u, system zabija procesy (OOM)')?>

<?php endif; ?>
<?php if ($opcache): ?>
          <?=meter('OPcache', $opcache['used'], $opcache['size'], e(formatSize($opcache['used'])) . ' z ' . e(formatSize($opcache['size'])),
              ($opcache['hits'] !== null ? 'trafienia ' . decimal($opcache['hits']) . '% · ' : '') . $opcache['scripts'] . ' ' . plural($opcache['scripts'], 'skrypt', 'skrypty', 'skryptów')
              . ($opcache['full'] ? ' · <b class="warn">pełny</b>, zwiększ <code>opcache.memory_consumption</code>' : ''))?>

<?php else: ?>
          <?=meter('OPcache', 0, 1, 'wyłączony', 'PHP kompiluje każdy skrypt przy każdym wejściu; włącz <code>opcache.enable</code>')?>

<?php endif; ?>
        </div>
<?php endif; ?>
<?php if ($diagRounds): $usage = diagUsage($diagRounds, $system['memory']['total'] ?? 0); ?>
        <div class="usage">
<?=cpuChart($usage)?>
<?php if ($system['memory']): ?>
<?=memoryChart($usage, $system['memory']['total'])?>
<?php endif; ?>
        </div>
<?php endif; ?>
        <div class="stats-grid">
<?php if ($system['processes'] && !empty($memory)): ?>
          <div>
            <h3>Najwięcej pamięci</h3>
            <ul class="stat-list">
<?php foreach ($system['processes'] as $name => $program): ?>
              <li style="--share: <?=share($program['bytes'], $memory['total'])?>"><span><?=e($name)?><?=$program['count'] > 1 ? ' <span class="muted">&times;' . $program['count'] . '</span>' : ''?></span><span><?=e(formatSize($program['bytes']))?> &middot; <?=decimal(100 * $program['bytes'] / $memory['total'])?>%</span></li>
<?php endforeach; ?>
            </ul>
          </div>
<?php endif; ?>
          <div>
            <h3>PHP</h3>
            <ul class="stat-list">
              <li style="--share: 0"><span>limit pamięci skryptu</span><span><?=e(ini_get('memory_limit'))?></span></li>
              <li style="--share: 0"><span>limit czasu skryptu</span><span><?=(int)ini_get('max_execution_time')?> s</span></li>
              <li style="--share: 0"><span>ten widok zużył</span><span><?=e(formatSize(memory_get_peak_usage(true)))?></span></li>
              <li style="--share: 0"><span>pomiar procesora</span><span>w tle, co <?=DIAG_INTERVAL?> s</span></li>
            </ul>
          </div>
        </div>
      </section>

<?php
$serverProblems = 0;
foreach ([hasGd(), canConvertToWebp(), canConvertGifToWebp(), canThumbVideo(), canConvertVideo(), canConvertHeif(), canZip(), !$cronLate, botAppKey() !== '', botHeartbeatSecret() !== '', dataWritable()] as $ok)
    if (!$ok)
        $serverProblems++;
?>
      <section class="card wide" data-state="<?=$serverProblems ? 'warn' : 'ok'?>" data-sum="<?=$serverProblems ? $serverProblems . ' ' . plural($serverProblems, 'problem', 'problemy', 'problemów') : 'wszystko jest'?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Serwer</h2>
        <dl class="server">
          <dt>PHP</dt>
          <dd><?=e(PHP_VERSION)?></dd>

          <dt>Miniatury (GD)</dt>
          <dd><?=hasGd() ? 'włączone' . (function_exists('imagewebp') ? ', WebP' : ', PNG') : '<b class="warn">GD wyłączone</b>: duże pliki nie mają podglądów'?></dd>

          <dt>PNG/JPG → WebP</dt>
          <dd><?=!canConvertToWebp() ? '<b class="warn">GD nie zapisuje WebP</b>: obrazy zostają w swoim formacie' : (findTool('cwebp') ? 'cwebp: ' . e(findTool('cwebp')) : '<b class="warn">brak cwebp</b>: zapisuje GD, z szumem kolorów przy liniach. Instalacja: <code>apt-get install -y webp</code>')?></dd>

          <dt>GIF → WebP</dt>
          <dd><?=canConvertGifToWebp() ? 'gif2webp: ' . e(findTool('gif2webp')) . (findTool('webpmux') ? '' : ' <b class="warn">(bez webpmux miniatury animowanych WebP się nie zrobią)</b>') : '<b class="warn">brak gif2webp</b>: GIF-y zostają GIF-ami. Instalacja: <code>apt-get install -y webp</code>'?></dd>

          <dt>Miniatury filmów</dt>
          <dd><?=canThumbVideo() ? 'ffmpeg: ' . e(findTool('ffmpeg')) : '<b class="warn">brak ffmpeg</b>: filmy w galerii nie mają miniatur, kafelek wczytuje cały film. Instalacja: <code>apt-get install -y ffmpeg</code>'?></dd>

          <dt>MP4 → WebM</dt>
          <dd><?=canConvertVideo() ? 'ffmpeg: ' . e(findTool('ffmpeg')) : '<b class="warn">brak ffmpeg</b>: MP4 zostaje MP4. Instalacja: <code>apt-get install -y ffmpeg</code>'?></dd>

          <dt>HEIC/HEIF → WebP</dt>
          <dd><?php $heif = findTool('magick') ?: findTool('convert') ?: findTool('ffmpeg'); ?>
<?=canConvertHeif() ? 'obraz zapisuje ' . e(basename($heif)) . ': ' . e($heif) : '<b class="warn">brak imagemagick i ffmpeg</b>: zdjęcia HEIC z telefonu nie zostaną przyjęte. Instalacja: <code>apt-get install -y imagemagick</code>'?></dd>

          <dt>AVIF</dt>
          <dd><?=function_exists('imagecreatefromavif') ? 'czyta GD' : (findTool('magick') || findTool('convert') || findTool('ffmpeg') ? 'przez imagemagick lub ffmpeg' : '<b class="warn">brak obsługi AVIF</b>: plik się wyświetli, ale bez miniatury. Instalacja: <code>apt-get install -y imagemagick</code>')?></dd>

          <dt>Pobieranie ZIP</dt>
          <dd><?=canZip() ? 'włączone' : '<b class="warn">brak modułu ZIP</b>: przyciski pobierania są ukryte. Instalacja: <code>apt-get install -y php8.1-zip &amp;&amp; systemctl restart php8.1-fpm</code>'?></dd>

          <dt>Limit wysyłania</dt>
          <dd><?=e(formatSize(uploadLimit()))?> <span class="muted">(PHP; nginx ma osobny client_max_body_size)</span></dd>

          <dt>Auto-sprawdzanie</dt>
          <dd><?=$cronLate
              ? '<b class="warn">nie działa</b>: ' . ($cronLast === null ? 'cron jeszcze ani razu nie sprawdził bota' : 'ostatnio ' . e(ago($cronLast))) . '. Zadanie cron: <code>' . e($cronCommand) . '</code>'
              : 'działa: ostatnio ' . e(ago($cronLast)) . ', ' . $autoChecks . ' ' . plural($autoChecks, 'sprawdzenie', 'sprawdzenia', 'sprawdzeń') . ' w ostatniej godzinie'?></dd>

          <dt>Cache miniatur</dt>
          <dd>
            <?=$thumbFiles?> <?=plural($thumbFiles, 'plik', 'pliki', 'plików')?>, <?=e(formatSize($thumbBytes))?>
            <button type="button" class="admin-btn small" data-action="clear-thumbs" data-confirm="Usunąć wszystkie miniatury? Utworzą się od nowa przy oglądaniu galerii.">Wyczyść</button>
          </dd>

          <dt>Specyfikacja API</dt>
          <dd>
            <?=is_file($specFile) ? 'pobrana ' . e(ago(filemtime($specFile))) : 'jeszcze nie pobrana'?>
            <button type="button" class="admin-btn small" data-action="refresh-spec">Odśwież</button>
          </dd>

          <dt>Klucz API bota</dt>
          <dd><?=botAppKey() === ''
              ? '<b class="warn">brak BOT_APP_KEY</b> w <code>inc/config.php</code>: bez niego strona nie zna ról z serwera bota i nie pokazuje poleceń moderatorskich'
              : 'ustawiony; role znane dla ' . count($knownRoles) . ' ' . plural(count($knownRoles), 'konta', 'kont', 'kont') . ', polecenia moderatorskie '
                  . ($privateTime ? 'pobrane ' . e(ago($privateTime)) : '<b class="warn">jeszcze nie pobrane</b> (klucz musi mieć uprawnienie Info)')?></dd>

          <dt>Heartbeat bota</dt>
          <dd><?=botHeartbeatSecret() === ''
              ? '<b class="warn">brak BOT_HEARTBEAT_SECRET</b> w <code>inc/config.php</code>: strona sama pyta API bota co minutę, a gdy API jest nieosiągalne, bot wygląda na wyłączonego'
              : ($heartbeatLast === null
                  ? '<b class="warn">bot jeszcze nic nie wysłał</b> na <code>/alive/</code> (Heartbeat w jego Config.json, ten sam sekret)'
                  : (time() - $heartbeatLast < BOT_HEARTBEAT_FRESH ? 'działa' : '<b class="warn">nie przychodzi</b>, strona pyta API bota') . ': ostatni ' . e(ago($heartbeatLast))
                      . (botAddresses() ? '; adres bota (chroniony przed blokadą): ' . e(implode(', ', array_keys(botAddresses()))) : ''))?></dd>

          <dt>Zapis danych</dt>
          <dd><?=dataWritable()
              ? 'inc/data: ' . e(formatSize($dataRoom['bytes'])) . ' (kosz ' . e(formatSize($dataRoom['trash'])) . ', podglądy WebP ' . e(formatSize($dataRoom['webp'])) . ')'
              : '<b class="warn">brak prawa zapisu</b>: ' . e(dataError())?></dd>

          <dt>Wersja strony</dt>
          <dd><?php if ($deployed): ?><a href="<?=e(REPO_URL . '/commit/' . $deployed['hash'])?>" target="_blank" rel="noopener"><code><?=e(substr($deployed['hash'], 0, 7))?></code></a><?=$deployed['subject'] !== '' ? ' ' . e($deployed['subject']) : ''?> <span class="muted">(<?=$deployed['date'] ? 'commit z ' . e(date('j.m.Y', $deployed['date'])) . ', ' : ''?>wdrożone <?=e(ago($deployed['at']))?>)</span><?php else: ?>brak informacji <span class="muted">(strona nie była wdrażana przez deploy.sh)</span><?php endif; ?></dd>

          <dt>Kopia danych</dt>
          <dd>
<?php if (canZip()): ?>
            <form class="backup" method="post" action="./">
              <input type="hidden" name="csrf" value="<?=e($csrf)?>" />
              <input type="hidden" name="action" value="backup" />
              <label class="admin-check"><input type="checkbox" name="gallery" value="1" /> z galerią (<?=e(formatSize($stats['bytes']))?>)</label>
              <button type="submit" class="admin-btn small">Pobierz ZIP</button>
            </form>
            <span class="muted">inc/data: <?=e(formatSize($dataBytes))?> (dostępy, historia, statusy, awarie, kosz). Bez inc/config.php, w którym jest sekret aplikacji Discord.</span>
<?php else: ?>
            <b class="warn">brak modułu ZIP</b>: <code>apt-get install -y php8.1-zip &amp;&amp; systemctl restart php8.1-fpm</code>
<?php endif; ?>
          </dd>
        </dl>
      </section>

      </div>
    </div>
<?php endif; ?>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../privacy/">Prywatność</a></footer>

<?php if ($flash): ?>
  <div class="toast" id="toast" role="status"><?=e($flash)?></div>
<?php else: ?>
  <div class="toast" id="toast" role="status" hidden></div>
<?php endif; ?>

  <script src="../js/sanakan-util.js?v=f417e538a8"></script>
  <script src="../js/explorer.js?v=b0c463f9b6"></script>
  <script src="../js/account.js?v=c8dfe2b1f3"></script>
  <script src="../js/netsphere.js?v=1c8be049a6"></script>
<?php if ($allowed): ?>
  <script src="../js/admin.js?v=7b8d802b60"></script>
<?php endif; ?>
</body>

</html>
