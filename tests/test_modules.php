<?php
    // How the code is split into files: every function in one place, the
    // modules of a part loaded by its file in inc/ and only defining things,
    // and none of them taking the site's folder for its own.

    // the PHP files of the site's code, not of the tests or the tools
    function siteSources()
    {
        $root = str_replace('\\', '/', dirname(__DIR__));
        $files = [];
        $walk = function ($dir) use (&$walk, &$files, $root) {
            foreach (scandir($dir) ?: [] as $name) {
                if ($name[0] === '.' || in_array($name, ['tests', 'tools', 'apps', 'node_modules', 'data', 'i', 'server'], true))
                    continue;
                $path = $dir . '/' . $name;
                if (is_dir($path))
                    $walk($path);
                else if (substr($name, -4) === '.php')
                    $files[] = substr($path, strlen($root) + 1);
            }
        };
        $walk($root);
        $files[] = 'i/index.php';

        return $files;
    }

    test('every function is defined in one place', function () {
        $root = dirname(__DIR__);
        $seen = [];
        foreach (siteSources() as $file)
            if (preg_match_all('/^    function &?(\w+)\(/m', (string)file_get_contents($root . '/' . $file), $found))
                foreach ($found[1] as $name)
                    $seen[strtolower($name)][] = $file;
        $twice = array_filter($seen, function ($files) { return count($files) > 1; });
        assertSame([], $twice, 'defined more than once');
    });

    test('the modules of a part only define things and are all loaded', function () {
        $root = str_replace('\\', '/', dirname(__DIR__));
        // each part and the file that loads its modules
        foreach (['gallery' => 'inc/gallery.php', 'auth' => 'inc/auth.php', 'admin' => 'admin/index.php'] as $part => $loader) {
            $facade = (string)file_get_contents($root . '/' . $loader);
            foreach (glob($root . '/inc/' . $part . '/*.php') as $module) {
                $name = basename($module);
                assertTrue(strpos($facade, "/" . $part . '/' . $name . "'") !== false, $name . ' is loaded by ' . $loader);
                $code = (string)file_get_contents($module);
                // at the top level only comments, functions and constants
                foreach (explode("\n", $code) as $line)
                    if (preg_match('/^    (?!\/\/|function |const |[{}\]]|$)\S/', $line))
                        test_assert(false, $name . ' does something when loaded: ' . trim($line));
                // (inside a function it is done when asked, as authConfigured() reads inc/config.php)
                assertFalse((bool)preg_match('/^    (require|include)/m', $code), $name . ' loads nothing itself');
                // one folder deeper than inc/: the site's folder is two up
                assertFalse((bool)preg_match('/dirname\(__DIR__\)/', $code), $name . ' takes inc/ for the site\'s folder');
            }
        }
    });
