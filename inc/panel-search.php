<?php
    // Searching the admin panel (admin/?szukaj=...): accounts by their name,
    // @name or ID, and addresses, whole or by their start ("45.148."), among the
    // addresses of the accounts, the scanners, the blocked ones and the busiest
    // of the last 24 hours, each with the accounts that came from it. A search
    // for an account's address finds the account too. admin/index.php includes
    // it after the login check, like the profile.
    if (!function_exists('accountCell')) {
        http_response_code(404);
        exit;
    }

    const SEARCH_SHOWN = 50;

    $search = (function ($query) {
        $lower = 'lower';
        $needle = $lower($query);
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
        foreach (array_unique(array_map('strval', $ids)) as $id) {
            $why = null;
            if (ctype_digit($query) && strpos($id, $query) !== false)
                $why = 'ID';
            else if (!$looksLikeAddress && strpos($lower($logins[$id]['name'] ?? ''), $needle) !== false)
                $why = 'nazwa';
            else if (!$looksLikeAddress && strpos($lower($logins[$id]['username'] ?? ''), $needle) !== false)
                $why = '@' . $logins[$id]['username'];
            else if ($looksLikeAddress)
                foreach (array_keys($addresses[$id] ?? []) as $ip)
                    if ($matches($ip)) {
                        $why = 'adres ' . $ip;
                        break;
                    }
            if ($why !== null)
                $accounts[$id] = $why;
        }

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
                foreach ($cfItems ?: [] as $item) {
                    $ip = preg_replace('~/\d+$~', '', $item['ip']);
                    if ($matches($ip) || ($isAddress && sameAddress($query, $ip)))
                        $add($item['ip'], 'zablokowany: ' . $item['comment']);
                }
            }
            uasort($found, function ($a, $b) { return $b['n'] <=> $a['n']; });
        }

        return [
            'query' => $query,
            'accounts' => array_slice($accounts, 0, SEARCH_SHOWN, true),
            'addresses' => array_slice($found, 0, SEARCH_SHOWN, true),
            'looksLikeAddress' => $looksLikeAddress,
            'marks' => diagMarks($scanners, $cfItems),
            'cfError' => $cfError,
            'logins' => $logins
        ];
    })($searchQuery);

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
        <h3 class="diag-title">Konta <span class="muted"><?=count($s['accounts'])?></span></h3>
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
<?php endif; ?>
<?php if ($s['addresses']): ?>
        <h3 class="diag-title">Adresy <span class="muted"><?=count($s['addresses'])?> &middot; zapytania z 24 h liczone, gdy adres był wśród najaktywniejszych</span></h3>
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
<?php endif; ?>
      </section>
    </div>
<?php
    }
