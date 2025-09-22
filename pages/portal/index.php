<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/_access.php'; // ini sudah memanggil session_boot()

$TOPBAR_SUBTITLE = 'Portal';

// (Opsional) Override isi mega menu portal di sini
$TOPBAR_MENU = [
  'monitoring' => [
    ['text' => 'Ketersediaan Kamar', 'href' => '/pages/rooms'],
    ['text' => 'Rawat Inap - Peta Bed', 'href' => '/pages/rooms?tab=bed'],
    ['text' => 'IGD 24 Jam', 'href' => '/igd'],
    ['text' => 'Dashboard Sanitasi', 'href' => '/pages/sanitasi'],
  ],
  'operasional' => [
    ['text' => 'Jadwal Dokter', 'href' => '/#jadwal'],
    ['text' => 'Poliklinik', 'href' => '/poliklinik'],
    ['text' => 'Penunjang (Lab/Radiologi)', 'href' => '/penunjang'],
    ['text' => 'Antrian Ralan', 'href' => '/pages/antrian'],
  ],
];

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
  <script>
    tailwind.config={
      darkMode:'class',
      theme:{extend:{colors:{primary:{DEFAULT:'#38bdf8'}},boxShadow:{soft:'0 10px 30px -10px rgba(0,0,0,.25)'}}}
    }
  </script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>*{-webkit-tap-highlight-color:transparent}</style>
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">

  <?php
    // Include topbar lite (megamenu portal default + override dari $TOPBAR_MENU)
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

    <!-- Cards -->
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <a href="/pages/rooms" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
        <p class="text-sm text-gray-500">Monitoring</p>
        <p class="mt-1 text-lg font-semibold">Ketersediaan Kamar</p>
        <p class="mt-1 text-sm text-gray-500">Widget ringkas & detail per bangsal.</p>
      </a>
      <a href="/#jadwal" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
        <p class="text-sm text-gray-500">Operasional</p>
        <p class="mt-1 text-lg font-semibold">Jadwal Dokter</p>
        <p class="mt-1 text-sm text-gray-500">Lihat/cek update jadwal.</p>
      </a>
      <a href="/berita" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
        <p class="text-sm text-gray-500">Konten</p>
        <p class="mt-1 text-lg font-semibold">Berita & CMS</p>
        <p class="mt-1 text-sm text-gray-500">Kelola pengumuman publik (CMS).</p>
      </a>
    </div>

    <!-- API Health -->
    <section class="mt-8 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Kesehatan API</h2>
        <div class="text-sm" :class="api.ok ? 'text-green-600' : 'text-red-500'"
             x-text="api.loading ? 'Memeriksa…' : (api.ok ? 'OK' : 'Gangguan')"></div>
      </div>
      <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 p-4 text-center dark:border-gray-800">
          <p class="text-xs text-gray-500">Poliklinik</p>
          <p class="mt-1 text-2xl font-bold tabular-nums" x-text="api.counts.poliklinik ?? '-'"></p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 text-center dark:border-gray-800">
          <p class="text-xs text-gray-500">Dokter</p>
          <p class="mt-1 text-2xl font-bold tabular-nums" x-text="api.counts.dokter ?? '-'"></p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 text-center dark:border-gray-800">
          <p class="text-xs text-gray-500">Jadwal</p>
          <p class="mt-1 text-2xl font-bold tabular-nums" x-text="api.counts.jadwal ?? '-'"></p>
        </div>
      </div>
      <template x-if="api.error">
        <p class="mt-3 text-sm text-red-500" x-text="api.error"></p>
      </template>
      <div class="mt-4">
        <button class="rounded-lg border px-3 py-1.5 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                @click="api.loading=true; fetch('/api-website/ping?db=1',{headers:{'Accept':'application/json'}})
                  .then(r=>r.json()).then(j=>{api.loading=false; api.ok=!!j.ok; api.counts=j.db?.counts||{}; api.error=j.ok?null:(j.error||'Gangguan')})
                  .catch(()=>{api.loading=false; api.ok=false; api.error='Gagal konek API'})">
          Cek Ulang
        </button>
      </div>
    </section>

    <!-- Logs placeholder -->
    <section class="mt-8 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <h2 class="text-lg font-semibold">Aktivitas Terbaru</h2>
      <p class="mt-1 text-sm text-gray-500">Integrasikan dengan log aplikasi/CMS jika diperlukan.</p>
      <ul class="mt-3 list-disc pl-5 text-sm text-gray-600 dark:text-gray-300">
        <li>[Contoh] Sinkronisasi jadwal selesai (08:10)</li>
        <li>[Contoh] Admin menambah berita (kemarin)</li>
      </ul>
    </section>
  </main>

  <footer class="border-t border-gray-200 bg-white/70 py-6 text-center text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900/60 dark:text-gray-400">
    © <script>document.write(new Date().getFullYear())</script> RSUD Matraman
  </footer>
</body>
</html>
