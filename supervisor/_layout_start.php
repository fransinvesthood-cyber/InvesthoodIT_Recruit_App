<?php
$pageTitle = $pageTitle ?? 'Supervisor Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | Investhood IT</title>
<script>(function(){try{document.documentElement.setAttribute('data-theme',localStorage.getItem('investhood-supervisor-theme')==='dark'?'dark':'light')}catch(e){document.documentElement.setAttribute('data-theme','light')}})();</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('css/supervisor.css') ?>?v=2">
</head>
<body class="supervisor-body">
<div class="supervisor-app">
<?php require __DIR__ . '/sidebar.php'; ?>
<main class="supervisor-main">
<?php require __DIR__ . '/navbar.php'; ?>
<div class="supervisor-content">
<?= $flashes ?? '' ?>
