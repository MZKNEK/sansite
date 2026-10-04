<?php
    // Link preview picture of state/ (og:image): the bot status, availability
    // and the 90 day bar, so a link pasted on Discord shows the state of the
    // moment. Drawn with GD at twice the size and scaled down for smooth edges,
    // then kept for a minute. Without GD's FreeType the logo has to do.
    require __DIR__ . '/../inc/bot.php';
    require __DIR__ . '/../inc/services.php';
    require __DIR__ . '/../inc/gallery.php';
    require __DIR__ . '/../inc/status-card.php';

    const OG_WIDTH = 1200;
    const OG_HEIGHT = 630;
    const OG_SCALE = 2;
    const OG_FONTS = __DIR__ . '/../inc/fonts/';

    $file = botFile('og.png');
    if (!is_file($file) || filemtime($file) < time() - BOT_CACHE_TTL) {
        if (!hasGd() || !function_exists('imagettftext') || !drawPreview($file)) {
            header('Location: ../sanakan.jpg', true, 302);
            exit;
        }
    }

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=' . BOT_CACHE_TTL);
    readfile($file);

    // a colour from "#rrggbb", optionally see-through (0 opaque - 127 clear)
    function color($img, $hex, $alpha = 0)
    {
        return imagecolorallocatealpha($img, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)), $alpha);
    }

    // text with extra space between the letters, like the HUD titles; $x, $y and
    // $size in the picture's own pixels; $align right ends the text at $x
    function text($img, $font, $size, $x, $y, $color, $string, $spacing = 0, $align = 'left')
    {
        $size *= OG_SCALE;
        $spacing *= OG_SCALE;
        $chars = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);

        // where each letter starts: the width of what comes before it, measured up
        // to a following "X", so narrow signs and spaces get their full advance
        $mark = imagettfbbox($size, 0, $font, 'X');
        $markWidth = $mark[2] - $mark[0];
        $starts = [];
        $before = '';
        foreach ($chars as $i => $char) {
            $box = imagettfbbox($size, 0, $font, $before . 'X');
            $starts[] = ($box[2] - $box[0]) - $markWidth + $i * $spacing;
            $before .= $char;
        }
        $box = imagettfbbox($size, 0, $font, $before);
        $width = $box[2] - $box[0] + (count($chars) - 1) * $spacing;
        $x = $x * OG_SCALE - ($align === 'right' ? $width : 0);

        foreach ($chars as $i => $char)
            imagettftext($img, $size, 0, (int)round($x + $starts[$i]), $y * OG_SCALE, $color, $font, $char);
    }

    // the HUD corner brackets of the site around a box
    function corners($img, $x1, $y1, $x2, $y2, $length, $color)
    {
        $t = 3 * OG_SCALE;
        foreach ([[$x1, $y1, 1, 1], [$x2, $y1, -1, 1], [$x1, $y2, 1, -1], [$x2, $y2, -1, -1]] as [$x, $y, $dx, $dy]) {
            $x *= OG_SCALE;
            $y *= OG_SCALE;
            $l = $length * OG_SCALE;
            imagefilledrectangle($img, min($x, $x + $dx * $l), min($y, $y + $dy * $t), max($x, $x + $dx * $l), max($y, $y + $dy * $t), $color);
            imagefilledrectangle($img, min($x, $x + $dx * $t), min($y, $y + $dy * $l), max($x, $x + $dx * $t), max($y, $y + $dy * $l), $color);
        }
    }

    // the status dot of the home page: green, moon, ring or do not disturb
    function statusDot($img, $cx, $cy, $r, $status, $background)
    {
        $colors = ['online' => '#23a55a', 'idle' => '#f0b232', 'offline' => '#80848e', 'maintenance' => '#f23f43'];
        $cx *= OG_SCALE;
        $cy *= OG_SCALE;
        $r *= OG_SCALE;
        imagefilledellipse($img, $cx, $cy, 2 * $r, 2 * $r, color($img, $colors[$status]));

        if ($status === 'idle')
            imagefilledellipse($img, (int)($cx - 0.45 * $r), (int)($cy - 0.45 * $r), (int)(1.45 * $r), (int)(1.45 * $r), $background);
        else if ($status === 'offline')
            imagefilledellipse($img, $cx, $cy, (int)(0.8 * $r), (int)(0.8 * $r), $background);
        else if ($status === 'maintenance')
            imagefilledrectangle($img, (int)($cx - 0.6 * $r), (int)($cy - 0.2 * $r), (int)($cx + 0.6 * $r), (int)($cy + 0.2 * $r), $background);
    }

    function drawPreview($file)
    {
        $state = botState();
        $status = shownStatus($state);
        $days = botDailyParts(DAYS_SHOWN);

        $img = imagecreatetruecolor(OG_WIDTH * OG_SCALE, OG_HEIGHT * OG_SCALE);
        $background = color($img, '#141517');
        imagefill($img, 0, 0, $background);

        // a purple glow in the top right corner, like the page background
        for ($i = 0; $i < 12; $i++) {
            $size = (int)((900 - $i * 60) * OG_SCALE);
            imagefilledellipse($img, OG_WIDTH * OG_SCALE, 0, $size, $size, color($img, '#9b59b6', 124 - $i));
        }
        corners($img, 32, 32, OG_WIDTH - 32, OG_HEIGHT - 32, 46, color($img, '#9b59b6'));

        // Share Tech Mono has no Polish letters, so it gets only the HUD labels and numbers
        $mono = OG_FONTS . 'ShareTechMono-Regular.ttf';
        $bold = OG_FONTS . 'Lato-Bold.ttf';
        $regular = OG_FONTS . 'Lato-Regular.ttf';
        if (!is_file($mono) || !is_file($bold) || !is_file($regular))
            return false;

        text($img, $mono, 20, 80, 112, color($img, '#b670d3'), 'SAFEGUARD · LV.9 · STATUS', 6);
        text($img, $bold, 64, 80, 196, color($img, '#efe2f7'), 'SANAKAN', 18);

        statusDot($img, 104, 278, 24, $status, $background);
        text($img, $bold, 38, 148, 293, color($img, '#efe2f7'), 'Bot ' . statusLabel($state));

        $uptimes = [
            '24 h' => str_replace('.', ',', $state['uptime']) . '%',
            DAYS_SHOWN . ' dni' => partsUptime($days)
        ];
        $x = 80;
        foreach ($uptimes as $label => $value) {
            text($img, $regular, 22, $x, 360, color($img, '#dcddde', 50), $label);
            text($img, $mono, 34, $x, 402, color($img, '#efe2f7'), $value);
            $x += 300;
        }

        // the last days, a block per day
        $partColors = ['ok' => color($img, '#23a55a', 30), 'warn' => color($img, '#f0b232', 25), 'fail' => color($img, '#d9534f', 20), 'none' => color($img, '#dcddde', 117)];
        $gap = 2;
        $width = (OG_WIDTH - 160 - (count($days) - 1) * $gap) / count($days);
        foreach ($days as $i => $part) {
            $left = 80 + $i * ($width + $gap);
            imagefilledrectangle($img, (int)($left * OG_SCALE), 446 * OG_SCALE, (int)(($left + $width) * OG_SCALE), 496 * OG_SCALE, $partColors[partClass($part)]);
        }
        text($img, $regular, 18, 80, 526, color($img, '#dcddde', 70), 'ostatnie ' . DAYS_SHOWN . ' dni');
        text($img, $regular, 18, OG_WIDTH - 80, 526, color($img, '#dcddde', 70), 'dziś', 0, 'right');

        text($img, $mono, 22, 80, 584, color($img, '#9b59b6'), 'sanakan.pl/state', 2);
        text($img, $regular, 18, OG_WIDTH - 80, 584, color($img, '#dcddde', 70), 'stan z ' . date('j.m.Y H:i', $state['checked']), 0, 'right');

        $out = imagecreatetruecolor(OG_WIDTH, OG_HEIGHT);
        imagecopyresampled($out, $img, 0, 0, 0, 0, OG_WIDTH, OG_HEIGHT, OG_WIDTH * OG_SCALE, OG_HEIGHT * OG_SCALE);
        imagedestroy($img);

        $tmp = $file . '.' . getmypid();
        $ok = @imagepng($out, $tmp, 6) && @rename($tmp, $file);
        imagedestroy($out);
        if (!$ok)
            @unlink($tmp);

        return $ok;
    }
