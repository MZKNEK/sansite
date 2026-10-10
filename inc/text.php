<?php
    // Small text helpers shared by every page and the panel: escaping, lower
    // case (also without the mbstring extension), Polish plurals, byte sizes,
    // thousands and what changed between two texts. inc/auth.php pulls this in, so any page that loads the login
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
            return function_exists('mb_strtolower') ? mb_strtolower((string)$text, 'UTF-8') : lowerWithoutMb($text);
        }

        // Without mbstring: strtolower changes only A-Z, so the capitals of
        // Latin-1, Latin Extended-A (all the Polish ones), Greek and Cyrillic are
        // changed by a table, built once. Every one of them is two bytes in UTF-8.
        function lowerWithoutMb($text)
        {
            static $map = null;
            if ($map === null) {
                $utf8 = function ($code) { return chr(0xC0 | $code >> 6) . chr(0x80 | $code & 0x3F); };
                $pairs = [0x178 => 0xFF];
                for ($code = 0xC0; $code <= 0xDE; $code++)
                    if ($code !== 0xD7)
                        $pairs[$code] = $code + 0x20;
                // Latin Extended-A: a capital and its small letter side by side,
                // the capital even or odd by range (İ and ĸ have no pair here)
                foreach ([[0x100, 0x12F, 0], [0x132, 0x137, 0], [0x139, 0x148, 1], [0x14A, 0x177, 0], [0x179, 0x17E, 1]] as [$from, $to, $odd])
                    for ($code = $from; $code < $to; $code++)
                        if ($code % 2 === $odd)
                            $pairs[$code] = $code + 1;
                for ($code = 0x391; $code <= 0x3AB; $code++)
                    if ($code !== 0x3A2)
                        $pairs[$code] = $code + 0x20;
                $pairs += [0x386 => 0x3AC, 0x388 => 0x3AD, 0x389 => 0x3AE, 0x38A => 0x3AF, 0x38C => 0x3CC, 0x38E => 0x3CD, 0x38F => 0x3CE];
                for ($code = 0x400; $code <= 0x40F; $code++)
                    $pairs[$code] = $code + 0x50;
                for ($code = 0x410; $code <= 0x42F; $code++)
                    $pairs[$code] = $code + 0x20;
                $map = [];
                foreach ($pairs as $upper => $small)
                    $map[$utf8($upper)] = $utf8($small);
            }

            return strtr(strtolower((string)$text), $map);
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

    if (!function_exists('textDiff')) {
        // What changed between two texts, as [the same start, the old middle,
        // the new middle, the same end]. Both ends stop at the edge of a word,
        // so a word that changed shows whole ("liste" → "listę", not "e" → "ę").
        function textDiff($old, $new)
        {
            $a = preg_split('//u', (string)$old, -1, PREG_SPLIT_NO_EMPTY);
            $b = preg_split('//u', (string)$new, -1, PREG_SPLIT_NO_EMPTY);
            $word = function ($char) { return $char !== null && preg_match('/[\p{L}\p{N}]/u', $char) === 1; };

            $max = min(count($a), count($b));
            $start = 0;
            while ($start < $max && $a[$start] === $b[$start])
                $start++;
            while ($start > 0 && $word($a[$start - 1]) && ($word($a[$start] ?? null) || $word($b[$start] ?? null)))
                $start--;

            $end = 0;
            while ($end < $max - $start && $a[count($a) - 1 - $end] === $b[count($b) - 1 - $end])
                $end++;
            while ($end > 0 && $word($a[count($a) - $end])
                && ($word($a[count($a) - $end - 1] ?? null) || $word($b[count($b) - $end - 1] ?? null)))
                $end--;

            $part = function ($chars, $from, $to) { return implode('', array_slice($chars, $from, $to - $from)); };

            return [
                $part($a, 0, $start),
                $part($a, $start, count($a) - $end),
                $part($b, $start, count($b) - $end),
                $part($a, count($a) - $end, count($a))
            ];
        }
    }
