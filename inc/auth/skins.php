<?php
    // The skins of the home page: the colours, the logo and, if the skin
    // brings any, the words it speaks, each unlocked by a find of the
    // Safeguard's register; the site's owner has them all. An account picks one
    // in its profile (/account/), which is kept in inc/data/skins.json, and the
    // choice goes into the cookie "skin" as the colour of the HUD goes into
    // "hud", so the static pages (the home page) can wear it before the first
    // paint (js/skins.js). The look itself is in css/skins.css and one
    // js/skin-<id>.js per skin; tests/test_skins.php keeps the ids here, there
    // and in the CSS together.

    const SKINS = [
        // id => the name, the find that unlocks it (null for none), whether the
        // skin speaks words on the home page (the profile offers the
        // translation switch only for those) and a line for the profile
        'teto' => [
            'name' => 'Teto',
            'find' => 'aprilfools',
            'words' => true,
            'what' => 'japońska strona z 1 kwietnia: czerwień, logo Teto i japońskie napisy'
        ],
        'miku' => [
            'name' => 'Miku',
            'find' => 'aprilfools',
            'words' => true,
            'what' => 'turkusowa zieleń, logo Miku i japońskie napisy'
        ],
        'tao' => [
            'name' => 'Hu Tao',
            'find' => 'halloween',
            'words' => false,
            'what' => 'czerwień ognia i logo Hu Tao, bez napisów'
        ],
        'rin' => [
            'name' => 'Rin',
            'find' => 'halloween',
            'words' => false,
            'what' => 'żółć Rin i jej logo, bez napisów'
        ],
        'march7' => [
            'name' => 'March 7th',
            'find' => 'xmas',
            'words' => false,
            'what' => 'róż March 7th i jej logo, bez napisów'
        ],
        'padoru' => [
            'name' => 'Padoru',
            'find' => 'xmas',
            'words' => false,
            'what' => 'świąteczne złoto padoru i jego logo, bez napisów'
        ],
        'reisen' => [
            'name' => 'Reisen',
            'find' => 'easter',
            'words' => false,
            'what' => 'lawendowy fiolet i logo Reisen, bez napisów'
        ],
        'peko' => [
            'name' => 'Pekora',
            'find' => 'easter',
            'words' => false,
            'what' => 'błękit Pekory i jej logo, bez napisów'
        ],
        'furi' => [
            'name' => 'Furina',
            'find' => 'newyear',
            'words' => false,
            'what' => 'królewski błękit Furiny i jej logo, bez napisów'
        ],
        'albedo' => [
            'name' => 'Albedo',
            'find' => 'newyear',
            'words' => false,
            'what' => 'złoto Albedo na fioletowym tle i jej logo, bez napisów'
        ]
    ];

    // The groups of the profile's picker: what a skin is unlocked by, read as
    // its season (a find outside the map keeps its own name of the register;
    // a skin unlocked by nothing sits under "Zawsze")
    const SKIN_GROUPS = [
        'aprilfools' => 'Prima aprilis',
        'halloween' => 'Halloween',
        'xmas' => 'Święta',
        'easter' => 'Wielkanoc',
        'newyear' => 'Nowy rok'
    ];

    // whether the skin speaks words on the home page (js/skin-<id>.js): the
    // profile offers its translation switch only for the ones that do
    function skinSpeaks($skin)
    {
        return $skin !== '' && (SKINS[$skin]['words'] ?? false) === true;
    }

    // whether the account may wear the skin: its find in the register, or the
    // owner, who wears anything; an unknown skin: no
    function skinUnlocked($skin, $id)
    {
        $entry = SKINS[$skin] ?? null;
        if ($entry === null)
            return false;
        if (realPanelAdminId($id))
            return true;

        return $entry['find'] === null || isset(safeguardFinds($id)[$entry['find']]);
    }

    // what the account picked: the skin and whether it speaks its words on the
    // home page (its colours and logo stay either way). The first format kept
    // only the id, so a plain string still reads as a skin that speaks, as the
    // first format of the HUD colour did.
    function skinPrefs($id)
    {
        $entry = readData('skins')[(string)$id] ?? null;
        if (is_string($entry))
            $entry = ['skin' => $entry];
        $entry = is_array($entry) ? $entry : [];

        $skin = $entry['skin'] ?? '';
        $skin = is_string($skin) && skinUnlocked($skin, $id) ? $skin : '';

        return ['skin' => $skin, 'words' => ($entry['words'] ?? true) !== false];
    }

    // what the account wears: its pick, while it may still wear it (a find
    // taken back stops the skin); '' for none
    function skinPref($id)
    {
        return skinPrefs($id)['skin'];
    }

    // whether the skin speaks its words on the home page (js/skins.js)
    function skinWords($id)
    {
        return skinPrefs($id)['words'];
    }

    // keeps or clears the pick and its words; refuses an unknown or locked skin
    function setSkin($id, $skin, $words = true)
    {
        $skin = (string)$skin;
        if ($skin !== '' && !skinUnlocked($skin, $id))
            return false;

        return updateData('skins', function (&$all) use ($id, $skin, $words) {
            if ($skin === '')
                unset($all[(string)$id]);
            else if ($words)
                $all[(string)$id] = $skin;
            else
                $all[(string)$id] = ['skin' => $skin, 'words' => false];
        });
    }

    // The cookies of the skin as the account has it (account.php), so the home
    // page can wear it at once and take it off when it is gone (a session that
    // ended, a find taken back); "skinwords" = "off" keeps the skin from
    // speaking there
    function sendSkinCookie($id)
    {
        $prefs = skinPrefs($id);
        if ($prefs['skin'] !== '')
            setcookie('skin', $prefs['skin'], time() + 31536000, '/');
        else if (isset($_COOKIE['skin']))
            setcookie('skin', '', time() - 3600, '/');

        if ($prefs['skin'] !== '' && !$prefs['words'])
            setcookie('skinwords', 'off', time() + 31536000, '/');
        else if (isset($_COOKIE['skinwords']))
            setcookie('skinwords', '', time() - 3600, '/');
    }

    // the attribute for <html> of a page that knows the account, so it wears
    // the skin before the first paint (hudHtmlAttributes() in
    // inc/auth/preferences.php); '' when there is none
    function skinHtmlAttribute($id)
    {
        $skin = $id === null ? '' : skinPref($id);

        return $skin === '' ? '' : ' data-skin="' . htmlspecialchars($skin, ENT_QUOTES, 'UTF-8') . '"';
    }
