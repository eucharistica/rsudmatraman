<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app.php';
app_boot();

$err = $_GET['e'] ?? '';
$msg = [
  'invalid'       => 'Data tidak valid. Mohon cek kembali.',
  'phone'         => 'Nomor WA tidak valid. Gunakan format Indonesia (08 / +62).',
  'csrf'          => 'Sesi kadaluarsa. Muat ulang halaman.',
  'server'        => 'Terjadi kesalahan di server. Coba lagi.',
  'required'      => 'Lengkapi semua isian yang wajib.',
];
$prefill = $_SESSION['complete_profile'] ?? []; 
$namePrefill = trim((string)($prefill['name'] ?? ''));
$emailPrefill = trim((string)($prefill['email'] ?? ''));
$next = (string)($prefill['next'] ?? '/pages/portal');
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth" x-data
      x-init="const th=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'); document.documentElement.classList.toggle('dark', th==='dark')">
      <head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lengkapi Profil — RSUD Matraman</title>
<link rel="stylesheet" href="/assets/components/css/tw.css"></script>

  <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3.x.x/dist/cdn.min.js"></script>
  <style>*{-webkit-tap-highlight-color:transparent}</style>
</head>
<body class="min-h-screen grid place-items-center bg-slate-50 p-4 dark:bg-gray-950 dark:text-gray-100">

  <div class="w-full max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-soft dark:border-gray-800 dark:bg-gray-900"
       x-data="profileForm()">

    <h1 class="text-xl font-semibold">Lengkapi Profil</h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Mohon lengkapi data berikut untuk melanjutkan.</p>

    <?php if ($err && isset($msg[$err])): ?>
      <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
        <?= htmlspecialchars($msg[$err], ENT_QUOTES) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="/auth/complete_profile_post.php"
          class="mt-4 space-y-4"
          @submit.prevent="handleSubmit($event)">

      <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES) ?>">
      <input type="hidden" name="user_id" value="<?= (int)($prefill['user_id'] ?? 0) ?>">

      <label class="block">
        <span class="text-sm">Nama Lengkap</span>
        <input name="name" x-model.trim="name" required
               class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
               placeholder="Nama sesuai identitas" value="<?= htmlspecialchars($namePrefill, ENT_QUOTES) ?>">
        <p class="mt-1 text-xs" :class="nameOk ? 'text-green-600' : 'text-red-500'"
           x-text="nameOk ? 'Oke' : 'Minimal 3 karakter'"></p>
      </label>

      <div>
        <span class="text-sm">Tanggal Lahir</span>
        <div class="mt-1 grid grid-cols-3 gap-2">
          <input name="dob_d" x-model.number="d" required type="number" min="1" max="31"
                 class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800" placeholder="Tanggal">
          <input name="dob_m" x-model.number="m" required type="number" min="1" max="12"
                 class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800" placeholder="Bulan">
          <input name="dob_y" x-model.number="y" required type="number" min="1900" :max="new Date().getFullYear()"
                 class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800" placeholder="Tahun">
        </div>
        <p class="mt-1 text-xs" :class="dobOk ? 'text-green-600' : 'text-red-500'"
           x-text="dobOk ? 'Tanggal valid' : 'Tanggal tidak valid'"></p>
      </div>

      <label class="block">
        <span class="text-sm">Nomor WA</span>
        <input name="phone" x-model.trim="phoneRaw" required
               class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
               placeholder="Nomor Telepon">
        <template x-if="phoneRaw.length>0">
          <p class="mt-1 text-xs"
             :class="phoneOk ? 'text-green-600' : 'text-red-500'"
             x-text="phoneOk ? 'Nomor valid: ' + normalized : 'Nomor tidak valid.'"></p>
        </template>
      </label>

      <div class="pt-2">
      <button type="submit"
                :disabled="!canSubmit"
                class="w-full rounded-lg px-4 py-2 text-white transition
                    bg-[#38bdf8] hover:brightness-110
                    disabled:opacity-60 disabled:cursor-not-allowed">
        Simpan & Lanjut
        </button>
      </div>

      <p class="text-[11px] text-gray-500 dark:text-gray-400">Email: <?= htmlspecialchars($emailPrefill ?: '-', ENT_QUOTES) ?></p>
    </form>
  </div>

  <script>
    function profileForm(){
      return {
        name: <?= json_encode($namePrefill) ?>,
        d: '', m: '', y: '',
        phoneRaw: '',
        get nameOk(){ return (this.name||'').trim().length >= 3; },
        get dobOk(){
          const d=+this.d, m=+this.m, y=+this.y;
          if(!(d&&m&&y)) return false;
          const dt = new Date(y, m-1, d);
          if (dt.getFullYear()!==y || (dt.getMonth()+1)!==m || dt.getDate()!==d) return false;
          // opsional: usia minimum 10 tahun
          const today = new Date();
          if (y > today.getFullYear()) return false;
          return true;
        },
        // normalisasi & validasi nomor Indonesia
        get normalized(){
          // buang spasi, titik, kurung, dash
          const raw = (this.phoneRaw||'').replace(/[.\s\-()]/g,'');
          // +628xx / 628xx / 08xx → normalisasi ke e164 +62...
          if (/^\+?62/.test(raw)) {
            return '+' + raw.replace(/^\+?/, '');
          }
          if (/^0/.test(raw)) {
            return '+62' + raw.substring(1);
          }
          return raw; 
        },
        get phoneOk(){
          const raw = (this.phoneRaw||'').trim();
          if (!raw) return false;
          // tolak input random: hanya digit, spasi, +, -, ., (), tidak boleh huruf
          if (/[A-Za-z]/.test(raw)) return false;
          // setelah normalisasi harus match: +628[8-11 digit]
          const n = this.normalized;
          return /^\+628\d{8,11}$/.test(n);
        },
        get canSubmit(){ return this.nameOk && this.dobOk && this.phoneOk; },
        handleSubmit(ev){
          if (!this.canSubmit) {
            // tampilkan pesan kecil
            ev.preventDefault();
            return;
          }
          // submit form jika valid
          ev.target.submit();
        }
      }
    }
  </script>
</body>
</html>
