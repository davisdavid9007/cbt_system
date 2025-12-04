<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';
$is_edit_mode = false;
$exam_data = [];

// Check if we're in edit mode
if (isset($_GET['edit'])) {
    $exam_id = $_GET['edit'];
    $is_edit_mode = true;
    
    // Fetch existing exam data
    $stmt = $pdo->prepare("
        SELECT e.*, s.subject_name 
        FROM exams e 
        JOIN subjects s ON e.subject_id = s.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$exam_id]);
    $exam_data = $stmt->fetch();
    
    if (!$exam_data) {
        $message = "Exam not found!";
        $message_type = "error";
        $is_edit_mode = false;
    }
}

// Handle form submission for both create and edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_name = trim($_POST['exam_name']);
    $subject_id = $_POST['subject_id'];
    $class = trim($_POST['class']);
    $duration_minutes = $_POST['duration_minutes'];
    $objective_count = $_POST['objective_count'];
    $theory_count = $_POST['theory_count'];
    $topics = isset($_POST['topics']) ? $_POST['topics'] : [];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    try {
        if ($is_edit_mode && isset($_POST['exam_id'])) {
            // Update existing exam
            $exam_id = $_POST['exam_id'];
            $topics_json = json_encode($topics);
            
            $stmt = $pdo->prepare("
                UPDATE exams 
                SET exam_name = ?, subject_id = ?, class = ?, duration_minutes = ?, 
                    objective_count = ?, theory_count = ?, topics = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $exam_name, $subject_id, $class, $duration_minutes,
                $objective_count, $theory_count, $topics_json, $is_active, $exam_id
            ]);
            
            $message = "Exam updated successfully!";
            $message_type = "success";
            
        } else {
            // Create new exam
            $topics_json = json_encode($topics);
            
            $stmt = $pdo->prepare("
                INSERT INTO exams (exam_name, subject_id, class, duration_minutes, 
                                 objective_count, theory_count, topics, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $exam_name, $subject_id, $class, $duration_minutes,
                $objective_count, $theory_count, $topics_json, $is_active
            ]);
            
            $message = "Exam created successfully!";
            $message_type = "success";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get all subjects and topics for the form
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();

// If in edit mode and we have subject_id, get topics for that subject
$topics = [];
if ($is_edit_mode && isset($exam_data['subject_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM topics WHERE subject_id = ? ORDER BY topic_name");
    $stmt->execute([$exam_data['subject_id']]);
    $topics = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit_mode ? 'Edit Exam' : 'Create New Exam' ?> - Mighty School For Valours</title>
    <style>
        /* Add your existing styles here */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 2rem;
            border-bottom: 1px solid #ddd;
        }
        .content {
            padding: 2rem;
        }
        .form-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        input, select, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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
            display: inline-block;
        }
        .btn-success {
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        .topics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .topic-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <h1>Mighty School <span>For Valours</span></h1>
                <span><?= $is_edit_mode ? 'Edit Exam' : 'Create New Exam' ?></span>
            </div>
        </div>

        <div class="content">
            <h1><?= $is_edit_mode ? 'Edit Exam: ' . htmlspecialchars($exam_data['exam_name'] ?? '') : 'Create New Exam' ?></h1>
            
            <?php if ($message): ?>
                <div class="message <?= $message_type ?>"><?= $message ?></div>
            <?php endif; ?>

            <form method="POST">
                <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="exam_id" value="<?= $exam_data['id'] ?>">
                <?php endif; ?>

                <div class="form-section">
                    <h2>Exam Information</h2>
                    <div class="form-group">
                        <label for="exam_name">Exam Name:</label>
                        <input type="text" id="exam_name" name="exam_name" required 
                               value="<?= htmlspecialchars($exam_data['exam_name'] ?? '') ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subject_id">Subject:</label>
                            <select id="subject_id" name="subject_id" required 
                                    onchange="loadTopics(this.value)">
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= $subject['id'] ?>" 
                                        <?= ($exam_data['subject_id'] ?? '') == $subject['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($subject['subject_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="class">Class:</label>
                            <input type="text" id="class" name="class" 
                                   value="<?= htmlspecialchars($exam_data['class'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="duration_minutes">Duration (minutes):</label>
                            <input type="number" id="duration_minutes" name="duration_minutes" required 
                                   value="<?= $exam_data['duration_minutes'] ?? 60 ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="is_active">Status:</label>
                            <select id="is_active" name="is_active">
                                <option value="1" <?= ($exam_data['is_active'] ?? 1) ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= !($exam_data['is_active'] ?? 1) ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Question Configuration</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="objective_count">Objective Questions Count:</label>
                            <input type="number" id="objective_count" name="objective_count" required 
                                   value="<?= $exam_data['objective_count'] ?? 10 ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="theory_count">Theory Questions Count:</label>
                            <input type="number" id="theory_count" name="theory_count" required 
                                   value="<?= $exam_data['theory_count'] ?? 0 ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Topics (for objective questions):</label>
                        <div id="topics-container">
                            <?php if (!empty($topics)): ?>
                                <div class="topics-grid">
                                    <?php 
                                    $selected_topics = [];
                                    if ($is_edit_mode && !empty($exam_data['topics'])) {
                                        $selected_topics = json_decode($exam_data['topics'], true);
                                    }
                                    ?>
                                    <?php foreach ($topics as $topic): ?>
                                        <div class="topic-checkbox">
                                            <input type="checkbox" name="topics[]" value="<?= $topic['id'] ?>" 
                                                   id="topic_<?= $topic['id'] ?>"
                                                   <?= in_array($topic['id'], $selected_topics ?? []) ? 'checked' : '' ?>>
                                            <label for="topic_<?= $topic['id'] ?>"><?= htmlspecialchars($topic['topic_name']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p>Please select a subject first to see available topics.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-success">
                        <?= $is_edit_mode ? 'Update Exam' : 'Create Exam' ?>
                    </button>
                    <a href="manage_exams.php" class="btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function loadTopics(subjectId) {
            if (!subjectId) {
                document.getElementById('topics-container').innerHTML = '<p>Please select a subject first to see available topics.</p>';
                return;
            }
            
            fetch(`get_topics_by_subject.php?subject_id=${subjectId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('topics-container').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('topics-container').innerHTML = '<p>Error loading topics</p>';
                });
        }
    </script>
</body>
</html>