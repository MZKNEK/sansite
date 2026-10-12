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
    // any of them from the console. The finds of the way to the gene are the
    // server's to give: each answer that makes one carries a proof of it, and
    // account.php notes them for an account only with one (megaProvenFinds()).

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

    // SAFEGUARD_SECRET, or '' without one
    function safeguardSecret()
    {
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

    // whether a walk, "RRDL…" (up, down, left, right), goes from the way in
    // through the corridors only and ends at the terminal
    function megaWalk($secret, $moves)
    {
        $moves = strtoupper((string)$moves);
        if ($moves === '' || strlen($moves) > MEGA_WALK_MAX || !preg_match('/^[UDLR]+$/', $moves))
            return false;

        $maze = megaMaze($secret);
        $steps = ['U' => [0, -1], 'D' => [0, 1], 'L' => [-1, 0], 'R' => [1, 0]];
        [$x, $y] = $maze['start'];
        foreach (str_split($moves) as $move) {
            $x += $steps[$move][0];
            $y += $steps[$move][1];
            if (($maze['rows'][$y][$x] ?? '#') !== ' ')
                return false;
        }

        return [$x, $y] === $maze['end'];
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
    // Each answer of megastructureAnswer() that makes one of these finds
    // carries 'proof', "<time>.<signature>", the server's time and the find
    // signed with the secret. The browser keeps it with the find and sends it
    // with it; the time of the proof is the time of the find, so none comes
    // earlier than the server gave it (the ranking puts who got there first
    // ahead). A proof can be passed on, as the gene itself can; it only keeps
    // the register from taking a find the server never gave.
    const MEGA_PROVEN_FINDS = ['builders', 'floor0', 'maze', 'paper', 'gene', 'admin'];

    function megaProof($secret, $key, $time)
    {
        return $time . '.' . substr(megaHash($secret, 'proof:' . $key . ':' . $time), 0, 32);
    }

    // the time of a find's proof, or null when the server did not give it
    function megaProofTime($secret, $key, $proof)
    {
        if ((string)$secret === '' || !is_string($proof) || !preg_match('/^(\d{1,12})\.[0-9a-f]{32}$/', $proof, $m))
            return null;

        return hash_equals(megaProof($secret, $key, (int)$m[1]), $proof) ? (int)$m[1] : null;
    }

    // The finds of a browser, key => time, as account.php notes them, with
    // their proofs, key => proof: those of MEGA_PROVEN_FINDS only with a right
    // proof and at its time, the rest as they are. Returns [the finds, the
    // keys left out], so the browser can forget those.
    function megaProvenFinds($secret, $found, $proofs)
    {
        if (!is_array($found))
            return [[], []];
        $proofs = is_array($proofs) ? $proofs : [];
        $finds = [];
        $left = [];
        foreach ($found as $key => $time) {
            $key = (string)$key;
            if (in_array($key, MEGA_PROVEN_FINDS, true)) {
                $time = megaProofTime($secret, $key, $proofs[$key] ?? null);
                if ($time === null) {
                    $left[] = $key;
                    continue;
                }
            }
            $finds[$key] = $time;
        }

        return [$finds, $left];
    }

    // The answer of megastructure.php to one question of its query:
    //   at=<address>  the 404 page: whether the door is open, the draught of a
    //                 corridor's address, floor zero and its maze, the archive
    //   door          the door opened: the first fragment and the corridor
    //   walk=<moves>  the maze walked: the word for the console
    //   ask=<word>    a word said in the console: the third fragment
    //   paper=<word>  the same word, from a page about to be printed: the fourth
    //   gene=<code>   the sequence given to the terminal: right or not
    //   enter=<code>  the gene given to enter the Netsphere: the archive's address
    //   record=<text> the archive's last record, deciphered: right or not
    // Each answer that makes a find carries its proof (megaProof()). Anything
    // else, or no secret, is an empty answer.
    function megastructureAnswer($secret, array $query, $now)
    {
        if ((string)$secret === '')
            return [];
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
            elseif ($step === MEGA_ROUTE_STEPS)
                $answer['zero'] = ['fragment' => geneFragment($secret, 2), 'maze' => megaMaze($secret), 'proof' => $proof('floor0')];
            elseif ($path === megaArchivePath($secret)) {
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

        if (isset($query['walk']))
            return megaWalk($secret, $query['walk'])
                ? ['word' => megaConsoleWord($secret)]
                : ['reached' => false];

        if (isset($query['ask']))
            return strtolower(trim((string)$query['ask'])) === megaConsoleWord($secret)
                ? ['fragment' => geneFragment($secret, 3), 'proof' => $proof('maze')]
                : ['known' => false];

        if (isset($query['paper']))
            return strtolower(trim((string)$query['paper'])) === megaConsoleWord($secret)
                ? ['fragment' => geneFragment($secret, 4), 'proof' => $proof('paper')]
                : ['known' => false];

        if (isset($query['gene']))
            return hash_equals(megaCode(geneSequence($secret)), megaCode($query['gene']))
                ? ['ok' => true, 'proof' => $proof('gene')]
                : ['ok' => false];

        return [];
    }
