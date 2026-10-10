<?php
    // The cards that need attention: cron, the cleanup, requests, a full inc/data.
    // A view of admin/index.php, included where it stands, with its variables.
?>
<?php $alertCount = count($requests ?: []) + ($cronLate ? 1 : 0) + ($maintenanceLate ? 1 : 0) + ($dataHeavy ? 1 : 0); ?>
<?php if ($alertCount): ?>
      <h2 class="panel-section" data-section="wymaga" data-label="Wymaga uwagi" data-count="<?=$alertCount?>">Wymaga uwagi</h2>
<?php endif; ?>

<?php if ($cronLate): ?>
      <section class="card wide alarm" role="alert" data-state="warn" data-sum="<?=e($cronLast === null ? 'brak' : ago($cronLast))?>">
        <h2><i>!</i>Cron nie sprawdza bota</h2>
        <p><?=$cronLast === null
            ? 'Automatyczne sprawdzanie jeszcze ani razu nie zadziałało.'
            : 'Ostatnie automatyczne sprawdzenie było ' . e(ago($cronLast)) . ' (' . e(date('j.m H:i', $cronLast)) . ').'?>
          Bez niego historia dostępności ma dziury, a awarie, gdy nikt nie odwiedza strony, nie są zapisywane.</p>
        <p class="hint">Zadanie cron (na serwerze, jako root): <code><?=e($cronCommand)?></code><br />Czy cron działa: <code>systemctl status cron</code></p>
      </section>
<?php endif; ?>

<?php if ($maintenanceLate): $tried = (int)($maintenance['tried'] ?? 0); $succeeded = (int)($maintenance['succeeded'] ?? 0); ?>
      <section class="card wide alarm" role="alert" data-state="warn" data-sum="<?=e($succeeded ? ago($succeeded) : 'brak')?>">
        <h2><i>!</i>Kosz się nie sprząta</h2>
        <p><?=$tried === 0
            ? 'Porządki (usuwanie z kosza po ' . TRASH_DAYS . ' dniach, dokańczanie przerwanych operacji) jeszcze ani razu nie ruszyły.'
            : 'Ostatnie udane porządki: ' . ($succeeded ? e(ago($succeeded)) : 'jeszcze żadne') . '; ostatnia próba ' . e(ago($tried)) . '.'?>
<?php foreach ($maintenance['last']['problems'] ?? [] as $problem): ?>
          <br /><b class="warn"><?=e($problem)?></b>
<?php endforeach; ?>
        </p>
        <p class="hint">Zadanie cron (na serwerze, jako root): <code><?=e($maintenanceCommand)?></code><br />Ręcznie: <code>sudo -u www-data php <?=e(str_replace('\\', '/', realpath(__DIR__ . '/../../run-maintenance.php')))?> --dry-run</code></p>
      </section>
<?php endif; ?>

<?php if ($requests): ?>
      <section class="card wide requests" data-state="warn" data-sum="<?=count($requests)?>">
        <h2><i>+</i>Prośby o dostęp</h2>
        <p class="hint">Konta, które zalogowały się do galerii albo do API bez dostępu i o niego poprosiły.</p>
        <ul class="people">
<?php foreach ($requests as $request): $id = $request['id']; ?>
          <li>
            <?=accountCell($id, $logins)?>
            <span class="role"><?=$request['for'] === 'api' ? 'API' : 'galeria'?></span>
<?php if (($request['note'] ?? '') !== ''): ?>
            <span class="note">„<?=e($request['note'])?>”</span>
<?php endif; ?>
            <span class="muted"><?=e(ago($request['time']))?></span>
            <span class="login-actions">
<?php if ($request['for'] === 'api'): ?>
              <button type="button" class="admin-btn small" data-action="grant" data-list="apiViewers" data-id="<?=e($id)?>">+ Dostęp do API</button>
<?php else: ?>
              <button type="button" class="admin-btn small" data-action="grant" data-list="galleryViewers" data-id="<?=e($id)?>">+ Oglądający</button>
              <button type="button" class="admin-btn small" data-action="grant" data-list="galleryAdmins" data-id="<?=e($id)?>">+ Admin galerii</button>
<?php endif; ?>
              <button type="button" class="admin-btn small danger" data-action="request-dismiss" data-for="<?=e($request['for'])?>" data-id="<?=e($id)?>" data-confirm="Odrzucić prośbę <?=e(accountLabel($id, $logins))?> o dostęp do <?=e(REQUEST_LABELS[$request['for']])?>?">Odrzuć</button>
            </span>
          </li>
<?php endforeach; ?>
        </ul>
      </section>
<?php endif; ?>

<?php if ($dataHeavy): ?>
      <section class="card wide alarm" role="alert" data-state="warn" data-sum="<?=e(formatSize($dataRoom['trash'] + $dataRoom['webp']))?>">
        <h2><i>!</i>Dużo danych w inc/data</h2>
        <p>Kosz zajmuje <b><?=e(formatSize($dataRoom['trash']))?></b>, a podglądy WebP <b><?=e(formatSize($dataRoom['webp']))?></b> &mdash; razem <?=e(formatSize($dataRoom['trash'] + $dataRoom['webp']))?> z <?=e(formatSize(PANEL_DATA_WARN_BYTES))?>. Kosz opróżnia się po <?=TRASH_DAYS?> dniach, podglądy po dwóch godzinach; możesz je też usunąć w kartach niżej.</p>
      </section>
<?php endif; ?>
