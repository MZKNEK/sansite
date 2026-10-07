<?php
    // Public bot status for anyone: availability, answer times, outages and the
    // notice set in the admin panel, and the state of the other Sanakan sites.
    // The page reloads itself every 10 minutes.
    require __DIR__ . '/../inc/bot.php';
    require __DIR__ . '/../inc/services.php';
    require __DIR__ . '/../inc/gallery.php';
    require __DIR__ . '/../inc/status-card.php';
    require __DIR__ . '/../inc/meta.php';

    $card = statusCard();
    $services = servicesCard();
    $notice = botNotice();
?>
<!DOCTYPE html>
<html lang="pl">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
<?=metaTags('Status', 'Bot ' . statusLabel(botState()) . '. Dostępność, ping do Discorda i awarie z ostatnich ' . DAYS_SHOWN . ' dni.', '/state/', null, [SITE_URL . '/state/og.php?v=' . intdiv(time(), 300), 1200, 630])?>
  <meta http-equiv="refresh" content="600" />
  <title>Status &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=7ea800b1aa" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=bbc3267158" type="text/css" rel="stylesheet" />
  <link href="../css/status.css?v=998eb1311f" type="text/css" rel="stylesheet" />
  <script src="../js/hud.js?v=0effb1151f"></script>
</head>

<body class="state-page">
  <main class="content state-content">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
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
  <footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../privacy/">Prywatność</a></footer>
  <script src="../js/netsphere.js?v=1c8be049a6"></script>
</body>

</html>
