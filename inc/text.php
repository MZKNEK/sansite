<?php
    // Small text helpers shared by every page and the panel: escaping, lower
    // case (also without the mbstring extension), Polish plurals, byte sizes and
    // thousands. inc/auth.php pulls this in, so any page that loads the login
    // (every page that needs them) has them; kept here so the pages do not each
    // define their own copy.
    if (!function_exists('e')) {
        function e($text)
        {
            return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('lower')) {
        // lower case for the search, also without the mbstring extension
        function lower($text)
        {
            return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        }
    }

    if (!function_exists('plural')) {
        // Polish plural: 1 plik, 2-4 pliki, 5+ plików (but 12-14 plików)
        function plural($n, $one, $few, $many)
        {
            if ($n == 1)
                return $one;

            $last = $n % 10;
            $lastTwo = $n % 100;
            return $last >= 2 && $last <= 4 && ($lastTwo < 12 || $lastTwo > 14) ? $few : $many;
        }
    }

    if (!function_exists('formatSize')) {
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
    }

    if (!function_exists('formatCount')) {
        // "41 234": thousands split by a space, as in Polish
        function formatCount($n)
        {
            return number_format((int)$n, 0, ',', ' ');
        }
    }
