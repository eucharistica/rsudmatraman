<!-- ===== HEAD ===== -->
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

<!-- Tailwind (compiled) -->
<link rel="stylesheet" href="/assets/components/css/tw.css" />

<!-- Alpine.js (CSP build: aman untuk CSP tanpa eval) -->
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3.x.x/dist/cdn.min.js"></script>

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
      window.dispatchEvent(new CustomEvent('rs-theme-change', { detail: { theme: theme } }));
    } catch(e){}
  };
  window.__toggleTheme = function(){
    var current = window.__getTheme();
    window.__setTheme(current === 'dark' ? 'light' : 'dark');
  };
  // Auto-bind semua tombol dengan data-theme-toggle
  window.addEventListener('DOMContentLoaded', function(){
    var btns = document.querySelectorAll('[data-theme-toggle]');
    for (var i=0; i<btns.length; i++){
      btns[i].addEventListener('click', window.__toggleTheme);
    }
  });
</script>

<style>
  * { -webkit-tap-highlight-color: transparent; }
</style>

<!-- App config + Alpine component factory -->
<script>
  // === CONFIG ===
  window.API_BASE_URL     = 'https://rsudmatraman.jakarta.go.id/api-website';
  window.API_SCHEDULE_URL = window.API_BASE_URL + '/jadwal';
  window.API_POLI_URL     = window.API_BASE_URL + '/poliklinik';
  window.API_ROOMS_URL    = window.API_BASE_URL + '/kamar';
  window.API_NEWS_URL     = '/api/berita';

  function todayHariUpper(){
    try {
      var s = new Intl.DateTimeFormat('id-ID', { weekday:'long', timeZone:'Asia/Jakarta' }).format(new Date());
      return s.toUpperCase();
    } catch(e){
      var map = ['MINGGU','SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU'];
      return map[new Date().getDay()] || 'SENIN';
    }
  }

  // Alpine component untuk landing page
  function app(){
    return {
      // ===== Helpers =====
      ensureHariSelected: function(){
        if (!this.filters.hari) this.filters.hari = todayHariUpper();
      },
      formatDate: function(d){
        try {
          var dt = new Date(d);
          if (isNaN(dt)) return '';
          return dt.toLocaleDateString('id-ID');
        } catch(e){
          return '';
        }
      },

      // ===== Carousel =====
      slides: [
        {img: 'https://images.unsplash.com/photo-1582719508461-905c673771fd?q=80&w=1600&auto=format&fit=crop', title: 'IGD 24 Jam',            desc: 'Penanganan gawat darurat cepat & terkoordinasi.'},
        {img: 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?q=80&w=1600&auto=format&fit=crop', title: 'Poliklinik Spesialis',  desc: 'Penyakit Dalam, Anak, Bedah, Jantung, dll.'},
        {img: 'https://images.unsplash.com/photo-1582719508461-905c673771fd?q=80&w=1600&auto=format&fit=crop', title: 'Fasilitas Nyaman',       desc: 'Rawat inap dengan pengawasan 24 jam.'}
      ],
      cur: 0,
      timer: null,
      start: function(){ this.stop(); var self=this; this.timer = setInterval(function(){ self.next(); }, 5000); },
      stop: function(){ if(this.timer) clearInterval(this.timer); },
      go: function(i){ this.cur = (i + this.slides.length) % this.slides.length; },
      next: function(){ this.go(this.cur + 1); },
      prev: function(){ this.go(this.cur - 1); },

      // ====== JADWAL (AJAX) ======
      isLoadingSchedule: false,
      schedule: [],
      poliOptions: [],
      meta: { total:0, page:1, per_page:12 },
      filters: { kd_poli:'', hari:'', q:'', page:1, per_page:12 },

      init: async function(){
        this.filters.hari = todayHariUpper();
        await this.loadPoli();
        await this.fetchSchedule(true);
      },

      loadPoli: async function(){
        try{
          var res = await fetch(window.API_POLI_URL, {mode:'cors', headers:{'Accept':'application/json'}});
          var json = await res.json();
          this.poliOptions = json.data || [];
        }catch(e){ console.error('loadPoli', e); }
      },

      fetchSchedule: async function(resetPage){
        if(resetPage) this.filters.page = 1;
        this.isLoadingSchedule = true;
        try{
          var url = new URL(window.API_SCHEDULE_URL);
          var p = this.filters;
          if(p.kd_poli) url.searchParams.set('kd_poli', p.kd_poli);
          if(p.hari)    url.searchParams.set('hari', String(p.hari).toUpperCase());
          if(p.q)       url.searchParams.set('q', p.q);
          url.searchParams.set('page', p.page);
          url.searchParams.set('per_page', p.per_page);

          var res = await fetch(url, {mode:'cors', headers:{'Accept':'application/json'}});
          var json = await res.json();
          var data = json.data || json || [];
          this.schedule = data.map(function(r){
            return {
              doctor:    r.nm_dokter || r.doctor || '-',
              specialty: r.nm_poli   || r.specialty || '-',
              day:       r.hari_kerja || r.day || '-',
              time:      (r.jam_mulai||'') + (r.jam_selesai ? ' - '+r.jam_selesai : ''),
              room:      r.nm_poli || r.room || '-'
            };
          });
          this.meta = json.meta || { total: this.schedule.length, page: p.page, per_page: p.per_page };
        }catch(e){
          console.error('fetchSchedule', e);
          alert('Gagal memuat jadwal.');
        } finally {
          this.isLoadingSchedule = false;
        }
      },

      changeHari: function(v){ this.filters.hari = v; this.fetchSchedule(true); },
      changePoli: function(v){ this.filters.kd_poli = v; this.fetchSchedule(true); },
      onSearchInput: function(){
        var self=this;
        clearTimeout(this._t);
        this._t = setTimeout(function(){ self.fetchSchedule(true); }, 450);
      },
      prevPage: function(){
        if(this.meta.page>1){ this.filters.page = this.meta.page-1; this.fetchSchedule(); }
      },
      nextPage: function(){
        var last = Math.max(1, Math.ceil(this.meta.total / this.meta.per_page));
        if(this.meta.page < last){ this.filters.page = this.meta.page+1; this.fetchSchedule(); }
      },

      // ====== NEWS ======
      isLoadingNews: false,
      news: [
        {title:'Pelayanan Vaksinasi Influenza', date:'2025-09-10', excerpt:'Vaksin influenza kini tersedia di RSUD Matraman.', href:'#'},
        {title:'Pembukaan Poli Jantung Baru',    date:'2025-09-05', excerpt:'Poli Jantung dengan EKG & Echo.',               href:'#'},
        {title:'MCU Perusahaan',                 date:'2025-08-25', excerpt:'Paket MCU untuk korporasi.',                     href:'#'}
      ],
      loadNews: async function(){
        this.isLoadingNews = true;
        try{
          var res = await fetch(window.API_NEWS_URL, {headers:{'Accept':'application/json'}});
          if(res.ok){
            var json = await res.json();
            var data = json.data || json || [];
            this.news = data.map(function(n){
              return {
                title:  n.title   || '-',
                date:   n.date    || '',
                excerpt:n.excerpt || '',
                href:   n.href    || '#',
                cover:  n.cover
              };
            });
          }
        }catch(e){
          console.warn('CMS news not configured; using demo items.');
        } finally {
          this.isLoadingNews = false;
        }
      }
    };
  }
</script>
