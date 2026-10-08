<?php
    // Tiny dependency-free test harness. tests/run.php loads this, then every
    // tests/test_*.php, and runs the cases they register with test(). No composer
    // and no external package, so it runs on the server's PHP too:
    //   php tests/run.php
    //
    // The data the site writes is pointed at a fresh temporary folder before the
    // code under test is loaded, so a test never touches inc/data (dataDir() in
    // inc/auth.php).

    if (!defined('SITE_DATA_DIR')) {
        $dir = sys_get_temp_dir() . '/sanakan-tests-' . getmypid() . '-' . bin2hex(random_bytes(4));
        if (!is_dir($dir) && !mkdir($dir, 0700, true))
            fwrite(STDERR, "Cannot create the test data folder: $dir\n");
        define('SITE_DATA_DIR', $dir);
        register_shutdown_function(function () use ($dir) { rmtree($dir); });
    }

    function rmtree($path)
    {
        if (is_link($path) || is_file($path))
            return @unlink($path);
        foreach (scandir($path) ?: [] as $name)
            if ($name !== '.' && $name !== '..')
                rmtree($path . '/' . $name);

        return @rmdir($path);
    }

    // wipes the data folder and the per-process read cache between two cases, so
    // one does not leak into the next
    function clearData()
    {
        $cache = &dataCache();
        $cache = [];
        foreach (glob(SITE_DATA_DIR . '/*') ?: [] as $path)
            rmtree($path);
    }

    // a fresh folder for a case that needs files on disk (always with forward
    // slashes, the way the pages pass $base to resolvePath())
    function tempDir()
    {
        $dir = SITE_DATA_DIR . '/t-' . bin2hex(random_bytes(4));
        if (!is_dir($dir))
            mkdir($dir, 0700, true);

        return str_replace('\\', '/', $dir);
    }

    // ---- The code under test ----------------------------------------------------
    // Required once here, whatever tests are loaded; the order follows what each
    // file needs (gallery pulls in auth, diag pulls in bot, system and services).
    require_once __DIR__ . '/../inc/gallery.php';
    require_once __DIR__ . '/../inc/bot.php';
    require_once __DIR__ . '/../inc/verdiff.php';
    require_once __DIR__ . '/../inc/markdown.php';
    require_once __DIR__ . '/../inc/status-card.php';
    require_once __DIR__ . '/../inc/diag.php';
    require_once __DIR__ . '/../inc/panel-stats.php';
    require_once __DIR__ . '/../inc/cloudflare.php';
    require_once __DIR__ . '/../inc/autoblock.php';
    require_once __DIR__ . '/../inc/services.php';
    require_once __DIR__ . '/../inc/meta.php';

    // a real session for the tests that need a logged-in account; start it with
    // sessionFor() and close it with sessionEnd() when done
    function sessionFor($id, $account)
    {
        if (session_status() === PHP_SESSION_ACTIVE)
            session_write_close();
        session_id($id);
        $_COOKIE[SITE_SESSION] = $id;
        siteSession();
        $_SESSION['gallery_user'] = $account;
        $_SESSION['login_time'] = time();
        $_SESSION['gallery_csrf'] = 'csrf-token';
    }

    function sessionEnd()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        unset($_COOKIE[SITE_SESSION]);
    }

    // ---- The cases --------------------------------------------------------------

    $GLOBALS['__tests'] = [];
    $GLOBALS['__assertions'] = 0;
    $GLOBALS['__suite'] = '';

    function test($name, $fn)
    {
        $GLOBALS['__tests'][] = [$GLOBALS['__suite'], $name, $fn];
    }

    function test_assert($condition, $message)
    {
        $GLOBALS['__assertions']++;
        if (!$condition)
            throw new RuntimeException($message);
    }

    function describe($value)
    {
        return is_string($value) ? "'" . $value . "'" : var_export($value, true);
    }

    function assertTrue($value, $message = '')
    {
        test_assert($value === true, ($message !== '' ? $message . ': ' : '') . 'expected true, got ' . describe($value));
    }

    function assertFalse($value, $message = '')
    {
        test_assert($value === false, ($message !== '' ? $message . ': ' : '') . 'expected false, got ' . describe($value));
    }

    function assertNull($value, $message = '')
    {
        test_assert($value === null, ($message !== '' ? $message . ': ' : '') . 'expected null, got ' . describe($value));
    }

    function assertSame($expected, $actual, $message = '')
    {
        test_assert($expected === $actual, ($message !== '' ? $message . ': ' : '') . 'expected ' . describe($expected) . ', got ' . describe($actual));
    }

    function assertEquals($expected, $actual, $message = '')
    {
        test_assert($expected == $actual, ($message !== '' ? $message . ': ' : '') . 'expected ' . describe($expected) . ', got ' . describe($actual));
    }

    // a substring in a string, or an element in an array
    function assertContains($needle, $haystack, $message = '')
    {
        $found = is_array($haystack) ? in_array($needle, $haystack, true) : strpos((string)$haystack, (string)$needle) !== false;
        test_assert($found, ($message !== '' ? $message . ': ' : '') . 'expected to find ' . describe($needle) . ' in ' . describe($haystack));
    }

    function assertMatches($pattern, $subject, $message = '')
    {
        test_assert((bool)preg_match($pattern, (string)$subject), ($message !== '' ? $message . ': ' : '') . describe($subject) . ' does not match ' . $pattern);
    }

    // runs $fn and returns the exception it threw, or fails
    function assertThrows($fn, $message = '')
    {
        try {
            $fn();
        } catch (Throwable $e) {
            return $e;
        }
        test_assert(false, ($message !== '' ? $message . ': ' : '') . 'expected an exception, none was thrown');

        return null;
    }
