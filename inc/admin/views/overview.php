<?php
    // The overview: the bot, the server's clock, the notice.
    // A view of admin/index.php, included where it stands, with its variables.
?>
      <h2 class="panel-section" data-section="przeglad" data-label="Przegląd">Przegląd</h2>

<?php $botStateNow = botState(); $botStatus = shownStatus($botStateNow); ?>
      <section class="card wide" data-clock="1" data-state="<?=$botStatus === 'online' ? 'ok' : ($botStatus === 'offline' ? 'bad' : 'warn')?>">
        <h2><i>01</i>Status bota</h2>
<?=statusSummary()?>
<?php $clock = serverClock(); ?>
        <p class="server-clock" data-server-ms="<?=$clock['ms']?>" data-zone="<?=e($clock['zone'])?>">
          Czas serwera <b class="server-clock-now"><?=e(date('H:i:s'))?></b>
          <span class="muted">(<?=e($clock['zone'])?>, UTC<?=e(date('P'))?><?=$clock['system'] !== '' && $clock['system'] !== $clock['zone'] ? '; system: ' . e($clock['system']) : ''?>)</span>
<?php if ($clock['ntp'] !== null): ?>
          &middot; <?=$clock['ntp'] ? 'NTP: zsynchronizowany' : '<b class="warn">NTP: niezsynchronizowany</b>'?>
<?php endif; ?>
          &middot; <span class="server-clock-drift"></span>
        </p>
        <p class="hint status-more">Bota sprawdza cron co minutę. Wykresy, czasy odpowiedzi i awarie: <a href="<?=e($root)?>state/">strona statusu</a>.</p>

        <form class="notice-form" data-action="notice">
          <h3>Ogłoszenie</h3>
          <p class="hint">Widać je na stronie statusu. W czasie przerwy technicznej status bota (także kropka na stronie głównej) pokazuje „nie przeszkadzać”, a awarie są oznaczane jako planowane.</p>
<?php if ($notice): ?>
          <p class="notice-now">Teraz widać: <b><?=e(noticeText($notice))?></b><?=!empty($notice['to']) ? ' <span class="muted">(do ' . e(date('j.m H:i', $notice['to'])) . ')</span>' : ''?></p>
<?php endif; ?>
          <textarea class="field" name="text" rows="2" maxlength="<?=NOTICE_LENGTH?>" placeholder="Np. Dziś wieczorem aktualizacja bota, przez chwilę może nie odpowiadać." aria-label="Treść ogłoszenia"><?=e($notice['text'] ?? '')?></textarea>
          <div class="notice-fields">
            <label class="admin-check"><input type="checkbox" name="maintenance" value="1"<?=!empty($notice['maintenance']) ? ' checked' : ''?> /> Przerwa techniczna</label>
            <span class="notice-time">Od <input type="datetime-local" class="field" name="from" aria-label="Od" value="<?=e(fieldTime($notice['maintenance']['from'] ?? null))?>" /></span>
            <span class="notice-time">Do <input type="datetime-local" class="field" name="to" aria-label="Do" value="<?=e(fieldTime($notice['to'] ?? null))?>" /></span>
          </div>
          <p class="hint">„Od” liczy się tylko dla przerwy (puste: od teraz). „Do” to koniec przerwy, a bez przerwy czas, kiedy ogłoszenie zniknie (puste: zostaje, aż się je usunie).</p>
          <div class="notice-actions">
            <button type="submit" class="admin-btn primary">Zapisz ogłoszenie</button>
<?php if ($notice): ?>
            <button type="button" class="admin-btn danger" data-action="notice-clear" data-confirm="Usunąć ogłoszenie?">Usuń</button>
<?php endif; ?>
          </div>
        </form>
      </section>
