// The 404 page, shown by nginx for any missing address.

// a short link to a command (/cmd/daily) where nginx has no rule for it yet
var command = location.pathname.match(/^\/cmd\/([^\/.]+)\/?$/);
if (command) location.replace('/cmd/#' + command[1]);

// the address that was not found
var path = location.pathname;
try {
  path = decodeURIComponent(path);
} catch (err) {
  // a broken %-code stays as it is
}
document.getElementById('lost').textContent = path;
