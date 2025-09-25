<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app.php';
app_boot();

// --- helper ---
$CFG_FILE  = __DIR__ . '/../_private/website.php';
$CFG       = is_file($CFG_FILE) ? require $CFG_FILE : [];
$SITE_KEY  = $CFG['RECAPTCHA_SITE_KEY'] ?? '';

$next = '/pages/portal';
if (isset($_GET['next']) && auth_is_safe_next((string)$_GET['next'])) {
  $next = (string)$_GET['next'];
}

// Sudah login? arahkan
if (auth_is_logged_in()) {
  $u = auth_current_user();
  $dest = auth_default_destination($u, $_GET['next'] ?? null);
  header('Location: '.$dest, true, 302);
  exit;
}

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf'];

// Error flash dari query ?e=
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

// Optional header info
$u     = auth_current_user();
$name  = trim((string)($u['name']  ?? 'Pengguna'));
$email = trim((string)($u['email'] ?? ''));
$avatar = trim((string)($u['avatar'] ?? ''));
if ($avatar === '' || !preg_match('~^https?://~i', $avatar)) {
  $hash   = $email !== '' ? md5(strtolower(trim($email))) : md5($name ?: 'user');
  $avatar = 'https://www.gravatar.com/avatar/' . $hash . '?s=160&d=identicon';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Masuk / Daftar — RSUD Matraman</title>
  <meta name="description" content="Halaman masuk dan pendaftaran akun RSUD Matraman. Login dengan Google atau email & kata sandi." />
  <meta name="theme-color" content="#38bdf8" />
  <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="/assets/components/css/tw.css">
  <style>[x-cloak]{display:none!important} *{-webkit-tap-highlight-color:transparent}</style>

  <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3.x.x/dist/cdn.min.js"></script>

  <script>
  document.addEventListener('alpine:init', function () {
    Alpine.data('authCard', function (initMode, csrf, next, useRecaptcha) {
      return {
        // ===== Tabs =====
        mode: (initMode === 'register' ? 'register' : 'login'),
        switchTo(m){
          this.mode = (m==='register' ? 'register' : 'login');
          if (this.useRecaptcha) {
            let self = this;
            this.$nextTick(function(){
              if (self.mode==='login') {
                window.renderRecaptchaIfNeeded('recaptcha-login','login');
              } else {
                window.renderRecaptchaIfNeeded('recaptcha-register','register');
              }
            });
          }
        },

        // ===== State umum =====
        csrf: csrf, next: next, useRecaptcha: !!useRecaptcha,

        // ===== Register state =====
        name:'', email:'', phone:'', pw:'', pw2:'',
        dob_d:'', dob_m:'', dob_y:'',
        emailStatus:'',   // '', 'checking', 'ok', 'exists'
        years:[], months:[1,2,3,4,5,6,7,8,9,10,11,12], days:[],

        init(){
          if (this.years.length === 0) {
            const Y = new Date().getFullYear();
            for (let i=Y; i>=1900; i--) this.years.push(i);
          }
          this.updateDays();

          if (this.useRecaptcha) {
            const renderCurrent = () => {
              if (this.mode==='login') {
                window.renderRecaptchaIfNeeded('recaptcha-login','login');
              } else {
                window.renderRecaptchaIfNeeded('recaptcha-register','register');
              }
            };

            // Jika sudah ready, render segera setelah Alpine apply x-show
            if (window.__recaptcha && window.__recaptcha.ready) {
              this.$nextTick(renderCurrent);
            } else {
              // Jika belum ready, tunggu event dari onRecaptchaReady()
              window.addEventListener('recaptcha-ready', () => {
                this.$nextTick(renderCurrent);
              }, { once:true });
            }
          }
        },

        updateDays(){
          const m = parseInt(this.dob_m||0,10);
          const y = parseInt(this.dob_y||0,10);
          let dmax = 31;
          if ([4,6,9,11].includes(m)) dmax = 30;
          else if (m===2){
            const leap = (y%4===0 && (y%100!==0 || y%400===0));
            dmax = leap ? 29 : 28;
          }
          const dSel = parseInt(this.dob_d||0,10);
          this.days = [];
          for (let d=1; d<=dmax; d++) this.days.push(d);
          if (dSel > dmax) this.dob_d = '';
        },

        validName(){
          const s = (this.name||'').trim();
          if (s.length < 3) return false;
          if (/\d/.test(s)) return false;
          return /^[A-Za-zÀ-ÖØ-öø-ÿ'\.\-\s]+$/.test(s);
        },
        validEmail(){
          const e = (this.email||'').trim();
          return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(e);
        },
        checkEmailAjax(){
          if (!this.validEmail()) { this.emailStatus=''; return; }
          this.emailStatus = 'checking';
          const self = this;
          fetch('/api-website/check_email.php?email=' + encodeURIComponent(this.email), { headers:{'Accept':'application/json'} })
            .then(r=>r.json())
            .then(j=>{ self.emailStatus = (j && j.ok===true) ? (j.exists ? 'exists' : 'ok') : ''; })
            .catch(()=>{ self.emailStatus=''; });
        },
        validPhone(){
          const p = (this.phone||'').trim();
          return /^(\+62|62|0)8\d{8,11}$/.test(p);
        },
        strongPw(){
          const s = this.pw || '';
          return /[A-Z]/.test(s) && /[a-z]/.test(s) && /\d/.test(s) && s.length >= 8;
        },
        pwMatch(){ return (this.pw||'') !== '' && this.pw === this.pw2; },
        dobValid(){
          const d = parseInt(this.dob_d||0,10);
          const m = parseInt(this.dob_m||0,10);
          const y = parseInt(this.dob_y||0,10);
          if (!(d && m && y)) return false;
          const dt = new Date(y, m-1, d);
          return dt.getFullYear()===y && (dt.getMonth()+1)===m && dt.getDate()===d;
        },
        disabledReg(){
          return !(this.validName() && this.validEmail() && this.emailStatus!=='exists'
                   && this.dobValid() && this.validPhone() && this.strongPw() && this.pwMatch());
        },

        // ===== Submit Login: ambil token aktif & submit form =====
        beforeSubmitLogin(formEl){
          if (!this.useRecaptcha) { formEl.submit(); return; }
          const t = window.getActiveRecaptchaToken('login');
          if (!t) { alert('Silakan centang reCAPTCHA.'); return; }
          formEl.querySelector('input[name=recaptcha_token]').value = t;
          formEl.submit();
        },

        // ===== Submit Register: NON-AJAX (hindari blokir CSP connect-src) =====
        beforeSubmitRegister(formEl){
          if (this.disabledReg()) return;

          // jika ingin kirim sebagai satu field 'dob', aktifkan 2 baris ini dan sediakan <input type=hidden name=dob>:
          // const dob = this.dob_y + '-' + ('0'+this.dob_m).slice(-2) + '-' + ('0'+this.dob_d).slice(-2);
          // formEl.querySelector('input[name=dob]')?.setAttribute('value', dob);

          if (!this.useRecaptcha) { formEl.submit(); return; }
          const t = window.getActiveRecaptchaToken('register');
          if (!t) { alert('Silakan centang reCAPTCHA.'); return; }
          formEl.querySelector('input[name=recaptcha_token]').value = t;
          formEl.submit();
        }
      };
    });
  });
  </script>

<?php if ($SITE_KEY): ?>
<script>
  // Global state
  window.__recaptcha = { loginId:null, regId:null, ready:false };

  // Dipanggil oleh Google API ketika siap
  function onRecaptchaReady(){
    window.__recaptcha.ready = true;
    // Beritahu Alpine/JS lain bahwa reCAPTCHA sudah siap
    window.dispatchEvent(new Event('recaptcha-ready'));
  }

  // Render helper (aman dipanggil berulang)
  window.renderRecaptchaIfNeeded = function(containerId, type){
    if (!window.__recaptcha.ready) return;
    var el = document.getElementById(containerId);
    if (!el) return;

    if (type === 'login') {
      if (window.__recaptcha.loginId === null) {
        window.__recaptcha.loginId = grecaptcha.render(containerId, { sitekey: '<?= htmlspecialchars($SITE_KEY, ENT_QUOTES) ?>' });
      } else {
        grecaptcha.reset(window.__recaptcha.loginId);
      }
    } else {
      if (window.__recaptcha.regId === null) {
        window.__recaptcha.regId = grecaptcha.render(containerId, { sitekey: '<?= htmlspecialchars($SITE_KEY, ENT_QUOTES) ?>' });
      } else {
        grecaptcha.reset(window.__recaptcha.regId);
      }
    }
  };

  // Ambil token aktif
  window.getActiveRecaptchaToken = function(type){
    if (type === 'login' && window.__recaptcha.loginId !== null)   return grecaptcha.getResponse(window.__recaptcha.loginId);
    if (type === 'register' && window.__recaptcha.regId !== null) return grecaptcha.getResponse(window.__recaptcha.regId);
    return '';
  };
</script>
<script src="https://www.google.com/recaptcha/api.js?onload=onRecaptchaReady&render=explicit&hl=id" async defer></script>
<?php endif; ?>
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
          Jika login dengan Google pertama kali, Anda akan diminta melengkapi profil singkat (Nama, Tgl Lahir, No WA).
        </p>
        <ul class="mt-6 space-y-2 text-sm text-gray-600 dark:text-gray-300">
          <li>• Login aman menggunakan OAuth 2.0 (Google) dan reCAPTCHA.</li>
          <li>• Data pribadi hanya digunakan untuk kebutuhan layanan.</li>
          <li>• Anda dapat mengganti akun saat login.</li>
        </ul>
      </section>

      <!-- Card: Login / Register -->
      <section
        x-data="authCard($el.dataset.mode, $el.dataset.csrf, $el.dataset.next, $el.dataset.recaptcha==='1')"
        x-init="init()"
        class="w-full rounded-2xl border border-gray-200 bg-white p-6 shadow-soft dark:border-gray-800 dark:bg-gray-900"
        data-mode="<?= (isset($_GET['mode']) && $_GET['mode']==='register') ? 'register' : 'login' ?>"
        data-csrf="<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>"
        data-next="<?= htmlspecialchars($next, ENT_QUOTES) ?>"
        data-recaptcha="<?= $SITE_KEY ? '1':'0' ?>"
      >
        <!-- Tabs -->
        <div class="mb-5 grid grid-cols-2 rounded-lg bg-gray-100 p-1 dark:bg-gray-800/60">
          <button type="button" class="rounded-md py-2 text-sm font-medium"
                  :class="mode==='login' ? 'bg-white shadow dark:bg-gray-900' : ''"
                  @click="switchTo('login')">Masuk</button>
          <button type="button" class="rounded-md py-2 text-sm font-medium"
                  :class="mode==='register' ? 'bg-white shadow dark:bg-gray-900' : ''"
                  @click="switchTo('register')">Daftar</button>
        </div>

        <!-- Error flash -->
        <?php if ($err && isset($errors[$err])): ?>
          <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
            <?= htmlspecialchars($errors[$err], ENT_QUOTES) ?>
          </div>
        <?php endif; ?>

        <!-- ===== LOGIN ===== -->
        <form x-show="mode==='login'" x-cloak method="POST" action="/auth/login_password.php"
              @submit.prevent="beforeSubmitLogin($el)" class="space-y-3">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>">
          <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES) ?>">
          <input type="hidden" name="recaptcha_token" value="">

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
                      @click="show=!show" x-text="show ? 'Hide' : 'Show'"></button>
            </div>
          </label>

          <?php if ($SITE_KEY): ?>
            <div class="mt-2"><div id="recaptcha-login" class="g-recaptcha"></div></div>
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

          <div class="mt-2 text-right">
            <a href="/auth/forgot.php"
              class="inline-block text-xs text-gray-500 underline hover:text-primary dark:text-gray-400">
              Lupa password?
            </a>
          </div>
        </form>

        <!-- ===== REGISTER (NON-AJAX) ===== -->
        <form x-show="mode==='register'" x-cloak method="POST" action="/auth/register.php"
              @submit.prevent="beforeSubmitRegister($el)" class="space-y-3">
          <input type="hidden" name="csrf" :value="csrf">
          <input type="hidden" name="next" :value="next">
          <input type="hidden" name="recaptcha_token" value="">
          <!-- Jika ingin kirim single field tanggal:
          <input type="hidden" name="dob" value="">
          -->

          <a href="/auth/google.php?next=<?= urlencode($next) ?>"
            class="inline-flex w-full items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800">
            <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" class="h-4 w-4" alt="">
            Daftar dan lanjutkan dengan Google
          </a>

          <div class="my-3 text-center text-xs text-gray-500 dark:text-gray-400">atau</div>

          <!-- Nama -->
          <label class="block">
            <span class="text-sm">Nama Lengkap</span>
            <input x-model.trim="name" name="name" autocomplete="name"
                   class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
                   placeholder="Nama sesuai KTP">
            <p class="mt-1 text-xs" :class="validName() ? 'text-green-600' : 'text-red-500'">
              <span x-show="validName()">Nama valid</span>
              <span x-show="!validName()">Nama minimal 3 huruf & tanpa angka</span>
            </p>
          </label>

          <!-- Email -->
          <label class="block">
            <span class="text-sm">Alamat Email</span>
            <input x-model.trim="email" name="email" type="email" autocomplete="email"
                   @blur="checkEmailAjax()"
                   class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
                   placeholder="nama@email.com">
            <p class="mt-1 text-xs"
              :class="emailStatus==='exists' ? 'text-red-500' : (validEmail() && emailStatus==='ok' ? 'text-green-600' : 'text-gray-500')">
              <span x-show="emailStatus==='checking'">Memeriksa email…</span>
              <span x-show="emailStatus==='exists'">Email sudah terdaftar</span>
              <span x-show="validEmail() && emailStatus==='ok'">Email tersedia</span>
              <span x-show="!validEmail()">Gunakan format email yang benar</span>
            </p>
          </label>

          <!-- Tanggal lahir -->
          <div>
            <span class="text-sm">Tanggal Lahir</span>
            <div class="mt-1 grid grid-cols-3 gap-2">
              <select x-model.number="dob_d" name="dob_d"
                      class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                <option value="">Tanggal</option>
                <template x-for="d in days" :key="'d-'+d"><option :value="d" x-text="d"></option></template>
              </select>
              <select x-model.number="dob_m" name="dob_m" @change="updateDays()"
                      class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                <option value="">Bulan</option>
                <template x-for="m in months" :key="'m-'+m"><option :value="m" x-text="m"></option></template>
              </select>
              <select x-model.number="dob_y" name="dob_y" @change="updateDays()"
                      class="rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                <option value="">Tahun</option>
                <template x-for="y in years" :key="'y-'+y"><option :value="y" x-text="y"></option></template>
              </select>
            </div>
            <p class="mt-1 text-xs" :class="dobValid() ? 'text-green-600' : 'text-red-500'">
              <span x-show="dobValid()">Tanggal valid</span>
              <span x-show="!dobValid()">Pilih hari, bulan, dan tahun yang valid</span>
            </p>
          </div>

          <!-- Nomor WA -->
          <label class="block">
            <span class="text-sm">Nomor WA</span>
            <input x-model.trim="phone" name="phone" inputmode="tel" autocomplete="tel"
                   class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
                   placeholder="08xxxxxxxxxx">
            <p class="mt-1 text-xs" :class="validPhone() ? 'text-green-600' : 'text-red-500'">
              <span x-show="validPhone()">Nomor valid</span>
              <span x-show="!validPhone()">Nomor tidak valid</span>
            </p>
          </label>

          <!-- Kata Sandi -->
          <label class="block">
            <span class="text-sm">Kata Sandi (min 8, huruf besar/kecil & angka)</span>
            <div class="relative mt-1" x-data="{show:false}">
              <input :type="show?'text':'password'" x-model="pw" name="password" required
                    class="w-full rounded-lg border px-3 py-2 pr-10 dark:border-gray-700 dark:bg-gray-800"
                    placeholder="••••••••" autocomplete="new-password">
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs"
                      @click="show=!show"><span x-show="!show">Show</span><span x-show="show">Hide</span></button>
            </div>
            <p class="mt-1 text-xs" :class="strongPw() ? 'text-green-600' : 'text-red-500'">
              <span x-show="strongPw()">Kata sandi kuat</span>
              <span x-show="!strongPw()">Wajib kombinasi huruf besar, kecil, dan angka (≥8)</span>
            </p>
          </label>

          <!-- Ulangi Password -->
          <label class="block">
            <span class="text-sm">Ulangi Kata Sandi</span>
            <div class="relative mt-1" x-data="{show:false}">
              <input :type="show?'text':'password'" x-model="pw2" name="password_confirm" required
                    class="w-full rounded-lg border px-3 py-2 pr-10 dark:border-gray-700 dark:bg-gray-800"
                    placeholder="••••••••" autocomplete="new-password">
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs"
                      @click="show=!show"><span x-show="!show">Show</span><span x-show="show">Hide</span></button>
            </div>
            <p class="mt-1 text-xs" :class="pwMatch() ? 'text-green-600' : 'text-red-500'">
              <span x-show="pwMatch()">Sama</span>
              <span x-show="!pwMatch()">Tidak sama</span>
            </p>
          </label>

          <?php if ($SITE_KEY): ?>
            <div class="mt-2"><div id="recaptcha-register" class="g-recaptcha"></div></div>
          <?php endif; ?>

          <button type="submit"
                  :disabled="disabledReg()"
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
