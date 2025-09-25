<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';

$u    = auth_current_user();
$role = auth_role();
$here = $_SERVER['REQUEST_URI'] ?? '/';
$next = ($here && str_starts_with($here, '/')) ? $here : '/pages/portal';

// Nama + email
$name  = trim((string)($u['name']  ?? 'Pengguna'));
$email = trim((string)($u['email'] ?? ''));

$avatar = trim((string)($u['avatar'] ?? ''));
if ($avatar === '' || !preg_match('~^https?://~i', $avatar)) {
  $hash   = $email !== '' ? md5(strtolower($email)) : md5($name ?: 'user');
  $avatar = 'https://www.gravatar.com/avatar/' . $hash . '?s=160&d=identicon';
}
?>
<header
  x-data="megaMenu();"
  x-init="init()"
  class="sticky top-0 z-50 border-b border-gray-200/60 bg-white dark:border-gray-800/60 dark:bg-gray-900"
>
  <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
    <!-- Logo -->
    <a href="/" class="flex items-center gap-2">
      <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary text-white font-bold">RS</span>
      <span class="leading-tight">
        <b>RSUD Matraman</b><br>
        <span class="text-xs text-gray-500 dark:text-gray-400">Terakreditasi Paripurna</span>
      </span>
    </a>

    <!-- Desktop nav -->
    <nav class="hidden md:flex items-center gap-4 text-sm">
    <button @click="toggle('about')"  @mouseenter="hover('about')"  :class="isOpen('about') && 'text-primary'"  class="hover:opacity-80">Tentang Kami</button>
    <button @click="toggle('services')" @mouseenter="hover('services')" :class="isOpen('services') && 'text-primary'" class="hover:opacity-80">Layanan</button>
    <button @click="toggle('de')"      @mouseenter="hover('de')"      :class="isOpen('de') && 'text-primary'"      class="hover:opacity-80">Program & Informasi</button>
    <a href="#kontak" class="hover:opacity-80">Kontak</a>

      <!-- Theme toggle -->
      <button class="ml-2 rounded-xl border px-3 py-1 text-xs dark:border-gray-700"
              @click="$dispatch('toggle-theme')">🌓 Tema</button>

      <!-- Auth -->
      <?php if ($u): ?>
        <!-- User dropdown (Nama + Avatar) -->
        <div x-data="{open:false}" class="relative">
          <button @click="open=!open" @keydown.escape.window="open=false"
                  class="ml-2 flex items-center gap-2 rounded-xl border px-3 py-1.5 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
            <img src="<?= htmlspecialchars($avatar, ENT_QUOTES) ?>" class="h-6 w-6 rounded-full" alt="Avatar">
            <span class="max-w-[160px] truncate"><?= htmlspecialchars($name, ENT_QUOTES) ?></span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 opacity-70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
          </button>
          <div x-cloak x-show="open" x-transition.origin.top.right @click.outside="open=false"
               class="absolute right-0 mt-2 w-48 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">

            <?php if ($role === 'admin'): ?>
              <!-- Admin: bisa lihat Portal & Dashboard -->
              <a href="/pages/portal" class="block px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">Portal</a>
              <a href="/pages/dashboard" class="block px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">Dashboard</a>
            <?php elseif ($role === 'editor'): ?>
              <!-- Editor: hanya Dashboard -->
              <a href="/pages/dashboard" class="block px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">Dashboard</a>
            <?php else: ?>
              <!-- User: hanya Portal -->
              <a href="/pages/portal" class="block px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">Portal</a>
            <?php endif; ?>

            <div class="border-t border-gray-200 dark:border-gray-800"></div>
            <a href="/auth/logout.php" class="block px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="/auth"
           class="ml-2 rounded-xl bg-primary px-3 py-1.5 font-semibold text-white hover:brightness-110">Login</a>
      <?php endif; ?>
    </nav>

    <!-- Mobile trigger -->
    <div class="md:hidden flex items-center gap-2">
      <?php if ($u): ?>
        <!-- Mobile: ikon user masuk ke dropdown sederhana -->
        <div x-data="{open:false}" class="relative">
          <button @click="open=!open" @keydown.escape.window="open=false"
                  class="flex items-center gap-2 rounded-xl border px-3 py-1.5 dark:border-gray-700">
            <img src="<?= htmlspecialchars($avatar, ENT_QUOTES) ?>" class="h-6 w-6 rounded-full" alt="Avatar">
            <span class="max-w-[120px] truncate"><?= htmlspecialchars($name, ENT_QUOTES) ?></span>
          </button>
          <div x-cloak x-show="open" x-transition.origin.top.right @click.outside="open=false"
               class="absolute right-0 mt-2 w-44 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
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
        <a href="/auth/"
           class="rounded-xl bg-primary px-3 py-2 text-sm font-semibold text-white hover:brightness-110">Login</a>
      <?php endif; ?>

      <button class="rounded-xl border px-3 py-2 text-sm dark:border-gray-700" @click="openFirst()">Menu</button>
    </div>
  </div>

  <!-- Backdrop: DESKTOP -->
  <div x-cloak x-show="open && !isMobile"
     x-transition.opacity
     class="fixed inset-x-0 bottom-0 top-16 z-40 bg-black/40"
     @click="close()">
  </div>

  <!-- Backdrop: MOBILE -->
  <div x-cloak x-show="open && isMobile"
     x-transition.opacity
     class="fixed inset-0 z-40 bg-black/60"
     @click="close()">
  </div>

  <!-- Desktop Mega Panel -->
  <div x-cloak x-show="open && !isMobile" x-transition
     class="fixed inset-x-0 top-16 z-50 mx-auto w-full max-w-5xl px-4"
     @keydown.escape.window="close()"
     @click.outside="close()"
     @mouseleave="close()">
    <div class="rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-gray-900">
      <div class="flex items-center justify-between px-6 py-4">
        <h3 class="text-lg font-semibold" x-text="activeTitle()"></h3>
        <button @click="close()" class="rounded-lg border px-2 py-1 text-sm dark:border-gray-700">Tutup ✕</button>
      </div>
      <div class="grid grid-cols-1 gap-2 border-t border-gray-100 p-2 dark:border-gray-800 md:grid-cols-2">
        <template x-for="item in (items[active] || [])" :key="item.text">
          <a :href="item.href"
            @click="close()"
            class="group flex items-center justify-between rounded-lg px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800">
            <span class="font-medium text-gray-900 group-hover:translate-x-1 transition dark:text-gray-100" x-text="item.text"></span>
            <span class="text-gray-400 group-hover:text-primary transition">→</span>
          </a>
        </template>
      </div>
    </div>
  </div>

  <!-- Mobile Fullscreen -->
  <div x-cloak x-show="open && isMobile" x-transition
     class="fixed inset-0 z-50 grid place-items-start bg-white dark:bg-gray-900"
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
          <button class="flex w-full items-center justify-between rounded-lg bg-white px-4 py-3 text-left shadow-sm hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800" @click="openSection('about')">
            <span class="font-medium">Tentang Kami</span><span>›</span>
          </button>
          <button class="mt-2 flex w-full items-center justify-between rounded-lg bg-white px-4 py-3 text-left shadow-sm hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800" @click="openSection('services')">
            <span class="font-medium">Layanan</span><span>›</span>
          </button>
          <button class="mt-2 flex w-full items-center justify-between rounded-lg bg-white px-4 py-3 text-left shadow-sm hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800" @click="openSection('de')">
            <span class="font-medium">Program & Informasi</span><span>›</span>
          </button>
          <a href="#kontak" class="mt-2 block rounded-lg bg-white px-4 py-3 shadow-sm hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800">Kontak</a>
        </div>
        <div x-show="level===1" class="rounded-xl bg-gray-50 p-2 shadow-sm dark:bg-gray-800/40">
          <template x-for="item in (items[active] || [])" :key="'m'+item.text">
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
  function megaMenu(){
    return {
      open:false, active:'about', level:0, history:[],isMobile: false,

      items:{
        about:[
          { text:'Profil RS', href:'/tentang' },
          { text:'Struktur Organisasi', href:'/struktur' },
          { text:'Akreditasi & Mutu', href:'/akreditasi' },
          { text:'Transparansi & Anti Korupsi', href:'/anti-korupsi' },
          { text:'Karir', href:'/karir' },
          { text:'Kontak Bagian & Unit', href:'/kontak-unit' },
        ],
        services:[
          { text:'IGD 24 Jam', href:'/igd' },
          { text:'Poliklinik Spesialis', href:'/poliklinik' },
          { text:'Penunjang (Lab/Radiologi)', href:'/penunjang' },
          { text:'Rawat Inap & Kamar', href:'/pages/rooms' },
          { text:'MCU & Vaksin', href:'/mcu' },
          { text:'Farmasi', href:'/farmasi' },
        ],
        de:[
          { text:'Informasi Pasien & Pengunjung', href:'/informasi' },
          { text:'Hak & Kewajiban Pasien', href:'/hak-kewajiban' },
          { text:'Tarif Layanan', href:'/tarif' },
          { text:'Berita & Artikel', href:'/berita' },
          { text:'Pengumuman', href:'/pengumuman' },
          { text:'FAQ', href:'/faq' },
        ],
      },

      // INIT: set & pantau breakpoint
      init(){
        try{
          var mql = window.matchMedia('(max-width: 767px)');
          var update = () => { this.isMobile = !!mql.matches; };
          update();
          // Listener aman CSP (tanpa arrow)
          if (mql.addEventListener) mql.addEventListener('change', update);
          else if (mql.addListener)  mql.addListener(update);

          // Tutup aman bila resize/berubah mode
          this.$watch('isMobile', (v)=>{ if(!v && this.level!==0) this.level=0; });

          // Pastikan tidak auto-open
          this.open = false;
        }catch(e){}
      },

      activeTitle(){
        return this.active==='about' ? 'Tentang Kami'
             : this.active==='services' ? 'Layanan'
             : 'Program & Informasi';
      },

      isOpen(k){ return this.open && this.active===k && this.level===0; },

      toggle(k){
        if (!this.open){ this.active = k; this.open = true; this.level = 0; return; }
        if (this.level !== 0){ this.level = 0; this.active = k; return; }
        if (this.active === k){ this.close(); } else { this.active = k; }
      },
      hover(k){
        if (this.isMobile) return;
        if (!this.open){ this.open = true; }
        this.level = 0; this.active = k;
      },
      openFirst(){ this.active='about'; this.open=true; this.level=0; },
      openSection(k){ this.history.push(this.active); this.active=k; this.level=1; },
      goBack(){
        if(this.level===1){ this.level=0; this.active=this.history.pop()||'about'; }
        else { this.close(); }
      },
      close(){ this.open=false; this.level=0; this.history=[]; }
    }
  }

  document.addEventListener('toggle-theme', () => {
    const root = document.documentElement;
    const nowDark = !root.classList.contains('dark');
    root.classList.toggle('dark', nowDark);
    localStorage.setItem('theme', nowDark ? 'dark' : 'light');
  });
</script>
