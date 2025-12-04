<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get filter parameters
$class_filter = $_GET['class'] ?? '';
$subject_filter = $_GET['subject_id'] ?? '';
$exam_filter = $_GET['exam_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build base query for analysis
$query = "SELECT r.*, s.class, e.exam_name, e.total_marks, sub.subject_name 
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

if ($subject_filter) {
    $query .= " AND e.subject_id = ?";
    $params[] = $subject_filter;
}

if ($exam_filter) {
    $query .= " AND r.exam_id = ?";
    $params[] = $exam_filter;
}

if ($date_from) {
    $query .= " AND DATE(r.submitted_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(r.submitted_at) <= ?";
    $params[] = $date_to;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Get all classes, subjects, and exams for filters
$classes = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL ORDER BY class")->fetchAll();
$subjects = $pdo->query("SELECT id, subject_name FROM subjects ORDER BY subject_name")->fetchAll();
$exams = $pdo->query("SELECT id, exam_name FROM exams ORDER BY exam_name")->fetchAll();

// Calculate analytics
$total_students = count(array_unique(array_column($results, 'student_id')));
$total_exams = count(array_unique(array_column($results, 'exam_id')));
$average_percentage = 0;
$pass_rate = 0;
$grade_distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];

if (count($results) > 0) {
    $total_percentage = 0;
    $passed = 0;
    
    foreach ($results as $result) {
        $total_percentage += $result['percentage'];
        if ($result['percentage'] >= 50) $passed++;
        
        // Count grade distribution
        $grade = $result['grade'];
        if (isset($grade_distribution[$grade])) {
            $grade_distribution[$grade]++;
        }
    }
    
    $average_percentage = round($total_percentage / count($results), 1);
    $pass_rate = round(($passed / count($results)) * 100, 1);
}

// Calculate subject-wise performance
$subject_performance = [];
$class_performance = [];

foreach ($results as $result) {
    $subject = $result['subject_name'];
    $class = $result['class'];
    
    if (!isset($subject_performance[$subject])) {
        $subject_performance[$subject] = [
            'total_score' => 0,
            'count' => 0,
            'average' => 0
        ];
    }
    
    if (!isset($class_performance[$class])) {
        $class_performance[$class] = [
            'total_score' => 0,
            'count' => 0,
            'average' => 0
        ];
    }
    
    $subject_performance[$subject]['total_score'] += $result['percentage'];
    $subject_performance[$subject]['count']++;
    
    $class_performance[$class]['total_score'] += $result['percentage'];
    $class_performance[$class]['count']++;
}

// Calculate averages
foreach ($subject_performance as $subject => &$data) {
    $data['average'] = round($data['total_score'] / $data['count'], 1);
}

foreach ($class_performance as $class => &$data) {
    $data['average'] = round($data['total_score'] / $data['count'], 1);
}

// Get top performers
$top_performers = [];
$stmt = $pdo->prepare("
    SELECT s.full_name, s.class, r.percentage, r.grade, e.exam_name, sub.subject_name
    FROM results r
    JOIN students s ON r.student_id = s.id
    JOIN exams e ON r.exam_id = e.id
    JOIN subjects sub ON e.subject_id = sub.id
    ORDER BY r.percentage DESC
    LIMIT 10
");
$stmt->execute();
$top_performers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results Analysis - Admin Portal</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .analysis-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .analysis-card h2 {
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
        .filter-group select, .filter-group input {
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
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
            font-size: 2.5rem;
            font-weight: bold;
            color: #4a90e2;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            color: #666;
            font-size: 1rem;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .chart-container h3 {
            color: #333;
            margin-bottom: 1rem;
            text-align: center;
        }
        .tables-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .table-container {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table-container h3 {
            color: #333;
            margin-bottom: 1rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .grade-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .grade-A { background: #d4edda; color: #155724; }
        .grade-B { background: #d1ecf1; color: #0c5460; }
        .grade-C { background: #fff3cd; color: #856404; }
        .grade-D { background: #f8d7da; color: #721c24; }
        .grade-F { background: #f5c6cb; color: #721c24; }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #4a90e2;
            text-decoration: none;
            font-weight: 600;
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
        @media (max-width: 768px) {
            .charts-grid, .tables-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="school-logo">       
            <img src="../assets/logo.png" alt="School Logo">
            <span>Admin Portal - Results Analysis</span>
        </div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="questions.php">Questions</a>
            <a href="exams.php">Exams</a>
            <a href="view_results.php">Results</a>
            <a href="staff.php">Staff</a>
            <a href="students.php">Students</a>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="analysis-card">
            <h2>📈 Advanced Results Analysis</h2>

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
                    <label for="date_from">Date From:</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>

                <div class="filter-group">
                    <label for="date_to">Date To:</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>

                <button type="submit" class="apply-filters">Apply Filters</button>
            </form>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($results); ?></div>
                    <div class="stat-label">Total Results</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Unique Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_exams; ?></div>
                    <div class="stat-label">Exams Taken</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $average_percentage; ?>%</div>
                    <div class="stat-label">Average Score</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pass_rate; ?>%</div>
                    <div class="stat-label">Pass Rate</div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <div class="chart-container">
                    <h3>Grade Distribution</h3>
                    <canvas id="gradeChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Subject Performance</h3>
                    <canvas id="subjectChart"></canvas>
                </div>
            </div>

            <!-- Performance Tables -->
            <div class="tables-grid">
                <!-- Top Performers -->
                <div class="table-container">
                    <h3>🏆 Top 10 Performers</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Exam</th>
                                <th>Score</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_performers as $performer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($performer['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($performer['class']); ?></td>
                                    <td><?php echo htmlspecialchars($performer['exam_name']); ?></td>
                                    <td><strong><?php echo $performer['percentage']; ?>%</strong></td>
                                    <td>
                                        <span class="grade-badge grade-<?php echo $performer['grade']; ?>">
                                            <?php echo $performer['grade']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Subject Performance -->
                <div class="table-container">
                    <h3>📚 Subject-wise Performance</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Average Score</th>
                                <th>Results Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subject_performance as $subject => $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subject); ?></td>
                                    <td><strong><?php echo $data['average']; ?>%</strong></td>
                                    <td><?php echo $data['count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Class Performance -->
            <div class="table-container">
                <h3>🏫 Class-wise Performance</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Average Score</th>
                            <th>Results Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($class_performance as $class => $data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($class); ?></td>
                                <td><strong><?php echo $data['average']; ?>%</strong></td>
                                <td><?php echo $data['count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <a href="view_results.php" class="back-link">← Back to Results</a>
        </div>
    </div>

    <script>
        // Grade Distribution Chart
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        const gradeChart = new Chart(gradeCtx, {
            type: 'pie',
            data: {
                labels: ['A (80-100%)', 'B (70-79%)', 'C (60-69%)', 'D (50-59%)', 'F (Below 50%)'],
                datasets: [{
                    data: [
                        <?php echo $grade_distribution['A']; ?>,
                        <?php echo $grade_distribution['B']; ?>,
                        <?php echo $grade_distribution['C']; ?>,
                        <?php echo $grade_distribution['D']; ?>,
                        <?php echo $grade_distribution['F']; ?>
                    ],
                    backgroundColor: [
                        '#d4edda',
                        '#d1ecf1',
                        '#fff3cd',
                        '#f8d7da',
                        '#f5c6cb'
                    ],
                    borderColor: [
                        '#c3e6cb',
                        '#bee5eb',
                        '#ffeaa7',
                        '#f5c6cb',
                        '#f1b0b7'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Subject Performance Chart
        const subjectCtx = document.getElementById('subjectChart').getContext('2d');
        const subjectChart = new Chart(subjectCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($subject_performance)); ?>,
                datasets: [{
                    label: 'Average Score (%)',
                    data: <?php echo json_encode(array_column($subject_performance, 'average')); ?>,
                    backgroundColor: '#4a90e2',
                    borderColor: '#357abd',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Percentage Score'
                        }
                    }
                }
            }
        });

        // Auto-apply filters when selections change
        document.addEventListener('DOMContentLoaded', function() {
            const filters = ['class', 'subject_id', 'exam_id', 'date_from', 'date_to'];
            let autoSubmitTimer;
            
            filters.forEach(filterId => {
                const element = document.getElementById(filterId);
                if (element) {
                    element.addEventListener('change', function() {
                        clearTimeout(autoSubmitTimer);
                        autoSubmitTimer = setTimeout(() => {
                            document.querySelector('form').submit();
                        }, 1000);
                    });
                }
            });
        });
    </script>
</body>
</html>