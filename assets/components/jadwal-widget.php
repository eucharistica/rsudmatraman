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
        <div x-show="isLoadingSchedule" class="absolute inset-0 z-10 grid place-items-center bg-white/60 text-gray-600">Memuat</div>
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