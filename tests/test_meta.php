<?php
    // inc/meta.php: the Open Graph tags of a page.

    test('metaTags draws the link preview tags', function () {
        $html = metaTags('Tytuł', 'Opis', '/i/', 'i');
        assertContains('property="og:title" content="Tytuł"', $html);
        assertContains('property="og:description" content="Opis"', $html);
        assertContains('property="og:url" content="https://sanakan.pl/i/"', $html);
        assertContains('og.php?p=i', $html);
        assertContains('content="1200"', $html);
        assertContains('summary_large_image', $html);
        assertContains('theme-color', $html);
    });

    test('metaTags without a description leaves the text out', function () {
        $html = metaTags('Tylko', null, '/x/', 'x');
        assertFalse(strpos($html, 'og:description') !== false);
        assertFalse(strpos($html, 'og:site_name') !== false);
        assertFalse(strpos($html, 'name="description"') !== false);
        assertContains('twitter:card', $html);
        assertContains('og:image', $html);
    });

    test('metaTags escapes a quote in the text', function () {
        assertContains('&quot;', metaTags('a"b', null, '/x/', 'x'));
    });
