<?php
    // The middles of the figures of 0x40's pictures (hues/): the centres of
    // hues/.pack/respack.json (0 to 1 across, for a picture with no
    // transparency to find its figure by), set by hand with hues/centres.php.
    // Run locally, the tool writes them into respack.json, which respack.php
    // keeps and deploy.sh sends. On the site an owner's changes cannot go
    // there, as the next deploy would put the local file back over them, so
    // they are kept in inc/data/hues-centres.json ({ name: cx, or null for the
    // one hues.js finds }) and laid over the pack's whenever pack.php gives
    // out respack.json.

    // the pack's list as an array, with the site's centres over its own; null
    // when the file is missing or broken
    function huesPack($file, $withSite = true)
    {
        $pack = json_decode((string)@file_get_contents($file), true);
        if (!is_array($pack) || !isset($pack['images']) || !is_array($pack['images']))
            return null;
        $centres = is_array($pack['centres'] ?? null) ? $pack['centres'] : [];
        if ($withSite) {
            foreach (readData('hues-centres') as $name => $cx) {
                if ($cx === null)
                    unset($centres[$name]);
                else if (is_numeric($cx))
                    $centres[$name] = (float)$cx;
            }
        }
        $centres = array_intersect_key($centres, array_flip($pack['images']));
        uksort($centres, 'strnatcasecmp');
        $pack['centres'] = $centres;

        return $pack;
    }

    // a middle as kept: 0 to 1, to three places
    function huesCentreValue($cx)
    {
        return round(max(0, min(1, (float)$cx)), 3);
    }

    // an owner's change on the site: a middle, or null for the one found
    function setSiteHuesCentre($name, $cx)
    {
        return updateData('hues-centres', function (&$data) use ($name, $cx) {
            $data[(string)$name] = $cx === null ? null : huesCentreValue($cx);
            uksort($data, 'strnatcasecmp');
        });
    }

    // a change run locally, straight into respack.json, written as respack.php
    // writes it
    function setPackHuesCentre($file, $name, $cx)
    {
        $pack = huesPack($file, false);
        if ($pack === null)
            return false;
        if ($cx === null)
            unset($pack['centres'][$name]);
        else
            $pack['centres'][$name] = huesCentreValue($cx);
        uksort($pack['centres'], 'strnatcasecmp');
        $pack['centres'] = (object)$pack['centres'];
        $json = json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        $temp = $file . '.tmp';

        return file_put_contents($temp, $json) !== false && rename($temp, $file);
    }
