<?php
    // The bot's changelog, verdiff.md in the root of its repository
    // (state/wersje/). The file is a heading per version, with the changes under
    // it in Markdown:
    //
    //   # 1.4.10.14
    //
    //   Data: 2026-10-08
    //   Commit: 1a2b3c4d
    //
    //   - what changed
    //   ## Techniczne
    //   - the rest
    //
    // The site reads it over HTTP (BOT_REPO_VERDIFF_URL in inc/config.php, the
    // raw address of the file), keeps the answer for a while and parses it into
    // one section per version: its date, its commit and the Markdown of the
    // changes. A version the bot reported and a section are then matched by the
    // version string, so a recorded version can show its changes without leaving
    // the site. Needs inc/bot.php (botFile()) and inc/auth.php (httpRaw()).

    // how long the answer is kept before the file is asked for again
    const VERDIFF_TTL = 21600;
    // after a failed try the file is asked again this soon
    const VERDIFF_RETRY = 600;

    function verdiffUrl()
    {
        return defined('BOT_REPO_VERDIFF_URL') ? trim((string)BOT_REPO_VERDIFF_URL) : '';
    }

    // the key a version is matched by: no spaces, no leading "v"
    function verdiffKey($version)
    {
        return ltrim(trim((string)$version), 'vV');
    }

    // verdiff.md as [key => ['version', 'date', 'commit', 'changes']]
    function verdiffParse($text)
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string)$text);
        $sections = [];
        $version = null;
        $body = [];
        $flush = function () use (&$sections, &$version, &$body) {
            if ($version === null)
                return;

            $date = null;
            $commit = null;
            $changes = [];
            foreach ($body as $line) {
                if ($date === null && preg_match('/^\s*Data:\s*(.+?)\s*$/iu', $line, $match))
                    $date = trim($match[1]);
                else if ($commit === null && preg_match('/^\s*Commit:\s*([0-9a-f]{7,40})\s*$/iu', $line, $match))
                    $commit = $match[1];
                else
                    $changes[] = $line;
            }
            $sections[verdiffKey($version)] = [
                'version' => trim($version),
                'date' => $date,
                'commit' => $commit,
                'changes' => trim(implode("\n", $changes)),
            ];
            $body = [];
        };

        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^#\s+(.+?)\s*$/', $line, $match)) {
                $flush();
                $version = $match[1];
            } else if ($version !== null) {
                $body[] = $line;
            }
        }
        $flush();

        return $sections;
    }

    // the cached changelog, asked for again when it is older than VERDIFF_TTL:
    // ['fetched', 'hash', 'sections']. A failed try keeps the last answer and
    // waits VERDIFF_RETRY.
    function verdiff()
    {
        $file = botFile('verdiff.json');
        $cache = json_decode((string)@file_get_contents($file), true);
        $known = is_array($cache) && isset($cache['fetched'], $cache['sections']);
        $url = verdiffUrl();

        if ($url === '' || ($known && time() - (int)$cache['fetched'] < VERDIFF_TTL))
            return $known ? $cache : ['fetched' => 0, 'hash' => '', 'sections' => []];

        [$body, $status] = httpRaw($url, [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true,
            'header' => "User-Agent: sanakan.pl\r\nAccept: text/plain"
        ]);
        if ($body !== false && $status === 200) {
            $cache = ['fetched' => time(), 'hash' => substr(hash('sha256', (string)$body), 0, 12), 'sections' => verdiffParse($body)];
        } else {
            // keep what is known and come back in VERDIFF_RETRY seconds
            $cache = $known ? $cache : ['hash' => '', 'sections' => []];
            $cache['fetched'] = time() - VERDIFF_TTL + VERDIFF_RETRY;
        }
        botWriteFile($file, json_encode($cache, JSON_UNESCAPED_UNICODE));

        return $cache;
    }

    // the section of one version, or null
    function verdiffChanges($version)
    {
        $sections = verdiff()['sections'];

        return $sections[verdiffKey($version)] ?? null;
    }

    // The accounts the site knows, by their Discord handle and by their nick, for
    // the @nick a changelog may carry, as lower-case handle => ['name',
    // 'avatar']. Only the accounts that logged in are here (the recent logins);
    // an account that never did stays plain text.
    function mentionUsers()
    {
        $users = [];
        foreach (readData('logins') as $login) {
            $entry = [
                'name' => $login['name'] ?? ($login['username'] ?? ''),
                'avatar' => $login['avatar'] ?? '',
            ];
            foreach ([$login['username'] ?? '', $login['name'] ?? ''] as $handle) {
                $handle = strtolower(trim((string)$handle));
                if ($handle !== '' && !isset($users[$handle]))
                    $users[$handle] = $entry;
            }
        }

        return $users;
    }

    // The version log of state/wersje/: every version the bot reported, newest
    // first, each with the changelog section that matches it when there is one.
    // Versions that are only in verdiff.md are listed too, with their date from
    // the file, so the page shows the changelog also before the log started.
    // Each entry is ['version', 'since' => time or null, 'date' => text or null,
    // 'commit' => hash or null, 'changes' => whether a section exists].
    function versionEntries()
    {
        $sections = verdiff()['sections'];
        $entries = [];
        $seen = [];
        foreach (botVersions() as $reported) {
            $key = verdiffKey($reported['version']);
            $section = $sections[$key] ?? null;
            $entries[] = [
                'version' => $reported['version'],
                'since' => $reported['since'] ?? null,
                'date' => $section['date'] ?? null,
                'commit' => $section['commit'] ?? null,
                'changes' => $section !== null,
            ];
            $seen[$key] = true;
        }
        foreach ($sections as $key => $section) {
            if (isset($seen[$key]))
                continue;
            $date = $section['date'] ?? null;
            $since = $date !== null ? strtotime($date) : false;
            $entries[] = [
                'version' => $section['version'],
                'since' => $since !== false ? $since : null,
                'date' => $date,
                'commit' => $section['commit'],
                'changes' => true,
            ];
        }
        usort($entries, function ($a, $b) {
            return ($b['since'] ?? 0) <=> ($a['since'] ?? 0);
        });

        return $entries;
    }
