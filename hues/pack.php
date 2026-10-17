<?php
    // One file of the pack of 0x40 from .pack/ (pack.php?f=Images/name.webp,
    // Songs/loop_Name.ogg, respack.json, pictures.bin...), for those
    // inc/members.php lets in.
    // The list may change with any run of respack.php or an owner's middle
    // (inc/hues-centres.php), so the browser asks for it again each time.
    require __DIR__ . '/../inc/members.php';
    require __DIR__ . '/../inc/hues-centres.php';
    memberGate(false);

    $name = (string)($_GET['f'] ?? '');
    if ($name === 'respack.json') {
        $pack = huesPack(__DIR__ . '/.pack/respack.json');
        if ($pack === null) {
            http_response_code(404);
            header('Cache-Control: no-store');
            exit;
        }
        $pack['centres'] = (object)$pack['centres'];
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($pack, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // all the pictures in one (respack.php), kept by the browser for a year
    // when asked for by the version of the file there is (pictures.bin&v=),
    // as another version gets another address; asked for by another one (a
    // list older or newer than the file, in the middle of a deploy) it is
    // given, but not kept
    if ($name === 'pictures.bin') {
        $file = __DIR__ . '/.pack/pictures.bin';
        $version = (string)($_GET['v'] ?? '');
        $kept = $version !== '' && is_file($file) && hash_equals(substr((string)md5_file($file), 0, 12), $version);
        sendMemberFile(__DIR__ . '/.pack', $name, ['bin' => 'application/octet-stream'], $kept ? 31536000 : 0);
        exit;
    }

    sendMemberFile(__DIR__ . '/.pack', $name, [
        'webp' => 'image/webp',
        'ogg' => 'audio/ogg'
    ]);
