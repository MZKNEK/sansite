<?php
    // The gallery: its trash, shared links, numbers and history.
    // A view of admin/index.php, included where it stands, with its variables.
?>
      <h2 class="panel-section" data-section="galeria" data-label="Galeria">Galeria</h2>

<?php
          $usageSnapshot = readData('gallery-usage');
          $usageRows = $usageSnapshot['accounts'] ?? [];
          $quotaPolicy = galleryLimitDefaults();
          $quotaDefaults = $quotaPolicy['values'];
          $quotaAccess = readData('access');
          $quotaOverrides = $quotaAccess['galleryLimitOverrides'] ?? [];
          $quotaImpact = [];
          $overCount = 0;
          foreach ($usageRows as $accountId => &$row) {
              $overrides = $quotaOverrides[$accountId]['values'] ?? [];
              if (isset($quotaAccess['uploadLimits'][$accountId])) $overrides['files'] = $quotaAccess['uploadLimits'][$accountId];
              $limits = $overrides + $quotaDefaults;
              $quotaImpact[] = ['usage' => $row['usage']['values'], 'overrides' => $overrides];
              $row['ratio'] = 0;
              foreach ($row['usage']['values'] as $key => $used) $row['ratio'] = max($row['ratio'], $used / $limits[$key]);
              if ($row['ratio'] > 1) $overCount++;
          }
          unset($row);
          uasort($usageRows, function ($a, $b) { return $b['ratio'] <=> $a['ratio']; });
          $usagePages = max(1, (int)ceil(count($usageRows) / 25));
          $usagePage = max(1, min($usagePages, (int)($_GET['usagePage'] ?? 1)));
?>
      <section class="card wide" data-sum="<?=$quotaDefaults['files']?> plików · <?=e(formatSize($quotaDefaults['totalBytes']))?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Limity folderów kont</h2>
        <p class="hint">Domyślne limity własnych folderów kont<?=$quotaPolicy['revision'] === 0 ? ' (na razie wartości bazowe aplikacji)' : ''?>. Dotyczą pól, których konto nie ma ustawionych po swojemu w profilu; obniżenie nie usuwa danych. Administrator panelu pomija je przy uploadzie, ochrona dekoderów działa zawsze.</p>
        <?php galleryLimitsForm(); ?>
        <p class="hint" id="quota-impact">Brak raportu zużycia — wpływ obniżenia jest nieznany.</p>
        <div class="stats-summary quota-transport">
          <span><b><?=e(ini_get('upload_max_filesize'))?></b> plik w PHP</span>
          <span><b><?=e(ini_get('post_max_size'))?></b> całe żądanie</span>
          <span><b><?=e(formatSize(effectiveUploadLimit()))?></b> da się wysłać</span>
          <span><b><?=defined('GALLERY_PROXY_BODY_BYTES') ? e(formatSize(GALLERY_PROXY_BODY_BYTES)) : '?'?></b> proxy</span>
        </div>
        <p class="hint">„Da się wysłać” to limit PHP po odjęciu 1 MiB na resztę formularza; wyższy limit pliku w panelu wymaga podniesienia limitów nginx i PHP. Przydziały kont nie rezerwują dysku (orientacyjny budżet galerii 25 GB, stan dysku w „Galerii w liczbach”).</p>
      </section>

      <section class="card wide" data-sum="<?=count($usageRows)?> <?=plural(count($usageRows), 'konto', 'konta', 'kont')?><?=$overCount ? ' · ' . $overCount . ' ponad' : ''?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Zużycie kont</h2>
        <script type="application/json" id="quota-impact-data"><?=json_encode($quotaImpact, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?></script>
        <p class="hint">Najbliżej limitu na górze; pasek to zajęta część najciaśniejszego z limitów konta. Liczy maintenance w tle, porcjami<?=isset($usageSnapshot['time']) ? ', ostatnio ' . e(ago($usageSnapshot['time'])) : ''?>.<?=$overCount ? ' <b class="warn">Ponad limit: ' . $overCount . '.</b>' : ''?></p>
<?php if (!$usageRows): ?>
        <p class="nobody"><?=isset($usageSnapshot['time']) ? 'Żadne konto nie ma jeszcze folderu.' : 'Brak raportu; maintenance policzy zużycie przy najbliższym przebiegu.'?></p>
<?php else: ?>
        <ul class="stat-list quota-usage">
<?php foreach (array_slice($usageRows, ($usagePage - 1) * 25, 25, true) as $accountId => $row): $values = $row['usage']['values']; ?>
          <li<?=$row['ratio'] > 1 ? ' class="over"' : ''?> style="--share: <?=min(100, round(100 * $row['ratio'], 1))?>%"><a href="?konto=<?=e($accountId)?>"><?=e($logins[$accountId]['name'] ?? $accountId)?></a><span><?=formatCount($values['files'])?> <?=plural($values['files'], 'plik', 'pliki', 'plików')?> &middot; <?=e(formatSize($values['totalBytes']))?> &middot; kosz <?=e(formatSize($values['trashBytes']))?> &middot; <b><?=round(100 * $row['ratio'])?>%</b></span></li>
<?php endforeach; ?>
        </ul>
<?php if ($usagePages > 1): ?>
        <p class="quota-pages">
<?php if ($usagePage > 1): ?>
          <a class="admin-btn small" href="?usagePage=<?=$usagePage - 1?>#sec-galeria">Poprzednie</a>
<?php endif; ?>
          <span class="muted">strona <?=$usagePage?> z <?=$usagePages?></span>
<?php if ($usagePage < $usagePages): ?>
          <a class="admin-btn small" href="?usagePage=<?=$usagePage + 1?>#sec-galeria">Następne</a>
<?php endif; ?>
        </p>
<?php endif; ?>
<?php endif; ?>
      </section>

      <section class="card wide" data-sum="<?=count($trash)?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Kosz</h2>
        <?php foreach ($maintenance['last']['problems'] ?? [] as $problem): ?><p class="warn"><?=e($problem)?></p><?php endforeach; ?>
        <?php if (isset($maintenance['tried'])): ?><p class="hint">Ostatnia próba: <?=e(ago($maintenance['tried']))?></p><?php endif; ?>
        <p class="hint">Usunięte w galerii pliki i foldery leżą tu <?=TRASH_DAYS?> dni, potem znikają same. Przywrócone wracają do swojego folderu.
          Porządki: <?=isset($maintenance['succeeded']) ? 'ostatnio ' . e(ago((int)$maintenance['succeeded'])) : 'jeszcze nie ruszyły'?><?php
          $backlog = (int)($maintenance['last']['backlog'] ?? 0); if ($backlog): ?>, zaległe <?=$backlog?> <?=plural($backlog, 'element', 'elementy', 'elementów')?><?php endif; ?>.</p>
<?php if (!$trash): ?>
        <p class="nobody">Kosz jest pusty.</p>
<?php else: ?>
        <div class="trash trash-list" id="trash-list" data-keep-scroll>
<?php foreach ($trash as $id => $item):
        $daysLeft = max(0, (int)ceil((($item['deleted'] ?? 0) + TRASH_DAYS * 86400 - time()) / 86400));
        $kind = !empty($item['folder']) ? 'folder' : (isImage($item['name']) ? 'image' : (isVideo($item['name']) ? 'video' : null));
?>
          <div class="trash-row">
            <span class="trash-name">
              <b><?=e($item['name'])?><?=!empty($item['folder']) ? '/' : ''?></b>
              <code><?=e(galleryPath($item['from']))?></code>
            </span>
            <span class="trash-info"><?=e(formatSize($item['size'] ?? 0))?> &middot; usunięte <?=e(ago($item['deleted'] ?? 0))?><?=empty($item['by']) ? '' : ' przez ' . e($item['by'])?> &middot; zostało <?=$daysLeft?> <?=plural($daysLeft, 'dzień', 'dni', 'dni')?><?=empty($item['hidden']) ? '' : ' &middot; <b>użytkownik usunął je ze swojego kosza</b>'?></span>
            <span class="login-actions">
<?php if ($kind): ?>
              <button type="button" class="admin-btn small trash-peek" data-item="<?=e($id)?>" data-kind="<?=$kind?>" aria-expanded="false">Podgląd</button>
<?php endif; ?>
              <button type="button" class="admin-btn small" data-action="restore" data-item="<?=e($id)?>">Przywróć</button>
              <button type="button" class="admin-btn small danger" data-action="trash-delete" data-item="<?=e($id)?>" data-confirm="Usunąć na zawsze <?=e(galleryPath($item['from']))?>? Tego nie da się cofnąć.">Usuń na zawsze</button>
            </span>
<?php if ($kind): ?>
            <div class="trash-preview" hidden></div>
<?php endif; ?>
          </div>
<?php endforeach; ?>
        </div>
        <p class="trash-all"><button type="button" class="admin-btn small danger" data-action="trash-empty" data-confirm="Usunąć na zawsze wszystko z kosza (<?=e(countLabel(count($trash)))?>)? Tego nie da się cofnąć.">Opróżnij kosz</button></p>
<?php endif; ?>
      </section>

      <section class="card wide" data-sum="<?=count($shares)?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Udostępnione linki</h2>
        <p class="hint">Foldery galerii udostępnione linkiem: każdy, kto go ma, ogląda je i pobiera bez logowania. Tworzy się je w galerii przyciskiem „Udostępnij” w folderze.</p>
<?php if (!$shares): ?>
        <p class="nobody">Żaden folder nie jest udostępniony.</p>
<?php else: ?>
        <div class="trash">
<?php foreach ($shares as $token => $share): $shareUrl = SITE_URL . $root . 'i/?s=' . $token; $shared = resolvePath($galleryDir, $share['rel'], true); ?>
          <div class="trash-row">
            <span class="trash-name">
              <a href="<?=e($root . 'i/' . folderUrl($shared[1] ?? $share['rel']))?>"><b><?=e(displayPath($shared[1] ?? $share['rel']))?></b></a>
              <code class="share-link"><?=e($shareUrl)?></code>
            </span>
            <span class="trash-info">od <?=e(ago($share['created']))?><?=($share['name'] ?? '') !== '' ? ', ' . e($share['name']) : ''?> &middot; <?=$share['expires'] === null ? 'bez końca' : 'do ' . e(date('j.m.Y H:i', $share['expires']))?><?=$shared ? '' : ' &middot; <b class="warn">folderu już nie ma</b>'?></span>
            <span class="login-actions">
              <button type="button" class="admin-btn small danger" data-action="share-revoke" data-token="<?=e($token)?>" data-confirm="<?=e('Wyłączyć link do ' . displayPath($shared[1] ?? $share['rel']) . '? Kto go ma, przestanie widzieć folder.')?>">Wyłącz</button>
            </span>
          </div>
<?php endforeach; ?>
        </div>
<?php endif; ?>
      </section>

      <section class="card wide" data-sum="<?=$stats['files']?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Galeria w liczbach</h2>
        <p class="hint">Policzone <?=e(ago($statsAgo))?>; odświeża się w tle raz na godzinę (cron), więc otwarcie panelu nie czeka na przejście galerii.
          <button type="button" class="admin-btn small" data-action="refresh-stats" data-confirm="Przeliczyć galerię od nowa? Przy dużej galerii to chwilę zajmie.">Przelicz teraz</button></p>
        <div class="stats-summary">
          <span><b><?=$stats['files']?></b> <?=plural($stats['files'], 'plik', 'pliki', 'plików')?></span>
          <span><b><?=e(formatSize($stats['bytes']))?></b> razem</span>
          <span><b><?=count($stats['folders'])?></b> <?=plural(count($stats['folders']), 'folder', 'foldery', 'folderów')?> w i/</span>
        </div>
<?php if ($diskTotal): $diskUsed = $diskTotal - $diskFree; $diskLow = $diskFree < $diskTotal * 0.1; ?>
        <div class="disk<?=$diskLow ? ' low' : ''?>">
          <div class="bar-head"><span>Dysk serwera<?=$diskLow ? ': <b class="warn">mało miejsca</b>' : ''?></span><b>wolne <?=e(formatSize($diskFree))?> z <?=e(formatSize($diskTotal))?></b></div>
          <div class="disk-bar" title="Zajęte <?=e(formatSize($diskUsed))?>, w tym galeria <?=e(formatSize($stats['bytes']))?>">
            <span class="gallery" style="width: <?=share($stats['bytes'], $diskTotal)?>"></span><span class="used" style="width: <?=share(max(0, $diskUsed - $stats['bytes']), $diskTotal)?>"></span>
          </div>
          <div class="timeline-legend"><span><i class="gallery"></i>galeria</span><span><i class="used"></i>reszta serwera</span><span><i class="none"></i>wolne</span></div>
        </div>
<?php endif; ?>
<?php if ($stats['files']): ?>
        <div class="stats-grid">
          <div>
            <h3>Foldery</h3>
            <ul class="stat-list">
<?php foreach ($stats['folders'] as $name => $folder): ?>
              <li style="--share: <?=share($folder['bytes'], $stats['bytes'])?>"><a href="<?=e($root . 'i/' . folderUrl((string)$name))?>"><?=e($name === '' ? 'i/ (bez folderu)' : 'i/' . $name)?></a><span><?=$folder['files']?> &middot; <?=e(formatSize($folder['bytes']))?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
          <div>
            <h3>Typy plików</h3>
            <ul class="stat-list">
<?php foreach ($stats['types'] as $type => $group): ?>
              <li style="--share: <?=share($group['bytes'], $stats['bytes'])?>"><span><?=e((string)$type)?></span><span><?=$group['files']?> &middot; <?=e(formatSize($group['bytes']))?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
          <div>
            <h3>Największe pliki</h3>
            <ol class="stat-list">
<?php foreach ($stats['largest'] as [$rel, $size]): ?>
              <li style="--share: <?=share($size, $stats['largest'][0][1])?>"><a href="<?=e($root . 'i/' . fileUrl($rel))?>" target="_blank" rel="noopener" title="<?=e(galleryPath($rel))?>"><?=e(galleryPath($rel))?></a><span><?=e(formatSize($size))?></span></li>
<?php endforeach; ?>
            </ol>
          </div>
        </div>
<?php else: ?>
        <p class="nobody">Galeria jest pusta.</p>
<?php endif; ?>
      </section>

      <section class="card wide" data-sum="<?=webpQuality('jpg')?> JPG">
        <h2><i><?=sprintf('%02d', $number++)?></i>Jakość konwersji na WebP</h2>
        <p class="hint">Z jaką jakością (<?=WEBP_QUALITY_MIN?>–<?=WEBP_QUALITY_MAX?>) zdjęcia zapisują się jako WebP. Niżej = mniejszy plik i słabsza jakość; wynik zapisuje się tylko, gdy wyjdzie mniejszy. Ręczna zmiana w galerii ma własny suwak.</p>
        <form class="quality-form" data-action="quality">
          <label>JPG <input type="number" name="qualityJpg" min="<?=WEBP_QUALITY_MIN?>" max="<?=WEBP_QUALITY_MAX?>" value="<?=webpQuality('jpg')?>" /></label>
          <label>PNG <input type="number" name="qualityPng" min="<?=WEBP_QUALITY_MIN?>" max="<?=WEBP_QUALITY_MAX?>" value="<?=webpQuality('png')?>" /></label>
          <label>GIF <input type="number" name="qualityGif" min="<?=WEBP_QUALITY_MIN?>" max="<?=WEBP_QUALITY_MAX?>" value="<?=webpQuality('gif')?>" /></label>
          <label>AVIF/HEIC <input type="number" name="qualityAvif" min="<?=WEBP_QUALITY_MIN?>" max="<?=WEBP_QUALITY_MAX?>" value="<?=webpQuality('avif')?>" /></label>
          <button type="submit" class="admin-btn primary">Zapisz</button>
        </form>
      </section>

      <section class="card wide" data-sum="<?=$history ? e(ago($history[0]['time'] ?? 0)) : 'brak'?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Historia zmian</h2>
        <p class="hint">Ostatnie zmiany w galerii i w panelu: kto, kiedy i co.</p>
<?php if (!$history): ?>
        <p class="nobody">Jeszcze nic się nie zmieniło.</p>
<?php else: ?>
        <ol class="history">
<?php foreach ($history as $entry): ?>
          <li>
            <time datetime="<?=e(date('c', $entry['time'] ?? 0))?>"><?=e(date('j.m H:i', $entry['time'] ?? 0))?></time>
            <span class="history-who"><?=e($entry['name'] ?: $entry['id'])?></span>
            <span class="history-text"><?=e($entry['text'] ?? '')?></span>
          </li>
<?php endforeach; ?>
        </ol>
<?php endif; ?>
      </section>
