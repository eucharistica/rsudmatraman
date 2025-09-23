<?php
require_once __DIR__ . '/../../lib/session.php';
session_boot();
$pageTitle = 'Informasi Jadwal — RSUD Matraman';
$pageDescription = 'Informasi jadwal praktik dokter di RSUD Matraman';
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <?php include dirname(__DIR__, 2) . '/partials/head.php'; ?>
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
<div x-data="app()" x-init="init()">
  <?php include dirname(__DIR__, 2) . '/partials/topbar-app-lite.php'; ?>
  <?php include dirname(__DIR__, 2) . '/assets/components/jadwal-widget.php'; ?>
  <?php include dirname(__DIR__, 2) . '/partials/footer-app.php'; ?>
</body>
</html>
