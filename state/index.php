<?php
    // Public bot status for anyone: the same card as in the admin panel, without
    // "check now". The page reloads itself every minute.
    require __DIR__ . '/../inc/bot.php';
    require __DIR__ . '/../inc/gallery.php';
    require __DIR__ . '/../inc/status-card.php';

    $card = statusCard(false);
?>
<!DOCTYPE html>
<html lang="pl">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Status bota Sanakan i jego dostępność w ostatnich 24 godzinach" />
  <meta http-equiv="refresh" content="60" />
  <title>Status &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Share+Tech+Mono&family=JetBrains+Mono:wght@400;700&display=swap" />
  <link href="../css/style.css?v=19" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=5" type="text/css" rel="stylesheet" />
  <link href="../css/status.css?v=2" type="text/css" rel="stylesheet" />
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

    <section class="card state-card">
<?=$card?>
    </section>
    <p class="state-note">Strona odświeża się sama co minutę.</p>
  </main>
</body>

</html>
