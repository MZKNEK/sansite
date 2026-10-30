<?php
    // The pictures of the bot's Pocket Waifu cards (the frames, the deres, the
    // stars, the layers of the ultimate cards), mirrored from the bot's
    // repository into inc/data/pw/ and served by the site at /pw/
    // (server/nginx/sanakan.conf), so Skalpelator and USkalpelator never send
    // their visitors to GitHub. inc/check-bot.php syncs it from cron: every
    // PW_SYNC_EVERY it asks GitHub for the list of the folder's files and
    // downloads only those whose content changed (each checked against its git
    // hash, so a broken or swapped file never gets in), at most PW_RUN_BUDGET
    // seconds a run, the rest the next minute; a file gone from the repository
    // goes here too. With them it reads how many styles each ultimate frame
    // has from the bot's code (variants.json), which USkalpelator used to parse
    // from GitHub itself. The pictures of the profile (the bot's np/ folder
    // and its two panels of statistics) come the same way into np/, for
    // Profilator. Kept in readData('pw') as ['checked', 'files' =>
    // [path => git hash], 'pending' => [path => git hash], 'error', 'synced'].
    require_once __DIR__ . '/auth.php';

    const PW_REPO = 'MZKNEK/sanakan';
    const PW_BRANCH = 'master';
    const PW_SOURCE = 'src/Pictures/PW';
    const PW_PROFILE_SOURCE = 'src/Pictures/np';
    const PW_PROFILE_FILES = [
        'src/Pictures/statsAnime.png' => 'np/statsAnime.png',
        'src/Pictures/statsManga.png' => 'np/statsManga.png'
    ];
    const PW_VARIANTS_SOURCE = 'src/Extensions/CardExtension.cs';
    const PW_SYNC_EVERY = 21600;
    const PW_RETRY = 900;
    const PW_RUN_BUDGET = 40;
    const PW_TYPES = ['png', 'webp', 'jpg', 'jpeg'];

    function pwDir()
    {
        return dataDir() . '/pw';
    }

    // one GET to GitHub: [body, status]
    function pwGet($url, $accept = '*/*')
    {
        return httpRaw($url, [
            'method' => 'GET',
            'timeout' => 15,
            'ignore_errors' => true,
            'header' => "User-Agent: sanakan.pl\r\nAccept: $accept"
        ]);
    }

    function pwRawUrl($path)
    {
        return 'https://raw.githubusercontent.com/' . PW_REPO . '/' . PW_BRANCH . '/'
            . implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    // the name of a file of the folders inside inc/data/pw, or null for one that
    // is not a picture or whose name could reach outside it
    function pwLocalName($path)
    {
        if (isset(PW_PROFILE_FILES[$path]))
            return PW_PROFILE_FILES[$path];
        if (strpos($path, PW_SOURCE . '/') === 0)
            $name = substr($path, strlen(PW_SOURCE) + 1);
        else if (strpos($path, PW_PROFILE_SOURCE . '/') === 0)
            $name = 'np/' . substr($path, strlen(PW_PROFILE_SOURCE) + 1);
        else
            return null;
        if (!preg_match('~^[\w .()+-]+(?:/[\w .()+-]+)*$~u', $name) || preg_match('~(^|/)\.~', $name))
            return null;
        // np/ belongs to the profile's pictures, a folder of the cards by that name would mix with them
        if (strpos($path, PW_SOURCE . '/np/') === 0)
            return null;

        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), PW_TYPES, true) ? $name : null;
    }

    // where a file of the mirror is in the bot's repository, pwLocalName() turned around
    function pwSourcePath($name)
    {
        $file = array_search($name, PW_PROFILE_FILES, true);
        if ($file !== false)
            return $file;

        return strpos($name, 'np/') === 0 ? PW_PROFILE_SOURCE . '/' . substr($name, 3) : PW_SOURCE . '/' . $name;
    }

    // git's hash of a file's content, which GitHub's list of files gives
    function pwGitHash($content)
    {
        return sha1('blob ' . strlen($content) . "\0" . $content);
    }

    // how many styles each ultimate frame has, from the bot's
    // GetCardVariantsCount() ("Quality.Delta => 8,"), or null
    function pwParseVariants($code)
    {
        $start = strpos((string)$code, 'public static int GetCardVariantsCount(this Card card)');
        if ($start === false)
            return null;
        $body = substr($code, $start);
        $end = strpos($body, '}');
        preg_match_all('/Quality\.(\w+)\s*=>\s*(\d+)/', $end === false ? $body : substr($body, 0, $end), $matches, PREG_SET_ORDER);
        $variants = [];
        foreach ($matches as [, $quality, $count])
            $variants[$quality] = (int)$count;

        return $variants ?: null;
    }

    // writes a file of the mirror through a temporary name
    function pwWrite($name, $content)
    {
        $file = pwDir() . '/' . $name;
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir))
            return false;

        return replaceFile($file, $content, 0644);
    }

    // Asks GitHub for the folder's files and what changed since; returns the
    // state with 'pending' filled, or with 'error' when GitHub did not answer
    function pwCheck($state, $now)
    {
        $state['checked'] = $now;
        [$body, $status] = pwGet('https://api.github.com/repos/' . PW_REPO . '/git/trees/' . PW_BRANCH . '?recursive=1', 'application/vnd.github+json');
        $tree = $status === 200 ? json_decode((string)$body, true) : null;
        if (!is_array($tree['tree'] ?? null) || !empty($tree['truncated'])) {
            $state['error'] = 'GitHub nie dał listy plików (HTTP ' . $status . ')';
            return $state;
        }

        $files = $state['files'] ?? [];
        $wanted = [];
        foreach ($tree['tree'] as $entry)
            if (($entry['type'] ?? '') === 'blob' && ($name = pwLocalName((string)($entry['path'] ?? ''))) !== null)
                $wanted[$name] = (string)$entry['sha'];

        // gone from the repository: gone here too
        foreach (array_diff_key($files, $wanted) as $name => $hash) {
            @unlink(pwDir() . '/' . $name);
            unset($files[$name]);
        }
        // what changed in the repository, and what is not as it should be here
        // (gone or changed on the disk): checked against the content itself
        $pending = [];
        foreach ($wanted as $name => $hash) {
            $local = pwDir() . '/' . $name;
            if (($files[$name] ?? null) !== $hash || !is_file($local) || pwGitHash((string)@file_get_contents($local)) !== $hash) {
                $pending[$name] = $hash;
                unset($files[$name]);
            }
        }

        // the styles of the ultimate frames, from the bot's code
        [$code, $codeStatus] = pwGet(pwRawUrl(PW_VARIANTS_SOURCE), 'text/plain');
        $variants = $codeStatus === 200 ? pwParseVariants($code) : null;
        if ($variants !== null)
            pwWrite('variants.json', json_encode($variants));

        $state['files'] = $files;
        $state['pending'] = $pending;
        unset($state['error']);
        if ($variants === null)
            $state['error'] = 'nie udało się odczytać liczby stylów ramek z ' . PW_VARIANTS_SOURCE;

        return $state;
    }

    // Syncs the mirror when it is due, downloading for at most $budget
    // seconds; returns how many files came
    function pwSync($now, $budget = PW_RUN_BUDGET)
    {
        $state = readData('pw');
        $pending = $state['pending'] ?? [];
        $wait = isset($state['error']) && !$pending ? PW_RETRY : PW_SYNC_EVERY;
        if (!$pending && $now - (int)($state['checked'] ?? 0) < $wait)
            return 0;

        if (!is_dir(dataDir()))
            @mkdir(dataDir(), 0750, true);
        $lock = @fopen(dataDir() . '/pw.lock', 'ce');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock !== false)
                fclose($lock);
            return 0;
        }

        // read again under the lock: another run may have written it meanwhile
        forgetData('pw');
        $state = readData('pw');
        $pending = $state['pending'] ?? [];
        if (!$pending) {
            $state = pwCheck($state, $now);
            $pending = $state['pending'] ?? [];
        }

        $started = microtime(true);
        $done = 0;
        $failed = 0;
        foreach ($pending as $name => $hash) {
            if (microtime(true) - $started >= $budget || $failed >= 3)
                break;
            [$content, $status] = pwGet(pwRawUrl(pwSourcePath($name)));
            if ($status !== 200 || $content === false || pwGitHash($content) !== $hash || !pwWrite($name, $content)) {
                $failed++;
                $state['error'] = 'nie udało się pobrać ' . $name . ' (HTTP ' . $status . ')';
                continue;
            }
            $state['files'][$name] = $hash;
            unset($state['pending'][$name]);
            $done++;
        }
        if (empty($state['pending'])) {
            unset($state['pending']);
            if ($failed === 0 && ($done > 0 || !isset($state['synced'])))
                $state['synced'] = $now;
        }
        writeData('pw', $state);
        flock($lock, LOCK_UN);
        fclose($lock);

        return $done;
    }

    // for the panel: how many pictures the mirror has, waiting, since when in
    // sync and what went wrong last
    function pwStatus()
    {
        $state = readData('pw');

        return [
            'files' => count($state['files'] ?? []),
            'pending' => count($state['pending'] ?? []),
            'checked' => $state['checked'] ?? null,
            'synced' => $state['synced'] ?? null,
            'error' => $state['error'] ?? null
        ];
    }
