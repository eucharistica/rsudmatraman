<?php
declare(strict_types=1);
?>
<section
  x-data="activity()"
  x-init="fetchLogs()"
  class="mt-8 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
  
  <div class="flex items-center justify-between gap-2">
    <h2 class="text-lg font-semibold">Aktivitas Sistem</h2>
    <div class="flex items-center gap-2">
      <button @click="resetFilters()" class="rounded-lg border px-3 py-1.5 text-sm dark:border-gray-700">Reset</button>
      <button @click="fetchLogs()" class="rounded-lg border px-3 py-1.5 text-sm dark:border-gray-700">Muat Ulang</button>
    </div>
    <div class="flex items-center gap-2">
    <a :href="apiUrl()+'&export=csv'"
        class="rounded-lg border px-3 py-1.5 text-sm dark:border-gray-700">Export CSV</a>
    </div>
  </div>

  <!-- Filters -->
  <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-6">
    <select x-model="filters.event" class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
      <option value="">Semua Event</option>
      <option>auth</option>
      <option>profile</option>
      <option>permission</option>
      <option>cms</option>
      <option>system</option>
      <option>api</option>
    </select>
    <select x-model="filters.action" class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
      <option value="">Semua Aksi</option>
      <option>login_success</option>
      <option>login_failed</option>
      <option>logout</option>
      <option>register</option>
      <option>register_google</option>
      <option>oauth_denied</option>
      <option>complete_required</option>
      <option>complete_success</option>
      <option>access_denied</option>
      <option>create</option><option>update</option><option>delete</option>
    </select>
    <select x-model="filters.role" class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
      <option value="">Semua Role</option>
      <option>admin</option><option>editor</option><option>user</option>
    </select>
    <input x-model.trim="filters.email" type="text" placeholder="Filter email"
           class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
    <input x-model="filters.from" type="date"
           class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
    <input x-model="filters.to" type="date"
           class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
  </div>
  <div class="mt-2">
    <input x-model.trim="filters.q" type="text" placeholder="Cari (message/target)"
           class="w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
  </div>

  <template x-if="loading"><p class="mt-3 text-sm text-gray-500">Memuat…</p></template>
  <template x-if="error"><p class="mt-3 text-sm text-red-500" x-text="error"></p></template>

  <div class="mt-4 overflow-x-auto">
    <table class="min-w-full text-left text-sm">
      <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
        <tr>
          <th class="py-2 pr-4">Waktu</th>
          <th class="py-2 pr-4">User</th>
          <th class="py-2 pr-4">Event</th>
          <th class="py-2 pr-4">Aksi</th>
          <th class="py-2 pr-4">Target</th>
          <th class="py-2 pr-4">IP</th>
          <th class="py-2 pr-4">Keterangan</th>
        </tr>
      </thead>
      <tbody>
        <template x-for="row in items" :key="row.id">
          <tr class="border-t border-gray-100 dark:border-gray-800">
            <td class="py-2 pr-4 whitespace-nowrap" x-text="row.created_at"></td>
            <td class="py-2 pr-4">
              <span x-text="row.user_name || row.user_email || '—'"></span>
              <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    x-text="row.user_role || '-'"></span>
            </td>
            <td class="py-2 pr-4" x-text="row.event"></td>
            <td class="py-2 pr-4" x-text="row.action"></td>
            <td class="py-2 pr-4">
              <span x-text="row.target_type || '-'"></span>
              <span x-text="row.target_id ? ('#'+row.target_id) : ''"></span>
            </td>
            <td class="py-2 pr-4" x-text="row.ip || '-'"></td>
            <td class="py-2 pr-4" x-text="row.message || '-'"></td>
          </tr>
        </template>
      </tbody>
    </table>
    <div class="mt-3 flex items-center gap-2 text-sm">
        <button @click="filters.page=Math.max(1,(filters.page-1)); fetchLogs()" class="rounded-lg border px-3 py-1.5 dark:border-gray-700">‹ Prev</button>
        <span x-text="'Hal. '+filters.page"></span>
        <button @click="filters.page=(items.length < 1 ? filters.page : filters.page+1); fetchLogs()" class="rounded-lg border px-3 py-1.5 dark:border-gray-700">Next ›</button>
    </div>
  </div>

  <script>
    function activity(){
      return {
        items: [], loading: true, error: null,
        filters: { event:'', action:'', role:'', email:'', from:'', to:'', q:'', page:1 },
        apiUrl(){ 
          const p = new URLSearchParams();
          if (this.filters.event)  p.set('event', this.filters.event);
          if (this.filters.action) p.set('action', this.filters.action);
          if (this.filters.role)   p.set('role', this.filters.role);
          if (this.filters.email)  p.set('email', this.filters.email);
          if (this.filters.from)   p.set('from', this.filters.from);
          if (this.filters.to)     p.set('to', this.filters.to);
          if (this.filters.q)      p.set('q', this.filters.q);
          if (this.filters.page) p.set('page', String(this.filters.page));
          p.set('limit','20');
          return '/api-website/audit_recent.php?' + p.toString();
        },
        async fetchLogs(){
            try{
                this.loading = true; this.error = null;
                const r = await fetch(this.apiUrl(), {headers:{'Accept':'application/json'}});
                const t = await r.text(); // ambil sebagai teks dulu
                let j;
                try { j = JSON.parse(t); } catch(_) {
                throw new Error('API tidak mengembalikan JSON: ' + t.slice(0,200));
                }
                if (!j.ok) throw new Error((j.error||'Gagal memuat log') + (j.message?(' — '+j.message):''));
                this.items = j.items;
            }catch(e){
                this.error = e.message || 'Gagal memuat log';
            }finally{
                this.loading = false;
            }
        },
        resetFilters(){ this.filters={event:'',action:'',role:'',email:'',from:'',to:'',q:''}; this.fetchLogs(); }
      }
    }
  </script>
</section>
