<?php
    // Reading and writing pictures and films: GD, the command-line tools (cwebp,
    // gif2webp, ImageMagick, ffmpeg) and what they can do here, the change to WebP
    // and WebM, turning. Part of the gallery, loaded by inc/gallery.php.

    function canConvertToWebp()
    {
        return hasGd() && function_exists('imagewebp');
    }

    // Path of a command-line tool (cwebp, gif2webp and webpmux from apt-get install webp,
    // ffmpeg), or null. PHP-FPM usually runs without PATH, so the usual folders
    // are tried too.
    function findTool($name)
    {
        static $found = [];
        if (array_key_exists($name, $found))
            return $found[$name];

        $found[$name] = null;
        if (!function_exists('proc_open'))
            return null;

        $dirs = array_merge(explode(PATH_SEPARATOR, (string)getenv('PATH')), ['/usr/bin', '/usr/local/bin']);
        foreach ($dirs as $dir) {
            foreach ([$name, $name . '.exe'] as $file) {
                $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $file;
                if ($dir !== '' && is_file($path) && is_executable($path))
                    return $found[$name] = $path;
            }
        }

        return null;
    }

    function canConvertGifToWebp()
    {
        return findTool('gif2webp') !== null;
    }

    // a film can be written as WebM when ffmpeg is there
    function canConvertVideo()
    {
        return findTool('ffmpeg') !== null;
    }

    // Runs a tool, at most $timeout seconds (a slow conversion must not hang the
    // upload); true when it finished without an error.
    function runTool($tool, $args, $timeout)
    {
        $command = escapeshellarg($tool);
        foreach ($args as $arg)
            $command .= ' ' . escapeshellarg($arg);
        // a picture made to eat memory must not take the server with it: the
        // tool gets TOOL_MEMORY_MB of address space and fails beyond it
        $memory = imageLimit('TOOL_MEMORY_MB');
        if ($memory > 0 && DIRECTORY_SEPARATOR === '/')
            $command = 'ulimit -v ' . ($memory * 1024) . ' 2>/dev/null; exec ' . $command;

        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process))
            return false;

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $deadline = microtime(true) + $timeout;
        do {
            $status = proc_get_status($process);
            if (!$status['running'])
                break;
            // the output is read away, so the tool never waits on a full pipe
            fread($pipes[1], 65536);
            fread($pipes[2], 65536);
            usleep(100000);
        } while (microtime(true) < $deadline);

        if ($status['running'])
            proc_terminate($process);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return !$status['running'] && $status['exitcode'] === 0;
    }

    // an animated GIF as an animated WebP
    function gifToWebp($source, $target, $quality = null)
    {
        if (imageLimitError($source) !== null)
            return false;
        $quality = $quality === null ? webpQuality('gif') : $quality;
        $tmp = $target . '.' . getmypid();
        // lossy and a light effort: a 22 MB GIF takes seconds instead of most of a minute, and ends up smaller
        $ok = runTool(findTool('gif2webp'), ['-lossy', '-q', (string)$quality, '-m', '2', '-mt', $source, '-o', $tmp], TOOL_TIMEOUT)
            && @rename($tmp, $target);
        if (!$ok)
            @unlink($tmp);

        return $ok;
    }

    // an MP4 as WebM, or false; VP9 for size, VP8 where the build has no VP9,
    // and a film that does not finish in $timeout keeps its original
    function mp4ToWebm($source, $target, $timeout = TOOL_TIMEOUT)
    {
        $ffmpeg = findTool('ffmpeg');
        if (!$ffmpeg)
            return false;

        $tmp = $target . '.' . getmypid();
        $encoders = [
            ['libvpx-vp9', ['-crf', '33', '-b:v', '0', '-row-mt', '1']],
            ['libvpx', ['-crf', '12', '-b:v', '1M']]
        ];
        foreach ($encoders as $encoder) {
            @unlink($tmp);
            $args = array_merge(['-v', 'error', '-y', '-i', $source, '-c:v', $encoder[0]], $encoder[1],
                ['-deadline', 'realtime', '-cpu-used', '4', '-c:a', 'libopus', '-b:a', '96k', '-f', 'webm', $tmp]);
            if (runTool($ffmpeg, $args, $timeout) && @rename($tmp, $target))
                return true;
        }
        @unlink($tmp);

        return false;
    }

    // a HEIC, HEIF or AVIF as WebP: ImageMagick (through libheif, keeping the
    // orientation), or ffmpeg; false when neither can
    function heifToWebp($source, $target, $quality = null)
    {
        if (imageLimitError($source) !== null)
            return false;
        $quality = $quality === null ? webpQuality('avif') : $quality;
        $tmp = dirname($target) . '/.heif-' . getmypid() . '.webp';
        // ImageMagick's own limits too: what it may hold in memory and on the
        // disk, the picture's sides, its time and threads
        $side = (string)imageLimit('IMAGE_MAX_SIDE');
        $limits = ['-limit', 'memory', '256MiB', '-limit', 'map', '512MiB', '-limit', 'disk', '1GiB',
            '-limit', 'width', $side, '-limit', 'height', $side, '-limit', 'time', (string)TOOL_TIMEOUT, '-limit', 'thread', '2'];
        foreach (['magick', 'convert'] as $name) {
            $tool = findTool($name);
            if (!$tool)
                continue;
            @unlink($tmp);
            if (runTool($tool, array_merge($limits, [$source, '-auto-orient', '-quality', (string)$quality, $tmp]), TOOL_TIMEOUT) && @filesize($tmp) > 0) {
                $ok = @rename($tmp, $target);
                @unlink($tmp);
                return $ok;
            }
        }

        $ffmpeg = findTool('ffmpeg');
        if ($ffmpeg) {
            @unlink($tmp);
            if (runTool($ffmpeg, ['-v', 'error', '-y', '-i', $source, '-frames:v', '1', $tmp], TOOL_TIMEOUT) && @filesize($tmp) > 0) {
                $ok = @rename($tmp, $target);
                @unlink($tmp);
                return $ok;
            }
        }
        @unlink($tmp);

        return false;
    }

    // whether the server can write a HEIC or HEIF (and so accept one at all)
    function canConvertHeif()
    {
        return findTool('magick') !== null || findTool('convert') !== null || findTool('ffmpeg') !== null;
    }

    function hasGd()
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    // An animated WebP says so in its extended header (VP8X, animation flag).
    // GD must not get one: it stops the whole script instead of failing.
    function isAnimatedWebp($path)
    {
        $head = (string)@file_get_contents($path, false, null, 0, 21);

        return strlen($head) === 21 && substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP'
            && substr($head, 12, 4) === 'VP8X' && (ord($head[20]) & 0x02);
    }

    // a picture as a true colour GD image (the first frame of an animated GIF or
    // WebP), or false; also when it is over the limits (imageLimitError()) or GD
    // would not have room for $copies of it
    function loadImage($source, $copies = 1)
    {
        $info = @getimagesize($source);
        if (!$info || imageLimitError($source, false) !== null || !gdFits($info[0], $info[1], $copies))
            return false;

        switch ($info[2]) {
            case IMAGETYPE_PNG: $img = @imagecreatefrompng($source); break;
            case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($source); break;
            case IMAGETYPE_GIF: $img = @imagecreatefromgif($source); break;
            case IMAGETYPE_AVIF:
                $img = function_exists('imagecreatefromavif') ? @imagecreatefromavif($source) : false;
                break;
            case IMAGETYPE_WEBP:
                $img = false;
                if (!function_exists('imagecreatefromwebp'))
                    break;
                if (!isAnimatedWebp($source)) {
                    $img = @imagecreatefromwebp($source);
                    break;
                }
                // GD cannot read an animated WebP; webpmux takes out its first frame
                if ($mux = findTool('webpmux')) {
                    $frame = sys_get_temp_dir() . '/sanakan-frame-' . getmypid() . '.webp';
                    if (runTool($mux, ['-get', 'frame', '1', $source, '-o', $frame], 20))
                        $img = @imagecreatefromwebp($frame);
                    @unlink($frame);
                }
                break;
            default: $img = false;
        }
        if ($img && !imageistruecolor($img))
            imagepalettetotruecolor($img);
        if ($img && $info[2] === IMAGETYPE_JPEG)
            $img = applyOrientation($img, jpegOrientation($source));

        return $img;
    }

    // The quality set in the panel for a kind of picture (jpg, png, gif, avif),
    // or the default above; the panel keeps it in inc/data/settings.json.
    function webpQuality($kind)
    {
        $defaults = ['jpg' => WEBP_QUALITY_JPG, 'png' => WEBP_QUALITY_PNG, 'gif' => GIF_WEBP_QUALITY, 'avif' => AVIF_WEBP_QUALITY];
        $value = readData('settings')['quality' . ucfirst($kind)] ?? null;
        if (!is_numeric($value))
            return $defaults[$kind] ?? WEBP_QUALITY_PNG;

        return max(WEBP_QUALITY_MIN, min(WEBP_QUALITY_MAX, (int)$value));
    }

    // the quality for a source file, by what it is
    function webpQualityOf($source)
    {
        $type = @getimagesize($source)[2] ?? 0;
        if ($type === IMAGETYPE_GIF)
            return webpQuality('gif');
        if ($type === IMAGETYPE_AVIF)
            return webpQuality('avif');
        if ($type === IMAGETYPE_PNG)
            return webpQuality('png');

        return webpQuality('jpg');
    }

    // Writes a GD image (with imagesavealpha) as WebP. cwebp gets it as a PNG;
    // without cwebp, or when it fails, GD writes it. $icc is the colour profile
    // of the original, put into the WebP so its colours stay the same.
    function writeWebp($img, $file, $quality = null, $icc = null)
    {
        $quality = $quality === null ? WEBP_QUALITY_PNG : $quality;
        if ($cwebp = findTool('cwebp')) {
            $png = sys_get_temp_dir() . '/sanakan-webp-' . getmypid() . '.png';
            $args = ['-quiet', '-q', (string)$quality, '-m', '4', '-mt'];
            $iccFile = null;
            if ($icc !== null && $icc !== '') {
                $iccFile = sys_get_temp_dir() . '/sanakan-icc-' . getmypid() . '.icc';
                if (@file_put_contents($iccFile, $icc) !== false)
                    $args = array_merge($args, ['-icc_profile', $iccFile]);
                else
                    $iccFile = null;
            }
            // -m 4, not 6: a 20 MP picture takes 3 s instead of 20 s, for 3% more bytes
            $ok = @imagepng($img, $png, 1)
                && runTool($cwebp, array_merge($args, [$png, '-o', $file]), TOOL_TIMEOUT);
            @unlink($png);
            if ($iccFile !== null)
                @unlink($iccFile);
            clearstatcache();
            if ($ok && @filesize($file) > 0)
                return true;
            @unlink($file);
        }

        return @imagewebp($img, $file, $quality);
    }

    // saves a picture as WebP, keeping transparency and the colour profile
    function convertToWebp($source, $target, $quality = null)
    {
        if (imageLimitError($source) !== null)
            return false;
        $info = @getimagesize($source);
        if ($quality === null)
            $quality = webpQualityOf($source);
        $cwebp = findTool('cwebp');
        // A JPEG or PNG goes through cwebp itself when it can: cwebp reads the
        // picture as it is and copies its ICC profile, so the colours do not
        // change. A JPEG that has to be turned (EXIF) takes the GD way below,
        // where the profile is passed along by hand.
        if ($cwebp && $info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
                && !($info[2] === IMAGETYPE_JPEG && jpegOrientation($source) > 1)) {
            $tmp = $target . '.' . getmypid();
            if (runTool($cwebp, ['-quiet', '-q', (string)$quality, '-m', '4', '-metadata', 'icc', $source, '-o', $tmp], TOOL_TIMEOUT) && @filesize($tmp) > 0)
                return @rename($tmp, $target);
            @unlink($tmp);
        }

        $img = loadImage($source);
        if (!$img)
            return false;

        imagealphablending($img, false);
        imagesavealpha($img, true);
        $tmp = $target . '.' . getmypid();
        $ok = writeWebp($img, $tmp, $quality, imageIccProfile($source)) && @rename($tmp, $target);
        unset($img);
        if (!$ok)
            @unlink($tmp);

        return $ok;
    }

    // ---- Rotating -------------------------------------------------------------------

    // Turns a picture by 90 degrees ($angle 90 to the left, -90 to the right, as
    // GD counts) and saves it in its own format. Not for GIFs and animated WebP,
    // which GD would flatten to one frame.
    function rotateImage($path, $angle, $base = null, $rel = null)
    {
        $ext = extensionOf($path);
        if (!hasGd() || !in_array($ext, ROTATE_TYPES, true))
            return false;
        if ($ext === 'webp' && (!function_exists('imagewebp') || isAnimatedWebp($path)))
            return false;

        // the picture and the turned one are in memory at once
        $img = loadImage($path, 2);
        if (!$img)
            return false;
        $rotated = imagerotate($img, $angle, imagecolorallocatealpha($img, 0, 0, 0, 127));
        unset($img);
        if (!$rotated)
            return false;
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        // written next to it under a hidden name, then put in its place
        $tmp = dirname($path) . '/.rotate-' . getmypid() . '.' . $ext;
        if ($ext === 'png')
            $ok = @imagepng($rotated, $tmp, 6);
        else if ($ext === 'webp')
            $ok = writeWebp($rotated, $tmp);
        else
            $ok = @imagejpeg($rotated, $tmp, 92);
        unset($rotated);

        $mtime = filemtime($path);
        $owner = $rel === null ? null : folderOwner($rel);
        if ($ok && $owner !== null && accountQuotaError($base, $owner, 0, filesize($tmp) - filesize($path), filesize($tmp)) !== null) {
            @unlink($tmp);
            return false;
        }
        if (!$ok || !@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        // a newer time also when turned twice in a second, so no old thumbnail is shown
        @chmod($path, 0644);
        @touch($path, max(time(), $mtime + 1));
        clearstatcache();

        return true;
    }

    // whether the server can change this picture's kind to WebP
    function canConvertImageToWebp($ext)
    {
        if ($ext === 'avif')
            return function_exists('imagecreatefromavif') || canConvertHeif();
        if ($ext === 'gif')
            return canConvertGifToWebp();

        return canConvertToWebp();
    }
