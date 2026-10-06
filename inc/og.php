<?php
    // Drawing link preview pictures (og:image) with GD: the dark HUD background
    // with the purple glow and corners, text with spaced letters, the logo in a
    // ring and the Discord status dot. Everything is drawn at twice the size and
    // scaled down for smooth edges. Used by og.php and state/og.php; needs
    // inc/bot.php for botFile().

    const OG_WIDTH = 1200;
    const OG_HEIGHT = 630;
    const OG_SCALE = 2;
    const OG_FONTS = __DIR__ . '/fonts/';
    const OG_BACKGROUND = '#141517';

    // Sends the cached picture $name, drawing it again by $draw($file) when it is
    // older than $ttl seconds or than this file. Without GD's FreeType, or when
    // drawing fails, the fixed picture of the home page has to do.
    function ogServe($name, $ttl, $draw)
    {
        $file = botFile($name);
        if (!is_file($file) || filemtime($file) < max(time() - $ttl, filemtime(__FILE__))) {
            if (!function_exists('imagettftext') || !is_file(OG_FONTS . 'Lato-Bold.ttf') || !$draw($file)) {
                header('Location: /sanakan-og.png', true, 302);
                exit;
            }
        }

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=' . $ttl);
        readfile($file);
    }

    // a colour from "#rrggbb", optionally see-through (0 opaque - 127 clear)
    function color($img, $hex, $alpha = 0)
    {
        return imagecolorallocatealpha($img, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)), $alpha);
    }

    // the empty picture at twice the size: background, the purple glow in the
    // top right corner like on the page, and the HUD corners
    function ogCanvas()
    {
        $img = imagecreatetruecolor(OG_WIDTH * OG_SCALE, OG_HEIGHT * OG_SCALE);
        imagefill($img, 0, 0, color($img, OG_BACKGROUND));

        for ($i = 0; $i < 12; $i++) {
            $size = (int)((900 - $i * 60) * OG_SCALE);
            imagefilledellipse($img, OG_WIDTH * OG_SCALE, 0, $size, $size, color($img, '#9b59b6', 124 - $i));
        }
        corners($img, 32, 32, OG_WIDTH - 32, OG_HEIGHT - 32, 46, color($img, '#9b59b6'));

        return $img;
    }

    // scales the picture down to its real size and writes it as PNG
    function ogSave($img, $file)
    {
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

    // Share Tech Mono has no Polish letters, so it gets only the HUD labels and numbers
    const OG_MONO = OG_FONTS . 'ShareTechMono-Regular.ttf';
    const OG_BOLD = OG_FONTS . 'Lato-Bold.ttf';
    const OG_REGULAR = OG_FONTS . 'Lato-Regular.ttf';

    // The layout of the pictures with data, as the status one: the SAFEGUARD
    // line (ASCII only) and the big name at the top left...
    function ogHead($img, $tag, $title)
    {
        text($img, OG_MONO, 20, 80, 112, color($img, '#b670d3'), $tag, 6);
        text($img, OG_BOLD, 64, 80, 196, color($img, '#efe2f7'), mb_strtoupper($title, 'UTF-8'), 18);
    }

    // ...a line in big letters under it, after a status dot or not...
    function ogLine($img, $text, $x = 80)
    {
        text($img, OG_BOLD, 38, $x, 293, color($img, '#efe2f7'), ogFit(OG_BOLD, 38, $text, OG_WIDTH - 80 - $x));
    }

    // ...a row of numbers with their labels, [label => value]...
    function ogStats($img, $stats, $step = 260)
    {
        $x = 80;
        foreach ($stats as $label => $value) {
            text($img, OG_REGULAR, 22, $x, 360, color($img, '#dcddde', 50), $label);
            text($img, OG_MONO, 34, $x, 402, color($img, '#efe2f7'), (string)$value);
            $x += $step;
        }
    }

    // ...a bar split in parts, [[weight, label]], as wide as their weights, the
    // labels with their colours under it as far as they fit...
    function ogBar($img, $parts)
    {
        $palette = ['#9b59b6', '#d2a8e8', '#6c3483', '#b670d3', '#4a235a', '#c39bd3', '#8e44ad', '#e8daef'];
        $total = array_sum(array_column($parts, 0));
        if (!$total)
            return;

        $gap = 3;
        $space = OG_WIDTH - 160 - (count($parts) - 1) * $gap;
        $left = 80;
        $legendX = 80;
        foreach (array_values($parts) as $i => [$weight, $label]) {
            $hex = $palette[$i % count($palette)];
            $width = max(2, $space * $weight / $total);
            imagefilledrectangle($img, (int)($left * OG_SCALE), 446 * OG_SCALE, (int)(($left + $width) * OG_SCALE), 496 * OG_SCALE, color($img, $hex));
            $left += $width + $gap;

            $labelWidth = textWidth(OG_REGULAR, 18, $label) + 22;
            if ($legendX + $labelWidth <= OG_WIDTH - 80) {
                imagefilledrectangle($img, (int)($legendX * OG_SCALE), 512 * OG_SCALE, (int)(($legendX + 12) * OG_SCALE), 524 * OG_SCALE, color($img, $hex));
                text($img, OG_REGULAR, 18, $legendX + 20, 526, color($img, '#dcddde', 50), $label);
            }
            $legendX += $labelWidth + 18;
        }
    }

    // ...and at the bottom the address and, on the right, how old the data is
    function ogFoot($img, $address, $note)
    {
        text($img, OG_MONO, 22, 80, 584, color($img, '#9b59b6'), $address, 2);
        text($img, OG_REGULAR, 18, OG_WIDTH - 80, 584, color($img, '#dcddde', 70), $note, 0, 'right');
    }

    // $text cut with "…" until it is at most $max pixels wide
    function ogFit($font, $size, $text, $max)
    {
        if (textWidth($font, $size, $text) <= $max)
            return $text;
        while ($text !== '' && textWidth($font, $size, $text . '…') > $max)
            $text = mb_substr($text, 0, -1);

        return rtrim($text) . '…';
    }

    // how wide text() draws $string, in the picture's own pixels
    function textWidth($font, $size, $string, $spacing = 0)
    {
        $box = imagettfbbox($size * OG_SCALE, 0, $font, $string);

        return ($box[2] - $box[0] + (mb_strlen($string) - 1) * $spacing * OG_SCALE) / OG_SCALE;
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

    // a rectangle in the picture's own pixels
    function box($img, $x1, $y1, $x2, $y2, $color)
    {
        imagefilledrectangle($img, (int)round($x1 * OG_SCALE), (int)round($y1 * OG_SCALE), (int)round($x2 * OG_SCALE), (int)round($y2 * OG_SCALE), $color);
    }

    // a circle in the picture's own pixels
    function circle($img, $cx, $cy, $diameter, $color)
    {
        $d = (int)round($diameter * OG_SCALE);
        imagefilledellipse($img, (int)round($cx * OG_SCALE), (int)round($cy * OG_SCALE), $d, $d, $color);
    }

    // a filled polygon from [[x, y], ...] in the picture's own pixels
    function shape($img, $points, $color)
    {
        $flat = [];
        foreach ($points as [$x, $y]) {
            $flat[] = (int)round($x * OG_SCALE);
            $flat[] = (int)round($y * OG_SCALE);
        }
        imagefilledpolygon($img, $flat, $color);
    }

    // a straight line $width wide, in the picture's own pixels
    function line($img, $x1, $y1, $x2, $y2, $width, $color)
    {
        $length = max(0.001, hypot($x2 - $x1, $y2 - $y1));
        $nx = -($y2 - $y1) / $length * $width / 2;
        $ny = ($x2 - $x1) / $length * $width / 2;
        shape($img, [[$x1 + $nx, $y1 + $ny], [$x2 + $nx, $y2 + $ny], [$x2 - $nx, $y2 - $ny], [$x1 - $nx, $y1 - $ny]], $color);
    }

    // the outline of a rectangle, $width thick
    function frame($img, $x1, $y1, $x2, $y2, $width, $color)
    {
        box($img, $x1, $y1, $x2, $y1 + $width, $color);
        box($img, $x1, $y2 - $width, $x2, $y2, $color);
        box($img, $x1, $y1, $x1 + $width, $y2, $color);
        box($img, $x2 - $width, $y1, $x2, $y2, $color);
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

    // The logo (sanakan.jpg) in a purple ring with a soft glow, centred at $cx,
    // $cy, and with $status the Discord status dot cut into its lower right edge,
    // as Discord shows it on an avatar
    function ogLogo($img, $cx, $cy, $r, $status = null)
    {
        $logo = @imagecreatefromjpeg(__DIR__ . '/../sanakan.jpg');
        if ($logo === false)
            return false;

        for ($i = 0; $i < 10; $i++) {
            $size = (int)((2 * $r + 50 - $i * 4) * OG_SCALE);
            imagefilledellipse($img, $cx * OG_SCALE, $cy * OG_SCALE, $size, $size, color($img, '#9b59b6', 122 - $i));
        }
        $ring = (int)((2 * $r + 10) * OG_SCALE);
        imagefilledellipse($img, $cx * OG_SCALE, $cy * OG_SCALE, $ring, $ring, color($img, '#9b59b6'));

        // the logo scaled to the circle, copied in only inside it
        $d = 2 * $r * OG_SCALE;
        $scaled = imagecreatetruecolor($d, $d);
        imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $d, $d, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
        $left = ($cx - $r) * OG_SCALE;
        $top = ($cy - $r) * OG_SCALE;
        $r2 = ($d / 2) ** 2;
        for ($y = 0; $y < $d; $y++)
            for ($x = 0; $x < $d; $x++)
                if (($x - $d / 2 + 0.5) ** 2 + ($y - $d / 2 + 0.5) ** 2 <= $r2)
                    imagesetpixel($img, $left + $x, $top + $y, imagecolorat($scaled, $x, $y));
        imagedestroy($scaled);

        if ($status !== null) {
            $background = color($img, OG_BACKGROUND);
            $dotX = (int)round($cx + $r * 0.707);
            $dotY = (int)round($cy + $r * 0.707);
            $dotR = (int)round($r * 0.2);
            $cut = 2 * ($dotR + 9) * OG_SCALE;
            imagefilledellipse($img, $dotX * OG_SCALE, $dotY * OG_SCALE, $cut, $cut, $background);
            statusDot($img, $dotX, $dotY, $dotR, $status, $background);
        }

        return true;
    }
