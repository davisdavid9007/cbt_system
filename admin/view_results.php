<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Handle filters
$class_filter = $_GET['class'] ?? '';
$exam_filter = $_GET['exam_id'] ?? '';
$subject_filter = $_GET['subject_id'] ?? '';

// Build query with filters
$query = "SELECT r.*, s.full_name, s.admission_number, s.class, e.exam_name, sub.subject_name 
          FROM results r 
          JOIN students s ON r.student_id = s.id 
          JOIN exams e ON r.exam_id = e.id 
          JOIN subjects sub ON e.subject_id = sub.id 
          WHERE 1=1";

$params = [];

if ($class_filter) {
    $query .= " AND s.class = ?";
    $params[] = $class_filter;
}

if ($exam_filter) {
    $query .= " AND r.exam_id = ?";
    $params[] = $exam_filter;
}

if ($subject_filter) {
    $query .= " AND e.subject_id = ?";
    $params[] = $subject_filter;
}

$query .= " ORDER BY r.submitted_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Get all classes, exams, and subjects for filters
$classes = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL ORDER BY class")->fetchAll();
$exams = $pdo->query("SELECT id, exam_name FROM exams ORDER BY exam_name")->fetchAll();
$subjects = $pdo->query("SELECT id, subject_name FROM subjects ORDER BY subject_name")->fetchAll();

// Calculate statistics
$total_results = count($results);
$average_percentage = 0;
$pass_rate = 0;

if ($total_results > 0) {
    $total_percentage = 0;
    $passed = 0;
    
    foreach ($results as $result) {
        $total_percentage += $result['percentage'];
        if ($result['percentage'] >= 50) $passed++;
    }
    
    $average_percentage = round($total_percentage / $total_results, 1);
    $pass_rate = round(($passed / $total_results) * 100, 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Results - Admin Portal</title>
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
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .nav-links {
            display: flex;
            gap: 1rem;
        }
        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        .nav-links a:hover {
            background: #4a90e2;
            color: white;
        }
        .logout-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
        }
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        .results-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .results-card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 2rem;
        }
        .filters {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }
        .filter-group select {
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
        .apply-filters {
            background: #4a90e2;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            align-self: end;
        }
        .apply-filters:hover {
            background: #357abd;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .results-table th,
        .results-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .results-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .results-table tr:hover {
            background: #f8f9fa;
        }
        .grade {
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            text-align: center;
        }
        .grade-A { background: #d4edda; color: #155724; }
        .grade-B { background: #d1ecf1; color: #0c5460; }
        .grade-C { background: #fff3cd; color: #856404; }
        .grade-D { background: #f8d7da; color: #721c24; }
        .grade-F { background: #f5c6cb; color: #721c24; }
        .export-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .export-btn:hover {
            background: #218838;
        }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #4a90e2;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #666;
            font-style: italic;
        }
        .admin-info {
            background: #e7f3ff;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border-left: 4px solid #4a90e2;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #4a90e2;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #4a90e2;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        .school-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .school-logo img {
            max-width: 50px;
            height: auto;
        }
        .school-logo span {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }
        .action-btns {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .btn {
            background: linear-gradient(45deg, #4a90e2, #357abd);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: transform 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-success {
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        .btn-warning {
            background: linear-gradient(45deg, #ffc107, #e0a800);
        }
        .clear-filters {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .clear-filters:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="school-logo">       
            <img src="../assets/logo.png" alt="School Logo">
            <span>Admin Portal - View Results</span>
        </div>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="questions.php">Questions</a>
            <a href="manage_exams.php">Exams</a>
            <a href="view_results.php">Results</a>
            <a href="manage_staff.php">Staff</a>
            <a href="students.php">Students</a>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="results-card">
            <h2>📊 System Results Overview</h2>

            <!-- Admin Information -->
            <div class="admin-info">
                <strong>Administrator Access:</strong> Full system-wide access to all results, classes, subjects, and exams.
            </div>

            <!-- Action Buttons -->
            <div class="action-btns">
                <button class="btn btn-success" onclick="exportToExcel()">
                    📥 Export to Excel
                </button>
                <a href="results_analysis.php" class="btn btn-warning">
                    📈 Advanced Analysis
                </a>
                <a href="manage_subjects_topics.php" class="btn">
                    📚 Manage Subjects
                </a>
                <?php if ($class_filter || $exam_filter || $subject_filter): ?>
                    <a href="view_results.php" class="clear-filters">
                        🗑️ Clear Filters
                    </a>
                <?php endif; ?>
            </div>

            <!-- Statistics -->
            <?php if (!empty($results)): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_results; ?></div>
                    <div class="stat-label">Total Results</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $average_percentage; ?>%</div>
                    <div class="stat-label">Average Percentage</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pass_rate; ?>%</div>
                    <div class="stat-label">Overall Pass Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($classes); ?></div>
                    <div class="stat-label">Classes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($subjects); ?></div>
                    <div class="stat-label">Subjects</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($exams); ?></div>
                    <div class="stat-label">Exams</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label for="class">Class:</label>
                    <select id="class" name="class">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['class']); ?>" <?php echo $class_filter == $class['class'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="exam_id">Exam:</label>
                    <select id="exam_id" name="exam_id">
                        <option value="">All Exams</option>
                        <?php foreach ($exams as $exam): ?>
                            <option value="<?php echo $exam['id']; ?>" <?php echo $exam_filter == $exam['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['exam_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="subject_id">Subject:</label>
                    <select id="subject_id" name="subject_id">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="apply-filters">Apply Filters</button>
            </form>

            <!-- Results Table -->
            <?php if (empty($results)): ?>
                <div class="no-results">
                    <h3>No results found</h3>
                    <p>No exam results match your current filter criteria.</p>
                    <?php if ($class_filter || $exam_filter || $subject_filter): ?>
                        <p>Try <a href="view_results.php">clearing the filters</a> to see all results.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Admission No.</th>
                                <th>Class</th>
                                <th>Exam</th>
                                <th>Subject</th>
                                <th>Objective Score</th>
                                <th>Theory Score</th>
                                <th>Subjective Score</th>
                                <th>Total Score</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Date Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): 
                                $grade_class = '';
                                if ($result['percentage'] >= 80) $grade_class = 'grade-A';
                                elseif ($result['percentage'] >= 70) $grade_class = 'grade-B';
                                elseif ($result['percentage'] >= 60) $grade_class = 'grade-C';
                                elseif ($result['percentage'] >= 50) $grade_class = 'grade-D';
                                else $grade_class = 'grade-F';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($result['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($result['admission_number']); ?></td>
                                    <td><?php echo htmlspecialchars($result['class']); ?></td>
                                    <td><?php echo htmlspecialchars($result['exam_name']); ?></td>
                                    <td><?php echo htmlspecialchars($result['subject_name']); ?></td>
                                    <td><?php echo $result['objective_score']; ?></td>
                                    <td><?php echo $result['theory_score']; ?></td>
                                    <td><?php echo $result['subjective_score'] ?? 'N/A'; ?></td>
                                    <td><strong><?php echo $result['total_score']; ?></strong></td>
                                    <td><strong><?php echo $result['percentage']; ?>%</strong></td>
                                    <td><span class="grade <?php echo $grade_class; ?>"><?php echo $result['grade']; ?></span></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($result['submitted_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm" 
                                                onclick="viewResultDetails(<?php echo $result['id']; ?>)"
                                                style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">
                                            👁️ View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <a href="index.php" class="back-link">← Back to Dashboard</a>
        </div>
    </div>

    <script>
        function exportToExcel() {
            // Get current filters
            const classFilter = document.getElementById('class').value;
            const examFilter = document.getElementById('exam_id').value;
            const subjectFilter = document.getElementById('subject_id').value;
            
            // Redirect to export script with filters
            let url = `export_results.php?export=excel&admin=true`;
            if (classFilter) url += `&class=${classFilter}`;
            if (examFilter) url += `&exam_id=${examFilter}`;
            if (subjectFilter) url += `&subject_id=${subjectFilter}`;
            
            window.location.href = url;
        }

        function viewResultDetails(resultId) {
            window.open(`result_details.php?id=${resultId}`, '_blank');
        }

        // Auto-apply filters when selections change (optional)
        document.addEventListener('DOMContentLoaded', function() {
            const filters = ['class', 'exam_id', 'subject_id'];
            let autoSubmitTimer;
            
            filters.forEach(filterId => {
                const element = document.getElementById(filterId);
                if (element) {
                    element.addEventListener('change', function() {
                        clearTimeout(autoSubmitTimer);
                        autoSubmitTimer = setTimeout(() => {
                            document.querySelector('form').submit();
                        }, 500);
                    });
                }
            });
        });
    </script>
</body>
</html>