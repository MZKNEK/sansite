<?php
    // Searching the admin panel (admin/?szukaj=...): accounts by their name,
    // @name or ID, and addresses, whole or by their start ("45.148."), among the
    // addresses of the accounts, the scanners, the blocked ones and the busiest
    // of the last 24 hours, each with the accounts that came from it. A search
    // for an account's address finds the account too. The accounts that match
    // whole come first, then the ones that start with it (accountSearchMatch());
    // each list shows SEARCH_SHOWN at a time, and "Pokaż więcej" (?ile=) more.
    // admin/index.php includes it after the login check, like the profile.
    if (!function_exists('accountCell')) {
        http_response_code(404);
        exit;
    }

    const SEARCH_SHOWN = 50;
    const SEARCH_SHOWN_MAX = 1000;

    $search = (function ($query, $shown) {
        $logins = readData('logins');
        $addresses = readData('addresses');
        $isAddress = filter_var($query, FILTER_VALIDATE_IP) !== false;
        $looksLikeAddress = $isAddress || (preg_match('/^[0-9a-f:.]+$/i', $query) && preg_match('/[.:]/', $query));
        $matches = function ($ip) use ($query, $isAddress) {
            return $isAddress ? sameAddress($query, $ip) : stripos((string)$ip, $query) === 0;
        };

        // every account the site knows of
        $ids = array_merge(array_keys($logins), array_keys($addresses), array_keys(readData('roles')));
        foreach (array_merge(array_keys(ACCESS_LISTS), ['PANEL_ADMINS']) as $list) {
            $config = configList(ACCESS_LISTS[$list] ?? $list);
            $ids = array_merge($ids, $config === true ? [] : $config, isset(ACCESS_LISTS[$list]) ? array_keys(panelList($list)) : []);
        }

        $accounts = [];
        $ranks = [];
        foreach (array_unique(array_map('strval', $ids)) as $id) {
            $match = null;
            if (!$looksLikeAddress)
                $match = accountSearchMatch($query, $id, $logins[$id] ?? []);
            if ($match === null && $looksLikeAddress)
                foreach (array_keys($addresses[$id] ?? []) as $ip)
                    if ($matches($ip)) {
                        $match = [$isAddress || (string)$ip === $query ? 0 : 1, 'adres ' . $ip];
                        break;
                    }
            if ($match !== null) {
                $ranks[$id] = $match[0];
                $accounts[$id] = $match[1];
            }
        }
        // the whole match first, then the start, then the rest, each in the order found
        $order = array_flip(array_keys($accounts));
        uksort($accounts, function ($a, $b) use ($ranks, $order) {
            return [$ranks[$a], $order[$a]] <=> [$ranks[$b], $order[$b]];
        });

        // the addresses, with what is known of each: requests in the last 24 h
        // (when among the busiest), country, user agent, most asked path, where found
        $found = [];
        $scanners = [];
        $cfItems = null;
        $cfError = null;
        if ($looksLikeAddress) {
            $add = function ($ip, $where, $info = []) use (&$found) {
                $ip = (string)$ip;
                $found[$ip] = ($found[$ip] ?? ['n' => 0, 'php' => 0, 'cc' => '', 'ua' => '', 'path' => '', 'where' => []]);
                foreach (['n', 'php', 'cc', 'ua', 'path'] as $key)
                    if (($info[$key] ?? '') !== '' && ($key === 'n' || $key === 'php' || $found[$ip][$key] === ''))
                        $found[$ip][$key] = $info[$key];
                $found[$ip]['where'][] = $where;
            };
            foreach (diagRecentAddresses() as $ip => $info)
                if ($matches($ip))
                    $add($ip, 'ruch z 24 h', $info);
            foreach ($addresses as $id => $known)
                foreach ($known as $ip => [$first, $last, $times, $cc, $ua])
                    if ($matches($ip))
                        $add($ip, 'konto ' . ($logins[$id]['name'] ?? $id), ['cc' => $cc, 'ua' => $ua]);
            $scanners = diagScanners();
            foreach ($scanners as $ip => $scanner)
                if ($matches($ip))
                    $add($ip, 'skaner: ' . $scanner['reason'], ['cc' => $scanner['cc'], 'ua' => $scanner['ua'], 'path' => (string)key($scanner['paths'])]);
            if (cloudflareConfigured()) {
                [$cfItems, $cfError] = cloudflareBlocked();
                // the list read last is searched; why Cloudflare was not asked
                // now (e.g. its pause) is the server card's business
                if ($cfItems !== null)
                    $cfError = null;
                foreach ($cfItems ?: [] as $item) {
                    $ip = preg_replace('~/\d+$~', '', $item['ip']);
                    if ($matches($ip) || ($isAddress && sameAddress($query, $ip)))
                        $add($item['ip'], 'zablokowany: ' . $item['comment']);
                }
            }
            // the address asked for whole first, then the busiest
            uksort($found, function ($a, $b) use ($found, $query) {
                $whole = function ($ip) use ($query) { return preg_replace('~/\d+$~', '', (string)$ip) === $query ? 0 : 1; };
                return [$whole($a), $found[$b]['n']] <=> [$whole($b), $found[$a]['n']];
            });
        }

        return [
            'query' => $query,
            'shown' => $shown,
            'accounts' => array_slice($accounts, 0, $shown, true),
            'accountsTotal' => count($accounts),
            'addresses' => array_slice($found, 0, $shown, true),
            'addressesTotal' => count($found),
            'looksLikeAddress' => $looksLikeAddress,
            'marks' => diagMarks($scanners, $cfItems),
            'cfError' => $cfError,
            'logins' => $logins
        ];
    })($searchQuery, min(SEARCH_SHOWN_MAX, max(SEARCH_SHOWN, (int)(is_string($_GET['ile'] ?? null) ? $_GET['ile'] : 0))));

    // how many of a list are shown, and the link that shows more of both
    function searchMore($s, $total, $anchor)
    {
        $shown = count($anchor === 'konta' ? $s['accounts'] : $s['addresses']);
        if ($total <= $shown)
            return '';
        $next = min(SEARCH_SHOWN_MAX, $s['shown'] + SEARCH_SHOWN);
        $more = $s['shown'] < SEARCH_SHOWN_MAX
            ? '<a class="admin-btn small" href="?szukaj=' . e(rawurlencode($s['query'])) . '&amp;ile=' . $next . '#' . $anchor . '">Pokaż więcej</a>'
            : '<span class="muted">zawęź wyszukiwanie, żeby zobaczyć resztę</span>';

        return '        <p class="quota-pages"><span class="muted">pokazane ' . formatCount($shown) . ' z ' . formatCount($total) . '</span> ' . $more . "</p>\n";
    }

    function searchPage($s)
    {
        $knownRoles = readData('roles');
        $most = $s['addresses'] ? max(1, max(array_column($s['addresses'], 'n'))) : 1;
?>
    <div class="panel-grid">
      <section class="card wide diag">
        <h2><i>?</i>Szukaj: <?=e($s['query'])?></h2>
<?php if (!$s['accounts'] && !$s['addresses']): ?>
        <p class="nobody">Nic nie znaleziono. Szukaj po nazwie konta, @nazwie, ID albo adresie IP, także po jego początku, np. <code>45.148.</code></p>
<?php endif; ?>
<?php if ($s['accounts']): ?>
        <h3 class="diag-title" id="konta">Konta <span class="muted"><?=formatCount($s['accountsTotal'])?></span></h3>
        <div class="logins logins-search">
<?php foreach ($s['accounts'] as $id => $why): $id = (string)$id; $badge = roleBadge($knownRoles[$id]['roles'] ?? null); ?>
          <div class="login-row">
            <span class="login-who"><?=accountCell($id, $s['logins'])?></span>
            <span class="login-when">znalezione po: <?=e($why)?></span>
            <span class="login-roles">
<?php if ($badge): ?>
              <span class="lv role-<?=e($badge['key'])?>" title="<?=e($badge['title'])?>">LV.<?=$badge['level']?> <?=e($badge['name'])?></span>
<?php endif; ?>
<?php if (isPanelAdminId($id)): ?>
              <span class="role protected">panel</span>
<?php endif; ?>
            </span>
            <span class="login-actions"><a class="admin-btn small" href="?konto=<?=e($id)?>">Profil</a></span>
          </div>
<?php endforeach; ?>
        </div>
<?=searchMore($s, $s['accountsTotal'], 'konta')?>
<?php endif; ?>
<?php if ($s['addresses']): ?>
        <h3 class="diag-title" id="adresy">Adresy <span class="muted"><?=formatCount($s['addressesTotal'])?> &middot; zapytania z 24 h liczone, gdy adres był wśród najaktywniejszych</span></h3>
<?php if ($s['cfError'] !== null): ?>
        <p class="hint"><b class="warn"><?=e($s['cfError'])?></b></p>
<?php endif; ?>
        <ul class="diag-ips">
<?php foreach ($s['addresses'] as $ip => $info): $ip = (string)$ip; ?>
          <li style="--share: <?=share($info['n'], $most)?>">
            <?=diagIpCell(preg_replace('~/\d+$~', '', $ip), $info['cc'], $info['ua'] !== '' ? $info['ua'] : $info['path'], $s['marks'])?>

            <span class="diag-count"><?=$info['n'] ? formatCount($info['n']) . ($info['php'] ? ' <span class="muted">PHP ' . formatCount($info['php']) . '</span>' : '') : '<span class="muted">–</span>'?></span>
            <span class="diag-agent"><span title="<?=e($info['ua'])?>"><?=e($info['ua'] !== '' ? $info['ua'] : 'bez user agenta')?></span><span class="muted"><?=e(implode(' · ', array_unique($info['where'])))?></span><?=$info['path'] !== '' ? '<code>' . e($info['path']) . '</code>' : ''?></span>
          </li>
<?php endforeach; ?>
        </ul>
<?=searchMore($s, $s['addressesTotal'], 'adresy')?>
<?php endif; ?>
      </section>
    </div>
<?php
    }
