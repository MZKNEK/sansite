<?php
    // Link previews (Open Graph): what Discord and other apps show for a pasted
    // link: title, description, the logo (or a big picture of the page's own,
    // $image as [url, width, height]) and the purple stripe (theme-color).
    // A null $description leaves out the text and the site name, so the
    // preview is only the title and the picture. The static page index.html
    // has such tags written out by hand.
    const SITE_URL = 'https://sanakan.pl';

    function metaTags($title, $description, $path, $image = null)
    {
        $image = $image ?? [SITE_URL . '/sanakan.jpg', 500, 500];
        $tags = [];
        if ($description !== null)
            $tags[] = ['name', 'description', $description];
        $tags[] = ['name', 'theme-color', '#9b59b6'];
        $tags[] = ['property', 'og:type', 'website'];
        if ($description !== null)
            $tags[] = ['property', 'og:site_name', 'Sanakan'];
        $tags[] = ['property', 'og:locale', 'pl_PL'];
        $tags[] = ['property', 'og:title', $title];
        if ($description !== null)
            $tags[] = ['property', 'og:description', $description];
        $tags = array_merge($tags, [
            ['property', 'og:url', SITE_URL . $path],
            ['property', 'og:image', $image[0]],
            ['property', 'og:image:width', (string)$image[1]],
            ['property', 'og:image:height', (string)$image[2]],
            ['name', 'twitter:card', $image[1] > $image[2] ? 'summary_large_image' : 'summary']
        ]);

        $html = '';
        foreach ($tags as $tag)
            $html .= '  <meta ' . $tag[0] . '="' . $tag[1] . '" content="' . htmlspecialchars($tag[2], ENT_QUOTES, 'UTF-8') . '" />' . "\n";

        return $html;
    }
