<?php
    // inc/gallery.php and inc/diag.php: the capability checks (a boolean each)
    // and the closed gallery without a session.

    test('the tool and capability checks are booleans', function () {
        assertNull(findTool('sanakan-no-such-tool-xyz'), 'a tool that is not there');
        assertTrue(is_bool(canConvertToWebp()));
        assertTrue(is_bool(canConvertGifToWebp()));
        assertTrue(is_bool(canConvertVideo()));
        assertTrue(is_bool(canConvertHeif()));
        assertTrue(is_bool(hasGd()));
        assertTrue(is_bool(canZip()));
        assertTrue(is_bool(diagAvailable()));
        assertTrue(uploadLimit() >= 0);
    });

    test('the gallery is closed without a session', function () {
        assertFalse(galleryCanView());
        assertFalse(galleryIsAdmin());
        assertFalse(galleryCanSeePrivate());
        assertFalse(galleryIsUploader());
        assertNull(ownFolder(tempDir()), 'no account, no own folder');
    });
