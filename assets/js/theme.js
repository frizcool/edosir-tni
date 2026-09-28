(function () {
  // Theme Switching
  const root = document.documentElement;
  const saved = localStorage.getItem('edosir-theme') || 'dark';
  root.setAttribute('data-theme', saved);

  const btn = document.getElementById('themeToggle');
  if (btn) {
    btn.addEventListener('click', function () {
      const current = root.getAttribute('data-theme');
      const next = current === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      localStorage.setItem('edosir-theme', next);
    });
  }

  // Mobile Drawer Navigation (Sidebar Off-Canvas)
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarClose = document.getElementById('sidebarClose');
  const sidebarBackdrop = document.getElementById('sidebarBackdrop');

  function openSidebar() {
    document.body.classList.add('sidebar-open');
  }

  function closeSidebar() {
    document.body.classList.remove('sidebar-open');
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function (e) {
      e.stopPropagation();
      if (document.body.classList.contains('sidebar-open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
  }

  if (sidebarClose) {
    sidebarClose.addEventListener('click', function (e) {
      e.stopPropagation();
      closeSidebar();
    });
  }

  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', closeSidebar);
  }

  // Close when pressing Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
      closeSidebar();
    }
  });

  // Automatically close sidebar when clicking a nav link on mobile screens
  const navLinks = document.querySelectorAll('.sidebar .nav-link');
  navLinks.forEach(function (link) {
    link.addEventListener('click', function () {
      if (window.innerWidth <= 900) {
        closeSidebar();
      }
    });
  });
})();

