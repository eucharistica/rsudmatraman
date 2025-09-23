<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app.php';
session_boot();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

$token = (string)($_GET['token'] ?? '');
$ok = ctype_xdigit($token) && strlen($token) === 64;
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Atur Ulang Kata Sandi</title>
  <link rel="stylesheet" href="/assets/components/css/tw.css"></script>
</head>
<body class="min-h-screen bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100">
  <?php include __DIR__ . '/../partials/header.php'; ?>
  <main class="mx-auto max-w-md px-4 py-10">
    <h1 class="text-xl font-bold">Atur Ulang Kata Sandi</h1>
    <?php if (!$ok): ?>
      <p class="mt-3 text-red-600">Token reset tidak valid.</p>
    <?php else: ?>
      <form class="mt-4 space-y-3" action="/auth/reset_post.php" method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <div>
          <label class="text-sm">Kata sandi baru</label>
          <input name="password" type="password" required minlength="8" class="w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
        </div>
        <div>
          <label class="text-sm">Ulangi kata sandi</label>
          <input name="password2" type="password" required minlength="8" class="w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
        </div>
        <button class="btn-primary">Simpan</button>
      </form>
    <?php endif; ?>
  </main>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
