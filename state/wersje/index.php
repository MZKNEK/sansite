<?php
    // Version history of the bot for anyone: every version it reported and when
    // it started, each with the changes from the bot's repository (verdiff.md,
    // inc/verdiff.php) rendered on the site. state/ links to it from the version
    // line. A version opens with ?v=<version>; the changes never leave the site.
    // The list is in series (1.4.10.x), the newest open, with a search box over
    // the versions and their changes (js/versions.js); a version's page links
    // the previous and the next one, also on the arrow keys.
    require __DIR__ . '/../../inc/bot.php';
    require __DIR__ . '/../../inc/verdiff.php';
    require __DIR__ . '/../../inc/markdown.php';
    require __DIR__ . '/../../inc/meta.php';

    $user = siteUser();
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

    // the versions either side of the one shown, for stepping through them
    $around = $shown !== null ? versionNeighbours($entries, $shown['version']) : ['older' => null, 'newer' => null];

    // The list: each version's first change as its summary and every change as
    // the text the search box looks in, when it ended and how long it ran (until
    // the next, newer version started; the newest runs on and keeps counting
    // up), then in runs of a series
    $groups = [];
    if ($shown === null) {
        $sections = verdiffSections();
        $newerSince = null;
        foreach ($entries as &$entry) {
            $items = isset($sections[verdiffKey($entry['version'])]) ? verdiffItems($sections[verdiffKey($entry['version'])]['changes']) : [];
            $plain = array_values(array_filter($items, function ($item) { return !$item[1]; })) ?: $items;
            $entry['summary'] = $plain ? $plain[0][0] : '';
            $entry['more'] = max(0, count($items) - 1);
            $entry['text'] = lower($entry['version'] . ' ' . implode(' ', array_column($items, 0)));
            $entry['ran'] = null;
            $entry['until'] = null;
            if ($entry['since']) {
                $entry['ran'] = max(0, ($newerSince ?? time()) - $entry['since']);
                $entry['until'] = $newerSince;
                $newerSince = $entry['since'];
            }
        }
        unset($entry);
        $groups = versionGroups($entries);
    }

    // the link preview picture (og.php?p=wersje): one version's own, or the
    // history's; the time in its address makes Discord fetch it again
    $image = SITE_URL . '/og.php?p=wersje' . ($shown !== null ? '&v=' . rawurlencode($shown['version']) : '') . '&t=' . intdiv(time(), BOT_COMMANDS_TTL);
?>
<!DOCTYPE html>
<html lang="pl"<?=hudHtmlAttributes($user)?>>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
<?=metaTags($shown !== null ? 'Wersja ' . $shown['version'] : 'Wersje', $shown !== null ? 'Zmiany w wersji ' . $shown['version'] . ' bota Sanakan.' : 'Historia wersji bota Sanakan.', '/state/wersje/', null, [$image, 1200, 630])?>
  <title><?=$shown !== null ? 'Wersja ' . e($shown['version']) : 'Wersje'?> &middot; Sanakan</title>
  <link rel="icon" href="../../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../../apple-touch-icon.png" />
  <link href="../../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../../css/style.css?v=08d6706c22" type="text/css" rel="stylesheet" />
  <link href="../../css/explorer.css?v=ed18f56732" type="text/css" rel="stylesheet" />
  <link href="../../css/status.css?v=095b66f9b8" type="text/css" rel="stylesheet" />
<?php if ($around['older']): ?>
  <link rel="prev" href="?v=<?=e(rawurlencode($around['older']['version']))?>" />
<?php endif; ?>
<?php if ($around['newer']): ?>
  <link rel="next" href="?v=<?=e(rawurlencode($around['newer']['version']))?>" />
<?php endif; ?>
  <script src="../../js/hud.js?v=2894733f39"></script>
  <script src="../../js/versions.js?v=2fa8a60efb" defer></script>
</head>

<body class="state-page">
  <main class="content state-content">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Status bota">&larr; Status</a>
<?php if ($user): ?>
        <?=accountMenuHtml($user, siteRoles(), $_SERVER['REQUEST_URI'] ?? '')?>
<?php endif; ?>
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
    <nav class="version-steps" aria-label="Inne wersje">
<?php if ($around['older']): ?>
      <a class="version-step older hud-corners" href="?v=<?=e(rawurlencode($around['older']['version']))?>" rel="prev" title="Poprzednia wersja (strzałka w lewo)"><small>&larr; Poprzednia</small><b><?=e($around['older']['version'])?></b></a>
<?php else: ?>
      <span class="version-step older empty"></span>
<?php endif; ?>
      <a class="version-step all" href="./" title="Wszystkie wersje">Wszystkie wersje</a>
<?php if ($around['newer']): ?>
      <a class="version-step newer hud-corners" href="?v=<?=e(rawurlencode($around['newer']['version']))?>" rel="next" title="Następna wersja (strzałka w prawo)"><small>Następna &rarr;</small><b><?=e($around['newer']['version'])?></b></a>
<?php else: ?>
      <span class="version-step newer empty"></span>
<?php endif; ?>
    </nav>
<?php else: ?>
    <section class="card state-card">
      <h2><i>+</i>Historia wersji</h2>
<?php if (!$entries): ?>
      <p class="incidents-none">Jeszcze brak wersji. Pojawią się, gdy bot zgłosi pierwszą.</p>
<?php else: ?>
      <label class="search version-search hud-corners">
        <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="M15.5 15.5 21 21" /></svg>
        <input id="version-search" type="search" autocomplete="off" spellcheck="false" placeholder="Szukaj wersji lub zmiany" aria-label="Szukaj wersji lub zmiany" />
        <kbd aria-hidden="true" title="Naciśnij /, żeby szukać">/</kbd>
      </label>
      <div class="search-info" id="version-search-info" aria-live="polite"></div>
<?php foreach ($groups as $g => $group): $first = $group['entries'][0]; $last = end($group['entries']); $count = count($group['entries']); ?>
<?php
        // the days the series covers: from its oldest version to its newest
        $from = versionDate($last);
        $to = versionDate($first);
        $from = $from !== null ? preg_replace('/ \d+:\d+$/', '', $from) : null;
        $to = $to !== null ? preg_replace('/ \d+:\d+$/', '', $to) : null;
        $span = $from !== null && $to !== null && $from !== $to ? $from . ' – ' . $to : ($to ?? $from);
?>
      <details class="version-group"<?=$g === 0 ? ' open' : ''?>>
        <summary>
          <span class="version-series"><?=e($group['series'])?><i>.x</i></span>
          <span class="version-count"><?=$count?> <?=plural($count, 'wersja', 'wersje', 'wersji')?></span>
          <span class="version-span"><?=$span !== null ? e($span) : ''?></span>
        </summary>
        <ul class="version-list">
<?php foreach ($group['entries'] as $entry): $isCurrent = $current !== null && strcasecmp($entry['version'], $current) === 0; $date = versionDate($entry); ?>
          <li class="version-entry<?=$isCurrent ? ' current' : ''?>" data-q="<?=e($entry['text'])?>">
<?php if ($entry['changes']): ?>
            <a class="version-row" href="?v=<?=e(rawurlencode($entry['version']))?>">
<?php else: ?>
            <div class="version-row">
<?php endif; ?>
              <span class="version-name"><?=e($entry['version'])?><?php if ($isCurrent): ?> <em class="version-now">teraz</em><?php endif; ?></span>
              <span class="version-summary"><?php if ($entry['summary'] !== ''): ?><?=e($entry['summary'])?><?php if ($entry['more']): ?> <small>+<?=$entry['more']?></small><?php endif; ?><?php elseif ($entry['changes']): ?><span class="version-none">bez listy zmian</span><?php else: ?><span class="version-none">brak opisu</span><?php endif; ?></span>
              <span class="version-when"><span class="version-from"><?=$date !== null ? e($date) : '—'?></span><?php if ($entry['since']): ?> <span class="version-until">&ndash; <?=$entry['until'] !== null ? e(versionUntil($entry['since'], $entry['until'])) : 'teraz'?></span><?php endif; ?><?php if ($entry['ran'] !== null): ?><small><?=$entry['until'] === null ? 'działa' : 'działała'?> <?=e(duration($entry['ran']))?></small><?php endif; ?></span>
<?=$entry['changes'] ? "            </a>
" : "            </div>
"?>
          </li>
<?php endforeach; ?>
        </ul>
      </details>
<?php endforeach; ?>
      <p class="incidents-none" id="version-search-none" hidden>Żadna wersja nie pasuje.</p>
<?php endif; ?>
    </section>
    <p class="state-note">Wersje i zmiany z repozytorium bota.</p>
<?php endif; ?>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../../state/">Status</a><i aria-hidden="true">&middot;</i><a href="../../privacy/">Prywatność</a></footer>
  <script src="../../js/account.js?v=51972de250"></script>
  <script src="../../js/netsphere.js?v=1c8be049a6"></script>
</body>

</html>
