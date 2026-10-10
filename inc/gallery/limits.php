<?php
    // How big a picture may be, measured from its header before anything decodes
    // it. Part of the gallery, loaded by inc/gallery.php.

    // ---- How big a picture may be -------------------------------------------------
    // A small file can hold a huge picture (a PNG of 30000×30000 pixels takes a
    // few kilobytes, and GD would want 3.6 GB for it), so the size of the file
    // says too little. The width and height are read from the header first,
    // without decoding anything, and a picture over the limits below is refused:
    // at the upload, before any file is kept, and before every thumbnail, turn
    // and conversion, also of the files from before. An animation counts all its
    // frames. inc/config.php may set other values under the same names
    // (IMAGE_MAX_PIXELS, IMAGE_MAX_SIDE, IMAGE_MAX_ANIMATION_PIXELS,
    // TOOL_MEMORY_MB).
    const IMAGE_MAX_PIXELS_DEFAULT = 16000000;

    const IMAGE_MAX_SIDE_DEFAULT = 8192;

    const IMAGE_MAX_ANIMATION_PIXELS_DEFAULT = 400000000;

    // what a tool (cwebp, gif2webp, ImageMagick, ffmpeg) may take, in MB of
    // address space; 0 for no limit. Not on Windows.
    const TOOL_MEMORY_MB_DEFAULT = 2048;

    function imageLimit($name)
    {
        authConfigured();

        return defined($name) ? (int)constant($name) : constant($name . '_DEFAULT');
    }

    // [width, height, frames] of a picture from its header, or null when they
    // cannot be read; a HEIC, HEIF or AVIF by its "ispe" boxes when getimagesize
    // does not know it. The frames are counted only with $frames (that reads
    // the whole file), otherwise 1.
    function imageSize($path, $frames = true)
    {
        $info = @getimagesize($path);
        if ($info && $info[0] > 0 && $info[1] > 0) {
            $count = 1;
            if ($frames && $info[2] === IMAGETYPE_GIF)
                $count = gifFrames($path);
            else if ($frames && $info[2] === IMAGETYPE_WEBP && isAnimatedWebp($path))
                $count = max(1, substr_count((string)@file_get_contents($path), 'ANMF'));

            return [(int)$info[0], (int)$info[1], $count];
        }
        if (!isHeifImage($path))
            return null;

        // the biggest of the image's extents (a photo of a phone is a grid of
        // tiles, each with its own, and the whole with the biggest)
        $head = (string)@file_get_contents($path, false, null, 0, 524288);
        $best = null;
        for ($pos = strpos($head, 'ispe'); $pos !== false; $pos = strpos($head, 'ispe', $pos + 4)) {
            if (strlen($head) < $pos + 16)
                break;
            $size = unpack('Nw/Nh', substr($head, $pos + 8, 8));
            if ($size['w'] > 0 && $size['h'] > 0 && ($best === null || $size['w'] * $size['h'] > $best[0] * $best[1]))
                $best = [$size['w'], $size['h']];
        }

        return $best === null ? null : [$best[0], $best[1], 1];
    }

    // frames of a GIF, by their graphic control blocks; never fewer than one
    function gifFrames($path)
    {
        return max(1, substr_count((string)@file_get_contents($path), "\x21\xF9\x04"));
    }

    // why a picture is too big to be decoded here, or null when it is not;
    // without $frames only its first frame counts (GD decodes no more of it)
    function imageLimitError($path, $frames = true)
    {
        $size = imageSize($path, $frames);
        if ($size === null)
            return 'nie da się odczytać wymiarów obrazu';

        [$width, $height, $frames] = $size;
        $side = imageLimit('IMAGE_MAX_SIDE');
        $pixels = imageLimit('IMAGE_MAX_PIXELS');
        if (max($width, $height) > $side)
            return 'obraz ma ' . $width . '×' . $height . ' px, a dłuższy bok może mieć najwyżej ' . $side . ' px';
        if ($width * $height > $pixels)
            return 'obraz ma ' . round($width * $height / 1e6, 1) . ' Mpx (' . $width . '×' . $height . '), a limit to ' . round($pixels / 1e6, 1) . ' Mpx';
        if ($frames > 1 && $width * $height * $frames > imageLimit('IMAGE_MAX_ANIMATION_PIXELS'))
            return 'animacja ma ' . $frames . ' klatek po ' . $width . '×' . $height . ' px, za dużo naraz';

        return null;
    }

    // Whether GD has room for $copies pictures of this size in what PHP may
    // still take (4 bytes a pixel and a little more); with no limit in PHP the
    // limits of imageLimitError() still hold. An estimate, not a promise.
    function gdFits($width, $height, $copies = 1)
    {
        $limit = iniBytes(ini_get('memory_limit'));
        if ($limit <= 0)
            return true;

        return memory_get_usage() + $width * $height * 4.4 * $copies + 8388608 < $limit;
    }
