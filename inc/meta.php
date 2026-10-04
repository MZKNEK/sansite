<?php
    // Link previews (Open Graph): what Discord and other apps show for a pasted
    // link: title, description, the logo (or a big picture of the page's own,
    // $image as [url, width, height]) and the purple stripe (theme-color).
    // The static pages (index.html, api/) have the same tags written out.
    const SITE_URL = 'https://sanakan.pl';

    function metaTags($title, $description, $path, $image = null)
    {
        $image = $image ?? [SITE_URL . '/sanakan.jpg', 500, 500];
        $tags = [
            ['name', 'description', $description],
            ['name', 'theme-color', '#9b59b6'],
            ['property', 'og:type', 'website'],
            ['property', 'og:site_name', 'Sanakan'],
            ['property', 'og:locale', 'pl_PL'],
            ['property', 'og:title', $title],
            ['property', 'og:description', $description],
            ['property', 'og:url', SITE_URL . $path],
            ['property', 'og:image', $image[0]],
            ['property', 'og:image:width', (string)$image[1]],
            ['property', 'og:image:height', (string)$image[2]],
            ['name', 'twitter:card', $image[1] > $image[2] ? 'summary_large_image' : 'summary']
        ];

        $html = '';
        foreach ($tags as $tag)
            $html .= '  <meta ' . $tag[0] . '="' . $tag[1] . '" content="' . htmlspecialchars($tag[2], ENT_QUOTES, 'UTF-8') . '" />' . "\n";

        return $html;
    }
