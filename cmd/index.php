<?php
    require __DIR__ . '/../inc/bot.php';
    require __DIR__ . '/../inc/meta.php';

    $state = botState();
    $data = botCommands();

    $prefix = $data['prefix'] ?? '';
    $modules = $data['modules'] ?? [];

    // texts from the API are escaped before they go into the page
    function e($text)
    {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }

    // two-digit numbers, like on the home page buttons
    function num($index)
    {
        return str_pad($index + 1, 2, '0', STR_PAD_LEFT);
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

    function moduleCount($module)
    {
        $count = 0;
        foreach ($module['subModules'] AS $submodule)
            $count += count($submodule['commands']);

        return $count;
    }

    $total = 0;
    foreach ($modules AS $module)
        $total += moduleCount($module);

    // The moderator and debug commands follow for the panel admins and the
    // bot's admins and devs (inc/auth.php); such a page is for this visitor only.
    $user = siteUser();
    siteRoles();
    $privateTotal = 0;
    if ($user !== null && canSeePrivateCommandsId($user['id'])) {
        foreach (botPrivateModules() AS $module) {
            $privateTotal += moduleCount($module);
            $modules[] = $module + ['private' => true];
        }
    }
    if (session_status() === PHP_SESSION_ACTIVE)
        session_write_close();
    if ($privateTotal)
        header('Cache-Control: private, no-store');

    // Address of a command on the page, e.g. #daily or #pw-daily with the module
    // prefix (commandSlug() in inc/bot.php); the same name twice gets -2, -3 and so on.
    $usedIds = [];
    function commandId($smprefix, $name)
    {
        global $usedIds;
        $id = commandSlug(trim($smprefix . ' ' . $name));

        $unique = $id;
        for ($i = 2; isset($usedIds[$unique]); $i++)
            $unique = $id . '-' . $i;
        $usedIds[$unique] = true;

        return $unique;
    }

    $statusText = [
        'online' => 'Bot działa',
        'idle' => !empty($state['issues'])
            ? 'Bot działa, ale ' . implode(', ', $state['issues'])
            : 'Bot działa, ale w ciągu ostatnich 24 h odpowiadał tylko w ' . str_replace('.', ',', $state['uptime']) . '% sprawdzeń',
        'offline' => 'Bot nie odpowiada' . botDownText($state),
        'maintenance' => 'Bot ma przerwę techniczną'
    ];
    $status = botInMaintenance(time()) ? 'maintenance' : $state['status'];

    // commands new or changed in the last two weeks get a mark, the latest change a line
    const MARK_DAYS = 14;
    $changes = botCommandChanges();
    $marks = [];
    foreach ($changes as $change) {
        if ($change['time'] < time() - MARK_DAYS * 86400)
            break;
        foreach (array_keys($change['changed']) as $key)
            $marks[$key] = $marks[$key] ?? 'changed';
        foreach ($change['added'] as $key)
            $marks[$key] = 'new';
    }
    $latest = $changes[0] ?? null;
    $latestParts = [];
    if ($latest) {
        if ($count = count($latest['added']))
            $latestParts[] = $count . ' ' . plural($count, 'nowe', 'nowe', 'nowych');
        if ($count = count($latest['changed']))
            $latestParts[] = $count . ' ' . plural($count, 'zmienione', 'zmienione', 'zmienionych');
        if ($count = count($latest['removed']))
            $latestParts[] = $count . ' ' . plural($count, 'usunięte', 'usunięte', 'usuniętych');
    }

include 'sanakan.head.html';

?>
    <header class="cmd-header">
      <div class="cmd-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title">Polecenia</h1>
      <div class="cmd-meta">
<?php if ($prefix != ''): ?>
        <span>Przedrostek <code><?=e($prefix)?></code></span>
<?php endif; ?>
<?php if ($total): ?>
        <span><?=$total?> <?=plural($total, 'polecenie', 'polecenia', 'poleceń')?></span>
<?php endif; ?>
<?php if ($privateTotal): ?>
        <span class="private-count" title="Widzą je tylko administratorzy">+ <?=$privateTotal?> moderatorskich i debug</span>
<?php endif; ?>
        <span class="state <?=e($status)?>"><?=e($statusText[$status])?></span>
      </div>
    </header>

<?php if (empty($modules)): ?>
    <p class="notice">Nie udało się pobrać listy poleceń. Spróbuj ponownie później.</p>
<?php else: ?>
<?php if ($latest): ?>
    <p class="changes-line"><span>Ostatnia zmiana w poleceniach <?=e(date('j.m.Y', $latest['time']))?>: <?=e(implode(', ', $latestParts))?></span><a href="zmiany/">Historia zmian &rarr;</a></p>
<?php endif; ?>
<?php if ($state['status'] == 'offline'): ?>
    <p class="notice"><?=$status === 'maintenance' ? 'Bot ma przerwę techniczną' : 'Bot teraz nie odpowiada'?>, poniżej jest ostatnia zapisana lista poleceń.</p>
<?php endif; ?>
    <div class="toolbar" id="toolbar">
      <label class="search hud-corners">
        <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="M15.5 15.5 21 21" /></svg>
        <input id="cmd-search" type="search" autocomplete="off" spellcheck="false" placeholder="Szukaj polecenia, aliasu lub opisu" aria-label="Szukaj polecenia" />
        <kbd aria-hidden="true" title="Naciśnij /, żeby szukać">/</kbd>
      </label>
      <nav class="module-chips" aria-label="Moduły">
<?php foreach ($modules AS $mi => $module): ?>
        <a href="#module-<?=$mi?>" data-module="<?=$mi?>"<?=empty($module['private']) ? '' : ' class="private" title="Polecenia moderatorskie i debug"'?>><i aria-hidden="true"><?=num($mi)?></i><?=e($module['name'])?><b class="chip-count"><?=moduleCount($module)?></b></a>
<?php endforeach; ?>
      </nav>
      <div class="search-info" id="search-info" aria-live="polite"></div>
    </div>
<?php endif; ?>

<?php foreach ($modules AS $mi => $module): ?>
    <section class="module<?=empty($module['private']) ? '' : ' private'?>" id="module-<?=$mi?>" data-module="<?=$mi?>">
      <h2 class="module-head"><i aria-hidden="true"><?=num($mi)?></i><?=e($module['name'])?><?php if (!empty($module['private'])): ?><small class="module-private" title="Polecenia moderatorskie i debug, widzą je tylko administratorzy"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="1.5" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>moderatorskie</small><?php endif; ?></h2>
<?php foreach ($module['subModules'] AS $submodule):
        $smprefix = $submodule['prefix'];
        if ($smprefix != '')
            $smprefix .= ' ';
?>
      <div class="submodule">
<?php if ($smprefix != ''): ?>
        <p class="submodule-prefix">
          Przedrostek modułu: <code><?=e($smprefix)?></code>
<?php if (!empty($submodule['prefixAliases'])): ?>
          <span class="submodule-aliases">aliasy:<?php foreach ($submodule['prefixAliases'] AS $pa): ?> <code><?=e($pa)?></code><?php endforeach; ?></span>
<?php endif; ?>
        </p>
<?php endif; ?>
<?php foreach ($submodule['commands'] AS $command):
        // the first alias is the name itself
        $aliases = array_values(array_filter($command['aliases'], function ($alias) use ($command) {
            return $alias !== $command['name'];
        }));
        $usage = trim($command['name'] . ' ' . $command['example']);
        $id = commandId($smprefix, $command['name']);
        $mark = $marks[trim($smprefix . $command['name'])] ?? null;
?>
        <article class="cmd" id="<?=e($id)?>">
          <div class="cmd-name">
            <a class="cmd-anchor" href="#<?=e($id)?>" title="Link do tego polecenia"><code class="cmd-main"><?=e($command['name'])?></code></a>
<?php if ($mark): ?>
            <a class="cmd-mark <?=$mark?>" href="zmiany/" title="<?=$mark === 'new' ? 'Nowe' : 'Zmienione'?> w ostatnich <?=MARK_DAYS?> dniach"><?=$mark === 'new' ? 'nowe' : 'zmienione'?></a>
<?php endif; ?>
            <button class="copy copy-link" type="button" data-anchor="<?=e($id)?>" hidden>Link</button>
<?php if ($aliases): ?>
            <div class="cmd-aliases"><?php foreach ($aliases AS $alias): ?><code><?=e($alias)?></code><?php endforeach; ?></div>
<?php endif; ?>
          </div>
          <div class="cmd-body">
            <p class="cmd-desc"><?=e($command['description'])?></p>
<?php if (!empty($command['attributes'])): ?>
            <ul class="cmd-params">
<?php foreach ($command['attributes'] AS $attr): ?>
              <li><?=e($attr['description'] ?? $attr['name'])?></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
          </div>
          <div class="cmd-example">
            <code><span class="cmd-prefix"><?=e($prefix . $smprefix)?></span><?=e($usage)?></code>
            <button class="copy" type="button" data-copy="<?=e($prefix . $smprefix . $usage)?>" hidden>Kopiuj</button>
          </div>
        </article>
<?php endforeach; ?>
      </div>
<?php endforeach; ?>
    </section>
<?php endforeach; ?>

    <p class="no-results" id="no-results" hidden>Brak pasujących poleceń.</p>

<?php

include 'sanakan.foot.html';
