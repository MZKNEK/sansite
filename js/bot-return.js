// state/: "Powiadom mnie, gdy wróci". While the bot has not answered for 5
// minutes (data-open), the browser may subscribe to one Web Push notification,
// sent by inc/push.php once the bot has answered 10 checks in a row and shown
// by sw.js, which then ends the subscription. A browser already waiting sees
// it can cancel instead; one without push (iOS outside the home screen) is told so.
(function () {
  var box = document.querySelector('[data-return-push]');
  if (!box) return;
  var open = box.getAttribute('data-open') === '1';
  var text = box.querySelector('[data-return-text]');
  var subscribe = box.querySelector('[data-return-subscribe]');
  var cancel = box.querySelector('[data-return-cancel]');
  var supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext;

  function show(message, subscribeShown, cancelShown) {
    text.textContent = message;
    subscribe.hidden = !subscribeShown;
    cancel.hidden = !cancelShown;
    subscribe.disabled = cancel.disabled = false;
    box.hidden = false;
  }

  function offer() {
    if (!open) {
      box.hidden = true;
      return;
    }
    if (Notification.permission === 'denied')
      show('Powiadomienia są zablokowane dla tej strony w ustawieniach przeglądarki.', false, false);
    else
      show('Bot nie odpowiada. Przeglądarka może dać znać, gdy wróci.', true, false);
  }

  function waiting() {
    show('Dostaniesz powiadomienie, gdy bot wróci i będzie odpowiadał przez 10 minut bez przerwy.', false, true);
  }

  function send(action, subscription) {
    return fetch('push.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: action, subscription: subscription.toJSON() }),
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        return response.ok ? 'ok' : (data.error || 'error');
      });
    }, function () { return 'error'; });
  }

  function key() {
    var value = box.getAttribute('data-key').replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(value + '==='.slice((value.length + 3) % 4));
    var bytes = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
    return bytes;
  }

  function registration() {
    // js/safeguard.js registers it on load; asked again here in case it has not yet
    return navigator.serviceWorker.register('../sw.js').then(function () {
      return navigator.serviceWorker.ready;
    });
  }

  if (!supported) {
    if (open)
      show('Ta przeglądarka nie pokazuje powiadomień ze stron. Na iPhonie i iPadzie dodaj najpierw stronę do ekranu głównego i otwórz ją stamtąd.', false, false);
    return;
  }

  subscribe.addEventListener('click', function () {
    subscribe.disabled = true;
    Notification.requestPermission().then(function (permission) {
      if (permission !== 'granted') {
        offer();
        return;
      }
      return registration().then(function (reg) {
        var options = { userVisibleOnly: true, applicationServerKey: key() };
        return reg.pushManager.subscribe(options).catch(function () {
          // one made with another key stands in the way: ended, then made anew
          return reg.pushManager.getSubscription().then(function (old) {
            return old ? old.unsubscribe().then(function () { return reg.pushManager.subscribe(options); }) : Promise.reject();
          });
        });
      }).then(function (subscription) {
        return send('subscribe', subscription).then(function (result) {
          if (result === 'ok') {
            waiting();
            return;
          }
          subscription.unsubscribe().catch(function () {});
          if (result === 'closed') {
            open = false;
            show('Bot już odpowiada albo trwa przerwa techniczna, więc zapisy są zamknięte.', false, false);
          } else
            show('Nie udało się zapisać. Spróbuj za chwilę.', true, false);
        });
      });
    }).catch(function () {
      show('Nie udało się włączyć powiadomień w tej przeglądarce.', true, false);
    });
  });

  cancel.addEventListener('click', function () {
    cancel.disabled = true;
    registration().then(function (reg) {
      return reg.pushManager.getSubscription();
    }).then(function (subscription) {
      if (!subscription) return offer();
      return send('unsubscribe', subscription).then(function () {
        return subscription.unsubscribe();
      }).then(offer);
    }).catch(function () {
      show('Nie udało się wypisać. Spróbuj za chwilę.', false, true);
    });
  });

  // a browser that already waits: cancelling instead, and its subscription
  // given again while subscribing is open, in case the site has lost it
  navigator.serviceWorker.getRegistration('../').then(function (reg) {
    return reg ? reg.pushManager.getSubscription() : null;
  }).then(function (subscription) {
    if (!subscription) return offer();
    waiting();
    if (open) send('subscribe', subscription);
  }).catch(offer);
})();
