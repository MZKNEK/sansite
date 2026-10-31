<?php
    // The server: availability, the machine, PHP, cron, backups.
    // A view of admin/index.php, included where it stands, with its variables.
?>
      <h2 class="panel-section" data-section="serwer" data-label="Serwer">Serwer</h2>

      <?php $diagCard = sprintf('%02d', $number++); $diagSub = 0; $diagLetter = function () use (&$diagSub, $diagCard) { return $diagCard . chr(64 + ++$diagSub); }; ?>
      <section class="card wide diag" data-sum="<?=count($diagEpisodes)?>">
        <h2><i><?=$diagCard?></i>Dostępność strony</h2>
        <p class="hint">Co 10 sekund serwer pyta stronę przez Cloudflare, tak jak odwiedzający, i bezpośrednio u siebie, z pominięciem Cloudflare, a dla porównania wiki. Obok zapisuje ruch z dziennika nginx. Gdy strona nie działa tylko przez Cloudflare (522), połączenia nie dochodzą do serwera. Gdy nie działa też na serwerze, zatyka się nginx albo PHP. Pomiary z <?=DIAG_KEEP_DAYS?> dni, pokazane 24 godziny.</p>
<?php if (!$diagRounds && $diagSince > $now - 86400 && $now - $diagSince < 300): ?>
        <p class="nobody">Awarie wyczyszczone o <?=e(date('H:i:s', $diagSince))?>. Pierwsze nowe pomiary pojawią się w ciągu minuty, odśwież wtedy stronę.</p>
<?php elseif (!$diagRounds): ?>
        <p class="nobody">Jeszcze nie ma pomiarów<?=$diagSince > $now - 86400 ? ' od wyczyszczenia awarii o ' . e(date('H:i', $diagSince)) . ', a powinny być co 10 sekund: sprawdź crona inc/check-site.php' : ''?>. Ustawienie serwera opisuje lista niżej.</p>
<?php else:
        $ngNow = $diagLast['ng'] ?? null;
        $fpmNow = $diagLast['fpm'] ?? null;
        $tcpNow = $diagLast['tcp'] ?? [];
?>
<?=diagBar('Przez Cloudflare', $diagRounds, ['pub', 'pubphp'])?>
<?=diagBar('Na serwerze, bez Cloudflare', $diagRounds, ['loc', 'locphp'])?>
<?=diagBar('Wiki przez Cloudflare', $diagRounds, ['wiki'])?>
        <div class="bar-ends"><span>24 h temu</span><span>teraz</span></div>
        <div class="timeline-legend">
          <span><i class="ok"></i>odpowiadała</span>
          <span><i class="warn"></i>wolno</span>
          <span><i class="fail"></i>bez odpowiedzi</span>
          <span><i class="none"></i>brak pomiarów</span>
        </div>

<?=diagTrafficChart($diagRounds)?>

        <div class="stats-summary diag-now" title="Ostatni pomiar, <?=e(date('H:i:s', $diagLast['t']))?>">
<?php if ($ngNow): ?>
          <span><b><?=$ngNow['a']?></b> <?=plural($ngNow['a'], 'połączenie', 'połączenia', 'połączeń')?> nginx</span>
<?php endif; ?>
<?php if ($fpmNow): ?>
          <span><b><?=$fpmNow['act']?>/<?=$fpmNow['tot']?></b> zajętych PHP-FPM<?=$fpmNow['q'] ? ', <b class="warn">' . $fpmNow['q'] . '</b> w kolejce' : ''?></span>
          <span><b><?=formatCount($fpmNow['mcr'])?></b> &times; pula pełna <span class="muted">od startu PHP-FPM</span></span>
<?php endif; ?>
<?php if (isset($tcpNow['lo'])): ?>
          <span><b><?=formatCount($tcpNow['lo'])?></b> odrzuconych połączeń <span class="muted">od startu serwera</span></span>
<?php endif; ?>
<?php if (isset($tcpNow['ct'])): ?>
          <span><b><?=formatCount($tcpNow['ct'])?></b> z <?=formatCount($tcpNow['ctm'])?> w conntrack</span>
<?php endif; ?>
        </div>

        <h3 class="diag-title"><i><?=$diagLetter()?></i><?=$diagSince > $now - 86400 ? 'Awarie od ' . e(date(date('Y-m-d', $diagSince) === date('Y-m-d') ? 'H:i' : 'j.m H:i', $diagSince)) : 'Awarie w ostatnich 24 godzinach'?><?=$diagEpisodes ? ' <span class="muted">' . count($diagEpisodes) . '</span>' : ''?></h3>
<?php if (!$diagEpisodes): ?>
        <p class="nobody">Strona cały czas odpowiadała.</p>
<?php else: ?>
        <div class="diag-episodes">
<?php foreach (array_slice($diagEpisodes, 0, 20) as $i => $episode): ?>
          <details class="diag-episode<?=$episode['ongoing'] ? ' ongoing' : ''?>"<?=$i === 0 ? ' open' : ''?>>
            <summary>
              <time><?=e(date(date('Y-m-d', $episode['from']) === date('Y-m-d') ? 'H:i:s' : 'j.m H:i:s', $episode['from']))?></time>
              <b><?=$episode['ongoing'] ? 'trwa' : e(diagSeconds($episode['to'] - $episode['from']))?></b>
              <span><?=e(diagVerdict($episode))?></span>
            </summary>
            <dl class="server">
<?php foreach (DIAG_PROBE_NAMES as $name => [$label]): if (!$episode['answers'][$name]) continue; ?>
              <dt><?=e(ucfirst($label))?></dt>
              <dd><?=e(implode(', ', array_map(function ($text, $times) { return $text . ($times > 1 ? ' ×' . $times : ''); }, array_keys($episode['answers'][$name]), $episode['answers'][$name])))?></dd>
<?php endforeach; ?>
              <dt>Połączenia TCP</dt>
              <dd><?=$episode['overflows'] === null ? 'brak danych' : ($episode['overflows'] ? '<b class="warn">' . formatCount($episode['overflows']) . ' odrzuconych</b> (pełna kolejka)' : 'żadne nie odrzucone')?><?=$episode['queue'] !== null ? ' · kolejka do ' . $episode['queue'] . ' · półotwartych do ' . $episode['syn'] : ''?></dd>
              <dt>nginx</dt>
              <dd><?=$episode['nginxActive'] === null ? 'brak danych' : 'do ' . $episode['nginxActive'] . ' połączeń' . ($episode['nginxUnhandled'] ? ' · <b class="warn">' . formatCount($episode['nginxUnhandled']) . ' nieobsłużonych</b> (worker_connections)' : '')?></dd>
              <dt>PHP-FPM</dt>
              <dd><?=$episode['fpmActive'] === null ? 'brak danych' : 'zajętych do ' . $episode['fpmActive'] . ' z ' . $episode['fpmTotal'] . ($episode['fpmQueue'] ? ' · <b class="warn">do ' . $episode['fpmQueue'] . ' w kolejce</b>' : '') . ($episode['fpmFull'] ? ' · <b class="warn">pula pełna ' . $episode['fpmFull'] . '×</b>' : '')?></dd>
              <dt>Obciążenie</dt>
              <dd><?=decimal($episode['load'], 2)?></dd>
              <dt>Ruch</dt>
              <dd><?=formatCount($episode['traffic']['n'])?> zapytań od minuty przed awarią do jej końca, w tym <?=formatCount($episode['traffic']['php'])?> do PHP · najwięcej <?=formatCount($episode['traffic']['peak'])?> w 10 s · błędy 4xx <?=formatCount($episode['traffic']['s4'])?>, 5xx <?=formatCount($episode['traffic']['s5'])?></dd>
            </dl>
            <?=diagIpList($episode['traffic']['ips'], $diagMarks)?>

          </details>
<?php endforeach; ?>
        </div>
        <p class="trash-all"><button type="button" class="admin-btn small danger" data-action="diag-clear" data-confirm="Wyczyścić awarie? Karta dostępności będzie liczyć od teraz: awarie, pasek 24 godzin i wykresy sprzed tej chwili przestaną się na niej liczyć (np. po przeniesieniu na nowy serwer).">Wyczyść awarie</button></p>
<?php endif; ?>

        <h3 class="diag-title"><i><?=$diagLetter()?></i>Ostatnia godzina <span class="muted"><?=formatCount($diagHour['n'])?> zapytań, <?=formatCount($diagHour['php'])?> do PHP, najwięcej <?=formatCount($diagHour['peak'])?> w 10 s</span></h3>
        <?=diagIpList($diagHour['ips'], $diagMarks)?>

<?php if ($diagHour['paths']): ?>
        <div class="stats-grid">
          <div>
            <h3>Najczęstsze adresy stron</h3>
            <ul class="stat-list">
<?php foreach ($diagHour['paths'] as [$path, $n]): ?>
              <li style="--share: <?=share($n, $diagHour['paths'][0][1])?>"><span title="<?=e($path)?>"><?=e($path)?></span><span><?=formatCount($n)?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
        </div>
<?php endif; ?>
<?php endif; ?>

        <h3 class="diag-title"><i><?=$diagLetter()?></i>Skanery w ostatnich <?=DIAG_KEEP_DAYS?> dniach<?=$diagScanners ? ' <span class="muted">' . count($diagScanners) . ' ' . plural(count($diagScanners), 'adres', 'adresy', 'adresów') . ', ' . formatCount(array_sum(array_column($diagScanners, 'n'))) . ' zapytań</span>' : ''?></h3>
        <p class="hint">Adresy, które pytały o to, czego na tej stronie nie ma (<code>/.env</code>, <code>/wp-*</code>, obce pliki <code>.php</code>, kopie zapasowe), albo przedstawiły się jako narzędzie do skanowania. Liczone są wszystkie ich zapytania od pierwszego takiego.</p>
<?php if (cloudflareConfigured()): $autoOn = autoBlockEnabled(); ?>
        <form class="auto-block" data-action="auto-block">
          <label class="admin-check"><input type="checkbox" name="on" value="1"<?=$autoOn ? ' checked' : ''?> /> Blokuj skanery w Cloudflare automatycznie</label>
          <button type="submit" class="admin-btn small">Zapisz</button>
        </form>
        <p class="hint">Co minutę blokowany jest adres, który w ciągu doby zapytał o co najmniej <?=AUTO_BLOCK_PROBES?> różne ścieżki, o które nikt tu nie pyta; sam user agent skanera nie wystarcza. Nigdy: adresy, z których logowały się konta, Cloudflare i adresy lokalne, prawdziwe roboty wyszukiwarek i znane skanery badawcze (Google, Bing, Censys, Shodan i inne, sprawdzone w DNS w obie strony, więc podszywający się pod nie odpadają). Adres odblokowany ręcznie nie wraca na listę sam. Automatyczne blokady znikają po <?=AUTO_BLOCK_DAYS?> dniach.</p>
<?php if ($autoBlock): $autoBlocked = $autoBlock['blocked'] ?? []; $autoTrusted = $autoBlock['trusted'] ?? []; ?>
        <p class="hint">
          Zablokowane automatycznie: <b><?=count($autoBlocked)?></b><?=$autoBlocked ? ', ostatnio ' . e(ago(max(array_column($autoBlocked, 0)))) : ''?>
<?php if ($autoTrusted): ?>
          &middot; pominięte jako zaufane: <span title="<?=e(implode(', ', array_map(function ($target, $entry) { return $target . ' ' . $entry[0]; }, array_keys($autoTrusted), $autoTrusted)))?>"><?=count($autoTrusted)?> (<?=e(implode(', ', array_slice(array_unique(array_map(function ($entry) { return preg_replace('/^.*?([^.]+\.[^.]+)$/', '$1', $entry[0]); }, $autoTrusted)), 0, 4)))?>)</span>
<?php endif; ?>
<?php if (!empty($autoBlock['error']) && $autoBlock['error'][0] > time() - 86400): ?>
          &middot; <b class="warn">ostatni błąd <?=e(ago($autoBlock['error'][0]))?>: <?=e($autoBlock['error'][1])?></b>
<?php endif; ?>
        </p>
<?php endif; ?>
<?php endif; ?>
<?php if (!$diagScanners): ?>
        <p class="nobody">Żadnych skanerów.</p>
<?php else: ?>
        <div class="diag-scroll">
          <?=diagScannerList(array_slice($diagScanners, 0, 50, true), $diagMarks)?>

        </div>
<?php endif; ?>
<?php if ($cfItems): ?>

        <h3 class="diag-title"><i><?=$diagLetter()?></i>Zablokowane w Cloudflare <span class="muted"><?=count($cfItems)?> na liście <?=e(CLOUDFLARE_LIST)?></span></h3>
        <div class="diag-scroll">
          <ul class="diag-blocked">
<?php foreach ($cfItems as $item): ?>
            <li>
              <code><?=e($item['ip'])?></code>
              <span><?=e($item['comment'])?><?=$item['created'] ? ' <span class="muted">' . e(date('j.m.Y H:i', $item['created'])) . '</span>' : ''?></span>
              <button type="button" class="admin-btn small" data-action="cf-unblock" data-item="<?=e($item['id'])?>" data-ip="<?=e($item['ip'])?>" data-confirm="<?=e('Odblokować ' . $item['ip'] . '?')?>">Odblokuj</button>
            </li>
<?php endforeach; ?>
          </ul>
        </div>
<?php endif; ?>
<?php if ($diagPages): ?>

        <h3 class="diag-title"><i><?=$diagLetter()?></i>Czas PHP według stron <span class="muted">24 godziny, najwięcej czasu w sumie u góry</span></h3>
        <ul class="stat-list diag-pages">
<?php foreach ($diagPages as [$path, $n, $average, $slowest, $total]): ?>
          <li style="--share: <?=share($total, $diagPages[0][4])?>"><span title="<?=e($path)?>"><?=e($path)?></span><span><?=formatCount($n)?> &times; średnio <?=e(milliseconds($average))?> &middot; najdłużej <?=e(milliseconds($slowest))?></span></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
<?php if ($diagSlow): ?>

        <h3 class="diag-title"><i><?=$diagLetter()?></i>Wolne zapytania PHP <span class="muted">ostatnie z dziennika slowlog</span></h3>
        <div class="diag-scroll">
<?php foreach ($diagSlow as $entry): ?>
          <pre class="diag-slow"><?=e($entry)?></pre>
<?php endforeach; ?>
        </div>
<?php endif; ?>

        <h3 class="diag-title"><i><?=$diagLetter()?></i>Ustawienie</h3>
<?php
        $nginxProbe = $diagLast['p']['nginx'] ?? null;
        $fpmProbe = $diagLast['p']['fpm'] ?? null;
        $logReadable = is_readable(diagAccessLog());
        $cloudflareInLog = false;
        foreach ($diagHour['ips'] as $ip)
            $cloudflareInLog = $cloudflareInLog || diagIsCloudflare($ip['ip']);
?>
        <dl class="server">
          <dt>Pomiary</dt>
          <dd><?=!diagAvailable()
              ? '<b class="warn">brak rozszerzenia curl</b>: <code>apt-get install -y ' . PHP_PACKAGE . '-curl &amp;&amp; systemctl restart ' . PHP_PACKAGE . '-fpm</code>'
              : ($diagLast && time() - $diagLast['t'] < 180
                  ? 'działają: ostatni ' . e(ago($diagLast['t']))
                  : '<b class="warn">nie działają</b>' . ($diagLast ? ': ostatni ' . e(ago($diagLast['t'])) : '') . '. Zadanie cron: <code>' . e($diagCron) . '</code>')?></dd>

          <dt>Dziennik nginx</dt>
          <dd><?=!$logReadable
              ? '<b class="warn">nie można czytać</b> <code>' . e(diagAccessLog()) . '</code>: <code>server/nginx/sanakan-log.conf</code> do <code>/etc/nginx/conf.d/</code> i nowy <code>sanakan.conf</code> (docs/server.md)'
              : 'czytany: <code>' . e(diagAccessLog()) . '</code>' . ($cloudflareInLog ? ' · <b class="warn">pokazuje adresy Cloudflare</b>: brak <code>set_real_ip_from</code> z <code>sanakan.conf</code>' : '')?></dd>

          <dt>Stan nginx</dt>
          <dd><?=$nginxProbe === null ? 'jeszcze nie sprawdzony' : (!empty($diagLast['ng']) ? 'czytany' : '<b class="warn">brak</b> (' . e($nginxProbe[0] === 200 ? 'odpowiedź to nie stub_status' : diagProbeText($nginxProbe)) . '): <code>location = /nginx-status</code> z <code>sanakan.conf</code>')?></dd>

          <dt>Stan PHP-FPM</dt>
          <dd><?=$fpmProbe === null ? 'jeszcze nie sprawdzony' : (!empty($diagLast['fpm']) ? 'czytany' : '<b class="warn">brak</b> (' . e($fpmProbe[0] === 200 ? 'odpowiedź to nie status puli' : diagProbeText($fpmProbe)) . '): <code>pm.status_path = /fpm-status</code> w <code>/etc/php/8.1/fpm/pool.d/www.conf</code> i <code>location = /fpm-status</code> z <code>sanakan.conf</code>')?></dd>

          <dt>Wolne zapytania</dt>
          <dd><?=$diagSlow === null
              ? '<b class="warn">nie można czytać</b> <code>' . e(diagSlowLog()) . '</code>: <code>request_slowlog_timeout = 5s</code> i <code>slowlog</code> w puli PHP-FPM (docs/server.md)'
              : 'dziennik <code>' . e(diagSlowLog()) . '</code>' . ($diagSlow ? '' : ', pusty')?></dd>

          <dt>Blokowanie w Cloudflare</dt>
          <dd><?=!cloudflareConfigured()
              ? 'wyłączone: <code>CLOUDFLARE_API_TOKEN</code>, <code>CLOUDFLARE_ACCOUNT_ID</code> i <code>CLOUDFLARE_LIST</code> w <code>inc/config.php</code> (docs/server.md)'
              : ($cfError !== null ? '<b class="warn">' . e($cfError) . '</b>' : 'lista <code>' . e(CLOUDFLARE_LIST) . '</code>, ' . count($cfItems) . ' ' . plural(count($cfItems), 'pozycja', 'pozycje', 'pozycji'))?></dd>
        </dl>
      </section>

<?php
$serverSum = [];
if ($cpuNow)
    $serverSum[] = decimal($cpuNow['busy']) . '% CPU';
if ($system['memory'])
    $serverSum[] = formatSize($system['memory']['total'] - $system['memory']['available']) . ' RAM';
?>
      <section class="card wide" data-sum="<?=$serverSum ? implode(' · ', $serverSum) : 'brak danych'?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Zasoby serwera</h2>
        <p class="hint">Pamięć i procesy z chwili otwarcia panelu, odśwież stronę po nowy. Procesor i wykresy są z pomiarów w tle co <?=DIAG_INTERVAL?> s (karta „Dostępność strony”): linia to średnia z 5 minut, a jaśniejsze pasmo nad nią przy procesorze sięga najbardziej zajętych 10 sekund z tego czasu. <?=e($system['name'])?><?=$system['uptime'] !== null ? ' · serwer działa od ' . e(duration($system['uptime'])) : ''?>.</p>
<?php if ($cpuNow === null && $system['memory'] === null): ?>
        <p class="nobody">Brak danych: serwer nie ma <code>/proc</code> (to nie Linux) albo PHP nie może go czytać (<code>open_basedir</code>).</p>
<?php else:
        $cores = $system['cpuInfo']['cores'];
        $load = $system['load'];
        $memory = $system['memory'];
        $opcache = $system['opcache'];
?>
        <div class="meters">
<?php if ($cpuNow): $cpu = $cpuNow; ?>
          <?=meter('Procesor', $cpu['busy'], 100, decimal($cpu['busy']) . '%',
              ($load ? 'obciążenie ' . decimal($load[0], 2) . ' · ' . decimal($load[1], 2) . ' · ' . decimal($load[2], 2) . ' (1, 5, 15 min)'
                  . ($cores && $load[1] > $cores ? ' <b class="warn">więcej niż rdzeni</b>' : '') . '<br />' : '')
              . ($cores ? $cores . ' ' . plural($cores, 'rdzeń', 'rdzenie', 'rdzeni') : '')
              . ($system['cpuInfo']['model'] ? ' · ' . e($system['cpuInfo']['model']) : '') . '<br />'
              . 'czekanie na dysk ' . decimal($cpu['iowait']) . '%' . ($cpu['iowait'] >= 10 ? ' <b class="warn">dysk nie nadąża</b>' : '')
              . ' · zabrane przez hosta ' . decimal($cpu['steal']) . '%' . ($cpu['steal'] >= 10 ? ' <b class="warn">host jest przeciążony</b>' : ''))?>

<?php endif; ?>
<?php if ($memory): $used = $memory['total'] - $memory['available']; ?>
          <?=meter('Pamięć RAM', $used, $memory['total'], e(formatSize($used)) . ' z ' . e(formatSize($memory['total'])),
              'wolne do użycia ' . e(formatSize($memory['available'])) . '<br />w tym cache dysku ' . e(formatSize($memory['cached'])) . ', które system odda, gdy zabraknie')?>

<?php $swapUsed = $memory['swapTotal'] - $memory['swapFree']; ?>
          <?=$memory['swapTotal'] > 0
              ? meter('Swap', $swapUsed, $memory['swapTotal'], e(formatSize($swapUsed)) . ' z ' . e(formatSize($memory['swapTotal'])),
                  $swapUsed > $memory['swapTotal'] * 0.5 ? '<b class="warn">mocno używany</b>: brakuje RAM-u, wszystko zwalnia' : 'pamięć na dysku, gdy brakuje RAM-u')
              : meter('Swap', 0, 1, 'brak', 'bez swapu, gdy zabraknie RAM-u, system zabija procesy (OOM)')?>

<?php endif; ?>
<?php if ($opcache): ?>
          <?=meter('OPcache', $opcache['used'], $opcache['size'], e(formatSize($opcache['used'])) . ' z ' . e(formatSize($opcache['size'])),
              ($opcache['hits'] !== null ? 'trafienia ' . decimal($opcache['hits']) . '% · ' : '') . $opcache['scripts'] . ' ' . plural($opcache['scripts'], 'skrypt', 'skrypty', 'skryptów')
              . ($opcache['full'] ? ' · <b class="warn">pełny</b>, zwiększ <code>opcache.memory_consumption</code>' : ''))?>

<?php else: ?>
          <?=meter('OPcache', 0, 1, 'wyłączony', 'PHP kompiluje każdy skrypt przy każdym wejściu; włącz <code>opcache.enable</code>')?>

<?php endif; ?>
        </div>
<?php endif; ?>
<?php if ($diagRounds): $usage = diagUsage($diagRounds, $system['memory']['total'] ?? 0); ?>
        <div class="usage">
<?=cpuChart($usage)?>
<?php if ($system['memory']): ?>
<?=memoryChart($usage, $system['memory']['total'])?>
<?php endif; ?>
        </div>
<?php endif; ?>
        <div class="stats-grid">
<?php if ($system['processes'] && !empty($memory)): ?>
          <div>
            <h3>Najwięcej pamięci</h3>
            <ul class="stat-list">
<?php foreach ($system['processes'] as $name => $program): ?>
              <li style="--share: <?=share($program['bytes'], $memory['total'])?>"><span><?=e($name)?><?=$program['count'] > 1 ? ' <span class="muted">&times;' . $program['count'] . '</span>' : ''?></span><span><?=e(formatSize($program['bytes']))?> &middot; <?=decimal(100 * $program['bytes'] / $memory['total'])?>%</span></li>
<?php endforeach; ?>
            </ul>
          </div>
<?php endif; ?>
          <div>
            <h3>PHP</h3>
            <ul class="stat-list">
              <li style="--share: 0"><span>limit pamięci skryptu</span><span><?=e(ini_get('memory_limit'))?></span></li>
              <li style="--share: 0"><span>limit czasu skryptu</span><span><?=(int)ini_get('max_execution_time')?> s</span></li>
              <li style="--share: 0"><span>ten widok zużył</span><span><?=e(formatSize(memory_get_peak_usage(true)))?></span></li>
              <li style="--share: 0"><span>pomiar procesora</span><span>w tle, co <?=DIAG_INTERVAL?> s</span></li>
            </ul>
          </div>
        </div>
      </section>

<?php
$serverProblems = 0;
foreach ([hasGd(), canConvertToWebp(), canConvertGifToWebp(), canThumbVideo(), canConvertVideo(), canConvertHeif(), canZip(), !$cronLate, botAppKey() !== '', botHeartbeatSecret() !== '', dataWritable()] as $ok)
    if (!$ok)
        $serverProblems++;
?>
      <section class="card wide" data-state="<?=$serverProblems ? 'warn' : 'ok'?>" data-sum="<?=$serverProblems ? $serverProblems . ' ' . plural($serverProblems, 'problem', 'problemy', 'problemów') : 'wszystko jest'?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Serwer</h2>
        <dl class="server">
          <dt>PHP</dt>
          <dd><?=e(PHP_VERSION)?></dd>

          <dt>Miniatury (GD)</dt>
          <dd><?=hasGd() ? 'włączone' . (function_exists('imagewebp') ? ', WebP' : ', PNG') : '<b class="warn">GD wyłączone</b>: duże pliki nie mają podglądów'?></dd>

          <dt>PNG/JPG → WebP</dt>
          <dd><?=!canConvertToWebp() ? '<b class="warn">GD nie zapisuje WebP</b>: obrazy zostają w swoim formacie' : (findTool('cwebp') ? 'cwebp: ' . e(findTool('cwebp')) : '<b class="warn">brak cwebp</b>: zapisuje GD, z szumem kolorów przy liniach. Instalacja: <code>apt-get install -y webp</code>')?></dd>

          <dt>GIF → WebP</dt>
          <dd><?=canConvertGifToWebp() ? 'gif2webp: ' . e(findTool('gif2webp')) . (findTool('webpmux') ? '' : ' <b class="warn">(bez webpmux miniatury animowanych WebP się nie zrobią)</b>') : '<b class="warn">brak gif2webp</b>: GIF-y zostają GIF-ami. Instalacja: <code>apt-get install -y webp</code>'?></dd>

          <dt>Miniatury filmów</dt>
          <dd><?=canThumbVideo() ? 'ffmpeg: ' . e(findTool('ffmpeg')) : '<b class="warn">brak ffmpeg</b>: filmy w galerii nie mają miniatur, kafelek wczytuje cały film. Instalacja: <code>apt-get install -y ffmpeg</code>'?></dd>

          <dt>MP4 → WebM</dt>
          <dd><?=canConvertVideo() ? 'ffmpeg: ' . e(findTool('ffmpeg')) : '<b class="warn">brak ffmpeg</b>: MP4 zostaje MP4. Instalacja: <code>apt-get install -y ffmpeg</code>'?></dd>

          <dt>HEIC/HEIF → WebP</dt>
          <dd><?php $heif = findTool('magick') ?: findTool('convert') ?: findTool('ffmpeg'); ?>
<?=canConvertHeif() ? 'obraz zapisuje ' . e(basename($heif)) . ': ' . e($heif) : '<b class="warn">brak imagemagick i ffmpeg</b>: zdjęcia HEIC z telefonu nie zostaną przyjęte. Instalacja: <code>apt-get install -y imagemagick</code>'?></dd>

          <dt>Kolejka konwersji</dt>
          <dd><?php $mediaSummary = mediaJobsSummary(mediaJobs()); ?>
            <?=$mediaSummary !== '' ? e($mediaSummary) : 'pusta'?> <span class="muted">(cron uruchamia ją co minutę)</span>
            <button type="button" class="admin-btn small" data-action="media-worker">Uruchom teraz</button>
          </dd>

          <dt>AVIF</dt>
          <dd><?=function_exists('imagecreatefromavif') ? 'czyta GD' : (findTool('magick') || findTool('convert') || findTool('ffmpeg') ? 'przez imagemagick lub ffmpeg' : '<b class="warn">brak obsługi AVIF</b>: plik się wyświetli, ale bez miniatury. Instalacja: <code>apt-get install -y imagemagick</code>')?></dd>

          <dt>Pobieranie ZIP</dt>
          <dd><?=canZip() ? 'włączone' : '<b class="warn">brak modułu ZIP</b>: przyciski pobierania są ukryte. Instalacja: <code>apt-get install -y ' . PHP_PACKAGE . '-zip &amp;&amp; systemctl restart ' . PHP_PACKAGE . '-fpm</code>'?></dd>

          <dt>Limit wysyłania</dt>
          <dd><?=e(formatSize(uploadLimit()))?> <span class="muted">(PHP; nginx ma osobny client_max_body_size)</span></dd>

          <dt>Auto-sprawdzanie</dt>
          <dd><?=$cronLate
              ? '<b class="warn">nie działa</b>: ' . ($cronLast === null ? 'cron jeszcze ani razu nie sprawdził bota' : 'ostatnio ' . e(ago($cronLast))) . '. Zadanie cron: <code>' . e($cronCommand) . '</code>'
              : 'działa: ostatnio ' . e(ago($cronLast)) . ', ' . $autoChecks . ' ' . plural($autoChecks, 'sprawdzenie', 'sprawdzenia', 'sprawdzeń') . ' w ostatniej godzinie'?></dd>

          <dt>Cache miniatur</dt>
          <dd>
            <?=$thumbFiles?> <?=plural($thumbFiles, 'plik', 'pliki', 'plików')?>, <?=e(formatSize($thumbBytes))?>
            <button type="button" class="admin-btn small" data-action="clear-thumbs" data-confirm="Usunąć wszystkie miniatury? Utworzą się od nowa przy oglądaniu galerii.">Wyczyść</button>
          </dd>

          <dt>Specyfikacja API</dt>
          <dd>
            <?=is_file($specFile) ? 'pobrana ' . e(ago(filemtime($specFile))) : 'jeszcze nie pobrana'?>
            <button type="button" class="admin-btn small" data-action="refresh-spec">Odśwież</button>
          </dd>

          <dt>Grafiki bota</dt>
          <dd><?php $pw = pwStatus(); ?>
<?=$pw['files'] === 0 && $pw['checked'] === null
    ? '<b class="warn">jeszcze nie pobrane</b>: cron ściąga je z repozytorium bota (' . e(PW_REPO) . ') do <code>inc/data/pw/</code>, Skalpelatory i Profilator biorą je z <code>/pw/</code>'
    : $pw['files'] . ' ' . plural($pw['files'], 'plik', 'pliki', 'plików')
        . ($pw['synced'] ? ', zgodne z repozytorium bota od ' . e(ago($pw['synced'])) : '')
        . ($pw['pending'] ? ', <b>' . $pw['pending'] . ' czeka na pobranie</b>' : '')
        . ($pw['checked'] ? ' <span class="muted">(sprawdzone ' . e(ago($pw['checked'])) . ')</span>' : '')
        . ($pw['error'] ? '; <b class="warn">' . e($pw['error']) . '</b>' : '')?>
            <button type="button" class="admin-btn small" data-action="pw-sync" title="Zapytaj GitHuba od razu, bez czekania na cron">Pobierz teraz</button>
          </dd>

          <dt>Klucz API bota</dt>
          <dd><?=botAppKey() === ''
              ? '<b class="warn">brak BOT_APP_KEY</b> w <code>inc/config.php</code>: bez niego strona nie zna ról z serwera bota i nie pokazuje poleceń moderatorskich'
              : 'ustawiony; role znane dla ' . count($knownRoles) . ' ' . plural(count($knownRoles), 'konta', 'kont', 'kont') . ', polecenia moderatorskie '
                  . ($privateTime ? 'pobrane ' . e(ago($privateTime)) : '<b class="warn">jeszcze nie pobrane</b> (klucz musi mieć uprawnienie Info)')?></dd>

          <dt>Heartbeat bota</dt>
          <dd><?=botHeartbeatSecret() === ''
              ? '<b class="warn">brak BOT_HEARTBEAT_SECRET</b> w <code>inc/config.php</code>: strona sama pyta API bota co minutę, a gdy API jest nieosiągalne, bot wygląda na wyłączonego'
              : ($heartbeatLast === null
                  ? '<b class="warn">bot jeszcze nic nie wysłał</b> na <code>/alive/</code> (Heartbeat w jego Config.json, ten sam sekret)'
                  : (time() - $heartbeatLast < BOT_HEARTBEAT_FRESH ? 'działa' : '<b class="warn">nie przychodzi</b>, strona pyta API bota') . ': ostatni ' . e(ago($heartbeatLast))
                      . (botAddresses() ? '; adres bota (chroniony przed blokadą): ' . e(implode(', ', array_keys(botAddresses()))) : ''))?></dd>

          <dt>Zapis danych</dt>
          <dd><?=dataWritable()
              ? 'inc/data: ' . e(formatSize($dataRoom['bytes'])) . ' (kosz ' . e(formatSize($dataRoom['trash'])) . ', podglądy WebP ' . e(formatSize($dataRoom['webp'])) . ')'
              : '<b class="warn">brak prawa zapisu</b>: ' . e(dataError())?></dd>

          <dt>Wersja strony</dt>
          <dd><?php if ($deployed): ?><a href="<?=e(REPO_URL . '/commit/' . $deployed['hash'])?>" target="_blank" rel="noopener"><code><?=e(substr($deployed['hash'], 0, 7))?></code></a><?=$deployed['subject'] !== '' ? ' ' . e($deployed['subject']) : ''?> <span class="muted">(<?=$deployed['date'] ? 'commit z ' . e(date('j.m.Y', $deployed['date'])) . ', ' : ''?>wdrożone <?=e(ago($deployed['at']))?>)</span><?php else: ?>brak informacji <span class="muted">(strona nie była wdrażana przez deploy.sh)</span><?php endif; ?></dd>

          <dt>Kopia danych</dt>
          <dd>
<?php if (canZip()): ?>
            <form class="backup" method="post" action="./">
              <input type="hidden" name="csrf" value="<?=e($csrf)?>" />
              <input type="hidden" name="action" value="backup" />
              <label class="admin-check"><input type="checkbox" name="gallery" value="1" /> z galerią (<?=e(formatSize($stats['bytes']))?>)</label>
              <button type="submit" class="admin-btn small">Pobierz ZIP</button>
            </form>
            <span class="muted">inc/data: <?=e(formatSize($dataBytes))?> (dostępy, historia, statusy, awarie, kosz). Bez inc/config.php, w którym jest sekret aplikacji Discord.</span>
<?php else: ?>
            <b class="warn">brak modułu ZIP</b>: <code>apt-get install -y <?=PHP_PACKAGE?>-zip &amp;&amp; systemctl restart <?=PHP_PACKAGE?>-fpm</code>
<?php endif; ?>
          </dd>
        </dl>
      </section>
