<?php
    // The trash: moving into it and back, its budget per account, deleting for
    // good, and the log of operations that lets one cut short be finished. Part
    // of the gallery, loaded by inc/gallery.php; cleaned up by inc/maintenance.php.

    // ---- Trash ------------------------------------------------------------------
    // Deleted files and folders go to inc/data/trash/<id>/ for TRASH_DAYS days;
    // the admin panel restores them or deletes them for good. inc/ is not
    // reachable from the web, so trashed pictures stop working by their links.

    function trashDir()
    {
        return dataDir() . '/trash';
    }

    // [id => ['name', 'from' (rel), 'folder' (bool), 'size', 'deleted' (time), 'by',
    // 'owner' (the account whose folder it came from)]], newest first
    function trashItems()
    {
        $items = readData('trash');
        uasort($items, function ($a, $b) {
            return ($b['deleted'] ?? 0) - ($a['deleted'] ?? 0);
        });

        return $items;
    }

    // what an account itself deleted from its folder, for it to bring back: the
    // trash items whose path is in its folder and that it did not take out of
    // its own trash (hideTrashItem(); the panel still sees those)
    function ownTrashItems($id)
    {
        return array_filter(trashItems(), function ($item) use ($id) {
            return empty($item['hidden']) && inUserFolder($item['from'] ?? '', $id);
        });
    }

    // An account with a folder of its own takes an item out of its own trash: the
    // entry disappears for it, but the file stays for TRASH_DAYS and the panel
    // can still bring it back.
    function hideTrashItem($id)
    {
        $saved = updateData('trash', function (&$items) use ($id) {
            if (!isset($items[$id]))
                return false;
            $items[$id]['hidden'] = time();
            return true;
        }, $found);

        return $saved && $found;
    }

    // whose budget a trash entry counts against: the account it was deleted from,
    // by its ID (older entries have it only in their path)
    function trashOwner($item)
    {
        return isset($item['owner']) ? (string)$item['owner'] : folderOwner($item['from'] ?? '');
    }

    // bytes in the trash deleted from the folder of an account, hidden ones too
    function userTrashBytes($id)
    {
        $bytes = 0;
        $items = readData('trash');
        foreach ($items as $item)
            if (trashOwner($item) === (string)$id)
                $bytes += (int)($item['size'] ?? 0);
        foreach (glob(operationsDir() . '/*.json') ?: [] as $file) {
            $operation = loadJsonFile($file);
            if (!validOperationDocument($operation)) throw new DataStoreException('invalid-journal', basename($file));
            if (in_array($operation['kind'] ?? '', ['trash', 'conversion'], true) && !isset($items[$operation['trash'] ?? ''])
                && isset($operation['item'], $operation['dst']) && file_exists($operation['dst'])
                && trashOwner($operation['item']) === (string)$id)
                $bytes += (int)$operation['item']['size'];
        }

        return $bytes;
    }

    function operationFor($trashId)
    {
        foreach (glob(operationsDir() . '/*.json') ?: [] as $file) {
            $operation = loadJsonFile($file);
            if (!validOperationDocument($operation)) throw new DataStoreException('invalid-journal', basename($file));
            if ((string)($operation['trash'] ?? '') === (string)$trashId) return true;
        }
        return false;
    }

    function validOperationDocument($op)
    {
        return is_array($op) && ($op['format'] ?? null) === 1 && isset($op['kind'], $op['trash'], $op['src'], $op['dst'])
            && in_array($op['kind'], ['trash', 'restore', 'conversion'], true) && is_string($op['src']) && is_string($op['dst'])
            && isset($op['item']) && is_array($op['item']) && validDataSchema('trash', [$op['item']])
            && is_string($op['trash']) && preg_match('/^\d{14}-[a-f0-9]{8}$/D', $op['trash']);
    }

    // One lock for taking things out of the trash (restoring, deleting for good),
    // so a restore and the cleanup never work on one entry at once; inside the
    // lock of the account (accountLock()) when both are held.
    function trashLock()
    {
        return fileLock(dataLockFile('trash-ops'), true);
    }

    // ---- The log of operations ---------------------------------------------------
    // A move to the trash and a restore change the disk and then the trash's
    // index; a process killed between the two would leave a file without its
    // entry or an entry without its file. So each is noted in
    // inc/data/operations/<id>.json before it starts ('kind' trash or restore,
    // the trash entry, where the file was and where it goes) and the note is
    // removed once both are done or undone. Notes left behind are finished or
    // undone by the maintenance (recoverOperations() in inc/maintenance.php).

    function operationsDir()
    {
        return dataDir() . '/operations';
    }

    // the id of the note, or null when it could not be written (the operation
    // then does not start)
    function beginOperation($operation)
    {
        $id = date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $dir = operationsDir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir))
            return null;

        $lock = fileLock(dataLockFile('operation-' . $id), true);
        if ($lock === null) return null;
        $locks = &operationLocks();
        $locks[$id] = $lock;
        if (writeJsonFile($dir . '/' . $id . '.json', ['format' => 1, 'started' => time()] + $operation)) return $id;
        releaseOperation($id);
        return null;
    }

    function &operationLocks()
    {
        static $locks = [];
        return $locks;
    }

    function releaseOperation($id)
    {
        $locks = &operationLocks();
        if (isset($locks[$id])) { fclose($locks[$id]); unset($locks[$id]); }
    }

    function endOperation($id)
    {
        $ok = @unlink(operationsDir() . '/' . $id . '.json');
        releaseOperation($id);
        return $ok;
    }

    // Moves a file or folder to the trash; false when it stays where it is, with
    // the reason in $error when it is one the visitor can do something about.
    // What is deleted from the folder of an account counts against its trash
    // (USER_TRASH_MAX_BYTES): when that is full, the entries of it that are past
    // TRASH_DAYS go first, and when there is still no room the file stays. A
    // gallery admin deleting is not held to it (its entries still count).
    function moveToTrash($full, $rel, &$error = null)
    {
        $mutation = galleryLock();
        $policy = galleryLock('policy', false);
        try { return moveToTrashLocked($full, $rel, $error); }
        finally { $policy(); $mutation(); }
    }

    function moveToTrashLocked($full, $rel, &$error = null)
    {
        $error = null;
        $owner = folderOwner($rel);
        $release = $owner === null ? null : accountLock($owner);
        if ($owner !== null && $release === null) {
            $error = 'folder konta jest teraz zajęty, spróbuj za chwilę';
            return false;
        }

        try {
            readData('trash');
            clearstatcache();
            if (!file_exists($full) || is_link($full)) return false;
            $size = treeSize($full);
            $trashLimit = $owner === null ? 0 : accountLimits($owner)['values']['trashBytes'];
            if ($owner !== null && userTrashBytes($owner) + $size > $trashLimit) {
                purgeTrash($owner);
                $used = userTrashBytes($owner);
                if ($used + $size > $trashLimit) {
                    $error = 'kosz folderu jest pełny (' . formatSize($used) . ' z ' . formatSize($trashLimit) . ', a to ' . formatSize($size)
                        . '); rzeczy znikają z niego po ' . TRASH_DAYS . ' dniach';
                    return false;
                }
            }

            $id = date('YmdHis') . '-' . bin2hex(random_bytes(4));
            $dir = trashDir() . '/' . $id;
            if (!@mkdir($dir, 0750, true))
                return false;

            $user = siteUser();
            $item = [
                'name' => basename($rel),
                'from' => $rel,
                'folder' => is_dir($full),
                'size' => $size,
                'deleted' => time(),
                'by' => $user['name'] ?? ''
            ] + ($owner !== null ? ['owner' => $owner] : []);
            $operation = beginOperation(['kind' => 'trash', 'trash' => $id, 'src' => $full, 'dst' => $dir . '/' . basename($rel), 'item' => $item]);
            if ($operation === null || !galleryRename($full, $dir . '/' . basename($rel))) {
                @rmdir($dir);
                if ($operation !== null)
                    endOperation($operation);
                return false;
            }

            $ok = updateData('trash', function (&$items) use ($id, $item) { $items[$id] = $item; });
            // without its entry the file could not be found or brought back: it
            // goes back where it was
            if ($ok) {
                endOperation($operation);
            } elseif (!file_exists($full) && galleryRename($dir . '/' . basename($rel), $full)) {
                @rmdir($dir);
                endOperation($operation);
            } else {
                $error = 'zapis i wycofanie nie powiodły się; zachowano dziennik operacji do odzyskania';
                releaseOperation($operation);
            }

            return $ok;
        } finally {
            if ($release !== null)
                $release();
        }
    }

    // Back where it was deleted from (that folder is made again when it is gone;
    // the folder of an account is the one it has now, whatever its nick was).
    // Back into the folder of an account only within its limits, a folder with
    // all that is in it; when it does not fit it stays in the trash. Returns
    // where it ended up, or null with the reason in $error.
    function restoreFromTrash($base, $id, &$error = null)
    {
        $mutation = galleryLock();
        $policy = galleryLock('policy', false);
        try { return restoreFromTrashLocked($base, $id, $error); }
        finally { $policy(); $mutation(); }
    }

    function restoreFromTrashLocked($base, $id, &$error = null)
    {
        $error = null;
        if (!preg_match('/^\d{14}-[a-f0-9]{8}$/D', (string)$id)) { $error = 'Nieprawidłowy wpis kosza.'; return null; }
        $item = readData('trash')[$id] ?? null;
        if ($item === null) {
            $error = 'Tego już nie ma w koszu, odśwież stronę.';
            return null;
        }
        $owner = folderOwner($item['from']);
        $release = $owner === null ? null : accountLock($owner);
        $lock = ($owner === null || $release !== null) ? trashLock() : null;
        if ($lock === null) {
            if ($release !== null)
                $release();
            $error = 'Kosz jest teraz zajęty, spróbuj za chwilę.';
            return null;
        }

        try {
            // what another request did meanwhile counts
            forgetData('trash');
            $item = readData('trash')[$id] ?? null;
            $source = trashDir() . '/' . $id . '/' . ($item['name'] ?? '');
            if ($item === null || !file_exists($source)) {
                $error = 'Tego już nie ma w koszu, odśwież stronę.';
                return null;
            }
            if (!empty($item['purging'])) {
                $error = $item['name'] . ' jest właśnie usuwane na zawsze (minęło ' . TRASH_DAYS . ' dni).';
                return null;
            }

            $from = $item['from'];
            if ($owner !== null && ($current = userFolder($base, $owner)) !== null)
                $from = preg_replace('~^' . USERS_DIR . '/[^/]+~', $current, $from);
            if ($owner !== null) {
                [$count, $bytes, $biggest] = treeUse($source);
                $why = accountRoomError($base, $owner, $count, $bytes, $biggest);
                if ($why !== null) {
                    $error = 'Nie przywrócono ' . $item['name'] . ': ' . $why . '. Zostaje w koszu; zrób miejsce i spróbuj jeszcze raz.';
                    return null;
                }
            }

            $folderRel = dirname($from) === '.' ? '' : dirname($from);
            $folder = $base . ($folderRel === '' ? '' : '/' . $folderRel);
            $name = (is_dir($folder) || @mkdir($folder, 0755, true)) ? freeName($folder, $item['name']) : null;
            $operation = $name === null ? null : beginOperation(['kind' => 'restore', 'trash' => $id, 'src' => $source, 'dst' => $folder . '/' . $name]);
            if ($operation === null || !galleryRename($source, $folder . '/' . $name)) {
                if ($operation !== null)
                    endOperation($operation);
                $error = 'Nie udało się przywrócić ' . $item['name'] . '.';
                return null;
            }

            if (!updateData('trash', function (&$items) use ($id) { unset($items[$id]); })) {
                // the entry would stay without its file: the file goes back to the trash
                $undone = !file_exists($source) && galleryRename($folder . '/' . $name, $source);
                if ($undone) endOperation($operation);
                else releaseOperation($operation);
                $error = 'Nie udało się przywrócić ' . $item['name'] . ($undone ? '.' : '; wycofanie nie powiodło się, zachowano dziennik do odzyskania.');
                return null;
            }
            @rmdir(trashDir() . '/' . $id);
            endOperation($operation);

            return ltrim($folderRel . '/' . $name, '/');
        } finally {
            fclose($lock);
            if ($release !== null)
                $release();
        }
    }

    function deleteFromTrash($id)
    {
        return purgeTrashEntry($id) === 'done';
    }

    // Deletes a trash entry for good, its files first, the entry only once they
    // are gone. 'done'; 'partial' when $deadline (microtime) came in a big
    // folder: the entry, marked 'purging', cannot be restored any more and is
    // finished later; 'skipped' when it is gone, or with $expiredBefore no
    // longer older than that once looked at under the lock; 'failed' when a file
    // would not go (the entry stays to be tried again). The lock is held only
    // for one call, so a restore waits at most that long.
    function purgeTrashEntry($id, $expiredBefore = null, $deadline = null)
    {
        if (!preg_match('/^\d{14}-[a-f0-9]{8}$/D', (string)$id)) return 'failed';
        if (operationFor($id)) return 'skipped';
        $lock = trashLock();
        if ($lock === null)
            return 'failed';

        try {
            forgetData('trash');
            $item = readData('trash')[$id] ?? null;
            if ($item === null)
                return 'skipped';
            if ($expiredBefore !== null && empty($item['purging']) && ($item['deleted'] ?? 0) >= $expiredBefore)
                return 'skipped';
            if (empty($item['purging']) && !updateData('trash', function (&$items) use ($id) {
                    if (isset($items[$id]))
                        $items[$id]['purging'] = time();
                }))
                return 'failed';

            $path = trashDir() . '/' . $id;
            if (file_exists($path) && !removeTree($path, $deadline) && file_exists($path))
                return $deadline !== null && microtime(true) >= $deadline ? 'partial' : 'failed';

            return updateData('trash', function (&$items) use ($id) { unset($items[$id]); }) ? 'done' : 'failed';
        } finally {
            fclose($lock);
        }
    }

    // [full path, path inside the item] of a picture or film in the trash: the
    // item itself ($rel '') or, for a folder, a file inside it; or null
    function trashFile($id, $rel)
    {
        $items = readData('trash');
        if (!preg_match('/^\d{14}-[0-9a-f]{8}$/', (string)$id) || !isset($items[$id]))
            return null;

        $item = trashDir() . '/' . $id . '/' . $items[$id]['name'];
        $rel = trim(str_replace('\\', '/', (string)$rel), '/');
        if (empty($items[$id]['folder'])) {
            $file = $rel === '' && is_file($item) ? [$item, ''] : null;
        } else {
            $dir = realpath($item);
            $file = $dir === false || $rel === '' ? null : resolvePath(str_replace('\\', '/', $dir), $rel, false);
        }

        return $file && (isImage($file[0]) || isVideo($file[0])) ? $file : null;
    }

    // the pictures and films of a folder in the trash, [path inside it, size],
    // at most $limit of them, and how many there are
    function trashFolderMedia($id, $limit)
    {
        $items = readData('trash');
        if (!isset($items[$id]) || empty($items[$id]['folder']))
            return [[], 0];

        $dir = realpath(trashDir() . '/' . $id . '/' . $items[$id]['name']);
        if ($dir === false)
            return [[], 0];

        $files = [];
        $count = 0;
        $walk = function ($path, $rel) use (&$walk, &$files, &$count, $limit) {
            $names = @scandir($path) ?: [];
            natcasesort($names);
            foreach ($names as $name) {
                if ($name[0] === '.')
                    continue;
                $full = $path . '/' . $name;
                if (is_dir($full)) {
                    $walk($full, $rel . $name . '/');
                } elseif (isImage($name) || isVideo($name)) {
                    if ($count++ < $limit)
                        $files[] = [$rel . $name, (int)@filesize($full)];
                }
            }
        };
        $walk(str_replace('\\', '/', $dir), '');

        return [$files, $count];
    }

    // what has been in the trash longer than TRASH_DAYS goes for good; with
    // $owner only what was deleted from the folder of that account (a full
    // trash of an account makes room so). The rest is done by the maintenance
    // (inc/maintenance.php) from its own cron job.
    function purgeTrash($owner = null)
    {
        $expiredBefore = time() - TRASH_DAYS * 86400;
        foreach (readData('trash') as $id => $item)
            if (($item['deleted'] ?? 0) < $expiredBefore && ($owner === null || trashOwner($item) === (string)$owner))
                purgeTrashEntry((string)$id, $expiredBefore);
    }
