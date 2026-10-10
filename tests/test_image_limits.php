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
        $gif = 'GIF89a' . pack('vv', 1000, 1000) . "\x80\x00\x00\x00\x00\x00\xff\xff\xff"
            . str_repeat("\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00", 401) . "\x3b";
        $path = headerFile('anim.gif', $gif);
        assertSame(401, imageSize($path)[2]);
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

    test('GIF descriptor parser ignores signatures inside subblocks and refuses truncation', function () {
        $head = 'GIF89a' . pack('vv', 10, 10) . "\x80\0\0\0\0\0\xff\xff\xff";
        $frame = "\x2c\0\0\0\0\x01\0\x01\0\0\x02\x02\x44\x01\0";
        $comment = "\x21\xfe\x04\x21\xf9\x04\x2c\0";
        assertSame(2, gifFrames(headerFile('mixed.gif', $head . $comment . $frame . "\x21\xf9\x04\0\0\0\0\0" . $frame . "\x3b")));
        assertSame(0, gifFrames(headerFile('cut.gif', $head . substr($frame, 0, -1))));
        $outside = "\x2c" . pack('vvvvC', 9, 9, 2, 2, 0) . "\x02\x02\x44\x01\0\x3b";
        assertSame(0, gifFrames(headerFile('outside.gif', $head . $outside)));
    });

    test('WebP frame count uses RIFF chunks rather than ANMF text inside metadata', function () {
        $chunk = function ($type, $data) { return $type . pack('V', strlen($data)) . $data . (strlen($data) & 1 ? "\0" : ''); };
        $riff = function ($data) { return 'RIFF' . pack('V', strlen($data) + 4) . 'WEBP' . $data; };
        $still = $chunk('VP8L', 'ANMF') . $chunk('EXIF', 'ANMFANMF');
        assertSame(1, webpFrames(headerFile('meta.webp', $riff($still)), 10, 10));
        $extended = $chunk('VP8X', "\x02" . str_repeat("\0", 9));
        $frame = $chunk('ANMF', str_repeat("\0", 16) . $chunk('VP8L', 'x'));
        assertSame(2, webpFrames(headerFile('frames.webp', $riff($extended . $frame . $frame)), 10, 10));
        assertSame(0, webpFrames(headerFile('cut.webp', substr($riff($extended . $frame), 0, -1)), 10, 10));
    });
