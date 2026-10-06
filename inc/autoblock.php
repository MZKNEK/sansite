<?php
    // Blocking scanners in Cloudflare without waiting for someone in the panel,
    // once the panel turned it on: inc/check-site.php runs it by cron after its
    // minute of rounds. An address is blocked only when it asked, in the last
    // day, for at least AUTO_BLOCK_PROBES different paths no visitor of this
    // site asks for (.env, .git, wp-login.php, PHP files the site does not
    // have...; inc/diag.php keeps them per scanner), so a mistyped link never
    // gets anyone blocked, and a scanning tool's user agent alone does not either.
    //
    // Never blocked automatically: local addresses and Cloudflare's, the
    // addresses logged-in accounts came from, and the crawlers of search
    // engines and known research scanners (Google, Bing, Censys, Shodan...)
    // whose address really is theirs: its reverse DNS gives a name under
    // AUTO_BLOCK_TRUSTED and that name leads back to the address, which a
    // scanner calling itself Googlebot cannot fake. An address taken off the
    // list in the panel is not blocked again automatically. Automatic blocks
    // come off after AUTO_BLOCK_DAYS; a scanner still at it is blocked again.
    // What it did is kept in inc/data/autoblock.json and goes to the history;
    // the addresses taken off in the panel in autoblock-released.json, which
    // the panel writes without waiting for a run.
    require_once __DIR__ . '/diag.php';
    require_once __DIR__ . '/cloudflare.php';

    const AUTO_BLOCK_PROBES = 3;
    const AUTO_BLOCK_PER_RUN = 5;
    const AUTO_BLOCK_DAYS = 30;
    // a crawler found to be one is looked up again after this many days
    const AUTO_BLOCK_TRUSTED_DAYS = 7;
    // the start of the comment of an automatic block on the Cloudflare list
    const AUTO_BLOCK_COMMENT = 'Automat';
    // the reverse DNS names of the crawlers of search engines and link previews,
    // and of research scanners that say who they are
    const AUTO_BLOCK_TRUSTED = [
        'googlebot.com', 'google.com', 'search.msn.com', 'applebot.apple.com', 'crawl.yahoo.net',
        'yandex.ru', 'yandex.net', 'yandex.com', 'baidu.com', 'baidu.jp', 'petalsearch.com',
        'fbsv.net', 'ahrefs.com', 'ahrefs.net',
        'censys-scanner.com', 'shodan.io', 'internet-measurement.com', 'shadowserver.org'
    ];

    function autoBlockEnabled()
    {
        return cloudflareConfigured() && !empty(readData('settings')['autoBlock']);
    }

    // The name the reverse DNS of an address gives, when it is under one of
    // AUTO_BLOCK_TRUSTED and leads back to the very address; null otherwise.
    function autoBlockTrustedHost($ip)
    {
        $host = @gethostbyaddr($ip);
        if (!is_string($host) || $host === $ip)
            return null;
        $host = strtolower(rtrim($host, '.'));

        $trusted = false;
        foreach (AUTO_BLOCK_TRUSTED as $domain)
            if ($host === $domain || substr($host, -strlen($domain) - 1) === '.' . $domain)
                $trusted = true;
        if (!$trusted)
            return null;

        // anyone can give its own address a name under googlebot.com; only
        // Google can make that name point to it
        $packed = @inet_pton($ip);
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record)
            if (@inet_pton($record['ip'] ?? $record['ipv6'] ?? '') === $packed)
                return $host;

        return null;
    }

    // An address taken off the list in the panel stays off: it is not blocked
    // automatically again. $target as the list has it (an IPv6 /64).
    function autoBlockReleased($target)
    {
        $released = readData('autoblock-released');
        $released[$target] = time();
        writeData('autoblock-released', $released);
    }

    // the lock of a run, so two cron runs do not work at once; false when taken
    function autoBlockLock()
    {
        if (!is_dir(dataDir()))
            @mkdir(dataDir(), 0750, true);
        $lock = @fopen(dataDir() . '/autoblock.lock', 'c');
        if ($lock === false)
            return false;
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return false;
        }

        return $lock;
    }

    // One run: blocks up to AUTO_BLOCK_PER_RUN new scanners, and once a day
    // takes the automatic blocks older than AUTO_BLOCK_DAYS off.
    function autoBlockRun()
    {
        if (!autoBlockEnabled())
            return;
        $lock = autoBlockLock();
        if (!$lock)
            return;  // the run before is still at it

        $state = readData('autoblock');
        $released = readData('autoblock-released');
        $now = time();
        foreach ($state['trusted'] ?? [] as $target => [$host, $time])
            if ($time < $now - AUTO_BLOCK_TRUSTED_DAYS * 86400)
                unset($state['trusted'][$target]);
        foreach ($state['failed'] ?? [] as $target => $time)
            if ($time < $now - 86400)
                unset($state['failed'][$target]);
        foreach (array_keys($released) as $target)
            unset($state['blocked'][$target]);

        $addresses = readData('addresses');
        $blocked = 0;
        $lookups = 0;
        foreach (diagScanners() as $ip => $scanner) {
            if ($blocked >= AUTO_BLOCK_PER_RUN || $lookups >= 2 * AUTO_BLOCK_PER_RUN)
                break;
            if (count($scanner['probes']) < AUTO_BLOCK_PROBES || $scanner['last'] < $now - 86400)
                continue;
            $target = cloudflareTarget($ip);
            if ($target === null || diagIsCloudflare($ip) || accountsAtAddress($ip, $addresses)
                    || isset($state['blocked'][$target]) || isset($released[$target]) || isset($state['trusted'][$target]) || isset($state['failed'][$target]))
                continue;

            $lookups++;
            if (($host = autoBlockTrustedHost($ip)) !== null) {
                $state['trusted'][$target] = [$host, $now];
                continue;
            }

            $why = cutText(implode(', ', array_slice($scanner['probes'], 0, 3)), 70);
            $error = cloudflareBlock($ip, AUTO_BLOCK_COMMENT . ', ' . count($scanner['probes']) . ' ścieżek: ' . $why);
            // tried again in a day; a problem with Cloudflare itself stops the run
            if ($error !== null) {
                $state['failed'][$target] = $now;
                $state['error'] = [$now, $target . ': ' . $error];
                break;
            }
            $state['blocked'][$target] = [$now, $why];
            unset($state['error']);
            addHistory('cloudflare', 'Automatycznie zablokowano w Cloudflare ' . $target . ': pytał o ' . count($scanner['probes']) . ' ścieżek, których nikt tu nie szuka (' . $why . ').', 'automat');
            $blocked++;
        }

        // once a day the old automatic blocks come off, by the comment on the list
        if (($state['pruned'] ?? 0) < $now - 86400) {
            $state['pruned'] = $now;
            [$items] = cloudflareBlocked();
            $removed = [];
            foreach ($items ?: [] as $item)
                if (strpos($item['comment'], AUTO_BLOCK_COMMENT . ',') === 0 && $item['created'] && $item['created'] < $now - AUTO_BLOCK_DAYS * 86400
                        && cloudflareUnblock($item['id']) === null) {
                    unset($state['blocked'][$item['ip']]);
                    $removed[] = $item['ip'];
                }
            if ($removed)
                addHistory('cloudflare', 'Po ' . AUTO_BLOCK_DAYS . ' dniach odblokowano w Cloudflare: ' . implode(', ', $removed) . '.', 'automat');
        }

        writeData('autoblock', $state);
        fclose($lock);
    }
