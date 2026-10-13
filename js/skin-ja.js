// The Japanese words of the home page (the fonts come with the language,
// css/style.css): what the skins Teto and Miku speak (js/skin-teto.js,
// js/skin-miku.js), kept here so both say the same things. A skin brings its
// colours and its logo (css/skins.css, js/skins.js); this module brings the
// words and the small pieces that go with them: the motto in Morse, the
// subtitle under the name, the title, description and locale of the page, and
// the name itself, Japanese at once when the skin is worn.
window.SanakanSkinJa = (function () {
  var heading = document.querySelector('h1.hud-title');
  // the name as the page wrote it, before any words of a skin are worn
  var name = heading ? heading.textContent : '';

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
      return text.replace(value, function () {
        return translate(value.slice(0, -statusHint.length)) + ' ' + WORDS[statusHint.trim()];
      });
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
    // A replacement string interprets $&, $` and $' in server-supplied text.
    // Keep them literal, or the DOM observer can keep expanding the same node.
    return text.replace(value, function () { return result; });
  }

  // the name, Japanese or as the page wrote it (the second for someone who must
  // put the plain name back, js/skin-teto.js on 1 April)
  function spell(japanese) {
    return japanese ? translate(name) : name;
  }

  // The name in the heading, with the glitched letter of April written around
  // it when there is one (node): Japanese at once while the skin is worn, the
  // glitch only waiting on its last kana for the find alone (js/skin-teto.js).
  function writeName(japanese, node) {
    var shown = spell(japanese);
    var letter = node || heading.querySelector('.april-letter');
    heading.setAttribute('aria-label', shown);
    if (!letter) {
      heading.replaceChildren(document.createTextNode(shown));

      return;
    }
    letter.querySelector('.april-letter-original').textContent = shown.slice(-1);
    letter.querySelector('.april-letter-kana').textContent = japanese ? name.slice(-1) : 'ン';
    heading.replaceChildren(document.createTextNode(shown.slice(0, -1)), letter);
  }

  // the rest of the Japanese home: the words of the motto in Morse, the
  // subtitle under the name, and the title, description and locale the page
  // shows and shares
  function decorate() {
    document.getElementById('motto').dataset.morse = 'OMOI KEDO TOBERU';
    var subtitle = document.querySelector('.kana');
    subtitle.removeAttribute('aria-hidden');
    subtitle.textContent = 'SANAKAN';
    subtitle.lang = 'en';
    document.querySelectorAll('meta[name="description"], meta[property="og:description"]').forEach(function (meta) {
      meta.content = 'Discordボット：コマンド、ウィキ、ワイフ、スカルペル、リアルタイムの稼働状況。';
    });
    document.querySelector('meta[property="og:title"]').content = 'サナカン';
    document.querySelector('meta[property="og:locale"]').content = 'ja_JP';
    writeName(true);
  }

  return { translate: translate, spell: spell, writeName: writeName, decorate: decorate };
})();
