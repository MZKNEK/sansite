<?php
    // The metadata of pictures: orientation, colour profiles, and taking out
    // what an upload should not keep (place, time, camera). Part of the gallery,
    // loaded by inc/gallery.php.

    // ---- Metadata --------------------------------------------------------------------
    // Photos carry EXIF and similar data: where and when they were taken, with
    // what. An upload loses it without the picture being encoded again; a JPEG
    // keeps only its orientation, so a photo from a phone is not turned over.

    // the JPEG segments before the picture data as [marker, whole segment], and
    // the rest of the file from the picture data on; null when it is no JPEG
    function jpegSegments($data)
    {
        if (substr($data, 0, 2) !== "\xFF\xD8")
            return null;

        $segments = [];
        $pos = 2;
        $length = strlen($data);
        while ($pos + 4 <= $length && $data[$pos] === "\xFF") {
            $marker = ord($data[$pos + 1]);
            // start of scan: the picture data runs to the end
            if ($marker === 0xDA)
                return [$segments, substr($data, $pos)];
            $size = unpack('n', substr($data, $pos + 2, 2))[1];
            $segments[] = [$marker, substr($data, $pos, $size + 2)];
            $pos += $size + 2;
        }

        return null;
    }

    // the orientation tag (1-8) of an EXIF block ("Exif\0\0" and a TIFF header), 1 when none
    function exifOrientation($exif)
    {
        $tiff = substr($exif, 6);
        if (strlen($tiff) < 14)
            return 1;
        $format = substr($tiff, 0, 2) === 'II' ? 'v' : 'n';
        $long = $format === 'v' ? 'V' : 'N';
        $ifd = unpack($long, substr($tiff, 4, 4))[1];
        if ($ifd + 2 > strlen($tiff))
            return 1;

        $count = unpack($format, substr($tiff, $ifd, 2))[1];
        for ($i = 0; $i < $count; $i++) {
            $entry = substr($tiff, $ifd + 2 + 12 * $i, 12);
            if (strlen($entry) < 12)
                break;
            if (unpack($format, substr($entry, 0, 2))[1] === 0x0112) {
                $value = unpack($format, substr($entry, 8, 2))[1];
                return $value >= 1 && $value <= 8 ? $value : 1;
            }
        }

        return 1;
    }

    // EXIF orientation of a JPEG file, 1 when it has none
    function jpegOrientation($path)
    {
        $parts = jpegSegments((string)@file_get_contents($path, false, null, 0, 262144) . "\xFF\xDA");
        foreach ($parts[0] ?? [] as $segment)
            if ($segment[0] === 0xE1 && substr($segment[1], 4, 6) === "Exif\0\0")
                return exifOrientation(substr($segment[1], 4));

        return 1;
    }

    // The ICC colour profile of a picture, or null. GD reads a JPEG or PNG
    // without looking at its profile, so the pixels keep the colours they were
    // made in; the profile has to go into the WebP too, or a photo in Display P3
    // or Adobe RGB comes out duller and shifted.
    function imageIccProfile($source)
    {
        $info = @getimagesize($source);
        if (!$info)
            return null;
        if ($info[2] === IMAGETYPE_JPEG)
            return jpegIccProfile($source);
        if ($info[2] === IMAGETYPE_PNG)
            return pngIccProfile($source);

        return null;
    }

    // the ICC profile of a JPEG: its APP2 segments ("ICC_PROFILE\0" and a part
    // number) joined in order
    function jpegIccProfile($path)
    {
        $parts = jpegSegments((string)@file_get_contents($path, false, null, 0, 1048576) . "\xFF\xDA");
        $chunks = [];
        foreach ($parts[0] ?? [] as $segment) {
            if ($segment[0] !== 0xE2)
                continue;
            $payload = substr($segment[1], 4);
            if (substr($payload, 0, 12) !== "ICC_PROFILE\0" || strlen($payload) < 14)
                continue;
            $chunks[ord($payload[12])] = substr($payload, 14);
        }
        if (!$chunks)
            return null;
        ksort($chunks);

        return implode('', $chunks);
    }

    // the ICC profile of a PNG: its iCCP chunk, zlib-compressed
    function pngIccProfile($path)
    {
        $data = (string)@file_get_contents($path, false, null, 0, 1048576);
        if (substr($data, 0, 8) !== "\x89PNG\r\n\x1A\n")
            return null;
        $pos = 8;
        $length = strlen($data);
        while ($pos + 12 <= $length) {
            $size = unpack('N', substr($data, $pos, 4))[1];
            $type = substr($data, $pos + 4, 4);
            if ($type === 'iCCP') {
                $chunk = substr($data, $pos + 8, $size);
                $nul = strpos($chunk, "\0");
                if ($nul === false || $nul + 2 > strlen($chunk))
                    return null;
                $profile = @gzuncompress(substr($chunk, $nul + 2));

                return $profile === false ? null : $profile;
            }
            if ($type === 'IDAT' || $type === 'IEND')
                break;
            $pos += $size + 12;
        }

        return null;
    }

    // turns and mirrors a GD image the way an EXIF orientation says
    function applyOrientation($img, $orientation)
    {
        // GD turns counter-clockwise; 5 and 7 are turned and then mirrored
        $turn = [3 => 180, 5 => -90, 6 => -90, 7 => 90, 8 => 90][$orientation] ?? 0;
        if ($turn) {
            $turned = imagerotate($img, $turn, 0);
            if ($turned) {
                unset($img);
                $img = $turned;
            }
        }
        if (in_array($orientation, [2, 5, 7], true))
            imageflip($img, IMG_FLIP_HORIZONTAL);
        else if ($orientation === 4)
            imageflip($img, IMG_FLIP_VERTICAL);

        return $img;
    }

    // EXIF (APP1, also XMP there), IPTC (APP13) and comments go; only the
    // orientation comes back, as a small EXIF block of its own
    function stripJpeg($data)
    {
        $parts = jpegSegments($data);
        if (!$parts)
            return null;

        $orientation = 1;
        $kept = [];
        foreach ($parts[0] as $segment) {
            if ($segment[0] === 0xE1 && substr($segment[1], 4, 6) === "Exif\0\0")
                $orientation = exifOrientation(substr($segment[1], 4));
            if (!in_array($segment[0], [0xE1, 0xED, 0xFE], true))
                $kept[] = $segment;
        }
        if (count($kept) === count($parts[0]))
            return null;

        if ($orientation !== 1) {
            $exif = "Exif\0\0" . "MM\0\x2A\0\0\0\x08" . "\0\x01" . "\x01\x12\0\x03\0\0\0\x01" . pack('n', $orientation) . "\0\0" . "\0\0\0\0";
            // after the JFIF header when there is one
            $at = $kept && $kept[0][0] === 0xE0 ? 1 : 0;
            array_splice($kept, $at, 0, [[0xE1, "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif]]);
        }

        return "\xFF\xD8" . implode('', array_column($kept, 1)) . $parts[1];
    }

    // PNG text chunks (often XMP), EXIF and the time go
    function stripPng($data)
    {
        if (substr($data, 0, 8) !== "\x89PNG\r\n\x1A\n")
            return null;

        $out = substr($data, 0, 8);
        $pos = 8;
        $dropped = false;
        while ($pos + 12 <= strlen($data)) {
            $size = unpack('N', substr($data, $pos, 4))[1];
            $type = substr($data, $pos + 4, 4);
            $chunk = substr($data, $pos, $size + 12);
            if (in_array($type, ['eXIf', 'tEXt', 'zTXt', 'iTXt', 'tIME'], true))
                $dropped = true;
            else
                $out .= $chunk;
            $pos += $size + 12;
            if ($type === 'IEND')
                break;
        }

        return $dropped ? $out : null;
    }

    // the EXIF and XMP chunks of an extended WebP go, and its header stops naming them
    function stripWebp($data)
    {
        if (substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WEBP')
            return null;

        $chunks = '';
        $pos = 12;
        $dropped = false;
        while ($pos + 8 <= strlen($data)) {
            $type = substr($data, $pos, 4);
            $size = unpack('V', substr($data, $pos + 4, 4))[1];
            $chunk = substr($data, $pos, 8 + $size + ($size & 1));
            if ($type === 'EXIF' || $type === 'XMP ') {
                $dropped = true;
            } else {
                // VP8X flags: 0x08 EXIF, 0x04 XMP
                if ($type === 'VP8X')
                    $chunk[8] = chr(ord($chunk[8]) & ~0x0C);
                $chunks .= $chunk;
            }
            $pos += 8 + $size + ($size & 1);
        }
        if (!$dropped)
            return null;

        return 'RIFF' . pack('V', strlen($chunks) + 4) . 'WEBP' . $chunks;
    }

    // Empties the Exif and XMP items of an AVIF (an ISO base media file): the
    // bytes they hold are zeroed, so where and with what a photo was taken is
    // gone; the picture's own item is left alone. null when nothing was dropped.
    function stripAvif($data)
    {
        $len = strlen($data);
        if ($len < 24 || substr($data, 4, 4) !== 'ftyp')
            return null;

        $meta = null;
        $mdat = null;
        $pos = 0;
        while ($pos + 8 <= $len) {
            $size = unpack('N', substr($data, $pos, 4))[1];
            $type = substr($data, $pos + 4, 4);
            $header = 8;
            if ($size === 1) {
                if ($pos + 16 > $len)
                    break;
                $size = unpack('J', substr($data, $pos + 8, 8))[1];
                $header = 16;
            } else if ($size === 0) {
                $size = $len - $pos;
            }
            if ($size < $header || $pos + $size > $len)
                break;
            if ($type === 'meta')
                $meta = [$pos + $header, $size - $header];
            else if ($type === 'mdat')
                $mdat = [$pos + $header, $size - $header];
            $pos += $size;
        }
        if ($meta === null || $mdat === null)
            return null;

        $items = avifItems($data, $meta[0], $meta[1]);
        if (!$items)
            return null;

        // the picture itself is never touched
        $image = [];
        foreach ($items as $item)
            if ($item['type'] === 'av01')
                foreach ($item['extents'] as $extent)
                    $image[] = [$item['base'] + $extent[0], $extent[1]];

        $changed = false;
        foreach ($items as $item) {
            if ($item['type'] !== 'Exif' && $item['type'] !== 'mime')
                continue;
            foreach ($item['extents'] as $extent) {
                $at = $item['base'] + $extent[0];
                $size = $extent[1];
                if ($size <= 0 || $at < $mdat[0] || $at + $size > $mdat[0] + $mdat[1])
                    continue;
                foreach ($image as $range)
                    if ($at < $range[0] + $range[1] && $range[0] < $at + $size)
                        continue 2;
                $data = substr_replace($data, str_repeat("\0", $size), $at, $size);
                $changed = true;
            }
        }

        return $changed ? $data : null;
    }

    // [['type' => ..., 'base' => ..., 'extents' => [[offset, size], ...]], ...]
    // of the items an AVIF's meta box names and places, or null
    function avifItems($data, $start, $size)
    {
        $types = null;
        $locations = null;
        $pos = $start + 4;   // meta is a full box: version and flags first
        $end = $start + $size;
        while ($pos + 8 <= $end) {
            $boxSize = unpack('N', substr($data, $pos, 4))[1];
            $boxType = substr($data, $pos + 4, 4);
            if ($boxSize < 8 || $pos + $boxSize > $end)
                return null;
            if ($boxType === 'iinf')
                $types = avifItemTypes($data, $pos, $boxSize);
            else if ($boxType === 'iloc')
                $locations = avifItemLocations($data, $pos, $boxSize);
            $pos += $boxSize;
        }
        if ($types === null || $locations === null)
            return null;

        $items = [];
        foreach ($types as $id => $type)
            if (isset($locations[$id]))
                $items[] = ['type' => $type, 'base' => $locations[$id]['base'], 'extents' => $locations[$id]['extents']];

        return $items;
    }

    // item ID => item type, from an iinf box, or null
    function avifItemTypes($data, $pos, $size)
    {
        $end = $pos + $size;
        $version = ord($data[$pos + 8]);
        $pos += 12;
        if ($version === 0) {
            if ($pos + 2 > $end)
                return null;
            $pos += 2;   // entry count
        } else {
            if ($pos + 4 > $end)
                return null;
            $pos += 4;
        }

        $types = [];
        while ($pos + 8 <= $end) {
            $boxSize = unpack('N', substr($data, $pos, 4))[1];
            if ($boxSize < 8 || $pos + $boxSize > $end)
                return null;
            if (substr($data, $pos + 4, 4) === 'infe') {
                $infe = avifInfe($data, $pos, $boxSize);
                if ($infe !== null)
                    $types[$infe[0]] = $infe[1];
            }
            $pos += $boxSize;
        }

        return $types;
    }

    // [item ID, item type] of an infe box, or null
    function avifInfe($data, $pos, $size)
    {
        $end = $pos + $size;
        if ($pos + 12 > $end)
            return null;
        $version = ord($data[$pos + 8]);
        $pos += 12;
        if ($version < 2)
            return null;   // the item type is not written out there
        $idSize = $version === 2 ? 2 : 4;
        if ($pos + $idSize + 6 > $end)
            return null;
        $id = $idSize === 2 ? unpack('n', substr($data, $pos, 2))[1] : unpack('N', substr($data, $pos, 4))[1];

        return [$id, substr($data, $pos + $idSize + 2, 4)];
    }

    // item ID => ['base' => ..., 'extents' => [[offset, size], ...]], from iloc, or null
    function avifItemLocations($data, $pos, $size)
    {
        $end = $pos + $size;
        if ($pos + 16 > $end)
            return null;
        $version = ord($data[$pos + 8]);
        $sizes = unpack('n', substr($data, $pos + 12, 2))[1];
        $pos += 14;
        $offsetSize = ($sizes >> 12) & 0xF;
        $lengthSize = ($sizes >> 8) & 0xF;
        $baseSize = ($sizes >> 4) & 0xF;
        $indexSize = $version === 1 || $version === 2 ? ($sizes & 0xF) : 0;
        if ($offsetSize > 8 || $lengthSize > 8 || $baseSize > 8 || $indexSize > 8)
            return null;

        if ($version < 2) {
            if ($pos + 2 > $end)
                return null;
            $count = unpack('n', substr($data, $pos, 2))[1];
            $pos += 2;
        } else {
            if ($pos + 4 > $end)
                return null;
            $count = unpack('N', substr($data, $pos, 4))[1];
            $pos += 4;
        }

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $idSize = $version < 2 ? 2 : 4;
            if ($pos + $idSize + 2 > $end)
                return null;
            $id = $idSize === 2 ? unpack('n', substr($data, $pos, 2))[1] : unpack('N', substr($data, $pos, 4))[1];
            $pos += $idSize;
            if ($version === 1 || $version === 2) {
                if ($pos + 2 > $end)
                    return null;
                $method = unpack('n', substr($data, $pos, 2))[1] & 0xF;
                $pos += 2;
                if ($method !== 0)
                    return null;   // the data is not in mdat
            }
            if ($pos + 2 > $end)
                return null;
            $pos += 2;   // data reference index
            $base = avifReadUint($data, $pos, $baseSize);
            $pos += $baseSize;
            if ($pos + 2 > $end)
                return null;
            $extentCount = unpack('n', substr($data, $pos, 2))[1];
            $pos += 2;
            $extents = [];
            for ($e = 0; $e < $extentCount; $e++) {
                $off = avifReadUint($data, $pos, $offsetSize);
                $pos += $offsetSize;
                $extLen = avifReadUint($data, $pos, $lengthSize);
                $pos += $lengthSize + $indexSize;
                $extents[] = [$off, $extLen];
            }
            $items[$id] = ['base' => $base, 'extents' => $extents];
        }

        return $items;
    }

    function avifReadUint($data, $pos, $size)
    {
        $value = 0;
        for ($i = 0; $i < $size; $i++)
            $value = ($value << 8) | ord($data[$pos + $i]);

        return $value;
    }

    // removes the metadata of a saved JPEG, PNG, WebP or AVIF; true when the file changed
    function stripMetadata($path)
    {
        $info = @getimagesize($path);
        $data = (string)@file_get_contents($path);
        // an AVIF is an ISO base media file, not one getimagesize always knows
        if (($info[2] ?? 0) === IMAGETYPE_AVIF || extensionOf($path) === 'avif')
            $clean = stripAvif($data);
        else switch ($info[2] ?? 0) {
            case IMAGETYPE_JPEG: $clean = stripJpeg($data); break;
            case IMAGETYPE_PNG: $clean = stripPng($data); break;
            case IMAGETYPE_WEBP: $clean = stripWebp($data); break;
            default: $clean = null;
        }
        if ($clean === null || $clean === '')
            return false;

        $tmp = dirname($path) . '/.strip-' . getmypid();
        if (@file_put_contents($tmp, $clean) === false || !@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        clearstatcache();

        return true;
    }
