// The arrow in the bottom right corner (a #to-top button), shown once the
// page's first <header> is out of sight, scrolls back to the top. Used from
// the keyboard it also puts the focus into the page's search (the id in its
// data-focus), as the arrow itself disappears. On the commands, the API and
// the gallery.
(function () {
  var button = document.getElementById('to-top');
  var header = document.querySelector('header');
  if (!button || !header || !('IntersectionObserver' in window)) return;

  button.hidden = false;
  new IntersectionObserver(function (entries) {
    button.classList.toggle('shown', !entries[0].isIntersecting);
  }).observe(header);

  button.addEventListener('click', function (e) {
    var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: still ? 'auto' : 'smooth' });

    var input = button.dataset.focus && document.getElementById(button.dataset.focus);
    if (e.detail === 0 && input) input.focus({ preventScroll: true });
  });
})();
