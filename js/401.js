// The 401 page, shown by the gallery and the panel for an address that needs
// a login.

// the address that was asked for
var path = location.pathname;
try {
  path = decodeURIComponent(path);
} catch (err) {
  // a broken %-code stays as it is
}
document.getElementById('asked').textContent = path + location.search;

// the login comes back to this very address (js/account.js)
if (window.SanakanAccount) document.getElementById('login').href = SanakanAccount.loginUrl();

// a find of the Safeguard's register (js/safeguard.js)
if (window.SanakanSafeguard) SanakanSafeguard.witness('unknown');
