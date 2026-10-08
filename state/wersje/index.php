<?php
    // Version history of the bot for anyone: every version it reported and when
    // it started, each with the changes from the bot's repository (verdiff.md,
    // inc/verdiff.php) rendered on the site. state/ links to it from the version
    // line. A version opens with ?v=<version>; the changes never leave the site.
    require __DIR__ . '/../../inc/bot.php';
    require __DIR__ . '/../../inc/verdiff.php';
    require __DIR__ . '/../../inc/markdown.php';
    require __DIR__ . '/../../inc/meta.php';

    $entries = versionEntries();
    $current = botHealth()['version'] ?? ($entries[0]['version'] ?? null);
    $wanted = isset($_GET['v']) && is_string($_GET['v']) ? trim($_GET['v']) : '';

    $shown = null;
    if ($wanted !== '') {
        foreach ($entries as $entry) {
            if (strcasecmp($entry['version'], $wanted) === 0) {
                $shown = $entry;
                break;
            }
        }
        if ($shown === null && ($section = verdiffChanges($wanted)) !== null)
            $shown = ['version' => $section['version'], 'since' => null, 'date' => $section['date'], 'commit' => $section['commit'], 'changes' => true];
    }
    $section = $shown !== null && $shown['changes'] ? verdiffChanges($shown['version']) : null;
    $changes = $section !== null ? markdownToHtml($section['changes'], mentionUsers()) : '';

    // the line under the title: when the version was introduced, its commit
    $introParts = [];
    if ($shown !== null) {
        if ($shown['since'])
            $introParts[] = 'wprowadzona ' . e(date('j.m.Y H:i', $shown['since'])) . ' (' . e(duration(time() - $shown['since'])) . ' temu)';
        else if ($shown['date'])
            $introParts[] = 'data w changelogu: ' . e($shown['date']);
        if ($shown['commit'])
            $introParts[] = 'commit <code>' . e($shown['commit']) . '</code>';
    }
    $intro = implode(' &middot; ', $introParts);
?>
<!DOCTYPE html>
<html lang="pl">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
<?=metaTags($shown !== null ? 'Wersja ' . $shown['version'] : 'Wersje', $shown !== null ? 'Zmiany w wersji ' . $shown['version'] . ' bota Sanakan.' : 'Historia wersji bota Sanakan.', '/state/wersje/', 'status')?>
  <title><?=$shown !== null ? 'Wersja ' . e($shown['version']) : 'Wersje'?> &middot; Sanakan</title>
  <link rel="icon" href="../../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../../apple-touch-icon.png" />
  <link href="../../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../../css/style.css?v=ec855414d1" type="text/css" rel="stylesheet" />
  <link href="../../css/explorer.css?v=ed18f56732" type="text/css" rel="stylesheet" />
  <link href="../../css/status.css?v=c478e7d175" type="text/css" rel="stylesheet" />
  <script src="../../js/hud.js?v=0effb1151f"></script>
</head>

<body class="state-page">
  <main class="content state-content">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Status bota">&larr; Status</a>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title"><?=$shown !== null ? 'Wersja ' . e($shown['version']) : 'Wersje'?></h1>
<?php if ($intro !== ''): ?>
      <p class="version-intro"><?=$intro?></p>
<?php endif; ?>
    </header>

<?php if ($shown !== null): ?>
    <section class="card state-card">
      <div class="markdown">
<?=$changes !== '' ? $changes : '<p class="incidents-none">Brak opisu zmian dla tej wersji w repozytorium bota.</p>'?>
      </div>
    </section>
    <p class="version-return"><a class="back hud-corners" href="./" title="Wszystkie wersje">&larr; Wszystkie wersje</a></p>
<?php else: ?>
    <section class="card state-card">
      <h2><i>+</i>Historia wersji</h2>
<?php if (!$entries): ?>
      <p class="incidents-none">Jeszcze brak wersji. Pojawią się, gdy bot zgłosi pierwszą.</p>
<?php else: ?>
      <ul class="version-list">
<?php foreach ($entries as $entry): $isCurrent = $current !== null && strcasecmp($entry['version'], $current) === 0; ?>
        <li class="version-entry<?=$isCurrent ? ' current' : ''?>">
          <span class="version-name"><?=e($entry['version'])?></span>
          <span class="version-when"><?php if ($entry['since']): ?>od <?=e(date('j.m.Y H:i', $entry['since']))?> &middot; <?=e(duration(time() - $entry['since']))?><?php elseif ($entry['date']): ?><?=e($entry['date'])?><?php else: ?>—<?php endif; ?></span>
<?php if ($entry['changes']): ?>
          <a class="version-diff hud-corners" href="?v=<?=e(rawurlencode($entry['version']))?>">Zobacz zmiany</a>
<?php else: ?>
          <span class="version-none">brak opisu</span>
<?php endif; ?>
        </li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>
    <p class="state-note">Wersje i zmiany z repozytorium bota.</p>
<?php endif; ?>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../../privacy/">Prywatność</a></footer>
  <script src="../../js/netsphere.js?v=1c8be049a6"></script>
</body>

</html>
