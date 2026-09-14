<?php
/**
 * ================================================
 * INVESTHOOD IT - Landing Page (Test Version)
 * ================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Test: Try a simple query
try {
    $programmes = Database::fetchAll("SELECT id, name FROM programmes LIMIT 5");
    $opportunitiesCount = Database::fetchOne("SELECT COUNT(*) as cnt FROM opportunities");
} catch (Exception $e) {
    error_log('Database error: ' . $e->getMessage());
    $programmes = [];
    $opportunitiesCount = ['cnt' => 0];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Landing Page Test</title>
</head>
<body style="font-family: Arial; padding: 20px;">
  <h1>Investhood IT - Landing Page (Test)</h1>
  
  <h2>Database Connectivity Test</h2>
  <p>Programmes fetched: <?= count($programmes) ?></p>
  <p>Opportunities count: <?= $opportunitiesCount['cnt'] ?? 0 ?></p>
  
  <hr>
  
  <h2>Programmes</h2>
  <?php if (!empty($programmes)): ?>
    <ul>
      <?php foreach ($programmes as $p): ?>
        <li><?= htmlspecialchars($p['name']) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p>No programmes found.</p>
  <?php endif; ?>
  
  <hr>
  <p><a href="<?= url('index.php') ?>">Back to Full Landing Page</a></p>
</body>
</html>
