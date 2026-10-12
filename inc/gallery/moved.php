<?php
    // The old links of files changed to WebP or WebM. Part of the gallery,
    // loaded by inc/gallery.php.

    // ---- Pictures changed to WebP -----------------------------------------------
    // A gallery admin can change a PNG, JPG or GIF to WebP; the original goes to
    // the trash and its old link keeps working: nginx sends a gallery picture
    // that is not there to i/index.php (server/nginx/sanakan.conf), which sends
    // it on to the WebP. The links are kept as links have them (publicRel()), so
    // the folder of an account may change its nick.

    // the old link of a picture now goes to the new one (links that went to the old one too)
    function addMovedLink($fromRel, $toRel)
    {
        $from = publicRel($fromRel);
        $to = publicRel($toRel);
        return updateData('moved', function (&$links) use ($from, $to) {
            foreach ($links as $old => $new)
                if ($new === $from)
                    $links[$old] = $to;
            $links[$from] = $to;
            unset($links[$to]);
        });
    }

    // after a move or rename the links follow the file, or everything in the folder
    function followMovedLinks($fromRel, $toRel)
    {
        $from = publicRel($fromRel);
        $to = publicRel($toRel);
        updateData('moved', function (&$links) use ($from, $to) {
            foreach ($links as $old => $new)
                if ($new === $from || strpos($new, $from . '/') === 0)
                    $links[$old] = $to . substr($new, strlen($from));
        });
    }

    // a picture that is not there: on to where it went, or a 404
    function sendMovedLink($rel)
    {
        $to = readData('moved')[trim((string)$rel, '/')] ?? null;
        if ($to === null) {
            http_response_code(404);
            return;
        }

        header('Cache-Control: public, max-age=3600');
        header('Location: ' . siteRoot() . 'i/' . implode('/', array_map('rawurlencode', explode('/', $to))), true, 302);
    }
