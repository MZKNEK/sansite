<?php
    // The history of the changes in the commands moved to cmd/changes/; the
    // old address, linked from Discord and the wiki, goes there for good.
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ../changes/' . ($query !== '' ? '?' . $query : ''), true, 301);
