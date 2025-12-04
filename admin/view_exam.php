<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get exam ID from URL
$exam_id = $_GET['id'] ?? null;

if (!$exam_id) {
    header("Location: exam_dashboard.php");
    exit();
}

// Fetch exam details with subject name
$stmt = $pdo->prepare("
    SELECT e.*, s.subject_name 
    FROM exams e 
    LEFT JOIN subjects s ON e.subject_id = s.id 
    WHERE e.id = ?
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    header("Location: exam_dashboard.php");
    exit();
}

// Decode topics from JSON and get topic names
$selected_topic_ids = json_decode($exam['topics'], true) ?? [];
$topic_names = [];

if (!empty($selected_topic_ids)) {
    $placeholders = str_repeat('?,', count($selected_topic_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT topic_name FROM topics WHERE id IN ($placeholders)");
    $stmt->execute($selected_topic_ids);
    $topic_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get exam statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_students FROM students WHERE class = ? AND status = 'active'");
$stmt->execute([$exam['class']]);
$total_students = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as completed_results FROM results WHERE exam_id = ?");
$stmt->execute([$exam_id]);
$completed_results = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT AVG(score) as average_score FROM results WHERE exam_id = ?");
$stmt->execute([$exam_id]);
$average_score = $stmt->fetchColumn();
$average_score = $average_score ? round($average_score, 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Exam - Impact Digital Academy</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #2ecc71 0%, #3498db 100%);
            min-height: 100vh;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo h1 {
            color: #4a90e2;
            font-size: 1.8rem;
        }

        .logo span {
            color: #ff6b6b;
        }

        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .exam-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-bottom: 2rem;
        }

        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .exam-title {
            color: #2c3e50;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }

        .exam-subtitle {
            color: #7f8c8d;
            font-size: 1.2rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-active {
            background: #27ae60;
            color: white;
        }

        .status-inactive {
            background: #95a5a6;
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #3498db;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .detail-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
        }

        .detail-section h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }

        .detail-item {
            margin-bottom: 0.8rem;
            display: flex;
            justify-content: space-between;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
        }

        .detail-value {
            color: #2c3e50;
        }

        .topics-list {
            list-style: none;
            padding: 0;
        }

        .topics-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
        }

        .topics-list li:before {
            content: "📚";
            margin-right: 0.5rem;
        }

        .topics-list li:last-child {
            border-bottom: none;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 2px solid #f8f9fa;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .btn-edit {
            background: #f39c12;
            color: white;
        }

        .btn-edit:hover {
            background: #e67e22;
        }

        .btn-back {
            background: #95a5a6;
            color: white;
        }

        .btn-back:hover {
            background: #7f8c8d;
        }

        .btn-results {
            background: #3498db;
            color: white;
        }

        .btn-results:hover {
            background: #2980b9;
        }

        .no-data {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
            padding: 1rem;
        }

        .progress-container {
            background: #e9ecef;
            border-radius: 10px;
            height: 10px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .progress-bar {
            background: #27ae60;
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .completion-rate {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-top: 0.3rem;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="logo">
            <h1>Impact Digital <span>Academy</span></h1>
            <span>Admin Panel - View Exam</span>
        </div>
    </div>

    <div class="container">
        <div class="exam-card">
            <div class="exam-header">
                <div>
                    <h1 class="exam-title"><?php echo htmlspecialchars($exam['exam_name']); ?></h1>
                    <div class="exam-subtitle">
                        <?php echo htmlspecialchars($exam['subject_name']); ?> • <?php echo htmlspecialchars($exam['class']); ?>
                    </div>
                </div>
                <span class="status-badge <?php echo $exam['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                    <?php echo $exam['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $exam['objective_count'] + $exam['theory_count']; ?></div>
                    <div class="stat-label">Total Questions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $exam['duration_minutes']; ?></div>
                    <div class="stat-label">Minutes Duration</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $completed_results; ?></div>
                    <div class="stat-label">Completed Tests</div>
                    <?php if ($total_students > 0): ?>
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?php echo ($completed_results / $total_students) * 100; ?>%"></div>
                        </div>
                        <div class="completion-rate">
                            <?php echo round(($completed_results / $total_students) * 100, 1); ?>% of class
                        </div>
                    <?php endif; ?>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $average_score; ?>%</div>
                    <div class="stat-label">Average Score</div>
                </div>
            </div>

            <!-- Exam Details -->
            <div class="details-grid">
                <div class="detail-section">
                    <h3>📋 Exam Information</h3>
                    <div class="detail-item">
                        <span class="detail-label">Exam Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($exam['exam_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Subject:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($exam['subject_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Class:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($exam['class']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Duration:</span>
                        <span class="detail-value"><?php echo $exam['duration_minutes']; ?> minutes</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="status-badge <?php echo $exam['is_active'] ? 'status-active' : 'status-inactive'; ?>" style="font-size: 0.8rem;">
                                <?php echo $exam['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </span>
                    </div>
                </div>

                <div class="detail-section">
                    <h3>📊 Question Breakdown</h3>
                    <div class="detail-item">
                        <span class="detail-label">Objective Questions:</span>
                        <span class="detail-value"><?php echo $exam['objective_count']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Theory Questions:</span>
                        <span class="detail-value"><?php echo $exam['theory_count']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Total Questions:</span>
                        <span class="detail-value"><strong><?php echo $exam['objective_count'] + $exam['theory_count']; ?></strong></span>
                    </div>
                </div>
            </div>

            <!-- Topics -->
            <div class="detail-section">
                <h3>📚 Covered Topics</h3>
                <?php if (!empty($topic_names)): ?>
                    <ul class="topics-list">
                        <?php foreach ($topic_names as $topic_name): ?>
                            <li><?php echo htmlspecialchars($topic_name); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="no-data">No topics selected for this exam</div>
                <?php endif; ?>
            </div>

            <!-- Timestamps -->
            <div class="detail-section">
                <h3>⏰ Timestamps</h3>
                <div class="detail-item">
                    <span class="detail-label">Created:</span>
                    <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($exam['created_at'])); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Last Updated:</span>
                    <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($exam['updated_at'])); ?></span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-edit">
                    ✏️ Edit Exam
                </a>
                <a href="exam_results.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-results">
                    📊 View Results
                </a>
                <a href="exam_dashboard.php" class="btn btn-back">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>

</html>