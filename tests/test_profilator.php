<?php
    // inc/profilator.php: Profilator's way to the bot's api/Profile.
    require_once __DIR__ . '/../inc/profilator.php';

    test('profilatorPath takes only ids of Discord accounts and the bot\'s own', function () {
        assertSame('discord/123456789012345678', profilatorPath('123456789012345678'));
        assertSame('discord/1/images', profilatorPath(PROFILATOR_BOT, 'images'));
        assertNull(profilatorPath('01'));
        assertNull(profilatorPath('2'));
        assertSame('discord/123456789012345678/images/7', profilatorPath('123456789012345678', 'images/7'));
        assertNull(profilatorPath('../catalog'));
        assertNull(profilatorPath('12345'));
        assertNull(profilatorPath('123456789012345678/../9'));
    });

    test('profilatorImageType knows the four picture types by their bytes', function () {
        assertSame('image/png', profilatorImageType("\x89PNG\r\n\x1a\nrest"));
        assertSame('image/jpeg', profilatorImageType("\xff\xd8\xff\xe0rest"));
        assertSame('image/gif', profilatorImageType('GIF89a....'));
        assertSame('image/webp', profilatorImageType('RIFF....WEBPVP8 '));
        assertNull(profilatorImageType('<svg xmlns="http://www.w3.org/2000/svg"/>'));
        assertNull(profilatorImageType(''));
    });

    test('profilatorMultipart makes one form with the file and a safe name', function () {
        [$body, $type] = profilatorMultipart(['use' => 'overlay'], 'file', '../evil name".png', 'BYTES', 'image/png');
        assertTrue(preg_match('~^multipart/form-data; boundary=(\S+)$~', $type, $match) === 1);
        assertContains('name="use"' . "\r\n\r\noverlay\r\n", $body);
        assertContains('filename=".._evil_name_.png"', $body);
        assertContains("Content-Type: image/png\r\n\r\nBYTES\r\n--" . $match[1] . "--\r\n", $body);
    });

    test('profilatorLayoutJson takes only an object, and not a too big one', function () {
        assertSame('{"v":1,"layers":[]}', profilatorLayoutJson(['v' => 1, 'layers' => []]));
        assertSame('{}', profilatorLayoutJson([]));
        assertNull(profilatorLayoutJson([1, 2]));
        assertNull(profilatorLayoutJson('{"v":1}'));
        assertNull(profilatorLayoutJson(['big' => str_repeat('x', PROFILATOR_MAX_LAYOUT)]));
    });

    test('profilatorKey takes a key of printable ASCII', function () {
        assertSame('0123456789abcdef', profilatorKey('0123456789abcdef'));
        assertNull(profilatorKey('short'));
        assertNull(profilatorKey(str_repeat('a', 65)));
        assertNull(profilatorKey("with space here"));
    });

    test('profilatorBot asks api/Profile with the site\'s key', function () {
        $sent = [];
        $GLOBALS['SANAKAN_HTTP'] = function ($url, $options) use (&$sent) {
            $sent[] = [$url, $options];
            return ['{"version":2}', 200];
        };
        [$body, $status] = profilatorBot('PUT', profilatorPath('123456789012345678'), '{"layout":{}}', ['Idempotency-Key' => 'abcdefgh12']);
        withoutHttp();

        assertSame(200, $status);
        assertSame('{"version":2}', $body);
        assertSame(botApiBase() . '/api/Profile/discord/123456789012345678', $sent[0][0]);
        assertSame('PUT', $sent[0][1]['method']);
        assertContains('x-app-key: ' . botAppKey(), $sent[0][1]['header']);
        assertContains('Idempotency-Key: abcdefgh12', $sent[0][1]['header']);
        assertSame('{"layout":{}}', $sent[0][1]['content']);
    });

    test('profilatorCatalog keeps the catalog a while and the old one when the bot is away', function () {
        $asked = 0;
        $GLOBALS['SANAKAN_HTTP'] = function ($url) use (&$asked) {
            $asked++;
            return [json_encode(['enabled' => false, 'features' => [['key' => 'accent']]]), 200];
        };
        $first = profilatorCatalog(1000);
        $again = profilatorCatalog(1000 + PROFILATOR_CATALOG_TTL - 1);
        assertSame(1, $asked, 'asked once while fresh');
        assertSame($first, $again);

        $GLOBALS['SANAKAN_HTTP'] = function () { return [false, 0]; };
        assertSame($first, profilatorCatalog(1000 + PROFILATOR_CATALOG_TTL), 'the old one while the bot does not answer');
        withoutHttp();
        assertSame('accent', $first['features'][0]['key']);
    });

    test('profilatorCatalog without a catalog and without the bot is null', function () {
        $GLOBALS['SANAKAN_HTTP'] = function () { return ['oops', 500]; };
        assertNull(profilatorCatalog(5000));
        withoutHttp();
    });

    test('profilatorSlotName keeps one short line and names an empty one by its place', function () {
        assertSame('Mój układ', profilatorSlotName("  Mój\n\tukład ", 0));
        assertSame('Układ 2', profilatorSlotName('   ', 1));
        assertSame('Układ 3', profilatorSlotName(['not text'], 2));
        assertSame(str_repeat('ż', PROFILATOR_SLOT_NAME), profilatorSlotName(str_repeat('ż', 100), 0));
    });

    test('profilatorSetSlot keeps three layouts per account, each account its own', function () {
        $me = '123456789012345678';
        $other = '223456789012345678';
        assertSame([null, null, null], profilatorSlots($me));

        $slots = profilatorSetSlot($me, 1, '{"v":1,"avatar":{}}', 'Zimowy', 1700000000);
        assertNull($slots[0]);
        assertSame(['name' => 'Zimowy', 'time' => 1700000000, 'layout' => '{"v":1,"avatar":{}}'], $slots[1]);
        assertSame([null, null, null], profilatorSlots($other), 'another account sees none of it');

        // the page gets the layout as an object again, an empty object still an object
        $out = profilatorSlotsOut(profilatorSlots($me));
        assertSame('{"v":1,"avatar":{}}', json_encode($out[1]['layout']));

        profilatorSetSlot($me, 1, null, '', 1700000001);
        assertSame([null, null, null], profilatorSlots($me));
        assertFalse(array_key_exists($me, readData('profilator-slots')), 'an account with no layouts leaves nothing behind');
    });

    test('profilatorFree is true only for a quote with nothing to pay', function () {
        assertTrue(profilatorFree(['missing' => [], 'sc' => 0, 'tc' => 0]));
        assertFalse(profilatorFree(['missing' => [['key' => 'accent']], 'sc' => 0, 'tc' => 0]));
        assertFalse(profilatorFree(['missing' => [], 'sc' => 500, 'tc' => 0]));
        assertFalse(profilatorFree(['missing' => [], 'sc' => 0, 'tc' => 5]));
        assertFalse(profilatorFree(null));
    });

    test('profilatorOwner lets only a site admin work on another\'s profile or the bot\'s, and not on its slots', function () {
        if (!defined('PANEL_ADMINS')) define('PANEL_ADMINS', ['999']);
        $admin = PANEL_ADMINS[0];
        $player = '123456789012345678';
        assertSame([$player, null], profilatorOwner($player, null, 'save'));
        assertSame([null, 'admin'], profilatorOwner($player, '223456789012345678', 'me'));
        assertSame(['223456789012345678', null], profilatorOwner($admin, '223456789012345678', 'save'));
        assertSame(['223456789012345678', null], profilatorOwner($admin, '223456789012345678', 'upload'));
        assertSame([PROFILATOR_BOT, null], profilatorOwner($admin, PROFILATOR_BOT, 'delete'));
        assertSame([null, 'admin'], profilatorOwner($player, PROFILATOR_BOT, 'save'));
        assertSame([null, 'as'], profilatorOwner($admin, '223456789012345678', 'slot'));
        assertSame([null, 'as'], profilatorOwner($admin, '../catalog', 'me'));
        assertSame([null, 'as'], profilatorOwner($admin, ['223456789012345678'], 'me'));
    });

    test('profilatorGrant gives for free the bot\'s profile always, a player\'s only when asked, never one\'s own', function () {
        $admin = '999';
        $player = '123456789012345678';
        assertTrue(profilatorGrant($admin, PROFILATOR_BOT, false));
        assertTrue(profilatorGrant($admin, $player, true));
        assertFalse(profilatorGrant($admin, $player, false));
        assertFalse(profilatorGrant($admin, $player, 'true'));
        assertFalse(profilatorGrant($player, $player, true));
    });
