<?php
    // What the panel's main page shows, read and counted before any of it is
    // written: panelOverviewData() gives it all as [name => value], for
    // admin/index.php to extract() for its views. Part of the panel.

    function panelOverviewData($galleryDir)
    {
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

        // requests for access, newest first; ones of accounts let in meanwhile go
        // away, dropped from the file as it is at that moment
        $keep = function ($stored) {
            $kept = [];
            foreach (array_keys(REQUEST_LABELS) as $for)
                foreach ($stored[$for] ?? [] as $id => $request)
                    if (!($for === 'gallery' ? canViewGalleryId((string)$id) : canViewApiId((string)$id)))
                        $kept[$for][(string)$id] = $request;

            return $kept;
        };
        $stored = readData('requests');
        $kept = $keep($stored);
        if ($kept != $stored)
            updateData('requests', function (&$stored) use ($keep) { $stored = $keep($stored); });
        $requests = [];
        foreach ($kept as $for => $list)
            foreach ($list as $id => $request)
                $requests[] = $request + ['id' => (string)$id, 'for' => $for];
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
        $cronCommand = "echo '* * * * * www-data php " . str_replace('\\', '/', realpath(__DIR__ . '/../check-bot.php'))
            . " > /dev/null 2>&1' > /etc/cron.d/sanakan-status";
        // the cleanup of the trash, its own cron job (inc/run-maintenance.php)
        $maintenance = maintenanceState();
        $maintenanceLate = maintenanceLate();
        $maintenanceCommand = "echo '*/10 * * * * www-data php " . str_replace('\\', '/', realpath(__DIR__ . '/../run-maintenance.php'))
            . " > /dev/null 2>&1' > /etc/cron.d/sanakan-maintenance";
        $thumbFiles = $thumbs['files'];
        $thumbBytes = $thumbs['bytes'];
        $specFile = botFile('swagger.json');
        $privateTime = botPrivateCommandsTime();
        $heartbeatLast = botHeartbeatTime();
        $system = systemStats();

        // availability of the site (inc/diag.php): the rounds of 24 h, failures, traffic of the last hour
        $now = time();
        $diagSince = max($now - 86400, diagClearedAt());
        $diagRounds = diagRounds($diagSince);
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
        $diagCron = "echo '* * * * * www-data php " . str_replace('\\', '/', realpath(__DIR__ . '/../check-site.php'))
            . " > /dev/null 2>&1' > /etc/cron.d/sanakan-site";

        $trash = trashItems();
        $shares = activeShares();
        uasort($shares, function ($a, $b) { return $b['created'] <=> $a['created']; });
        $history = historyEntries(100);

        return get_defined_vars();
    }
