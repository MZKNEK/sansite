<?php
    // The version history moved to state/versions/; the old address, linked
    // from Discord and the wiki, goes there for good, with its ?v=.
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ../versions/' . ($query !== '' ? '?' . $query : ''), true, 301);
