<?php
    // inc/gallery.php: the media a request is answered with.

    test('sendMedia sends the whole file or one range', function () {
        $dir = tempDir();
        file_put_contents($dir . '/a.png', 'ABCDEF');
        unset($_SERVER['HTTP_RANGE'], $_SERVER['HTTP_IF_NONE_MATCH']);

        ob_start();
        sendMedia($dir . '/a.png', 'public');
        assertSame('ABCDEF', ob_get_clean());

        $_SERVER['HTTP_RANGE'] = 'bytes=2-4';
        ob_start();
        sendMedia($dir . '/a.png', 'public');
        $part = ob_get_clean();
        unset($_SERVER['HTTP_RANGE']);
        assertSame('CDE', $part);

        $_SERVER['HTTP_RANGE'] = 'bytes=99-';
        ob_start();
        sendMedia($dir . '/a.png', 'public');
        $none = ob_get_clean();
        unset($_SERVER['HTTP_RANGE']);
        assertSame('', $none, 'a range past the end sends no body');
    });

    test('sendMovedLink answers with a redirect or a 404, without a body', function () {
        writeData('moved', ['a.png' => 'b.webp']);

        ob_start();
        sendMovedLink('a.png');
        assertSame('', ob_get_clean(), 'a redirect has no body');

        ob_start();
        sendMovedLink('missing.png');
        assertSame('', ob_get_clean(), 'a 404 has no body');
    });

    test('a user file link cannot leave the folder belonging to its token', function () {
        $base = tempDir();
        mkdir($base . '/users/123-First/nested', 0700, true);
        mkdir($base . '/users/807-Second', 0700, true);
        file_put_contents($base . '/users/123-First/nested/own image.png', 'OWN');
        file_put_contents($base . '/users/807-Second/secret.png', 'OTHER ACCOUNT');
        writeData('user-folders', ['123' => 'abcdef0123456789']);
        unset($_SERVER['HTTP_RANGE'], $_SERVER['HTTP_IF_NONE_MATCH']);

        http_response_code(200);
        ob_start();
        sendUserFile($base, userLinkRel('/i/u/abcdef0123456789/nested/own%20image.png'));
        assertSame('OWN', ob_get_clean(), 'a normal public link still works');
        assertSame(200, http_response_code());

        foreach ([
            '/i/u/abcdef0123456789/../807-Second/secret.png',
            '/i/u/abcdef0123456789/..%5C807-Second%5Csecret.png',
            '/i/u/abcdef0123456789/nested/../../807-Second/secret.png'
        ] as $uri) {
            http_response_code(200);
            ob_start();
            sendUserFile($base, userLinkRel($uri));
            assertSame('', ob_get_clean(), 'no content from another account: ' . $uri);
            assertSame(404, http_response_code(), $uri);
        }

        // Canonical containment also applies to a symlink planted on the server.
        if (@symlink($base . '/users/807-Second', $base . '/users/123-First/other')) {
            http_response_code(200);
            ob_start();
            sendUserFile($base, 'u/abcdef0123456789/other/secret.png');
            assertSame('', ob_get_clean());
            assertSame(404, http_response_code());
        }

        writeData('moved', ['u/abcdef0123456789/old.png' => 'u/abcdef0123456789/new.webp']);
        ob_start();
        sendUserFile($base, 'u/abcdef0123456789/old.png');
        assertSame('', ob_get_clean());
        assertSame(302, http_response_code(), 'old conversion links still redirect');
        http_response_code(200);
    });
