<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/app.php';
session_boot();
$db = db();

$u     = auth_current_user();
$name  = trim((string)($u['name']  ?? 'Pengguna'));
$email = trim((string)($u['email'] ?? ''));
$role  = strtolower((string)($u['role']  ?? 'user'));
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth"
      x-data="{theme:localStorage.getItem('theme')|| (matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'),
               api:{loading:true,ok:false,counts:{},error:null}}"
      x-init="$watch('theme',t=>localStorage.setItem('theme',t));
              (async()=>{
                try{
                  const r=await fetch('/api-website/ping?db=1',{headers:{'Accept':'application/json'}});
                  const j=await r.json();
                  api.loading=false; api.ok=!!j.ok; api.counts=j.db?.counts||{};
                  if(!j.ok) api.error=j.error||'Gagal mengecek API';
                }catch(e){ api.loading=false; api.ok=false; api.error='Gagal konek API'; }
              })()"
      :class="{dark:theme==='dark'}">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">
  <link rel="shortcut icon" href="/assets/img/favicon.ico" type="image/x-icon">
  <title>Dashboard — RSUD Matraman</title>
  <meta name="theme-color" content="#38bdf8" />

  <link rel="stylesheet" href="/assets/components/css/tw.css"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>*{-webkit-tap-highlight-color:transparent}</style>
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">

  <?php
    $TOPBAR_SUBTITLE = 'Dashboard';
    include dirname(__DIR__, 3) . '/partials/topbar-dashboard-lite.php';
  ?>

  <main class="mx-auto max-w-7xl px-4 py-8">
    <?php 
    if (rbac_user_has_role($db, (int)($_SESSION['user']['id'] ?? 0), 'admin')) {
      include dirname(__DIR__, 3) . '/partials/dashboard-activity.php';
    }
    ?>
  </main>
  <?php include dirname(__DIR__, 3) . '/partials/footer-app.php'; ?>
</body>
</html>
