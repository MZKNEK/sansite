<?php
    // One song of WAP from .mu/ (song.php?f=name), for those inc/members.php
    // lets in (wapAllowed()).
    require __DIR__ . '/../inc/members.php';
    memberGate(false, wapAllowed());

    sendMemberFile(__DIR__ . '/.mu', $_GET['f'] ?? '', [
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg',
        'opus' => 'audio/ogg',
        'm4a' => 'audio/mp4',
        'aac' => 'audio/aac',
        'flac' => 'audio/flac',
        'wav' => 'audio/wav',
        'webm' => 'audio/webm'
    ]);
