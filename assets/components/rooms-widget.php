<section
  x-data="RoomsWidget({ baseUrl: 'https://rsudmatraman.jakarta.go.id/api-website' })"
  x-init="init()"
  class="mx-auto max-w-7xl px-4 py-10"
>
  <header class="mb-2 flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="text-3xl font-bold">Informasi Ketersediaan Tempat Tidur</h1>
    </div>
    <div class="text-xs text-gray-500 dark:text-gray-400">
      <template x-if="lastUpdated">
        <span>Terakhir diperbarui: <b x-text="formatTS(lastUpdated)"></b></span>
      </template>
    </div>
  </header>

  <!-- Loading -->
  <div x-show="isLoading" class="rounded-xl border border-gray-200 p-6 text-gray-600 dark:border-gray-800">
    Memuat data...
  </div>

  <!-- Konten -->
  <template x-for="grp in groups" :key="grp.kelompok_key">
    <section class="mt-6">
      <!-- Label Kelompok -->
      <div class="inline-flex items-center rounded-lg bg-violet-300 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-900">
        <span x-text="grp.kelompok_name"></span>
      </div>

      <!-- Grid Kelas -->
      <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <template x-for="row in grp.kelas" :key="grp.kelompok_key + row.kelas">
          <button
            @click="openDetail(grp.kelompok_key, row.kelas, grp.kelompok_name + ' • ' + row.kelas)"
            class="group flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-5 text-left shadow-sm transition hover:shadow-md dark:border-gray-800 dark:bg-gray-900"
          >
            <div>
              <p class="text-sm text-gray-500">Tersedia</p>
              <p class="mt-1 text-4xl font-extrabold tabular-nums" x-text="row.kosong"></p>
              <p class="mt-1 text-sm text-gray-500" x-text="row.kelas"></p>
            </div>
            <div class="rounded-xl p-3"
                 :class="row.kosong>0 ? 'bg-[#457B3B]' : 'bg-[#CA444A]'">
              <img :src="row.kosong>0 ? '/assets/img/icon-nol-bed.png' : '/assets/img/icon-use-bed.png'"
                   alt="" class="h-10 w-10 object-contain">
            </div>
          </button>
        </template>
      </div>
    </section>
  </template>

  <!-- Empty -->
  <template x-if="!isLoading && groups.length===0">
    <p class="mt-8 text-sm text-gray-500">Tidak ada data kamar.</p>
  </template>

  <!-- Modal Detail Bangsal -->
    <div
      x-show="showModal"
      x-transition
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60"
      @click.self="showModal=false"
      @keydown.escape.window="showModal=false"
    >
      <div
        class="mx-auto w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900"
        @click.stop
      >
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-lg font-semibold" x-text="modalTitle"></h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="lastUpdatedDetail">
              Terakhir diperbarui: <b x-text="formatTS(lastUpdatedDetail)"></b>
            </p>
          </div>
          <button @click="showModal=false" class="rounded-lg border px-2 py-1 text-sm dark:border-gray-700">
            Tutup ✕
          </button>
        </div>
    
        <div class="mt-4" x-show="isLoadingModal">Memuat...</div>
    
        <div class="mt-4 grid grid-cols-1 gap-3" x-show="!isLoadingModal">
          <template x-for="b in modalRows" :key="b.kd_bangsal">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 p-4 dark:border-gray-800">
              <div>
                <p class="font-semibold" x-text="b.nm_bangsal"></p>
                <p class="text-xs text-gray-500">Total: <span x-text="b.total"></span></p>
              </div>
              <div class="flex items-center gap-4 text-sm">
                <span class="inline-flex items-center gap-2 rounded-lg bg-green-100 px-3 py-1 font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300">
                  Kosong: <span x-text="b.kosong"></span>
                </span>
                <span class="rounded-lg bg-gray-100 px-3 py-1 font-semibold text-gray-700 dark:bg-gray-800/60 dark:text-gray-200">
                  Isi: <span x-text="b.isi"></span>
                </span>
              </div>
            </div>
          </template>
    
          <template x-if="modalRows.length===0">
            <p class="text-sm text-gray-500">Belum ada bangsal untuk kombinasi ini.</p>
          </template>
        </div>
      </div>
    </div>
  </div>
  <div x-effect="document.body.style.overflow = showModal ? 'hidden' : ''"></div>
</section>

<script>
  function RoomsWidget(cfg){
    const BASE = (cfg && cfg.baseUrl) || '';
    const API_SUM = BASE + '/kamar';
    const API_DET = BASE + '/kamar/detail';
    return {
      // theme
      theme: localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'),
      setTheme(t){ this.theme = t; localStorage.setItem('theme', t); },

      // state utama
      isLoading:true,
      groups:[],
      lastUpdated:null,

      // modal
      showModal:false,
      modalTitle:'',
      modalRows:[],
      isLoadingModal:false,
      lastUpdatedDetail:null,

      formatTS(d){
        try { return new Date(d).toLocaleString('id-ID', { timeZone:'Asia/Jakarta' }); }
        catch(e){ return new Date().toLocaleString('id-ID'); }
      },

      async init(){
        this.setTheme(this.theme);
        await this.loadSummary();
      },

      async loadSummary(){
        this.isLoading = true;
        try{
          const res = await fetch(API_SUM, {headers:{'Accept':'application/json'}});
          const json = await res.json();
          this.groups = json.data || [];
          this.lastUpdated = new Date();
        }catch(e){
          console.error(e); alert('Gagal memuat data kamar.');
        }finally{
          this.isLoading = false;
        }
      },

      async openDetail(jenis, kelas, title){
        this.showModal = true;
        this.modalTitle = title || (jenis+' • '+kelas);
        this.modalRows = [];
        this.isLoadingModal = true;
        try{
          const url = new URL(API_DET);
          url.searchParams.set('jenis', jenis);
          url.searchParams.set('kelas', kelas);
          const res = await fetch(url, {headers:{'Accept':'application/json'}});
          const json = await res.json();
          this.modalRows = json.data || [];
          this.lastUpdatedDetail = new Date();
        }catch(e){
          console.error(e); alert('Gagal memuat detail bangsal.');
        }finally{
          this.isLoadingModal = false;
        }
      },
    }
  }
</script>
