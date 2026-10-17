<?php
    // One file of the pack of 0x40 from .pack/ (pack.php?f=Images/name.webp,
    // Songs/loop_Name.ogg, respack.json...), for those inc/members.php lets in.
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

    sendMemberFile(__DIR__ . '/.pack', $name, [
        'webp' => 'image/webp',
        'ogg' => 'audio/ogg'
    ]);
