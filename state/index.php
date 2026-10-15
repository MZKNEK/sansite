<?php
    // Public bot status for anyone: availability, answer times, outages and the
    // notice set in the admin panel, and the state of the other Sanakan sites.
    // The page reloads itself every 10 minutes.
    require __DIR__ . '/../inc/bot.php';
    installDataErrorHandler();
    require __DIR__ . '/../inc/services.php';
    require __DIR__ . '/../inc/gallery.php';
    require __DIR__ . '/../inc/status-card.php';
    require __DIR__ . '/../inc/meta.php';

    $card = statusCard();
    $services = servicesCard();
    $notice = botNotice();
    $user = siteUser();
?>
<!DOCTYPE html>
<html lang="pl"<?=hudHtmlAttributes($user)?>>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
<?=metaTags('Status', 'Bot ' . statusLabel(botState()) . '. Dostępność, ping do Discorda i awarie z ostatnich ' . DAYS_SHOWN . ' dni.', '/state/', null, [SITE_URL . '/state/og.php?v=' . intdiv(time(), 300), 1200, 630])?>
  <meta http-equiv="refresh" content="600" />
  <title>Status &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link rel="manifest" href="../site.webmanifest" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=19681cb590" type="text/css" rel="stylesheet" />
  <link href="../css/palette.css?v=3bb1398e4e" type="text/css" rel="stylesheet" />
  <link href="../css/account.css?v=66047e5fc2" type="text/css" rel="stylesheet" />
  <link href="../css/skins.css?v=ac431f15d7" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=08cb01faf8" type="text/css" rel="stylesheet" />
  <link href="../css/status.css?v=c4ddf09879" type="text/css" rel="stylesheet" />
  <script src="../js/appearance.js?v=035d31fe77"></script>
  <link href="../css/seasonal-background.css?v=b5d5b2be79" type="text/css" rel="stylesheet" />
</head>

<body class="state-page">
  <main class="content state-content">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
<?php if ($user): ?>
        <?=accountMenuHtml($user, siteRoles(), $_SERVER['REQUEST_URI'] ?? '')?>
<?php endif; ?>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title">Status</h1>
    </header>

<?php if ($notice): ?>
    <p class="notice-bar<?=empty($notice['maintenance']) ? '' : ' maintenance'?>" role="status"><?=e(noticeText($notice))?></p>
<?php endif; ?>
    <section class="card state-card">
<?=$card?>
    </section>

    <section class="card state-card">
      <h2><i>+</i>Strona i pozostałe serwisy</h2>
<?=$services?>
    </section>
    <p class="state-note">Strona odświeża się sama co 10 minut.</p>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../state/">Status</a><i aria-hidden="true">&middot;</i><a href="../privacy/">Prywatność</a></footer>
  <script src="../js/account.js?v=9ad24ea77c"></script>
  <script src="../js/netsphere.js?v=a98676efe2"></script>
  <script src="../js/safeguard.js?v=56d4cd03bb"></script>
  <script src="../js/seasonal-background.js?v=f262c89973"></script>
  <script src="../js/palette.js?v=9d395acc33"></script>
</body>

</html>
