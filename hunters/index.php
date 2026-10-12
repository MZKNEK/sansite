<?php
    // The ranking of the hunters, for anyone: the accounts by their finds of
    // the Safeguard's register (safeguardRanking() in inc/auth/finds.php), the
    // panel's admins among them without a place, and how many found each
    // secret and who was first. A secret's name shows only to the account that
    // found it (the profile has the hints); an account leaves the ranking in
    // its profile.
    require __DIR__ . '/../inc/auth.php';
    require __DIR__ . '/../inc/meta.php';

    date_default_timezone_set('Europe/Warsaw');

    $user = siteUser();
    $mine = $user !== null ? safeguardFinds($user['id']) : [];
    // the name and avatar the ranking shows, as the account has them now
    if ($mine)
        noteHunter($user);
    $ranking = safeguardRanking();
    $stats = safeguardFindStats($ranking);
    $seeAll = $user !== null && realPanelAdminId($user['id']);
    $hidden = $user !== null && $mine && hunterHidden($user['id']);
    $total = count(SAFEGUARD_FINDS);
    $placed = count(array_filter($ranking, function ($row) { return !$row['admin']; }));

    // the account menu of every page, built while the session is still open (its CSRF token)
    $accountMenu = $user !== null ? accountMenuHtml($user, siteRoles(), $_SERVER['REQUEST_URI'] ?? '') : '';
    if (session_status() === PHP_SESSION_ACTIVE)
        session_write_close();
    if ($user !== null)
        header('Cache-Control: private, no-store');

    // the mark of a seasonal find next to a hunter's name
    const SEASON_MARKS = ['xmas' => '❄', 'easter' => '◒', 'birthday' => '✦'];

    // "01", like the numbers of the cards
    function hunterPlace($n)
    {
        return str_pad((string)$n, 2, '0', STR_PAD_LEFT);
    }

    // the name in the colour of its role on the bot's server
    function hunterName($row)
    {
        $role = $row['badge'] !== null ? ' role-' . $row['badge']['key'] : '';

        return '<span class="hunter-name' . e($role) . '"' . ($row['badge'] !== null ? ' title="' . e($row['badge']['title']) . '"' : '') . '>' . e($row['name']) . '</span>';
    }
?>
<!DOCTYPE html>
<html lang="pl"<?=hudHtmlAttributes($user)?>>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex" />
<?=metaTags('Łowcy sekretów', 'Kto odkrył najwięcej sekretów strony Sanakan.', '/hunters/', 'home')?>
  <title>Łowcy sekretów &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link rel="manifest" href="../site.webmanifest" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=3b12b4a18b" type="text/css" rel="stylesheet" />
  <link href="../css/palette.css?v=ff07306679" type="text/css" rel="stylesheet" />
  <link href="../css/account.css?v=1f284a3102" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=4e82c4b35a" type="text/css" rel="stylesheet" />
  <link href="../css/status.css?v=f9c143a63a" type="text/css" rel="stylesheet" />
  <script src="../js/hud.js?v=2894733f39"></script>
</head>

<body class="state-page">
  <main class="content state-content hunters-page">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
        <?=$accountMenu?>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title">Łowcy</h1>
      <p class="version-intro"><?=$placed?> <?=plural($placed, 'łowca', 'łowców', 'łowców')?> &middot; <?=$total?> <?=plural($total, 'sekret', 'sekrety', 'sekretów')?> w rejestrze Safeguarda, sezonowe osobno</p>
    </header>

    <section class="card state-card">
      <h2><i>01</i>Ranking</h2>
      <p class="hunters-hint">Konta, które odkryły sekrety strony, według liczby wpisów w rejestrze. Przy równej liczbie wyżej jest to, które doszło do niej pierwsze. Administratorzy panelu są na liście bez miejsca: wiedzą, gdzie szukać, więc nikogo nie wyprzedzają.</p>
<?php if (!$ranking): ?>
      <p class="incidents-none">Jeszcze nikt niczego nie znalazł. Safeguard czeka.</p>
<?php else: ?>
      <ol class="hunters">
<?php foreach ($ranking as $row): $me = $user !== null && $row['id'] === (string)$user['id']; $seasonal = array_intersect_key(SAFEGUARD_SEASONAL, $row['finds']); ?>
        <li class="hunter<?=$row['admin'] ? ' admin' : ''?><?=$me ? ' me' : ''?><?=$row['place'] !== null && $row['place'] <= 3 ? ' top top-' . $row['place'] : ''?>">
          <span class="hunter-place"><?=$row['place'] !== null ? hunterPlace($row['place']) : '&mdash;'?></span>
<?php if ($row['avatar'] !== ''): ?>
          <img class="hunter-avatar" src="<?=e($row['avatar'])?>" alt="" width="32" height="32" loading="lazy" />
<?php else: ?>
          <span class="hunter-avatar" aria-hidden="true"></span>
<?php endif; ?>
          <span class="hunter-who">
            <?=hunterName($row)?>
<?php if ($row['admin']): ?>
            <small class="hunter-tag" title="Administrator panelu: zna sekrety, więc nie zajmuje miejsca w rankingu">poza rankingiem</small>
<?php endif; ?>
<?php if ($me): ?>
            <small class="hunter-tag me">ty</small>
<?php endif; ?>
<?php foreach ($seasonal as $key => [$name]): ?>
            <small class="hunter-season <?=e($key)?>" title="<?=e($name)?>"><?=SEASON_MARKS[$key] ?? '✦'?></small>
<?php endforeach; ?>
          </span>
          <span class="hunter-bar" aria-hidden="true"><i style="width: <?=round(100 * $row['count'] / max(1, $total), 1)?>%"></i></span>
          <span class="hunter-count"><b><?=$row['count']?></b> / <?=$total?></span>
          <time class="hunter-when" datetime="<?=e(date('c', $row['reached']))?>"><?=$row['count'] === $total ? 'komplet ' : ''?><?=e(date('j.m.Y', $row['reached']))?></time>
        </li>
<?php endforeach; ?>
      </ol>
<?php endif; ?>
<?php if ($hidden): ?>
      <p class="hunters-hint">Twojego konta nie ma na liście: wypisano je z rankingu w <a href="../account/#rejestr">profilu</a>.</p>
<?php endif; ?>
    </section>

    <section class="card state-card">
      <h2><i>02</i>Sekrety</h2>
      <p class="hunters-hint">Ilu łowców odkryło każdy sekret i kto był pierwszy. Nazwę sekretu widzi tylko ten, kto go znalazł<?=$user === null ? ', po zalogowaniu' : ''?>; wskazówki są w <a href="../account/#rejestr">rejestrze w profilu</a>.</p>
      <ol class="secrets">
<?php foreach ([SAFEGUARD_FINDS, SAFEGUARD_SEASONAL] as $list => $finds): ?>
<?php foreach ($finds as $key => [$name]): $stat = $stats[$key]; $known = $seeAll || isset($mine[$key]); ?>
        <li class="<?=isset($mine[$key]) ? 'found' : ''?><?=$list ? ' seasonal' : ''?>">
          <b><?=$known || $list ? e($name) : 'Wpis niezidentyfikowany'?></b>
          <span><?=$stat['hunters'] ? $stat['hunters'] . ' ' . plural($stat['hunters'], 'łowca', 'łowców', 'łowców') : 'nikt jeszcze'?><?=$list ? ' &middot; sezonowy' : ''?></span>
<?php if ($stat['first'] !== null): ?>
          <span class="secret-first">pierwszy: <?=hunterName($stat['first'])?> <time datetime="<?=e(date('c', $stat['time']))?>"><?=e(date('j.m.Y', $stat['time']))?></time></span>
<?php endif; ?>
        </li>
<?php endforeach; ?>
<?php endforeach; ?>
      </ol>
    </section>
    <p class="state-note">Znaleziska bez logowania zostają w przeglądarce i trafiają do rankingu po zalogowaniu. Z rankingu można się wypisać w profilu.</p>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../state/">Status</a><i aria-hidden="true">&middot;</i><a href="../privacy/">Prywatność</a></footer>
  <script src="../js/account.js?v=ff288d9afb"></script>
  <script src="../js/netsphere.js?v=59bbd96f55"></script>
  <script src="../js/safeguard.js?v=29a153249c"></script>
  <script src="../js/palette.js?v=15434ca4d0"></script>
</body>

</html>
