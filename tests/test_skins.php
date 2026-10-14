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

    test('one seasonal find unlocks both of its new skins and no skins from the other event', function () {
        foreach (['sakura' => ['chisato', 'yaemiko'], 'perseids' => ['suisei', 'hoshino']] as $find => $keys) {
            clearData();
            foreach (['chisato', 'yaemiko', 'suisei', 'hoshino'] as $key)
                assertFalse(skinUnlocked($key, '111'));
            writeData('finds', ['111' => [$find => time()]]);
            foreach (['chisato', 'yaemiko', 'suisei', 'hoshino'] as $key) {
                assertSame(in_array($key, $keys, true), skinUnlocked($key, '111'));
                assertFalse(skinSpeaks($key));
            }
            foreach ($keys as $key) {
                assertTrue(setSkin('111', $key));
                assertSame($key, skinPref('111'));
            }
        }
    });

    test('a permissions preview removes the owners skin bonus but keeps earned skins and the saved pick', function () {
        sessionFor('sanakan-skins-preview', ['id' => '999', 'name' => 'Root', 'avatar' => 'a']);
        try {
            setSkin('999', 'teto', false);
            foreach ([['role' => 'user'], ['panel' => false], ['role' => 'user', 'panel' => true]] as $rights) {
                setTestRights($rights, 15);
                assertFalse(skinUnlocked('teto', '999'), 'no owner bonus in a preview, even with panel access');
                assertSame('', skinPref('999'));
                assertFalse(setSkin('999', 'tao'), 'the server refuses an unearned pick too');
                assertSame(['skin' => 'teto', 'words' => false], readData('skins')['999'], 'the saved pick survives');
            }
            writeData('finds', ['999' => ['aprilfools' => time()]]);
            assertTrue(skinUnlocked('teto', '999'));
            assertTrue(skinUnlocked('miku', '999'));
            assertFalse(skinUnlocked('tao', '999'));
            assertSame('teto', skinPref('999'), 'earned skins remain usable in the preview');
            setTestRights(null);
            assertTrue(skinUnlocked('tao', '999'), 'ending the preview restores the owner bonus');
            setTestRights(['role' => 'user'], 15);
            $_SESSION['test_rights']['until'] = time() - 1;
            testRights(null, true);
            assertTrue(skinUnlocked('tao', '999'), 'expiry restores the bonus too');
        } finally {
            setTestRights(null);
            sessionEnd();
        }
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

    test('the generated skin catalog matches PHP and every skin has its logo and CSS', function () {
        $root = dirname(__DIR__);
        assertSame(skinCatalogScript(), (string)file_get_contents($root . '/js/skin-catalog.js'),
            'run php tools/asset-versions.php after changing the catalog');
        foreach (SKINS as $key => $skin) {
            if ($key !== 'custom')
                assertTrue(is_file($root . '/skins/' . $key . '.webp'), $key . ' has a logo');
            assertTrue(strpos((string)@file_get_contents($root . '/css/skins.css'), 'html[data-skin="' . $key . '"]') !== false,
                'css/skins.css styles ' . $key);
            assertTrue(is_bool($skin['words'] ?? null), $key . ' says whether it speaks words');
            assertSame($skin['words'], isset($skin['lang']), $key . ' declares its language when it has words');

            $find = $skin['find'];
            assertTrue($find === null || $find === 'all' || isset(SAFEGUARD_FINDS[$find]) || isset(SAFEGUARD_SEASONAL[$find]),
                $key . ' is unlocked by a find of the register');
            assertTrue($find === null || isset(SKIN_GROUPS[$find]), $key . ' has its group in the picker');
        }
    });

    test('custom skin requires every normal and seasonal find, even for the owner', function () {
        $complete = array_fill_keys(array_keys(SAFEGUARD_FINDS + SAFEGUARD_SEASONAL), time());
        foreach (['111', '999'] as $id) {
            assertFalse(skinUnlocked('custom', $id));
            writeData('finds', [$id => $complete]);
            assertTrue(skinUnlocked('custom', $id));
            foreach (array_keys($complete) as $missing) {
                $incomplete = $complete;
                unset($incomplete[$missing]);
                writeData('finds', [$id => $incomplete]);
                assertFalse(skinUnlocked('custom', $id), 'missing ' . $missing);
                assertFalse(setSkin($id, 'custom'));
            }
        }
    });

    test('custom colours are validated and kept when switching or losing a find', function () {
        $input = customSkinPrefs('111');
        $input['colors']['accent'] = '#12AB34';
        assertFalse(setCustomSkin('111', $input));
        writeData('finds', ['111' => array_fill_keys(array_keys(SAFEGUARD_FINDS + SAFEGUARD_SEASONAL), time())]);
        assertTrue(setSkin('111', 'custom', true, $input));
        assertSame('#12ab34', customSkinPrefs('111')['colors']['accent']);
        assertSame('custom', skinPref('111'));
        assertSame('', customSkinAppearance('111')['image']);
        assertTrue(strpos(skinHtmlAttribute('111'), 'data-custom-skin=') !== false);
        $saved = customSkinPrefs('111');
        foreach (['red', '#123', '#1234567', '#123456;--text:red', ['#123456'], "#123456\n"] as $bad) {
            $invalid = $input;
            $invalid['colors']['accent'] = $bad;
            assertFalse(setSkin('111', 'custom', true, $invalid));
            assertSame($saved, customSkinPrefs('111'));
        }
        $invalid = $input;
        unset($invalid['colors']['text']);
        assertFalse(setCustomSkin('111', $invalid));
        assertTrue(setSkin('111', 'teto'));
        assertSame($saved, customSkinPrefs('111'));
        assertTrue(setSkin('111', 'custom'));
        removeSafeguardFinds('111', ['killy']);
        assertSame('', skinPref('111'));
        assertSame('', skinHtmlAttribute('111'));
        assertSame($saved, customSkinPrefs('111'));
        assertSame('custom', readData('skins')['111']);
        assertSame(customSkinPrefs('222')['colors']['accent'], '#9b59b6');
    });

    test('custom images are restricted to the accounts own real gallery images', function () {
        $base = tempDir();
        mkdir($base . '/users/111-Own/nested', 0750, true);
        mkdir($base . '/users/222-Other', 0750, true);
        writeData('user-folders', ['111' => '1111111111111111', '222' => '2222222222222222']);
        writeData('roles', ['111' => ['roles' => ['user' => true, 'onGuild' => true], 'checked' => time()]]);
        writeData('finds', ['111' => array_fill_keys(array_keys(SAFEGUARD_FINDS + SAFEGUARD_SEASONAL), time())]);
        userTokens('111');
        $tokens = userTokens('222');
        $ownToken = $tokens['111'];
        $otherToken = $tokens['222'];
        $image = imagecreatetruecolor(10, 10);
        imagepng($image, $base . '/users/111-Own/nested/own.png');
        imagepng($image, $base . '/users/222-Other/foreign.png');
        file_put_contents($base . '/users/111-Own/fake.png', 'not an image');
        file_put_contents($base . '/users/111-Own/movie.webm', 'not an image');
        $own = 'u/' . $ownToken . '/nested/own.png';
        $input = customSkinPrefs('111');
        $input['image'] = $own;
        assertSame($own, customSkinImage('111', $own, $base));
        assertTrue(setCustomSkin('111', $input, $base));
        foreach (['u/' . $otherToken . '/foreign.png', 'u/' . $ownToken . '/../222-Other/foreign.png',
            'u/' . $ownToken . '/fake.png', 'u/' . $ownToken . '/movie.webm', 'u/' . $ownToken . '/missing.png',
            'https://example.org/image.png', 'users/111-Own/nested/own.png'] as $bad) {
            assertSame(null, customSkinImage('111', $bad, $base), $bad);
            $input['image'] = $bad;
            assertFalse(setCustomSkin('111', $input, $base), $bad);
        }
        $images = customSkinImages('111', $base);
        assertTrue(isset($images[$own]));
        assertFalse(isset($images['u/' . $otherToken . '/foreign.png']));
        assertFalse(isset($images['u/' . $ownToken . '/movie.webm']));
        addMovedLink('users/111-Own/old.png', 'users/111-Own/nested/own.png');
        assertSame($own, customSkinImage('111', 'u/' . $ownToken . '/old.png', $base));
        unlink($base . '/users/111-Own/nested/own.png');
        assertSame(null, customSkinImage('111', $own, $base));
    });
