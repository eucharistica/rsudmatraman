<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app.php';
app_boot();

$s = (string)($_GET['s'] ?? '');
$t = (string)($_GET['t'] ?? '');

$valid = false; $uid = 0;
if ($s !== '' && $t !== '' && ctype_xdigit($s) && ctype_xdigit($t) && strlen($s)===16 && strlen($t)===64) {
  $db = db();
  $st = $db->prepare("SELECT user_id, verifier_hash, expires_at, used FROM password_resets WHERE selector=:s LIMIT 1");
  $st->execute([':s'=>$s]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row && (int)$row['used'] === 0 && strtotime((string)$row['expires_at']) > time()) {
    $hash = (string)$row['verifier_hash'];
    if (hash_equals($hash, hash('sha256',$t))) {
      $valid = true; $uid = (int)$row['user_id'];
    }
  }
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password — RSUD Matraman</title>
  <link rel="stylesheet" href="/assets/components/css/tw.css">
  <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen grid place-items-center bg-slate-50 dark:bg-gray-950 dark:text-gray-100">
  <main class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
    <?php if (!$valid): ?>
      <h1 class="text-xl font-semibold">Tautan Tidak Valid</h1>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Tautan reset tidak valid atau sudah kadaluarsa.</p>
      <a class="mt-4 inline-block rounded-lg border px-3 py-1.5 dark:border-gray-700" href="/auth/forgot.php">Kirim ulang tautan</a>
    <?php else: ?>
      <h1 class="text-xl font-semibold">Buat Kata Sandi Baru</h1>
      <form method="POST" action="/auth/reset_post.php" class="mt-4 space-y-3"
            x-data="{pw:'',pw2:'', strong(){return /[A-Z]/.test(this.pw)&&/[a-z]/.test(this.pw)&&/\d/.test(this.pw)&&this.pw.length>=8}, match(){return this.pw!==''&&this.pw===this.pw2}}">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>">
        <input type="hidden" name="selector" value="<?= htmlspecialchars($s, ENT_QUOTES) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($t, ENT_QUOTES) ?>">

        <label class="block">
          <span class="text-sm">Kata Sandi Baru</span>
          <input type="password" x-model="pw" name="password" required class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800" placeholder="Minimal 8 karakter; kombinasi huruf & angka">
          <p class="mt-1 text-xs" :class="strong()?'text-green-600':'text-red-500'">
            <span x-show="strong()">Kata sandi kuat</span>
            <span x-show="!strong()">Gunakan huruf besar/kecil & angka (≥8)</span>
          </p>
        </label>

        <label class="block">
          <span class="text-sm">Ulangi Kata Sandi</span>
          <input type="password" x-model="pw2" name="password_confirm" required class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800" placeholder="Ulangi kata sandi">
          <p class="mt-1 text-xs" :class="match()?'text-green-600':'text-red-500'">
            <span x-show="match()">Sama</span><span x-show="!match()">Tidak sama</span>
          </p>
        </label>

        <button :disabled="!(strong()&&match())" class="w-full rounded-lg bg-primary px-4 py-2 text-white hover:brightness-110 disabled:opacity-60">Simpan Kata Sandi</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
