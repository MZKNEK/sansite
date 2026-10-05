<?php
    // Picture gallery in i/ (used by i/index.php): safe paths inside the folder,
    // thumbnails, who may view and manage it, and adding, moving and deleting
    // files. The Discord login is in inc/auth.php, shared with the admin panel.
    //
    // Thumbnails are made with GD and cached. Without GD small pictures are
    // shown as they are, and big ones (some GIFs have tens of MB) get a
    // placeholder and load only when opened.
    const THUMB_SIZE = 360;
    const THUMBLESS_MAX_BYTES = 1500000;
    const IMAGE_TYPES = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    const VIDEO_TYPES = ['webm'];
    // what "change to WebP" applies to; GIFs go through gif2webp to stay animated
    const WEBP_SOURCE_TYPES = ['png', 'jpg', 'jpeg', 'gif'];
    const WEBP_QUALITY = 90;
    const GIF_WEBP_QUALITY = 75;
    const TOOL_TIMEOUT = 50;
    const TRASH_DAYS = 30;
    const THUMB_KEEP_DAYS = 30;
    const SEARCH_LIMIT = 300;
    const ZIP_MAX_BYTES = 1073741824;
    const ROTATE_TYPES = ['png', 'jpg', 'jpeg', 'webp'];
    const MAX_NAME_LENGTH = 150;

    require_once __DIR__ . '/auth.php';

    $base = str_replace('\\', '/', __DIR__);

    // [full path, path relative to this folder] for a folder or file inside it, or null;
    // realpath() also resolves "..", so nothing outside this folder can be reached
    function resolvePath($base, $rel, $wantDir)
    {
        $rel = trim(str_replace('\\', '/', (string)$rel), '/');
        $full = realpath($base . ($rel === '' ? '' : '/' . $rel));
        if ($full === false)
            return null;

        $full = str_replace('\\', '/', $full);
        if ($full !== $base && strpos($full, $base . '/') !== 0)
            return null;
        if ($wantDir ? !is_dir($full) : !is_file($full))
            return null;

        $relPath = ltrim(substr($full, strlen($base)), '/');
        foreach (explode('/', $relPath) as $part)
            if ($part !== '' && $part[0] === '.')
                return null;
        if ($relPath === 'index.php')
            return null;

        return [$full, $relPath];
    }

    function extensionOf($name)
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    function isImage($name)
    {
        return in_array(extensionOf($name), IMAGE_TYPES, true);
    }

    function isVideo($name)
    {
        return in_array(extensionOf($name), VIDEO_TYPES, true);
    }

    // WebM is Matroska: the file starts with the EBML magic number and names its doc type
    function isWebm($path)
    {
        $head = (string)@file_get_contents($path, false, null, 0, 64);

        return strncmp($head, "\x1A\x45\xDF\xA3", 4) === 0 && strpos($head, 'webm') !== false;
    }

    function canConvertToWebp()
    {
        return hasGd() && function_exists('imagewebp');
    }

    // Path of a command-line tool (gif2webp and webpmux from apt-get install webp,
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

    // Runs a tool, at most $timeout seconds (a slow conversion must not hang the
    // upload); true when it finished without an error.
    function runTool($tool, $args, $timeout)
    {
        $command = escapeshellarg($tool);
        foreach ($args as $arg)
            $command .= ' ' . escapeshellarg($arg);

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
    function gifToWebp($source, $target)
    {
        $tmp = $target . '.' . getmypid();
        // lossy and a light effort: a 22 MB GIF takes seconds instead of most of a minute, and ends up smaller
        $ok = runTool(findTool('gif2webp'), ['-lossy', '-q', (string)GIF_WEBP_QUALITY, '-m', '2', '-mt', $source, '-o', $tmp], TOOL_TIMEOUT)
            && @rename($tmp, $target);
        if (!$ok)
            @unlink($tmp);

        return $ok;
    }

    function hasGd()
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    // URL of a file relative to this folder, every part encoded on its own
    function fileUrl($rel)
    {
        return implode('/', array_map('rawurlencode', explode('/', $rel)));
    }

    function folderUrl($rel)
    {
        return $rel === '' ? './' : '?p=' . rawurlencode($rel);
    }

    // a frame of a video can be a thumbnail
    function canThumbVideo()
    {
        return hasGd() && findTool('ffmpeg') !== null;
    }

    // thumbnail URL of a picture or a video, or null when it should get a placeholder
    function thumbUrl($rel, $full)
    {
        if (isVideo($rel))
            return canThumbVideo() ? '?thumb=' . rawurlencode($rel) . '&v=' . filemtime($full) : null;
        if (hasGd())
            return '?thumb=' . rawurlencode($rel) . '&v=' . filemtime($full);

        return filesize($full) <= THUMBLESS_MAX_BYTES ? fileUrl($rel) : null;
    }

    // lower case for the search, also without the mbstring extension
    function lower($text)
    {
        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    function e($text)
    {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }

    function formatSize($bytes)
    {
        if ($bytes < 1024)
            return $bytes . ' B';
        if ($bytes < 1024 * 1024)
            return round($bytes / 1024) . ' KB';

        if ($bytes < 1024 * 1024 * 1024)
            return str_replace('.', ',', round($bytes / 1024 / 1024, 1)) . ' MB';

        return str_replace('.', ',', round($bytes / 1024 / 1024 / 1024, 1)) . ' GB';
    }

    // Polish plural: 1 plik, 2-4 pliki, 5+ plików (but 12-14 plików)
    function plural($n, $one, $few, $many)
    {
        if ($n == 1)
            return $one;

        $last = $n % 10;
        $lastTwo = $n % 100;
        return $last >= 2 && $last <= 4 && ($lastTwo < 12 || $lastTwo > 14) ? $few : $many;
    }

    // visible entries of a folder: no hidden files and no index.php in the top folder
    function listNames($dirPath, $dirRel)
    {
        $names = [];
        foreach (scandir($dirPath) ?: [] as $name) {
            if ($name[0] === '.' || ($dirRel === '' && $name === 'index.php'))
                continue;
            $names[] = $name;
        }
        natcasesort($names);

        return array_values($names);
    }

    // An animated WebP says so in its extended header (VP8X, animation flag).
    // GD must not get one: it stops the whole script instead of failing.
    function isAnimatedWebp($path)
    {
        $head = (string)@file_get_contents($path, false, null, 0, 21);

        return strlen($head) === 21 && substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP'
            && substr($head, 12, 4) === 'VP8X' && (ord($head[20]) & 0x02);
    }

    // a picture as a true colour GD image (the first frame of an animated GIF or WebP), or false
    function loadImage($source)
    {
        $info = @getimagesize($source);
        if (!$info)
            return false;

        switch ($info[2]) {
            case IMAGETYPE_PNG: $img = @imagecreatefrompng($source); break;
            case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($source); break;
            case IMAGETYPE_GIF: $img = @imagecreatefromgif($source); break;
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

    // saves a picture as WebP, keeping transparency
    function convertToWebp($source, $target)
    {
        $img = loadImage($source);
        if (!$img)
            return false;

        imagealphablending($img, false);
        imagesavealpha($img, true);
        $tmp = $target . '.' . getmypid();
        $ok = @imagewebp($img, $tmp, WEBP_QUALITY) && @rename($tmp, $target);
        imagedestroy($img);
        if (!$ok)
            @unlink($tmp);

        return $ok;
    }

    // What a folder tile shows: name, number of items and up to three pictures from inside
    function folderEntry($full, $rel)
    {
        $inside = listNames($full, $rel);
        $previews = [];
        foreach ($inside as $innerName) {
            if (count($previews) == 3)
                break;
            $innerFull = $full . '/' . $innerName;
            if (is_file($innerFull) && (isImage($innerName) || isVideo($innerName)) && ($url = thumbUrl($rel . '/' . $innerName, $innerFull)))
                $previews[] = $url;
        }

        return [
            'name' => basename($rel),
            'rel' => $rel,
            'count' => count($inside),
            'mtime' => filemtime($full),
            'previews' => $previews
        ];
    }

    // What a file tile shows
    function fileEntry($full, $rel)
    {
        $name = basename($rel);
        $image = isImage($name);
        $dims = $image ? @getimagesize($full) : false;
        // a photo turned by its EXIF orientation is shown the other way round
        if ($dims && $dims[2] === IMAGETYPE_JPEG && jpegOrientation($full) >= 5)
            $dims = [$dims[1], $dims[0]];

        return [
            'name' => $name,
            'rel' => $rel,
            'ext' => extensionOf($name),
            'size' => filesize($full),
            'mtime' => filemtime($full),
            'kind' => $image ? 'image' : (isVideo($name) ? 'video' : 'file'),
            'dims' => $dims ? $dims[0] . '×' . $dims[1] : '',
            'thumb' => $image || isVideo($name) ? thumbUrl($rel, $full) : null
        ];
    }

    // Folders and files anywhere in the gallery with every word of the query in
    // their name, as [folders, files, whether there were more than SEARCH_LIMIT]
    function searchGallery($base, $query)
    {
        $words = preg_split('/\s+/', lower(trim($query)), -1, PREG_SPLIT_NO_EMPTY);
        $found = ['folders' => [], 'files' => [], 'more' => false];
        if ($words)
            searchFolder($base, '', $words, $found, 0);

        return [$found['folders'], $found['files'], $found['more']];
    }

    function searchFolder($dirPath, $dirRel, $words, &$found, $depth)
    {
        foreach (listNames($dirPath, $dirRel) as $name) {
            $full = $dirPath . '/' . $name;
            $rel = ltrim($dirRel . '/' . $name, '/');
            $folder = is_dir($full) && !is_link($full);

            $matches = true;
            foreach ($words as $word)
                if (strpos(lower($name), $word) === false)
                    $matches = false;

            if ($matches) {
                if (count($found['folders']) + count($found['files']) >= SEARCH_LIMIT) {
                    $found['more'] = true;
                    return;
                }
                if ($folder)
                    $found['folders'][] = folderEntry($full, $rel);
                else if (is_file($full))
                    $found['files'][] = fileEntry($full, $rel);
            }

            if ($folder && $depth < 10)
                searchFolder($full, $rel, $words, $found, $depth + 1);
            if ($found['more'])
                return;
        }
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

        imagedestroy($img);
        imagedestroy($thumb);

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

    // Thumbnail of one picture, made on first use and then served from the cache.
    // The URL has the file time in it, so browsers may keep it for good.
    function sendThumb($base, $rel)
    {
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
            $lock = @fopen(thumbsDir() . '/.lock', 'c');
            if ($lock !== false)
                flock($lock, LOCK_EX);
            clearstatcache(true, $cache);
            if (!is_file($cache))
                makeThumb($file[0], $cache);
            if ($lock !== false)
                fclose($lock);
        }

        if (!is_file($cache)) {
            // no thumbnail possible: the picture itself has to do, a video gets none
            if ($video)
                http_response_code(404);
            else
                header('Location: ' . fileUrl($file[1]));
            return;
        }

        // still in use, so pruneThumbs() keeps it
        if (filemtime($cache) < time() - 86400)
            @touch($cache);

        header('Content-Type: ' . ($webp ? 'image/webp' : 'image/png'));
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($cache);
    }

    // a folder or a file inside the gallery, see resolvePath()
    function resolveAny($base, $rel)
    {
        return resolvePath($base, $rel, true) ?: resolvePath($base, $rel, false);
    }

    // every folder of the gallery, for the move dialog
    function allFolders($base, $rel = '', $depth = 0)
    {
        $list = [$rel];
        $full = $rel === '' ? $base : $base . '/' . $rel;
        if ($depth >= 10)
            return $list;

        foreach (listNames($full, $rel) as $name)
            if (is_dir($full . '/' . $name) && !is_link($full . '/' . $name))
                $list = array_merge($list, allFolders($base, ltrim($rel . '/' . $name, '/'), $depth + 1));

        return $list;
    }

    // ---- Access -------------------------------------------------------------
    // checked against the config and the panel's lists on every request,
    // so taking an account out works at once

    function galleryCanView()
    {
        $user = siteUser();
        return $user !== null && canViewGalleryId($user['id']);
    }

    function galleryIsAdmin()
    {
        $user = siteUser();
        return $user !== null && isGalleryAdminId($user['id']);
    }

    // ---- Changes ------------------------------------------------------------

    // "i" or "i/folder/file", as paths are written in messages
    function galleryPath($rel)
    {
        return $rel === '' ? 'i' : 'i/' . $rel;
    }

    // a change went fine: it goes to the history, then the page gets the answer
    function done($action, $history, $message = null)
    {
        addHistory($action, $history);
        reply(true, $message ?? $history);
    }

    function reply($ok, $message, $status = 200, $extra = [])
    {
        http_response_code($ok ? 200 : $status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $ok, 'message' => $message] + $extra);
        exit;
    }

    // what is wrong with a new file or folder name, or null
    function nameError($name, $isFile)
    {
        if ($name === '' || $name === '.' || $name === '..')
            return 'Nazwa nie może być pusta.';
        if ($name[0] === '.')
            return 'Nazwa nie może zaczynać się od kropki.';
        if (preg_match('/[\/\\\\:*?"<>|\x00-\x1F]/', $name))
            return 'Nazwa nie może zawierać znaków / \\ : * ? " < > |';
        if (strlen($name) > MAX_NAME_LENGTH)
            return 'Nazwa jest za długa.';
        if ($isFile && !isImage($name) && !isVideo($name))
            return 'Można dodawać tylko obrazki i filmy: ' . implode(', ', array_merge(IMAGE_TYPES, VIDEO_TYPES)) . '.';

        return null;
    }

    // the name, or "name (2).png" and so on when it is taken
    function freeName($dirPath, $name)
    {
        if (!file_exists($dirPath . '/' . $name))
            return $name;

        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $stem = $ext === '' ? $name : substr($name, 0, -strlen($ext) - 1);
        for ($i = 2; ; $i++) {
            $candidate = $stem . ' (' . $i . ')' . ($ext === '' ? '' : '.' . $ext);
            if (!file_exists($dirPath . '/' . $candidate))
                return $candidate;
        }
    }

    function removeTree($path)
    {
        if (is_link($path) || is_file($path))
            return @unlink($path);

        foreach (scandir($path) ?: [] as $name)
            if ($name !== '.' && $name !== '..' && !removeTree($path . '/' . $name))
                return false;

        return @rmdir($path);
    }

    // bytes in a file or a folder with everything inside
    function treeSize($path)
    {
        if (is_link($path) || is_file($path))
            return (int)@filesize($path);

        $bytes = 0;
        foreach (scandir($path) ?: [] as $name)
            if ($name !== '.' && $name !== '..')
                $bytes += treeSize($path . '/' . $name);

        return $bytes;
    }

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

    // turns and mirrors a GD image the way an EXIF orientation says
    function applyOrientation($img, $orientation)
    {
        // GD turns counter-clockwise; 5 and 7 are turned and then mirrored
        $turn = [3 => 180, 5 => -90, 6 => -90, 7 => 90, 8 => 90][$orientation] ?? 0;
        if ($turn) {
            $turned = imagerotate($img, $turn, 0);
            if ($turned) {
                imagedestroy($img);
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

    // removes the metadata of a saved JPEG, PNG or WebP; true when the file changed
    function stripMetadata($path)
    {
        $info = @getimagesize($path);
        $data = (string)@file_get_contents($path);
        switch ($info[2] ?? 0) {
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

    // ---- Rotating -------------------------------------------------------------------

    // Turns a picture by 90 degrees ($angle 90 to the left, -90 to the right, as
    // GD counts) and saves it in its own format. Not for GIFs and animated WebP,
    // which GD would flatten to one frame.
    function rotateImage($path, $angle)
    {
        $ext = extensionOf($path);
        if (!hasGd() || !in_array($ext, ROTATE_TYPES, true))
            return false;
        if ($ext === 'webp' && (!function_exists('imagewebp') || isAnimatedWebp($path)))
            return false;

        $img = loadImage($path);
        if (!$img)
            return false;
        $rotated = imagerotate($img, $angle, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagedestroy($img);
        if (!$rotated)
            return false;
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        // written next to it under a hidden name, then put in its place
        $tmp = dirname($path) . '/.rotate-' . getmypid() . '.' . $ext;
        if ($ext === 'png')
            $ok = @imagepng($rotated, $tmp, 6);
        else if ($ext === 'webp')
            $ok = @imagewebp($rotated, $tmp, WEBP_QUALITY);
        else
            $ok = @imagejpeg($rotated, $tmp, 92);
        imagedestroy($rotated);

        $mtime = filemtime($path);
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

    // ---- ZIP downloads ----------------------------------------------------------------

    function canZip()
    {
        return class_exists('ZipArchive');
    }

    // files under a path for the ZIP as [full path or null for an empty folder,
    // path in the ZIP]; hidden files and the names in $skip (only right in the
    // given folder, e.g. the gallery script) stay out
    function zipCollect($full, $local, $skip, &$files, &$bytes, $depth = 0)
    {
        if (is_file($full)) {
            $files[] = [$full, $local];
            $bytes += filesize($full);
            return;
        }
        if (!is_dir($full) || is_link($full) || $depth > 10)
            return;

        $names = [];
        foreach (scandir($full) ?: [] as $name)
            if ($name[0] !== '.' && !in_array($name, $skip, true))
                $names[] = $name;
        if (!$names)
            $files[] = [null, $local . '/'];
        foreach ($names as $name)
            zipCollect($full . '/' . $name, $local . '/' . $name, [], $files, $bytes, $depth + 1);
    }

    // gallery items ([full path, rel] each) as what sendZip() takes: the top
    // folder is "i" and loses its index.php, the others keep their own name
    function galleryZipRoots($items)
    {
        $roots = [];
        foreach ($items as $item)
            $roots[] = [$item[0], $item[1] === '' ? 'i' : basename($item[1]), $item[1] === '' ? ['index.php'] : []];

        return $roots;
    }

    // Sends files and folders ([full path, name in the ZIP, names to leave out
    // right in it] each) as one ZIP, stored without compression, since
    // pictures and videos are compressed already. $maxBytes null means no limit
    // but the free disk space. A problem goes back to $backUrl as a message.
    function sendZip($roots, $zipName, $backUrl, $maxBytes = ZIP_MAX_BYTES)
    {
        $fail = function ($message) use ($backUrl) {
            setFlash($message);
            header('Location: ' . $backUrl, true, 303);
            exit;
        };
        if (!canZip())
            $fail('Serwer nie ma modułu ZIP (pakiet php-zip), pobieranie jest wyłączone.');

        $files = [];
        $bytes = 0;
        foreach ($roots as $root)
            zipCollect($root[0], $root[1], $root[2], $files, $bytes);
        if (!array_filter(array_column($files, 0)))
            $fail('Nie ma tu żadnych plików do pobrania.');
        if ($maxBytes !== null && $bytes > $maxBytes)
            $fail('To za dużo naraz (' . formatSize($bytes) . ', limit ' . formatSize($maxBytes) . '). Pobierz mniejsze foldery osobno.');
        // the ZIP is put together on the disk first
        $free = @disk_free_space(sys_get_temp_dir());
        if ($free !== false && $free < $bytes + 100 * 1024 * 1024)
            $fail('Za mało wolnego miejsca na dysku serwera na złożenie ZIP (' . formatSize($bytes) . ').');

        // the visitor's other pages need not wait while the ZIP is put together
        // and sent; a later problem opens the session again for its message
        if (session_status() === PHP_SESSION_ACTIVE)
            session_write_close();

        @set_time_limit(0);
        $tmp = @tempnam(sys_get_temp_dir(), 'sanakan-zip-');
        $zip = new ZipArchive();
        if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true)
            $fail('Nie udało się utworzyć pliku ZIP.');
        foreach ($files as $file) {
            if ($file[0] === null) {
                $zip->addEmptyDir($file[1]);
            } else {
                $zip->addFile($file[0], $file[1]);
                $zip->setCompressionName($file[1], ZipArchive::CM_STORE);
            }
        }
        if (!$zip->close()) {
            @unlink($tmp);
            $fail('Nie udało się utworzyć pliku ZIP.');
        }

        $ascii = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $zipName);
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($tmp));
        header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($zipName));
        header('Cache-Control: no-store');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    // ---- Duplicates ---------------------------------------------------------------
    // SHA-256 of gallery files, cached in inc/data/hashes.json as
    // [rel => ['size', 'mtime', 'hash', 'source' => hash of the uploaded original]].
    // A file turned into WebP differs from what was uploaded, so the original's
    // hash is kept too, and sending the same PNG again is still recognised.

    function fileHash($full, $rel, &$cache)
    {
        $size = filesize($full);
        $mtime = filemtime($full);
        $entry = $cache[$rel] ?? null;
        if (!$entry || $entry['size'] !== $size || $entry['mtime'] !== $mtime)
            $cache[$rel] = ['size' => $size, 'mtime' => $mtime, 'hash' => hash_file('sha256', $full)];

        return $cache[$rel]['hash'];
    }

    // where in the gallery a file with this content already is; only files of the
    // same size get hashed, so this stays quick
    function findDuplicates($base, $size, $hash)
    {
        $cache = readData('hashes');
        $before = $cache;
        $matches = [];

        $walk = function ($dirPath, $dirRel, $depth) use (&$walk, &$cache, &$matches, $size, $hash) {
            foreach (listNames($dirPath, $dirRel) as $name) {
                $full = $dirPath . '/' . $name;
                $rel = ltrim($dirRel . '/' . $name, '/');
                if (is_dir($full) && !is_link($full)) {
                    if ($depth < 10)
                        $walk($full, $rel, $depth + 1);
                    continue;
                }

                $source = $cache[$rel]['source'] ?? null;
                if ($source === $hash || (filesize($full) === $size && fileHash($full, $rel, $cache) === $hash))
                    $matches[] = galleryPath($rel);
            }
        };
        $walk($base, '', 0);

        if ($cache !== $before)
            writeData('hashes', $cache);

        return $matches;
    }

    function recordHash($full, $rel, $source)
    {
        $cache = readData('hashes');
        fileHash($full, $rel, $cache);
        $cache[$rel]['source'] = $source;
        writeData('hashes', $cache);
    }

    // after a move or rename the cached hashes follow the file, or everything in the folder
    function moveHashes($fromRel, $toRel)
    {
        $cache = readData('hashes');
        $moved = [];
        foreach ($cache as $rel => $entry) {
            $rel = (string)$rel;
            if ($rel === $fromRel || strpos($rel, $fromRel . '/') === 0)
                $rel = $toRel . substr($rel, strlen($fromRel));
            $moved[$rel] = $entry;
        }
        writeData('hashes', $moved);
    }

    function dropHashes($fromRel)
    {
        $cache = readData('hashes');
        foreach (array_keys($cache) as $rel)
            if ((string)$rel === $fromRel || strpos((string)$rel, $fromRel . '/') === 0)
                unset($cache[$rel]);
        writeData('hashes', $cache);
    }

    // ---- Trash ------------------------------------------------------------------
    // Deleted files and folders go to inc/data/trash/<id>/ for TRASH_DAYS days;
    // the admin panel restores them or deletes them for good. inc/ is not
    // reachable from the web, so trashed pictures stop working by their links.

    function trashDir()
    {
        return dataDir() . '/trash';
    }

    // [id => ['name', 'from' (rel), 'folder' (bool), 'size', 'deleted' (time), 'by']], newest first
    function trashItems()
    {
        $items = readData('trash');
        uasort($items, function ($a, $b) {
            return ($b['deleted'] ?? 0) - ($a['deleted'] ?? 0);
        });

        return $items;
    }

    function moveToTrash($full, $rel)
    {
        $id = date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $dir = trashDir() . '/' . $id;
        if (!@mkdir($dir, 0750, true))
            return false;

        $folder = is_dir($full);
        $size = treeSize($full);
        if (!@rename($full, $dir . '/' . basename($rel))) {
            @rmdir($dir);
            return false;
        }

        $user = siteUser();
        $items = readData('trash');
        $items[$id] = [
            'name' => basename($rel),
            'from' => $rel,
            'folder' => $folder,
            'size' => $size,
            'deleted' => time(),
            'by' => $user['name'] ?? ''
        ];

        return writeData('trash', $items);
    }

    // back where it was deleted from (that folder is made again when it is gone);
    // returns where it ended up, or null
    function restoreFromTrash($base, $id)
    {
        $items = readData('trash');
        if (!isset($items[$id]))
            return null;

        $item = $items[$id];
        $source = trashDir() . '/' . $id . '/' . $item['name'];
        $folderRel = dirname($item['from']) === '.' ? '' : dirname($item['from']);
        $folder = $base . ($folderRel === '' ? '' : '/' . $folderRel);
        if (!is_dir($folder) && !@mkdir($folder, 0755, true))
            return null;

        $name = freeName($folder, $item['name']);
        if (!file_exists($source) || !@rename($source, $folder . '/' . $name))
            return null;

        @rmdir(trashDir() . '/' . $id);
        unset($items[$id]);
        writeData('trash', $items);

        return ltrim($folderRel . '/' . $name, '/');
    }

    function deleteFromTrash($id)
    {
        $items = readData('trash');
        if (!isset($items[$id]))
            return false;

        removeTree(trashDir() . '/' . $id);
        unset($items[$id]);

        return writeData('trash', $items);
    }

    // what has been in the trash longer than TRASH_DAYS goes for good
    function purgeTrash()
    {
        foreach (readData('trash') as $id => $item)
            if (($item['deleted'] ?? 0) < time() - TRASH_DAYS * 86400)
                deleteFromTrash($id);
    }

    function iniBytes($value)
    {
        $value = trim((string)$value);
        $bytes = (float)$value;
        switch (strtolower(substr($value, -1))) {
            case 'g': $bytes *= 1024;
            case 'm': $bytes *= 1024;
            case 'k': $bytes *= 1024;
        }

        return (int)$bytes;
    }

    // the biggest file the server takes in one upload
    function uploadLimit()
    {
        $limits = array_filter([iniBytes(ini_get('upload_max_filesize')), iniBytes(ini_get('post_max_size'))]);

        return $limits ? min($limits) : 0;
    }

    function countLabel($n)
    {
        return $n . ' ' . plural($n, 'element', 'elementy', 'elementów');
    }

    // the items picked in the page, each as [full path, rel]; the top folder itself
    // cannot be one, and when none of them is in the gallery nothing is done
    function postedItems($base)
    {
        $items = [];
        foreach ((array)($_POST['items'] ?? []) as $rel) {
            $item = resolveAny($base, $rel);
            if ($item && $item[1] !== '')
                $items[] = $item;
        }

        if (!$items)
            reply(false, 'Nie znaleziono zaznaczonych elementów, odśwież stronę.', 404);

        return $items;
    }

    function handlePost($base)
    {
        // a request bigger than post_max_size arrives with no fields at all
        if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0)
            reply(false, 'Plik jest za duży (limit serwera: ' . formatSize(uploadLimit()) . ').', 413);

        if (!authConfigured())
            reply(false, 'Logowanie nie jest włączone na serwerze.', 403);

        $action = (string)($_POST['action'] ?? '');

        if (!siteUser())
            reply(false, 'Trzeba się zalogować.', 401);
        if (!checkCsrf())
            reply(false, 'Sesja wygasła, odśwież stronę.', 403);

        if ($action === 'request-access')
            handleAccessRequest('gallery', galleryCanView(), $_POST['back'] ?? '');

        if ($action === 'zip') {
            if (!galleryCanView())
                reply(false, 'To konto nie ma dostępu do galerii.', 403);
            $dir = trim((string)($_POST['dir'] ?? ''), '/');
            sendZip(galleryZipRoots(postedItems($base)), ($dir === '' ? 'galeria' : basename($dir)) . ' - wybrane.zip',
                localBack($_POST['back'] ?? ''));
        }

        if (!galleryIsAdmin())
            reply(false, 'To konto nie może zarządzać galerią.', 403);

        switch ($action) {

            case 'upload':
                uploadFile($base);

            case 'mkdir':
                $dir = resolvePath($base, $_POST['dir'] ?? '', true);
                $name = trim((string)($_POST['name'] ?? ''));
                if (!$dir)
                    reply(false, 'Nie ma takiego folderu.', 404);
                if ($error = nameError($name, false))
                    reply(false, $error, 400);
                if (file_exists($dir[0] . '/' . $name))
                    reply(false, 'Coś o nazwie ' . $name . ' już tu jest.', 409);
                if (!@mkdir($dir[0] . '/' . $name, 0755))
                    reply(false, 'Nie udało się utworzyć folderu.', 500);
                done('mkdir', 'Utworzono folder ' . galleryPath(ltrim($dir[1] . '/' . $name, '/')) . '.');

            case 'delete':
                purgeTrash();
                $items = postedItems($base);
                $trashed = [];
                $failed = [];
                foreach ($items as $item) {
                    if (moveToTrash($item[0], $item[1])) {
                        $trashed[] = galleryPath($item[1]);
                        dropHashes($item[1]);
                    } else {
                        $failed[] = basename($item[1]);
                    }
                }

                if ($trashed)
                    addHistory('delete', 'Do kosza: ' . implode(', ', $trashed) . '.');
                $message = 'Przeniesiono do kosza ' . countLabel(count($trashed)) . ' (na ' . TRASH_DAYS . ' dni, przywracanie w panelu).';
                if ($failed)
                    reply(false, $message . ' Nie udało się: ' . implode(', ', $failed) . '.', 500);
                reply(true, $message);

            case 'rotate':
                // GD turns counter-clockwise
                $left = ($_POST['direction'] ?? '') === 'left';
                $rotated = [];
                $skipped = [];
                foreach (postedItems($base) as $item) {
                    if (is_file($item[0]) && rotateImage($item[0], $left ? 90 : -90))
                        $rotated[] = galleryPath($item[1]);
                    else
                        $skipped[] = basename($item[1]);
                }

                if ($rotated)
                    addHistory('rotate', 'Obrócono w ' . ($left ? 'lewo' : 'prawo') . ': ' . implode(', ', $rotated) . '.');
                $message = 'Obrócono ' . countLabel(count($rotated)) . '.';
                if ($skipped)
                    reply(count($rotated) > 0, $message . ' Pominięto (obracać można obrazki PNG, JPG i WebP bez animacji): ' . implode(', ', $skipped) . '.', 400);
                reply(true, $message);

            case 'duplicates':
                $hash = strtolower((string)($_POST['hash'] ?? ''));
                if (!preg_match('/^[0-9a-f]{64}$/', $hash))
                    reply(false, 'Zły skrót pliku.', 400);
                reply(true, '', 200, ['matches' => findDuplicates($base, (int)($_POST['size'] ?? -1), $hash)]);

            case 'rename':
                $items = postedItems($base);
                if (count($items) !== 1)
                    reply(false, 'Zmienić nazwę można tylko jednemu elementowi naraz.', 400);

                list($full, $rel) = $items[0];
                $isFile = is_file($full);
                $old = basename($rel);
                $name = trim((string)($_POST['name'] ?? ''));
                if ($error = nameError($name, $isFile))
                    reply(false, $error, 400);
                // the name does not change what the file is, so the extension stays
                if ($isFile && extensionOf($name) !== extensionOf($old))
                    reply(false, 'Rozszerzenie musi zostać .' . extensionOf($old) . ', zmiana nazwy nie zmienia formatu pliku.', 400);
                if ($name === $old)
                    reply(true, 'Nazwa się nie zmieniła.');
                if (file_exists(dirname($full) . '/' . $name) && strcasecmp($name, $old) !== 0)
                    reply(false, 'Coś o nazwie ' . $name . ' już tu jest.', 409);
                if (!@rename($full, dirname($full) . '/' . $name))
                    reply(false, 'Nie udało się zmienić nazwy.', 500);
                moveHashes($rel, ltrim((dirname($rel) === '.' ? '' : dirname($rel)) . '/' . $name, '/'));

                done('rename', 'Zmieniono nazwę ' . galleryPath($rel) . ' na ' . $name . '.');

            case 'move':
                $target = resolvePath($base, $_POST['target'] ?? '', true);
                if (!$target)
                    reply(false, 'Nie ma takiego folderu docelowego.', 404);

                $moved = [];
                $problems = [];
                foreach (postedItems($base) as $item) {
                    $name = basename($item[1]);
                    if ($target[0] === $item[0] || strpos($target[0] . '/', $item[0] . '/') === 0) {
                        $problems[] = $name . ' (folderu nie da się przenieść do niego samego)';
                    } else if (dirname($item[0]) === $target[0]) {
                        $problems[] = $name . ' (już tam jest)';
                    } else if (file_exists($target[0] . '/' . $name)) {
                        $problems[] = $name . ' (w folderze docelowym jest już coś o tej nazwie)';
                    } else if (!@rename($item[0], $target[0] . '/' . $name)) {
                        $problems[] = $name . ' (nie udało się)';
                    } else {
                        $moved[] = galleryPath($item[1]);
                        moveHashes($item[1], ltrim($target[1] . '/' . $name, '/'));
                    }
                }

                $where = galleryPath($target[1]);
                if ($moved)
                    addHistory('move', 'Przeniesiono do ' . $where . ': ' . implode(', ', $moved) . '.');
                if ($problems)
                    reply(count($moved) > 0, 'Przeniesiono ' . countLabel(count($moved)) . ' do ' . $where . '. Pominięto: ' . implode(', ', $problems) . '.', 409);
                reply(true, 'Przeniesiono ' . countLabel(count($moved)) . ' do ' . $where . '.');
        }

        reply(false, 'Nieznana akcja.', 400);
    }

    // one file per request, so every file gets its own progress and its own error
    function uploadFile($base)
    {
        $dir = resolvePath($base, $_POST['dir'] ?? '', true);
        if (!$dir)
            reply(false, 'Nie ma takiego folderu.', 404);

        $file = $_FILES['file'] ?? null;
        if (!$file || is_array($file['name']))
            reply(false, 'Nie wysłano pliku.', 400);
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE)
            reply(false, $file['name'] . ': plik jest za duży (limit serwera: ' . formatSize(uploadLimit()) . ').', 413);
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']))
            reply(false, $file['name'] . ': wysyłanie się nie udało.', 400);

        $name = trim(basename(str_replace('\\', '/', (string)$file['name'])));
        if ($error = nameError($name, true))
            reply(false, $name . ': ' . $error, 400);

        // the extension alone is not enough, the content has to match it
        if (isVideo($name) ? !isWebm($file['tmp_name']) : !@getimagesize($file['tmp_name']))
            reply(false, $name . ': zawartość nie pasuje do typu pliku.', 400);

        // "change to WebP" ticked in the page; the original stays when that is not
        // possible or when the WebP would not be smaller
        $note = '';
        if (($_POST['webp'] ?? '') === '1' && in_array(extensionOf($name), WEBP_SOURCE_TYPES, true)) {
            $isGif = extensionOf($name) === 'gif';
            if (!($isGif ? canConvertGifToWebp() : canConvertToWebp())) {
                $note = $isGif
                    ? ' Serwer nie umie zamieniać GIF-ów na animowane WebP (brak gif2webp), zostawiono GIF.'
                    : ' Serwer nie umie zapisać WebP, zostawiono oryginał.';
            } else {
                $target = freeName($dir[0], pathinfo($name, PATHINFO_FILENAME) . '.webp');
                $path = $dir[0] . '/' . $target;
                $converted = $isGif ? gifToWebp($file['tmp_name'], $path) : convertToWebp($file['tmp_name'], $path);
                clearstatcache();

                if (!$converted) {
                    $note = ' Nie udało się zamienić na WebP, zostawiono oryginał.';
                } else if (filesize($path) >= $file['size']) {
                    $note = ' WebP wyszedłby większy (' . formatSize(filesize($path)) . '), zostawiono oryginał.';
                    @unlink($path);
                } else {
                    @chmod($path, 0644);
                    recordHash($path, ltrim($dir[1] . '/' . $target, '/'), hash_file('sha256', $file['tmp_name']));
                    $sizes = ' (' . formatSize($file['size']) . ' → ' . formatSize(filesize($path)) . ')';
                    done('upload', 'Dodano ' . galleryPath(ltrim($dir[1] . '/' . $target, '/')) . ', zamienione z ' . $name . $sizes . '.',
                        'Dodano ' . $name . ' jako ' . $target . $sizes . '.');
                }
            }
        }

        $target = freeName($dir[0], $name);
        $sentHash = hash_file('sha256', $file['tmp_name']);
        if (!@move_uploaded_file($file['tmp_name'], $dir[0] . '/' . $target))
            reply(false, $name . ': nie udało się zapisać pliku.', 500);
        @chmod($dir[0] . '/' . $target, 0644);
        $stripped = !isVideo($name) && stripMetadata($dir[0] . '/' . $target);
        recordHash($dir[0] . '/' . $target, ltrim($dir[1] . '/' . $target, '/'), $stripped ? $sentHash : null);

        done('upload', 'Dodano ' . galleryPath(ltrim($dir[1] . '/' . $target, '/')) . '.',
            ($target === $name ? 'Dodano ' . $name . '.' : 'Dodano ' . $name . ' jako ' . $target . ' (nazwa była zajęta).')
            . ($stripped ? ' Usunięto metadane (np. miejsce i czas zrobienia).' : '') . $note);
    }
