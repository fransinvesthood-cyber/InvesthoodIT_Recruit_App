<?php
// Helper to manage schedules without a database table
function get_schedules() {
    $file = __DIR__ . '/_schedules_data.json';
    if (!file_exists($file)) return [];
    $data = file_get_contents($file);
    return json_decode($data, true) ?: [];
}

function save_schedule($attempt_id, $assessor_id, $date, $time, $duration, $notes) {
    $schedules = get_schedules();
    
    // Save or overwrite the schedule for this specific attempt
    $schedules[$attempt_id] = [
        'assessor_id' => $assessor_id,
        'scheduled_at' => $date . ' ' . $time . ':00',
        'duration_minutes' => $duration,
        'notes' => $notes,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $file = __DIR__ . '/_schedules_data.json';
    file_put_contents($file, json_encode($schedules, JSON_PRETTY_PRINT));
}
?>