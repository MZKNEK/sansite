<?php
    // Link previews (Open Graph): what Discord and other apps show for a pasted
    // link: title, description, the logo and the purple stripe (theme-color).
    // The static pages (index.html, api/) have the same tags written out.
    const SITE_URL = 'https://sanakan.pl';

    function metaTags($title, $description, $path)
    {
        $tags = [
            ['name', 'description', $description],
            ['name', 'theme-color', '#9b59b6'],
            ['property', 'og:type', 'website'],
            ['property', 'og:site_name', 'Sanakan'],
            ['property', 'og:locale', 'pl_PL'],
            ['property', 'og:title', $title],
            ['property', 'og:description', $description],
            ['property', 'og:url', SITE_URL . $path],
            ['property', 'og:image', SITE_URL . '/sanakan.jpg'],
            ['property', 'og:image:width', '500'],
            ['property', 'og:image:height', '500'],
            ['name', 'twitter:card', 'summary']
        ];

        $html = '';
        foreach ($tags as $tag)
            $html .= '  <meta ' . $tag[0] . '="' . $tag[1] . '" content="' . htmlspecialchars($tag[2], ENT_QUOTES, 'UTF-8') . '" />' . "\n";

        return $html;
    }
