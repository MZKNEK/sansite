<?php
    // The bot's changelog, verdiff.md in the root of its repository
    // (state/versions/). The file is a heading per version, with the changes under
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
    // the site. The bot's repository may keep only the current version in the
    // file, so a section once read is kept when the file no longer has it, and
    // the ones the site missed are read from the file's history on GitHub
    // (verdiffBackfill()). A version the bot runs from another branch before
    // its merge takes its section from that branch's file (verdiffBranches()).
    // The versions before VERDIFF_FROM, which the file
    // never described, have their changes written here, in
    // inc/verdiff-builtin.md (same format), and are never looked for in the
    // file. Needs inc/bot.php (botFile()) and inc/auth.php (httpRaw()).

    // how long the answer is kept before the file is asked for again
    const VERDIFF_TTL = 21600;
    // after a failed try the file is asked again this soon
    const VERDIFF_RETRY = 600;
    // how long after the bot reports a version without a section the file is
    // asked for every VERDIFF_RETRY instead of every VERDIFF_TTL
    const VERDIFF_NEW = 86400;
    // the first version verdiff.md describes
    const VERDIFF_FROM = '1.4.10.14';

    function verdiffUrl()
    {
        return defined('BOT_REPO_VERDIFF_URL') ? trim((string)BOT_REPO_VERDIFF_URL) : '';
    }

    // [owner, repo, ref, path] of a raw.githubusercontent.com address, or null
    // for any other address (then there is no history to read)
    function verdiffGithub($url)
    {
        if (!preg_match('~^https://raw\.githubusercontent\.com/([^/]+)/([^/]+)/(?:refs/heads/)?([^/]+)/(.+)$~', (string)$url, $match))
            return null;

        return [$match[1], $match[2], $match[3], $match[4]];
    }

    // the key a version is matched by: no spaces, no leading "v", no build
    // metadata after "+" (botVersionNumber())
    function verdiffKey($version)
    {
        return ltrim(trim(explode('+', (string)$version, 2)[0]), 'vV');
    }

    // whether verdiff.md is expected to describe a version (VERDIFF_FROM on)
    function verdiffExpected($version)
    {
        return version_compare(verdiffKey($version), VERDIFF_FROM, '>=');
    }

    // the sections written in inc/verdiff-builtin.md, for the versions before
    // VERDIFF_FROM
    function verdiffBuiltin()
    {
        static $sections = null;

        return $sections ?? ($sections = verdiffParse((string)@file_get_contents(__DIR__ . '/verdiff-builtin.md')));
    }

    // every known section: the changelog's, then the built-in ones
    function verdiffSections()
    {
        return verdiff()['sections'] + verdiffBuiltin();
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

    // Whether the cached changelog ($cache, null when there is none) is asked for
    // again: when it is older than VERDIFF_TTL, or when the newest version the
    // bot reported ($newest, ['version', 'since'] as botVersions() has it) has
    // no section in it yet, came in the last VERDIFF_NEW and the last try was
    // VERDIFF_RETRY ago, so a new version gets its changes soon after the file
    // is pushed and not hours later. A version the file never describes stops
    // the quicker tries after VERDIFF_NEW.
    function verdiffDue($cache, $newest, $now)
    {
        if ($cache === null || $now - (int)$cache['fetched'] >= VERDIFF_TTL)
            return true;
        $version = $newest['version'] ?? '';
        if ($newest === null || !verdiffExpected($version) || isset($cache['sections'][verdiffKey($version)]))
            return false;
        if ($now - (int)($newest['since'] ?? 0) >= VERDIFF_NEW)
            return false;

        return $now - (int)($cache['tried'] ?? $cache['fetched']) >= VERDIFF_RETRY;
    }

    // one GET to GitHub: [body, status]
    function verdiffGet($url, $accept = 'text/plain')
    {
        return httpRaw($url, [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true,
            'header' => "User-Agent: sanakan.pl\r\nAccept: $accept"
        ]);
    }

    // the cached changelog, or null when there is none
    function verdiffCached()
    {
        $cache = json_decode((string)@file_get_contents(botFile('verdiff.json')), true);

        return is_array($cache) && isset($cache['fetched'], $cache['sections']) ? $cache : null;
    }

    // the cached changelog, asked for again when verdiffDue() says so:
    // ['fetched', 'tried', 'hash', 'sections', 'history', 'revisions']. The
    // sections of the file win over the known ones, and a known section the
    // file no longer has is kept. A failed try keeps the last answer and waits
    // VERDIFF_RETRY. $url stands in for verdiffUrl() (the tests).
    function verdiff($url = null)
    {
        $file = botFile('verdiff.json');
        $cache = verdiffCached();
        $known = $cache !== null;
        $url = $url ?? verdiffUrl();
        $newest = botVersions()[0] ?? null;

        if ($url === '' || !verdiffDue($cache, $newest, time()))
            return $known ? $cache : ['fetched' => 0, 'hash' => '', 'sections' => []];

        [$body, $status] = verdiffGet($url);
        if ($body !== false && $status === 200) {
            $old = $known ? $cache : [];
            $parsed = verdiffParse($body);
            $cache = [
                'fetched' => time(),
                'hash' => substr(hash('sha256', (string)$body), 0, 12),
                'sections' => $parsed + ($old['sections'] ?? []),
                // a section the file has now is no longer a branch's
                'borrowed' => array_diff_key($old['borrowed'] ?? [], $parsed),
            ] + $old;
        } else {
            // keep what is known and come back in VERDIFF_RETRY seconds
            $cache = $known ? $cache : ['hash' => '', 'sections' => []];
            $cache['fetched'] = time() - VERDIFF_TTL + VERDIFF_RETRY;
        }
        $cache['tried'] = time();
        botWriteFile($file, json_encode($cache, JSON_UNESCAPED_UNICODE));

        return $cache;
    }

    // verdiff(), then reads the sections the site missed from the history of
    // verdiff.md on GitHub: the commits that changed the file, newest first, each revision
    // asked for once (its hash goes to 'revisions'). A known section is kept, so
    // the current file and the newer revisions win. Only for a
    // raw.githubusercontent.com address, at most every VERDIFF_TTL and only
    // while a version the bot reported (VERDIFF_FROM on) has no section; from cron
    // (inc/check-bot.php), as it may ask for several files. $url stands in for
    // verdiffUrl() (the tests).
    function verdiffBackfill($url = null)
    {
        $url = $url ?? verdiffUrl();
        $cache = verdiff($url);
        $github = verdiffGithub($url);
        if ($github === null)
            return;

        $missing = false;
        foreach (botVersions() as $reported)
            if (verdiffExpected($reported['version']) && !isset($cache['sections'][verdiffKey($reported['version'])]))
                $missing = true;
        if (!$missing || time() - (int)($cache['history'] ?? 0) < VERDIFF_TTL)
            return;

        [$owner, $repo, $ref, $path] = $github;
        [$body, $status] = verdiffGet('https://api.github.com/repos/' . $owner . '/' . $repo . '/commits?path='
            . rawurlencode(rawurldecode($path)) . '&sha=' . rawurlencode($ref) . '&per_page=100', 'application/vnd.github+json');
        $commits = $body !== false && $status === 200 ? json_decode((string)$body, true) : null;

        $sections = $cache['sections'];
        $revisions = $cache['revisions'] ?? [];
        foreach (is_array($commits) ? $commits : [] as $commit) {
            $sha = $commit['sha'] ?? '';
            if (!is_string($sha) || !preg_match('/^[0-9a-f]{40}$/', $sha) || in_array($sha, $revisions, true))
                continue;
            [$text, $status] = verdiffGet('https://raw.githubusercontent.com/' . $owner . '/' . $repo . '/' . $sha . '/' . $path);
            // a revision that did not come is asked for again next time
            if ($text === false || $status !== 200)
                continue;
            $sections += verdiffParse($text);
            $revisions[] = $sha;
        }

        // read again, so a fetch that came meanwhile is not lost
        $cache = verdiffCached() ?? $cache;
        $cache['sections'] += $sections;
        $cache['revisions'] = $revisions;
        $cache['history'] = time();
        botWriteFile(botFile('verdiff.json'), json_encode($cache, JSON_UNESCAPED_UNICODE));
    }

    // The versions the bot reported (VERDIFF_FROM on) that the configured
    // file does not describe: without a section, or with one taken from a
    // branch; [key => when the bot began running it]
    function verdiffMissing($cache)
    {
        $missing = [];
        foreach (botVersions() as $reported) {
            $key = verdiffKey($reported['version']);
            if (verdiffExpected($key) && (!isset($cache['sections'][$key]) || isset($cache['borrowed'][$key])))
                $missing[$key] = max($missing[$key] ?? 0, (int)($reported['since'] ?? 0));
        }

        return $missing;
    }

    // The sections of verdiff.md on the repository's other branches, for the
    // versions the bot reported that the configured branch (BOT_REPO_VERDIFF_URL)
    // does not describe yet: a bot run from a branch for a while shows its
    // changes before the merge. Only a reported version takes a section from
    // a branch, so a branch's versions that never ran are neither listed nor
    // counted as skipped. A section taken ('borrowed' => [key => branch]) is
    // kept, also once the branch is gone, follows the branch while it changes,
    // and gives way to the configured file's own as soon as it has one
    // (verdiff()). The branches are asked of the GitHub API, one request, only
    // while a version is missing, as often as verdiffDue() asks the file for
    // it (VERDIFF_RETRY while the version is new, VERDIFF_TTL after); a
    // branch's file is read once per head commit ('heads' => [branch => sha])
    // and only its sections the configured file has not are kept
    // ('branchSections' => [branch => [key => section]]). Two branches with
    // a section of the same version: the first by name. Only for a
    // raw.githubusercontent.com address; from cron (inc/check-bot.php), after
    // verdiffBackfill(). $url stands in for verdiffUrl() (the tests).
    function verdiffBranches($url = null)
    {
        $github = verdiffGithub($url ?? verdiffUrl());
        $cache = verdiffCached();
        if ($github === null || $cache === null)
            return;
        $missing = verdiffMissing($cache);
        if (!$missing)
            return;

        [$owner, $repo, $ref, $path] = $github;
        $now = time();
        $own = array_diff_key($cache['sections'], $cache['borrowed'] ?? []);
        $branchSections = $cache['branchSections'] ?? [];
        $heads = $cache['heads'] ?? [];
        $checked = (int)($cache['branches'] ?? 0);
        $wait = $now - max($missing) < VERDIFF_NEW ? VERDIFF_RETRY : VERDIFF_TTL;
        if ($now - $checked >= $wait) {
            $checked = $now;
            [$body, $status] = verdiffGet('https://api.github.com/repos/' . $owner . '/' . $repo . '/branches?per_page=100', 'application/vnd.github+json');
            $list = $body !== false && $status === 200 ? json_decode((string)$body, true) : null;
            if (is_array($list)) {
                $seen = [];
                foreach ($list as $branch) {
                    $name = $branch['name'] ?? null;
                    $sha = $branch['commit']['sha'] ?? null;
                    if (!is_string($name) || $name === $ref || !is_string($sha) || !preg_match('/^[0-9a-f]{40}$/', $sha))
                        continue;
                    $seen[$name] = true;
                    if (($heads[$name] ?? null) === $sha)
                        continue;
                    [$text, $status] = verdiffGet('https://raw.githubusercontent.com/' . $owner . '/' . $repo . '/' . $sha . '/' . $path);
                    // a file that did not come is asked for again next time
                    if ($text !== false && $status === 200)
                        $branchSections[$name] = array_diff_key(verdiffParse($text), $own);
                    elseif ($status === 404)
                        unset($branchSections[$name]);
                    else
                        continue;
                    $heads[$name] = $sha;
                }
                // the branches gone from the repository
                $branchSections = array_intersect_key($branchSections, $seen);
                $heads = array_intersect_key($heads, $seen);
            }
        }

        ksort($branchSections, SORT_STRING);
        $found = [];
        foreach (array_keys($missing) as $key)
            foreach ($branchSections as $name => $sections)
                if (isset($sections[$key])) {
                    $found[$key] = [$name, $sections[$key]];
                    break;
                }

        // read again, so a fetch that came meanwhile is not lost
        $cache = verdiffCached() ?? $cache;
        foreach ($found as $key => [$name, $section])
            if (!isset($cache['sections'][$key]) || isset($cache['borrowed'][$key])) {
                $cache['sections'][$key] = $section;
                $cache['borrowed'][$key] = $name;
            }
        $cache['branchSections'] = $branchSections;
        $cache['heads'] = $heads;
        $cache['branches'] = $checked;
        botWriteFile(botFile('verdiff.json'), json_encode($cache, JSON_UNESCAPED_UNICODE));
    }

    // the section of one version, or null
    function verdiffChanges($version)
    {
        $sections = verdiffSections();

        return $sections[verdiffKey($version)] ?? null;
    }

    // The list items of a section's changes as plain text, for the link preview
    // picture: [[text, technical]], technical for the items under a level-2
    // heading (## Techniczne). Only the top level of a list counts; the
    // Markdown marks are taken off, a link keeps its text.
    function verdiffItems($changes)
    {
        $items = [];
        $technical = false;
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", (string)$changes)) as $line) {
            if (preg_match('/^#{2,}\s/', $line))
                $technical = true;
            else if (preg_match('/^(?:[-*+]|\d+[.)])\s+(.+?)\s*$/', $line, $match)) {
                $text = preg_replace('/\[([^\]]+)\]\([^)\s]+\)/', '$1', $match[1]);
                $text = preg_replace('/(\*\*|__|~~)(.+?)\1/', '$2', $text);
                $text = preg_replace('/`([^`]+)`/', '$1', $text);
                $text = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/', '$1', $text);
                $items[] = [trim($text), $technical];
            }
        }

        return $items;
    }

    // The accounts the site knows, by their Discord handle and by their nick, for
    // the @nick a changelog may carry, as lower-case handle => ['name',
    // 'avatar', 'badge', 'roles']. 'badge' is the account's role as roleBadge()
    // reads it (null when the site never asked), so a click can open the rank.
    // Only the accounts that logged in are here (the recent logins); an account
    // that never did stays plain text.
    function mentionUsers()
    {
        $users = [];
        foreach (readData('logins') as $id => $login) {
            $roles = botRoles($id);
            $entry = [
                'name' => $login['name'] ?? ($login['username'] ?? ''),
                'avatar' => $login['avatar'] ?? '',
                'badge' => $roles === null ? null : roleBadge($roles),
                'roles' => $roles === null ? [] : roleNames($roles),
            ];
            foreach ([$login['username'] ?? '', $login['name'] ?? ''] as $handle) {
                $handle = strtolower(trim((string)$handle));
                if ($handle !== '' && !isset($users[$handle]))
                    $users[$handle] = $entry;
            }
        }

        return $users;
    }

    // The version log of state/versions/: every version the bot reported, the
    // highest version first, each with the changelog section that matches it
    // when there is one. Each entry is ['version', 'since' => its first start
    // or null, 'last' => its latest start or null, 'date' => text or null,
    // 'commit' => hash or null, 'changes' => whether a section exists]. A
    // version reported again (the bot went back to it) is listed once. A version
    // that is only in verdiff.md and the bot has not reported yet is not listed,
    // so the page never shows a version ahead of the bot. One the bot skipped
    // (an update from 1.4.11.8 straight to 1.4.11.10) is listed without a start
    // ('skipped' => true) between its neighbours. The versions of
    // inc/verdiff-builtin.md came before the site logged any, so those the bot
    // never reported are listed without a start.
    function versionEntries()
    {
        $sections = verdiffSections();
        $entries = [];
        $listed = [];
        $newest = null;
        foreach (botVersions() as $reported) {
            $key = verdiffKey($reported['version']);
            $since = $reported['since'] ?? null;
            if (isset($listed[$key])) {
                $entry = &$entries[$listed[$key]];
                if ($since && (!$entry['since'] || $since < $entry['since']))
                    $entry['since'] = $since;
                if ($since && (!$entry['last'] || $since > $entry['last']))
                    $entry['last'] = $since;
                unset($entry);
                continue;
            }
            $section = $sections[$key] ?? null;
            $entries[] = [
                'version' => $reported['version'],
                'since' => $since,
                'last' => $since,
                'date' => $section['date'] ?? null,
                'commit' => $section['commit'] ?? null,
                'changes' => $section !== null,
            ];
            $listed[$key] = count($entries) - 1;
            if ($newest === null || version_compare($key, $newest, '>'))
                $newest = $key;
        }
        foreach (verdiffBuiltin() as $key => $section) {
            if (isset($listed[$key]))
                continue;
            $entries[] = [
                'version' => $section['version'],
                'since' => null,
                'last' => null,
                'date' => $section['date'],
                'commit' => $section['commit'],
                'changes' => true,
            ];
        }
        usort($entries, function ($a, $b) {
            return version_compare(verdiffKey($b['version']), verdiffKey($a['version']));
        });

        // the versions the bot skipped: in the changelog, never reported, older
        // than the newest it reported
        foreach (verdiff()['sections'] as $key => $section) {
            if (isset($listed[$key]) || $newest === null || !verdiffExpected($key) || !version_compare($key, $newest, '<'))
                continue;
            $at = count($entries);
            foreach ($entries as $i => $entry) {
                if (version_compare(verdiffKey($entry['version']), $key, '<')) {
                    $at = $i;
                    break;
                }
            }
            array_splice($entries, $at, 0, [[
                'version' => $section['version'],
                'since' => null,
                'last' => null,
                'date' => $section['date'],
                'commit' => $section['commit'],
                'changes' => true,
                'skipped' => true,
            ]]);
        }

        return $entries;
    }

    // the version of versionEntries() the bot started last, or null
    function versionStartedLast($entries)
    {
        $last = null;
        foreach ($entries as $entry)
            if ($entry['last'] && ($last === null || $entry['last'] > $last['last']))
                $last = $entry;

        return $last['version'] ?? null;
    }

    // The series a version belongs to on state/versions/: its first three parts
    // ("1.4.10" of "1.4.10.16", "1.0.0" of "1.0.0.0-alpha")
    function versionSeries($version)
    {
        $parts = explode('.', verdiffKey($version));

        return implode('.', array_slice($parts, 0, 3));
    }

    // The entries of versionEntries() in runs of one series, in their order:
    // [['series', 'entries']].
    function versionGroups($entries)
    {
        $groups = [];
        foreach ($entries as $entry) {
            $series = versionSeries($entry['version']);
            $last = count($groups) - 1;
            if ($last >= 0 && $groups[$last]['series'] === $series)
                $groups[$last]['entries'][] = $entry;
            else
                $groups[] = ['series' => $series, 'entries' => [$entry]];
        }

        return $groups;
    }

    // The series of versionGroups() in runs of a line, the first two numbers
    // ("1.4" of "1.4.10"): [['line' => '1.4', 'groups' => [...]], ...], newest
    // first. The list shows the newest line's series as they are and folds each
    // older line into one row.
    function versionLines($groups)
    {
        $lines = [];
        foreach ($groups as $group) {
            $line = implode('.', array_slice(explode('.', $group['series']), 0, 2));
            $last = count($lines) - 1;
            if ($last >= 0 && $lines[$last]['line'] === $line)
                $lines[$last]['groups'][] = $group;
            else
                $lines[] = ['line' => $line, 'groups' => [$group]];
        }

        return $lines;
    }

    // The versions next to one in versionEntries() that have changes to show:
    // ['older' => entry or null, 'newer' => entry or null]. Both are null for
    // a version that is not listed.
    function versionNeighbours($entries, $version)
    {
        $index = null;
        foreach ($entries as $i => $entry) {
            if (strcasecmp($entry['version'], $version) === 0) {
                $index = $i;
                break;
            }
        }
        $found = ['older' => null, 'newer' => null];
        if ($index === null)
            return $found;

        for ($i = $index + 1; $i < count($entries) && $found['older'] === null; $i++)
            if ($entries[$i]['changes'])
                $found['older'] = $entries[$i];
        for ($i = $index - 1; $i >= 0 && $found['newer'] === null; $i--)
            if ($entries[$i]['changes'])
                $found['newer'] = $entries[$i];

        return $found;
    }

    // The date of a list entry as the page writes it: the start the site
    // logged ("9.10.2026 19:33"), else the changelog's date ("8.10.2026" of
    // "2026-10-08", any other text as it is), else null
    function versionDate($entry)
    {
        if (!empty($entry['since']))
            return date('j.m.Y H:i', $entry['since']);
        $date = (string)($entry['date'] ?? '');
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $match))
            return (int)$match[3] . '.' . $match[2] . '.' . $match[1];

        return $date !== '' ? $date : null;
    }
