<?php
    // The gallery: its trash, shared links, numbers and history.
    // A view of admin/index.php, included where it stands, with its variables.
?>
      <h2 class="panel-section" data-section="galeria" data-label="Galeria">Galeria</h2>

      <section class="card wide" data-sum="<?=count($trash)?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Kosz</h2>
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
