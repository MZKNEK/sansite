<?php
    // inc/auth.php: the skins of the home page (inc/auth/skins.php), picked in
    // the profile (/account/), kept in inc/data/skins.json and unlocked by a
    // find of the Safeguard's register; the site's owner wears them all, and
    // each may speak its words on the home page or keep them off.

    if (!defined('PANEL_ADMINS')) define('PANEL_ADMINS', ['999']);

    test('skinPrefs starts empty and speaking, and keeps an unlocked pick per account', function () {
        clearData();
        assertSame(['skin' => '', 'words' => true], skinPrefs('111'), 'nothing picked, nothing to keep off');
        assertSame('', skinPref('111'), 'no pick, no skin');
        assertFalse(skinUnlocked('teto', '111'), 'locked until its find is in the register');
        assertFalse(setSkin('111', 'teto'), 'the locked skin is not kept');

        writeData('finds', ['111' => ['aprilfools' => time()]]);
        assertTrue(skinUnlocked('teto', '111'));
        assertTrue(setSkin('111', 'teto'));
        assertSame('teto', skinPref('111'));
        assertSame('', skinPref('222'), 'one account does not touch another');
    });

    test('the skin speaks by default, and the account may keep the words off', function () {
        clearData();
        writeData('finds', ['111' => ['aprilfools' => time()]]);
        assertTrue(setSkin('111', 'teto', false));
        assertSame('teto', skinPref('111'), 'the colours and the logo stay');
        assertFalse(skinWords('111'));
        assertSame(['skin' => 'teto', 'words' => false], skinPrefs('111'));

        assertTrue(setSkin('111', 'teto'));
        assertTrue(skinWords('111'), 'and the words come back');
        assertSame('teto', readData('skins')['111'], 'the plain id is the skin that speaks');
    });

    test('the first format, a plain id, still reads as a skin that speaks', function () {
        clearData();
        writeData('finds', ['111' => ['aprilfools' => time()]]);
        writeData('skins', ['111' => 'teto']);
        assertSame('teto', skinPref('111'));
        assertTrue(skinWords('111'));
    });

    test('setSkin refuses an unknown skin, and the default clears the pick', function () {
        clearData();
        writeData('finds', ['111' => ['aprilfools' => time()]]);
        assertFalse(setSkin('111', 'rainbow'));
        assertSame('', skinPref('111'));

        assertTrue(setSkin('111', 'teto'));
        assertTrue(setSkin('111', ''));
        assertSame('', skinPref('111'));
        assertSame([], readData('skins'), 'the default keeps no entry');
    });

    test('the owner wears any skin without its find', function () {
        clearData();
        assertTrue(skinUnlocked('teto', '999'), 'the owner has them all');
        assertTrue(setSkin('999', 'teto'));
        assertSame('teto', skinPref('999'));
    });

    test('a find taken back stops the skin', function () {
        clearData();
        writeData('finds', ['111' => ['aprilfools' => time()]]);
        setSkin('111', 'teto');
        writeData('finds', []);

        assertSame('', skinPref('111'), 'what the profile shows and the cookie carries (sendSkinCookie)');
    });

    test('skinHtmlAttribute carries the pick for <html>, nothing otherwise', function () {
        clearData();
        assertSame('', skinHtmlAttribute(null));
        assertSame('', skinHtmlAttribute('111'));

        writeData('finds', ['111' => ['aprilfools' => time()]]);
        setSkin('111', 'teto');
        assertSame(' data-skin="teto"', skinHtmlAttribute('111'));
    });

    test('skinSpeaks tells the skins that bring words from the rest', function () {
        assertTrue(skinSpeaks('teto'));
        assertTrue(skinSpeaks('miku'));
        assertFalse(skinSpeaks('tao'), 'Hu Tao brings none');
        assertFalse(skinSpeaks('albedo'));
        assertFalse(skinSpeaks('sadako'), 'Sadako brings none');
        assertFalse(skinSpeaks('lain'));
        assertFalse(skinSpeaks('cibo'), 'Cibo brings none');
        assertFalse(skinSpeaks('killy'));
        assertFalse(skinSpeaks(''), 'the default speaks nothing');
        assertFalse(skinSpeaks('rainbow'), 'and an unknown skin neither');
    });

    test('every skin has its script and its block in the CSS', function () {
        $root = dirname(__DIR__);
        foreach (SKINS as $key => $skin) {
            $script = (string)@file_get_contents($root . '/js/skin-' . $key . '.js');
            assertTrue(strpos($script, "id: '" . $key . "'") !== false, 'js/skin-' . $key . '.js defines the skin');
            assertTrue(strpos((string)@file_get_contents($root . '/css/skins.css'), 'html[data-skin="' . $key . '"]') !== false,
                'css/skins.css styles ' . $key);
            assertTrue(is_bool($skin['words'] ?? null), $key . ' says whether it speaks words');

            $find = $skin['find'];
            assertTrue($find === null || isset(SAFEGUARD_FINDS[$find]) || isset(SAFEGUARD_SEASONAL[$find]),
                $key . ' is unlocked by a find of the register');
            assertTrue($find === null || isset(SKIN_GROUPS[$find]), $key . ' has its group in the picker');
        }
    });
