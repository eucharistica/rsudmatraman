<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app.php';
app_boot();

if (auth_is_logged_in()) {
  $u = auth_current_user();
  header('Location: ' . auth_default_destination($u, null), true, 302);
  exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lupa Password — RSUD Matraman</title>
  <link rel="stylesheet" href="/assets/components/css/tw.css">
  <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen grid place-items-center bg-slate-50 dark:bg-gray-950 dark:text-gray-100">
  <main class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
    <h1 class="text-xl font-semibold">Lupa Password</h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Masukkan email Anda. Kami akan mengirim tautan untuk mengatur ulang kata sandi.</p>

    <form method="POST" action="/auth/forgot_post.php" class="mt-4 space-y-3">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>">
      <label class="block">
        <span class="text-sm">Email</span>
        <input name="email" required type="email" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800" placeholder="nama@email.com">
      </label>
      <button class="w-full rounded-lg bg-primary px-4 py-2 text-white hover:brightness-110">Kirim Tautan Reset</button>
    </form>

    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
      Ingat kata sandi? <a href="/auth" class="underline">Kembali ke login</a>
    </p>
  </main>
</body>
</html>
