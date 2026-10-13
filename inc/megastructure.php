<?php
    // The Net Terminal Gene, the hardest secret of the Safeguard's register, and
    // the two on its way. In BLAME! everyone looks for the gene; here it has
    // fallen apart into four fragments of a sequence:
    //   1. the Builders' door: on the 404 page, only from 3:00 to 3:07 at night
    //      or from 15:00 to 15:07 in the afternoon, for whoever cannot stay up
    //      (Polish time, the server's clock), a door opens; behind it, the first
    //      fragment and the Builders' stairs going down (a find, "builders");
    //   2. floor zero: the stairs are floors, /pietro/<n>, each a 404 page,
    //      and around a flight of them a draught comes from its shaft, from
    //      above or below, the stronger the nearer; the number in the address is
    //      changed until the shaft is found, which drops to the next flight,
    //      and the last one's to floor zero, with the second fragment (a find,
    //      "floor0") and a maze. Every account has its own flights
    //      (megaFlights());
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
    // fragments, the floors, the maze and the word all come from
    // SAFEGUARD_SECRET in inc/config.php, and only megastructure.php gives them
    // out, each where it belongs. Without the secret there is no gene to find,
    // and without an account no way to it: the stairs are an account's own,
    // so a floor passed on leads nobody else anywhere.
    //
    // A find is noted in the browser (js/safeguard.js), where anyone could note
    // any of them from the console, so every find of the register is the
    // server's to give: each answer that makes one carries a proof of it, and
    // account.php notes it for an account only with one (megaProvenFinds()).
    // The finds of the way to the gene are made here; the others the server
    // witnesses as well as it can (megaWitness()). Floor zero's maze is never
    // given whole either: a walk in it shows only what is near its end.

    const GENE_FRAGMENTS = 4;
    // the Builders' hours, Polish time: from each HOUR:00 for MINUTES minutes
    const BUILDERS_HOURS = [3, 15];
    const BUILDERS_MINUTES = 7;
    // The stairs: MEGA_FLIGHTS flights of MEGA_FLIGHT_SPAN floors each, each
    // somewhere in a band of MEGA_FLIGHT_BAND floors of its own from
    // MEGA_FLIGHT_FIRST up, the first in the highest band, so they never meet
    // and each goes lower, down towards floor zero; the floors as the 404
    // page has them for any address (js/404.js). The draught of a flight's
    // shaft is felt in all of its band, so a visitor gone off the flight
    // still finds the way back.
    const MEGA_FLIGHTS = 3;
    const MEGA_FLIGHT_SPAN = 6000;
    const MEGA_FLIGHT_BAND = 3000000;
    const MEGA_FLIGHT_FIRST = 1000;
    // how strong the draught is, 0 to 7: one more for each of these floors
    // the shaft is nearer than
    const MEGA_DRAUGHT = [20000, 2000, 600, 200, 60, 20, 6];
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

    // An account's flights of the stairs, [['from' => its first floor,
    // 'start' => the floor it is come down to, 'shaft' => the floor of its
    // shaft]...]: its own, from the secret and its ID, the same every time.
    // The start is at least a third of the flight away from the shaft.
    function megaFlights($secret, $id)
    {
        $flights = [];
        $third = intdiv(MEGA_FLIGHT_SPAN, 3);
        for ($k = 0; $k < MEGA_FLIGHTS; $k++) {
            $label = 'flight:' . $id . ':' . $k;
            $from = megaBandFloor($k) + megaPick($secret, $label, 0, MEGA_FLIGHT_BAND - MEGA_FLIGHT_SPAN);
            $shaft = megaPick($secret, $label, 1, MEGA_FLIGHT_SPAN);
            $start = ($shaft + $third + megaPick($secret, $label, 2, $third)) % MEGA_FLIGHT_SPAN;
            $flights[] = ['from' => $from, 'start' => $from + $start, 'shaft' => $from + $shaft];
        }

        return $flights;
    }

    // the lowest floor of the band of flight k (from 0, the first)
    function megaBandFloor($k)
    {
        return MEGA_FLIGHT_FIRST + (MEGA_FLIGHTS - 1 - $k) * MEGA_FLIGHT_BAND;
    }

    // the flight whose band a floor is in, or null when none
    function megaFlightOf($floor)
    {
        if ($floor < MEGA_FLIGHT_FIRST || $floor >= MEGA_FLIGHT_FIRST + MEGA_FLIGHTS * MEGA_FLIGHT_BAND)
            return null;

        return MEGA_FLIGHTS - 1 - intdiv($floor - MEGA_FLIGHT_FIRST, MEGA_FLIGHT_BAND);
    }

    // the address of a floor of the stairs
    function megaFloorPath($floor)
    {
        return '/pietro/' . $floor;
    }

    // the floor of an address of the stairs, "/pietro/<n>", or null
    function megaFloor($path)
    {
        return preg_match('~^/pietro/(\d{1,9})$~D', megaPath($path), $m) ? (int)$m[1] : null;
    }

    // the draught on a floor of a flight's band: ['from' => 'below' (a lower
    // floor, a smaller number) or 'above', 'strength' => 0 to 7, the nearer
    // the shaft the stronger]
    function megaDraught($floor, $shaft)
    {
        $far = abs($shaft - $floor);
        $strength = 0;
        foreach (MEGA_DRAUGHT as $near)
            if ($far < $near)
                $strength++;

        return ['from' => $shaft < $floor ? 'below' : 'above', 'strength' => $strength];
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

    // ---- The way to the gene, in its order -------------------------------------------
    // Each step of the way is open to whoever knows its answer, the console's
    // word, the sequence, so an account notes one only after those before it
    // (megaChainFinds()), and one made sooner after them than anyone could
    // flags it (safeguardCheat()): key => [the finds before it, the least
    // seconds after the last of them].
    const MEGA_CHAIN = [
        // three flights of the stairs, each shaft found floor by floor, a page each
        'floor0' => [['builders'], 30],
        // the maze walked to its terminal, then its word in the console or a page printed
        'maze' => [['floor0'], 20],
        'paper' => [['floor0'], 20],
        // the four fragments typed to the terminal
        'gene' => [['builders', 'floor0', 'maze', 'paper'], 5],
        // the record deciphered with the letters the register gives
        'admin' => [['gene'], 30]
    ];

    // The finds of the way to the gene an account may note, of $finds, key =>
    // time (megaClaimProofs()): each only once the account has those before it
    // ($had, its finds so far) or notes them with it, from no later. Returns
    // [the finds, the keys left out].
    function megaChainFinds($finds, $had)
    {
        $left = [];
        // in the order of the way, so a step left out leaves out those after it
        foreach (MEGA_CHAIN as $key => [$before]) {
            if (!isset($finds[$key]))
                continue;
            foreach ($before as $need) {
                $at = min($finds[$need] ?? PHP_INT_MAX, $had[$need] ?? PHP_INT_MAX);
                if ($at > $finds[$key]) {
                    unset($finds[$key]);
                    $left[] = $key;
                    break;
                }
            }
        }

        return [$finds, $left];
    }

    // ---- What the server witnesses -------------------------------------------------
    // Most finds happen in the browser alone, where the server cannot see them
    // made, so it checks what it can before it gives their proof (?witness=):
    //   - a seasonal find, its day (Polish time, give or take MEGA_DAY_SLACK
    //     hours for the browsers of other time zones);
    //   - the idle home page, the Emitter held, the logo broken and a wait at
    //     a closed door, the time they took, from a ticket the browser got
    //     when they began (?ticket=), each ticket good for one find only
    //     (megaSpendTicket());
    //   - what the failure page hides, the snake, the server plays again
    //     (snakeReplay());
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
        'denied' => ['denied', 170, 86400]
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
    // door), snake (a game of the failure page, whose food its nonce places)
    // or killy, which only the slots Killy walks in give (always in the
    // preview). Null when there is none.
    function megaTicket($secret, $kind, $now, $preview = false)
    {
        $time = $now->getTimestamp();
        if (!in_array($kind, ['idle', 'gbe', 'killy', 'denied', 'snake'], true) || ($kind === 'killy' && !$preview && !megaKillyWalks($secret, $time)))
            return null;

        return megaProof($secret, 'ticket:' . $kind, $time);
    }

    // A ticket is good for one use, $use (a find, or "score"), so one passed
    // on gives nobody else anything: inc/data/tickets.json keeps "<use>:<ticket>"
    // => [the ticket's time, ID] while a ticket that old could count. $id is
    // the account using it, '' for none; only that account may use it again.
    // False when it was used already, or the file could not be saved.
    function megaSpendTicket($use, $ticket, $since, $now, $id = '')
    {
        $id = (string)$id;
        $oldest = $now - max(SNAKE_LIFE, max(array_column(MEGA_TICKETS, 2)));
        $saved = updateData('tickets', function (&$all) use ($use, $ticket, $since, $id, $oldest) {
            foreach ($all as $name => $entry)
                if (!is_array($entry) || (int)($entry[0] ?? 0) < $oldest)
                    unset($all[$name]);
            $name = $use . ':' . $ticket;
            if (isset($all[$name]))
                return $id !== '' && (string)($all[$name][1] ?? '') === $id;
            $all[$name] = [$since, $id];

            return true;
        }, $spent);

        return $saved && $spent === true;
    }

    // ---- The snake of the failure page -----------------------------------------------
    // The snake of the 500 page (js/500.js) is played in the browser, so the
    // server plays the game again before it believes it: its food falls where
    // the nonce of the game's ticket says, by the same xorshift as the page's,
    // the page sends the way the snake went, tick by tick, and the server
    // counts what it ate, and that the game took no less time than its ticks.
    // A score typed in, or a game made up and sent at once, gives nothing; a
    // program playing it in real time still would, which no server can tell
    // from a hand. The best game of an account is kept in inc/data/snake.json,
    // ID => ['best', 'time'].
    const SNAKE_COLS = 20;
    const SNAKE_ROWS = 16;
    // fed so many times, the snake is a find of the register
    const SNAKE_FED = 15;
    // a tick's milliseconds: START_MS, FASTER_MS less for each eaten, FASTEST_MS at the least
    const SNAKE_START_MS = 140;
    const SNAKE_FASTER_MS = 3;
    const SNAKE_FASTEST_MS = 70;
    const SNAKE_TICKS_MAX = 20000;
    // the most a game's ticket may be old, pauses and all
    const SNAKE_LIFE = 86400;

    // the seed of a game's food, from the nonce of its ticket; null without one
    function snakeSeed($ticket)
    {
        if (!is_string($ticket) || !preg_match('/^\d{1,12}\.([0-9a-f]{16})\.[0-9a-f]{32}$/D', $ticket, $m))
            return null;

        return hexdec(substr($m[1], 0, 8)) ?: 1;
    }

    // the next number of a xorshift, 0 to 2^32 - 1, as next() in js/500.js
    function snakeNext(&$x)
    {
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0xFFFFFFFF;

        return $x;
    }

    // where the food falls: a free cell, counted column by column, as place() in js/500.js
    function snakePlace($taken, &$rng)
    {
        $free = [];
        for ($x = 0; $x < SNAKE_COLS; $x++)
            for ($y = 0; $y < SNAKE_ROWS; $y++)
                if (!isset($taken[$x . ',' . $y]))
                    $free[] = [$x, $y];

        return $free[snakeNext($rng) % count($free)];
    }

    // A game played again from its seed and its way, "R12D3…" (each direction
    // with the ticks it went so): ['eaten', 'ms' => the least time its ticks
    // take, 'over' => whether it has ended, 'food' => [x, y] where it lies
    // now], or null for a way the page could
    // not have sent (malformed, too long, a turn straight back, a tick after
    // the end).
    function snakeReplay($seed, $moves)
    {
        $moves = (string)$moves;
        if ($seed === null || strlen($moves) > SNAKE_TICKS_MAX || !preg_match('/^(?:[UDLR][1-9]\d{0,4})+$/D', $moves))
            return null;
        preg_match_all('/([UDLR])(\d+)/', $moves, $runs, PREG_SET_ORDER);

        $steps = ['U' => [0, -1], 'D' => [0, 1], 'L' => [-1, 0], 'R' => [1, 0]];
        $y = intdiv(SNAKE_ROWS, 2);
        $snake = [[6, $y], [5, $y], [4, $y]];
        $taken = ['6,' . $y => true, '5,' . $y => true, '4,' . $y => true];
        $rng = $seed;
        $food = snakePlace($taken, $rng);
        $dir = [1, 0];
        $eaten = 0;
        $ms = 0;
        $ticks = 0;
        $over = false;
        foreach ($runs as [, $way, $count]) {
            $next = $steps[$way];
            if ($next[0] === -$dir[0] && $next[1] === -$dir[1])
                return null;
            $dir = $next;
            for ($i = 0; $i < (int)$count; $i++) {
                if ($over || ++$ticks > SNAKE_TICKS_MAX)
                    return null;
                if ($ticks > 1)
                    $ms += max(SNAKE_FASTEST_MS, SNAKE_START_MS - $eaten * SNAKE_FASTER_MS);
                $head = [$snake[0][0] + $dir[0], $snake[0][1] + $dir[1]];
                $grows = $head === $food;
                if (!$grows)
                    unset($taken[implode(',', array_pop($snake))]);
                if ($head[0] < 0 || $head[1] < 0 || $head[0] >= SNAKE_COLS || $head[1] >= SNAKE_ROWS || isset($taken[implode(',', $head)])) {
                    $over = true;
                    continue;
                }
                array_unshift($snake, $head);
                $taken[implode(',', $head)] = true;
                if ($grows) {
                    $eaten++;
                    if (count($snake) === SNAKE_COLS * SNAKE_ROWS)
                        $over = true;
                    else
                        $food = snakePlace($taken, $rng);
                }
            }
        }

        return ['eaten' => $eaten, 'ms' => $ms, 'over' => $over, 'food' => $food];
    }

    // the best game of an account, how many it ate, 0 without one
    function snakeBest($id)
    {
        $entry = readData('snake')[(string)$id] ?? null;

        return is_array($entry) ? (int)($entry['best'] ?? 0) : 0;
    }

    // the best games of SNAKE_FED and more, ID => how many it ate, which the
    // ranking of the hunters shows by who found the snake (hunters/)
    function snakeRecords()
    {
        $records = [];
        foreach (readData('snake') as $id => $entry)
            if (is_array($entry) && (int)($entry['best'] ?? 0) >= SNAKE_FED)
                $records[(string)$id] = (int)$entry['best'];

        return $records;
    }

    // keeps a game of an account when it is its best; false when it could not be saved
    function noteSnakeGame($id, $eaten, $now)
    {
        if ($eaten <= snakeBest($id))
            return true;

        return updateData('snake', function (&$all) use ($id, $eaten, $now) {
            $id = (string)$id;
            if (!is_array($all[$id] ?? null) || $eaten > (int)($all[$id]['best'] ?? 0))
                $all[$id] = ['best' => $eaten, 'time' => $now];
        });
    }

    // What megastructure.php answers to a game of the snake ($query: 'snake',
    // its way, and 'ticket'): ['eaten'], with the find's proof the first time
    // a game of the ticket is fed SNAKE_FED times, and for an account ($id)
    // its 'best'; ['denied' => true] for a game the server does not believe.
    function snakeAnswer($secret, array $query, $now, $preview = false, $id = '')
    {
        $time = $now->getTimestamp();
        $ticket = $query['ticket'] ?? null;
        $since = megaProofTime($secret, 'ticket:snake', $ticket);
        $game = $since !== null && $since <= $time && $time - $since <= SNAKE_LIFE ? snakeReplay(snakeSeed($ticket), $query['snake']) : null;
        // played no faster than its ticks, give or take the second the ticket's time is cut to
        if ($game === null || (!$preview && ($time - $since + 1) * 1000 < $game['ms'] * 0.95))
            return ['denied' => true];

        $answer = ['eaten' => $game['eaten']];
        if ($game['eaten'] >= SNAKE_FED && megaSpendTicket('snake', $ticket, $since, $time))
            $answer['proof'] = megaProof($secret, 'snake', $time);
        if ((string)$id !== '') {
            if ($game['eaten'] > 0 && megaSpendTicket('score', $ticket, $since, $time, $id))
                noteSnakeGame($id, $game['eaten'], $time);
            $answer['best'] = snakeBest($id);
        }

        return $answer;
    }

    // The answer of megastructure.php to one question of its query:
    //   at=<address>  the 404 page: whether the door is open, on a floor of
    //                 the account's stairs the draught, or its shaft, the
    //                 next flight's floor, or floor zero (with what is seen
    //                 from the maze's way in), the archive
    //   door          the door opened: the first fragment and the stairs' first floor
    //   walk=<moves>  a walk in the maze, with zero=<the proof of floor zero>:
    //                 what is seen where it ends, and the word for the console
    //                 when that is the terminal
    //   ask=<word>    a word said in the console: the third fragment
    //   paper=<word>  the same word, from a page about to be printed: the fourth
    //   gene=<code>   the sequence given to the terminal: right or not
    //   enter=<code>  the gene given to enter the Netsphere: the archive's address
    //   record=<text> the archive's last record, deciphered: right or not
    //   snake=<way>   a game of the snake, with its ticket (snakeAnswer())
    //   ticket=<kind> when something began (megaTicket()), for the snake with
    //                 the account's best game
    //   witness=<key> a find made in the browser (megaWitness()), a ticket's
    //                 once (megaSpendTicket())
    // Each answer that makes a find carries its proof (megaProof()). Anything
    // else, or no secret, is an empty answer; only text counts in the query.
    // $id is the account asking, '' for none: the way to the gene is an
    // account's, so without one the 404 page only learns whether the door is
    // open, and the rest of the way answers ['stranger' => true].
    function megastructureAnswer($secret, array $query, $now, $preview = false, $id = '')
    {
        if ((string)$secret === '')
            return [];
        $query = array_filter($query, 'is_string');
        $id = (string)$id;
        $proof = function ($key) use ($secret, $now) {
            return megaProof($secret, $key, $now->getTimestamp());
        };
        $way = ['door', 'walk', 'ask', 'paper', 'gene', 'enter', 'record'];
        if ($id === '' && array_intersect_key($query, array_flip($way)) && !isset($query['at']))
            return ['stranger' => true];

        if (isset($query['at'])) {
            $answer = ['door' => buildersHour($now)];
            if ($id === '')
                return $answer;
            $path = megaPath($query['at']);
            $floor = megaFloor($path);
            $k = $floor === null ? null : megaFlightOf($floor);
            if ($k !== null) {
                $flights = megaFlights($secret, $id);
                $flight = $flights[$k];
                if ($floor !== $flight['shaft'])
                    $answer['draught'] = megaDraught($floor, $flight['shaft']);
                elseif ($k < MEGA_FLIGHTS - 1)
                    $answer['shaft'] = megaFloorPath($flights[$k + 1]['start']);
                else {
                    $maze = megaMaze($secret);
                    $answer['zero'] = [
                        'fragment' => geneFragment($secret, 2),
                        'maze' => ['width' => strlen($maze['rows'][0]), 'height' => count($maze['rows']), 'start' => $maze['start'], 'sight' => megaSight($secret, $maze['start'])],
                        'proof' => $proof('floor0')
                    ];
                }
            }
            if ($path === megaArchivePath($secret)) {
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
                ? ['fragment' => geneFragment($secret, 1), 'stairs' => megaFloorPath(megaFlights($secret, $id)[0]['start']), 'proof' => $proof('builders')]
                : ['closed' => true];

        if (isset($query['walk'])) {
            // only from floor zero, whose proof the walk carries
            $zero = megaProofTime($secret, 'floor0', $query['zero'] ?? null);
            if ($zero === null || $zero > $now->getTimestamp() || $zero < $now->getTimestamp() - SAFEGUARD_FIND_DAYS * 86400)
                return ['denied' => true];
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

        // before ticket=, which a game and a witness carry
        if (isset($query['snake']))
            return snakeAnswer($secret, $query, $now, $preview, $id);

        if (isset($query['witness'])) {
            $key = $query['witness'];
            $witnessed = megaWitness($secret, $query, $now, $preview);
            // a ticket gives its find once
            if ($witnessed !== null && isset(MEGA_TICKETS[$key])
                && !megaSpendTicket($key, $query['ticket'], megaProofTime($secret, 'ticket:' . MEGA_TICKETS[$key][0], $query['ticket']), $now->getTimestamp()))
                $witnessed = null;

            return $witnessed !== null ? ['proof' => $witnessed] : ['denied' => true];
        }

        if (isset($query['ticket'])) {
            $ticket = megaTicket($secret, $query['ticket'], $now, $preview);
            if ($ticket === null)
                return ['none' => true];

            return $query['ticket'] === 'snake' && (string)$id !== '' ? ['ticket' => $ticket, 'best' => snakeBest($id)] : ['ticket' => $ticket];
        }

        return [];
    }
