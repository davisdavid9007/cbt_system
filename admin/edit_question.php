<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$question_id = $_GET['id'] ?? 0;

// Fetch question details
$stmt = $pdo->prepare("
    SELECT oq.*, s.subject_name, t.topic_name 
    FROM objective_questions oq 
    JOIN subjects s ON oq.subject_id = s.id 
    JOIN topics t ON oq.topic_id = t.id 
    WHERE oq.id = ?
");
$stmt->execute([$question_id]);
$question = $stmt->fetch();

if (!$question) {
    header("Location: manage_subjects_topics.php");
    exit();
}

// Handle form submission for updating question
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data with proper validation
    $question_text = $_POST['question_text'] ?? '';
    $option_a = $_POST['option_a'] ?? '';
    $option_b = $_POST['option_b'] ?? '';
    $option_c = $_POST['option_c'] ?? '';
    $option_d = $_POST['option_d'] ?? '';
    $correct_answer = $_POST['correct_answer'] ?? '';
    $difficulty_level = $_POST['difficulty_level'] ?? 'medium';
    $marks = $_POST['marks'] ?? 1;
    $class = $_POST['class'] ?? '';

    // Validate required fields
    if (empty($question_text) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d) || empty($correct_answer)) {
        $error = "All fields except class are required!";
    } else {
        try {
            // Update the question in database - removed updated_at column
            $stmt = $pdo->prepare("
                UPDATE objective_questions 
                SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, 
                    correct_answer = ?, difficulty_level = ?, marks = ?, class = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $question_text, $option_a, $option_b, $option_c, $option_d, 
                $correct_answer, $difficulty_level, $marks, $class, $question_id
            ]);
            
            $success = "Question updated successfully!";
            
        } catch (Exception $e) {
            $error = "Error updating question: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Question - Mighty School For Valours</title>
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
            padding: 20px;
        }
        .container {
            max-width: 800px;
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
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .logo h1 {
            color: #4a90e2;
            font-size: 1.8rem;
        }
        .content {
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        input, textarea, select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #4a90e2;
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        .btn {
            background: linear-gradient(45deg, #4a90e2, #357abd);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.3s ease;
            margin-right: 1rem;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-success {
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        .btn-secondary {
            background: linear-gradient(45deg, #6c757d, #5a6268);
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
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .options-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        .option-group {
            border: 2px solid #e9ecef;
            padding: 1rem;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .option-group label {
            font-weight: bold;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="../assets/logo.png" alt="School Logo" style="max-width: 15%; max-height: 15%;">
                <span>Edit Question</span>
            </div>
        </div>

        <div class="content">
            <h1>Edit Question</h1>
            
            <?php if (isset($success)): ?>
                <div class="message success"><?= $success ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="message error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="question_text">Question Text:</label>
                    <textarea id="question_text" name="question_text" required placeholder="Enter the question text"><?= htmlspecialchars($question['question_text']) ?></textarea>
                </div>

                <div class="options-grid">
                    <div class="option-group">
                        <label for="option_a">Option A:</label>
                        <input type="text" id="option_a" name="option_a" required value="<?= htmlspecialchars($question['option_a']) ?>">
                    </div>
                    <div class="option-group">
                        <label for="option_b">Option B:</label>
                        <input type="text" id="option_b" name="option_b" required value="<?= htmlspecialchars($question['option_b']) ?>">
                    </div>
                    <div class="option-group">
                        <label for="option_c">Option C:</label>
                        <input type="text" id="option_c" name="option_c" required value="<?= htmlspecialchars($question['option_c']) ?>">
                    </div>
                    <div class="option-group">
                        <label for="option_d">Option D:</label>
                        <input type="text" id="option_d" name="option_d" required value="<?= htmlspecialchars($question['option_d']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="correct_answer">Correct Answer:</label>
                        <select id="correct_answer" name="correct_answer" required>
                            <option value="A" <?= $question['correct_answer'] == 'A' ? 'selected' : '' ?>>A</option>
                            <option value="B" <?= $question['correct_answer'] == 'B' ? 'selected' : '' ?>>B</option>
                            <option value="C" <?= $question['correct_answer'] == 'C' ? 'selected' : '' ?>>C</option>
                            <option value="D" <?= $question['correct_answer'] == 'D' ? 'selected' : '' ?>>D</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="difficulty_level">Difficulty Level:</label>
                        <select id="difficulty_level" name="difficulty_level" required>
                            <option value="easy" <?= $question['difficulty_level'] == 'easy' ? 'selected' : '' ?>>Easy</option>
                            <option value="medium" <?= $question['difficulty_level'] == 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="hard" <?= $question['difficulty_level'] == 'hard' ? 'selected' : '' ?>>Hard</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="marks">Marks:</label>
                        <input type="number" id="marks" name="marks" value="<?= $question['marks'] ?? 1 ?>" min="1" max="10">
                    </div>

                    <div class="form-group">
                        <label for="class">Class (Optional):</label>
                        <input type="text" id="class" name="class" value="<?= htmlspecialchars($question['class'] ?? '') ?>" placeholder="e.g., JSS1, SS2">
                    </div>
                </div>

                <div class="form-group">
                    <p><strong>Subject:</strong> <?= htmlspecialchars($question['subject_name']) ?></p>
                    <p><strong>Topic:</strong> <?= htmlspecialchars($question['topic_name']) ?></p>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-success">Update Question</button>
                    <a href="manage_subjects_topics.php" class="btn btn-secondary">Back to Management</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>