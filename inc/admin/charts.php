<?php
    // The charts and lists of the panel's availability card (inc/diag.php). Part
    // of the panel, loaded by admin/index.php.

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
                    . ' &middot; ' . e(date('j.m H:i', $scanner['first']))
                    . ($scanner['last'] - $scanner['first'] >= 60 ? '–' . e(date($sameDay ? 'H:i' : 'j.m H:i', $scanner['last'])) : '') . '</span>'
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
