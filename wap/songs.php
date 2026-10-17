<?php
    // The songs of WAP (wap/): every audio file put straight in wap/.mu/, listed
    // at each request, so a song added or removed shows at once, with the title
    // and the artist of its tags or its name (wapSongs(), tags.php). wap.js
    // reads the list, the files go out through song.php; both only for those
    // inc/members.php lets in:
    //   [{ "file": "song.php?f=...", "title": "...", "artist": "..." }, ...]
    require __DIR__ . '/../inc/members.php';
    memberGate(false);
    require __DIR__ . '/tags.php';
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(wapSongs(__DIR__ . '/.mu'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
