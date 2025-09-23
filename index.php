<?php
require_once __DIR__ . '/lib/session.php';
session_boot();
$pageTitle = 'RSUD Matraman — Rumah Sakit Modern';
$pageDescription = 'RSUD Matraman: Layanan kesehatan cepat, ramah, dan terpercaya. IGD 24 jam, poliklinik spesialis, lab, rawat inap, jadwal & info kamar real-time.';
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
  <?php include __DIR__ . '/partials/header.php'; ?>

  <!-- Konten Halaman dibungkus Alpine app() -->
  <div x-data="app()" x-init="init()">

    <!-- Hero + Carousel (big) -->
    <section class="relative overflow-hidden" x-init="start()" @mouseenter="stop()" @mouseleave="start()">
      <div class="relative aspect-[21/9] w-full max-h-[720px]">
        <template x-for="(s, i) in slides" :key="i">
          <div x-show="cur===i" x-transition.opacity class="absolute inset-0">
            <img :src="s.img" alt="" class="h-full w-full object-cover" />
            <div class="absolute inset-0 bg-black/40"></div>
            <div class="absolute inset-0 mx-auto flex max-w-7xl items-center px-4">
              <div class="max-w-xl text-white drop-shadow">
                <p class="mb-2 inline-block rounded-full bg-white/20 px-3 py-1 text-xs">RSUD Matraman</p>
                <h1 class="text-4xl font-black sm:text-5xl" x-text="s.title"></h1>
                <p class="mt-3 text-lg opacity-90" x-text="s.desc"></p>
                <div class="mt-6 flex flex-wrap gap-3">
                  <a href="#daftar" class="rounded-xl bg-primary px-5 py-3 font-semibold text-white hover:brightness-110">Buat Janji</a>
                  <a href="#layanan" class="rounded-xl border border-white/60 px-5 py-3 font-semibold text-white hover:bg-white/10">Lihat Layanan</a>
                </div>
              </div>
            </div>
          </div>
        </template>
        <button @click="prev()" aria-label="Prev" class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 text-gray-800 hover:bg-white">‹</button>
        <button @click="next()" aria-label="Next" class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 text-gray-800 hover:bg-white">›</button>
        <div class="pointer-events-none absolute bottom-4 left-1/2 flex -translate-x-1/2 gap-2">
          <template x-for="(s, i) in slides" :key="'d'+i">
            <div @click="go(i)" class="pointer-events-auto h-2 w-6 cursor-pointer rounded-full" :class="cur===i?'bg-white':'bg-white/50'"></div>
          </template>
        </div>
      </div>
    </section>

    <!-- Services -->
    <section id="layanan" class="mx-auto max-w-7xl px-4 py-16">
      <div class="text-center">
        <h2 class="text-3xl font-bold">Layanan Unggulan</h2>
        <p class="mt-2 text-gray-600 dark:text-gray-300">Semua kebutuhan kesehatan Anda dalam satu tempat.</p>
      </div>
      <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-start gap-4">
            <div class="rounded-xl bg-primary/10 p-3 text-primary">🚑</div>
            <div>
              <h3 class="text-lg font-semibold">IGD 24 Jam</h3>
              <p class="mt-1 text-gray-600 dark:text-gray-300">Penanganan gawat darurat cepat & terkoordinasi.</p>
            </div>
          </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-start gap-4">
            <div class="rounded-xl bg-primary/10 p-3 text-primary">🩺</div>
            <div>
              <h3 class="text-lg font-semibold">Poliklinik</h3>
              <p class="mt-1 text-gray-600 dark:text-gray-300">Penyakit Dalam, Anak, Bedah, Kandungan, Jantung, dll.</p>
            </div>
          </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-start gap-4">
            <div class="rounded-xl bg-primary/10 p-3 text-primary">🧪</div>
            <div>
              <h3 class="text-lg font-semibold">Laboratorium</h3>
              <p class="mt-1 text-gray-600 dark:text-gray-300">Hasil cepat & akurat, terintegrasi.</p>
            </div>
          </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-start gap-4">
            <div class="rounded-xl bg-primary/10 p-3 text-primary">🫀</div>
            <div>
              <h3 class="text-lg font-semibold">Cardio Center</h3>
              <p class="mt-1 text-gray-600 dark:text-gray-300">EKG, Echocardiography, Treadmill.</p>
            </div>
          </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-start gap-4">
            <div class="rounded-xl bg-primary/10 p-3 text-primary">💉</div>
            <div>
              <h3 class="text-lg font-semibold">MCU & Vaksin</h3>
              <p class="mt-1 text-gray-600 dark:text-gray-300">Paket medical check-up & vaksinasi lengkap.</p>
            </div>
          </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-start gap-4">
            <div class="rounded-xl bg-primary/10 p-3 text-primary">🛏️</div>
            <div>
              <h3 class="text-lg font-semibold">Rawat Inap</h3>
              <p class="mt-1 text-gray-600 dark:text-gray-300">Kenyamanan prioritas dengan pengawasan 24 jam.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Appointment CTA -->
    <section id="daftar" class="bg-gradient-to-b from-sky-50 to-white py-16 dark:from-gray-950 dark:to-gray-950">
      <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-8 px-4 lg:grid-cols-2">
        <div>
          <h3 class="text-2xl font-bold">Buat Janji Online</h3>
          <p class="mt-2 text-gray-600 dark:text-gray-300">Isi data singkat; tim kami akan menghubungi untuk konfirmasi.</p>
          <form class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2" onsubmit="event.preventDefault(); alert('Contoh form. Integrasikan ke CMS/WhatsApp.');">
            <input class="rounded-xl border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900" placeholder="Nama" required />
            <input type="tel" class="rounded-xl border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900" placeholder="Nomor HP" required />
            <input type="date" class="rounded-xl border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900" required />
            <select class="rounded-xl border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
              <option>Penyakit Dalam</option><option>Anak</option><option>Kandungan</option><option>Bedah</option><option>Jantung</option>
            </select>
            <textarea class="sm:col-span-2 rounded-xl border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900" rows="3" placeholder="Keluhan..."></textarea>
            <button class="rounded-xl bg-primary px-5 py-3 font-semibold text-white hover:brightness-110 sm:col-span-2">Kirim</button>
          </form>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-soft dark:border-gray-800 dark:bg-gray-900">
          <h4 class="text-lg font-semibold">Lokasi & Kontak</h4>
          <p class="mt-1 text-gray-600 dark:text-gray-300">Jl. Kebon Kelapa Raya No.29, Jakarta</p>
          <div class="mt-4 aspect-video overflow-hidden rounded-xl">
            <iframe class="h-full w-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3966.776927904942!2d106.84513!3d-6.16053!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNsKwMDknMzguMCJTIDEwNsKwNTAnNDIuNSJF!5e0!3m2!1sen!2sid!4v1680000000000"></iframe>
          </div>
          <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <a href="tel:+622112345678" class="rounded-xl border border-gray-200 p-4 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800">📞 IGD 24 Jam: (021) 123 456 78</a>
            <a href="https://wa.me/6281234567890" class="rounded-xl border border-gray-200 p-4 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800">💬 WhatsApp: 0812-3456-7890</a>
          </div>
        </div>
      </div>
    </section>

    <?php include __DIR__ . '/assets/components/jadwal-widget.php'; ?>

    <!-- News (CMS-ready) -->
    <section id="berita" class="bg-gradient-to-b from-sky-50 to-white py-16 dark:from-gray-950 dark:to-gray-950">
      <div class="mx-auto max-w-7xl px-4">
        <div class="flex items-end justify-between">
          <div>
            <h3 class="text-2xl font-bold">Berita Terbaru</h3>
            <p class="mt-1 text-gray-600 dark:text-gray-300">Terhubung dengan CMS.</p>
          </div>
          <button class="rounded-xl border border-gray-300 px-4 py-2 text-sm dark:border-gray-700" @click="loadNews()" x-text="isLoadingNews ? 'Memuat...' : 'Muat dari CMS'"></button>
        </div>
        <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <template x-for="(n, i) in news" :key="'n'+i">
            <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
              <img :src="n.cover || 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?q=80&w=1200&auto=format&fit=crop'" alt="" class="h-40 w-full object-cover" />
              <div class="p-4">
                <h4 class="line-clamp-2 text-lg font-semibold" x-text="n.title"></h4>
                <p class="mt-1 text-xs text-gray-500" x-text="new Date(n.date).toLocaleDateString('id-ID')"></p>
                <p class="mt-2 line-clamp-3 text-gray-600 dark:text-gray-300" x-text="n.excerpt"></p>
                <a :href="n.href" class="mt-4 inline-block text-sm font-semibold text-primary">Baca Selengkapnya →</a>
              </div>
            </article>
          </template>
        </div>
        <div class="mt-6 text-center">
          <a href="/berita" class="rounded-xl border border-gray-300 px-5 py-2.5 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Lihat Semua Berita</a>
        </div>
      </div>
    </section>

  </div><!-- /Alpine app wrapper -->

  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
