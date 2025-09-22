<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

if (function_exists('session_boot')) { session_boot(); }

$u    = auth_current_user();              // array|null
$role = auth_role();                      // 'admin'|'editor'|'user'|null

// ------ Konfigurasi ringan (opsional, bisa di-set sebelum include) ------
$TOPBAR_BRAND      = $TOPBAR_BRAND      ?? 'RSUD Matraman';
$TOPBAR_SUBTITLE   = $TOPBAR_SUBTITLE   ?? '';          // contoh: 'Portal', 'Dashboard Admin'
$TOPBAR_HOME_HREF  = $TOPBAR_HOME_HREF  ?? '/';
$TOPBAR_LOGO_TEXT  = $TOPBAR_LOGO_TEXT  ?? 'RS';
$TOPBAR_MAX_WIDTH  = $TOPBAR_MAX_WIDTH  ?? 'max-w-7xl';
$TOPBAR_STICKY     = $TOPBAR_STICKY     ?? true;        // sticky top
$TOPBAR_HAS_THEME  = $TOPBAR_HAS_THEME  ?? true;        // tombol tema
$TOPBAR_MENU       = $TOPBAR_MENU       ?? [];          // override item mega menu (array)

// ------ Default Mega Menu (khusus Portal; beda dari Landing) ------
$DEFAULT_MENU = [
  'monitoring' => [
    ['text' => 'Ketersediaan Kamar', 'href' => '/pages/rooms'],
    ['text' => 'Bed Management',     'href' => '/pages/rooms?tab=bed'],
    ['text' => 'IGD 24 Jam',         'href' => '/igd'],
  ],
  'operasional' => [
    ['text' => 'Jadwal Dokter',      'href' => '/#jadwal'],
    ['text' => 'Poliklinik',         'href' => '/poliklinik'],
    ['text' => 'Penunjang (Lab/RIS)','href' => '/penunjang'],
  ],
  'konten' => [
    ['text' => 'Berita & Artikel',   'href' => '/berita'],
    ['text' => 'Pengumuman',         'href' => '/pengumuman'],
    ['text' => 'FAQ',                'href' => '/faq'],
  ],
  'administrasi' => [
    ['text' => 'Tarif Layanan',      'href' => '/tarif'],
    ['text' => 'Informasi Pasien',   'href' => '/informasi'],
    ['text' => 'Hak & Kewajiban',    'href' => '/hak-kewajiban'],
  ],
];

// Merge override (pakai key yang sama: monitoring/operasional/konten/administrasi)
$MENU = array_replace_recursive($DEFAULT_MENU, $TOPBAR_MENU);

// ------ Next redirect target (post-login) ------
$here = $_SERVER['REQUEST_URI'] ?? '/';
$next = ($here && str_starts_with($here, '/')) ? $here : '/pages/portal';

// ------ Nama, email, avatar ------
$name  = trim((string)($u['name']  ?? 'Pengguna'));
$email = trim((string)($u['email'] ?? ''));
$avatar = trim((string)($u['avatar'] ?? ''));
if ($avatar === '' || !preg_match('~^https?://~i', $avatar)) {
  $hash   = $email !== '' ? md5(strtolower($email)) : md5($name ?: 'user');
  $avatar = 'https://www.gravatar.com/avatar/' . $hash . '?s=160&d=identicon';
}

$hdrSticky = $TOPBAR_STICKY ? 'sticky top-0' : '';
?>
<!-- Tailwind + Alpine (CDN, aman jika belum dipasang di layout) -->
<link rel="stylesheet" href="/assets/components/css/tw.css"></script>
<script>
  tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:{DEFAULT:'#38bdf8'}},boxShadow:{soft:'0 10px 30px -10px rgba(0,0,0,.25)'}}}};
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<header class="<?= $hdrSticky ?> z-40 border-b border-gray-200/60 bg-white/80 backdrop-blur dark:border-gray-800/60 dark:bg-gray-900/70"
        x-data="portalTopbar()">
  <div class="mx-auto flex h-14 <?= htmlspecialchars($TOPBAR_MAX_WIDTH, ENT_QUOTES) ?> items-center justify-between px-4">
    <!-- Brand -->
    <a href="<?= htmlspecialchars($TOPBAR_HOME_HREF, ENT_QUOTES) ?>" class="flex items-center gap-2">
      <span class="grid h-8 w-8 place-items-center rounded-xl bg-primary text-white"><?= htmlspecialchars($TOPBAR_LOGO_TEXT, ENT_QUOTES) ?></span>
      <span class="text-sm">
        <b><?= htmlspecialchars($TOPBAR_BRAND, ENT_QUOTES) ?></b>
        <?php if ($TOPBAR_SUBTITLE): ?>
          <span class="ml-2 hidden text-xs text-gray-500 dark:text-gray-400 sm:inline"><?= htmlspecialchars($TOPBAR_SUBTITLE, ENT_QUOTES) ?></span>
        <?php endif; ?>
      </span>
    </a>

    <!-- Right: Theme + User Dropdown -->
    <div class="flex items-center gap-2">
      <?php if ($TOPBAR_HAS_THEME): ?>
      <button class="hidden rounded-lg border px-3 py-1 text-xs dark:border-gray-700 md:inline"
              @click="toggleTheme()">🌓 Tema</button>
      <?php endif; ?>

      <?php if ($u): ?>
        <!-- User dropdown -->
        <div class="relative" x-data="{open:false}">
          <button @click="open=!open" @keydown.escape.window="open=false"
                  class="flex items-center gap-2 rounded-lg border px-2 py-1.5 text-xs hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
            <img src="<?= htmlspecialchars($avatar, ENT_QUOTES) ?>" alt="Avatar" class="h-6 w-6 rounded-full">
            <span class="hidden sm:inline max-w-[140px] truncate"><?= htmlspecialchars($name, ENT_QUOTES) ?></span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 opacity-70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
          </button>
          <div x-cloak x-show="open" x-transition.origin.top.right @click.outside="open=false"
               class="absolute right-0 mt-2 w-48 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
              Masuk sebagai<br><b class="text-gray-800 dark:text-gray-100"><?= htmlspecialchars($name, ENT_QUOTES) ?></b>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-800"></div>
            <?php if ($role === 'admin'): ?>
              <a href="/pages/portal" class="block px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">Portal</a>
              <a href="/pages/dashboard" class="block px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">Dashboard</a>
            <?php elseif ($role === 'editor'): ?>
              <a href="/pages/dashboard" class="block px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">Dashboard</a>
            <?php else: ?>
              <a href="/pages/portal" class="block px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">Portal</a>
            <?php endif; ?>
            <div class="border-t border-gray-200 dark:border-gray-800"></div>
            <a href="/auth/logout.php" class="block px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="/auth?next=<?= htmlspecialchars($next, ENT_QUOTES) ?>"
           class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:brightness-110">Login</a>
      <?php endif; ?>

      <!-- Mobile menu trigger -->
      <button class="rounded-lg border px-3 py-1.5 text-xs dark:border-gray-700 md:hidden"
              @click="openFirst()">Menu</button>
    </div>
  </div>

  <!-- Backdrop -->
  <div x-show="open" x-transition.opacity class="fixed inset-0 z-30 bg-black/60" @click="close()"></div>

  <!-- Desktop Mega Menu -->
  <div x-show="open && !isMobile" x-transition
       class="fixed inset-x-0 top-14 z-40 mx-auto w-full <?= htmlspecialchars($TOPBAR_MAX_WIDTH, ENT_QUOTES) ?> px-4"
       @keydown.escape.window="close()" @click.outside="close()">
    <div class="rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-gray-900">
      <div class="flex items-center justify-between px-6 py-4">
        <h3 class="text-lg font-semibold" x-text="activeTitle()"></h3>
        <button @click="close()" class="rounded-lg border px-2 py-1 text-sm dark:border-gray-700">Tutup ✕</button>
      </div>
      <div class="grid grid-cols-1 gap-2 border-t border-gray-100 p-2 dark:border-gray-800 md:grid-cols-2">
        <template x-for="item in items[active] ?? []" :key="item.text">
          <a :href="item.href"
             class="group flex items-center justify-between rounded-lg px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800">
            <span class="font-medium text-gray-900 group-hover:translate-x-1 transition dark:text-gray-100" x-text="item.text"></span>
            <span class="text-gray-400 group-hover:text-primary transition">→</span>
          </a>
        </template>
      </div>
    </div>
  </div>

  <!-- Mobile Fullscreen -->
  <div x-show="open && isMobile" x-transition
       class="fixed inset-0 z-40 grid place-items-start bg-white dark:bg-gray-900"
       @keydown.escape.window="close()">
    <div class="w-full">
      <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
        <div class="flex items-center gap-2">
          <button @click="goBack()" x-show="history.length>0" class="text-sm">← Kembali</button>
          <p class="font-semibold" x-text="activeTitle()"></p>
        </div>
        <button @click="close()" class="rounded-lg border px-2 py-1 text-sm dark:border-gray-700">Tutup ✕</button>
      </div>
      <div class="p-2">
        <div x-show="level===0" class="rounded-xl bg-gray-50 p-2 shadow-sm dark:bg-gray-800/40">
          <button class="flex w-full items-center justify-between rounded-lg bg-white px-4 py-3 text-left shadow-sm hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800" @click="openSection('monitoring')">
            <span class="font-medium">Monitoring</span><span>›</span>
          </button>
          <button class="mt-2 flex w-full items-center justify-between rounded-lg bg-white px-4 py-3 text-left shadow-sm hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800" @click="openSection('operasional')">
            <span class="font-medium">Operasional</span><span>›</span>
          </button>
          <button class="mt-2 flex w-full items-center justify-between rounded-lg bg-white px-4 py-3 text-left shadow-sm hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800" @click="openSection('konten')">
            <span class="font-medium">Konten</span><span>›</span>
          </button>
          <button class="mt-2 flex w-full items-center justify-between rounded-lg bg-white px-4 py-3 text-left shadow-sm hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800" @click="openSection('administrasi')">
            <span class="font-medium">Administrasi</span><span>›</span>
          </button>
        </div>
        <div x-show="level===1" class="rounded-xl bg-gray-50 p-2 shadow-sm dark:bg-gray-800/40">
          <template x-for="item in items[active] ?? []" :key="'m'+item.text">
            <a :href="item.href" class="block rounded-lg bg-white px-4 py-3 shadow-sm hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800">
              <span class="font-medium" x-text="item.text"></span>
            </a>
          </template>
        </div>
      </div>
    </div>
  </div>
</header>

<script>
  function portalTopbar(){
    const items = <?=
      json_encode($MENU, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
    ?>;
    return {
      open:false, active:'monitoring', level:0, history:[],
      items,
      get isMobile(){ return window.matchMedia('(max-width: 767px)').matches; },
      activeTitle(){
        const map={monitoring:'Monitoring', operasional:'Operasional', konten:'Konten', administrasi:'Administrasi'};
        return map[this.active] || 'Menu';
      },
      isOpen(k){ return this.open && this.active===k; },
      openFirst(){ this.active='monitoring'; this.open=true; this.level=0; },
      toggle(k){ if(this.open && this.active===k){ this.close(); return; } this.active=k; this.open=true; this.level=0; },
      openSection(k){ this.history.push(this.active); this.active=k; this.level=1; },
      goBack(){ if(this.level===1){ this.level=0; this.active=this.history.pop()||'monitoring'; } else { this.close(); } },
      close(){ this.open=false; this.level=0; this.history=[]; },
      toggleTheme(){
        const r=document.documentElement; const dark=!r.classList.contains('dark');
        r.classList.toggle('dark', dark); localStorage.setItem('theme', dark?'dark':'light');
      }
    }
  }
</script>
