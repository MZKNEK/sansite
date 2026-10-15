<?php
    // The completion reward. Keep its design when another skin is selected;
    // gallery images stay private and use the gallery's existing access checks.
    const CUSTOM_SKIN_COLORS = [
        'accent' => 'Akcent',
        'accent-light' => 'Jasny akcent',
        'accent-dark' => 'Ciemny akcent',
        'bg-dark' => 'Tło',
        'bg-mid' => 'Drugie tło',
        'text' => 'Tekst',
        'home-title' => 'Tytuł strony głównej'
    ];

    // The background animations of the skins (--season-background in
    // css/skins.css), any of which a custom design may wear; '' for none.
    // tests/test_skins.php checks that every one used there is listed here.
    const CUSTOM_SKIN_BACKGROUNDS = [
        '' => 'Bez animacji',
        'snow' => 'Śnieg (Ceres Fauna, Padoru)',
        'butterflies' => 'Motyle (Echidna)',
        'magic' => 'Złoty pył i gwiazdy (Marisa Kirisame)',
        'lavender' => 'Lawendowe drobinki (Reisen)',
        'rabbits' => 'Króliczki (Pekora)',
        'fireworks' => 'Fajerwerki (Nakiri Ayame, Albedo)',
        'ink' => 'Krople atramentu (Tomie)',
        'wired' => 'Zakłócenia sygnału (Lain)',
        'netsphere' => 'Sieć punktów (Cibo)',
        'emitter' => 'Stalowy pył i żar (Killy)',
        'sakura' => 'Płatki sakury (March 7th)',
        'lightning' => 'Pioruny (Raiden Shogun)',
        'perseids' => 'Gwiazdy i komety (Suisei, Hoshino Ai)',
        'fireflies' => 'Świetliki (Firefly, Yoimiya)'
    ];

    // The editor starts with the actual site palettes, not a second set of
    // colour literals. Read once per request; css/skins.css remains canonical.
    function customSkinPalettes()
    {
        static $palettes = null;
        if ($palettes !== null)
            return $palettes;
        $css = (string)file_get_contents(dirname(__DIR__, 2) . '/css/skins.css');
        $result = [];
        foreach (['dark' => ':root', 'light' => 'html[data-skin="echidna"]'] as $base => $selector) {
            if (!preg_match('~' . preg_quote($selector, '~') . '\s*\{([^}]+)\}~s', $css, $block))
                throw new RuntimeException('Missing custom skin base: ' . $base);
            foreach (CUSTOM_SKIN_COLORS as $key => $label) {
                if ($key === 'home-title')
                    continue;
                // The light base follows Echidna's displayed page gradient;
                // her surface palette may intentionally keep a brighter input.
                if ($base === 'light' && $key === 'bg-mid'
                    && preg_match('~--page-bg-mid:\s*(#[0-9a-f]{6})\s*;~i', $block[1], $pageColor)) {
                    $result[$base][$key] = strtolower($pageColor[1]);
                    continue;
                }
                if (!preg_match('~--' . preg_quote($key, '~') . ':\s*(#[0-9a-f]{6})\s*;~i', $block[1], $color))
                    throw new RuntimeException('Missing custom skin colour: ' . $base . '/' . $key);
                $result[$base][$key] = strtolower($color[1]);
            }
            if ($base === 'dark') {
                if (!preg_match('~--heading:\s*(#[0-9a-f]{6})\s*;~i', $css, $heading))
                    throw new RuntimeException('Missing dark heading colour');
                $result[$base]['home-title'] = strtolower($heading[1]);
            } else {
                $result[$base]['home-title'] = $result[$base]['text'];
            }
        }
        return $palettes = $result;
    }

    function customSkinPrefs($id)
    {
        $entry = readData('custom-skins')[(string)$id] ?? [];
        $entry = is_array($entry) ? $entry : [];
        $base = ($entry['base'] ?? 'dark') === 'light' ? 'light' : 'dark';
        $defaults = customSkinPalettes()[$base];
        $colors = [];
        foreach (CUSTOM_SKIN_COLORS as $key => $label) {
            $color = $entry['colors'][$key] ?? null;
            $colors[$key] = is_string($color) && preg_match('/^#[0-9a-f]{6}$/iD', $color) ? strtolower($color) : $defaults[$key];
        }

        $background = $entry['background'] ?? '';
        $background = is_string($background) && isset(CUSTOM_SKIN_BACKGROUNDS[$background]) ? $background : '';

        return ['image' => is_string($entry['image'] ?? null) ? $entry['image'] : '', 'colors' => $colors, 'base' => $base, 'background' => $background];
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
        $designBase = $input['base'] ?? 'dark';
        if (!in_array($designBase, ['dark', 'light'], true))
            return false;
        $background = $input['background'] ?? '';
        if (!is_string($background) || !isset(CUSTOM_SKIN_BACKGROUNDS[$background]))
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

        return updateData('custom-skins', function (&$all) use ($id, $image, $colors, $designBase, $background) {
            $all[(string)$id] = ['image' => $image, 'colors' => $colors, 'base' => $designBase, 'background' => $background];
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
