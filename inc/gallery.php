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

    // Path of a command-line tool from libwebp (on Ubuntu: apt-get install webp),
    // or null. PHP-FPM usually runs without PATH, so the usual folders are tried too.
    function webpTool($name)
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
        return webpTool('gif2webp') !== null;
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
        $ok = runTool(webpTool('gif2webp'), ['-lossy', '-q', (string)GIF_WEBP_QUALITY, '-m', '2', '-mt', $source, '-o', $tmp], TOOL_TIMEOUT)
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

    // thumbnail URL of a picture, or null when it should get a placeholder
    function thumbUrl($rel, $full)
    {
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

        return str_replace('.', ',', round($bytes / 1024 / 1024, 1)) . ' MB';
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
                if ($mux = webpTool('webpmux')) {
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

    function makeThumb($source, $target)
    {
        $img = loadImage($source);
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

    // Thumbnail of one picture, made on first use and then served from the cache.
    // The URL has the file time in it, so browsers may keep it for good.
    function sendThumb($base, $rel)
    {
        $file = resolvePath($base, $rel, false);
        if (!$file || !isImage($file[1])) {
            http_response_code(404);
            return;
        }

        $webp = function_exists('imagewebp');
        $key = md5($file[0] . '|' . filemtime($file[0]) . '|' . filesize($file[0]) . '|' . THUMB_SIZE);
        $cache = sys_get_temp_dir() . '/sanakan-thumbs/' . $key . ($webp ? '.webp' : '.png');

        if (!is_file($cache) && (!hasGd() || !makeThumb($file[0], $cache))) {
            // no thumbnail possible, the picture itself has to do
            header('Location: ' . fileUrl($file[1]));
            return;
        }

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

    function reply($ok, $message, $status = 200)
    {
        http_response_code($ok ? 200 : $status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $ok, 'message' => $message]);
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

        if ($action === 'logout') {
            logout();
            reply(true, 'Wylogowano.');
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
                reply(true, 'Utworzono folder ' . $name . '.');

            case 'delete':
                $items = postedItems($base);
                $failed = [];
                foreach ($items as $item)
                    if (!removeTree($item[0]))
                        $failed[] = basename($item[1]);

                $done = count($items) - count($failed);
                if ($failed)
                    reply(false, 'Usunięto ' . countLabel($done) . ', nie udało się: ' . implode(', ', $failed) . '.', 500);
                reply(true, 'Usunięto ' . countLabel($done) . '.');

            case 'move':
                $target = resolvePath($base, $_POST['target'] ?? '', true);
                if (!$target)
                    reply(false, 'Nie ma takiego folderu docelowego.', 404);

                $moved = 0;
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
                        $moved++;
                    }
                }

                $where = $target[1] === '' ? 'i' : 'i/' . $target[1];
                if ($problems)
                    reply($moved > 0, 'Przeniesiono ' . countLabel($moved) . ' do ' . $where . '. Pominięto: ' . implode(', ', $problems) . '.', 409);
                reply(true, 'Przeniesiono ' . countLabel($moved) . ' do ' . $where . '.');
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
                    reply(true, 'Dodano ' . $name . ' jako ' . $target . ' (' . formatSize($file['size']) . ' → ' . formatSize(filesize($path)) . ').');
                }
            }
        }

        $target = freeName($dir[0], $name);
        if (!@move_uploaded_file($file['tmp_name'], $dir[0] . '/' . $target))
            reply(false, $name . ': nie udało się zapisać pliku.', 500);
        @chmod($dir[0] . '/' . $target, 0644);

        reply(true, ($target === $name ? 'Dodano ' . $name . '.' : 'Dodano ' . $name . ' jako ' . $target . ' (nazwa była zajęta).') . $note);
    }
