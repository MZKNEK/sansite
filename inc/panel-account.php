<?php
    // The profile of one account in the admin panel (admin/?konto=ID), for the
    // accounts of the panel only: who it is and its roles on the bot's server,
    // what it may open here, the requests from its addresses in the last 24
    // hours, the devices it is logged in on (each can be logged out), the
    // addresses it came from in the last ADDRESS_KEEP_DAYS days (whether one is
    // a scanner or blocked in Cloudflare, and the other accounts that came from
    // the same ones), its own folder in the gallery and how full it is, what it
    // did in the gallery, its requests for access, and
    // the history of what it changed and what was changed for it.
    // admin/index.php includes it after the login check and has the helpers;
    // $profile is ready before the page starts, profilePage() writes the cards.
    if (!function_exists('accountCell')) {
        http_response_code(404);
        exit;
    }

    const PROFILE_HISTORY_SHOWN = 60;
    const PROFILE_UPLOADS_SHOWN = 12;

    $profile = (function ($id) {
        $logins = readData('logins');
        $addresses = readData('addresses');
        $rolesEntry = readData('roles')[$id] ?? null;

        // the addresses of this account, the newest first, with the other accounts at them
        $own = $addresses[$id] ?? [];
        uasort($own, function ($a, $b) { return $b[1] <=> $a[1]; });
        $shared = [];
        foreach (array_keys($own) as $ip)
            foreach (array_keys(accountsAtAddress((string)$ip, $addresses)) as $other)
                if ((string)$other !== $id)
                    $shared[$other][] = (string)$ip;

        // what it may open: [label, yes, where from, list to add to or take from]
        $access = [];
        $access[] = ['Panel administratora', isPanelAdminId($id), isPanelAdminId($id) ? 'inc/config.php (PANEL_ADMINS)' : '', null];
        foreach (['galleryAdmins' => 'Admin galerii', 'galleryViewers' => 'Ogląda galerię', 'galleryUploaders' => 'Własny folder w galerii', 'galleryPrivate' => 'Prywatny folder w galerii', 'apiViewers' => 'Dokumentacja API'] as $list => $label) {
            $config = configList(ACCESS_LISTS[$list]);
            $entry = panelList($list)[$id] ?? null;
            if ($config === true)
                $access[] = [$label, true, 'każdy zalogowany (inc/config.php)', null];
            else if (in_array($id, $config, true))
                $access[] = [$label, true, 'inc/config.php', null];
            else if ($entry !== null)
                $access[] = [$label, true, 'panel' . (!empty($entry['added']) ? ', ' . date('d.m.Y', $entry['added']) : '')
                    . (!empty($entry['by']) ? ', nadał(a) ' . ($logins[$entry['by']]['name'] ?? $entry['by']) : '')
                    . (($entry['note'] ?? '') !== '' ? ': „' . $entry['note'] . '”' : ''), $list];
            else if ($list === 'apiViewers' && hasBotRole($id, API_ROLES))
                $access[] = [$label, true, 'przez rolę na serwerze bota', $list];
            else if ($list === 'galleryViewers' && isGalleryAdminId($id))
                $access[] = [$label, true, 'jako admin galerii', null];
            else if ($list === 'galleryPrivate' && isPanelAdminId($id))
                $access[] = [$label, true, 'jako właściciel strony (panel)', null];
            else
                $access[] = [$label, false, '', $list];
        }

        $requests = [];
        foreach (REQUEST_LABELS as $for => $label)
            if ($request = pendingRequest($for, $id))
                $requests[] = $request + ['for' => $for];

        // what it changed, and what others changed for it (its ID in the text);
        // its changes in the gallery counted, with the files it added
        [$history, $gallery, $uploads] = accountActivity($id, true, PROFILE_HISTORY_SHOWN, PROFILE_UPLOADS_SHOWN);

        // its folder in the gallery, also after the access was taken back
        $galleryBase = str_replace('\\', '/', dirname(__DIR__)) . '/i';
        $folder = userFolder($galleryBase, $id);

        $scanners = diagScanners();
        [$cfItems, $cfError] = cloudflareConfigured() ? cloudflareBlocked() : [null, null];
        $lastAddress = $own ? reset($own)[1] : 0;

        return [
            'id' => $id,
            'login' => $logins[$id] ?? null,
            'name' => $logins[$id]['name'] ?? 'nieznane konto',
            'roles' => $rolesEntry['roles'] ?? null,
            'rolesChecked' => $rolesEntry['checked'] ?? null,
            'seen' => max($lastAddress, $logins[$id]['last'] ?? 0),
            'loggedOut' => accountSessionsSince($id),
            'addresses' => $own,
            'shared' => $shared,
            'access' => $access,
            'requests' => $requests,
            'history' => $history,
            'traffic' => diagAccountTraffic($id),
            'devices' => accountDevices($id),
            'gallery' => $gallery,
            'uploads' => $uploads,
            'folder' => $folder,
            'folderUse' => $folder !== null ? treeUse($galleryBase . '/' . $folder) : [0, 0],
            'filesLimit' => userFilesLimit($id),
            'marks' => diagMarks($scanners, $cfItems),
            'cfError' => $cfError,
            'logins' => $logins
        ];
    })($profileId);

    function profilePage($p)
    {
        $id = $p['id'];
        $badge = roleBadge($p['roles']);
        $self = $id === (siteUser()['id'] ?? '');
        $mostTimes = $p['addresses'] ? max(array_column($p['addresses'], 2)) : 0;
        $thisDevice = sessionKey(session_id());
        $traffic = $p['traffic'];
        $busiest = max(1, max(array_column($traffic['hours'], 'n')));
        $galleryRoot = dirname(__DIR__) . '/i/';
?>
    <div class="panel-grid">
      <section class="card wide profile">
        <h2><i>01</i>Konto</h2>
        <div class="profile-head">
          <img src="<?=e($p['login']['avatar'] ?? 'https://cdn.discordapp.com/embed/avatars/' . (((int)$id >> 22) % 6) . '.png')?>" alt="" width="64" height="64" />
          <div>
            <b class="profile-name"><?=e($p['name'])?></b>
<?php if (!empty($p['login']['username'])): ?>
            <span class="muted">@<?=e($p['login']['username'])?></span>
<?php endif; ?>
            <code><?=e($id)?></code>
<?php if ($badge): ?>
            <span class="lv role-<?=e($badge['key'])?>" title="<?=e($badge['title'])?>">LV.<?=$badge['level']?> <?=e($badge['name'])?></span>
<?php endif; ?>
          </div>
        </div>
        <dl class="server">
          <dt>Role na serwerze bota</dt>
          <dd><?=$p['roles'] === null ? 'nieznane (bot jeszcze nie pytany o to konto)' : e(roleNames($p['roles']) ? implode(', ', roleNames($p['roles'])) : $badge['name']) . ($p['rolesChecked'] ? ' <span class="muted">(sprawdzone ' . e(ago($p['rolesChecked'])) . ')</span>' : '')?></dd>

          <dt>Logowania</dt>
          <dd><?=$p['login'] ? (int)($p['login']['count'] ?? 0) . '&times;, ostatnio ' . e(ago($p['login']['last'] ?? 0)) : 'nie ma go wśród ostatnich logowań'?></dd>

          <dt>Ostatnio widziane</dt>
          <dd><?=$p['seen'] ? e(ago($p['seen'])) : 'nigdy'?></dd>

          <dt>Sesje</dt>
          <dd>
            <?=$p['loggedOut'] ? 'wylogowane z panelu ' . e(ago($p['loggedOut'])) : 'bez wymuszonego wylogowania'?>
<?php if (!$self): ?>
            <button type="button" class="admin-btn small danger" data-action="logout-account" data-id="<?=e($id)?>" data-confirm="<?=e('Wylogować ' . $p['name'] . ' na wszystkich urządzeniach? Będzie musiał(a) zalogować się jeszcze raz.')?>">Wyloguj na wszystkich urządzeniach</button>
<?php endif; ?>
          </dd>
        </dl>
        <p class="hint status-more"><a href="./">&larr; Panel</a></p>
      </section>

      <section class="card wide">
        <h2><i>02</i>Dostępy</h2>
        <dl class="server">
<?php foreach ($p['access'] as [$label, $yes, $from, $list]): ?>
          <dt><?=e($label)?></dt>
          <dd>
            <?=$yes ? '<b class="profile-yes">tak</b>' . ($from !== '' ? ' <span class="muted">(' . e($from) . ')</span>' : '') : '<span class="muted">nie</span>'?>
<?php if ($list !== null && $yes && $from !== 'przez rolę na serwerze bota'): ?>
            <button type="button" class="admin-btn small danger" data-action="revoke" data-list="<?=e($list)?>" data-id="<?=e($id)?>" data-confirm="<?=e('Odebrać: ' . $label . '?')?>">Odbierz</button>
<?php elseif ($list !== null && !$yes): ?>
            <button type="button" class="admin-btn small" data-action="grant" data-list="<?=e($list)?>" data-id="<?=e($id)?>">Nadaj</button>
<?php endif; ?>
          </dd>
<?php endforeach; ?>
<?php if ($p['folder'] !== null || isGalleryUploaderId($id)): ?>
          <dt>Własny folder</dt>
          <dd>
            <?=$p['folder'] !== null ? '<a href="' . e(siteRoot() . 'i/' . folderUrl($p['folder'])) . '">' . e(galleryPath($p['folder'])) . '</a>' : '<span class="muted">powstanie przy pierwszym wejściu do galerii</span>'?>
            &middot; <?=$p['folderUse'][0]?> z <?=$p['filesLimit']?> <?=plural($p['filesLimit'], 'zdjęcia', 'zdjęć', 'zdjęć')?> &middot; <?=e(formatSize($p['folderUse'][1]))?> z <?=e(formatSize(USER_TOTAL_MAX_BYTES))?>
          </dd>
          <dt>Limit zdjęć</dt>
          <dd>
            <form class="grant upload-limit" data-action="upload-limit">
              <input type="hidden" name="id" value="<?=e($id)?>" />
              <input type="text" name="limit" inputmode="numeric" pattern="\d{1,4}" value="<?=$p['filesLimit']?>" aria-label="Limit zdjęć" required />
              <button type="submit" class="admin-btn small">Zapisz</button>
              <span class="muted">domyślnie <?=USER_FILES_DEFAULT?>, każde do <?=e(formatSize(USER_FILE_MAX_BYTES))?></span>
            </form>
          </dd>
<?php endif; ?>
<?php foreach ($p['requests'] as $request): ?>
          <dt>Prośba o dostęp do <?=e(REQUEST_LABELS[$request['for']])?></dt>
          <dd>
            <?=e(ago($request['time']))?><?=($request['note'] ?? '') !== '' ? ': „' . e($request['note']) . '”' : ''?>
            <button type="button" class="admin-btn small" data-action="request-dismiss" data-for="<?=e($request['for'])?>" data-id="<?=e($id)?>">Odrzuć</button>
          </dd>
<?php endforeach; ?>
        </dl>
      </section>

      <section class="card wide">
        <h2><i>03</i>Ruch</h2>
        <p class="hint">Zapytania z adresów tego konta w ostatnich 24 godzinach, z dziennika nginx. Z tego samego adresu może pytać też ktoś inny (dom, praca).</p>
        <div class="stats-summary">
          <span><b><?=formatCount($traffic['n'])?></b> <?=plural($traffic['n'], 'zapytanie', 'zapytania', 'zapytań')?></span>
          <span><b><?=formatCount($traffic['php'])?></b> do PHP</span>
          <span><b><?=formatCount(max(array_column($traffic['hours'], 'n')))?></b> najwięcej w godzinę</span>
        </div>
        <div class="bar">
          <div class="response-chart" aria-label="Zapytania z adresów konta w ostatnich 24 godzinach, po godzinie">
<?php foreach ($traffic['hours'] as $hour): ?>
            <span title="<?=e(date('H:i', $hour['from']) . '-' . date('H:i', $hour['from'] + 3600) . ': ' . formatCount($hour['n']) . ' zapytań, ' . formatCount($hour['php']) . ' do PHP')?>"><?php if ($hour['n']): ?><i style="height: <?=max(4, round(100 * $hour['n'] / $busiest))?>%"></i><?php endif; ?></span>
<?php endforeach; ?>
          </div>
          <div class="bar-ends"><span>24 h temu</span><span>teraz</span></div>
        </div>
<?php if ($traffic['paths']): ?>
        <div class="stats-grid">
          <div>
            <h3>Najczęściej otwierane</h3>
            <ul class="stat-list">
<?php foreach ($traffic['paths'] as $path => $n): ?>
              <li style="--share: <?=share($n, reset($traffic['paths']))?>"><span title="<?=e($path)?>"><?=e($path)?></span><span><?=formatCount($n)?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
        </div>
<?php endif; ?>
      </section>

      <section class="card wide">
        <h2><i>04</i>Zalogowane urządzenia</h2>
        <p class="hint">Sesje tego konta, które wciąż są na serwerze. Każdą można zakończyć osobno; konto zaloguje się tam jeszcze raz przez Discorda.</p>
<?php if (!$p['devices']): ?>
        <p class="nobody">Żadnych: konto nie jest nigdzie zalogowane albo jeszcze nie wchodziło od tej wersji strony.</p>
<?php else: ?>
        <ul class="devices">
<?php foreach ($p['devices'] as $key => [$login, $last, $ip, $cc, $agent]): ?>
          <li>
            <span class="device-name" title="<?=e($agent)?>"><?=e(deviceName($agent))?><?=(string)$key === $thisDevice ? ' <span class="role protected">to urządzenie</span>' : ''?></span>
            <span class="muted"><?=e($ip)?><?=$cc !== '' ? ' ' . e($cc) : ''?> &middot; zalogowane <?=e(date('d.m H:i', $login))?> &middot; ostatnio <?=e(ago($last))?></span>
<?php if ((string)$key !== $thisDevice): ?>
            <button type="button" class="admin-btn small danger" data-action="logout-session" data-id="<?=e($id)?>" data-session="<?=e($key)?>" data-confirm="<?=e('Wylogować ' . $p['name'] . ' na tym urządzeniu (' . deviceName($agent) . ')?')?>">Wyloguj</button>
<?php endif; ?>
          </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </section>

      <section class="card wide diag">
        <h2><i>05</i>Adresy IP</h2>
        <p class="hint">Adresy, z których konto korzystało w ostatnich <?=ADDRESS_KEEP_DAYS?> dniach, notowane przy wejściach zalogowanego konta co najwyżej co <?=ADDRESS_NOTE_EVERY / 60?> minut. Adresu konta panelu ani swojego obecnego nie da się zablokować.<?=$p['cfError'] !== null ? ' <b class="warn">' . e($p['cfError']) . '</b>' : ''?></p>
<?php if (!$p['addresses']): ?>
        <p class="nobody">Jeszcze żadnych: adresy są notowane od tej wersji strony, przy następnym wejściu konta.</p>
<?php else: ?>
        <ul class="diag-ips">
<?php foreach ($p['addresses'] as $ip => [$first, $last, $times, $cc, $ua]): $ip = (string)$ip; ?>
          <li style="--share: <?=share($times, $mostTimes)?>">
            <?=diagIpCell($ip, $cc, 'konto ' . $p['name'], $p['marks'])?>

            <span class="diag-count"><?=formatCount($times)?>&times;</span>
            <span class="diag-agent"><span><?=e(date('d.m H:i', $first))?><?=$last - $first >= 60 ? '–' . e(date(date('Y-m-d', $first) === date('Y-m-d', $last) ? 'H:i' : 'd.m H:i', $last)) : ''?> <span class="muted">(ostatnio <?=e(ago($last))?>)</span></span><span title="<?=e($ua)?>"><?=e($ua !== '' ? $ua : 'bez user agenta')?></span></span>
          </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
<?php if ($p['shared']): ?>

        <h3 class="diag-title">Inne konta z tych samych adresów</h3>
        <p class="hint">Ten sam adres (albo ta sama sieć /64 IPv6) to zwykle ten sam dom, praca albo drugie konto tej samej osoby.</p>
        <ul class="people">
<?php foreach ($p['shared'] as $other => $ips): ?>
          <li><?=accountCell($other, $p['logins'])?><span class="muted"><?=e(implode(', ', array_unique($ips)))?></span></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </section>

      <section class="card wide">
        <h2><i>06</i>Galeria</h2>
        <p class="hint">Co to konto zrobiło w galerii, z historii zmian (ostatnie <?=HISTORY_KEEP?> wpisów całej strony).</p>
<?php if (!array_sum($p['gallery'])): ?>
        <p class="nobody">Nic jeszcze nie zmieniło.</p>
<?php else: ?>
        <div class="stats-summary">
<?php foreach (GALLERY_ACTIONS as $action => $label): if (!$p['gallery'][$action]) continue; ?>
          <span><b><?=formatCount($p['gallery'][$action])?></b> <?=e($label)?></span>
<?php endforeach; ?>
        </div>
<?php if ($p['uploads']): ?>
        <div class="stats-grid">
          <div>
            <h3>Ostatnio dodane</h3>
            <ul class="stat-list">
<?php foreach ($p['uploads'] as $upload): $there = is_file($galleryRoot . $upload['rel']); ?>
              <li style="--share: 0"><?=$there ? '<a href="' . e(siteRoot() . 'i/' . fileUrl($upload['rel'])) . '" target="_blank" rel="noopener">' . e('i/' . $upload['rel']) . '</a>' : '<span class="muted" title="Plik przeniesiono albo usunięto">' . e('i/' . $upload['rel']) . '</span>'?><span><?=e(date('d.m.Y H:i', $upload['time']))?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
        </div>
<?php endif; ?>
<?php endif; ?>
      </section>

      <section class="card wide">
        <h2><i>07</i>Historia</h2>
        <p class="hint">Co to konto zmieniło w galerii i w panelu, i co inni zmienili dla niego.</p>
<?php if (!$p['history']): ?>
        <p class="nobody">Nic.</p>
<?php else: ?>
        <ol class="history">
<?php foreach ($p['history'] as $entry): ?>
          <li>
            <time datetime="<?=e(date('c', $entry['time'] ?? 0))?>"><?=e(date('d.m H:i', $entry['time'] ?? 0))?></time>
            <span class="history-who"><?=$entry['own'] || empty($entry['id']) ? e(($entry['name'] ?? '') ?: ($entry['id'] ?? '')) : '<a href="?konto=' . e($entry['id']) . '">' . e(($entry['name'] ?? '') ?: $entry['id']) . '</a>'?></span>
            <span class="history-text"><?=e($entry['text'] ?? '')?></span>
          </li>
<?php endforeach; ?>
        </ol>
<?php endif; ?>
      </section>
    </div>
<?php
    }
