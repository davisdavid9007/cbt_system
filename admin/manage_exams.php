<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// Handle exam status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_exam_status'])) {
        $exam_id = $_POST['exam_id'];
        $is_active = $_POST['is_active'] ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE exams SET is_active = ? WHERE id = ?");
            $stmt->execute([$is_active, $exam_id]);
            $message = "Exam status updated successfully!";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Error updating exam: " . $e->getMessage();
            $message_type = "error";
        }
    }

    if (isset($_POST['delete_exam'])) {
        $exam_id = $_POST['exam_id'];

        try {
            // Check if there are any results for this exam
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM results WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            $result_count = $stmt->fetchColumn();

            if ($result_count > 0) {
                $message = "Cannot delete exam - there are existing results for this exam.";
                $message_type = "error";
            } else {
                $stmt = $pdo->prepare("DELETE FROM exams WHERE id = ?");
                $stmt->execute([$exam_id]);
                $message = "Exam deleted successfully!";
                $message_type = "success";
            }
        } catch (Exception $e) {
            $message = "Error deleting exam: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get all exams with subject information
$exams = $pdo->query("
    SELECT e.*, s.subject_name 
    FROM exams e 
    JOIN subjects s ON e.subject_id = s.id 
    ORDER BY e.is_active DESC, e.created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams - Tip Top Schools</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1e3c72 0%, #8a2be2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 1.5rem 2rem;
            border-bottom: 3px solid #8a2be2;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e3c72;
        }

        .content {
            padding: 2.5rem;
        }

        .page-title {
            color: #1e3c72;
            margin-bottom: 1.5rem;
            font-size: 2.2rem;
            font-weight: 700;
            text-align: center;
        }

        .message {
            padding: 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            text-align: center;
            border: 2px solid transparent;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            margin-top: 2rem;
            border: 1px solid #e9ecef;
        }

        .table-header {
            background: linear-gradient(45deg, #1e3c72, #8a2be2);
            color: white;
            padding: 1.5rem;
        }

        .table-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 1.2rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: #f8f9fa;
            font-weight: 700;
            color: #1e3c72;
            font-size: 1.1rem;
            border-bottom: 2px solid #8a2be2;
        }

        tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
            transition: transform 0.2s ease;
        }

        .btn {
            background: linear-gradient(45deg, #1e3c72, #8a2be2);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 1rem;
            border: 2px solid transparent;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(138, 43, 226, 0.4);
        }

        .btn-success {
            background: linear-gradient(45deg, #28a745, #20c997);
        }

        .btn-success:hover {
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
        }

        .btn-danger {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
        }

        .btn-danger:hover {
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.4);
        }

        .btn-warning {
            background: linear-gradient(45deg, #ffc107, #e0a800);
        }

        .btn-warning:hover {
            box-shadow: 0 8px 20px rgba(255, 193, 7, 0.4);
        }

        .status-active {
            color: #28a745;
            font-weight: bold;
            background: #d4edda;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            border: 2px solid #28a745;
        }

        .status-inactive {
            color: #e74c3c;
            font-weight: bold;
            background: #f8d7da;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            border: 2px solid #e74c3c;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #28a745;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .action-btns {
            display: flex;
            gap: 0.8rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .no-data {
            text-align: center;
            color: #666;
            padding: 3rem;
            font-style: italic;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 1rem;
            border: 2px dashed #ddd;
        }

        .no-data a {
            color: #8a2be2;
            text-decoration: none;
            font-weight: 600;
        }

        .no-data a:hover {
            text-decoration: underline;
        }

        .exam-info {
            font-weight: 600;
            color: #1e3c72;
        }

        .question-count {
            background: #e7f3ff;
            padding: 0.3rem 0.6rem;
            border-radius: 8px;
            border: 1px solid #1e3c72;
            font-size: 0.9rem;
        }

        .action-form {
            display: inline;
            margin: 0;
        }

        @media (max-width: 768px) {
            .content {
                padding: 1.5rem;
            }
            
            .action-btns {
                flex-direction: column;
                align-items: flex-start;
            }
            
            table {
                font-size: 0.9rem;
            }
            
            th, td {
                padding: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="school-logo">
                <img src="../assets/logo.png" alt="School Logo">
                <span>Tip Top Schools - Manage Exams</span>
            </div>
        </div>

        <div class="content">
            <h1 class="page-title">📝 Manage Exams</h1>

            <?php if ($message): ?>
                <div class="message <?= $message_type ?>"><?= $message ?></div>
            <?php endif; ?>

            <div class="table-container">
                <div class="table-header">
                    <h3>🎯 All Exams</h3>
                </div>

                <?php if (empty($exams)): ?>
                    <div class="no-data">
                        <p>📝 No exams found.</p>
                        <p><a href="create_exam.php" class="btn btn-success">➕ Create your first exam</a></p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Exam Name</th>
                                <th>Subject</th>
                                <th>Class</th>
                                <th>Questions</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td class="exam-info"><?= htmlspecialchars($exam['exam_name']) ?></td>
                                    <td><?= htmlspecialchars($exam['subject_name']) ?></td>
                                    <td><span class="question-count"><?= htmlspecialchars($exam['class']) ?></span></td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                            <span class="question-count">📋 Obj: <?= $exam['objective_count'] ?></span>
                                            <span class="question-count">📝 Thy: <?= $exam['theory_count'] ?></span>
                                        </div>
                                    </td>
                                    <td>⏱️ <?= $exam['duration_minutes'] ?> minutes</td>
                                    <td>
                                        <?php if ($exam['is_active']): ?>
                                            <span class="status-active">● Active</span>
                                        <?php else: ?>
                                            <span class="status-inactive">● Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-btns">
                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                            <label class="switch" title="Toggle Exam Status">
                                                <input type="checkbox" name="is_active" value="1"
                                                    <?= $exam['is_active'] ? 'checked' : '' ?>
                                                    onchange="this.form.submit()">
                                                <span class="slider"></span>
                                            </label>
                                            <input type="hidden" name="update_exam_status" value="1">
                                        </form>

                                        <a href="create_exam.php?edit=<?= $exam['id'] ?>" class="btn btn-warning" title="Edit Exam">✏️ Edit</a>

                                        <form method="POST" class="action-form" onsubmit="return confirm('Are you sure you want to delete this exam? This action cannot be undone.')">
                                            <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                            <input type="hidden" name="delete_exam" value="1">
                                            <button type="submit" class="btn btn-danger" title="Delete Exam">🗑️ Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div style="margin-top: 2.5rem; text-align: center; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="create_exam.php" class="btn btn-success">➕ Create New Exam</a>
                <a href="index.php" class="btn">🏠 Back to Dashboard</a>
            </div>
        </div>
    </div>

    <script>
        // Add confirmation for status changes
        document.querySelectorAll('input[type="checkbox"][name="is_active"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const status = this.checked ? 'activate' : 'deactivate';
                const examName = this.closest('tr').querySelector('.exam-info').textContent;
                
                if (!confirm(`Are you sure you want to ${status} the exam "${examName}"?`)) {
                    this.checked = !this.checked;
                    return false;
                }
            });
        });
    </script>
</body>


</html>
