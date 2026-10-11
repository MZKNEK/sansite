<?php
    // Access and accounts: the preview with other rights, the lists, the logins.
    // A view of admin/index.php, included where it stands, with its variables.
?>
      <h2 class="panel-section" data-section="dostep" data-label="Dostęp i konta">Dostęp i konta</h2>

<?php $number = 2; $test = testRights($user['id']); ?>
      <section class="card wide" data-sum="<?=$test ? 'włączony' : 'wyłączony'?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Podgląd z innymi uprawnieniami</h2>
        <p class="hint">Na chwilę zmienia twoje uprawnienia na całej stronie, żeby zobaczyć ją na żywo tak, jak widzi ją konto z takimi. Tylko twoje konto i tylko w tej sesji; pasek na dole każdej strony pokazuje, co jest zmienione, i przywraca twoje. Panel daje też API, więc API przez rolę na serwerze sprawdzisz bez panelu.</p>
<?php if ($test): ?>
        <p class="test-now">Teraz: <b><?=e(testRightsText($test))?></b> do <?=e(date('H:i', $test['until']))?>
          <button type="button" class="admin-btn small" data-action="test-off">Wróć do swoich</button></p>
<?php endif; ?>
        <form class="test-form" data-action="test-rights">
          <label class="hud-field">
            <span class="hud-field-label">Panel</span>
            <span class="hud-select"><select name="panel"><option value="">bez zmian</option><option value="1">z panelem</option><option value="0" selected>bez panelu</option></select><svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg></span>
          </label>
          <label class="hud-field">
            <span class="hud-field-label">Galeria</span>
            <span class="hud-select"><select name="gallery"><option value="">bez zmian</option>
<?php foreach (TEST_GALLERY as $key => $label): ?>
              <option value="<?=e($key)?>"<?=$key === 'none' ? ' selected' : ''?>><?=e($label)?></option>
<?php endforeach; ?>
            </select><svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg></span>
          </label>
          <label class="hud-field">
            <span class="hud-field-label">Prywatna galeria</span>
            <span class="hud-select"><select name="private"><option value="">bez zmian</option><option value="1">tak</option><option value="0" selected>nie</option></select><svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg></span>
          </label>
          <label class="hud-field">
            <span class="hud-field-label">Lista API</span>
            <span class="hud-select"><select name="api"><option value="">bez zmian</option><option value="1">na liście</option><option value="0" selected>poza listą</option></select><svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg></span>
          </label>
          <label class="hud-field">
            <span class="hud-field-label">Rola na serwerze bota</span>
            <span class="hud-select"><select name="role"><option value="">bez zmian</option>
<?php foreach (BOT_ROLES as $key => [$level, $badgeLabel, $name]): ?>
              <option value="<?=e($key)?>"<?=$key === 'user' ? ' selected' : ''?>><?=e($name)?> (LV.<?=$level?>)</option>
<?php endforeach; ?>
              <option value="<?=TEST_ROLE_OUT?>">poza serwerem</option>
            </select><svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg></span>
          </label>
          <label class="hud-field">
            <span class="hud-field-label">Czas</span>
            <span class="hud-select"><select name="minutes">
<?php foreach (TEST_MINUTES as $minutes): ?>
              <option value="<?=$minutes?>"<?=$minutes === 15 ? ' selected' : ''?>><?=$minutes < 60 ? $minutes . ' min' : ($minutes / 60) . ' godz.'?></option>
<?php endforeach; ?>
            </select><svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg></span>
          </label>
          <button type="submit" class="admin-btn primary">Włącz podgląd</button>
        </form>
      </section>

      <section class="card" data-sum="<?=$panelAdmins === true ? 'wszyscy' : count($panelAdmins)?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Dostęp do panelu</h2>
        <ul class="people">
<?php foreach ($panelAdmins === true ? [] : $panelAdmins as $id): ?>
          <li><?=accountCell($id, $logins)?><span class="badge-config" title="Ustawione w inc/config.php">config</span></li>
<?php endforeach; ?>
        </ul>
        <p class="hint">Listę zmienia się w <code>inc/config.php</code> (<code>PANEL_ADMINS</code>), panel nie może nadawać dostępu do samego siebie.</p>
      </section>

<?php foreach ($lists as $list => $info): ?>
      <section class="card" data-sum="<?=$info['everyone'] ? 'wszyscy' : count($info['entries'])?>">
        <h2><i><?=sprintf('%02d', $number++)?></i><?=e(LIST_CARDS[$list][0])?></h2>
        <p class="hint"><?=e(LIST_CARDS[$list][1])?></p>
<?php if ($info['everyone']): ?>
        <p class="everyone"><?=$list === 'galleryUploaders' ? 'Zablokowane wszystkim' : 'Każde konto Discord'?> (<code><?=e(ACCESS_LISTS[$list])?> = true</code> w konfiguracji).</p>
<?php endif; ?>
        <ul class="people">
<?php foreach ($info['entries'] as $entry): ?>
          <li>
            <?=accountCell($entry['id'], $logins)?>
<?php if ($entry['note'] !== ''): ?>
            <span class="note"><?=e($entry['note'])?></span>
<?php endif; ?>
<?php if ($entry['config']): ?>
            <span class="badge-config" title="Ustawione w inc/config.php">config</span>
<?php else: ?>
<?php if ($list === 'galleryUploaders'): ?>
            <button type="button" class="admin-btn small" data-action="revoke" data-list="<?=e($list)?>" data-id="<?=e($entry['id'])?>" data-confirm="Odblokować własny folder <?=e(accountLabel($entry['id'], $logins))?>?">Odblokuj</button>
<?php else: ?>
            <button type="button" class="admin-btn danger small" data-action="revoke" data-list="<?=e($list)?>" data-id="<?=e($entry['id'])?>" data-confirm="Odebrać dostęp: <?=e(accountLabel($entry['id'], $logins))?>?">Usuń</button>
<?php endif; ?>
<?php endif; ?>
          </li>
<?php endforeach; ?>
<?php if ($list === 'apiViewers'): foreach ($apiByRole as $id => $role): ?>
          <li>
            <?=accountCell($id, $logins)?>
            <span class="role bot role-<?=e($role)?>" title="Rola na serwerze Sanakana, według bota">rola: <?=e(BOT_ROLES[$role][2])?></span>
          </li>
<?php endforeach; endif; ?>
<?php if (!$info['entries'] && !$info['everyone'] && ($list !== 'apiViewers' || !$apiByRole)): ?>
          <li class="nobody">Nikogo jeszcze nie ma.</li>
<?php endif; ?>
        </ul>
        <form class="grant" data-action="grant">
          <input type="hidden" name="list" value="<?=e($list)?>" />
          <input type="text" class="field" name="id" inputmode="numeric" pattern="\d{17,20}" placeholder="ID konta Discord" aria-label="ID konta Discord" required />
          <input type="text" class="field" name="note" maxlength="60" placeholder="Notatka (opcjonalnie)" aria-label="Notatka" />
          <button type="submit" class="admin-btn primary"><?=$list === 'galleryUploaders' ? 'Zablokuj' : 'Dodaj'?></button>
        </form>
      </section>
<?php endforeach; ?>

      <section class="card wide" data-sum="<?=count($logins)?>">
        <h2><i><?=sprintf('%02d', $number++)?></i>Ostatnie logowania</h2>
        <p class="hint">Każdy, kto zalogował się przez Discord w galerii, w API albo w panelu, także bez dostępu. Stąd najłatwiej komuś go nadać. Kolorowy poziom to najwyższa rola na serwerze bota (wszystkie w dymku), odświeżana, gdy konto odwiedza stronę; galerii nie daje. Kolumny to dostępy na stronie, jaśniejsze mocniejsze; strzałka na końcu wiersza pokazuje, skąd są, i przyciski do nadania nowych.</p>
<?php if (!$logins): ?>
        <p class="nobody">Nikt się jeszcze nie logował.</p>
<?php else: ?>
        <p class="logins-all"><button type="button" class="admin-btn small" id="logins-toggle" aria-expanded="false">Rozwiń wszystkie</button></p>
        <div class="logins">
          <div class="login-head" aria-hidden="true">
            <span>Konto</span><span>Ostatnio</span><span>Rola</span>
<?php foreach (ACCESS_COLUMNS as $label): ?>
            <span><?=e($label)?></span>
<?php endforeach; ?>
            <span></span>
          </div>
<?php foreach ($logins as $id => $login):
        $id = (string)$id;
        // the highest role on the bot's server, the others in its title
        $serverRoles = $knownRoles[$id]['roles'] ?? null;
        $badge = roleBadge($serverRoles);
        [$cells, $details] = accessColumns($id, $logins, $galleryDir, $serverRoles);
?>
          <div class="login-row">
            <span class="login-who"><?=accountCell($id, $logins)?></span>
            <span class="login-when"><?=e(ago($login['last'] ?? 0))?> &middot; <?=(int)($login['count'] ?? 0)?>&times;</span>
            <span class="login-lv">
<?php if ($badge): ?>
              <span class="lv role-<?=e($badge['key'])?>" title="<?=e(roleNames($serverRoles) ? 'Role na serwerze Sanakana: ' . implode(', ', roleNames($serverRoles)) : $badge['title'])?>">LV.<?=$badge['level']?> <?=e($badge['name'])?></span>
<?php endif; ?>
            </span>
<?php foreach (ACCESS_COLUMNS as $column => $label): [$text, $level, $title] = $cells[$column]; ?>
            <span class="access-cell<?=$level !== '' ? ' ' . $level : ''?>" data-column="<?=e($label)?>" title="<?=e($title)?>"><?=e($text)?></span>
<?php endforeach; ?>
            <button type="button" class="login-more" aria-expanded="false" title="Skąd te uprawnienia i nadawanie nowych">
              <svg class="account-icon" viewBox="0 0 24 24" aria-hidden="true"><?=ACCOUNT_ICONS['caret']?></svg><span class="visually-hidden">Szczegóły</span>
            </button>
            <div class="login-details" hidden>
              <dl class="server">
<?php foreach ($details as [$label, $text]): ?>
                <dt><?=e($label)?></dt>
                <dd><?=e($text)?></dd>
<?php endforeach; ?>
              </dl>
              <div class="login-actions">
<?php if (!canViewGalleryId($id)): ?>
                <button type="button" class="admin-btn small" data-action="grant" data-list="galleryViewers" data-id="<?=e($id)?>">+ Oglądający</button>
<?php endif; ?>
<?php if (isGalleryUploaderId($id)): ?>
                <button type="button" class="admin-btn small danger" data-action="grant" data-list="galleryUploaders" data-id="<?=e($id)?>" data-confirm="<?=e('Zablokować własny folder ' . accountLabel($id, $logins) . '? Folder i pliki zostają.')?>">Zablokuj folder</button>
<?php endif; ?>
<?php if (!isGalleryAdminId($id)): ?>
                <button type="button" class="admin-btn small" data-action="grant" data-list="galleryAdmins" data-id="<?=e($id)?>">+ Admin galerii</button>
<?php endif; ?>
<?php if (!canSeePrivateGalleryId($id)): ?>
                <button type="button" class="admin-btn small" data-action="grant" data-list="galleryPrivate" data-id="<?=e($id)?>">+ Prywatny folder</button>
<?php endif; ?>
<?php if (!canViewApiId($id)): ?>
                <button type="button" class="admin-btn small" data-action="grant" data-list="apiViewers" data-id="<?=e($id)?>">+ API</button>
<?php endif; ?>
                <a class="admin-btn small" href="?konto=<?=e($id)?>">Profil konta, odbieranie</a>
              </div>
            </div>
          </div>
<?php endforeach; ?>
        </div>
<?php endif; ?>
        <p class="logout-all">
          <span class="hint"><?=$sessionsSince ? 'Ostatnio wylogowano wszystkich ' . e(ago($sessionsSince)) . '.' : 'Po odebraniu komuś dostępu można też zakończyć wszystkie sesje.'?></span>
          <button type="button" class="admin-btn small danger" data-action="logout-all" data-confirm="Wylogować wszystkich z galerii i panelu? Ty zostaniesz zalogowany, inni muszą zalogować się jeszcze raz.">Wyloguj wszystkich</button>
        </p>
      </section>
