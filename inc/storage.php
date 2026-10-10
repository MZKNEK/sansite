<?php
    // The data the site keeps as JSON files in inc/data: where they are, reading
    // them, and changing them so that two requests at once lose nothing and a
    // failed write never leaves a broken or empty file behind; the old one stays
    // and the caller hears of the failure. No session and no HTML here: the
    // pages, cron and the CLI tools share it. inc/auth.php pulls this in.
    //
    // How a file is changed (updateJsonFile()): a lock file of its own, which is
    // never replaced or removed, is locked; the file is read from the disk (not
    // from the cache of this request); the change is made; the new JSON goes to a
    // new file that is flushed and renamed over the old one; then the lock is let
    // go. Readers take the same lock shared, so they wait for a change that is
    // being written and never see a file that is not whole. Every process that
    // writes, PHP-FPM, cron and the CLI, goes through here.

    // how long a request waits for a file someone else is changing, in seconds;
    // a change takes milliseconds, so this only stops a stuck one from holding
    // every PHP-FPM worker
    const DATA_LOCK_WAIT = 5;

    // Where the site keeps its data. A test (or an unusual install) can point it
    // elsewhere with SITE_DATA_DIR before the files are loaded; an empty value is
    // ignored, so a misconfiguration cannot move the data to a bare path.
    function dataDir()
    {
        if (defined('SITE_DATA_DIR') && SITE_DATA_DIR !== '')
            return rtrim((string)SITE_DATA_DIR, '/\\');

        return __DIR__ . '/data';
    }

    function dataWritable()
    {
        return is_dir(dataDir()) ? is_writable(dataDir()) : is_writable(__DIR__);
    }

    // The data read this request, so a page that asks for the same file many
    // times reads it once (the panel walks the access lists and the roles in
    // loops); every write drops the entry, so a read after one is fresh. A
    // change never starts from it (updateData() reads the disk).
    function &dataCache()
    {
        static $cache = [];

        return $cache;
    }

    // "access" and "access.json" are one file, with one lock
    function dataName($name)
    {
        return preg_replace('/\.json$/', '', (string)$name);
    }

    function forgetData($name)
    {
        $cache = &dataCache();
        unset($cache[dataName($name)]);
    }

    function dataFile($name)
    {
        return dataDir() . '/' . dataName($name) . '.json';
    }

    function dataLockFile($name)
    {
        return dataDir() . '/locks/' . dataName($name) . '.lock';
    }

    // What a file has; [] when there is none. A broken file (empty, cut, not
    // JSON, unreadable) reads as [] too, so the pages go on, but it is logged
    // and no change is ever written over it (updateData()).
    function readData($name)
    {
        $name = dataName($name);
        $cache = &dataCache();
        if (array_key_exists($name, $cache))
            return $cache[$name];

        $lock = fileLock(dataLockFile($name), false);
        $data = loadJsonFile(dataFile($name));
        if ($lock !== null)
            fclose($lock);
        if ($data === null)
            error_log('sanakan: ' . dataFile($name) . ' is broken, read as empty and kept as it is');

        return $cache[$name] = $data ?? [];
    }

    // Changes a file of inc/data: $change gets its data, fresh from the disk, by
    // reference and changes it in place; what it returns comes back in $result.
    // The file is written only when the data changed. False when the change
    // could not be made (the file is broken, someone else held it too long, the
    // disk refused), and then the file is as it was. $change must not leave the
    // request (no reply(), no exit) and must not wait for the network.
    function updateData($name, $change, &$result = null, $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    {
        $name = dataName($name);
        forgetData($name);
        if (!is_dir(dataDir()) && !@mkdir(dataDir(), 0750, true) && !is_dir(dataDir()))
            return false;

        $ok = updateJsonFile(dataFile($name), dataLockFile($name), $change, $result, $flags);
        forgetData($name);

        return $ok;
    }

    // Puts $data in place of what the file has, for what is made whole each time
    // (a cache with one writer, the first state, the panel's "log out all"). A
    // change of a list others change too goes through updateData(). A broken
    // file is set aside first, for a look later. False when it could not be
    // written; the file keeps what it had.
    function writeData($name, $data)
    {
        $name = dataName($name);

        return updateData($name, function (&$current) use ($data) {
            $current = $data;
        }, $result) || replaceBrokenData($name, $data);
    }

    // writeData() over a broken file: the file goes aside as name.json.broken-<time>
    function replaceBrokenData($name, $data)
    {
        $file = dataFile($name);
        $lock = fileLock(dataLockFile($name), true);
        if ($lock === null)
            return false;
        $ok = false;
        if (is_file($file) && loadJsonFile($file) === null && @rename($file, $file . '.broken-' . time())) {
            error_log('sanakan: ' . $file . ' was broken, set aside and written anew');
            $ok = writeJsonFile($file, $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        fclose($lock);
        forgetData($name);

        return $ok;
    }

    // The compatible form of updateData() for the files named with .json: $change
    // gets the data and returns them changed.
    function updateDataFile($name, $change)
    {
        return updateData($name, function (&$data) use ($change) {
            $data = $change($data);
        }, $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // ---- The files themselves -------------------------------------------------
    // Also used for the files outside inc/data (the bot's state, diag).

    // A lock file opened and locked, shared or not, or null when that did not
    // work within DATA_LOCK_WAIT seconds. Closing the handle lets it go. The file
    // itself stays: removing it while someone waits on it would let two in.
    function fileLock($lockFile, $exclusive)
    {
        $dir = dirname($lockFile);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir))
            return null;
        $handle = @fopen($lockFile, 'c');
        if ($handle === false)
            return null;

        $mode = ($exclusive ? LOCK_EX : LOCK_SH) | LOCK_NB;
        for ($waited = 0; !flock($handle, $mode); $waited++) {
            if ($waited >= DATA_LOCK_WAIT * 50) {
                fclose($handle);
                error_log('sanakan: ' . $lockFile . ' held for longer than ' . DATA_LOCK_WAIT . ' s');
                return null;
            }
            usleep(20000);
        }

        return $handle;
    }

    // what a JSON file has: [] when it is not there, null when it is broken
    function loadJsonFile($file)
    {
        clearstatcache(true, $file);
        if (!file_exists($file))
            return [];
        $data = json_decode((string)@file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    function writeJsonFile($file, $data, $flags = 0)
    {
        $json = encodeData($data, $flags);

        return $json !== null && replaceFile($file, $json);
    }

    // updateData() for any JSON file with its lock file; see there. $rebuild
    // lets a change start from [] over a broken file, for what is only a summary
    // made again from what comes next (diag); the broken file is set aside.
    function updateJsonFile($file, $lockFile, $change, &$result = null, $flags = 0, $rebuild = false)
    {
        $lock = fileLock($lockFile, true);
        if ($lock === null)
            return false;

        try {
            $data = loadJsonFile($file);
            if ($data === null && $rebuild && @rename($file, $file . '.broken-' . time())) {
                error_log('sanakan: ' . $file . ' was broken, set aside and made again');
                $data = [];
            }
            if ($data === null) {
                error_log('sanakan: ' . $file . ' is broken, the change is not written over it');
                return false;
            }
            $before = $data;
            $result = $change($data);

            return !is_array($data) ? false : ($data === $before || writeJsonFile($file, $data, $flags));
        } finally {
            fclose($lock);
        }
    }

    // $data as JSON, or null when it cannot be (text that is not UTF-8, say).
    // Nothing is cut or replaced on the way: what comes out is all of it.
    function encodeData($data, $flags = 0)
    {
        try {
            return json_encode($data, $flags | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('sanakan: data not written, it is not valid JSON: ' . $e->getMessage());
            return null;
        }
    }

    // Puts $content into $file whole or not at all: it is written to a new file
    // next to it, flushed to the disk and renamed over it, so a reader finds the
    // old file or the new one, never a part, and a failure leaves the old one as
    // it was. $mode, when given, is set on the new file before it takes the place
    // of the old one. Anything but a string (a json_encode() that failed) is
    // refused.
    function replaceFile($file, $content, $mode = null)
    {
        if (!is_string($content))
            return false;

        $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $handle = @fopen($tmp, 'x');
        if ($handle === false)
            return false;
        $ok = @fwrite($handle, $content) === strlen($content) && fflush($handle) && @fsync($handle);
        fclose($handle);
        if ($ok && $mode !== null)
            @chmod($tmp, $mode);
        if ($ok && @rename($tmp, $file))
            return true;

        @unlink($tmp);
        return false;
    }
