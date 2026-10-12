(function () {
  'use strict';
  document.querySelectorAll('.admin-sidebar .sidebar-group').forEach(function (group) {
    var toggle = group.querySelector(':scope > [data-submenu-toggle]');
    var submenu = group.querySelector(':scope > .sidebar-submenu');
    if (!toggle || !submenu) return;
    var hasCurrentLink = !!submenu.querySelector('.sidebar-link.is-current');
    group.classList.toggle('is-expanded', hasCurrentLink);
    toggle.setAttribute('aria-expanded', hasCurrentLink ? 'true' : 'false');
    toggle.addEventListener('click', function () {
      var expanded = group.classList.toggle('is-expanded');
      toggle.setAttribute('aria-expanded', String(expanded));
    });
  });
}());
