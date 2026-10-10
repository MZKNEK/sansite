<?php
    // Thumbnails of pictures and films: their URLs, making them, their cache.
    // Part of the gallery, loaded by inc/gallery.php.

    // a frame of a video can be a thumbnail
    function canThumbVideo()
    {
        return hasGd() && findTool('ffmpeg') !== null;
    }

    // ?thumb= of a file: its time, so a changed picture gets a new URL, and
    // THUMB_URL_VERSION, so no browser goes on using a thumbnail it was once
    // allowed to keep for a year (before sendThumb() said no-store)
    function thumbQuery($rel, $full)
    {
        return '?thumb=' . rawurlencode(publicRel($rel)) . '&v=' . filemtime($full) . '&c=' . THUMB_URL_VERSION;
    }

    // thumbnail URL of a picture or a video, or null when it should get a placeholder
    function thumbUrl($rel, $full)
    {
        if (isVideo($rel))
            return canThumbVideo() ? thumbQuery($rel, $full) : null;
        // a picture over the limits is never decoded, nor sent to the browser
        // in its place: it gets the placeholder
        if (imageLimitError($full, false) !== null)
            return null;
        if (hasGd())
            return thumbQuery($rel, $full);

        return filesize($full) <= THUMBLESS_MAX_BYTES ? fileUrl($rel) : null;
    }

    // a frame from about the first second of a video as a GD image, or false
    function videoFrame($source)
    {
        $ffmpeg = findTool('ffmpeg');
        if (!$ffmpeg)
            return false;

        $frame = sys_get_temp_dir() . '/sanakan-frame-' . getmypid() . '.png';
        // a video shorter than a second has no frame there, then the first one
        foreach (['1', '0'] as $at) {
            @unlink($frame);
            if (runTool($ffmpeg, ['-v', 'error', '-ss', $at, '-i', $source, '-frames:v', '1', '-y', $frame], 20) && @filesize($frame) > 0)
                break;
        }
        $img = @filesize($frame) > 0 ? loadImage($frame) : false;
        @unlink($frame);

        return $img;
    }

    function makeThumb($source, $target)
    {
        $img = isVideo($source) ? videoFrame($source) : loadImage($source);
        if (!$img)
            return false;

        $width = imagesx($img);
        $height = imagesy($img);
        $scale = min(1, THUMB_SIZE / max($width, $height));
        $thumbWidth = max(1, (int)round($width * $scale));
        $thumbHeight = max(1, (int)round($height * $scale));

        // keeps transparency, e.g. of the coins and the card overlays
        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

        @mkdir(dirname($target), 0775, true);
        $tmp = $target . '.' . getmypid();
        $ok = function_exists('imagewebp') ? @imagewebp($thumb, $tmp, 82) : @imagepng($thumb, $tmp, 6);
        if ($ok)
            $ok = @rename($tmp, $target);

        unset($img);
        unset($thumb);

        return $ok;
    }

    // The thumbnail cache, in inc/data/thumbs so it outlives a restart and the
    // cleaning of /tmp (a gallery that lost it makes all its thumbnails at once).
    // The old cache in /tmp moves there the first time; /tmp only when inc/data
    // cannot be written.
    function thumbsDir()
    {
        $dir = dataDir() . '/thumbs';
        $old = sys_get_temp_dir() . '/sanakan-thumbs';
        if (!is_dir($dir) && dataWritable()) {
            @mkdir(dataDir(), 0750, true);
            if (!(is_dir($old) && @rename($old, $dir)))
                @mkdir($dir, 0775, true);
        }

        return is_dir($dir) && is_writable($dir) ? $dir : $old;
    }

    // Removes the thumbnails nobody has looked at for THUMB_KEEP_DAYS (sendThumb()
    // renews their time once a day); those of deleted, renamed or changed pictures
    // are never asked for again. Run by inc/check-bot.php once a day.
    function pruneThumbs()
    {
        $removed = 0;
        $before = time() - THUMB_KEEP_DAYS * 86400;
        foreach (glob(thumbsDir() . '/*') ?: [] as $file)
            if (is_file($file) && filemtime($file) < $before && @unlink($file))
                $removed++;

        return $removed;
    }

    // Thumbnail of one picture, made on first use and then served from the cache
    // on the server. Whether the visitor may see it is checked on every request
    // (i/index.php), so no browser or proxy keeps the answer: a logout, another
    // account or a link turned off must not leave the thumbnail reachable.
    function sendThumb($base, $rel)
    {
        header('Cache-Control: private, no-store');
        $file = resolvePath($base, $rel, false);
        $video = $file && isVideo($file[1]);
        if (!$file || (!isImage($file[1]) && !($video && canThumbVideo()))) {
            http_response_code(404);
            return;
        }

        $webp = function_exists('imagewebp');
        $key = md5($file[0] . '|' . filemtime($file[0]) . '|' . filesize($file[0]) . '|' . THUMB_SIZE);
        $cache = thumbsDir() . '/' . $key . ($webp ? '.webp' : '.png');

        if (!is_file($cache) && hasGd()) {
            // One thumbnail at a time: GD holds the whole picture in memory and the
            // server has one core, so a folder full of new pictures must not make
            // them all at once. A request that waited may find its thumbnail made.
            // It waits only THUMB_WAIT seconds: the requests of a whole folder
            // waiting in line would take every worker of the small PHP-FPM pool,
            // so the rest is asked again by the page (js/explorer.js).
            $lock = @fopen(thumbsDir() . '/.lock', 'c');
            for ($waited = 0; $lock !== false && !flock($lock, LOCK_EX | LOCK_NB); $waited++) {
                if ($waited >= THUMB_WAIT * 10) {
                    fclose($lock);
                    http_response_code(503);
                    header('Retry-After: 2');
                    return;
                }
                usleep(100000);
            }
            clearstatcache(true, $cache);
            if (!is_file($cache))
                makeThumb($file[0], $cache);
            if ($lock !== false)
                fclose($lock);
        }

        if (!is_file($cache)) {
            // no thumbnail possible: the picture itself has to do, a video and a
            // picture over the limits get none
            if ($video || imageLimitError($file[0], false) !== null)
                http_response_code(404);
            else
                header('Location: ' . fileUrl($file[1]));
            return;
        }

        // still in use, so pruneThumbs() keeps it
        if (filemtime($cache) < time() - 86400)
            @touch($cache);

        header('Content-Type: ' . ($webp ? 'image/webp' : 'image/png'));
        readfile($cache);
    }
