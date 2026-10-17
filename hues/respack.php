<?php
    // The pack of 0x40 (hues/), in .pack/ (a dot folder, which nginx refuses,
    // so pack.php gives it out to those inc/members.php lets in), in the
    // folders of a 0x40 Hues respack:
    //   Images/<name>.webp               a picture, 1280x720 with alpha
    //   Animations/<name>/<name>_NN.webp the frames of an animation
    //   Songs/loop_<Name>.ogg            a song's loop, and the buildup
    //   Songs/build_<Name>.ogg           played once before it, if any
    // A PNG put in Images/ or Animations/ is changed to lossless WebP (cwebp,
    // from CWEBP or the PATH; the 2-bit line art of the pack is several times
    // larger as lossy WebP) and removed, then respack.json lists what there is,
    // which hues.js reads (through pack.php). Run it after adding or removing files:
    //   php hues/respack.php
    //   CWEBP=/path/to/cwebp php hues/respack.php
    // The middles of the figures of pictures with no transparency to find
    // them by (centres, name: 0 to 1 across, set by hand, as with
    // php hues/centres.php) are kept too, for the pictures still there.
    // The songs' classic beat maps of the pack (title, rhythm, buildupRhythm of
    // a respack's songs.xml) are kept in respack.json from run to run; a
    // songs.xml given takes their place for the songs it names:
    //   php hues/respack.php path/to/songs.xml ...
    // It lies in a folder the site serves, so a request over the web stops here.
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
    $root = str_replace('\\', '/', __DIR__) . '/.pack';
    $cwebp = getenv('CWEBP') ?: 'cwebp';

    $pngs = array_merge(glob($root . '/Images/*.png') ?: [], glob($root . '/Animations/*/*.png') ?: []);
    foreach ($pngs as $png) {
        $webp = substr($png, 0, -4) . '.webp';
        exec(escapeshellarg($cwebp) . ' -quiet -lossless -z 9 -metadata none ' . escapeshellarg($png) . ' -o ' . escapeshellarg($webp), $output, $code);
        if ($code !== 0 || !is_file($webp) || filesize($webp) === 0) {
            fwrite(STDERR, "Nie udało się zmienić na WebP: $png (cwebp: $cwebp)\n");
            exit(1);
        }
        unlink($png);
        echo substr($webp, strlen($root) + 1), "\n";
    }

    // "NightDriveHome" as "Night Drive Home", "Valsed'Hiver" as "Valse
    // d'Hiver", "Echo(slowed)" as "Echo (slowed)"
    function songTitle($name)
    {
        return preg_replace('/(?<=[a-z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])|(?<=[a-z])(?=[a-z]\'[A-Z])|(?<=\S)(?=\()/', ' ', $name);
    }

    $names = function ($pattern) {
        $list = array_map(function ($path) { return pathinfo($path, PATHINFO_FILENAME); }, glob($pattern) ?: []);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);
        return $list;
    };

    $images = $names($root . '/Images/*.webp');
    $animations = [];
    foreach (glob($root . '/Animations/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $frames = $names($dir . '/*.webp');
        if ($frames)
            $animations[] = ['name' => basename($dir), 'frames' => $frames];
    }
    // the maps kept from the last run, by the loop's file, then the ones of
    // the songs.xml given
    $maps = [];
    $old = json_decode((string)@file_get_contents($root . '/respack.json'), true);
    $centres = array_intersect_key($old['centres'] ?? [], array_flip($images));
    ksort($centres, SORT_NATURAL | SORT_FLAG_CASE);
    foreach (($old['songs'] ?? []) as $song) {
        if (isset($song['rhythm']))
            $maps[$song['loop']] = array_intersect_key($song, array_flip(['title', 'rhythm', 'buildupRhythm']));
    }
    foreach (array_slice($argv, 1) as $xmlFile) {
        $xml = @simplexml_load_file($xmlFile);
        if ($xml === false) {
            fwrite(STDERR, "Nie udało się odczytać $xmlFile.\n");
            exit(1);
        }
        foreach ($xml->song as $song) {
            $map = ['title' => trim((string)$song->title), 'rhythm' => trim((string)$song->rhythm)];
            if (isset($song->buildupRhythm))
                $map['buildupRhythm'] = trim((string)$song->buildupRhythm);
            $maps[(string)$song['name'] . '.ogg'] = array_filter($map, 'strlen');
        }
    }

    $songs = [];
    foreach ($names($root . '/Songs/loop_*.ogg') as $loop) {
        $name = substr($loop, 5);
        $songs[] = array_merge([
            'name' => songTitle($name),
            'loop' => $loop . '.ogg',
            'build' => is_file($root . '/Songs/build_' . $name . '.ogg') ? 'build_' . $name . '.ogg' : null
        ], $maps[$loop . '.ogg'] ?? []);
    }

    $json = json_encode(['images' => $images, 'centres' => (object)$centres, 'animations' => $animations, 'songs' => $songs],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    if (file_put_contents($root . '/respack.json', $json) === false)
        exit(1);
    $mapped = count(array_filter($songs, function ($song) { return isset($song['rhythm']); }));
    echo 'respack.json: ', count($images), ' obrazów, ', count($animations), ' animacji, ', count($songs), " utworów ($mapped z klasyczną mapą)\n";
