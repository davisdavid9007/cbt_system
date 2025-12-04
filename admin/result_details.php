<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$result_id = $_GET['id'] ?? 0;

// Get result details
$stmt = $pdo->prepare("
    SELECT r.*, s.full_name, s.admission_number, s.class, s.email, 
           e.exam_name, e.total_marks, e.objective_count, e.theory_count, e.subjective_count,
           sub.subject_name,
           es.start_time, es.end_time
    FROM results r 
    JOIN students s ON r.student_id = s.id 
    JOIN exams e ON r.exam_id = e.id 
    JOIN subjects sub ON e.subject_id = sub.id
    LEFT JOIN exam_sessions es ON r.session_id = es.id
    WHERE r.id = ?
");
$stmt->execute([$result_id]);
$result = $stmt->fetch();

if (!$result) {
    header("Location: view_results.php");
    exit();
}

// Get detailed breakdown if available
$detailed_scores = [];
if (!empty($result['detailed_scores'])) {
    $detailed_scores = json_decode($result['detailed_scores'], true);
}

// Get exam session questions and answers
$session_answers = [];
if ($result['session_id']) {
    $stmt = $pdo->prepare("
        SELECT esq.*, oq.question_text, oq.correct_answer, oq.marks as question_marks
        FROM exam_session_questions esq
        LEFT JOIN objective_questions oq ON esq.question_id = oq.id
        WHERE esq.session_id = ?
    ");
    $stmt->execute([$result['session_id']]);
    $session_questions = $stmt->fetchAll();
    
    // Get student answers
    $stmt = $pdo->prepare("SELECT * FROM exam_answers WHERE session_id = ?");
    $stmt->execute([$result['session_id']]);
    $answers = $stmt->fetchAll();
    
    foreach ($answers as $answer) {
        $session_answers[$answer['question_id']] = $answer['answer'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result Details - Admin Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .header {
            background: #34495e;
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .header h1 {
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }
        .header .subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        .content {
            padding: 2rem;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #4a90e2;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 2rem;
            transition: background 0.3s ease;
        }
        .back-btn:hover {
            background: #357abd;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .info-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid #4a90e2;
        }
        .info-card h3 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        .info-label {
            font-weight: 600;
            color: #555;
        }
        .info-value {
            color: #333;
        }
        .score-breakdown {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .score-breakdown h3 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }
        .score-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .score-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .score-value {
            font-size: 2rem;
            font-weight: bold;
            color: #4a90e2;
            margin-bottom: 0.5rem;
        }
        .score-label {
            color: #666;
            font-size: 0.9rem;
        }
        .grade-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .grade-A { background: #d4edda; color: #155724; }
        .grade-B { background: #d1ecf1; color: #0c5460; }
        .grade-C { background: #fff3cd; color: #856404; }
        .grade-D { background: #f8d7da; color: #721c24; }
        .grade-F { background: #f5c6cb; color: #721c24; }
        .questions-section {
            margin-top: 2rem;
        }
        .questions-section h3 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }
        .question-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        .question-text {
            flex: 1;
            font-weight: 500;
            line-height: 1.5;
        }
        .question-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            color: #666;
        }
        .options {
            margin: 1rem 0;
            padding-left: 1rem;
        }
        .option {
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            border-radius: 4px;
        }
        .option.correct {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .option.incorrect {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .option.selected {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .answer-status {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 6px;
            font-weight: 600;
        }
        .answer-status.correct {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .answer-status.incorrect {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .print-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 1rem;
        }
        .print-btn:hover {
            background: #218838;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .container {
                box-shadow: none;
                border-radius: 0;
            }
            .back-btn, .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Result Details</h1>
            <div class="subtitle">Comprehensive breakdown of student performance</div>
        </div>
        
        <div class="content">
            <a href="view_results.php" class="back-btn">← Back to Results</a>
            <button class="print-btn" onclick="window.print()">🖨️ Print Result</button>

            <!-- Student and Exam Information -->
            <div class="info-grid">
                <div class="info-card">
                    <h3>Student Information</h3>
                    <div class="info-item">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($result['full_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Admission No:</span>
                        <span class="info-value"><?php echo htmlspecialchars($result['admission_number']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Class:</span>
                        <span class="info-value"><?php echo htmlspecialchars($result['class']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($result['email']); ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <h3>Exam Information</h3>
                    <div class="info-item">
                        <span class="info-label">Exam:</span>
                        <span class="info-value"><?php echo htmlspecialchars($result['exam_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Subject:</span>
                        <span class="info-value"><?php echo htmlspecialchars($result['subject_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Marks:</span>
                        <span class="info-value"><?php echo $result['total_marks']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date Submitted:</span>
                        <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($result['submitted_at'])); ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <h3>Performance Summary</h3>
                    <div class="info-item">
                        <span class="info-label">Total Score:</span>
                        <span class="info-value"><strong><?php echo $result['total_score']; ?>/<?php echo $result['total_marks']; ?></strong></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Percentage:</span>
                        <span class="info-value"><strong><?php echo $result['percentage']; ?>%</strong></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Grade:</span>
                        <span class="info-value">
                            <span class="grade-badge grade-<?php echo $result['grade']; ?>">
                                <?php echo $result['grade']; ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <?php echo $result['percentage'] >= 50 ? '✅ Pass' : '❌ Fail'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Score Breakdown -->
            <div class="score-breakdown">
                <h3>📊 Score Breakdown</h3>
                <div class="score-grid">
                    <div class="score-item">
                        <div class="score-value"><?php echo $result['objective_score']; ?></div>
                        <div class="score-label">Objective Questions</div>
                    </div>
                    <div class="score-item">
                        <div class="score-value"><?php echo $result['theory_score']; ?></div>
                        <div class="score-label">Theory Questions</div>
                    </div>
                    <div class="score-item">
                        <div class="score-value"><?php echo $result['subjective_score'] ?? 0; ?></div>
                        <div class="score-label">Subjective Questions</div>
                    </div>
                    <div class="score-item">
                        <div class="score-value"><?php echo $result['total_score']; ?></div>
                        <div class="score-label">Total Score</div>
                    </div>
                </div>
            </div>

            <!-- Objective Questions Breakdown -->
            <?php if (!empty($session_questions)): ?>
            <div class="questions-section">
                <h3>🎯 Objective Questions Analysis</h3>
                <?php foreach ($session_questions as $index => $session_question): 
                    if (!$session_question['question_text']) continue;
                    
                    $student_answer = $session_answers[$session_question['question_id']] ?? '';
                    $is_correct = $student_answer === $session_question['correct_answer'];
                    $options = [
                        'A' => $session_question['option_a'] ?? '',
                        'B' => $session_question['option_b'] ?? '',
                        'C' => $session_question['option_c'] ?? '',
                        'D' => $session_question['option_d'] ?? ''
                    ];
                ?>
                    <div class="question-item">
                        <div class="question-header">
                            <div class="question-text">
                                <strong>Q<?php echo $index + 1; ?>:</strong> <?php echo htmlspecialchars($session_question['question_text']); ?>
                            </div>
                            <div class="question-meta">
                                <span>Marks: <?php echo $session_question['question_marks'] ?? 1; ?></span>
                                <span>Status: <?php echo $is_correct ? '✅ Correct' : '❌ Incorrect'; ?></span>
                            </div>
                        </div>
                        
                        <div class="options">
                            <?php foreach ($options as $letter => $option): 
                                if (empty($option)) continue;
                                $classes = ['option'];
                                if ($letter === $session_question['correct_answer']) $classes[] = 'correct';
                                if ($letter === $student_answer && !$is_correct) $classes[] = 'incorrect';
                                if ($letter === $student_answer) $classes[] = 'selected';
                            ?>
                                <div class="<?php echo implode(' ', $classes); ?>">
                                    <strong><?php echo $letter; ?>:</strong> <?php echo htmlspecialchars($option); ?>
                                    <?php if ($letter === $student_answer): ?>
                                        <em> (Your answer)</em>
                                    <?php endif; ?>
                                    <?php if ($letter === $session_question['correct_answer']): ?>
                                        <em> (Correct answer)</em>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="answer-status <?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
                            <?php if ($is_correct): ?>
                                ✅ Correct! You scored <?php echo $session_question['question_marks'] ?? 1; ?> mark(s)
                            <?php else: ?>
                                ❌ Incorrect. Your answer: <?php echo $student_answer ?: 'Not attempted'; ?>, 
                                Correct answer: <?php echo $session_question['correct_answer']; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>