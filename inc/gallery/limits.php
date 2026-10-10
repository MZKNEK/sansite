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
            else if ($frames && $info[2] === IMAGETYPE_WEBP)
                $count = webpFrames($path, $info[0], $info[1]);
            if ($count < 1)
                return null;

            return [(int)$info[0], (int)$info[1], $count];
        }
        if (!isHeifImage($path))
            return null;

        // the biggest of the image's extents (a photo of a phone is a grid of
        // tiles, each with its own, and the whole with the biggest)
        $head = (string)@file_get_contents($path, false, null, 0, 524288);
        // Sequences need a reliable sample-table parser; do not pretend ispe
        // alone proves their animation budget.
        $brands = substr($head, 8, max(0, min(512, (int)(unpack('N', substr($head, 0, 4))[1] ?? 0)) - 8));
        if (strpos($brands, 'avis') !== false || strpos($brands, 'msf1') !== false)
            return null;
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

    // Count image descriptors, never optional GCE signatures in compressed data.
    // 0 denotes a truncated or inconsistent container. Stop at the first frame
    // exceeding the pixel budget: decoding will be refused in any case.
    function gifFrames($path)
    {
        $stream = @fopen($path, 'rb');
        if (!$stream) return 0;
        try {
            $head = fread($stream, 13);
            if (strlen($head) !== 13 || !in_array(substr($head, 0, 6), ['GIF87a', 'GIF89a'], true)) return 0;
            $screen = unpack('vw/vh', substr($head, 6, 4));
            if (!$screen['w'] || !$screen['h']) return 0;
            if ((ord($head[10]) & 128) && strlen(fread($stream, 3 * (1 << ((ord($head[10]) & 7) + 1)))) !== 3 * (1 << ((ord($head[10]) & 7) + 1))) return 0;
            $count = 0;
            while (($byte = fread($stream, 1)) !== '') {
                if ($byte === "\x3b") return $count;
                if ($byte === "\x21") {
                    if (strlen(fread($stream, 1)) !== 1 || !gifSkipBlocks($stream)) return 0;
                } elseif ($byte === "\x2c") {
                    $descriptor = fread($stream, 9);
                    if (strlen($descriptor) !== 9) return 0;
                    $frame = unpack('vx/vy/vw/vh', substr($descriptor, 0, 8));
                    if (!$frame['w'] || !$frame['h'] || $frame['x'] + $frame['w'] > $screen['w'] || $frame['y'] + $frame['h'] > $screen['h']) return 0;
                    $packed = ord($descriptor[8]);
                    $colors = 3 * (1 << (($packed & 7) + 1));
                    if (($packed & 128) && strlen(fread($stream, $colors)) !== $colors) return 0;
                    $codeSize = fread($stream, 1);
                    if ($codeSize === '' || ord($codeSize) < 2 || ord($codeSize) > 8 || !gifSkipBlocks($stream)) return 0;
                    $count++;
                    if ($screen['w'] * $screen['h'] * $count > imageLimit('IMAGE_MAX_ANIMATION_PIXELS')) return $count;
                } else return 0;
            }
            return 0;
        } finally { fclose($stream); }
    }

    function gifSkipBlocks($stream)
    {
        while (($byte = fread($stream, 1)) !== '') {
            $size = ord($byte);
            if ($size === 0) return true;
            if (strlen(fread($stream, $size)) !== $size) return false;
        }
        return false;
    }

    function webpFrames($path, $width, $height)
    {
        $stream = @fopen($path, 'rb');
        if (!$stream) return 0;
        try {
            $header = fread($stream, 12);
            $length = filesize($path);
            if (strlen($header) !== 12 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8) !== 'WEBP'
                || unpack('V', substr($header, 4, 4))[1] + 8 !== $length) return 0;
            $count = 0;
            $animated = false;
            $still = false;
            while (ftell($stream) < $length) {
                $chunk = fread($stream, 8);
                if (strlen($chunk) !== 8) return 0;
                $size = unpack('V', substr($chunk, 4))[1];
                $next = ftell($stream) + $size + ($size & 1);
                if ($next > $length) return 0;
                $type = substr($chunk, 0, 4);
                if ($type === 'VP8X') {
                    if ($size !== 10) return 0;
                    $animated = (ord(fread($stream, 1)) & 2) !== 0;
                } elseif ($type === 'ANMF') {
                    if (!$animated || $size < 24) return 0;
                    $frame = fread($stream, 16);
                    $u24 = function ($offset) use ($frame) { return unpack('V', substr($frame, $offset, 3) . "\0")[1]; };
                    if ((ord($frame[15]) & 252) || 2 * $u24(0) + $u24(6) + 1 > $width || 2 * $u24(3) + $u24(9) + 1 > $height) return 0;
                    $end = $next - ($size & 1);
                    $image = false;
                    while (ftell($stream) < $end) {
                        $part = fread($stream, 8);
                        if (strlen($part) !== 8 || !in_array(substr($part, 0, 4), ['ALPH', 'VP8 ', 'VP8L'], true)) return 0;
                        $lengthPart = unpack('V', substr($part, 4))[1];
                        $after = ftell($stream) + $lengthPart + ($lengthPart & 1);
                        if ($after > $end || fseek($stream, $after) !== 0) return 0;
                        if (substr($part, 0, 4) !== 'ALPH') $image = true;
                    }
                    if (!$image) return 0;
                    $count++;
                    if ($width * $height * $count > imageLimit('IMAGE_MAX_ANIMATION_PIXELS')) return $count;
                } elseif ($type === 'VP8 ' || $type === 'VP8L') $still = true;
                if (fseek($stream, $next) !== 0) return 0;
            }
            return $animated ? $count : ($still ? 1 : 0);
        } finally { fclose($stream); }
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
