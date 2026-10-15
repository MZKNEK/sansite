// The search of every page: Ctrl+K (⌘K on a Mac), or the button the script
// puts in the footer, opens a box that finds the site's pages, the bot's
// commands and its versions (palette.php, asked the first time it opens) and
// goes to the one picked, with the arrow keys and Enter, or a click.
(function () {
  // the site's root, from this script's own address (js/palette.js)
  var ROOT = new URL('../', document.currentScript.src).pathname;
  var LIMIT = 40;
  var MAC = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
  // The April Fools' home page supplies its own language; other pages use Polish.
  function text(value) {
    return window.SanakanHomeLanguage ? SanakanHomeLanguage.text(value) : value;
  }

  // [name, path from the site's root or a full address, words it is also found by]
  var PAGES = [
    ['Strona główna', '', 'start home sanakan'],
    ['Polecenia', 'cmd/', 'komendy commands cmd'],
    ['Zmiany w poleceniach', 'cmd/changes/', 'historia changes nowe zmiany'],
    ['Status bota', 'state/', 'dostępność uptime awarie ping'],
    ['Historia wersji', 'state/versions/', 'changelog versions verdiff'],
    ['Galeria', 'i/', 'obrazki zdjęcia gallery pliki'],
    ['Profil', 'account/', 'konto account wygląd kolor urządzenia'],
    ['Rejestr Safeguarda', 'account/#rejestr', 'sekrety znaleziska easter egg'],
    ['Łowcy sekretów', 'hunters/', 'ranking łowcy rejestr znaleziska easter egg hunters'],
    ['Dokumentacja API', 'api/', 'swagger endpoint'],
    ['Skalpelator', 'skalpel/', 'karta przycinanie obrazek crop'],
    ['USkalpelator', 'uskalpel/', 'ultimate karta przycinanie crop'],
    ['Prywatność', 'privacy/', 'dane cookies privacy'],
    ['Zasady', 'terms/', 'regulamin terms warunki'],
    ['Wiki', 'https://wiki.sanakan.pl', 'pomoc poradnik'],
    ['Waifu', 'https://waifu.sanakan.pl', 'karty kolekcja'],
    ['Alter', 'https://alter.sanakan.pl/', '']
  ];
  var KINDS = { page: 'Strona', command: 'Polecenie', version: 'Wersja' };
  // on the same rank the commands come first, they are what is looked for most
  var ORDER = { command: 0, page: 1, version: 2 };

  // lower case without the Polish marks, so "zrodlo" finds "źródło"; the
  // length stays, for the highlight
  function norm(text) {
    return String(text).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ł/g, 'l');
  }

  function wordsOf(text) {
    return text.split(/[^a-z0-9]+/).filter(Boolean);
  }

  // names: what the item is called besides its title (the aliases of a
  // command); tags: what it is only about (the words of a page, the module of
  // a command), weaker than any name
  function item(kind, title, href, sub, names, tags) {
    var t = norm(title);
    names = (names || []).map(norm);
    tags = (tags || []).map(norm);
    return {
      kind: kind, title: title, href: href, sub: sub || '',
      t: t, names: names, tags: tags,
      words: wordsOf([norm(text(KINDS[kind])), t, norm(sub || '')].concat(names, tags).join(' '))
    };
  }

  function pages() {
    var list = PAGES.map(function (page) {
      return item('page', text(page[0]), /^https?:/.test(page[1]) ? page[1] : ROOT + page[1], '', [page[0]], page[2].split(' '));
    });
    // the panel only for the accounts whose menu has it (accountMenuHtml() in inc/auth.php)
    if (document.querySelector('.account-drop a[href$="admin/"]'))
      list.splice(7, 0, item('page', text('Panel administratora'), ROOT + 'admin/', '', ['Panel administratora'], ['admin', 'panel']));
    return list;
  }

  // what is searched: the pages, made again at every opening (the account
  // menu of a static page comes later), then the commands and the versions
  var items = [];
  var extra = [];
  var prefix = '.';  // the bot's, from palette.php
  var loaded = null; // the commands and versions: null before, a promise after

  function load() {
    if (loaded) return loaded;
    loaded = fetch(ROOT + 'palette.php')
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) {
        if (!data) throw new Error('no data');
        if (data.prefix) prefix = norm(data.prefix);
        (data.commands || []).forEach(function (cmd) {
          extra.push(item('command', cmd.name, ROOT + 'cmd/#' + encodeURIComponent(cmd.id),
            cmd.description, cmd.aliases, [cmd.module]));
        });
        (data.versions || []).forEach(function (version) {
          extra.push(item('version', version, ROOT + 'state/versions/?v=' + encodeURIComponent(version), '', [], []));
        });
      })
      // tried again on the next opening
      .catch(function () { loaded = null; });
    return loaded;
  }

  // what was typed, ready to compare: without the marks, the extra spaces
  // and the bot's prefix, as a command is often typed the way it is used (.daily)
  function clean(text) {
    var query = norm(text.trim()).replace(/\s+/g, ' ');
    if (prefix && query.indexOf(prefix) === 0) query = query.slice(prefix.length).trim();
    return query;
  }

  // whether a typed word answers a word of the item: its start, or the same
  // word in another form (Polish changes the ending: nagroda, nagrodę; karty,
  // kartę), told by a shared start missing at most the last two letters
  function like(token, word) {
    if (word.indexOf(token) === 0) return true;
    if (token.length < 4) return false;
    var same = 0;
    while (same < token.length && token[same] === word[same]) same++;
    return same >= token.length - 2;
  }

  // how well an item answers the query: 0 is best, -1 is not at all
  function rank(entry, query, tokens) {
    var t = entry.t;
    if (t === query) return 0;
    if (t.indexOf(query) === 0 || entry.names.indexOf(query) >= 0) return 1;
    if (entry.names.some(function (name) { return name.indexOf(query) === 0; })) return 2;
    if ((' ' + t).indexOf(' ' + query) >= 0) return 3;
    if (t.indexOf(query) >= 0) return 4;
    // every word typed somewhere in it, maybe in another form
    var fits = tokens.every(function (token) {
      return entry.words.some(function (word) { return like(token, word); });
    });
    if (!fits) return -1;
    // then by where: all of it in the name, some in the name or the
    // description, only in the tags
    function found(text, all) {
      var words = wordsOf(text);
      return tokens[all ? 'every' : 'some'](function (token) {
        return words.some(function (word) { return like(token, word); });
      });
    }
    var named = [t].concat(entry.names).join(' ');
    if (found(named, true)) return 5;
    return found(named + ' ' + norm(entry.sub), false) ? 6 : 7;
  }

  function search(text) {
    var query = clean(text);
    if (!query) return items.filter(function (entry) { return entry.kind === 'page'; });
    var tokens = wordsOf(query);
    if (!tokens.length) tokens = [query];
    return items
      .map(function (entry, i) { return { entry: entry, rank: rank(entry, query, tokens), i: i }; })
      .filter(function (hit) { return hit.rank >= 0; })
      .sort(function (a, b) {
        return a.rank - b.rank || ORDER[a.entry.kind] - ORDER[b.entry.kind] || a.i - b.i;
      })
      .slice(0, LIMIT)
      .map(function (hit) { return hit.entry; });
  }

  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text != null) node.textContent = text;
    return node;
  }

  // the title with what was typed marked in it
  function titled(entry, query) {
    var b = el('b');
    var at = query ? entry.t.indexOf(query) : -1;
    if (at < 0 || entry.t.length !== entry.title.length) {
      b.textContent = entry.title;
      return b;
    }
    b.append(entry.title.slice(0, at), el('mark', null, entry.title.slice(at, at + query.length)), entry.title.slice(at + query.length));
    return b;
  }

  var open = null;
  var refresh = null;
  document.addEventListener('sanakan:home-language', function () {
    items = pages().concat(extra);
    if (refresh) refresh();
  });

  function show() {
    if (open || document.body.classList.contains('ns-open')) return;
    items = pages().concat(extra);
    var before = document.activeElement;

    var overlay = el('div', 'pl-overlay');
    var box = el('div', 'pl-box hud-corners');
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.setAttribute('aria-label', 'Szukaj na stronie');

    var field = el('label', 'pl-field');
    field.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="M15.5 15.5 21 21" /></svg>';
    // "bare": the croppers' styles of their own fields leave it alone
    var input = el('input', 'bare');
    input.type = 'search';
    input.autocomplete = 'off';
    input.spellcheck = false;
    input.placeholder = 'Strona, polecenie bota, wersja…';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'true');
    input.setAttribute('aria-controls', 'pl-list');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-label', 'Szukaj na stronie');
    field.append(input, el('kbd', null, 'Esc'));

    var list = el('ul', 'pl-list');
    list.id = 'pl-list';
    list.setAttribute('role', 'listbox');
    var info = el('p', 'pl-info');
    info.setAttribute('aria-live', 'polite');
    var foot = el('p', 'pl-foot', '↑ ↓ wybór · Enter przejście · Esc zamknięcie');

    box.append(field, list, info, foot);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    document.body.classList.add('pl-open');

    var shown = [];
    var active = 0;

    function mark(index) {
      if (!shown.length) return;
      active = (index + shown.length) % shown.length;
      Array.prototype.forEach.call(list.children, function (li, i) {
        li.setAttribute('aria-selected', i === active ? 'true' : 'false');
      });
      var li = list.children[active];
      input.setAttribute('aria-activedescendant', li.id);
      li.scrollIntoView({ block: 'nearest' });
    }

    function render() {
      var query = clean(input.value);
      shown = search(input.value);
      list.textContent = '';
      shown.forEach(function (entry, i) {
        var li = el('li');
        li.id = 'pl-option-' + i;
        li.setAttribute('role', 'option');
        var a = el('a');
        a.href = entry.href;
        a.tabIndex = -1;
        a.append(el('span', 'pl-kind pl-' + entry.kind, text(KINDS[entry.kind])), titled(entry, query));
        if (entry.sub) a.appendChild(el('small', null, entry.sub));
        a.addEventListener('mousemove', function () { if (active !== i) mark(i); });
        a.addEventListener('click', function (e) {
          if (e.ctrlKey || e.metaKey || e.shiftKey || e.button > 0) return;
          shut(false);
        });
        li.appendChild(a);
        list.appendChild(li);
      });
      active = 0;
      if (shown.length) mark(0);
      else input.removeAttribute('aria-activedescendant');

      if (!shown.length)
        info.textContent = loaded === null && query ? 'Nic nie znaleziono, a listy poleceń nie udało się wczytać.' : 'Nic nie znaleziono.';
      else if (!query)
        info.textContent = 'Wpisz nazwę polecenia, strony albo numer wersji.';
      else
        info.textContent = '';
    }

    function go() {
      var entry = shown[active];
      if (!entry) return;
      shut(false);
      location.href = entry.href;
    }

    function shut(back) {
      document.removeEventListener('keydown', key, true);
      overlay.remove();
      document.body.classList.remove('pl-open');
      open = null;
      refresh = null;
      if (back !== false && before && before.focus) before.focus();
    }

    function key(e) {
      if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        shut();
      } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        mark(active + (e.key === 'ArrowDown' ? 1 : -1));
      } else if (e.key === 'Enter' && e.target === input) {
        e.preventDefault();
        go();
      } else if (e.key === 'Tab') {
        // only the field takes the focus here
        e.preventDefault();
        input.focus();
      }
    }

    input.addEventListener('input', render);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) shut();
    });
    document.addEventListener('keydown', key, true);
    open = shut;
    refresh = render;

    render();
    input.focus();
    var waiting = load();
    if (waiting) {
      if (input.value === '') info.textContent = 'Wczytywanie poleceń i wersji…';
      waiting.then(function () {
        items = pages().concat(extra);
        if (open === shut) render();
      });
    }
  }

  document.addEventListener('keydown', function (e) {
    if (!e.key || e.key.toLowerCase() !== 'k' || !(MAC ? e.metaKey : e.ctrlKey) || e.altKey || e.shiftKey) return;
    e.preventDefault();
    if (open) open();
    else show();
  });

  // the button in the footer, for phones and for those who do not know the keys
  Array.prototype.forEach.call(document.querySelectorAll('.site-foot'), function (footer) {
    var button = el('button', 'foot-search', 'Szukaj');
    button.type = 'button';
    button.title = 'Szukaj na stronie (' + (MAC ? '⌘K' : 'Ctrl+K') + ')';
    button.appendChild(el('kbd', null, MAC ? '⌘K' : 'Ctrl K'));
    button.addEventListener('click', show);
    var dot = el('i', null, '·');
    dot.setAttribute('aria-hidden', 'true');
    footer.append(dot, button);
  });
})();
