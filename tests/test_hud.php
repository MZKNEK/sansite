<?php
    // inc/auth.php: the HUD colour chosen in the profile (/account/), stored in
    // inc/data/hud.json and put on <html> by hudHtmlAttributes().

    test('hudMode defaults to default and stores a picked mode', function () {
        clearData();
        assertSame('default', hudMode('111'));
        assertTrue(setHudMode('111', 'full'));
        assertSame('full', hudMode('111'));
        assertTrue(setHudMode('222', 'accent'));
        assertSame('full', hudMode('111'), 'one account does not touch another');
        assertSame('accent', hudMode('222'));
    });

    test('setHudMode refuses an unknown mode and default clears the entry', function () {
        clearData();
        assertFalse(setHudMode('111', 'rainbow'));
        assertSame('default', hudMode('111'));

        setHudMode('111', 'accent');
        assertSame('accent', hudMode('111'));
        assertTrue(setHudMode('111', 'default'));
        assertSame('default', hudMode('111'));
        assertSame([], readData('hud'), 'default keeps no entry');
    });

    test('hudHtmlAttributes carries the chosen mode and nothing without an account', function () {
        clearData();
        assertSame('', hudHtmlAttributes(null));

        $user = ['id' => '111', 'name' => 'One', 'avatar' => 'a'];
        assertTrue(strpos(hudHtmlAttributes($user), 'data-hud="default"') !== false);

        setHudMode('111', 'accent');
        $attrs = hudHtmlAttributes($user);
        assertTrue(strpos($attrs, 'data-hud="accent"') !== false);
    });
