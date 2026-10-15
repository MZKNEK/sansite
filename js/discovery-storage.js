// Optional discovery progress stays in memory until this browser opts in.
window.SanakanDiscoveryStorage = (function () {
  var ROOT = new URL('../', document.currentScript.src).pathname;
  var CHOICE = 'sanakan-discovery-choice';
  var ACCOUNT = 'sanakan-discovery-account';
  var KEYS = ['safeguard', 'safeguard-gene', 'safeguard-lore', 'safeguard-easter', 'safeguard-snake', 'safeguard-hat', 'safeguard-cake'];
  var memory = Object.create(null);
  var choice = null;
  var panel = null;
  var scheduled = false;
  var signedIn = false;
  try {
    choice = localStorage.getItem(CHOICE);
    signedIn = localStorage.getItem(ACCOUNT) === 'yes';
  } catch (err) {}
  if (choice !== 'yes' && choice !== 'no') choice = null;

  function getItem(key) {
    if (choice === 'yes') {
      try {
        var value = localStorage.getItem(key);
        if (value !== null) memory[key] = value;
      } catch (err) {}
    }
    return Object.prototype.hasOwnProperty.call(memory, key) ? memory[key] : null;
  }

  function merge(key, older, current) {
    if (older === null || older === current) return current;
    try {
      var a = JSON.parse(older), b = JSON.parse(current);
      if (key === 'safeguard') {
        var joined = { finds: {}, sent: {}, proofs: {}, retryProofs: {} };
        [a, b].forEach(function (state) {
          Object.keys(state.finds || {}).forEach(function (find) {
            var earlier = !joined.finds[find] || state.finds[find] < joined.finds[find];
            if (earlier) {
              joined.finds[find] = state.finds[find];
              if (state.proofs && state.proofs[find]) joined.proofs[find] = state.proofs[find];
            }
            var proofs = (joined.retryProofs[find] || []).concat(state.retryProofs && state.retryProofs[find] || []);
            if (state.proofs && state.proofs[find]) proofs.push(state.proofs[find]);
            joined.retryProofs[find] = proofs.filter(function (proof, i) { return proofs.indexOf(proof) === i; });
            if (state.sent && state.sent[find]) joined.sent[find] = 1;
          });
        });
        return JSON.stringify(joined);
      }
      if (key === 'safeguard-lore' && Array.isArray(a) && Array.isArray(b))
        return JSON.stringify(a.concat(b).filter(function (item, i, all) { return all.indexOf(item) === i; }));
      if (key === 'safeguard-gene') return JSON.stringify(Object.assign({}, a, b));
      if (key === 'safeguard-easter' && a.year === b.year)
        return JSON.stringify({ year: b.year, pages: Array.from(new Set(a.pages.concat(b.pages))) });
      if (key === 'safeguard-snake') return String(Math.max(Number(older) || 0, Number(current) || 0));
    } catch (err) {}
    return current;
  }

  function updateSettings() {
    document.querySelectorAll('[data-discovery-choice]').forEach(function (button) {
      button.setAttribute('aria-pressed', String(button.getAttribute('data-discovery-choice') === choice));
    });
    document.querySelectorAll('[data-discovery-status]').forEach(function (status) {
      status.textContent = choice === 'yes' ? 'Zapisywanie włączone.' : choice === 'no' ? 'Postęp tylko na bieżącej stronie.' : 'Zapisywanie czeka na Twój wybór.';
    });
  }

  function tellWorker() {
    if (!('serviceWorker' in navigator)) return;
    navigator.serviceWorker.ready.then(function (registration) {
      if (registration.active) registration.active.postMessage({ type: 'sanakan-discovery-storage', enabled: choice === 'yes' });
    }).catch(function () {});
  }

  function setChoice(value, remember) {
    if (value !== 'yes' && value !== 'no') return;
    var startSaving = value === 'yes' && choice !== 'yes';
    choice = value;
    if (remember !== false) {
      try { localStorage.setItem(CHOICE, value); } catch (err) {}
    }
    KEYS.forEach(function (key) {
      try {
        if (value === 'no') localStorage.removeItem(key);
        else if (startSaving && Object.prototype.hasOwnProperty.call(memory, key)) {
          memory[key] = merge(key, localStorage.getItem(key), memory[key]);
          localStorage.setItem(key, memory[key]);
        }
      } catch (err) {}
    });
    if (panel) panel.remove();
    panel = null;
    document.documentElement.removeAttribute('data-discovery-prompt');
    updateSettings();
    tellWorker();
    document.dispatchEvent(new CustomEvent('sanakan:discovery-storage', { detail: { choice: choice } }));
  }

  function prompt() {
    if (choice !== null || panel || scheduled) return;
    scheduled = true;
    function show() {
      scheduled = false;
      if (choice !== null || panel) return;
      panel = document.createElement('section');
      panel.className = 'discovery-prompt hud-corners';
      panel.setAttribute('role', 'region');
      panel.setAttribute('aria-labelledby', 'discovery-prompt-title');
      panel.setAttribute('aria-live', 'polite');
      panel.innerHTML = '<span class="discovery-label">Rejestr Safeguarda</span>'
        + '<h2 id="discovery-prompt-title">Zachować odkrycia?</h2>'
        + '<p>Zapisywać postęp w tej przeglądarce, aby wrócić do niego później?</p>'
        + '<div class="discovery-actions"><button type="button" data-discovery-choice="yes">Zapisuj</button>'
        + '<button type="button" data-discovery-choice="no">Nie zapisuj</button></div>'
        + '<a class="discovery-privacy" href="' + ROOT + 'privacy/#odkrycia">Prywatność i zmiana wyboru <span aria-hidden="true">↗</span></a>';
      document.body.appendChild(panel);
      document.documentElement.setAttribute('data-discovery-prompt', '');
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', show, { once: true });
    else setTimeout(show, 0);
  }

  function setItem(key, value) {
    memory[key] = String(value);
    if (choice === 'yes') {
      try { localStorage.setItem(key, memory[key]); } catch (err) {}
    } else if (choice === null) prompt();
  }

  function accountReady() {
    if (signedIn || !document.querySelector('.account-menu')) return;
    signedIn = true;
    try { localStorage.setItem(ACCOUNT, 'yes'); } catch (err) {}
    setChoice('yes');
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-discovery-choice]');
    if (button) setChoice(button.getAttribute('data-discovery-choice'));
  });
  window.addEventListener('storage', function (event) {
    if (event.key === ACCOUNT) signedIn = event.newValue === 'yes';
    if (event.key === CHOICE) {
      if (event.newValue === 'yes' || event.newValue === 'no') setChoice(event.newValue, false);
      else { choice = null; updateSettings(); tellWorker(); }
    }
  });
  function ready() { accountReady(); updateSettings(); }
  if (signedIn && choice === null) setChoice('yes');
  document.addEventListener('sanakan:account', accountReady);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready, { once: true });
  else ready();
  tellWorker();
  if ('serviceWorker' in navigator) navigator.serviceWorker.addEventListener('controllerchange', tellWorker);
  return { getItem: getItem, setItem: setItem, choice: function () { return choice; }, setChoice: setChoice };
})();
