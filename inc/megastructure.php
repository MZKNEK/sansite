<?php
    // The Net Terminal Gene, the hardest secret of the Safeguard's register, and
    // the two on its way. In BLAME! everyone looks for the gene; here it has
    // fallen apart into four fragments of a sequence:
    //   1. the Builders' door: on the 404 page, only from 3:00 to 3:07 at night
    //      or from 15:00 to 15:07 in the afternoon, for whoever cannot stay up
    //      (Polish time, the server's clock), a door opens; behind it, the first
    //      fragment and the first address of a corridor (a find, "builders");
    //   2. floor zero: each address of the corridor, a 404 page, feels a draught
    //      from the next one; the last is floor zero, with the second fragment
    //      (a find, "floor0") and a maze;
    //   3. the maze walked to its terminal, deep in it: a word to say to the
    //      Safeguard in the browser's console, and safeguard('<that word>')
    //      there the third fragment (a find, "maze");
    //   4. the fourth fragment the Safeguard leaves only on paper: once the
    //      browser knows the word, any page printed (its preview is enough)
    //      shows it, and nothing on the screen does (a find, "paper").
    // The four together, "gen <sequence>" in the terminal of the Netsphere,
    // give the gene, the one time the Safeguard lets anyone in (a find, "gene").
    //
    // The repository is public, so nothing of it is written in the code: the
    // fragments, the addresses, the maze and the word all come from
    // SAFEGUARD_SECRET in inc/config.php, and only megastructure.php gives them
    // out, each where it belongs. Without the secret there is no gene to find.
    //
    // A find is noted in the browser (js/safeguard.js), where anyone could note
    // any of them from the console, so every find of the register is the
    // server's to give: each answer that makes one carries a proof of it, and
    // account.php notes it for an account only with one (megaProvenFinds()).
    // The finds of the way to the gene are made here; the others the server
    // witnesses as well as it can (megaWitness()). Floor zero's maze is never
    // given whole either: a walk in it shows only what is near its end.

    const GENE_FRAGMENTS = 4;
    // the corridor's addresses before floor zero
    const MEGA_ROUTE_STEPS = 5;
    // the Builders' hours, Polish time: from each HOUR:00 for MINUTES minutes
    const BUILDERS_HOURS = [3, 15];
    const BUILDERS_MINUTES = 7;
    // the first part of the corridor's addresses, as the places of the Megastructure
    const MEGA_ROUTE_WORDS = ['szyb', 'korytarz', 'sektor', 'poziom', 'wezel', 'przejscie', 'kanal', 'tunel'];
    // floor zero's maze, in cells; drawn twice as many characters plus one
    const MEGA_MAZE_W = 25;
    const MEGA_MAZE_H = 9;
    // the longest walk the maze takes, in steps
    const MEGA_WALK_MAX = 4000;
    // how far the visitor sees in the maze, in characters each way
    const MEGA_SIGHT = 3;

    // SAFEGUARD_SECRET, or '' without one; inc/config.php is read first
    function safeguardSecret()
    {
        authConfigured();

        return defined('SAFEGUARD_SECRET') ? (string)SAFEGUARD_SECRET : '';
    }

    function megaHash($secret, $label)
    {
        return hash_hmac('sha256', $label, $secret);
    }

    // a number from 0 to n - 1, the i-th of a label
    function megaPick($secret, $label, $i, $n)
    {
        return hexdec(substr(megaHash($secret, $label . ':' . $i), 0, 7)) % $n;
    }

    // fragment n (1 to 4): four characters, 0-9 and A-F
    function geneFragment($secret, $n)
    {
        return strtoupper(substr(megaHash($secret, 'fragment:' . $n), 0, 4));
    }

    // "7C2E-91AF-0D3B-55E0"
    function geneSequence($secret)
    {
        $fragments = [];
        for ($n = 1; $n <= GENE_FRAGMENTS; $n++)
            $fragments[] = geneFragment($secret, $n);

        return implode('-', $fragments);
    }

    // the corridor: MEGA_ROUTE_STEPS addresses, then floor zero, the last
    function megaRoute($secret)
    {
        $route = [];
        for ($i = 0; $i <= MEGA_ROUTE_STEPS; $i++) {
            $hash = megaHash($secret, 'route:' . $i);
            $route[] = '/' . MEGA_ROUTE_WORDS[hexdec(substr($hash, 0, 2)) % count(MEGA_ROUTE_WORDS)] . '/' . substr($hash, 2, 6);
        }

        return $route;
    }

    // what the Safeguard answers to in the console: five small letters
    function megaConsoleWord($secret)
    {
        $word = '';
        for ($i = 0; $i < 5; $i++)
            $word .= chr(97 + megaPick($secret, 'console', $i, 26));

        return $word;
    }

    // Floor zero's maze: ['rows' => lines of '#' and ' ', 'start' => [x, y],
    // the way in on its left edge, 'end' => [x, y], the terminal, the cell
    // farthest from the way in]. Dug from the way in by a depth-first walk, so
    // there is one way between any two places.
    function megaMaze($secret)
    {
        static $made = [];
        if (isset($made[$secret]))
            return $made[$secret];

        $w = MEGA_MAZE_W;
        $h = MEGA_MAZE_H;
        $grid = array_fill(0, 2 * $h + 1, array_fill(0, 2 * $w + 1, '#'));
        $startY = megaPick($secret, 'maze-start', 0, $h);
        $seen = ['0,' . $startY => true];
        $stack = [[0, $startY]];
        $grid[2 * $startY + 1][1] = ' ';
        $n = 0;
        while ($stack) {
            [$cx, $cy] = $stack[count($stack) - 1];
            $next = [];
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $cx + $dx;
                $ny = $cy + $dy;
                if ($nx >= 0 && $ny >= 0 && $nx < $w && $ny < $h && !isset($seen[$nx . ',' . $ny]))
                    $next[] = [$dx, $dy];
            }
            if (!$next) {
                array_pop($stack);
                continue;
            }
            [$dx, $dy] = $next[megaPick($secret, 'maze', $n++, count($next))];
            $nx = $cx + $dx;
            $ny = $cy + $dy;
            $seen[$nx . ',' . $ny] = true;
            $grid[2 * $cy + 1 + $dy][2 * $cx + 1 + $dx] = ' ';
            $grid[2 * $ny + 1][2 * $nx + 1] = ' ';
            $stack[] = [$nx, $ny];
        }
        $grid[2 * $startY + 1][0] = ' ';
        $start = [0, 2 * $startY + 1];

        // the terminal: the cell the longest way from the way in
        $far = [$start, 0];
        $dist = [$start[0] . ',' . $start[1] => 0];
        $queue = [$start];
        for ($i = 0; $i < count($queue); $i++) {
            [$x, $y] = $queue[$i];
            $d = $dist[$x . ',' . $y];
            if ($x % 2 === 1 && $y % 2 === 1 && $d > $far[1])
                $far = [[$x, $y], $d];
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;
                if (($grid[$ny][$nx] ?? '#') === ' ' && !isset($dist[$nx . ',' . $ny])) {
                    $dist[$nx . ',' . $ny] = $d + 1;
                    $queue[] = [$nx, $ny];
                }
            }
        }

        return $made[$secret] = ['rows' => array_map('implode', $grid), 'start' => $start, 'end' => $far[0]];
    }

    // where a walk, "RRDL…" (up, down, left, right), from the way in through
    // the corridors only ends, [x, y], or null when it goes through a wall;
    // an empty walk stays at the way in
    function megaWalkTo($secret, $moves)
    {
        $moves = strtoupper((string)$moves);
        if (strlen($moves) > MEGA_WALK_MAX || !preg_match('/^[UDLR]*$/', $moves))
            return null;

        $maze = megaMaze($secret);
        $steps = ['U' => [0, -1], 'D' => [0, 1], 'L' => [-1, 0], 'R' => [1, 0]];
        [$x, $y] = $maze['start'];
        foreach ($moves === '' ? [] : str_split($moves) as $move) {
            $x += $steps[$move][0];
            $y += $steps[$move][1];
            if (($maze['rows'][$y][$x] ?? '#') !== ' ')
                return null;
        }

        return [$x, $y];
    }

    // whether a walk goes through the corridors to the terminal
    function megaWalk($secret, $moves)
    {
        return (string)$moves !== '' && megaWalkTo($secret, $moves) === megaMaze($secret)['end'];
    }

    // What is seen from a place of the maze: ['x', 'y' of the top left corner,
    // 'rows' => up to MEGA_SIGHT characters each way, cut at the edges], with
    // 'end' => [x, y] when the terminal is among them
    function megaSight($secret, $at)
    {
        $maze = megaMaze($secret);
        $x0 = max(0, $at[0] - MEGA_SIGHT);
        $y0 = max(0, $at[1] - MEGA_SIGHT);
        $x1 = min(strlen($maze['rows'][0]) - 1, $at[0] + MEGA_SIGHT);
        $y1 = min(count($maze['rows']) - 1, $at[1] + MEGA_SIGHT);
        $rows = [];
        for ($y = $y0; $y <= $y1; $y++)
            $rows[] = substr($maze['rows'][$y], $x0, $x1 - $x0 + 1);
        $sight = ['x' => $x0, 'y' => $y0, 'rows' => $rows];
        [$ex, $ey] = $maze['end'];
        if ($ex >= $x0 && $ex <= $x1 && $ey >= $y0 && $ey <= $y1)
            $sight['end'] = $maze['end'];

        return $sight;
    }

    // whether the Builders' door is open at a time (a DateTimeImmutable), in
    // one of their hours
    function buildersHour($now)
    {
        $warsaw = $now->setTimezone(new DateTimeZone('Europe/Warsaw'));

        return in_array((int)$warsaw->format('G'), BUILDERS_HOURS, true) && (int)$warsaw->format('i') < BUILDERS_MINUTES;
    }

    // an address as the 404 page shows it, to compare: small letters, no
    // slash at the end
    function megaPath($path)
    {
        $path = strtolower(trim((string)$path));

        return $path === '/' ? $path : rtrim($path, '/');
    }

    // a sequence as typed: only its 0-9 and A-F, in capitals
    function megaCode($code)
    {
        return preg_replace('/[^0-9A-F]/', '', strtoupper((string)$code));
    }

    // ---- Past the gene ---------------------------------------------------------------
    // The one who has the gene may enter the Netsphere ("netsfera" in the
    // terminal), an address only the gene opens: the archive of its
    // Administration, with its last record ciphered. Each letter of the key
    // is given as a place in what the register says of the finds on the way to
    // the gene, which whoever got this far has; the record, deciphered and
    // given to the terminal, is a find of its own ("admin"), shown as ??? to
    // everyone else.

    // the words of the last record, without Polish letters, so the cipher
    // runs over A to Z alone
    const MEGA_RECORD_WORDS = [
        'MROK', 'SZYB', 'ECHO', 'RDZEN', 'KABEL', 'PYL', 'WIEZA', 'BRAMA', 'ZAPIS', 'SYGNAL', 'KLUCZ', 'PROMIEN',
        'CIEN', 'SZUM', 'PUSTKA', 'KANAL', 'TUNEL', 'WEZEL', 'SEKTOR', 'POZIOM', 'GRANICA', 'SIEC', 'KOD', 'RDZA',
        'STAL', 'BETON', 'PIETRO', 'SCIANA', 'DRZWI', 'MOST', 'OTCHLAN', 'PRZEPUST', 'ZAMEK', 'KLATKA', 'WIDMO', 'ZNAK',
        'SLAD', 'IMIE', 'GLOS', 'CISZA', 'STRAZ', 'ROZKAZ', 'PAMIEC', 'ARCHIWUM', 'DOSTEP', 'ODMOWA', 'PROTOKOL', 'RDZENIE',
        'MASZYNA', 'BUDOWA', 'WIECZNOSC', 'POCZATEK', 'KONIEC', 'ZRODLO', 'SYNAPSA', 'PULS', 'ISKRA', 'POPIOL', 'SZKIELET', 'SKORUPA',
        'OKO', 'DLON', 'KREW', 'GEN'
    ];
    // the finds the key's letters are taken from: every one of them is on the
    // way to the gene, so whoever opened the archive has them all
    const MEGA_KEY_FINDS = ['builders', 'floor0', 'maze', 'paper', 'gene'];
    const MEGA_KEY_LENGTH = 6;
    const MEGA_RECORD_LENGTH = 4;

    // the archive's address, which only the gene opens
    function megaArchivePath($secret)
    {
        return '/netsfera/' . substr(megaHash($secret, 'archive'), 0, 8);
    }

    // the last record of the Administration, deciphered: four words
    function megaRecord($secret)
    {
        $words = [];
        for ($i = 0; $i < MEGA_RECORD_LENGTH; $i++)
            $words[] = MEGA_RECORD_WORDS[megaPick($secret, 'record', $i, count(MEGA_RECORD_WORDS))];

        return implode(' ', $words);
    }

    // The key: ['letters' => "QWERTY", 'places' => [[name of the find, word, letter], ...]],
    // each letter the given letter (from 1) of the given word (from 1) of what
    // the register says of that find once found, always one of A to Z.
    function megaKey($secret)
    {
        $letters = '';
        $places = [];
        for ($i = 0; $i < MEGA_KEY_LENGTH; $i++) {
            $key = MEGA_KEY_FINDS[megaPick($secret, 'key-find', $i, count(MEGA_KEY_FINDS))];
            [$name, $what] = SAFEGUARD_FINDS[$key];
            $spots = [];
            foreach (preg_split('/\s+/u', trim($what)) as $w => $word)
                foreach (mb_str_split($word) as $l => $char)
                    if (preg_match('/^[A-Za-z]$/', $char))
                        $spots[] = [$w + 1, $l + 1, strtoupper($char)];
            [$word, $letter, $char] = $spots[megaPick($secret, 'key-spot', $i, count($spots))];
            $letters .= $char;
            $places[] = [$name, $word, $letter];
        }

        return ['letters' => $letters, 'places' => $places];
    }

    // each letter of a text moved on by the letter of the key under it (A by
    // nothing, B by one…), the key going on over the letters only
    function megaCipher($text, $key)
    {
        $out = '';
        $k = 0;
        foreach (str_split($text) as $char) {
            if ($char >= 'A' && $char <= 'Z') {
                $char = chr(65 + (ord($char) - 65 + ord($key[$k % strlen($key)]) - 65) % 26);
                $k++;
            }
            $out .= $char;
        }

        return $out;
    }

    // a record as typed: capitals, one space between the words
    function megaWords($text)
    {
        return trim(preg_replace('/[^A-Z]+/', ' ', strtoupper((string)$text)));
    }

    // ---- Proofs of the finds ---------------------------------------------------------
    // Every find of the register needs a proof of it, "<time>.<nonce>.<signature>",
    // the server's time, a random nonce and the find signed with the secret,
    // given by the answer of megastructureAnswer() that makes it. The nonce
    // makes every proof given out its own, so two visitors making a find in
    // the same second do not get the same one, which only the first account
    // sending it could use (megaClaimProofs()). The browser keeps it with the
    // find and sends it with it, and account.php notes a find only with a right
    // proof, at its time, so none comes earlier than the server gave it (the
    // ranking puts who got there first ahead, and the Safeguard's check of
    // finds made at once, safeguardCheat(), counts on these times). A proof
    // counts for one account only (megaClaimProofs()).

    // the proof of a find at a time; a new nonce without one, and '' the proofs
    // given before the nonces, "<time>.<signature>", which still count
    function megaProof($secret, $key, $time, $nonce = null)
    {
        $nonce = $nonce ?? bin2hex(random_bytes(8));
        $signed = 'proof:' . $key . ':' . $time . ($nonce === '' ? '' : ':' . $nonce);

        return $time . '.' . ($nonce === '' ? '' : $nonce . '.') . substr(megaHash($secret, $signed), 0, 32);
    }

    // the time of a find's proof, or null when the server did not give it
    function megaProofTime($secret, $key, $proof)
    {
        if ((string)$secret === '' || !is_string($proof) || !preg_match('/^(\d{1,12})\.(?:([0-9a-f]{16})\.)?[0-9a-f]{32}$/D', $proof, $m))
            return null;

        return hash_equals(megaProof($secret, $key, (int)$m[1], $m[2] ?? ''), $proof) ? (int)$m[1] : null;
    }

    // The finds of a browser, key => time, as account.php notes them, with
    // their proofs, key => proof: only finds of the register (a ticket is
    // signed the same way, but is none), with a right proof, at its time, and
    // not older than SAFEGUARD_FIND_DAYS. Returns [the finds, the keys left
    // out], so the browser can forget those.
    function megaProvenFinds($secret, $found, $proofs, $now)
    {
        if (!is_array($found))
            return [[], []];
        $proofs = is_array($proofs) ? $proofs : [];
        $finds = [];
        $left = [];
        foreach (array_keys($found) as $key) {
            $key = (string)$key;
            $time = isset(SAFEGUARD_FINDS[$key]) || isset(SAFEGUARD_SEASONAL[$key]) ? megaProofTime($secret, $key, $proofs[$key] ?? null) : null;
            if ($time === null || $time > $now || $time < $now - SAFEGUARD_FIND_DAYS * 86400)
                $left[] = $key;
            else
                $finds[$key] = $time;
        }

        return [$finds, $left];
    }

    // A proof counts for the first account that sends it, so one passed on
    // gives nobody else the find: inc/data/proofs.json keeps proof => [ID,
    // time] while the proof is young enough to count. A find the account
    // already has from no later is not claimed again, so the browser sending
    // it once more adds nothing to the file. $finds are those of
    // megaProvenFinds(). Returns [the finds, the keys left out, as another
    // account has their proof], or null when it could not be saved.
    function megaClaimProofs($id, $finds, $proofs, $now)
    {
        $id = (string)$id;
        foreach (safeguardFinds($id) as $key => $time)
            if (isset($finds[$key]) && $time <= $finds[$key])
                unset($finds[$key]);
        $saved = updateData('proofs', function (&$all) use ($id, $finds, $proofs, $now) {
            foreach ($all as $sig => $entry)
                if (!is_array($entry) || (int)($entry[1] ?? 0) < $now - SAFEGUARD_FIND_DAYS * 86400)
                    unset($all[$sig]);
            $left = [];
            foreach ($finds as $key => $time) {
                $sig = (string)$proofs[$key];
                if (isset($all[$sig]) && (string)$all[$sig][0] !== $id) {
                    unset($finds[$key]);
                    $left[] = $key;
                } else {
                    $all[$sig] = [$id, $time];
                }
            }

            return [$finds, $left];
        }, $result);

        return $saved ? $result : null;
    }

    // ---- What the server witnesses -------------------------------------------------
    // Most finds happen in the browser alone, where the server cannot see them
    // made, so it checks what it can before it gives their proof (?witness=):
    //   - a seasonal find, its day (Polish time, give or take MEGA_DAY_SLACK
    //     hours for the browsers of other time zones);
    //   - the idle home page, the Emitter held, the logo broken, a wait at a
    //     closed door and what the failure page hides, the time they took, from a ticket the browser
    //     got when they began (?ticket=);
    //   - Killy, the server's own roll: a ticket only in one slot of
    //     MEGA_KILLY_SLOT seconds in MEGA_KILLY_CHANCE, the same for everyone;
    //   - the password of the X-Safeguard header, which comes from the secret.
    // The others (the scan, the console, the Konami code, the lore, the
    // network lost, the Morse code, the error pages) are as easy to make as to
    // fake; their proof only puts the server's time on them.

    // the seconds a ticket of a find has to be old before it counts, and the
    // most it may be
    const MEGA_TICKETS = [
        'idle' => ['idle', 170, 86400],
        // held 3 seconds, a second less for the way of the ticket
        'gbe' => ['gbe', 2, 120],
        // three shots in a row, each held 3 seconds, and the logo growing back
        'regen' => ['gbe', 10, 300],
        'killy' => ['killy', 0, 300],
        // left standing at a door nobody may pass, three minutes
        'denied' => ['denied', 170, 86400],
        // fifteen fed, a few seconds each at the least
        'snake' => ['snake', 12, 3600]
    ];
    const MEGA_KILLY_SLOT = 20;
    const MEGA_KILLY_CHANCE = 5;
    const MEGA_DAY_SLACK = 14;
    // the finds the server gives a proof of when asked
    const MEGA_PLAIN_FINDS = ['netsphere', 'lost', 'console', 'silicon', 'lore', 'offline', 'morse', 'unknown'];

    // the password of the X-Safeguard header, "toha-" and four characters of the secret
    function megaHeaderWord($secret)
    {
        return 'toha-' . substr(megaHash($secret, 'header'), 0, 4);
    }

    // The header itself, sent by account.php (which every static page asks)
    // and megastructure.php; nginx sends the rest of it, without the password
    // (server/nginx/sanakan-headers.conf). Nothing without the secret.
    function sendSafeguardHeader()
    {
        $secret = safeguardSecret();
        if ($secret !== '' && !headers_sent())
            header('X-Safeguard-Haslo-Awaryjne: ' . megaHeaderWord($secret));
    }

    // whether Killy walks in the slot of a time, the same for every visitor
    function megaKillyWalks($secret, $time)
    {
        return megaPick($secret, 'killy', intdiv($time, MEGA_KILLY_SLOT), MEGA_KILLY_CHANCE) === 0;
    }

    // whether it is a seasonal find's day at a time, in Poland or in a time
    // zone up to MEGA_DAY_SLACK hours away
    function megaSeason($key, $now)
    {
        foreach ([-MEGA_DAY_SLACK, 0, MEGA_DAY_SLACK] as $hours)
            if (in_array($key, safeguardHolidays($now->setTimezone(new DateTimeZone('Europe/Warsaw'))->modify(($hours >= 0 ? '+' : '') . $hours . ' hours')), true))
                return true;

        return false;
    }

    // The proof of a find the browser says it made, or null when the server
    // does not believe it: $query has 'witness' (the key) and, for some, the
    // 'ticket' or the 'word'. $preview skips the waiting and the days, to try
    // it out (tools/preview).
    function megaWitness($secret, array $query, $now, $preview = false)
    {
        $key = (string)$query['witness'];
        $time = $now->getTimestamp();
        if (in_array($key, MEGA_PLAIN_FINDS, true))
            $ok = true;
        elseif (isset(SAFEGUARD_SEASONAL[$key]))
            $ok = $preview || megaSeason($key, $now);
        elseif ($key === 'header')
            $ok = hash_equals(megaHeaderWord($secret), strtolower(trim((string)($query['word'] ?? ''))));
        elseif (isset(MEGA_TICKETS[$key])) {
            [$kind, $wait, $life] = MEGA_TICKETS[$key];
            $since = megaProofTime($secret, 'ticket:' . $kind, $query['ticket'] ?? null);
            $ok = $since !== null && $since <= $time && $time - $since <= $life && ($preview || $time - $since >= $wait);
        } else
            $ok = false;

        return $ok ? megaProof($secret, $key, $time) : null;
    }

    // A ticket of when something began: idle, gbe (an Emitter's shot held,
    // whose first of a row is the regen's too), denied (the wait at a closed
    // door), snake (what the failure page hides) or killy, which only the slots Killy walks in give (always in the
    // preview). Null when there is none.
    function megaTicket($secret, $kind, $now, $preview = false)
    {
        $time = $now->getTimestamp();
        if (!in_array($kind, ['idle', 'gbe', 'killy', 'denied', 'snake'], true) || ($kind === 'killy' && !$preview && !megaKillyWalks($secret, $time)))
            return null;

        return megaProof($secret, 'ticket:' . $kind, $time);
    }

    // The answer of megastructure.php to one question of its query:
    //   at=<address>  the 404 page: whether the door is open, the draught of a
    //                 corridor's address, floor zero (with what is seen from
    //                 the maze's way in), the archive
    //   door          the door opened: the first fragment and the corridor
    //   walk=<moves>  a walk in the maze: what is seen where it ends, and the
    //                 word for the console when that is the terminal
    //   ask=<word>    a word said in the console: the third fragment
    //   paper=<word>  the same word, from a page about to be printed: the fourth
    //   gene=<code>   the sequence given to the terminal: right or not
    //   enter=<code>  the gene given to enter the Netsphere: the archive's address
    //   record=<text> the archive's last record, deciphered: right or not
    //   ticket=<kind> when something began (megaTicket())
    //   witness=<key> a find made in the browser (megaWitness())
    // Each answer that makes a find carries its proof (megaProof()). Anything
    // else, or no secret, is an empty answer; only text counts in the query.
    function megastructureAnswer($secret, array $query, $now, $preview = false)
    {
        if ((string)$secret === '')
            return [];
        $query = array_filter($query, 'is_string');
        $proof = function ($key) use ($secret, $now) {
            return megaProof($secret, $key, $now->getTimestamp());
        };

        if (isset($query['at'])) {
            $path = megaPath($query['at']);
            $route = megaRoute($secret);
            $step = array_search($path, $route, true);
            $answer = ['door' => buildersHour($now)];
            if ($step !== false && $step < MEGA_ROUTE_STEPS)
                $answer['draught'] = $route[$step + 1];
            elseif ($step === MEGA_ROUTE_STEPS) {
                $maze = megaMaze($secret);
                $answer['zero'] = [
                    'fragment' => geneFragment($secret, 2),
                    'maze' => ['width' => strlen($maze['rows'][0]), 'height' => count($maze['rows']), 'start' => $maze['start'], 'sight' => megaSight($secret, $maze['start'])],
                    'proof' => $proof('floor0')
                ];
            } elseif ($path === megaArchivePath($secret)) {
                $key = megaKey($secret);
                $answer['archive'] = ['record' => megaCipher(megaRecord($secret), $key['letters']), 'key' => $key['places']];
            }

            return $answer;
        }

        if (isset($query['enter']))
            return hash_equals(megaCode(geneSequence($secret)), megaCode($query['enter']))
                ? ['archive' => megaArchivePath($secret)]
                : ['denied' => true];

        if (isset($query['record']))
            return hash_equals(megaRecord($secret), megaWords($query['record']))
                ? ['ok' => true, 'proof' => $proof('admin')]
                : ['ok' => false];

        if (isset($query['door']))
            return buildersHour($now)
                ? ['fragment' => geneFragment($secret, 1), 'corridor' => megaRoute($secret)[0], 'proof' => $proof('builders')]
                : ['closed' => true];

        if (isset($query['walk'])) {
            $at = megaWalkTo($secret, $query['walk']);
            if ($at === null)
                return ['reached' => false];
            $answer = ['sight' => megaSight($secret, $at)];
            if ($at === megaMaze($secret)['end'])
                $answer['word'] = megaConsoleWord($secret);

            return $answer;
        }

        if (isset($query['ask']))
            return strtolower(trim($query['ask'])) === megaConsoleWord($secret)
                ? ['fragment' => geneFragment($secret, 3), 'proof' => $proof('maze')]
                : ['known' => false];

        if (isset($query['paper']))
            return strtolower(trim($query['paper'])) === megaConsoleWord($secret)
                ? ['fragment' => geneFragment($secret, 4), 'proof' => $proof('paper')]
                : ['known' => false];

        if (isset($query['gene']))
            return hash_equals(megaCode(geneSequence($secret)), megaCode($query['gene']))
                ? ['ok' => true, 'proof' => $proof('gene')]
                : ['ok' => false];

        // before ticket=, which a witness may carry
        if (isset($query['witness'])) {
            $witnessed = megaWitness($secret, $query, $now, $preview);

            return $witnessed !== null ? ['proof' => $witnessed] : ['denied' => true];
        }

        if (isset($query['ticket'])) {
            $ticket = megaTicket($secret, $query['ticket'], $now, $preview);

            return $ticket !== null ? ['ticket' => $ticket] : ['none' => true];
        }

        return [];
    }
