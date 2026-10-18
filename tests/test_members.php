<?php
    // inc/members.php and inc/hues-centres.php: the files of 0x40 and WAP and
    // the middles of 0x40's pictures; wap/tags.php: WAP's list of songs.
    require_once __DIR__ . '/../inc/members.php';
    require_once __DIR__ . '/../inc/hues-centres.php';
    require_once __DIR__ . '/../wap/tags.php';

    // a login is configured, so memberAllowed() and wapAllowed() answer
    if (!defined('DISCORD_CLIENT_ID')) define('DISCORD_CLIENT_ID', 'test');
    if (!defined('DISCORD_CLIENT_SECRET')) define('DISCORD_CLIENT_SECRET', 'test');
    if (!defined('DISCORD_REDIRECT_URI')) define('DISCORD_REDIRECT_URI', 'https://example.test/auth/discord/');
    if (!defined('PANEL_ADMINS')) define('PANEL_ADMINS', ['999']);

    // an MP3 of nothing but an ID3v1 tag
    function id3v1Song($title, $artist)
    {
        return str_repeat("\xFF", 64) . 'TAG' . str_pad($title, 30, "\0") . str_pad($artist, 30, "\0") . str_repeat("\0", 65);
    }

    test('the songs of WAP are named by their tags or their names, the tags read once a file', function () {
        $dir = tempDir() . '/mu';
        mkdir($dir);
        file_put_contents($dir . '/b.mp3', id3v1Song('Tagged', 'Someone'));
        file_put_contents($dir . '/01 - Artist - Named_Song.ogg', 'OggS?');
        file_put_contents($dir . '/notes.txt', 'not a song');
        file_put_contents($dir . '/.hidden.mp3', id3v1Song('Hidden', 'Nobody'));

        $list = wapSongs($dir);
        assertSame([
            ['file' => 'song.php?f=01%20-%20Artist%20-%20Named_Song.ogg', 'title' => 'Named Song', 'artist' => 'Artist'],
            ['file' => 'song.php?f=b.mp3', 'title' => 'Tagged', 'artist' => 'Someone']
        ], $list);
        $kept = readData('wap-tags');
        assertSame(['01 - Artist - Named_Song.ogg', 'b.mp3'], array_keys($kept));

        // the kept tags are used while the file stays the same
        $kept['b.mp3']['title'] = 'From the list';
        writeData('wap-tags', $kept);
        assertSame('From the list', wapSongs($dir)[1]['title']);

        // a changed file (another time, as a copy or a deploy gives it) is read
        // again, a removed one leaves the list
        file_put_contents($dir . '/b.mp3', id3v1Song('Retagged', 'Someone else'));
        touch($dir . '/b.mp3', time() + 60);
        unlink($dir . '/01 - Artist - Named_Song.ogg');
        clearstatcache();
        assertSame([['file' => 'song.php?f=b.mp3', 'title' => 'Retagged', 'artist' => 'Someone else']], wapSongs($dir));
        assertSame(['b.mp3'], array_keys(readData('wap-tags')));
    });

    // what sendMemberFile() sends for $name, and the status it sets
    function memberFileReply($dir, $name, $range = null, $method = 'GET')
    {
        unset($_SERVER['HTTP_RANGE'], $_SERVER['HTTP_IF_NONE_MATCH']);
        if ($range !== null)
            $_SERVER['HTTP_RANGE'] = $range;
        $_SERVER['REQUEST_METHOD'] = $method;
        http_response_code(200);
        ob_start();
        sendMemberFile($dir, $name, ['ogg' => 'audio/ogg', 'webp' => 'image/webp']);
        $body = ob_get_clean();
        unset($_SERVER['HTTP_RANGE']);
        $_SERVER['REQUEST_METHOD'] = 'GET';

        return [http_response_code(), $body];
    }

    test('sendMemberFile sends a file of its folder whole or in one range', function () {
        $dir = tempDir() . '/media';
        mkdir($dir . '/Songs', 0700, true);
        file_put_contents($dir . '/Songs/loop.ogg', 'ABCDEF');

        assertSame([200, 'ABCDEF'], memberFileReply($dir, 'Songs/loop.ogg'));
        assertSame([206, 'CDE'], memberFileReply($dir, 'Songs/loop.ogg', 'bytes=2-4'));
        assertSame([206, 'EF'], memberFileReply($dir, 'Songs/loop.ogg', 'bytes=-2'));
        assertSame([416, ''], memberFileReply($dir, 'Songs/loop.ogg', 'bytes=99-'));
        assertSame([200, ''], memberFileReply($dir, 'Songs/loop.ogg', null, 'HEAD'), 'a HEAD has no body');
    });

    test('sendMemberFile gives nothing outside its folder, in a dot folder or of another type', function () {
        $base = tempDir();
        mkdir($base . '/media/.hidden', 0700, true);
        mkdir($base . '/media-other', 0700, true);
        file_put_contents($base . '/media/.hidden/secret.ogg', 'HIDDEN');
        file_put_contents($base . '/media/.dot.ogg', 'DOT');
        file_put_contents($base . '/media/notes.txt', 'TEXT');
        file_put_contents($base . '/media-other/song.ogg', 'NEIGHBOUR');
        file_put_contents($base . '/outside.ogg', 'OUTSIDE');

        foreach (['../outside.ogg', '..\\outside.ogg', '../media-other/song.ogg', '.hidden/secret.ogg', '.dot.ogg',
                  'notes.txt', "loop.ogg\0.ogg", '', 'missing.ogg'] as $name)
            assertSame([404, ''], memberFileReply($base . '/media', $name), 'refused: ' . json_encode($name));
    });

    test('the pack list carries the middles set on the site over its own', function () {
        $file = tempDir() . '/respack.json';
        file_put_contents($file, json_encode([
            'images' => ['Alpha', 'Beta', 'Gamma'],
            'centres' => ['Alpha' => 0.3, 'Beta' => 0.6, 'Gone' => 0.1],
            'animations' => [],
            'songs' => []
        ]));

        assertSame(['Alpha' => 0.3, 'Beta' => 0.6], huesPack($file)['centres'], 'a middle of a picture gone is left out');

        assertTrue(setSiteHuesCentre('Gamma', 1.7));
        assertTrue(setSiteHuesCentre('Alpha', null));
        assertTrue(setSiteHuesCentre('Beta', '0.4444'));
        assertSame(['Beta' => 0.444, 'Gamma' => 1.0], huesPack($file)['centres'], 'set, clamped, rounded, given back to the one found');
        assertSame(['Alpha' => 0.3, 'Beta' => 0.6], huesPack($file, false)['centres'], 'the pack itself keeps its own');
        assertSame(null, huesPack(tempDir() . '/missing.json'));
    });

    test('a middle set locally goes into respack.json, the rest of it kept', function () {
        $file = tempDir() . '/respack.json';
        file_put_contents($file, json_encode(['images' => ['Alpha', 'Beta'], 'centres' => (object)[], 'animations' => [], 'songs' => [['loop' => 'loop_A.ogg']]]));

        assertTrue(setPackHuesCentre($file, 'Beta', 0.25));
        assertTrue(setPackHuesCentre($file, 'Alpha', 0.5));
        $pack = json_decode(file_get_contents($file), true);
        assertSame(['Alpha' => 0.5, 'Beta' => 0.25], $pack['centres']);
        assertSame([['loop' => 'loop_A.ogg']], $pack['songs']);

        assertTrue(setPackHuesCentre($file, 'Alpha', null));
        assertTrue(setPackHuesCentre($file, 'Beta', null));
        assertSame('{}', json_encode(json_decode(file_get_contents($file))->centres), 'no middles stay an object');
    });

    test('WAP lets in LV.2 and the owners, not a higher role alone', function () {
        writeData('roles', [
            '590' => ['roles' => ['onGuild' => true, 'user' => true], 'checked' => time()],
            '591' => ['roles' => ['onGuild' => true, 'dev' => true, 'user' => true], 'checked' => time()],
        ]);
        writeData('access', ['vipAccounts' => ['590' => ['note' => '', 'added' => 1, 'by' => '999']]]);

        $allowed = function ($id) {
            sessionFor('sanakan-wap-' . $id, ['id' => $id, 'name' => 'Test', 'avatar' => 'a']);
            $result = [memberAllowed(), wapAllowed()];
            sessionEnd();
            return $result;
        };
        assertSame([true, true], $allowed('590'), 'LV.2');
        assertSame([true, false], $allowed('591'), 'a dev without LV.2 gets 0x40 only');
        assertSame([true, true], $allowed('999'), 'an owner');
        assertSame([false, false], $allowed('592'), 'no role');

        // the owner trying LV.2 without the panel
        sessionFor('sanakan-wap-test', ['id' => '999', 'name' => 'Root', 'avatar' => 'a']);
        setTestRights(['panel' => false, 'role' => 'vip'], 15);
        assertTrue(wapAllowed());
        assertSame('vip', roleBadge(siteRoles())['key']);
        assertContains('rola: vip', testRightsText(testRights('999')));
        setTestRights(['panel' => false, 'role' => 'admin'], 15);
        assertFalse(wapAllowed());
        setTestRights(null);
        sessionEnd();
    });
