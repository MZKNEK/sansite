<?php
    // Skin metadata shared by the profile and the generated browser catalog.
    // Unlock checks and stored preferences belong to inc/auth/skins.php.
    const SKINS = [
        // id => the name, the find that unlocks it (null for none), whether the
        // skin speaks words on the home page (the profile offers the
        // translation switch only for those) and a line for the profile
        'teto' => [
            'name' => 'Teto',
            'lang' => 'ja',
            'alt' => 'テト',
            'find' => 'aprilfools',
            'words' => true,
            'what' => 'japońska strona z 1 kwietnia: czerwień, logo Teto i japońskie napisy'
        ],
        'miku' => [
            'name' => 'Miku',
            'lang' => 'ja',
            'alt' => 'ミク',
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
        ],
        'sadako' => [
            'name' => 'Sadako',
            'find' => 'friday13',
            'words' => false,
            'what' => 'widmowy błękit studni i logo Sadako, bez napisów'
        ],
        'lain' => [
            'name' => 'Lain',
            'find' => 'friday13',
            'words' => false,
            'what' => 'śliwkowy fiolet i szaroróż Lain, jej logo, bez napisów'
        ],
        'cibo' => [
            'name' => 'Cibo',
            'find' => 'birthday',
            'words' => false,
            'what' => 'granat i lawendowa biel Cibo, jej logo, bez napisów'
        ],
        'killy' => [
            'name' => 'Killy',
            'find' => 'birthday',
            'words' => false,
            'what' => 'stalowy błękit i czerń Killy\'ego, jego logo, bez napisów'
        ],
        'chisato' => [
            'name' => 'Chisato',
            'find' => 'sakura',
            'words' => false,
            'what' => 'bordowa czerwień i krem Chisato, jej logo, bez napisów'
        ],
        'yaemiko' => [
            'name' => 'Yae Miko',
            'find' => 'sakura',
            'words' => false,
            'what' => 'róż i śliwkowy fiolet Yae Miko, jej logo, bez napisów'
        ],
        'suisei' => [
            'name' => 'Suisei',
            'find' => 'perseids',
            'words' => false,
            'what' => 'gwiezdny błękit na granacie Suisei, jej logo, bez napisów'
        ],
        'hoshino' => [
            'name' => 'Hoshino Ai',
            'find' => 'perseids',
            'words' => false,
            'what' => 'magenta i ciemny fiolet Hoshino Ai, jej logo, bez napisów'
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
        'newyear' => 'Nowy rok',
        'friday13' => 'Piątek trzynastego',
        'birthday' => 'Urodziny',
        'sakura' => 'Sakura',
        'perseids' => 'Perseidy'
    ];

    function skinCatalogScript()
    {
        $catalog = [];
        foreach (SKINS as $id => $skin)
            $catalog[$id] = ['name' => $skin['name'], 'lang' => $skin['lang'] ?? null, 'alt' => $skin['alt'] ?? $skin['name']];

        return "// Generated from inc/skin-catalog.php by tools/skin-catalog.php.\n"
            . 'window.SanakanSkinCatalog = ' . json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n";
    }
