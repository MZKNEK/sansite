<?php
    // The Net Terminal Gene, the hardest secret of the Safeguard's register, and
    // the two on its way. In BLAME! everyone looks for the gene; here it has
    // fallen apart into four fragments of a sequence:
    //   1. the Builders' door: on the 404 page, only from 3:00 to 3:07 at night
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

    const GENE_FRAGMENTS = 4;
    // the corridor's addresses before floor zero
    const MEGA_ROUTE_STEPS = 5;
    // the Builders' hour, Polish time: from HOUR:00 for MINUTES minutes
    const BUILDERS_HOUR = 3;
    const BUILDERS_MINUTES = 7;
    // the first part of the corridor's addresses, as the places of the Megastructure
    const MEGA_ROUTE_WORDS = ['szyb', 'korytarz', 'sektor', 'poziom', 'wezel', 'przejscie', 'kanal', 'tunel'];
    // floor zero's maze, in cells; drawn twice as many characters plus one
    const MEGA_MAZE_W = 25;
    const MEGA_MAZE_H = 9;
    // the longest walk the maze takes, in steps
    const MEGA_WALK_MAX = 4000;

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

    // whether the Builders' door is open at a time (a DateTimeImmutable)
    function buildersHour($now)
    {
        $warsaw = $now->setTimezone(new DateTimeZone('Europe/Warsaw'));

        return (int)$warsaw->format('G') === BUILDERS_HOUR && (int)$warsaw->format('i') < BUILDERS_MINUTES;
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

    // The answer of megastructure.php to one question of its query:
    //   at=<address>  the 404 page: whether the door is open, the draught of a
    //                 corridor's address, floor zero and its maze
    //   door          the door opened: the first fragment and the corridor
    //   walk=<moves>  the maze walked: the word for the console
    //   ask=<word>    a word said in the console: the third fragment
    //   paper=<word>  the same word, from a page about to be printed: the fourth
    //   gene=<code>   the sequence given to the terminal: right or not
    // Anything else, or no secret, is an empty answer.
    function megastructureAnswer($secret, array $query, $now)
    {
        if ((string)$secret === '')
            return [];

        if (isset($query['at'])) {
            $route = megaRoute($secret);
            $step = array_search(megaPath($query['at']), $route, true);
            $answer = ['door' => buildersHour($now)];
            if ($step !== false && $step < MEGA_ROUTE_STEPS)
                $answer['draught'] = $route[$step + 1];
            elseif ($step === MEGA_ROUTE_STEPS)
                $answer['zero'] = ['fragment' => geneFragment($secret, 2), 'maze' => megaMaze($secret)];

            return $answer;
        }

        if (isset($query['door']))
            return buildersHour($now)
                ? ['fragment' => geneFragment($secret, 1), 'corridor' => megaRoute($secret)[0]]
                : ['closed' => true];

        if (isset($query['walk']))
            return megaWalk($secret, $query['walk'])
                ? ['word' => megaConsoleWord($secret)]
                : ['reached' => false];

        if (isset($query['ask']))
            return strtolower(trim((string)$query['ask'])) === megaConsoleWord($secret)
                ? ['fragment' => geneFragment($secret, 3)]
                : ['known' => false];

        if (isset($query['paper']))
            return strtolower(trim((string)$query['paper'])) === megaConsoleWord($secret)
                ? ['fragment' => geneFragment($secret, 4)]
                : ['known' => false];

        if (isset($query['gene']))
            return ['ok' => hash_equals(megaCode(geneSequence($secret)), megaCode($query['gene']))];

        return [];
    }
