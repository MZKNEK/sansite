(function () {
  var input = document.getElementById('cmd-search');
  if (!input) return;

  var modules = document.querySelectorAll('.module');
  var noResults = document.getElementById('no-results');

  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase();
    var anyVisible = false;

    Array.prototype.forEach.call(modules, function (module) {
      var moduleVisible = false;

      Array.prototype.forEach.call(module.querySelectorAll('.submodule'), function (sub) {
        var subVisible = false;

        Array.prototype.forEach.call(sub.querySelectorAll('tbody tr'), function (row) {
          // textContent includes hidden aliases, so they are searchable too
          var match = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
          row.style.display = match ? '' : 'none';
          subVisible = subVisible || match;
        });

        sub.style.display = subVisible ? '' : 'none';
        moduleVisible = moduleVisible || subVisible;
      });

      module.style.display = moduleVisible ? '' : 'none';
      anyVisible = anyVisible || moduleVisible;
    });

    noResults.style.display = anyVisible ? 'none' : 'block';
  });
})();
