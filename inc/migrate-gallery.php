<?php
    // Offline only: stop writers and cron, back up data, dry-run, then migrate.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require __DIR__ . '/gallery.php';
    try {
        $dry = in_array('--dry-run', $argv, true);
        $release = galleryLock('policy');
        try {
            $access = readDataStrict('access', true);
            foreach (['settings', 'trash', 'shares', 'roles', 'sessions'] as $name)
                readDataStrict($name, true);
            $count = 0;
            foreach ($access['uploadLimits'] ?? [] as $id => $limit) {
                if (!preg_match('/^\d{17,20}$/D', (string)$id))
                    throw new RuntimeException('Nieprawidłowe ID starego limitu.');
                $group = $access['galleryLimitOverrides'][$id] ?? ['revision' => 0, 'values' => []];
                if ($group['revision'] !== 0) validateQuotaGroup($group);
                if (isset($group['values']['files']) && $group['values']['files'] !== $limit)
                    throw new RuntimeException('Konflikt starego i nowego limitu konta ' . $id . '.');
                $group['values']['files'] = $limit;
                $group['revision']++;
                $group['changedBy'] = 'migration';
                $group['changedAt'] = time();
                $access['galleryLimitOverrides'][$id] = $group;
                $count++;
            }
            unset($access['uploadLimits']);
            foreach ($access['galleryLimitOverrides'] ?? [] as $group) validateQuotaGroup($group);
            galleryLimitDefaults();
            if (!$dry) {
                if (!updateData('access', function (&$data) use ($access) { $data = $access; }))
                    throw new RuntimeException('Błąd zapisu migracji access.');
                // Initialize only absent documents, never replace an existing one.
                foreach (['settings', 'trash', 'shares', 'roles', 'sessions'] as $name)
                    if (!is_file(dataFile($name)) && !updateData($name, function (&$data) { $data = []; }))
                        throw new RuntimeException('Błąd inicjalizacji ' . $name);
                    elseif (!is_file(dataFile($name)) && !writeJsonFile(dataFile($name), []))
                        throw new RuntimeException('Błąd zapisu ' . $name);
                if (!replaceFile(dataDir() . '/initialized', "gallery-v1\n", 0640))
                    throw new RuntimeException('Błąd zapisu znacznika inicjalizacji.');
            }
            fwrite(STDOUT, ($dry ? 'Dry-run: ' : '') . $count . " przeniesionych limitów.\n");
        } finally { $release(); }
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }
