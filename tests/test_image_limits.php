<?php
    // inc/gallery.php: a picture is measured by its header before anything
    // decodes it, and one over the limits is refused. The files here are only
    // headers: a decoder would fail on them, and nothing should get that far.

    // a PNG of these sides, its header alone
    function pngHeader($width, $height)
    {
        $ihdr = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));
    }

    function headerFile($name, $content)
    {
        $path = tempDir() . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    test('a picture within the limits passes', function () {
        $path = headerFile('ok.png', pngHeader(4000, 3000));
        assertSame([4000, 3000, 1], imageSize($path));
        assertNull(imageLimitError($path));
    });

    test('a small file with a huge picture is refused before decoding', function () {
        $path = headerFile('bomb.png', pngHeader(30000, 30000));
        assertTrue(filesize($path) < 100, 'a few bytes');
        assertTrue(strpos(imageLimitError($path), 'dłuższy bok') !== false);
        assertFalse(loadImage($path), 'GD never gets it');
        assertNull(thumbUrl('bomb.png', $path), 'the placeholder instead of a thumbnail');
        assertFalse(convertToWebp($path, tempDir() . '/out.webp'));
    });

    test('both the side and the pixels count', function () {
        assertTrue(strpos(imageLimitError(headerFile('long.png', pngHeader(IMAGE_MAX_SIDE_DEFAULT + 1, 10))), 'bok') !== false);
        assertNull(imageLimitError(headerFile('edge.png', pngHeader(IMAGE_MAX_SIDE_DEFAULT, 1000))), 'the side itself is allowed');
        $square = (int)ceil(sqrt(IMAGE_MAX_PIXELS_DEFAULT + 1));
        assertTrue(strpos(imageLimitError(headerFile('big.png', pngHeader($square, $square))), 'Mpx') !== false);
    });

    test('an animation counts all its frames, a thumbnail only the first', function () {
        $gif = 'GIF89a' . pack('vv', 400, 400) . "\x00\x00\x00" . str_repeat("\x21\xF9\x04\x00\x00\x00\x00\x00", 3000);
        $path = headerFile('anim.gif', $gif);
        assertSame(3000, imageSize($path)[2]);
        assertTrue(strpos(imageLimitError($path), 'klatek') !== false);
        assertNull(imageLimitError($path, false));
        assertFalse(gifToWebp($path, tempDir() . '/anim.webp'), 'no conversion is started');
    });

    test('a HEIC is measured by its ispe boxes', function () {
        $ispe = function ($w, $h) { return pack('N', 20) . 'ispe' . "\x00\x00\x00\x00" . pack('NN', $w, $h); };
        $head = pack('N', 24) . 'ftypheic' . "\x00\x00\x00\x00" . 'mif1heic';
        $photo = headerFile('photo.heic', $head . $ispe(512, 512) . $ispe(4032, 3024));
        assertSame([4032, 3024, 1], imageSize($photo), 'the whole grid, not a tile');
        assertNull(imageLimitError($photo));

        $huge = headerFile('huge.heic', $head . $ispe(20000, 20000));
        assertTrue(imageLimitError($huge) !== null);
        assertFalse(heifToWebp($huge, tempDir() . '/huge.webp'));

        $blind = headerFile('blind.heic', $head);
        assertSame('nie da się odczytać wymiarów obrazu', imageLimitError($blind), 'without its sides it is refused');
    });

    test('GD is asked only when it has room', function () {
        assertTrue(gdFits(100, 100));
        if (iniBytes(ini_get('memory_limit')) > 0)
            assertFalse(gdFits(100000, 100000), 'forty gigabytes do not fit');
    });
