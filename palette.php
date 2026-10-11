<?php
    // What the search of every page (Ctrl+K, js/palette.js) finds besides the
    // pages themselves: the bot's commands with their address on /cmd/
    // (commandAnchor() in inc/bot.php, so a link opens the same command as the
    // page's own) and the versions of /state/wersje/. Only what anyone sees:
    // the moderator and debug commands stay on /cmd/, so the answer is the same
    // for everyone and browsers may keep it a few minutes.
    require __DIR__ . '/inc/bot.php';
    require __DIR__ . '/inc/verdiff.php';

    $data = botCommands() ?? [];
    $used = [];
    $commands = [];
    foreach ($data['modules'] ?? [] as $module) {
        foreach ($module['subModules'] ?? [] as $submodule) {
            $prefix = trim((string)($submodule['prefix'] ?? ''));
            foreach ($submodule['commands'] ?? [] as $command) {
                $name = (string)($command['name'] ?? '');
                $commands[] = [
                    'id' => commandAnchor($prefix, $name, $used),
                    'name' => trim($prefix . ' ' . $name),
                    'aliases' => array_values(array_diff(array_map('strval', $command['aliases'] ?? []), [$name])),
                    'description' => (string)($command['description'] ?? ''),
                    'module' => (string)($module['name'] ?? '')
                ];
            }
        }
    }

    $versions = [];
    foreach (versionEntries() as $entry)
        $versions[] = (string)$entry['version'];

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    echo json_encode([
        'prefix' => (string)($data['prefix'] ?? ''),
        'commands' => $commands,
        'versions' => $versions
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
