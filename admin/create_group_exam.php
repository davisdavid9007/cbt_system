<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_name = trim($_POST['exam_name']);
    $group_name = trim($_POST['group_name']);
    $total_duration = $_POST['total_duration_minutes'];
    $subjects = $_POST['subjects'];
    $question_counts = $_POST['question_counts'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    try {
        $pdo->beginTransaction();

        // Create subject group
        $stmt = $pdo->prepare("INSERT INTO subject_groups (group_name, total_duration_minutes, is_active) VALUES (?, ?, ?)");
        $stmt->execute([$group_name, $total_duration, $is_active]);
        $group_id = $pdo->lastInsertId();

        // Add subjects to group
        $stmt = $pdo->prepare("INSERT INTO subject_group_members (group_id, subject_id, question_count, display_order) VALUES (?, ?, ?, ?)");
        $order = 1;
        foreach ($subjects as $index => $subject_id) {
            $stmt->execute([$group_id, $subject_id, $question_counts[$index], $order]);
            $order++;
        }

        // Create the group exam
        $stmt = $pdo->prepare("INSERT INTO exams (exam_name, exam_type, group_id, duration_minutes, is_active) VALUES (?, 'group', ?, ?, ?)");
        $stmt->execute([$exam_name, $group_id, $total_duration, $is_active]);

        $pdo->commit();
        $message = "Group exam created successfully!";
        $message_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get all subjects
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Group Exam - Mighty School For Valours</title>
    <style>
        /* Add your existing styles here */
        .subject-row {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }

        .add-subject-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 1rem;
        }

        .remove-subject {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <h1>Mighty School <span>For Valours</span></h1>
                <span>Create Group Exam (UTME Style)</span>
            </div>
        </div>

        <div class="content">
            <h1>Create Group Exam</h1>

            <?php if ($message): ?>
                <div class="message <?= $message_type ?>"><?= $message ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-section">
                    <h2>Exam Information</h2>
                    <div class="form-group">
                        <label for="exam_name">Exam Name:</label>
                        <input type="text" id="exam_name" name="exam_name" required
                            placeholder="e.g., UTME Practice Test 2024">
                    </div>

                    <div class="form-group">
                        <label for="group_name">Group Name:</label>
                        <input type="text" id="group_name" name="group_name" required
                            placeholder="e.g., Science Combination">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="total_duration_minutes">Total Duration (minutes):</label>
                            <input type="number" id="total_duration_minutes" name="total_duration_minutes"
                                required value="180" min="1">
                        </div>

                        <div class="form-group">
                            <label for="is_active">Status:</label>
                            <select id="is_active" name="is_active">
                                <option value="1" selected>Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Subjects Configuration</h2>
                    <div id="subjects-container">
                        <div class="subject-row">
                            <select name="subjects[]" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= $subject['id'] ?>">
                                        <?= htmlspecialchars($subject['subject_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="question_counts[]" required
                                placeholder="Questions count" min="1" value="20">
                            <button type="button" class="remove-subject" onclick="removeSubject(this)">×</button>
                        </div>
                    </div>

                    <button type="button" class="add-subject-btn" onclick="addSubject()">
                        + Add Another Subject
                    </button>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-success">Create Group Exam</button>
                    <a href="manage_exams.php" class="btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function addSubject() {
            const container = document.getElementById('subjects-container');
            const newRow = document.createElement('div');
            newRow.className = 'subject-row';
            newRow.innerHTML = `
                <select name="subjects[]" required>
                    <option value="">Select Subject</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= $subject['id'] ?>">
                            <?= htmlspecialchars($subject['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="question_counts[]" required 
                       placeholder="Questions count" min="1" value="20">
                <button type="button" class="remove-subject" onclick="removeSubject(this)">×</button>
            `;
            container.appendChild(newRow);
        }

        function removeSubject(button) {
            const container = document.getElementById('subjects-container');
            if (container.children.length > 1) {
                button.closest('.subject-row').remove();
            }
        }
    </script>
</body>

</html>