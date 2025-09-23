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
