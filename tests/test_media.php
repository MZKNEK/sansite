<?php
    // inc/gallery.php: what a file is by its name and its bytes, and metadata
    // stripping for JPEG, PNG and WebP.

    // a 12 byte ISO base media header: size, "ftyp", brand
    function fx_header($brand)
    {
        return pack('N', 20) . 'ftyp' . $brand;
    }

    // one JPEG segment: marker, length, payload
    function fx_jpegSegment($marker, $payload)
    {
        return "\xFF" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
    }

    // a little-endian EXIF block with only an orientation tag
    function fx_exif($orientation)
    {
        $entry = "\x12\x01" . "\x03\x00" . "\x01\x00\x00\x00" . pack('v', $orientation) . "\x00\x00";

        return "Exif\0\0" . "II" . "\x2A\x00" . "\x08\x00\x00\x00" . "\x01\x00" . $entry . "\x00\x00\x00\x00";
    }

    // a JPEG: SOI, APP0 (JFIF), APP1 (the EXIF block), then the scan data
    function fx_jpeg($orientation = 1)
    {
        return "\xFF\xD8"
            . fx_jpegSegment(0xE0, "JFIF\0\x01\x02")
            . fx_jpegSegment(0xE1, fx_exif($orientation))
            . "\xFF\xDA\x00\x02picture";
    }

    // a PNG chunk: length, type, data, CRC
    function fx_pngChunk($type, $data)
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    function fx_png($withText = true)
    {
        $png = "\x89PNG\r\n\x1A\n" . fx_pngChunk('IHDR', str_repeat("\0", 13));
        if ($withText)
            $png .= fx_pngChunk('tEXt', "Author\0me");

        return $png . fx_pngChunk('IDAT', 'pppp') . fx_pngChunk('IEND', '');
    }

    // a WebP chunk with its padding byte
    function fx_webpChunk($type, $data)
    {
        return $type . pack('V', strlen($data)) . $data . (strlen($data) & 1 ? "\0" : '');
    }

    function fx_webp()
    {
        $vp8x = 'VP8X' . pack('V', 10) . "\x0C" . str_repeat("\0", 9);
        $exif = 'EXIF' . pack('V', 4) . 'abcd';
        $body = 'WEBP' . $vp8x . $exif;

        return 'RIFF' . pack('V', strlen($body) + 4) . $body;
    }

    test('file kind by the extension', function () {
        assertTrue(isImage('a.PNG'));
        assertTrue(isImage('a.jpeg'));
        assertTrue(isImage('a.avif'));
        assertTrue(isVideo('a.webm'));
        assertTrue(isVideo('a.mp4'));
        assertFalse(isImage('a.webm'));
        assertFalse(isVideo('a.png'));
        assertSame('png', extensionOf('a.PNG'));
        assertSame('', extensionOf('noext'));
    });

    test('container sniffing', function () {
        $dir = tempDir();
        $write = function ($name, $bytes) use ($dir) {
            file_put_contents($dir . '/' . $name, $bytes);
            return $dir . '/' . $name;
        };

        assertTrue(isMp4($write('a.mp4', fx_header('isom'))));
        assertFalse(isMp4($write('b.bin', "\x00\x00\x00\x08mdatxxxx")));
        assertTrue(isWebm($write('a.webm', "\x1A\x45\xDF\xA3" . 'webmxxxxx')));
        assertFalse(isWebm($write('c.bin', 'nope')));
        foreach (['heic', 'heix', 'mif1', 'avif'] as $brand)
            assertTrue(isHeifImage($write('h-' . $brand . '.heic', fx_header($brand))), $brand);
        assertFalse(isHeifImage($write('x.heic', fx_header('isom'))));
        assertFalse(isHeifImage($write('short.heic', 'too short')));
    });

    test('the content has to match the extension', function () {
        $dir = tempDir();
        file_put_contents($dir . '/v.mp4', fx_header('isom'));
        file_put_contents($dir . '/v.webm', "\x1A\x45\xDF\xA3" . 'webm');
        file_put_contents($dir . '/f.heic', fx_header('heic'));

        assertTrue(isVideoContent('v.mp4', $dir . '/v.mp4'));
        assertFalse(isVideoContent('v.webm', $dir . '/v.mp4'));
        assertTrue(isVideoContent('v.webm', $dir . '/v.webm'));
        assertTrue(isImageContent('f.heic', $dir . '/f.heic'));
        assertFalse(isImageContent('f.jpg', $dir . '/f.heic'));
    });

    test('jpegSegments stops at the scan data', function () {
        $parts = jpegSegments(fx_jpeg());
        assertSame(2, count($parts[0]));
        assertSame(0xE0, $parts[0][0][0]);
        assertSame(0xE1, $parts[0][1][0]);
        assertMatches('/^\xFF\xDA/', $parts[1]);
        assertNull(jpegSegments('not a jpeg'));
    });

    test('exifOrientation reads the tag', function () {
        assertSame(6, exifOrientation(fx_exif(6)));
        assertSame(1, exifOrientation(fx_exif(1)));
        assertSame(1, exifOrientation('garbage'), 'a broken block falls back to 1');
    });

    test('jpegOrientation reads a file', function () {
        $dir = tempDir();
        file_put_contents($dir . '/a.jpg', fx_jpeg(6));
        file_put_contents($dir . '/b.jpg', fx_jpeg(1));
        assertSame(6, jpegOrientation($dir . '/a.jpg'));
        assertSame(1, jpegOrientation($dir . '/b.jpg'));
    });

    test('stripJpeg drops the metadata and keeps the orientation', function () {
        assertNull(stripJpeg('not a jpeg'));

        $clean = stripJpeg(fx_jpeg(6));
        assertTrue(is_string($clean));
        assertContains("\xFF\xE1", $clean, 'the orientation EXIF comes back');
        $dir = tempDir();
        file_put_contents($dir . '/a.jpg', $clean);
        assertSame(6, jpegOrientation($dir . '/a.jpg'));

        $plain = stripJpeg(fx_jpeg(1));
        assertTrue(is_string($plain));
        assertFalse(strpos($plain, "Exif\0\0") !== false, 'orientation 1 needs no EXIF');
    });

    test('jpegIccProfile joins the APP2 parts', function () {
        $dir = tempDir();
        $icc = function ($part, $data) { return fx_jpegSegment(0xE2, "ICC_PROFILE\0" . chr($part) . chr(2) . $data); };
        // the parts out of order, the way a joiner has to sort them
        $jpeg = "\xFF\xD8" . $icc(2, 'second') . $icc(1, 'first') . "\xFF\xDA\x00\x02x";
        file_put_contents($dir . '/c.jpg', $jpeg);
        assertSame('firstsecond', jpegIccProfile($dir . '/c.jpg'));
    });

    test('stripPng drops the text chunks', function () {
        assertNull(stripPng('not a png'));
        $clean = stripPng(fx_png(true));
        assertTrue(is_string($clean));
        assertFalse(strpos($clean, 'tEXt') !== false, 'the text chunk is gone');
        assertContains('IHDR', $clean);
        assertContains('IDAT', $clean);
        assertContains('IEND', $clean);
        assertNull(stripPng(fx_png(false)), 'nothing to drop means no change');
    });

    test('stripWebp drops EXIF and clears the header flags', function () {
        assertNull(stripWebp('not a webp'));
        $clean = stripWebp(fx_webp());
        assertTrue(is_string($clean));
        assertFalse(strpos($clean, 'EXIF') !== false, 'the EXIF chunk is gone');
        // RIFF(4) size(4) WEBP(4) VP8X(4) size(4) -> the flags byte is at 20
        assertSame(0, ord($clean[20]) & 0x0C, 'the EXIF flag is cleared');
    });

    test('stripAvif leaves a plain file alone', function () {
        assertNull(stripAvif('too short'));
        assertNull(stripAvif(fx_header('avif')), 'no meta or mdat box');
    });

    // one ISO box and a full box (with version and flags)
    function fx_box($type, $payload)
    {
        return pack('N', 8 + strlen($payload)) . $type . $payload;
    }

    function fx_full($type, $payload)
    {
        return fx_box($type, "\0\0\0\0" . $payload);
    }

    // A whole AVIF: ftyp, mdat with the picture and the metadata, then meta with
    // iinf (the item types) and iloc (where each item sits in mdat). With
    // $withMetadata false it holds only the picture item.
    function fx_avif($withMetadata = true)
    {
        $image = 'IMG';
        $exif = 'EXIFDATA';
        $xmp = 'XMP';

        $ftyp = fx_box('ftyp', 'avif' . pack('N', 0) . 'avif' . 'mif1');
        $mdat = fx_box('mdat', $image . ($withMetadata ? $exif . $xmp : ''));
        $at = strlen($ftyp) + 8;   // where the mdat payload starts

        $infe = function ($id, $type) {
            return fx_box('infe', chr(2) . "\0\0\0" . pack('n', $id) . pack('n', 0) . $type . "\0");
        };
        $items = [[1, 'av01', $at, strlen($image)]];
        if ($withMetadata) {
            $items[] = [2, 'Exif', $at + strlen($image), strlen($exif)];
            $items[] = [3, 'mime', $at + strlen($image) + strlen($exif), strlen($xmp)];
        }

        $iinf = fx_full('iinf', pack('n', count($items)) . implode('', array_map(function ($item) use ($infe) {
            return $infe($item[0], $item[1]);
        }, $items)));

        // offsetSize 4, lengthSize 4, baseOffsetSize 0, indexSize 0
        $iloc = fx_full('iloc', pack('n', 0x4400) . pack('n', count($items)) . implode('', array_map(function ($item) {
            return pack('n', $item[0]) . pack('n', 0) . pack('n', 1) . pack('N', $item[2]) . pack('N', $item[3]);
        }, $items)));

        return $ftyp . $mdat . fx_full('meta', $iinf . $iloc);
    }

    test('stripAvif empties the Exif and XMP items only', function () {
        $clean = stripAvif(fx_avif());
        assertTrue(is_string($clean));
        assertSame('IMG', substr($clean, 32, 3), 'the picture is left alone');
        assertSame(str_repeat("\0", 8), substr($clean, 35, 8), 'the Exif bytes are zeroed');
        assertSame(str_repeat("\0", 3), substr($clean, 43, 3), 'the XMP bytes are zeroed');

        assertNull(stripAvif(fx_avif(false)), 'no metadata means no change');
    });

    test('an animated WebP is told by its VP8X flag', function () {
        $dir = tempDir();
        $make = function ($flags) {
            return 'RIFF' . pack('V', 20) . 'WEBP' . 'VP8X' . pack('V', 10) . chr($flags) . str_repeat("\0", 9);
        };
        file_put_contents($dir . '/anim.webp', $make(0x12));
        file_put_contents($dir . '/still.webp', $make(0x10));
        file_put_contents($dir . '/plain.webp', 'nope');
        assertTrue(isAnimatedWebp($dir . '/anim.webp'));
        assertFalse(isAnimatedWebp($dir . '/still.webp'));
        assertFalse(isAnimatedWebp($dir . '/plain.webp'));
    });

    test('pngIccProfile reads the iCCP chunk', function () {
        $dir = tempDir();
        $profile = 'ICC-PROFILE-BYTES';
        file_put_contents($dir . '/c.png', "\x89PNG\r\n\x1A\n" . fx_pngChunk('iCCP', "name\0" . chr(0) . gzcompress($profile)) . fx_pngChunk('IEND', ''));
        assertSame($profile, pngIccProfile($dir . '/c.png'));
        file_put_contents($dir . '/d.png', fx_png(false));
        assertNull(pngIccProfile($dir . '/d.png'), 'no profile');
    });
