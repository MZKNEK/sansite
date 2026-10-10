<?php
    function fileFingerprint($path)
    {
        clearstatcache(true, $path);
        if (!is_file($path) || is_link($path)) return null;
        $stat = @stat($path);
        $hash = @hash_file('sha256', $path);
        return $stat === false || $hash === false ? null : ['hash' => $hash, 'size' => $stat['size'],
            'mtime' => $stat['mtime'], 'ino' => $stat['ino'], 'dev' => $stat['dev']];
    }

    function conversionArtifactInUse($path)
    {
        foreach (glob(operationsDir() . '/*.json') ?: [] as $file)
            if ((loadJsonFile($file)['candidate'] ?? null) === $path) return true;
        return false;
    }

    function pruneConversionStaging($deadline)
    {
        // Age alone is insufficient: never clean beside a live encoder.
        @mkdir(dataDir() . '/locks', 0750, true);
        $active = @fopen(dataLockFile('gallery-encoder'), 'c');
        if (!$active || !flock($active, LOCK_EX | LOCK_NB)) {
            if ($active) fclose($active);
            return;
        }
        try {
            foreach (glob(dataDir() . '/conversion-staging/*') ?: [] as $path) {
                if (microtime(true) >= $deadline) break;
                if (!is_link($path) && is_file($path) && filemtime($path) < time() - 7200 && !conversionArtifactInUse($path))
                    if (!@unlink($path)) throw new RuntimeException('Nie udało się sprzątnąć pliku roboczego konwersji.');
            }
        } finally { fclose($active); }
    }

    function conversionSnapshot($full)
    {
        return galleryWrite(function () use ($full) {
            $fingerprint = fileFingerprint($full);
            if ($fingerprint === null) throw new RuntimeException('Źródło zniknęło.');
            $dir = dataDir() . '/conversion-staging';
            if (!is_dir($dir) && !@mkdir($dir, 0750, true)) throw new RuntimeException('Nie można przygotować konwersji.');
            $free = @disk_free_space($dir);
            if ($free !== false && $free < $fingerprint['size'] * 2 + 104857600)
                throw new RuntimeException('Za mało wolnego dysku na konwersję.');
            $budget = defined('GALLERY_STAGING_MAX_BYTES') ? GALLERY_STAGING_MAX_BYTES : 1073741824;
            if (treeSize($dir) + treeSize(webpPreviewDir()) + 2 * $fingerprint['size'] > $budget)
                throw new RuntimeException('Budżet plików roboczych konwersji jest zajęty. Spróbuj później.');
            $input = $dir . '/' . bin2hex(random_bytes(16)) . '.' . extensionOf($full);
            if (!@copy($full, $input) || @hash_file('sha256', $input) !== $fingerprint['hash']) {
                @unlink($input);
                throw new RuntimeException('Nie udało się wykonać stabilnej kopii źródła.');
            }
            @chmod($input, 0600);
            return ['input' => $input, 'fingerprint' => $fingerprint, 'originalFull' => $full];
        });
    }

    // Entire commit is journaled. Recovery only recognizes its own artifacts
    // by their hashes; a file with the right name alone proves nothing.
    function commitConversion($full, $rel, $candidate, $wanted, $fingerprint, &$error, $source = '', $actor = null)
    {
        $mutation = galleryLock();
        $policy = galleryLock('policy', false);
        $release = null;
        $operation = null;
        try {
            $owner = folderOwner($rel);
            if ($actor !== null && !isGalleryAdminId($actor) && ($owner !== (string)$actor || !isGalleryUploaderId($actor)))
                throw new RuntimeException('Utracono uprawnienia do źródła; wynik pominięto.');
            $release = $owner === null ? null : accountLock($owner);
            if ($owner !== null && $release === null) throw new RuntimeException('Folder konta jest zajęty.');
            if ($fingerprint === null || fileFingerprint($full) !== $fingerprint)
                throw new RuntimeException('Źródło zmieniło się lub zniknęło; wynik pominięto.');
            $converted = fileFingerprint($candidate);
            if ($converted === null || $converted['size'] <= 0) throw new RuntimeException('Brak wyniku konwersji.');
            if (!isVideo($wanted) && imageLimitError($candidate) !== null) throw new RuntimeException('Wynik nie spełnia ograniczeń obrazu.');
            if ($owner !== null) {
                $base = substr($full, 0, -strlen($rel) - 1);
                $why = accountQuotaError($base, $owner, 0, $converted['size'] - $fingerprint['size'], $converted['size']);
                if ($why !== null) throw new RuntimeException($why);
                if (userTrashBytes($owner) + $fingerprint['size'] > accountLimits($owner)['values']['trashBytes'])
                    throw new RuntimeException('Brak miejsca na oryginał w koszu.');
            }
            $name = $wanted === $full ? basename($full) : freeName(dirname($full), basename($wanted));
            $target = dirname($full) . '/' . $name;
            // Keep the commit on one filesystem; deployment inventory must
            // verify data and gallery are not on different mounted volumes.
            if (@stat(dirname($target))['dev'] !== @stat(dirname($candidate))['dev'])
                throw new RuntimeException('Staging konwersji musi być na tym samym systemie plików co galeria.');
            $trashId = date('YmdHis') . '-' . bin2hex(random_bytes(4));
            $trashFolder = trashDir() . '/' . $trashId;
            if (!@mkdir($trashFolder, 0750, true)) throw new RuntimeException('Nie można przygotować kosza.');
            $trashPath = $trashFolder . '/' . basename($full);
            $item = ['name' => basename($full), 'from' => $rel, 'folder' => false, 'size' => $fingerprint['size'],
                'deleted' => time(), 'by' => 'konwersja'] + ($owner !== null ? ['owner' => $owner] : []);
            $operation = beginOperation(['kind' => 'conversion', 'trash' => $trashId, 'src' => $full,
                'dst' => $trashPath, 'target' => $target, 'candidate' => $candidate, 'item' => $item,
                'sourceHash' => $fingerprint['hash'], 'candidateHash' => $converted['hash'], 'uploadHash' => $source]);
            if ($operation === null) throw new RuntimeException('Nie można zapisać dziennika konwersji.');
            if (!galleryRename($full, $trashPath)) throw new RuntimeException('Nie można przenieść oryginału.');
            if (file_exists($target) || !galleryRename($candidate, $target)) throw new RuntimeException('Nie można opublikować wyniku.');
            @chmod($target, 0644);
            @touch($target, $fingerprint['mtime']);
            if (!updateData('trash', function (&$items) use ($trashId, $item) { $items[$trashId] = $item; }))
                throw new RuntimeException('Wynik zapisano, ale indeks kosza wymaga odzyskania; zachowano dziennik.');
            $targetRel = ltrim((dirname($rel) === '.' ? '' : dirname($rel)) . '/' . $name, '/');
            if (($targetRel !== $rel && !addMovedLink($rel, $targetRel)) || !recordHash($target, $targetRel, $source))
                throw new RuntimeException('Wynik zapisano, ale metadane wymagają odzyskania; zachowano dziennik.');
            if ($rel !== $targetRel) dropHashes($rel);
            endOperation($operation);
            $operation = null;
            return $targetRel;
        } catch (RuntimeException $failure) {
            $error = $failure->getMessage();
            if ($operation !== null) {
                $error .= ' Zachowano dziennik operacji do odzyskania.';
                releaseOperation($operation);
            }
            return false;
        } finally {
            if ($release !== null) $release();
            $policy(); $mutation();
        }
    }
