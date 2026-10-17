<?php
    // The songs of WAP for songs.php (wapSongs()), and the title and the
    // artist written in a song file's own tags: ID3v2 (2.2 to 2.4) and,
    // without it, ID3v1 of an MP3, the Vorbis comments of Ogg (Vorbis, Opus)
    // and FLAC, the iTunes atoms of M4A. Only what leads to the two is read; a
    // cover picture is stepped over. No mbstring or iconv: UTF-16 and Latin-1
    // become UTF-8 here.

    const TAG_TEXT_MAX = 65536;      // a text frame or atom longer than this is skipped
    const TAG_COMMENTS_MAX = 1048576; // the comments read at most (a cover may be among them)
    const SONG_TYPES = ['mp3', 'ogg', 'oga', 'opus', 'm4a', 'aac', 'flac', 'wav', 'webm'];

    // ---- the list

    // Every audio file put straight in $dir, in natural order, with its title
    // and artist: from its tags, and what they lack from its name ("Artist -
    // Title.mp3" gives both; a leading "1. ", "01 - " or a bare "04 ", two
    // digits so "7 Rings" stays, is a track number and goes; underscores are
    // spaces). The tags are read once a file: kept in inc/data/wap-tags.json
    // with its size and time, read again only when one of them changes, and
    // the file is written only when the list does.
    function wapSongs($dir)
    {
        $files = [];
        foreach (@scandir($dir) ?: [] as $name) {
            if ($name[0] !== '.' && is_file($dir . '/' . $name)
                && in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), SONG_TYPES, true))
                $files[] = $name;
        }
        natcasesort($files);

        $known = readData('wap-tags');
        $kept = [];
        $songs = [];
        foreach ($files as $name) {
            $path = $dir . '/' . $name;
            $stamp = filesize($path) . '-' . filemtime($path);
            $entry = $known[$name] ?? null;
            $tags = is_array($entry) && ($entry['stamp'] ?? null) === $stamp
                ? ['title' => (string)($entry['title'] ?? ''), 'artist' => (string)($entry['artist'] ?? '')]
                : songTags($path);
            $kept[$name] = ['stamp' => $stamp] + $tags;

            $label = trim(str_replace('_', ' ', pathinfo($name, PATHINFO_FILENAME)));
            $label = preg_replace('/^(?:\d{1,3}\s*[.\-]|\d{2})\s+(?=\S)/u', '', $label);
            $parts = explode(' - ', $label, 2);
            $songs[] = [
                'file' => 'song.php?f=' . rawurlencode($name),
                'title' => $tags['title'] !== '' ? $tags['title'] : (count($parts) === 2 ? trim($parts[1]) : $label),
                'artist' => $tags['artist'] !== '' ? $tags['artist'] : (count($parts) === 2 ? trim($parts[0]) : '')
            ];
        }
        // a list that could not be kept is read again next time
        if ($kept !== $known)
            updateData('wap-tags', function (&$data) use ($kept) { $data = $kept; });

        return $songs;
    }

    // ['title' => ..., 'artist' => ...], each '' when the file says nothing;
    // the album's artist stands in for a missing artist
    function songTags($path)
    {
        $tags = ['title' => '', 'artist' => '', 'band' => ''];
        $file = @fopen($path, 'rb');
        if ($file === false)
            return ['title' => '', 'artist' => ''];

        $at = 0;
        $head = (string)fread($file, 12);
        if (substr($head, 0, 3) === 'ID3') {
            $at = id3v2Tags($file, $tags);
            fseek($file, $at);
            $head = (string)fread($file, 12);
        }
        if (substr($head, 0, 4) === 'fLaC')
            flacTags($file, $at + 4, $tags);
        else if (substr($head, 0, 4) === 'OggS')
            oggTags($file, $at, $tags);
        else if (substr($head, 4, 4) === 'ftyp')
            mp4Tags($file, $tags);
        if ($tags['title'] === '' && $tags['artist'] === '')
            id3v1Tags($file, $tags);
        fclose($file);

        return ['title' => $tags['title'], 'artist' => $tags['artist'] !== '' ? $tags['artist'] : $tags['band']];
    }

    // the first value of each tag counts; several in one (ID3v2.4 splits them
    // with NUL) are joined with commas
    function setTag(array &$tags, $key, $text)
    {
        if ($tags[$key] !== '')
            return;
        if (!preg_match('//u', $text))
            $text = latin1Text($text);
        $parts = array_filter(array_map('trim', explode("\0", $text)), 'strlen');
        $tags[$key] = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', implode(', ', $parts)));
    }

    // ---- text

    function utf8Char($code)
    {
        if ($code < 0x80)
            return chr($code);
        if ($code < 0x800)
            return chr(0xC0 | $code >> 6) . chr(0x80 | $code & 0x3F);
        if ($code < 0x10000)
            return chr(0xE0 | $code >> 12) . chr(0x80 | $code >> 6 & 0x3F) . chr(0x80 | $code & 0x3F);

        return chr(0xF0 | $code >> 18) . chr(0x80 | $code >> 12 & 0x3F) . chr(0x80 | $code >> 6 & 0x3F) . chr(0x80 | $code & 0x3F);
    }

    function latin1Text($text)
    {
        return preg_replace_callback('/[\x80-\xFF]/', function ($match) { return utf8Char(ord($match[0])); }, $text);
    }

    // UTF-16, its byte order from a BOM (each string of a frame may have one)
    // or $bigEndian; a NUL between strings stays one
    function utf16Text($text, $bigEndian)
    {
        $out = '';
        $high = 0;
        for ($i = 0; $i + 1 < strlen($text); $i += 2) {
            $unit = $bigEndian ? ord($text[$i]) << 8 | ord($text[$i + 1]) : ord($text[$i + 1]) << 8 | ord($text[$i]);
            if ($unit === 0xFEFF)
                continue;
            if ($unit === 0xFFFE) {
                $bigEndian = !$bigEndian;
                continue;
            }
            if ($unit >= 0xD800 && $unit < 0xDC00) {
                $high = $unit;
                continue;
            }
            if ($unit >= 0xDC00 && $unit < 0xE000) {
                if ($high)
                    $out .= utf8Char(0x10000 + ($high - 0xD800 << 10) + ($unit - 0xDC00));
                $high = 0;
                continue;
            }
            $out .= $unit === 0 ? "\0" : utf8Char($unit);
        }

        return $out;
    }

    // ---- ID3

    function synchsafe($bytes)
    {
        $value = 0;
        foreach (str_split($bytes) as $byte)
            $value = $value << 7 | ord($byte) & 0x7F;

        return $value;
    }

    function bigEndian($bytes)
    {
        $value = 0;
        foreach (str_split($bytes) as $byte)
            $value = $value << 8 | ord($byte);

        return $value;
    }

    // the text of a frame by its first byte: Latin-1, UTF-16 with a BOM,
    // UTF-16BE, UTF-8
    function id3Text($data)
    {
        $encoding = ord($data[0] ?? "\0");
        $text = (string)substr($data, 1);
        if ($encoding === 1 || $encoding === 2)
            return utf16Text($text, $encoding === 2);

        return $encoding === 3 ? $text : latin1Text($text);
    }

    // the ID3v2 tag at the start of $file; returns where the audio begins.
    // A tag unsynchronised as a whole (v2.2, v2.3, rare) is not read.
    function id3v2Tags($file, array &$tags)
    {
        fseek($file, 0);
        $header = (string)fread($file, 10);
        if (strlen($header) < 10)
            return 0;
        $version = ord($header[3]);
        $flags = ord($header[5]);
        $end = 10 + synchsafe(substr($header, 6, 4));
        $audio = $end + ($version === 4 && $flags & 0x10 ? 10 : 0);
        if ($version < 2 || $version > 4 || ($flags & 0x80 && $version < 4))
            return $audio;

        $at = 10;
        if ($flags & 0x40 && $version > 2) {
            $size = (string)fread($file, 4);
            $at += $version === 4 ? synchsafe($size) : 4 + bigEndian($size);
        }
        $wanted = $version === 2
            ? ['TT2' => 'title', 'TP1' => 'artist', 'TP2' => 'band']
            : ['TIT2' => 'title', 'TPE1' => 'artist', 'TPE2' => 'band'];
        $headerSize = $version === 2 ? 6 : 10;

        while ($at + $headerSize <= $end && ($tags['title'] === '' || $tags['artist'] === '')) {
            fseek($file, $at);
            $frame = (string)fread($file, $headerSize);
            if (strlen($frame) < $headerSize || $frame[0] === "\0")
                break;
            if ($version === 2) {
                $id = substr($frame, 0, 3);
                $size = bigEndian(substr($frame, 3, 3));
                $frameFlags = 0;
            } else {
                $id = substr($frame, 0, 4);
                $size = $version === 4 ? synchsafe(substr($frame, 4, 4)) : bigEndian(substr($frame, 4, 4));
                $frameFlags = ord($frame[9]);
            }
            if ($size <= 0 || $at + $headerSize + $size > $end)
                break;
            $at += $headerSize + $size;
            if (!isset($wanted[$id]) || $size > TAG_TEXT_MAX)
                continue;

            $data = (string)fread($file, $size);
            if (strlen($data) < $size)
                break;
            if ($version === 3) {
                if ($frameFlags & 0xC0)
                    continue;
                if ($frameFlags & 0x20)
                    $data = substr($data, 1);
            } else if ($version === 4) {
                if ($frameFlags & 0x0C)
                    continue;
                if ($frameFlags & 0x40)
                    $data = substr($data, 1);
                if ($frameFlags & 0x01)
                    $data = substr($data, 4);
                if ($frameFlags & 0x02)
                    $data = str_replace("\xFF\x00", "\xFF", $data);
            }
            setTag($tags, $wanted[$id], id3Text($data));
        }

        return $audio;
    }

    // the old 128 bytes at the end of an MP3
    function id3v1Tags($file, array &$tags)
    {
        if (fstat($file)['size'] < 128 || fseek($file, -128, SEEK_END) !== 0)
            return;
        $tag = (string)fread($file, 128);
        if (substr($tag, 0, 3) !== 'TAG')
            return;
        setTag($tags, 'title', latin1Text(rtrim(substr($tag, 3, 30), "\0 ")));
        setTag($tags, 'artist', latin1Text(rtrim(substr($tag, 33, 30), "\0 ")));
    }

    // ---- Vorbis comments (Ogg, FLAC)

    function littleEndian32($data, $at)
    {
        return strlen($data) >= $at + 4 ? unpack('V', $data, $at)[1] : -1;
    }

    // the comments: a vendor string, their count, then "KEY=value" each with
    // its length; a list cut short (TAG_COMMENTS_MAX) is read as far as it goes
    function vorbisComments($data, array &$tags)
    {
        $vendor = littleEndian32($data, 0);
        if ($vendor < 0)
            return;
        $at = 4 + $vendor;
        $count = littleEndian32($data, $at);
        $at += 4;
        $keys = ['TITLE' => 'title', 'ARTIST' => 'artist', 'ALBUMARTIST' => 'band', 'ALBUM ARTIST' => 'band'];
        for ($i = 0; $i < $count; $i++) {
            $length = littleEndian32($data, $at);
            if ($length < 0 || $at + 4 + $length > strlen($data))
                return;
            $field = explode('=', substr($data, $at + 4, $length), 2);
            $at += 4 + $length;
            $key = $keys[strtoupper($field[0])] ?? null;
            if ($key !== null && isset($field[1]))
                setTag($tags, $key, $field[1]);
        }
    }

    // FLAC's metadata blocks from $at, the comments in the one of type 4
    function flacTags($file, $at, array &$tags)
    {
        for ($block = 0; $block < 64; $block++) {
            fseek($file, $at);
            $header = (string)fread($file, 4);
            if (strlen($header) < 4)
                return;
            $length = bigEndian(substr($header, 1, 3));
            if ((ord($header[0]) & 0x7F) === 4) {
                vorbisComments((string)fread($file, min($length, TAG_COMMENTS_MAX)), $tags);
                return;
            }
            if (ord($header[0]) & 0x80)
                return;
            $at += 4 + $length;
        }
    }

    // An Ogg stream's second packet holds the comments ("\x03vorbis" or
    // "OpusTags" before them). Pages give packets in segments; one shorter
    // than 255 bytes ends a packet.
    function oggTags($file, $at, array &$tags)
    {
        $packet = '';
        $index = 0;
        for ($page = 0; $page < 512; $page++) {
            fseek($file, $at);
            $header = (string)fread($file, 27);
            if (strlen($header) < 27 || substr($header, 0, 4) !== 'OggS')
                return;
            $segments = array_values(unpack('C*', (string)fread($file, ord($header[26]))) ?: []);
            $data = (string)fread($file, array_sum($segments));
            $at += 27 + count($segments) + array_sum($segments);
            $offset = 0;
            foreach ($segments as $length) {
                if ($index === 1)
                    $packet .= substr($data, $offset, $length);
                $offset += $length;
                if ($length < 255 || strlen($packet) >= TAG_COMMENTS_MAX) {
                    if ($index === 1 || strlen($packet) >= TAG_COMMENTS_MAX) {
                        $skip = substr($packet, 0, 7) === "\x03vorbis" ? 7 : (substr($packet, 0, 8) === 'OpusTags' ? 8 : -1);
                        if ($skip > 0)
                            vorbisComments(substr($packet, $skip), $tags);
                        return;
                    }
                    $index++;
                }
            }
        }
    }

    // ---- MP4 (M4A)

    // [start, end] of the inside of the first $type atom between $start and
    // $end, or null
    function mp4Atom($file, $start, $end, $type)
    {
        for ($at = $start; $at + 8 <= $end;) {
            fseek($file, $at);
            $header = (string)fread($file, 16);
            if (strlen($header) < 8)
                return null;
            $size = bigEndian(substr($header, 0, 4));
            $inside = 8;
            if ($size === 1) {
                $size = bigEndian(substr($header, 8, 8));
                $inside = 16;
            } else if ($size === 0) {
                $size = $end - $at;
            }
            if ($size < $inside)
                return null;
            if (substr($header, 4, 4) === $type)
                return [$at + $inside, min($end, $at + $size)];
            $at += $size;
        }

        return null;
    }

    // moov > udta > meta (4 bytes of version first) > ilst > ©nam, ©ART, aART,
    // each with a data atom: 8 bytes of type and locale, then the UTF-8 text
    function mp4Tags($file, array &$tags)
    {
        $box = [0, fstat($file)['size']];
        foreach (['moov', 'udta', 'meta', 'ilst'] as $type) {
            $box = mp4Atom($file, $box[0], $box[1], $type);
            if ($box === null)
                return;
            if ($type === 'meta')
                $box[0] += 4;
        }
        foreach (["\xA9nam" => 'title', "\xA9ART" => 'artist', 'aART' => 'band'] as $type => $key) {
            $item = mp4Atom($file, $box[0], $box[1], $type);
            $data = $item === null ? null : mp4Atom($file, $item[0], $item[1], 'data');
            if ($data === null || $data[1] - $data[0] <= 8 || $data[1] - $data[0] > TAG_TEXT_MAX)
                continue;
            fseek($file, $data[0] + 8);
            setTag($tags, $key, (string)fread($file, $data[1] - $data[0] - 8));
        }
    }
