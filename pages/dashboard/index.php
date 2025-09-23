<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app.php';
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
    include __DIR__ . '/../../partials/topbar-dashboard-lite.php';
  ?>

  <main class="mx-auto max-w-7xl px-4 py-8">
    <header>
      <h1 class="text-2xl font-bold">Halo, <?= htmlspecialchars($name, ENT_QUOTES) ?></h1>
      <p class="mt-1 text-gray-600 dark:text-gray-300">Anda masuk sebagai <b><?= htmlspecialchars($role, ENT_QUOTES) ?></b>.</p>
    </header>

    <!-- Quick cards -->
    <section class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
    </section>

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
      <template x-if="api.error"><p class="mt-3 text-sm text-red-500" x-text="api.error"></p></template>
      <div class="mt-4">
        <button class="rounded-lg border px-3 py-1.5 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                @click="api.loading=true; fetch('/api-website/ping.php?db=1',{headers:{'Accept':'application/json'}})
                .then(r=>r.text())
                .then(t=>{
                  let j; try { j = JSON.parse(t); } catch(_){ throw new Error('Ping bukan JSON: '+t.slice(0,200)); }
                  api.loading=false; api.ok=!!j.ok; api.counts=j.db?.counts||{};
                  api.error = j.ok ? null : (j.error + (j.message?(' — '+j.message):''));
                })
                .catch(e=>{ api.loading=false; api.ok=false; api.error = e.message; });">
          Cek Ulang
        </button>
      </div>
    </section>

    <?php 
    if (rbac_user_has_role($db, (int)($_SESSION['user']['id'] ?? 0), 'admin')) {
      include __DIR__ . '/../../partials/dashboard-activity.php';
    }
    ?>
  </main>
  <?php include dirname(__DIR__, 2) . '/partials/footer-app.php'; ?>
</body>
</html>
