<?php
    // The completion reward. Keep its design when another skin is selected;
    // gallery images stay private and use the gallery's existing access checks.
    const CUSTOM_SKIN_COLORS = [
        'accent' => ['Akcent', '#9b59b6'],
        'accent-light' => ['Jasny akcent', '#b670d3'],
        'accent-dark' => ['Ciemny akcent', '#713b87'],
        'bg-dark' => ['Tło', '#080909'],
        'bg-mid' => ['Drugie tło', '#202225'],
        'text' => ['Tekst', '#dcddde'],
        'home-title' => ['Tytuł strony głównej', '#f0e2ff']
    ];

    function customSkinPrefs($id)
    {
        $entry = readData('custom-skins')[(string)$id] ?? [];
        $entry = is_array($entry) ? $entry : [];
        $colors = [];
        foreach (CUSTOM_SKIN_COLORS as $key => [$label, $default]) {
            $color = $entry['colors'][$key] ?? null;
            $colors[$key] = is_string($color) && preg_match('/^#[0-9a-f]{6}$/iD', $color) ? strtolower($color) : $default;
        }

        return ['image' => is_string($entry['image'] ?? null) ? $entry['image'] : '', 'colors' => $colors];
    }

    // Resolve only an image in this account's folder, including a converted
    // file's old link. Canonical paths also reject links escaping that folder.
    function customSkinImage($id, $image, $base = null)
    {
        if (!is_string($image) || $image === '' || !isGalleryUploaderId($id))
            return null;
        require_once __DIR__ . '/../gallery.php';
        $base = str_replace('\\', '/', realpath($base ?? dirname(__DIR__, 2) . '/i') ?: '');
        if ($base === '' || !preg_match('~^u/' . USER_TOKEN . '/~', $image))
            return null;
        $folder = userFolder($base, $id);
        if ($folder === null)
            return null;
        $resolved = resolvePath($base, $image, false);
        if ($resolved === null) {
            $moved = readData('moved')[$image] ?? null;
            $resolved = is_string($moved) ? resolvePath($base, $moved, false) : null;
        }
        if ($resolved === null || strpos($resolved[1], $folder . '/') !== 0 || !isImage($resolved[1])
            || !isImageContent($resolved[1], $resolved[0]))
            return null;

        return publicRel($resolved[1]);
    }

    function setCustomSkin($id, $input, $base = null)
    {
        if (!skinUnlocked('custom', $id) || !is_array($input) || !is_array($input['colors'] ?? null))
            return false;
        $colors = [];
        foreach (CUSTOM_SKIN_COLORS as $key => $entry) {
            $color = $input['colors'][$key] ?? null;
            if (!is_string($color) || !preg_match('/^#[0-9a-f]{6}$/iD', $color))
                return false;
            $colors[$key] = strtolower($color);
        }
        $image = $input['image'] ?? '';
        if (!is_string($image))
            return false;
        if ($image !== '') {
            $image = customSkinImage($id, $image, $base);
            if ($image === null)
                return false;
        }

        return updateData('custom-skins', function (&$all) use ($id, $image, $colors) {
            $all[(string)$id] = ['image' => $image, 'colors' => $colors];
        });
    }

    function customSkinAppearance($id)
    {
        $prefs = customSkinPrefs($id);
        $image = customSkinImage($id, $prefs['image']);
        $prefs['image'] = $image === null ? '' : siteRoot() . 'i/' . fileUrl($image);

        return $prefs;
    }

    // The profile's selector includes nested folders, but no hidden files,
    // videos or symlinks. Values use tokens rather than account identifiers.
    function customSkinImages($id, $base)
    {
        if (!isGalleryUploaderId($id))
            return [];
        $folder = userFolder($base, $id);
        if ($folder === null || resolvePath($base, $folder, true) !== [$base . '/' . $folder, $folder])
            return [];
        $images = [];
        foreach (allFolders($base, $folder) as $dir)
            foreach (listNames($base . '/' . $dir, $dir) as $name) {
                $rel = $dir . '/' . $name;
                if (!isImage($name) || !is_file($base . '/' . $rel) || is_link($base . '/' . $rel)
                    || !isImageContent($name, $base . '/' . $rel))
                    continue;
                $images[publicRel($rel)] = substr($rel, strlen($folder) + 1);
            }
        natcasesort($images);

        return $images;
    }
