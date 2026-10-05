<?php
    // History of the changes in the bot's commands: new, changed and removed
    // ones, logged by inc/bot.php whenever the command list from the API differs
    // from the one before.
    require __DIR__ . '/../../inc/bot.php';
    require __DIR__ . '/../../inc/meta.php';

    const CHANGES_SHOWN = 100;

    botState();
    $changes = array_slice(botCommandChanges(), 0, CHANGES_SHOWN);
    $since = botCommandsWatchedSince();
    $stored = json_decode((string)@file_get_contents(botFile('commands-index.json')), true);
    $index = $stored['commands'] ?? [];

    function e($text)
    {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }

    // Polish plural: 1 polecenie, 2-4 polecenia, 5+ poleceń (but 12-14 poleceń)
    function plural($n, $one, $few, $many)
    {
        if ($n == 1)
            return $one;

        $last = $n % 10;
        $lastTwo = $n % 100;
        return $last >= 2 && $last <= 4 && ($lastTwo < 12 || $lastTwo > 14) ? $few : $many;
    }

    // name and module of a command; a link to it on cmd/ while it is still there
    function commandName($key, $command, $link)
    {
        $name = '<code>' . e($key) . '</code>';
        if ($link)
            $name = '<a href="../#' . e(commandSlug($key)) . '">' . $name . '</a>';

        return $name . (isset($command['module']) && $command['module'] !== '' ? '<small>' . e($command['module']) . '</small>' : '');
    }

    function summary($change)
    {
        $parts = [];
        if ($count = count($change['added']))
            $parts[] = $count . ' ' . plural($count, 'nowe', 'nowe', 'nowych');
        if ($count = count($change['changed']))
            $parts[] = $count . ' ' . plural($count, 'zmienione', 'zmienione', 'zmienionych');
        if ($count = count($change['removed']))
            $parts[] = $count . ' ' . plural($count, 'usunięte', 'usunięte', 'usuniętych');

        return implode(', ', $parts);
    }
?>
<!DOCTYPE html>
<html lang="pl">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
<?=metaTags('Zmiany w poleceniach · Sanakan', 'Nowe, zmienione i usunięte polecenia bota Sanakan.', '/cmd/zmiany/')?>
  <meta name="author" content="Sniku" />
  <title>Zmiany w poleceniach &middot; Sanakan</title>
  <link rel="icon" href="../../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../../apple-touch-icon.png" />
  <link href="../../css/fonts.css?v=1" type="text/css" rel="stylesheet" />
  <link href="../../css/style.css?v=29" type="text/css" rel="stylesheet" />
  <link href="../style.css?v=11" type="text/css" rel="stylesheet" />
</head>

<body class="cmd-page">
  <main class="content">
    <header class="cmd-header">
      <div class="cmd-top">
        <a class="back hud-corners" href="../" title="Polecenia">&larr; Polecenia</a>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title">Zmiany</h1>
      <div class="cmd-meta">
        <span><?=$since ? 'Zapisywane same od ' . e(date('j.m.Y', $since)) : 'Zapisywanie jeszcze się nie zaczęło'?></span>
      </div>
    </header>

<?php if (!$changes): ?>
    <p class="no-results">Od tego czasu lista poleceń się nie zmieniła.</p>
<?php endif; ?>

<?php foreach ($changes as $change): ?>
    <section class="change">
      <h2 class="change-head"><?=e(date('j.m.Y', $change['time']))?> <small><?=e(date('H:i', $change['time']))?> &middot; <?=e(summary($change))?></small></h2>
      <ul class="change-list">
<?php foreach ($change['added'] as $key): ?>
        <li>
          <span class="change-kind added">nowe</span>
          <span class="change-name"><?=commandName($key, $index[$key] ?? [], isset($index[$key]))?></span>
          <span class="change-what"><?=e($index[$key]['description'] ?? '')?></span>
        </li>
<?php endforeach; ?>
<?php foreach ($change['changed'] as $key => $fields): ?>
        <li>
          <span class="change-kind changed">zmienione</span>
          <span class="change-name"><?=commandName((string)$key, $index[$key] ?? [], isset($index[$key]))?></span>
          <span class="change-what">
<?php foreach ($fields as $field => $values): ?>
            <p><b><?=e(COMMAND_FIELDS[$field] ?? $field)?>:</b> <?php if ($values[0] !== ''): ?><del><?=e($values[0])?></del> &rarr; <?php endif; ?><ins><?=$values[1] === '' ? '(brak)' : e($values[1])?></ins></p>
<?php endforeach; ?>
          </span>
        </li>
<?php endforeach; ?>
<?php foreach ($change['removed'] as $key): ?>
        <li>
          <span class="change-kind removed">usunięte</span>
          <span class="change-name"><?=commandName($key, $change['gone'][$key] ?? [], false)?></span>
          <span class="change-what"><?=e($change['gone'][$key]['description'] ?? '')?></span>
        </li>
<?php endforeach; ?>
      </ul>
    </section>
<?php endforeach; ?>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../../privacy/">Prywatność</a></footer>
</body>

</html>
