<?php
    require __DIR__ . '/../inc/bot.php';

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

    $statusText = [
        'online' => 'Bot działa',
        'idle' => 'Bot działa, ale w ciągu ostatnich 24 h odpowiadał tylko w ' . str_replace('.', ',', $state['uptime']) . '% sprawdzeń',
        'offline' => 'Bot teraz nie odpowiada'
    ];

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
        <span class="state <?=e($state['status'])?>"><?=e($statusText[$state['status']])?></span>
      </div>
    </header>

<?php if (empty($modules)): ?>
    <p class="notice">Nie udało się pobrać listy poleceń. Spróbuj ponownie później.</p>
<?php else: ?>
<?php if ($state['status'] == 'offline'): ?>
    <p class="notice">Bot teraz nie odpowiada, poniżej jest ostatnia zapisana lista poleceń.</p>
<?php endif; ?>
    <div class="toolbar" id="toolbar">
      <label class="search hud-corners">
        <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="M15.5 15.5 21 21" /></svg>
        <input id="cmd-search" type="search" autocomplete="off" spellcheck="false" placeholder="Szukaj polecenia, aliasu lub opisu" aria-label="Szukaj polecenia" />
        <kbd aria-hidden="true" title="Naciśnij /, żeby szukać">/</kbd>
      </label>
      <nav class="module-chips" aria-label="Moduły">
<?php foreach ($modules AS $mi => $module): ?>
        <a href="#module-<?=$mi?>" data-module="<?=$mi?>"><i aria-hidden="true"><?=num($mi)?></i><?=e($module['name'])?><b class="chip-count"><?=moduleCount($module)?></b></a>
<?php endforeach; ?>
      </nav>
      <div class="search-info" id="search-info" aria-live="polite"></div>
    </div>
<?php endif; ?>

<?php foreach ($modules AS $mi => $module): ?>
    <section class="module" id="module-<?=$mi?>" data-module="<?=$mi?>">
      <h2 class="module-head"><i aria-hidden="true"><?=num($mi)?></i><?=e($module['name'])?></h2>
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
?>
        <article class="cmd">
          <div class="cmd-name">
            <code class="cmd-main"><?=e($command['name'])?></code>
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
