// On April Fools' Day the last letter of the home page's name glitches into
// Japanese. Only activating that letter changes the language and earns a find.
// Shared scripts still supply their usual content; translate it as it arrives.
// No language preference is saved, so following a link returns to Polish.
(function () {
  if (!document.body.classList.contains('home') || !window.SanakanSafeguard
      || !SanakanSafeguard.holiday('aprilfools')) return;

  var heading = document.querySelector('h1.hud-title');
  var name = heading.textContent;
  var letter = document.createElement('button');
  letter.type = 'button';
  letter.className = 'april-letter';
  letter.setAttribute('aria-label', 'Zbadaj zakłócony znak');
  var original = document.createElement('span');
  original.className = 'april-letter-original';
  original.textContent = name.slice(-1);
  original.setAttribute('aria-hidden', 'true');
  var kana = document.createElement('span');
  kana.className = 'april-letter-kana';
  kana.textContent = 'ン';
  kana.setAttribute('aria-hidden', 'true');
  letter.append(original, kana);
  heading.setAttribute('aria-label', name);
  heading.replaceChildren(document.createTextNode(name.slice(0, -1)), letter);
  letter.addEventListener('click', activate, { once: true });

  var WORDS = {
    'Sanakan': 'サナカン',
    'Safeguard': 'セーフガード',
    'SAFEGUARD · LV.9': 'セーフガード · レベル9',
    'Niby nie taki lekki, ale lata': '重そうだけど、飛べる',
    'OMOI KEDO TOBERU': '重そうだけど、飛べる',
    'Polecenia': 'コマンド',
    'Wiki': 'ウィキ',
    'Waifu': 'ワイフ',
    'Alter': 'オルター',
    'Skalpelator': 'スカルペル',
    'USkalpelator': 'Uスカルペル',
    'Status': '稼働状況',
    'Prywatność': 'プライバシー',
    'Szukaj': '検索',
    'Zaloguj przez Discord': 'Discordでログイン',
    'Profil': 'プロフィール',
    'Galeria': 'ギャラリー',
    'Panel': '管理パネル',
    'Wyloguj': 'ログアウト',
    'Dokumentacja API': 'APIドキュメント',
    'Dokumentacja API, wymaga logowania przez Discorda': 'APIドキュメント：Discordでのログインが必要です',
    'wymaga logowania': 'ログインが必要です',
    'masz dostęp': 'アクセス権があります',
    'Sprawdzanie statusu bota': 'ボットの状態を確認中',
    '(kliknij, żeby zobaczyć dostępność)': '（クリックして稼働状況を表示）',
    'Dostępny: bot działa': 'オンライン：ボットは稼働中です',
    'Nie przeszkadzać: przerwa techniczna, bot może nie odpowiadać': 'メンテナンス中：ボットが応答しない場合があります',
    'Strona główna': 'ホーム',
    'Zmiany w poleceniach': 'コマンドの変更履歴',
    'Status bota': 'ボットの稼働状況',
    'Historia wersji': 'バージョン履歴',
    'Rejestr Safeguarda': 'セーフガードの記録',
    'Łowcy sekretów': '秘密の探求者',
    'Panel administratora': '管理パネル',
    'Strona': 'ページ',
    'Polecenie': 'コマンド',
    'Wersja': 'バージョン',
    'Szukaj na stronie': 'サイト内を検索',
    'Strona, polecenie bota, wersja…': 'ページ、ボットのコマンド、バージョン…',
    '↑ ↓ wybór · Enter przejście · Esc zamknięcie': '↑ ↓ 選択 · Enter 開く · Esc 閉じる',
    'Nic nie znaleziono, a listy poleceń nie udało się wczytać.': '見つかりませんでした。コマンド一覧も読み込めませんでした。',
    'Nic nie znaleziono.': '見つかりませんでした。',
    'Wpisz nazwę polecenia, strony albo numer wersji.': 'コマンド名、ページ名、バージョン番号を入力してください。',
    'Wczytywanie poleceń i wersji…': 'コマンドとバージョンを読み込み中…',
    'Ping pong z Safeguardem': 'セーフガードと卓球',
    'Mysz, palec albo strzałki. Do pięciu punktów.': 'マウス、タッチ、矢印キーで操作。5点先取。',
    'Jeszcze raz': 'もう一度',
    'Zamknij': '閉じる',
    'Zamknij (Esc)': '閉じる（Esc）',
    'Ty': 'あなた',
    'Safeguard przepuścił piłkę. To się nie powtórzy.': 'セーフガードがボールを逃した。次はない。',
    'Punkt dla Safeguarda.': 'セーフガードの得点。',
    'Rewanż. Safeguard się nie męczy.': '再戦。セーフガードは疲れない。',
    'odebrano:': '受信：',
    'odpowiedź:': '応答：',
    'Safeguard już idzie': 'セーフガードが向かっています',
    'Słucham': '聞いています',
    'Cel namierzony': '目標を捕捉',
    'Gen nadal nie istnieje': '遺伝子はまだ存在しません',
    'Odmowa dostępu': 'アクセス拒否',
    'Hej, mieszkańcze': 'こんにちは、住民',
    'Powtórz': 'もう一度送信してください',
    'Rejestr Safeguarda · nowy wpis': 'セーフガードの記録 · 新しい発見',
    'Zagubiony w tłumaczeniu': '翻訳の迷子',
    'Sygnał zwrotny': '返信信号',
    'Czuwanie': '見守り',
    'Strzał z Emitera': '重力子放射線射出装置の発射',
    'Regeneracja': '再生',
    'Nielegalny mieszkaniec': '不法居住者',
    'Intruz w kodzie': 'コードへの侵入者',
    'Forma krzemowa': '珪素生物',
    'Kronikarz': '記録者',
    'Pisanki z Megastruktury': 'メガストラクチャーのイースターエッグ',
    'Pisanka': 'イースターエッグ',
    'wpis sezonowy': '季節の記録',
    'BEZ ROLI': 'ロールなし',
    'DEV': '開発者',
    'ADMIN': '管理者',
    'SEMI-ADMIN': '副管理者',
    'TESTER': 'テスター',
    'MOD': 'モデレーター',
    'USER': 'ユーザー',
    'POZA SERWEREM': 'サーバー未参加',
    'Tego konta nie ma na serwerze Sanakana': 'このアカウントはサナカンのサーバーに参加していません',
    'Konto nie ma roli na serwerze Sanakana': 'このアカウントにはサナカンのサーバーのロールがありません',
    'Podgląd z innymi uprawnieniami': '別の権限でプレビュー中',
    'Wróć do swoich': '自分の権限に戻る'
  };

  function translate(text) {
    var value = text.trim();
    var statusHint = ' (kliknij, żeby zobaczyć dostępność)';
    if (value.endsWith(statusHint)) {
      return text.replace(value, translate(value.slice(0, -statusHint.length))
        + ' ' + WORDS[statusHint.trim()]);
    }
    var result = Object.prototype.hasOwnProperty.call(WORDS, value) ? WORDS[value] : undefined;
    if (result === undefined) {
      result = value
        .replace(/^Szukaj na stronie \((.+)\)$/, 'サイト内を検索（$1）')
        .replace(/^Zalogowano jako (.+)\.$/, '$1としてログインしました。')
        .replace(/^Rola na serwerze Sanakana: (.+)$/, function (_, role) {
          return 'サナカンのサーバーでのロール：' + translate(role.toUpperCase());
        })
        .replace(/^LV\.(\d+) (DEV|ADMIN|SEMI-ADMIN|TESTER|MOD|USER)$/, function (_, level, role) {
          return 'レベル' + level + ' ' + WORDS[role];
        })
        .replace(/^Zaraz wracam: bot działa, ale w ciągu ostatnich 24 h odpowiadał tylko w (.+)% sprawdzeń$/, '離席中：ボットは稼働していますが、過去24時間の応答率は$1%です')
        .replace(/^Zaraz wracam: bot działa, ale (.+)$/, function (_, issues) {
          return '離席中：ボットは稼働中です。' + issues
            .replace(/nie odpowiada/g, '応答なし').replace(/opóźnienie/g, '遅延');
        })
        .replace(/^Niedostępny: bot nie odpowiada\s*(.*)$/, function (_, down) {
          return 'オフライン：ボットは応答していません' + (down ? '（' + down
            .replace(/od /g, '').replace(/dni|dnia|dzień/g, '日')
            .replace(/godz\.?|h/g, '時間').replace(/min/g, '分').replace(/sek\.?|s\b/g, '秒') + '）' : '');
        })
        .replace(/ \(kliknij, żeby zobaczyć dostępność\)$/, '（クリックして稼働状況を表示）')
        .replace(/^Safeguard wygrywa (\d+:\d+)\. Jak zawsze\.$/, 'セーフガードの勝利、$1。いつも通り。')
        .replace(/^Wygrywasz (\d+:\d+)\. Safeguard żąda rewanżu\.$/, 'あなたの勝利、$1。セーフガードが再戦を要求しています。')
        .replace(/^(\d+) z (\d+)/, '$1 / $2')
        .replace(/^wpis sezonowy/, '季節の記録')
        .replace(/ · rejestr w profilu$/, ' · プロフィールに記録')
        .replace(/ · zapisane w tej przeglądarce, po zalogowaniu trafi do profilu$/, ' · このブラウザーに保存しました。ログイン後にプロフィールへ同期します');
    }
    return text.replace(value, result);
  }

  var ATTRIBUTES = ['title', 'aria-label', 'alt', 'placeholder'];
  function localize(node) {
    // User names, typed commands and script sources are never translated.
    var parent = node.nodeType === 1 ? node : node.parentElement;
    if (!parent || parent.closest('script, style, textarea, .account-name, .ns-cmd')) return;
    if (node.nodeType === 3) {
      var text = translate(node.data);
      if (text !== node.data) node.data = text;
    } else if (node.nodeType === 1) {
      ATTRIBUTES.forEach(function (attr) {
        if (!node.hasAttribute(attr)) return;
        var value = node.getAttribute(attr);
        var text = translate(value);
        if (text !== value) node.setAttribute(attr, text);
      });
      Array.prototype.forEach.call(node.childNodes, localize);
    }
  }

  function activate() {
    heading.replaceChildren(document.createTextNode(name));
    heading.removeAttribute('aria-label');
    window.SanakanHomeLanguage = { text: translate };
    document.documentElement.lang = 'ja';
    document.documentElement.classList.add('home-japanese');
    document.title = 'サナカン';
    var logo = document.querySelector('img.logo');
    logo.src = './teto.webp';
    logo.alt = 'テト';
    var subtitle = document.querySelector('.kana');
    subtitle.removeAttribute('aria-hidden');
    subtitle.textContent = 'SANAKAN';
    subtitle.lang = 'en';
    document.getElementById('motto').dataset.morse = 'OMOI KEDO TOBERU';
    document.querySelectorAll('meta[name="description"], meta[property="og:description"]').forEach(function (meta) {
      meta.content = 'Discordボット：コマンド、ウィキ、ワイフ、スカルペル、リアルタイムの稼働状況。';
    });
    document.querySelector('meta[property="og:title"]').content = 'サナカン';
    document.querySelector('meta[property="og:locale"]').content = 'ja_JP';

    localize(document.body);
    // Reloading restores the original language, logo and the account's HUD
    // without saving a language choice or changing its recorded discoveries.
    function returnButton(text, label) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'home-language-reset';
      button.textContent = text;
      button.setAttribute('aria-label', label);
      button.title = label;
      button.addEventListener('click', function () { location.reload(); });
      return button;
    }
    heading.replaceChildren(returnButton(heading.textContent, '通常表示に戻る'));
    heading.setAttribute('aria-label', translate(name));
    subtitle.replaceChildren(returnButton('SANAKAN', 'Return to normal appearance'));
    new MutationObserver(function (changes) {
      changes.forEach(function (change) {
        if (change.type === 'childList') change.addedNodes.forEach(localize);
        else localize(change.target);
      });
    }).observe(document.body, { childList: true, subtree: true, characterData: true,
      attributes: true, attributeFilter: ATTRIBUTES });
    document.dispatchEvent(new CustomEvent('sanakan:home-language'));
    SanakanSafeguard.witness('aprilfools');
  }
})();
