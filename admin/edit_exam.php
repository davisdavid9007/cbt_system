<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// Get exam ID from URL
$exam_id = $_GET['id'] ?? null;

if (!$exam_id) {
    header("Location: exam_dashboard.php");
    exit();
}

// Fetch exam details
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    header("Location: exam_dashboard.php");
    exit();
}

// Decode topics from JSON
$selected_topics = json_decode($exam['topics'], true) ?? [];

// Handle exam update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_exam'])) {
    $exam_name = $_POST['exam_name'];
    $class = $_POST['class'];
    $subject_id = $_POST['subject_id'];
    $topics = $_POST['topics'] ?? [];
    $objective_count = $_POST['objective_count'];
    $theory_count = $_POST['theory_count'];
    $duration_minutes = $_POST['duration_minutes'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Convert topics array to JSON
    $topics_json = json_encode($topics);

    try {
        $stmt = $pdo->prepare("UPDATE exams SET exam_name = ?, class = ?, subject_id = ?, topics = ?, objective_count = ?, theory_count = ?, duration_minutes = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$exam_name, $class, $subject_id, $topics_json, $objective_count, $theory_count, $duration_minutes, $is_active, $exam_id]);

        $message = "Exam updated successfully!";
        $message_type = "success";

        // Refresh exam data
        $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
        $stmt->execute([$exam_id]);
        $exam = $stmt->fetch();
        $selected_topics = json_decode($exam['topics'], true) ?? [];
    } catch (Exception $e) {
        $message = "Error updating exam: " . $e->getMessage();
        $message_type = "error";
    }
}

$subjects = $pdo->query("SELECT * FROM subjects")->fetchAll();
$topics = $pdo->query("
    SELECT t.*, s.subject_name 
    FROM topics t 
    LEFT JOIN subjects s ON t.subject_id = s.id 
    ORDER BY s.subject_name, t.topic_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Exam - Impact Digital Academy</title>
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
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .exam-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .exam-card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #f39c12;
        }

        .topics-container {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 1rem;
            max-height: 200px;
            overflow-y: auto;
        }

        .topic-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .topic-checkbox:hover {
            background: #f8f9fa;
        }

        .topic-checkbox input {
            width: auto;
            margin-right: 0.5rem;
        }

        .topic-checkbox label small {
            margin-left: 0.5rem;
            opacity: 0.7;
        }

        .update-btn {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.3s ease;
            margin-bottom: 1rem;
        }

        .update-btn:hover {
            transform: translateY(-2px);
        }

        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 600;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #2ecc71;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .no-topics-message {
            text-align: center;
            color: #666;
            padding: 2rem;
            font-style: italic;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px dashed #ddd;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-group input {
            width: auto;
        }

        .exam-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #f39c12;
        }

        .exam-info p {
            margin-bottom: 0.5rem;
            color: #555;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="logo">
            <h1>Impact Digital <span>Academy</span></h1>
            <span>Admin Panel - Edit Exam</span>
        </div>
    </div>

    <div class="container">
        <div class="exam-card">
            <h2>✏️ Edit Exam</h2>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="exam-info">
                <p><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($exam['created_at'])); ?></p>
                <p><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($exam['updated_at'])); ?></p>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="exam_name">Exam Name:</label>
                    <input type="text" id="exam_name" name="exam_name" value="<?php echo htmlspecialchars($exam['exam_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="class">Class:</label>
                    <select id="class" name="class" required>
                        <option value="">Select Class</option>
                        <option value="JSS 1" <?php echo $exam['class'] == 'JSS 1' ? 'selected' : ''; ?>>JSS 1</option>
                        <option value="JSS 2" <?php echo $exam['class'] == 'JSS 2' ? 'selected' : ''; ?>>JSS 2</option>
                        <option value="JSS 3" <?php echo $exam['class'] == 'JSS 3' ? 'selected' : ''; ?>>JSS 3</option>
                        <option value="SS 1" <?php echo $exam['class'] == 'SS 1' ? 'selected' : ''; ?>>SS 1</option>
                        <option value="SS 2" <?php echo $exam['class'] == 'SS 2' ? 'selected' : ''; ?>>SS 2</option>
                        <option value="SS 3" <?php echo $exam['class'] == 'SS 3' ? 'selected' : ''; ?>>SS 3</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="subject_id">Subject:</label>
                    <select id="subject_id" name="subject_id" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo $exam['subject_id'] == $subject['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Topics:</label>
                    <div class="topics-container" id="topics-container">
                        <?php if (empty($topics)): ?>
                            <div class="no-topics-message">No topics available. Please add topics first.</div>
                        <?php else: ?>
                            <?php foreach ($topics as $topic): ?>
                                <div class="topic-checkbox" data-subject-id="<?php echo $topic['subject_id']; ?>">
                                    <input type="checkbox" id="topic_<?php echo $topic['id']; ?>" name="topics[]" value="<?php echo $topic['id']; ?>"
                                        <?php echo in_array($topic['id'], $selected_topics) ? 'checked' : ''; ?>>
                                    <label for="topic_<?php echo $topic['id']; ?>">
                                        <?php echo htmlspecialchars($topic['topic_name']); ?>
                                        <small style="color: #666; font-size: 0.8em;">(<?php echo htmlspecialchars($topic['subject_name']); ?>)</small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="objective_count">Number of Objective Questions:</label>
                    <input type="number" id="objective_count" name="objective_count" min="1" max="100" value="<?php echo $exam['objective_count']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="theory_count">Number of Theory Questions:</label>
                    <input type="number" id="theory_count" name="theory_count" min="0" max="20" value="<?php echo $exam['theory_count']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="duration_minutes">Duration (minutes):</label>
                    <input type="number" id="duration_minutes" name="duration_minutes" min="5" max="180" value="<?php echo $exam['duration_minutes']; ?>" required>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo $exam['is_active'] ? 'checked' : ''; ?>>
                        <label for="is_active">Activate this exam (students can see and take it)</label>
                    </div>
                </div>

                <button type="submit" name="update_exam" class="update-btn">✅ Update Exam</button>
            </form>

            <a href="exam_dashboard.php" class="back-link">← Back to Exam Dashboard</a>
        </div>
    </div>

    <script>
        // Function to filter topics based on selected subject
        function filterTopics() {
            const subjectSelect = document.getElementById('subject_id');
            const topicsContainer = document.getElementById('topics-container');
            const selectedSubjectId = subjectSelect.value;

            // Hide all topic checkboxes first
            const allTopicCheckboxes = topicsContainer.querySelectorAll('.topic-checkbox');
            allTopicCheckboxes.forEach(topic => {
                topic.style.display = 'none';
            });

            // Show only topics for the selected subject
            if (selectedSubjectId) {
                const subjectTopics = topicsContainer.querySelectorAll(`.topic-checkbox[data-subject-id="${selectedSubjectId}"]`);
                subjectTopics.forEach(topic => {
                    topic.style.display = 'flex';
                });

                // If no topics found for this subject, show message
                if (subjectTopics.length === 0) {
                    const noTopicsMsg = document.getElementById('no-topics-message');
                    if (!noTopicsMsg) {
                        const message = document.createElement('div');
                        message.id = 'no-topics-message';
                        message.className = 'no-topics-message';
                        message.innerHTML = 'No topics available for this subject. Please add topics first.';
                        topicsContainer.appendChild(message);
                    }
                } else {
                    const noTopicsMsg = document.getElementById('no-topics-message');
                    if (noTopicsMsg) {
                        noTopicsMsg.remove();
                    }
                }
            } else {
                // If no subject selected, show all topics
                allTopicCheckboxes.forEach(topic => {
                    topic.style.display = 'flex';
                });
            }
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const subjectSelect = document.getElementById('subject_id');
            if (subjectSelect) {
                subjectSelect.addEventListener('change', filterTopics);
                // Trigger filter on page load
                filterTopics();
            }
        });
    </script>
</body>

</html>