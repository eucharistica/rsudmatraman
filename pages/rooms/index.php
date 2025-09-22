<?php
require_once __DIR__ . '/../../lib/session.php';
session_boot();
$pageTitle = 'Informasi Kamar — RSUD Matraman';
$pageDescription = 'Informasi ketersediaan tempat tidur rawat inap RSUD Matraman';
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head><?php include __DIR__ . '/../../partials/head.php'; ?></head>
<body class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
  <?php include __DIR__ . '/../../partials/header.php'; ?>
  <?php include __DIR__ . '/../../assets/components/jambesuk-widget.php'; ?>
  <?php include __DIR__ . '/../../assets/components/rooms-widget.php'; ?>
  <?php include __DIR__ . '/../../partials/footer.php'; ?>
</body>
</html>
