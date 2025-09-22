<?php
$pageTitle = $pageTitle ?? 'RSUD Matraman';
$pageDescription = $pageDescription ?? 'Layanan kesehatan cepat, ramah, dan terpercaya. IGD 24 jam, poliklinik spesialis, lab, rawat inap, jadwal & info kamar real-time.';
?>
<!-- ===== HEAD (global) ===== -->
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>" />
<meta name="theme-color" content="#38bdf8" />

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico" />

<!-- Prevent flash: set theme class on <html> ASAP -->
<script>
(function(){
  try {
    var t = localStorage.getItem('theme');
    if(!t){ t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
    if(t === 'dark'){ document.documentElement.classList.add('dark'); }
    document.documentElement.dataset.theme = t;
  } catch(e){}
})();
</script>

<!-- Tailwind CDN + config -->
<link rel="stylesheet" href="/assets/components/css/tw.css"></script>
<script>
  tailwind.config = {
    darkMode: 'class',
    theme: {
      extend: {
        colors: { primary: { DEFAULT: '#38bdf8' } },
        boxShadow: { soft: '0 10px 30px -10px rgba(0,0,0,.25)' }
      }
    }
  };
</script>

<!-- Alpine.js -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- Theme helpers (global) -->
<script>
  window.__getTheme = function(){
    try { return localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); }
    catch(e){ return 'light'; }
  };
  window.__setTheme = function(theme){
    try {
      if(theme !== 'dark' && theme !== 'light'){ theme = 'light'; }
      document.documentElement.classList.toggle('dark', theme === 'dark');
      document.documentElement.dataset.theme = theme;
      localStorage.setItem('theme', theme);
      window.dispatchEvent(new CustomEvent('rs-theme-change', { detail: { theme } }));
    } catch(e){}
  };
  window.__toggleTheme = function(){
    var current = window.__getTheme();
    window.__setTheme(current === 'dark' ? 'light' : 'dark');
  };
  // Auto-bind semua tombol dengan data-theme-toggle
  window.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('[data-theme-toggle]').forEach(function(btn){
      btn.addEventListener('click', window.__toggleTheme);
    });
  });
</script>

<style>
  * { -webkit-tap-highlight-color: transparent; }
</style>
