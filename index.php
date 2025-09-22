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
  <script>
    // === CONFIG ===
    window.API_BASE_URL     = 'https://rsudmatraman.jakarta.go.id/api-website';
    window.API_SCHEDULE_URL = window.API_BASE_URL + '/jadwal';
    window.API_POLI_URL     = window.API_BASE_URL + '/poliklinik';
    window.API_ROOMS_URL    = window.API_BASE_URL + '/kamar';
    window.API_NEWS_URL     = '/api/berita';

    function todayHariUpper(){
      try {
        const s = new Intl.DateTimeFormat('id-ID', { weekday:'long', timeZone:'Asia/Jakarta' }).format(new Date());
        return s.toUpperCase();
      } catch(e){
        const map = ['MINGGU','SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU'];
        return map[new Date().getDay()] || 'SENIN';
      }
    }

    function app(){
      return {
        // Carousel state
        slides: [
          {img: 'https://images.unsplash.com/photo-1584017911766-d451b3d278cc?q=80&w=1600&auto=format&fit=crop', title: 'IGD 24 Jam', desc: 'Penanganan gawat darurat cepat & terkoordinasi.'},
          {img: 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?q=80&w=1600&auto=format&fit=crop', title: 'Poliklinik Spesialis', desc: 'Penyakit Dalam, Anak, Bedah, Jantung, dll.'},
          {img: 'https://images.unsplash.com/photo-1582719508461-905c673771fd?q=80&w=1600&auto=format&fit=crop', title: 'Fasilitas Nyaman', desc: 'Rawat inap dengan pengawasan 24 jam.'},
        ],
        cur: 0, timer: null,
        start(){ this.stop(); this.timer = setInterval(()=>{ this.next() }, 5000); },
        stop(){ if(this.timer) clearInterval(this.timer); },
        go(i){ this.cur = (i+this.slides.length)%this.slides.length; },
        next(){ this.go(this.cur+1); },
        prev(){ this.go(this.cur-1); },

        // ====== JADWAL (AJAX) ======
        isLoadingSchedule:false,
        schedule:[],
        poliOptions:[],
        meta:{ total:0, page:1, per_page:12 },
        filters:{ kd_poli:'', hari:'', q:'', page:1, per_page:12 },

        async init(){
          // set hari default = hari ini, lalu muat poli & jadwal
          this.filters.hari = todayHariUpper();
          await this.loadPoli();
          await this.fetchSchedule(true);
        },

        async loadPoli(){
          try{
            const res = await fetch(window.API_POLI_URL, {mode:'cors', headers:{'Accept':'application/json'}});
            const json = await res.json();
            this.poliOptions = json.data || [];
          }catch(e){ console.error('loadPoli', e); }
        },

        async fetchSchedule(resetPage=false){
          if(resetPage) this.filters.page = 1;
          this.isLoadingSchedule = true;
          try{
            const url = new URL(window.API_SCHEDULE_URL);
            const p = this.filters;
            if(p.kd_poli) url.searchParams.set('kd_poli', p.kd_poli);
            if(p.hari)    url.searchParams.set('hari', p.hari.toUpperCase());
            if(p.q)       url.searchParams.set('q', p.q);
            url.searchParams.set('page', p.page);
            url.searchParams.set('per_page', p.per_page);

            const res = await fetch(url, {mode:'cors', headers:{'Accept':'application/json'}});
            const json = await res.json();
            const data = json.data || json || [];
            this.schedule = data.map(r=>({
              doctor: r.nm_dokter || r.doctor || '-',
              specialty: r.nm_poli || r.specialty || '-',
              day: r.hari_kerja || r.day || '-',
              time: (r.jam_mulai||'') + (r.jam_selesai ? ' - '+r.jam_selesai : ''),
              room: r.nm_poli || r.room || '-',
            }));
            this.meta = json.meta || { total: this.schedule.length, page: p.page, per_page: p.per_page };
          }catch(e){ console.error('fetchSchedule', e); alert('Gagal memuat jadwal.'); }
          finally{ this.isLoadingSchedule = false; }
        },

        changeHari(v){ this.filters.hari = v; this.fetchSchedule(true); },
        changePoli(v){ this.filters.kd_poli = v; this.fetchSchedule(true); },
        onSearchInput(){ clearTimeout(this._t); this._t = setTimeout(()=> this.fetchSchedule(true), 450); },
        prevPage(){ if(this.meta.page>1){ this.filters.page = this.meta.page-1; this.fetchSchedule(); } },
        nextPage(){ const last = Math.max(1, Math.ceil(this.meta.total/this.meta.per_page)); if(this.meta.page<last){ this.filters.page = this.meta.page+1; this.fetchSchedule(); } },

        // ====== NEWS (CMS-ready) ======
        isLoadingNews:false,
        news:[
          {title:'Pelayanan Vaksinasi Influenza', date:'2025-09-10', excerpt:'Vaksin influenza kini tersedia di RSUD Matraman.', href:'#'},
          {title:'Pembukaan Poli Jantung Baru', date:'2025-09-05', excerpt:'Poli Jantung dengan EKG & Echo.', href:'#'},
          {title:'MCU Perusahaan', date:'2025-08-25', excerpt:'Paket MCU untuk korporasi.', href:'#'},
        ],
        async loadNews(){
          this.isLoadingNews = true;
          try{
            const res = await fetch(window.API_NEWS_URL, {headers:{'Accept':'application/json'}});
            if(res.ok){
              const json = await res.json();
              const data = json.data || json || [];
              this.news = data.map(n=>({ title:n.title||'-', date:n.date||'', excerpt:n.excerpt||'', href:n.href||'#', cover:n.cover }));
            }
          }catch(e){ console.warn('CMS news not configured; using demo items.'); }
          finally{ this.isLoadingNews = false; }
        },
      }
    }
  </script>
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

    <!-- Schedule (AJAX) with filters -->
    <section id="jadwal" class="mx-auto max-w-7xl px-4 py-16">
      <div class="mb-4">
        <h3 class="text-2xl font-bold">Jadwal Praktek Dokter</h3>
        <p class="mt-1 text-gray-600 dark:text-gray-300">Gunakan filter di bawah untuk menyaring.</p>
      </div>

      <!-- Filters -->
      <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
        <div class="md:col-span-4">
          <label class="mb-1 block text-sm font-medium">Poliklinik</label>
          <select x-model="filters.kd_poli" @change="changePoli($event.target.value)"
                  class="w-full rounded-xl border border-gray-300 px-3 py-2 focus:outline-none focus:ring dark:border-gray-700">
            <option value="">Semua Poliklinik</option>
            <template x-for="p in poliOptions" :key="p.kd_poli">
              <option :value="p.kd_poli" x-text="p.nm_poli"></option>
            </template>
          </select>
        </div>
        <div class="md:col-span-3">
          <label class="mb-1 block text-sm font-medium">Hari</label>
          <select
            x-ref="hariSelect"
            x-model="filters.hari"
            x-init="
              if (!filters.hari) { filters.hari = todayHariUpper(); }
              $nextTick(() => { $refs.hariSelect.value = filters.hari })
            "
            @change="changeHari($event.target.value)"
            class="w-full rounded-xl border border-gray-300 px-3 py-2 focus:outline-none focus:ring dark:border-gray-700"
          >
            <template x-for="h in ['SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU','MINGGU']" :key="h">
              <option :value="h" :selected="h===filters.hari" x-text="h"></option>
            </template>
          </select>
        </div>

        <div class="md:col-span-3">
          <label class="mb-1 block text-sm font-medium">Cari Dokter/Poli</label>
          <input x-model.trim="filters.q" @input="onSearchInput()" type="search" placeholder="Nama dokter atau poli..."
                 class="w-full rounded-xl border border-gray-300 px-3 py-2 focus:outline-none focus:ring dark:border-gray-700" />
        </div>
        <div class="md:col-span-2">
          <label class="mb-1 block text-sm font-medium">Baris per halaman</label>
          <select x-model.number="filters.per_page" @change="fetchSchedule(true)"
                  class="w-full rounded-xl border border-gray-300 px-3 py-2 focus:outline-none focus:ring dark:border-gray-700">
            <option :value="12">12</option>
            <option :value="20">20</option>
            <option :value="30">30</option>
            <option :value="50">50</option>
          </select>
        </div>
      </div>

      <!-- Table/Grid -->
      <div class="relative mt-6">
        <div x-show="isLoadingSchedule" class="absolute inset-0 z-10 grid place-items-center bg-white/60 text-gray-600">Memuat...</div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
          <template x-for="(it, idx) in schedule" :key="idx">
            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
              <p class="text-lg font-semibold" x-text="it.doctor"></p>
              <p class="text-sm text-gray-500" x-text="it.specialty"></p>
              <div class="mt-2 text-sm">
                <p>Hari: <b x-text="it.day"></b></p>
                <p>Waktu: <b x-text="it.time"></b></p>
                <p>Ruang: <b x-text="it.room"></b></p>
              </div>
            </div>
          </template>
        </div>
        <template x-if="schedule.length===0 && !isLoadingSchedule">
          <p class="mt-6 text-center text-sm text-gray-500">Tidak ada data.</p>
        </template>
      </div>

      <!-- Pagination -->
      <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-gray-600">
        <div>
          <span>Total:</span> <b x-text="meta.total"></b>
          <template x-if="meta.page && meta.per_page">
            <span>(Hal. <b x-text="meta.page"></b> dari <b x-text="Math.max(1, Math.ceil(meta.total/meta.per_page))"></b>)</span>
          </template>
        </div>
        <div class="flex gap-2">
          <button @click="prevPage()" :disabled="meta.page<=1" class="rounded-xl border border-gray-300 px-3 py-2 disabled:opacity-50">Sebelumnya</button>
          <button @click="nextPage()" :disabled="meta.page>=Math.ceil(meta.total/meta.per_page)" class="rounded-xl border border-gray-300 px-3 py-2 disabled:opacity-50">Berikutnya</button>
        </div>
      </div>
    </section>

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
