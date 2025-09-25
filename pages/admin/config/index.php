<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/lib/app.php';
app_boot();
$db = db();
rbac_require_roles($db, ['admin']);
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Konfigurasi — Dashboard Admin</title>
  <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="/assets/components/css/tw.css">
  <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100"
      x-data="configPage('<?= htmlspecialchars($csrf, ENT_QUOTES) ?>')"
      x-init="load()">

  <?php $TOPBAR_SUBTITLE='Dashboard • Konfigurasi'; include dirname(__DIR__, 3) . '/partials/topbar-dashboard-lite.php'; ?>

  <main class="mx-auto max-w-5xl px-4 py-8">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">Konfigurasi Website</h1>
    </div>

    <form class="mt-6 space-y-8" @submit.prevent="save">
      <!-- GENERAL -->
      <section class="rounded-2xl border p-5 dark:border-gray-800">
        <h2 class="text-lg font-semibold">Umum</h2>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="text-sm">Nama RS</label>
            <input x-model="form['site.name']" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
          </div>
          <div>
            <label class="text-sm">Tagline</label>
            <input x-model="form['site.tagline']" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
          </div>
          <div class="sm:col-span-2">
            <label class="text-sm">Alamat</label>
            <textarea x-model="form['contact.address']" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800"></textarea>
          </div>
        </div>
      </section>

      <!-- KONTAK -->
      <section class="rounded-2xl border p-5 dark:border-gray-800">
        <h2 class="text-lg font-semibold">Kontak</h2>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div><label class="text-sm">Telepon</label>
            <input x-model="form['contact.phone']" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
          </div>
          <div><label class="text-sm">WhatsApp</label>
            <input x-model="form['contact.whatsapp']" placeholder="+628xx..." class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
          </div>
          <div><label class="text-sm">Email</label>
            <input x-model="form['contact.email']" type="email" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
          </div>
          <div><label class="text-sm">Website</label>
            <input x-model="form['links.website']" placeholder="https://..." class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
          </div>
        </div>
      </section>

      <!-- SOSIAL -->
      <section class="rounded-2xl border p-5 dark:border-gray-800">
        <h2 class="text-lg font-semibold">Sosial Media</h2>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <template x-for="k in ['social.facebook','social.x','social.instagram','social.tiktok','social.youtube']">
            <div>
              <label class="text-sm" x-text="k.replace('social.','Sosmed: ')"></label>
              <input x-model="form[k]" placeholder="https://..." class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
            </div>
          </template>
        </div>
      </section>

      <!-- SMTP -->
      <section class="rounded-2xl border p-5 dark:border-gray-800">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold">SMTP (Email)</h2>
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" x-model="form['smtp.enabled']">
            <span>Aktifkan SMTP</span>
          </label>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div><label class="text-sm">Host</label>
            <input x-model="form['smtp.host']" placeholder="smtp.gmail.com" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
          </div>
          <div><label class="text-sm">Port</label>
            <input x-model="form['smtp.port']" type="number" min="1" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
          </div>
          <div><label class="text-sm">Username</label>
            <input x-model="form['smtp.username']" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
          </div>
          <div>
            <label class="text-sm">Password</label>
            <div class="relative">
              <input :type="showPwd?'text':'password'" x-model="form['smtp.password']" class="mt-1 w-full rounded-lg border px-3 py-2 pr-10 dark:border-gray-700 dark:bg-gray-800">
              <button type="button" @click="showPwd=!showPwd" class="absolute right-2 top-2 text-xs rounded border px-2 py-1 dark:border-gray-700" x-text="showPwd?'Hide':'Show'"></button>
            </div>
          </div>
          <div>
            <label class="text-sm">Keamanan</label>
            <select x-model="form['smtp.secure']" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
              <option value="">None</option><option value="tls">TLS</option><option value="ssl">SSL</option>
            </select>
          </div>
          <div><label class="text-sm">From Email</label>
            <input x-model="form['smtp.from_email']" type="email" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
          </div>
          <div><label class="text-sm">From Name</label>
            <input x-model="form['smtp.from_name']" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
          </div>
        </div>
        <div class="mt-4">
        <button
          type="button"
          @click="sendTest()"
          class="rounded-lg border px-3 py-1.5 text-sm dark:border-gray-700 disabled:opacity-50"
          :disabled="testing"
          x-text="testing ? 'Mengirim…' : 'Kirim Email Tes'">
        </button>
        </div>
      </section>

      <div class="flex gap-2">
        <button class="btn-primary">Simpan</button>
        <button type="button" class="rounded-lg border px-4 py-2 dark:border-gray-700" @click="load()">Reset</button>
      </div>
      <input type="hidden" name="csrf" :value="csrf">
    </form>
  </main>

  <script>
    function configPage(csrf){
      return {
        csrf,
        form:{},
        showPwd:false,
        testing:false,

        async load(){
          try{
            const r = await fetch('/api-website/config_get.php?keys=' + encodeURIComponent([
              'site.name','site.tagline',
              'contact.phone','contact.whatsapp','contact.email','contact.address',
              'links.website',
              'social.facebook','social.x','social.instagram','social.tiktok','social.youtube',
              'smtp.enabled','smtp.host','smtp.port','smtp.username','smtp.password','smtp.secure','smtp.from_email','smtp.from_name',
            ].join(',')));
            const j = await r.json();
            if(!j.ok) throw new Error(j.error||'Gagal memuat');
            this.form = Object.assign({'smtp.enabled': false}, j.data || {});
            this.form['smtp.enabled'] = (this.form['smtp.enabled']==='1' || this.form['smtp.enabled']===1 || this.form['smtp.enabled']===true);
          }catch(e){
            vtoast.error(e.message || 'Gagal memuat');
          }
        },

        async save(){
          try{
            const body = new URLSearchParams();
            body.set('csrf', this.csrf);
            for (const [k,v] of Object.entries(this.form)) {
              const f = k.replaceAll('.','_');
              if (k==='smtp.enabled') body.set(f, this.form['smtp.enabled'] ? '1':'0');
              else body.set(f, v ?? '');
            }
            const r = await fetch('/api-website/config_save.php', {
              method:'POST',
              headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'},
              body
            });
            const txt = await r.text();
            let j; try { j = JSON.parse(txt); } catch { throw new Error('Respon bukan JSON: '+txt.slice(0,200)); }
            if(!j.ok) throw new Error(j.message||j.error||'Gagal menyimpan');
            toast.success('Konfigurasi tersimpan');
          }catch(e){
            toast.error(e.message || 'Gagal menyimpan');
          }
        },

        async sendTest(){
          try{
            const to = (this.form['contact.email'] || '').trim();
            if (!to) throw new Error('Isi dahulu Email Kontak (contact.email)');
            this.testing = true;

            const r = await fetch('/api-website/smtp_test.php?to=' + encodeURIComponent(to), {
              method: 'POST',
              headers: { 'Accept': 'application/json' }
            });
            const text = await r.text();
            let j; try { j = JSON.parse(text); } catch { throw new Error('Respon bukan JSON: ' + text.slice(0, 200)); }
            if (!j.ok) throw new Error(j.message || j.error || 'Gagal mengirim email tes');

            vtoast.success('Email tes terkirim ke ' + to);
          } catch(e){
            vtoast.error(e.message || 'Gagal kirim email tes');
          } finally {
            this.testing = false;
          }
        }
      }
    }
  </script>
  <?php include dirname(__DIR__, 3) . '/partials/footer-app.php'; ?>
</body>
</html>
