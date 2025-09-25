<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app.php';
app_boot();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cek Email — RSUD Matraman</title>
  <link rel="stylesheet" href="/assets/components/css/tw.css">
</head>
<body class="min-h-screen grid place-items-center bg-slate-50 dark:bg-gray-950 dark:text-gray-100">
  <main class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 text-center dark:border-gray-800 dark:bg-gray-900">
    <h1 class="text-xl font-semibold">Cek Email Anda</h1>
    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
      Jika email terdaftar, kami telah mengirim tautan untuk mengatur ulang kata sandi.
    </p>
    <a href="/auth" class="mt-6 inline-block rounded-lg bg-primary px-4 py-2 text-white hover:brightness-110">Kembali ke Login</a>
  </main>
</body>
</html>
