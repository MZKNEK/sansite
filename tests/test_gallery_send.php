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
