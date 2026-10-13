<?php
    // inc/auth.php: the HUD colour chosen in the profile (/account/), stored in
    // inc/data/hud.json and put on <html> by hudHtmlAttributes(). A panel admin
    // may pick any role's colour, others keep their own.

    test('hudPrefs defaults to default and own, and stores a picked pair', function () {
        clearData();
        assertSame(['mode' => 'default', 'color' => 'own'], hudPrefs('111'));
        assertTrue(setHud('111', 'full', 'dev'));
        assertSame('full', hudMode('111'));
        assertSame('dev', hudColor('111'));
        assertSame(['mode' => 'full', 'color' => 'dev'], hudPrefs('111'));
        assertSame('default', hudMode('222'), 'one account does not touch another');
    });

    test('setHud refuses an unknown mode or colour, and default+own clears it', function () {
        clearData();
        assertFalse(setHud('111', 'rainbow', 'own'));
        assertFalse(setHud('111', 'accent', 'magenta'));
        assertSame('default', hudMode('111'));

        setHud('111', 'accent', 'dev');
        assertSame('accent', hudMode('111'));
        assertTrue(setHud('111', 'default', 'own'));
        assertSame('default', hudMode('111'));
        assertSame('own', hudColor('111'));
        assertSame([], readData('hud'), 'default + own keeps no entry');
    });

    test('the first format, a plain string, still reads as a mode', function () {
        clearData();
        writeData('hud', ['111' => 'full']);
        assertSame('full', hudMode('111'));
        assertSame('own', hudColor('111'));
    });

    test('hudColorKey gives the picked colour, or the role for own', function () {
        clearData();
        $badge = ['key' => 'admin'];
        assertSame('admin', hudColorKey('111', $badge), 'own follows the role');
        assertSame('', hudColorKey('111', null), 'own with no known role is empty');

        setHud('111', 'accent', 'dev');
        assertSame('dev', hudColorKey('111', $badge), 'the picked colour wins');
        assertSame('dev', hudColorKey('111', null), 'a picked colour needs no role');
    });

    test('hudHtmlAttributes carries the mode, the colour and the own role', function () {
        clearData();
        assertSame(' data-hud="default"', hudHtmlAttributes(null), 'the server marks an anonymous default instead of trusting old cookies');

        $user = ['id' => '111', 'name' => 'One', 'avatar' => 'a'];
        setHud('111', 'accent', 'dev');
        $attrs = hudHtmlAttributes($user);
        assertTrue(strpos($attrs, 'data-hud="accent"') !== false);
        assertTrue(strpos($attrs, 'class="role-dev"') !== false, 'the picked colour, not the role');
        assertTrue(strpos($attrs, 'data-hud-own="') !== false);
    });

    test('snowOff is kept per account and clears when turned back on', function () {
        clearData();
        assertFalse(snowOff('111'));
        assertTrue(setSnowOff('111', true));
        assertTrue(snowOff('111'));
        assertFalse(snowOff('222'), 'one account does not touch another');
        assertTrue(setSnowOff('111', false));
        assertFalse(snowOff('111'));
        assertSame([], readData('snow'), 'snow on keeps no entry');
    });
