<?php
    // A small Markdown renderer for the changelog the bot keeps in its
    // repository (verdiff.md, state/wersje/). It is deliberately tiny and
    // dependency-free, like the rest of the site: headings, paragraphs, ordered
    // and unordered lists, fenced code, blockquotes, rules, bold, italic,
    // strikethrough, inline code, links, and an @nick of an account the site
    // knows, drawn as the avatar and the nick (the page passes the accounts,
    // mentionUsers() in inc/verdiff.php). The text is escaped first, so a
    // changelog cannot inject HTML; only http(s), mailto and relative links
    // survive.

    // whether a link target is one a page may follow
    function markdownLinkAllowed($url)
    {
        return (bool)preg_match('~^(https?://|mailto:|/|#)~i', (string)$url);
    }

    // an avatar, the nick and the rank of an account the site knows, for its @nick
    function markdownMention($handle, $user)
    {
        $avatar = (string)($user['avatar'] ?? '');
        $name = htmlspecialchars((string)($user['name'] ?? $handle), ENT_QUOTES, 'UTF-8');
        $image = $avatar === ''
            ? ''
            : '<img src="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt="" width="16" height="16" loading="lazy" />';
        $badge = $user['badge'] ?? null;

        return '<span class="mention" title="@' . htmlspecialchars($handle, ENT_QUOTES, 'UTF-8') . '">'
            . $image . $name . ($badge === null ? '' : markdownMentionRank($badge)) . '</span>';
    }

    // the rank of an @nick as one filled tag, e.g. "LV.9 dev"
    function markdownMentionRank($badge)
    {
        $key = preg_replace('/[^A-Za-z]/', '', (string)($badge['key'] ?? 'none'));
        $level = (int)($badge['level'] ?? 0);
        $text = $level > 0
            ? 'LV.' . $level . (($badge['name'] ?? '') !== '' ? ' ' . $badge['name'] : '')
            : (string)($badge['label'] ?? '');

        return '<span class="lv role-' . $key . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    // Inline pieces of one line: `code`, @nick, [text](url), **bold**, *italic*,
    // ~~gone~~. $mentions maps a lower-case handle to ['name', 'avatar']; an
    // @nick with no entry stays plain text.
    function markdownInline($text, array $mentions = [])
    {
        // inline code first, kept aside so the rules below do not touch it
        $code = [];
        $text = preg_replace_callback('/`([^`]+)`/', function ($match) use (&$code) {
            $code[] = '<code>' . $match[1] . '</code>';
            return "\x00" . (count($code) - 1) . "\x00";
        }, $text);

        // The mentions next: a handle the site knows becomes a chip, kept aside
        // like the code, so the emphasis and link rules below do not reach the
        // handle or the chip (a handle may hold an underscore, which the italic
        // rule would otherwise take).
        $chips = [];
        if ($mentions) {
            $text = preg_replace_callback('/(?<![\w.@])@([A-Za-z0-9_](?:[A-Za-z0-9._]{0,29}[A-Za-z0-9_])?)/', function ($match) use (&$chips, $mentions) {
                $user = $mentions[strtolower($match[1])] ?? null;
                if ($user === null)
                    return $match[0];

                $chips[] = markdownMention($match[1], $user);
                return "\x01" . (count($chips) - 1) . "\x01";
            }, $text);
        }

        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($match) {
            $url = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');

            return markdownLinkAllowed($url) ? '<a href="' . $match[2] . '" rel="noopener nofollow">' . $match[1] . '</a>' : $match[0];
        }, $text);

        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text);
        $text = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $text);
        $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_([^_]+)_/', '<em>$1</em>', $text);

        foreach ($code as $i => $html)
            $text = str_replace("\x00" . $i . "\x00", $html, $text);
        foreach ($chips as $i => $html)
            $text = str_replace("\x01" . $i . "\x01", $html, $text);

        return $text;
    }

    // a changelog's Markdown as HTML; $mentions as markdownInline() takes them
    function markdownToHtml($text, array $mentions = [])
    {
        $text = htmlspecialchars(str_replace(["\r\n", "\r"], "\n", (string)$text), ENT_QUOTES, 'UTF-8');
        $lines = explode("\n", $text);
        $count = count($lines);
        $out = '';
        $paragraph = [];
        $flush = function () use (&$paragraph, &$out, $mentions) {
            if ($paragraph) {
                $out .= '<p>' . markdownInline(implode(' ', $paragraph), $mentions) . "</p>\n";
                $paragraph = [];
            }
        };

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            if (preg_match('/^\s*```/', $line)) {
                $flush();
                $code = [];
                for ($i++; $i < $count && !preg_match('/^\s*```/', $lines[$i]); $i++)
                    $code[] = $lines[$i];
                $out .= '<pre><code>' . implode("\n", $code) . "</code></pre>\n";
                continue;
            }

            if (trim($line) === '') {
                $flush();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $match)) {
                $flush();
                $level = strlen($match[1]);
                $out .= "<h$level>" . markdownInline($match[2], $mentions) . "</h$level>\n";
                continue;
            }

            if (preg_match('/^\s*([-*_])\1{2,}\s*$/', $line)) {
                $flush();
                $out .= "<hr />\n";
                continue;
            }

            if (preg_match('/^\s*(?:&gt;|>)\s?(.*)$/', $line)) {
                $flush();
                $quote = [];
                while ($i < $count && preg_match('/^\s*(?:&gt;|>)\s?(.*)$/', $lines[$i], $match)) {
                    $quote[] = $match[1];
                    $i++;
                }
                $out .= '<blockquote>' . markdownInline(implode(' ', $quote), $mentions) . "</blockquote>\n";
                $i--;
                continue;
            }

            if (preg_match('/^\s*[-*+]\s+(.*)$/', $line)) {
                $flush();
                $items = [];
                while ($i < $count && preg_match('/^\s*[-*+]\s+(.*)$/', $lines[$i], $match)) {
                    $items[] = $match[1];
                    $i++;
                }
                $out .= markdownItems('ul', $items, $mentions);
                $i--;
                continue;
            }

            if (preg_match('/^\s*\d+[.)]\s+(.*)$/', $line)) {
                $flush();
                $items = [];
                while ($i < $count && preg_match('/^\s*\d+[.)]\s+(.*)$/', $lines[$i], $match)) {
                    $items[] = $match[1];
                    $i++;
                }
                $out .= markdownItems('ol', $items, $mentions);
                $i--;
                continue;
            }

            $paragraph[] = trim($line);
        }
        $flush();

        return trim($out);
    }

    function markdownItems($tag, $items, array $mentions = [])
    {
        $out = "";
        foreach ($items as $item)
            $out .= "\n  <li>" . markdownInline($item, $mentions) . "</li>";

        return "<$tag>$out\n</$tag>\n";
    }
