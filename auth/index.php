<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
session_boot(); 

function is_safe_next(string $n): bool {
  // Harus path internal absolut seperti /pages/portal
  if ($n === '' || $n[0] !== '/') return false;
  // Cegah header injection
  if (str_contains($n, "\r") || str_contains($n, "\n")) return false;
  // Tolak protocol-relative dan URL absolut
  if (str_starts_with($n, '//')) return false;
  if (preg_match('/^[a-z][a-z0-9+\-.]*:/i', $n)) return false; // http:, javascript:, dll
  return true;
}

$CFG = require __DIR__ . '/../_private/website.php';
$SITE_KEY = $CFG['RECAPTCHA_SITE_KEY'] ?? '';

$next = '/pages/portal';
if (isset($_GET['next']) && is_safe_next((string)$_GET['next'])) {
  $next = (string)$_GET['next'];
}


if (auth_is_logged_in()) {
  $role = auth_role();
  if ($next && $next !== '/login' && $next !== '/auth/') {
    header('Location: ' . $next, true, 302); exit;
  }
  $dest = in_array($role, ['admin','editor'], true) ? '/pages/dashboard' : '/pages/portal';
  header('Location: ' . $dest, true, 302); exit;
}

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf'];

$err = $_GET['e'] ?? '';
$errors = [
  'captcha'        => 'Verifikasi reCAPTCHA gagal. Coba lagi.',
  'login'          => 'Email atau kata sandi salah.',
  'inactive'       => 'Akun Anda belum aktif / diblokir.',
  'invalid'        => 'Data tidak valid. Mohon cek kembali.',
  'exists'         => 'Email sudah terdaftar. Silakan masuk.',
  'google'         => 'Login Google gagal. Coba lagi.',
  'google_denied'  => 'Login Google dibatalkan. Silakan pilih akun dan izinkan akses untuk melanjutkan.',
];

?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth"
      x-data="{theme:localStorage.getItem('theme')|| (matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light')}"
      x-init="document.documentElement.classList.toggle('dark', theme==='dark');
              $watch('theme',t=>localStorage.setItem('theme',t));">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Masuk / Daftar — RSUD Matraman</title>

  <!-- Favicon -->
  <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">
  <link rel="shortcut icon" href="/assets/img/favicon.ico" type="image/x-icon">

  <!-- SEO -->
  <meta name="description" content="Halaman masuk dan pendaftaran akun RSUD Matraman. Login dengan Google atau email & kata sandi." />
  <meta name="theme-color" content="#38bdf8" />

  <!-- Tailwind & Alpine -->
  <link rel="stylesheet" href="/assets/components/css/tw.css"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <?php if ($SITE_KEY): ?>
  <script>
    // Render eksplisit dua widget: login & register
    window.__recaptchaWidgets = { login:null, register:null };
    function onRecaptchaReady(){
      try{
        if (document.getElementById('recaptcha-login') && !window.__recaptchaWidgets.login){
          window.__recaptchaWidgets.login = grecaptcha.render('recaptcha-login', {
            'sitekey': '<?= htmlspecialchars($SITE_KEY, ENT_QUOTES) ?>'
          });
        }
        if (document.getElementById('recaptcha-register') && !window.__recaptchaWidgets.register){
          window.__recaptchaWidgets.register = grecaptcha.render('recaptcha-register', {
            'sitekey': '<?= htmlspecialchars($SITE_KEY, ENT_QUOTES) ?>'
          });
        }
      }catch(e){ console.error('recaptcha render:', e); }
    }
  </script>
  <script src="https://www.google.com/recaptcha/api.js?onload=onRecaptchaReady&render=explicit&hl=id" async defer></script>
  <?php endif; ?>

  <style>*{-webkit-tap-highlight-color:transparent}</style>
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">

  <?php include __DIR__ . '/../partials/header.php'; ?>

  <main class="mx-auto max-w-7xl px-4 py-12">
    <div class="grid grid-cols-1 items-start gap-10 lg:grid-cols-2">
      <!-- Info panel -->
      <section>
        <p class="text-sm font-semibold text-primary">RSUD Matraman</p>
        <h1 class="mt-2 text-3xl font-black sm:text-4xl">Masuk atau Daftar Akun</h1>
        <p class="mt-3 text-gray-600 dark:text-gray-300">
          Gunakan akun Google atau email & kata sandi untuk mengakses portal dan dashboard.
          Jika login dengan Google pertama kali, Anda akan diminta melengkapi profil singkat (Nama, Tanggal Lahir, No WA).
        </p>

        <ul class="mt-6 space-y-2 text-sm text-gray-600 dark:text-gray-300">
          <li>• Login aman menggunakan OAuth 2.0 (Google) dan reCAPTCHA.</li>
          <li>• Data pribadi hanya digunakan untuk kebutuhan layanan.</li>
          <li>• Anda dapat mengganti akun saat login.</li>
        </ul>
      </section>

      <!-- Card: Login / Register -->
      <section
        x-data="{
          mode: (new URLSearchParams(location.search)).get('mode')==='register' ? 'register' : 'login',
          // Register state & validators
          reg:{name:'', email:'', d:'', m:'', y:'', phone:'', pw:'', pw2:''},
          isIndo(p){ return /^(\+62|62|0)8\d{8,11}$/.test((p||'').trim()); },
          strong(pw){ return /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/.test(pw||''); },
          dobValid(){
            const d=+this.reg.d||0, m=+this.reg.m||0, y=+this.reg.y||0;
            if(!(d&&m&&y)) return false;
            const dt=new Date(y, m-1, d);
            return dt.getFullYear()==y && (dt.getMonth()+1)==m && dt.getDate()==d;
          },
          disabledReg(){
            return !(this.reg.name.trim().length>=3
                     && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(this.reg.email||'')
                     && this.dobValid()
                     && this.isIndo(this.reg.phone)
                     && this.strong(this.reg.pw)
                     && (this.reg.pw===this.reg.pw2));
          },
        }"
        class="rounded-2xl border border-gray-200 bg-white p-6 shadow-soft dark:border-gray-800 dark:bg-gray-900 w-full">

        <!-- Tabs -->
        <div class="mb-5 grid grid-cols-2 rounded-lg bg-gray-100 p-1 dark:bg-gray-800/60">
          <button class="rounded-md py-2 text-sm font-medium"
                  :class="mode==='login' ? 'bg-white shadow dark:bg-gray-900' : ''"
                  @click="mode='login'">Masuk</button>
          <button class="rounded-md py-2 text-sm font-medium"
                  :class="mode==='register' ? 'bg-white shadow dark:bg-gray-900' : ''"
                  @click="mode='register'">Daftar</button>
        </div>

        <!-- Error flash -->
        <?php if ($err && isset($errors[$err])): ?>
          <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
            <?= htmlspecialchars($errors[$err], ENT_QUOTES) ?>
          </div>
        <?php endif; ?>

        <!-- LOGIN FORM -->
        <form x-show="mode==='login'" method="POST" action="/auth/login_password.php" class="space-y-3">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>">
          <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES) ?>">

          <label class="block">
            <span class="text-sm">Alamat Email</span>
            <input name="email" required type="email"
                   class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
                   placeholder="nama@contoh.com">
          </label>

          <label class="block">
            <span class="text-sm">Kata Sandi</span>
            <div class="relative mt-1" x-data="{show:false}">
              <input :type="show?'text':'password'" name="password" required
                    class="w-full rounded-lg border px-3 py-2 pr-10 dark:border-gray-700 dark:bg-gray-800"
                    placeholder="••••••••">
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs"
                      @click="show=!show">
                <span x-text="show ? 'Hide' : 'Show'"></span>
              </button>
            </div>
          </label>

          <?php if ($SITE_KEY): ?>
            <div class="mt-2">
              <div id="recaptcha-login" class="g-recaptcha"></div>
            </div>
          <?php endif; ?>

          <button type="submit"
                  class="mt-2 w-full rounded-lg bg-primary px-4 py-2 text-white hover:brightness-110">
            Masuk
          </button>

          <div class="my-3 text-center text-xs text-gray-500 dark:text-gray-400">atau</div>

          <a href="/auth/google.php?next=<?= urlencode($next) ?>"
             class="inline-flex w-full items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800">
            <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" class="h-4 w-4" alt="">
            Lanjutkan dengan Google
          </a>

          <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Pertama kali dengan Google? Anda akan diminta melengkapi Nama, Tgl Lahir, dan No WA sebelum melanjutkan.
          </p>
        </form>

        <!-- REGISTER FORM -->
        <form x-show="mode==='register'" x-cloak method="POST" action="/auth/register.php" class="space-y-3">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>">
          <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES) ?>">

          <label class="block">
            <span class="text-sm">Nama Lengkap</span>
            <input name="name" x-model="reg.name" required
                   class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
                   placeholder="Nama sesuai KTP">
          </label>

          <label class="block">
            <span class="text-sm">Alamat Email</span>
            <input name="email" x-model="reg.email" required type="email"
                   class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
                   placeholder="nama@contoh.com">
          </label>

          <div>
            <span class="text-sm">Tanggal Lahir</span>
            <div class="mt-1 grid grid-cols-3 gap-2">
              <input name="dob_d" x-model="reg.d" required type="number" min="1" max="31"
                     class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800" placeholder="HH">
              <input name="dob_m" x-model="reg.m" required type="number" min="1" max="12"
                     class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800" placeholder="BB">
              <input name="dob_y" x-model="reg.y" required type="number" min="1900" max="2100"
                     class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800" placeholder="TTTT">
            </div>
            <p class="mt-1 text-xs"
              :class="dobValid() ? 'text-green-600' : 'text-red-500'"
              x-text="dobValid() ? 'Tanggal valid' : 'Lengkapi tanggal lahir'">
            </p>
          </div>

          <label class="block">
            <span class="text-sm">Nomor WA (Indonesia)</span>
            <input name="phone" x-model="reg.phone" required
                   class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
                   placeholder="08xxxxxxxxxx atau +628xxxxxxxxxx">
            <p class="mt-1 text-xs"
              :class="isIndo(reg.phone) ? 'text-green-600' : 'text-red-500'"
              x-text="isIndo(reg.phone) ? 'Nomor valid' : 'Nomor harus Indonesia (08 / +62)'">
            </p>
          </label>

          <label class="block">
            <span class="text-sm">Kata Sandi (min 8, huruf besar/kecil & angka)</span>
            <div class="relative mt-1" x-data="{show:false}">
              <input :type="show?'text':'password'" x-model="reg.pw" name="password" required
                    class="w-full rounded-lg border px-3 py-2 pr-10 dark:border-gray-700 dark:bg-gray-800" placeholder="••••••••">
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs"
                      @click="show=!show"><span x-text="show ? 'Hide' : 'Show'"></span></button>
            </div>
            <p class="mt-1 text-xs" :class="strong(reg.pw)?'text-green-600':'text-red-500'"
              x-text="strong(reg.pw)?'Kuat':'Lemah'"></p>
          </label>

          <label class="block">
            <span class="text-sm">Ulangi Kata Sandi</span>
            <div class="relative mt-1" x-data="{show:false}">
              <input :type="show?'text':'password'" x-model="reg.pw2" name="password_confirm" required
                    class="w-full rounded-lg border px-3 py-2 pr-10 dark:border-gray-700 dark:bg-gray-800" placeholder="••••••••">
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs"
                      @click="show=!show"><span x-text="show ? 'Hide' : 'Show'"></span></button>
            </div>
            <p class="mt-1 text-xs"
              :class="reg.pw && reg.pw===reg.pw2 ? 'text-green-600' : 'text-red-500'"
              x-text="(reg.pw && reg.pw===reg.pw2) ? 'Sama' : 'Tidak sama'"></p>
          </label>

          <?php if ($SITE_KEY): ?>
            <div class="mt-2">
              <div id="recaptcha-register" class="g-recaptcha"></div>
            </div>
          <?php endif; ?>


          <button type="submit" :disabled="disabledReg()"
                  class="mt-2 w-full rounded-lg bg-primary px-4 py-2 text-white hover:brightness-110 disabled:opacity-60">
            Daftar
          </button>
        </form>

        <p class="mt-5 text-xs text-gray-500 dark:text-gray-400">
          Dengan masuk/daftar, Anda menyetujui kebijakan privasi dan ketentuan layanan RSUD Matraman.
        </p>
      </section>
    </div>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
