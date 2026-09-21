<?php
/**
 * One-time script to add placements management buttons to admin dashboard.
 * Run this once, then delete it.
 */

$file = __DIR__ . '/dashboard.php';
$content = file_get_contents($file);

if ($content === false) {
    die("Cannot read $file\n");
}

// 1. Update the "Active Placements" exec card
$search = 'data-count="248">0</span>
               <span class="admin-exec-card__label">Active Placements</span>
               <div class="admin-exec-card__footer">
                 <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> 18 new this month REPLACED</span>
                 <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>';

$replace = 'data-count="<?= (int)$placementActive ?>">0</span>
               <span class="admin-exec-card__label">Active Placements</span>
               <div class="admin-exec-card__footer">
                 <span class="admin-exec-card__period"><i class="fas fa-users"></i> <?= (int)$placementTotal ?> total placements</span>
                 <a href="<?= url(\'admin/placements.php\') ?>" class="admin-exec-card__link">Manage <i class="fas fa-arrow-right"></i></a>';

if (strpos($content, $search) !== false) {
    $content = str_replace($search, $replace, $content);
    echo "Updated Active Placements exec card.\n";
} else {
    echo "WARNING: Could not find exec card pattern.\n";
}

// 2. Update the quick actions "Manage Placements" link
$search2 = '<a href="#admin-placements" class="quick-action__btn"><i class="fas fa-handshake"></i> Manage Placements</a>';
$replace2 = '<a href="<?= url(\'admin/placements.php\') ?>" class="quick-action__btn"><i class="fas fa-handshake"></i> Manage Placements</a>';

if (strpos($content, $search2) !== false) {
    $content = str_replace($search2, $replace2, $content);
    echo "Updated quick actions Manage Placements link.\n";
} else {
    echo "WARNING: Could not find quick actions link pattern.\n";
}

file_put_contents($file, $content);
echo "Done.\n";
