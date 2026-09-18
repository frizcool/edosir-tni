(function () {
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
})();
