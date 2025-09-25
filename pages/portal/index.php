<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app.php';
require_once __DIR__ . '/_access.php';

$TOPBAR_SUBTITLE = 'Portal';

$u = function_exists('current_user') ? current_user() : ($_SESSION['user'] ?? null);
if (!$u) {
  header('Location: /login/?next=' . rawurlencode('/pages/portal'));
  exit;
}

$name   = trim((string)($u['name'] ?? 'Pengguna'));
$email  = trim((string)($u['email'] ?? ''));
$role   = strtolower(trim((string)($u['role'] ?? 'user')));
$roles  = [];
if ($role !== '') { $roles[] = $role; }
if (!empty($u['roles']) && is_array($u['roles'])) {
  foreach ($u['roles'] as $r) {
    $r = strtolower(trim((string)$r));
    if ($r !== '' && !in_array($r, $roles, true)) $roles[] = $r;
  }
}
if (empty($u['roles']) && is_string($u['role'] ?? null) && str_contains((string)$u['role'], ',')) {
  foreach (explode(',', (string)$u['role']) as $r) {
    $r = strtolower(trim($r));
    if ($r !== '' && !in_array($r, $roles, true)) $roles[] = $r;
  }
}

$avatar = trim((string)($u['avatar'] ?? ''));
if ($avatar === '' || !preg_match('~^https?://~i', $avatar)) {
  $hash = $email !== '' ? md5(strtolower(trim($email))) : md5($name ?: 'user');
  $avatar = 'https://www.gravatar.com/avatar/' . $hash . '?s=160&d=identicon';
}

function role_badge(string $r): string {
  $map = [
    'admin'  => 'bg-red-100 text-red-700 ring-red-200',
    'editor' => 'bg-blue-100 text-blue-700 ring-blue-200',
    'user'   => 'bg-gray-100 text-gray-700 ring-gray-200',
  ];
  $cls = $map[$r] ?? 'bg-gray-100 text-gray-700 ring-gray-200';
  return '<span class="px-2 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset '.$cls.'">'.htmlspecialchars($r, ENT_QUOTES).'</span>';
}
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
  <title>Portal — RSUD Matraman</title>
  <meta name="theme-color" content="#38bdf8" />

  <!-- CDN CSS/JS global halaman -->
  <link rel="stylesheet" href="/assets/components/css/tw.css"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3.x.x/dist/cdn.min.js"></script>
  <style>*{-webkit-tap-highlight-color:transparent}</style>
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">

  <?php
    include __DIR__ . '/../../partials/topbar-app-lite.php';
  ?>

  <main class="mx-auto max-w-7xl px-4 py-8">
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6">
      <img src="<?= htmlspecialchars($avatar, ENT_QUOTES) ?>" alt="Avatar" class="h-20 w-20 rounded-full ring-2 ring-white shadow">
      <div>
        <h1 class="text-2xl font-bold leading-tight">Halo, <?= htmlspecialchars($name, ENT_QUOTES) ?> 👋</h1>
        <div class="mt-2 flex flex-wrap gap-2">
          <?php foreach ($roles as $r) { echo role_badge($r); } ?>
        </div>
        <?php if ($email): ?>
          <p class="text-sm text-gray-600 dark:text-gray-300 mt-1"><?= htmlspecialchars($email, ENT_QUOTES) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </main>
  <?php include dirname(__DIR__, 2) . '/partials/footer-app.php'; ?>
</body>
</html>
