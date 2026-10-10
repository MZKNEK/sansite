<?php
    // Account policy in bytes; UI accepts whole MiB. No disk reservation is
    // implied by these limits. The installation's 25 GB budget is advisory.
    function galleryQuotaFields()
    {
        return ['files' => ['Liczba plików', USER_FILES_DEFAULT, USER_FILES_MAX, 1],
            'fileBytes' => ['Limit pliku (MiB)', USER_FILE_MAX_BYTES, 128, 1048576],
            'totalBytes' => ['Aktywne miejsce (MiB)', USER_TOTAL_MAX_BYTES, 1000, 1048576],
            'trashBytes' => ['Kosz (MiB)', USER_TRASH_MAX_BYTES, 1000, 1048576]];
    }

    function quotaInteger($value, $maximum)
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[0-9]+$/D', (string)$value)
            || strlen((string)$value) > 12 || (int)$value < 1 || (int)$value > $maximum)
            throw new InvalidArgumentException('Wartość musi być liczbą całkowitą od 1 do ' . $maximum . '.');
        return (int)$value;
    }

    function validateQuotaGroup($group, $defaults = false)
    {
        if (!is_array($group) || !isset($group['revision'], $group['values']) || !is_int($group['revision'])
            || $group['revision'] < 1 || !is_array($group['values']) || ($defaults && ($group['version'] ?? null) !== 1))
            throw new DataStoreException('quota-schema', 'limits');
        foreach ($group['values'] as $key => $value) {
            $field = galleryQuotaFields()[$key] ?? null;
            if ($field === null || !is_int($value) || $value < $field[3] || $value > $field[2] * $field[3] || $value % $field[3] !== 0)
                throw new DataStoreException('quota-value', 'limits');
        }
        if ($defaults && count($group['values']) !== 4)
            throw new DataStoreException('quota-fields', 'settings');
        return $group;
    }

    function galleryLimitDefaults()
    {
        $settings = readData('settings');
        if (array_key_exists('galleryLimitDefaults', $settings))
            return validateQuotaGroup($settings['galleryLimitDefaults'], true);
        $values = [];
        foreach (galleryQuotaFields() as $key => $field)
            $values[$key] = $field[1];
        return ['version' => 1, 'revision' => 0, 'values' => $values];
    }

    function accountLimits($id, $fresh = false)
    {
        $release = galleryLock('policy', false);
        try {
            $defaults = galleryLimitDefaults();
            $access = readData('access');
            $overrides = $access['galleryLimitOverrides'] ?? [];
            $group = array_key_exists((string)$id, $overrides) ? validateQuotaGroup($overrides[(string)$id]) : ['revision' => 0, 'values' => []];
            if ($group['revision'] !== 0 || $group['values'] !== [])
                $group = validateQuotaGroup($group);
            // Transitional read only; the offline migration removes this key.
            if (isset($access['uploadLimits'][(string)$id])) {
                $old = $access['uploadLimits'][(string)$id];
                if (isset($group['values']['files']) && $group['values']['files'] !== $old)
                    throw new DataStoreException('migration-conflict', 'access');
                $group['values']['files'] = $old;
            }
            return ['values' => $group['values'] + $defaults['values'], 'overrides' => $group['values'],
                'revision' => $group['revision'], 'defaultsRevision' => $defaults['revision'],
                'defaults' => $defaults['values']];
        } finally { $release(); }
    }

    function accountUsage($base, $id)
    {
        clearstatcache();
        $folder = userFolder($base, $id);
        [$files, $bytes, $biggest] = $folder === null ? [0, 0, 0] : treeUse($base . '/' . $folder);
        $trash = 0;
        $hidden = 0;
        foreach (readData('trash') as $item)
            if (trashOwner($item) === (string)$id) {
                $trash += $item['size'];
                if (!empty($item['hidden']))
                    $hidden += $item['size'];
            }
        $trash = userTrashBytes($id);
        $values = ['files' => $files, 'totalBytes' => $bytes, 'fileBytes' => $biggest, 'trashBytes' => $trash];
        $exceeded = [];
        foreach (accountLimits($id)['values'] as $key => $limit)
            if ($values[$key] > $limit)
                $exceeded[] = $key;
        return ['values' => $values, 'hiddenBytes' => $hidden, 'exceeded' => $exceeded];
    }

    function accountQuotaError($base, $id, $count, $bytes, $biggest, $upload = false)
    {
        if ($upload && panelUploadBypass())
            return null;
        $limits = accountLimits($id, true)['values'];
        $folder = userFolder($base, $id);
        clearstatcache();
        [$has, $used] = $folder === null ? [0, 0] : treeUse($base . '/' . $folder);
        if ($biggest > $limits['fileBytes'])
            return 'plik ma ' . formatSize($biggest) . ', limit pliku to ' . formatSize($limits['fileBytes']);
        if ($count > 0 && $has + $count > $limits['files'])
            return 'limit liczby plików: ' . $has . ' z ' . $limits['files'] . ' zdjęć';
        if ($bytes > 0 && $used + $bytes > $limits['totalBytes'])
            return 'w folderze konta zostało ' . formatSize(max(0, $limits['totalBytes'] - $used)) . ' wolnego z ' . formatSize($limits['totalBytes']);
        return null;
    }

    function panelUploadBypass()
    {
        $user = siteUser();
        return $user !== null && isPanelAdminId($user['id']);
    }

    function effectiveUploadLimit($id = null)
    {
        $upload = iniBytes(ini_get('upload_max_filesize'));
        $post = iniBytes(ini_get('post_max_size'));
        $caps = [];
        if ($upload > 0) $caps[] = $upload;
        if ($post > 0) $caps[] = max(0, $post - 1048576);
        if (defined('GALLERY_PROXY_BODY_BYTES') && GALLERY_PROXY_BODY_BYTES > 0)
            $caps[] = max(0, GALLERY_PROXY_BODY_BYTES - 1048576);
        if ($id !== null && !panelUploadBypass())
            $caps[] = accountLimits($id)['values']['fileBytes'];
        return $caps ? min($caps) : 0;
    }

    function saveGalleryLimits($id, $input, $revision, $actor, $reset = [])
    {
        if ($id !== null && !preg_match('/^\d{17,20}$/D', $id))
            throw new InvalidArgumentException('ID Discord musi mieć 17–20 cyfr.');
        if ((!is_string($revision) && !is_int($revision)) || !preg_match('/^(0|[1-9][0-9]{0,9})$/D', (string)$revision))
            throw new InvalidArgumentException('Brakuje poprawnej rewizji formularza.');
        if (!is_array($input) || !is_array($reset))
            throw new InvalidArgumentException('Nieprawidłowe pola limitów.');
        $values = [];
        foreach ($input as $key => $value) {
            $field = galleryQuotaFields()[$key] ?? null;
            if ($field === null)
                throw new InvalidArgumentException('Nieznane pole limitu.');
            $values[$key] = quotaInteger($value, $field[2]) * $field[3];
        }
        foreach ($reset as $key)
            if (!isset(galleryQuotaFields()[$key]) || isset($values[$key]))
                throw new InvalidArgumentException('Nieprawidłowy reset limitu.');
        if ($id === null && (count($values) !== 4 || $reset))
            throw new InvalidArgumentException('Podaj cztery wartości domyślne.');
        $release = galleryLock('policy');
        try {
            $document = $id === null ? 'settings' : 'access';
            readData($document); // fail on invalid schema before changing it
            $saved = updateData($document, function (&$data) use ($id, $values, $reset, $revision, $actor, &$before, &$after) {
                $old = $id === null ? ($data['galleryLimitDefaults'] ?? ['revision' => 0, 'values' => []])
                    : ($data['galleryLimitOverrides'][$id] ?? ['revision' => 0, 'values' => []]);
                if ($old['revision'] !== 0) validateQuotaGroup($old, $id === null);
                if ($old['revision'] !== (int)$revision)
                    throw new UnexpectedValueException('Ustawienia zmieniły się. Odśwież stronę i spróbuj ponownie.');
                if ($id !== null && isset($data['uploadLimits']))
                    throw new DataStoreException('migration-required', 'access');
                $before = $old['values'];
                $after = $values + $before;
                foreach ($reset as $key) unset($after[$key]);
                $group = ['revision' => $old['revision'] + 1, 'values' => $after, 'changedBy' => $actor, 'changedAt' => time()];
                if ($id === null) $data['galleryLimitDefaults'] = ['version' => 1] + $group;
                else $data['galleryLimitOverrides'][$id] = $group;
            });
            if (!$saved) throw new DataStoreException('write', $document);
        } finally { $release(); }
        return addHistory('gallery-limits', 'Limity ' . ($id ?? 'domyślne') . ': ' . json_encode($before) . ' → ' . json_encode($after)
            . '; reset: ' . implode(', ', $reset) . '; administrator ' . $actor . '.');
    }

    // Reusable markup; profile fields explicitly select inherit/override.
    function galleryLimitsForm($id = null)
    {
        $policy = $id === null ? galleryLimitDefaults() : accountLimits($id);
        echo '<form class="quota-form" data-action="' . ($id === null ? 'gallery-limit-defaults' : 'account-gallery-limits') . '">';
        if ($id !== null) echo '<input type="hidden" name="id" value="' . e($id) . '" />';
        echo '<input type="hidden" name="revision" value="' . $policy['revision'] . '" />';
        foreach (galleryQuotaFields() as $key => $field) {
            // the name above the field, the unit after it
            $name = preg_replace('/ \(MiB\)$/', '', $field[0]);
            echo '<label><span>' . e($name) . '</span><span class="quota-field">';
            if ($id !== null)
                echo '<select name="mode_' . $key . '" aria-label="' . e($name) . ': skąd wartość"><option value="inherit">domyślne ('
                    . ($policy['defaults'][$key] / $field[3]) . ')</option><option value="override"'
                    . (isset($policy['overrides'][$key]) ? ' selected' : '') . '>własne</option></select>';
            echo '<input type="number" required min="1" max="' . $field[2] . '" name="' . $key . '" value="'
                . ($policy['values'][$key] / $field[3]) . '" />' . ($field[3] > 1 ? '<em>MiB</em>' : '') . '</span></label>';
        }
        echo '<span class="quota-buttons"><button class="admin-btn primary" type="submit">Zapisz limity</button>';
        if ($id !== null)
            echo '<button type="button" class="admin-btn small" data-action="account-gallery-limits-reset" data-id="' . e($id)
                . '" data-revision="' . $policy['revision'] . '">Wszystkie domyślne</button>';
        echo '</span></form>';
        if ($policy['values']['fileBytes'] > $policy['values']['totalBytes'])
            echo '<p class="warn">Limit pojedynczego pliku przekracza całkowite miejsce konta. O zapisie decyduje również dostępne miejsce.</p>';
    }

    // the account's use against each limit, a bar per limit as in the
    // gallery's numbers; over a limit the bar and the numbers turn amber
    function galleryUsageHtml($base, $id)
    {
        $usage = accountUsage($base, $id);
        $policy = accountLimits($id);
        $names = ['files' => 'Pliki', 'fileBytes' => 'Największy plik', 'totalBytes' => 'Aktywne miejsce', 'trashBytes' => 'Kosz'];
        echo '<ul class="stat-list quota-usage">';
        foreach ($names as $key => $name) {
            $used = $usage['values'][$key];
            $limit = $policy['values'][$key];
            $show = $key === 'files' ? 'formatCount' : 'formatSize';
            $over = in_array($key, $usage['exceeded'], true);
            echo '<li' . ($over ? ' class="over"' : '') . ' style="--share: ' . min(100, round(100 * $used / $limit, 1)) . '%"><span>' . e($name)
                . (isset($policy['overrides'][$key]) ? ' <i>własny limit</i>' : '') . '</span><span>' . e($show($used)) . ' z ' . e($show($limit))
                . ' &middot; ' . round(100 * $used / $limit) . '%</span></li>';
        }
        echo '</ul>';
        if ($usage['hiddenBytes'])
            echo '<p class="hint">W koszu ' . e(formatSize($usage['hiddenBytes'])) . ' to wpisy ukryte przez użytkownika; nadal zajmują miejsce, aż wygasną.</p>';
        if ($usage['exceeded'])
            echo '<p class="warn">Przekroczone: ' . e(implode(', ', array_intersect_key(['files' => 'liczba plików', 'fileBytes' => 'największy plik',
                'totalBytes' => 'aktywne miejsce', 'trashBytes' => 'kosz'], array_flip($usage['exceeded']))))
                . '. Zapis zwiększający zużycie jest zablokowany; usunięcie plików zwalnia miejsce, ukrycie wpisu kosza nie.</p>';
    }

    function refreshAccountUsageSnapshot($base, $deadline)
    {
        $snapshot = readData('gallery-usage');
        $ids = array_fill_keys(array_map('strval', array_keys(readData('access')['galleryLimitOverrides'] ?? [])), true);
        foreach (@scandir($base . '/' . USERS_DIR) ?: [] as $name)
            if (preg_match('/^(\d{17,20})(?:-|$)/', $name, $match)) $ids[$match[1]] = true;
        foreach (readData('trash') as $item)
            if (($owner = trashOwner($item)) !== null) $ids[$owner] = true;
        $ordered = array_keys($ids);
        sort($ordered, SORT_STRING);
        $cursor = $snapshot['cursor'] ?? '';
        $updated = [];
        foreach ($ordered as $id) {
            if (strcmp((string)$id, (string)$cursor) <= 0) continue;
            if (microtime(true) >= $deadline) break;
            $updated[$id] = ['time' => time(), 'usage' => accountUsage($base, (string)$id)];
            $cursor = (string)$id;
        }
        if (!$ordered || $cursor === (string)end($ordered)) $cursor = '';
        if (!updateData('gallery-usage', function (&$data) use ($updated, $cursor, $ids) {
            $data['accounts'] = $updated + array_intersect_key($data['accounts'] ?? [], $ids);
            $data['cursor'] = $cursor;
            $data['time'] = time();
        })) throw new DataStoreException('write', 'gallery-usage');
    }
