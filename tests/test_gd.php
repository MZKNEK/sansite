<?php
    // inc/gallery.php: the GD helpers (thumbnails, rotation, reading and writing
    // WebP). Skipped when the gd extension is not loaded, the way the server can
    // be without php-gd; CI enables gd.

    test('the GD image helpers', function () {
        if (!hasGd()) {
            assertFalse(hasGd(), 'no gd here');
            return;
        }

        $dir = tempDir();
        $img = imagecreatetruecolor(100, 50);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 100, 50));
        imagepng($img, $dir . '/a.png');
        imagedestroy($img);

        $loaded = loadImage($dir . '/a.png');
        assertTrue($loaded !== false);
        assertSame(100, imagesx($loaded));
        assertSame(50, imagesy($loaded));
        imagedestroy($loaded);

        assertTrue(makeThumb($dir . '/a.png', $dir . '/thumb'), 'a thumbnail is made');
        assertTrue(is_file($dir . '/thumb'));

        // turning 90 degrees swaps the sides
        assertTrue(rotateImage($dir . '/a.png', 90));
        $rotated = getimagesize($dir . '/a.png');
        assertSame(50, $rotated[0]);
        assertSame(100, $rotated[1]);

        if (canConvertToWebp()) {
            assertTrue(convertToWebp($dir . '/a.png', $dir . '/a.webp'));
            assertTrue(filesize($dir . '/a.webp') > 0);
        }
    });
