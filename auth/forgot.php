<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app.php';
session_boot();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lupa Password</title>
  <link rel="stylesheet" href="/assets/components/css/tw.css"></script>
</head>
<body class="min-h-screen bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100">
  <?php include __DIR__ . '/../partials/header.php'; ?>
  <main class="mx-auto max-w-md px-4 py-10">
    <h1 class="text-xl font-bold">Lupa Password</h1>
    <form class="mt-4 space-y-3" action="/auth/forgot_post.php" method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
      <label class="block text-sm">Email</label>
      <input name="email" type="email" required class="w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
      <button class="btn-primary">Kirim Link Reset</button>
    </form>
  </main>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
