<?php
$pageTitle=$pageTitle??'Commercial Manager Portal';
$currentPage=$currentPage??'';
$localFlash=cm_local_flash();
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> | Investhood IT</title>
<script>(function(){try{const t=localStorage.getItem('investhood-unified-theme');document.documentElement.setAttribute('data-theme',t==='dark'?'dark':'light')}catch(e){}})();</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>"><link rel="stylesheet" href="<?= url('css/commercial_manager.css') ?>">
<link rel="stylesheet" href="<?= url('css/unified_portal.css') ?>?v=1">

  <link rel="stylesheet" href="<?=url('css/styles_original_pm.css')?>">
  <link rel="stylesheet" href="<?=url('css/original_pm_sidebar_compat.css')?>">
<link rel="stylesheet" href="<?= url('css/dashboard_card_modals.css') ?>?v=20260919">
</head><body class="ux-body"><div class="ux-app">
<?php require __DIR__.'/sidebar.php'; ?>
<main class="ux-main"><?php require __DIR__.'/navbar.php'; ?>
<div class="ux-content"><?= $flashes??'' ?><?= $localFlash ?>