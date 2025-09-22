<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/app.php';
session_boot();
$db = db();
rbac_require_roles($db, ['admin']);

// Ambil semua user (batasi 200 untuk performa)
$rows = $db->query("
  SELECT u.id, u.name, u.email, u.role, COALESCE(u.status,'active') AS status,
         GROUP_CONCAT(r.slug ORDER BY r.slug SEPARATOR ',') AS roles
  FROM users u
  LEFT JOIN user_roles ur ON ur.user_id=u.id
  LEFT JOIN roles r ON r.id=ur.role_id
  GROUP BY u.id
  ORDER BY u.id DESC
  LIMIT 200
")->fetchAll();

// Ambil daftar role dari tabel roles
$roles = $db->query("SELECT slug, name FROM roles ORDER BY slug")->fetchAll(PDO::FETCH_ASSOC);

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

// Data untuk Alpine
$rolesJson = json_encode($roles, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$usersJson = json_encode(array_map(function($r){
  return [
    'id'    => (int)$r['id'],
    'name'  => (string)($r['name'] ?? ''),
    'email' => (string)($r['email'] ?? ''),
    'roles' => $r['roles'] ? explode(',', $r['roles']) : [],
    'status'=> (string)($r['status'] ?: 'active'),
  ];
}, $rows), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">
  <link rel="shortcut icon" href="/assets/img/favicon.ico" type="image/x-icon">
  <title>Dashboard — Kelola Pengguna</title>
  <meta name="theme-color" content="#38bdf8" />
  <link rel="stylesheet" href="/assets/components/css/tw.css"></script>
  <script>
    tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:'#38bdf8'},boxShadow:{soft:'0 10px 30px -10px rgba(0,0,0,.25)'}}}}
  </script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>*{-webkit-tap-highlight-color:transparent}</style>
</head>
<body
  class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100"
  x-data='userRolePage(<?= $rolesJson ?>, <?= $usersJson ?>, "<?= htmlspecialchars($csrf, ENT_QUOTES) ?>")'
>
  <?php $TOPBAR_SUBTITLE='Dashboard • Pengguna'; include dirname(__DIR__, 3) . '/partials/topbar-dashboard-lite.php'; ?>

  <main class="mx-auto max-w-7xl px-4 py-8">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">Pengguna</h1>
      <div class="text-sm" x-show="flash" x-text="flash"
           :class="flashOk ? 'text-green-600' : 'text-red-500'"></div>
    </div>

    <!-- Filter sederhana -->
    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-4">
      <input x-model.trim="q" placeholder="Cari nama/email…" class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
      <select x-model="filterRole" class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
        <option value="">Semua Role</option>
        <template x-for="r in roles" :key="r.slug">
          <option :value="r.slug" x-text="r.slug"></option>
        </template>
      </select>
      <select x-model="filterStatus" class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
        <option value="">Semua Status</option>
        <option value="active">active</option>
        <option value="inactive">inactive</option>
      </select>
      <button @click="resetFilter()" class="rounded-lg border px-3 py-2 text-sm dark:border-gray-700">Reset</button>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900 dark:text-gray-400">
          <tr>
            <th class="px-3 py-2 text-left">ID</th>
            <th class="px-3 py-2 text-left">Nama</th>
            <th class="px-3 py-2 text-left">Email</th>
            <th class="px-3 py-2 text-left">Roles</th>
            <th class="px-3 py-2 text-left">Status</th>
            <th class="px-3 py-2 text-left">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="u in filtered" :key="u.id">
            <tr class="border-t border-gray-100 align-top dark:border-gray-800">
              <td class="px-3 py-2 whitespace-nowrap" x-text="u.id"></td>
              <td class="px-3 py-2">
                <div class="font-medium" x-text="u.name || '—'"></div>
                <div class="text-xs text-gray-500" x-text="u.email"></div>
              </td>
              <td class="px-3 py-2 hidden sm:table-cell" x-text="u.email"></td>
              <td class="px-3 py-2">
                <div class="flex flex-wrap gap-2">
                  <template x-for="r in roles" :key="u.id+'-'+r.slug">
                    <label class="inline-flex items-center gap-1 rounded-lg border px-2 py-1 text-xs dark:border-gray-700">
                      <input type="checkbox"
                             :checked="u.roles.includes(r.slug)"
                             @change="toggleRole(u, r.slug, $event.target.checked)">
                      <span x-text="r.slug"></span>
                    </label>
                  </template>
                </div>
              </td>
              <td class="px-3 py-2">
                <label class="inline-flex items-center gap-2">
                  <input type="checkbox"
                         :checked="u.status==='active'"
                         @change="setStatus(u, $event.target.checked ? 'active' : 'inactive')">
                  <span class="text-xs rounded px-2 py-0.5"
                        :class="u.status==='active' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'"
                        x-text="u.status"></span>
                </label>
              </td>
              <td class="px-3 py-2">
                <button class="rounded-lg px-3 py-1.5 text-white bg-[#38bdf8] hover:brightness-110 disabled:opacity-50"
                        :disabled="!u._dirty"
                        @click="saveRow(u)">Simpan</button>
                <button class="ml-1 rounded-lg border px-3 py-1.5 text-sm dark:border-gray-700"
                        :disabled="!u._dirty"
                        @click="revertRow(u)">Batal</button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </main>

  <footer class="border-t border-gray-200 bg-white/70 py-6 text-center text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900/60 dark:text-gray-400">
    © <script>document.write(new Date().getFullYear())</script> RSUD Matraman
  </footer>

  <script>
    function userRolePage(roles, users, csrf){
      return {
        roles, csrf, q:'', filterRole:'', filterStatus:'',
        list: users.map(u => ({...u, _orig: JSON.parse(JSON.stringify(u)), _dirty:false})),
        flash: '', flashOk: true,

        get filtered(){
          return this.list.filter(u=>{
            const qq = this.q.toLowerCase();
            const passQ = !qq || (u.name?.toLowerCase().includes(qq) || u.email?.toLowerCase().includes(qq));
            const passRole = !this.filterRole || u.roles.includes(this.filterRole);
            const passStatus = !this.filterStatus || u.status === this.filterStatus;
            return passQ && passRole && passStatus;
          });
        },
        resetFilter(){ this.q=''; this.filterRole=''; this.filterStatus=''; },

        toggleRole(u, slug, on){
          const set = new Set(u.roles);
          on ? set.add(slug) : set.delete(slug);
          u.roles = Array.from(set);
          u._dirty = this.changed(u);
        },
        setStatus(u, status){
          u.status = status;
          u._dirty = this.changed(u);
        },
        changed(u){
          const a = {roles:[...u.roles].sort().join(','), status:u.status};
          const b = {roles:[...u._orig.roles].sort().join(','), status:u._orig.status};
          return (a.roles !== b.roles) || (a.status !== b.status);
        },
        revertRow(u){
          Object.assign(u, JSON.parse(JSON.stringify(u._orig)));
          u._dirty = false;
        },
        async saveRow(u){
          try{
            const body = new URLSearchParams();
            body.set('csrf', this.csrf);
            body.set('user_id', String(u.id));
            u.roles.forEach(r => body.append('roles[]', r));
            body.set('status', u.status);

            const r = await fetch('/api-website/user_update_roles.php', {
              method:'POST',
              headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'},
              body
            });
            const t = await r.text(); let j;
            try { j = JSON.parse(t); } catch(_){ throw new Error('Respon bukan JSON: '+t.slice(0,200)); }
            if(!j.ok) throw new Error(j.message || j.error || 'Gagal menyimpan');

            // sukses → commit _orig
            u._orig = JSON.parse(JSON.stringify({id:u.id,name:u.name,email:u.email,roles:u.roles,status:u.status}));
            u._dirty = false;
            this.flashOk = true; this.flash = 'Tersimpan ✓'; setTimeout(()=>this.flash='', 2000);
          }catch(e){
            this.flashOk = false; this.flash = e.message || 'Gagal menyimpan';
            setTimeout(()=>this.flash='', 4000);
          }
        }
      }
    }
  </script>
</body>
</html>
