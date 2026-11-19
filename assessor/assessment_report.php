<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('assessor');
$user = current_user();
$pageTitle = 'Evaluation Report';

$conn = Database::getConnection();
$attemptId = (int)($_GET['id'] ?? 0);

// Load attempt
$stmt = $conn->prepare("SELECT aa.id, aa.attempt_number, aa.status, aa.submitted_at, aa.assessed_at, aa.assessor_result,
                               u.first_name, u.last_name, u.email, 
                               ao.code AS outcome_code, ao.title AS outcome_title,
                               ast.first_name AS assessor_first, ast.last_name AS assessor_last
                        FROM assessment_attempts aa 
                        JOIN users u ON u.id = aa.participant_id 
                        JOIN assessment_outcomes ao ON ao.id = aa.outcome_id
                        LEFT JOIN users ast ON ast.id = aa.assessor_id
                        WHERE aa.id = ?");
$stmt->bind_param('i', $attemptId);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attempt) { http_response_code(404); exit('Assessment not found.'); }

// HARDCODED CRITERIA (Same as score page)
$criteriaList = [
    ['id' => 1, 'title' => 'Knowledge & Understanding', 'max_score' => 20, 'weight' => 1],
    ['id' => 2, 'title' => 'Practical Application', 'max_score' => 30, 'weight' => 1],
    ['id' => 3, 'title' => 'Communication', 'max_score' => 10, 'weight' => 1],
    ['id' => 4, 'title' => 'Professional Conduct', 'max_score' => 10, 'weight' => 1]
];

// Pull scores from Session
$savedScores = $_SESSION['mock_scores'][$attemptId] ?? [];

$totalScore = 0;
$totalMaxScore = 0;
$scores = [];

foreach ($criteriaList as $c) {
    $cid = $c['id'];
    $scoreData = $savedScores[$cid] ?? ['score' => 0, 'feedback' => ''];
    $score = (float)$scoreData['score'];
    $feedback = $scoreData['feedback'];
    
    $totalScore += $score;
    $totalMaxScore += (float)$c['max_score'];
    
    $scores[] = [
        'title' => $c['title'],
        'score' => $score,
        'max_score' => $c['max_score'],
        'feedback' => $feedback
    ];
}

$percentage = $totalMaxScore > 0 ? round(($totalScore / $totalMaxScore) * 100, 1) : 0;
$candidateName = trim($attempt['first_name'] . ' ' . $attempt['last_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Report - <?=e($candidateName)?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: #f4f4f5; margin: 0; padding: 40px; color: #333; }
        .report-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 50px; box-shadow: 0 4px 6px rgba(0,0,0,.05); border-radius: 8px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #eaeaea; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 24px; color: #1a56db; }
        .header p { margin: 5px 0 0; font-size: 14px; color: #666; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; font-size: 14px; }
        .meta-grid strong { display: block; color: #555; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .score-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .score-table th, .score-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eaeaea; }
        .score-table th { background: #f9fafb; font-size: 12px; text-transform: uppercase; color: #666; }
        .score-table td { font-size: 14px; }
        .score-table .feedback { font-size: 13px; color: #555; font-style: italic; margin-top: 4px; display: block; }
        .result-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 40px; }
        .result-box h2 { margin: 0 0 10px; font-size: 32px; color: #166534; }
        .result-box p { margin: 0; font-size: 16px; color: #15803d; }
        .signatures { display: flex; justify-content: space-between; margin-top: 60px; padding-top: 20px; border-top: 1px solid #eaeaea; }
        .sig-line { width: 45%; border-bottom: 1px solid #333; height: 30px; margin-bottom: 8px; }
        .sig-text { font-size: 12px; color: #666; }
        .print-btn { display: block; width: 200px; margin: 0 auto 30px; padding: 12px; text-align: center; background: #1a56db; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; cursor: pointer; border: none; font-size: 16px; }
        @media print {
            body { background: #fff; padding: 0; }
            .report-container { box-shadow: none; padding: 0; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>

    <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print / Save as PDF</button>

    <div class="report-container">
        <div class="header">
            <div>
                <h1>Assessment Evaluation Report</h1>
                <p>Investhood IT Recruitment</p>
            </div>
            <div style="text-align: right;">
                <p><strong>Date:</strong> <?=date('d M Y')?></p>
                <p><strong>Ref:</strong> #<?=str_pad($attemptId, 6, '0', STR_PAD_LEFT)?></p>
            </div>
        </div>

        <div class="meta-grid">
            <div>
                <strong>Candidate Name</strong>
                <?=e($candidateName)?><br>
                <?=e($attempt['email'])?>
            </div>
            <div>
                <strong>Assessment Details</strong>
                <?=e($attempt['outcome_code'])?> - <?=e($attempt['outcome_title'])?><br>
                Attempt <?=(int)$attempt['attempt_number']?>
            </div>
            <div>
                <strong>Assessor</strong>
                <?=e(trim($attempt['assessor_first'] . ' ' . $attempt['assessor_last']))?>
            </div>
            <div>
                <strong>Assessment Status</strong>
                <?=e(ucwords(str_replace('_', ' ', $attempt['status'])))?>
            </div>
        </div>

        <table class="score-table">
            <thead>
                <tr>
                    <th>Criterion</th>
                    <th style="width: 80px; text-align: center;">Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scores as $s): ?>
                <tr>
                    <td>
                        <strong><?=e($s['title'])?></strong>
                        <?php if ($s['feedback']): ?>
                            <span class="feedback">"<?=e($s['feedback'])?>"</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center; font-weight: bold;">
                        <?=number_format((float)$s['score'], 1)?> / <?=number_format((float)$s['max_score'], 1)?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="result-box">
            <h2><?=number_format($percentage, 1)?>%</h2>
            <p>Overall Score: <?=number_format($totalScore, 1)?> out of <?=number_format($totalMaxScore, 1)?> points</p>
        </div>

        <div class="signatures">
            <div>
                <div class="sig-line"></div>
                <div class="sig-text">Assessor Signature</div>
            </div>
            <div>
                <div class="sig-line"></div>
                <div class="sig-text">Date</div>
            </div>
        </div>
    </div>

</body>
</html>