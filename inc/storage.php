<?php
    // The data the site keeps as JSON files in inc/data: where they are, reading
    // them, and writing them so that a failed write never leaves a broken or
    // empty file behind; the old one stays and the caller hears of the failure.
    // No session and no HTML here: the pages, cron and the CLI tools share it.
    // inc/auth.php pulls this in.

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
    // loops); every write drops the entry, so a read after one is fresh.
    function &dataCache()
    {
        static $cache = [];

        return $cache;
    }

    function forgetData($name)
    {
        $cache = &dataCache();
        unset($cache[preg_replace('/\.json$/', '', (string)$name)]);
    }

    function readData($name)
    {
        $cache = &dataCache();
        if (array_key_exists($name, $cache))
            return $cache[$name];

        $file = dataDir() . '/' . $name . '.json';
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;

        return $cache[$name] = is_array($data) ? $data : [];
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

    // false when the data could not be written; the file keeps what it had
    function writeData($name, $data)
    {
        forgetData($name);
        $json = encodeData($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === null || (!is_dir(dataDir()) && !@mkdir(dataDir(), 0750, true)))
            return false;

        return replaceFile(dataDir() . '/' . $name . '.json', $json);
    }

    // Opens a file of inc/data locked, gives its data to $change and writes back
    // what that returns, so two requests at once do not lose a change. Data that
    // cannot be written as JSON leaves the file as it was. False when nothing
    // was written.
    function updateDataFile($name, $change)
    {
        forgetData($name);
        if (!is_dir(dataDir()) && !@mkdir(dataDir(), 0750, true))
            return false;
        $handle = @fopen(dataDir() . '/' . $name, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if ($handle !== false)
                fclose($handle);
            return false;
        }
        $data = json_decode((string)stream_get_contents($handle), true);
        $ok = rewriteLocked($handle, encodeData($change(is_array($data) ? $data : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        flock($handle, LOCK_UN);
        fclose($handle);

        return $ok;
    }

    // Writes $json over what a file opened and locked by the caller has, when
    // there is any: null (data encodeData() refused) leaves the file untouched.
    function rewriteLocked($handle, $json)
    {
        if (!is_string($json))
            return false;

        return ftruncate($handle, 0) && rewind($handle) && fwrite($handle, $json) === strlen($json) && fflush($handle);
    }
