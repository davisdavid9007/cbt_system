<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_subject'])) {
        $subject_name = trim($_POST['subject_name']);
        $description = trim($_POST['description']);
        
        if (!empty($subject_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, description) VALUES (?, ?)");
                $stmt->execute([$subject_name, $description]);
                $message = "Subject added successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Error adding subject: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
    
    if (isset($_POST['add_topic'])) {
        $topic_name = trim($_POST['topic_name']);
        $subject_id = $_POST['subject_id'];
        $topic_description = trim($_POST['topic_description']);
        
        if (!empty($topic_name) && !empty($subject_id)) {
            try {
                // Get the next available ID
                $stmt = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM topics");
                $next_id = $stmt->fetch()['next_id'];
                
                $stmt = $pdo->prepare("INSERT INTO topics (id, topic_name, subject_id, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$next_id, $topic_name, $subject_id, $topic_description]);
                $message = "Topic added successfully!";
                $message_type = "success";
                
                // Clear form
                $_POST['topic_name'] = '';
                $_POST['topic_description'] = '';
            } catch (Exception $e) {
                $message = "Error adding topic: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
    
    // Handle topic deletion
    if (isset($_POST['delete_topic'])) {
        $topic_id = $_POST['topic_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Delete questions first
            $pdo->prepare("DELETE FROM objective_questions WHERE topic_id = ?")->execute([$topic_id]);
            $pdo->prepare("DELETE FROM subjective_questions WHERE topic_id = ?")->execute([$topic_id]);
            
            if ($pdo->query("SHOW TABLES LIKE 'theory_questions'")->rowCount() > 0) {
                $pdo->prepare("DELETE FROM theory_questions WHERE topic_id = ?")->execute([$topic_id]);
            }
            
            // Delete the topic
            $pdo->prepare("DELETE FROM topics WHERE id = ?")->execute([$topic_id]);
            
            $pdo->commit();
            $message = "Topic and all associated questions deleted successfully!";
            $message_type = "success";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error deleting topic: " . $e->getMessage();
            $message_type = "error";
        }
    }
    
    // Handle question deletion
    if (isset($_POST['delete_question'])) {
        $question_id = $_POST['question_id'];
        $question_type = $_POST['question_type'];
        
        try {
            $table_name = $question_type . '_questions';
            $stmt = $pdo->prepare("DELETE FROM $table_name WHERE id = ?");
            $stmt->execute([$question_id]);
            
            $message = "Question deleted successfully!";
            $message_type = "success";
            
        } catch (Exception $e) {
            $message = "Error deleting question: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get all subjects and topics
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();

// Get selected subject for filtering (default to first subject if none selected)
$selected_subject_id = $_GET['subject_id'] ?? ($subjects[0]['id'] ?? null);
$topics = [];

if ($selected_subject_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, s.subject_name 
        FROM topics t 
        JOIN subjects s ON t.subject_id = s.id 
        WHERE t.subject_id = ? 
        ORDER BY t.topic_name
    ");
    $stmt->execute([$selected_subject_id]);
    $topics = $stmt->fetchAll();
} else {
    // If no subject selected, get all topics
    $topics = $pdo->query("
        SELECT t.*, s.subject_name 
        FROM topics t 
        JOIN subjects s ON t.subject_id = s.id 
        ORDER BY s.subject_name, t.topic_name
    ")->fetchAll();
}

// Get questions for modal if topic_id is provided
$current_topic_questions = [];
$current_topic_name = '';
if (isset($_GET['view_questions']) && !empty($_GET['topic_id'])) {
    $topic_id = $_GET['topic_id'];
    
    // Get topic info
    $stmt = $pdo->prepare("SELECT topic_name FROM topics WHERE id = ?");
    $stmt->execute([$topic_id]);
    $topic = $stmt->fetch();
    
    if ($topic) {
        $current_topic_name = $topic['topic_name'];
        
        // Get objective questions
        $stmt = $pdo->prepare("SELECT * FROM objective_questions WHERE topic_id = ? ORDER BY created_at DESC");
        $stmt->execute([$topic_id]);
        $current_topic_questions['objective'] = $stmt->fetchAll();
        
        // Get subjective questions
        $stmt = $pdo->prepare("SELECT * FROM subjective_questions WHERE topic_id = ? ORDER BY created_at DESC");
        $stmt->execute([$topic_id]);
        $current_topic_questions['subjective'] = $stmt->fetchAll();
        
        // Get theory questions
        $current_topic_questions['theory'] = [];
        if ($pdo->query("SHOW TABLES LIKE 'theory_questions'")->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT * FROM theory_questions WHERE topic_id = ? ORDER BY created_at DESC");
            $stmt->execute([$topic_id]);
            $current_topic_questions['theory'] = $stmt->fetchAll();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects & Topics - Admin Portal</title>
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
            padding: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 1rem 2rem;
            }
        }
        .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }
        @media (min-width: 768px) {
            .nav-links {
                justify-content: flex-end;
                gap: 1rem;
            }
        }
        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 600;
            padding: 0.5rem 0.8rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-size: 0.8rem;
            white-space: nowrap;
        }
        @media (min-width: 480px) {
            .nav-links a {
                font-size: 0.9rem;
                padding: 0.5rem 1rem;
            }
        }
        .nav-links a:hover {
            background: #4a90e2;
            color: white;
        }
        .logout-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
            font-size: 0.8rem;
        }
        @media (min-width: 480px) {
            .logout-btn {
                font-size: 0.9rem;
                padding: 0.5rem 1.5rem;
            }
        }
        .logout-btn:hover {
            background: #ff5252;
        }
        .container {
            max-width: 1200px;
            margin: 1rem auto;
            padding: 0 1rem;
        }
        @media (min-width: 768px) {
            .container {
                margin: 2rem auto;
                padding: 0 2rem;
            }
        }
        .content-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        @media (min-width: 768px) {
            .content-card {
                padding: 2rem;
                border-radius: 20px;
            }
        }
        .content-card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 1.5rem;
        }
        @media (min-width: 768px) {
            .content-card h2 {
                font-size: 2rem;
            }
        }
        .form-section {
            background: #f8f9fa;
            padding: 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        @media (min-width: 768px) {
            .form-section {
                padding: 1.5rem;
                margin-bottom: 2rem;
            }
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
                gap: 1.5rem;
            }
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }
        @media (min-width: 768px) {
            label {
                font-size: 1rem;
            }
        }
        select, input, textarea {
            width: 100%;
            padding: 0.7rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }
        @media (min-width: 768px) {
            select, input, textarea {
                padding: 0.75rem;
                font-size: 1rem;
            }
        }
        select:focus, input:focus, textarea:focus {
            outline: none;
            border-color: #4a90e2;
        }
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        .btn {
            background: linear-gradient(45deg, #4a90e2, #357abd);
            color: white;
            border: none;
            padding: 0.7rem 1.2rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }
        @media (min-width: 768px) {
            .btn {
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
            }
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-success {
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        .tables-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        @media (min-width: 768px) {
            .tables-grid {
                grid-template-columns: 1fr 1fr;
                gap: 2rem;
                margin-top: 2rem;
            }
        }
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table-header {
            background: #34495e;
            color: white;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .table-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }
        .table-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        @media (min-width: 768px) {
            .table-header h3 {
                font-size: 1.3rem;
            }
        }
        .subject-filter {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        @media (min-width: 480px) {
            .subject-filter {
                flex-direction: row;
                align-items: center;
                gap: 1rem;
            }
        }
        .subject-filter select {
            width: 100%;
        }
        @media (min-width: 480px) {
            .subject-filter select {
                width: auto;
                min-width: 150px;
            }
        }
        @media (min-width: 768px) {
            .subject-filter select {
                min-width: 200px;
            }
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 0.8rem;
        }
        @media (min-width: 768px) {
            th, td {
                padding: 1rem;
                font-size: 0.9rem;
            }
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .message {
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: 0.9rem;
        }
        @media (min-width: 768px) {
            .message {
                padding: 1rem;
                font-size: 1rem;
            }
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
        .no-data {
            text-align: center;
            color: #666;
            padding: 1.5rem;
            font-style: italic;
            font-size: 0.9rem;
        }
        @media (min-width: 768px) {
            .no-data {
                padding: 2rem;
                font-size: 1rem;
            }
        }
        .action-btns {
            display: flex;
            gap: 0.3rem;
            flex-wrap: wrap;
        }
        @media (min-width: 768px) {
            .action-btns {
                gap: 0.5rem;
            }
        }
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }
        @media (min-width: 768px) {
            .btn-sm {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }
        .btn-danger {
            background: linear-gradient(45deg, #dc3545, #c82333);
        }
        .school-logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            justify-content: center;
        }
        @media (min-width: 768px) {
            .school-logo {
                justify-content: flex-start;
                gap: 1rem;
            }
        }
        .school-logo img {
            max-width: 40px;
            height: auto;
        }
        @media (min-width: 768px) {
            .school-logo img {
                max-width: 50px;
            }
        }
        .school-logo span {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            text-align: center;
        }
        @media (min-width: 768px) {
            .school-logo span {
                font-size: 1.2rem;
                text-align: left;
            }
        }
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #333;
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
        @media (max-width: 767px) {
            .nav-links {
                display: none;
                flex-direction: column;
                width: 100%;
                gap: 0.5rem;
            }
            .nav-links.active {
                display: flex;
            }
            .mobile-menu-toggle {
                display: block;
            }
        }
        .table-responsive {
            overflow-x: auto;
        }
        @media (max-width: 767px) {
            table {
                min-width: 600px;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal-content {
            background: white;
            margin: 2rem auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            background: #34495e;
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s ease;
        }
        
        .close-modal:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .modal-body {
            padding: 2rem;
            max-height: calc(90vh - 120px);
            overflow-y: auto;
        }
        
        .question-section {
            margin-bottom: 2.5rem;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .question-section-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .question-section-header h4 {
            margin: 0;
            color: #333;
            font-size: 1.2rem;
        }
        
        .question-count {
            background: #4a90e2;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .question-items {
            padding: 1.5rem;
        }
        
        .question-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1.2rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .question-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.8rem;
        }
        
        .question-text {
            flex: 1;
            font-weight: 500;
            font-size: 1rem;
            line-height: 1.4;
        }
        
        .question-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            color: #666;
            align-items: center;
        }
        
        .question-type-badge {
            background: #4a90e2;
            color: white;
            padding: 0.3rem 0.7rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .question-type-badge.subjective {
            background: #28a745;
        }
        
        .question-type-badge.theory {
            background: #ff6b6b;
        }
        
        .question-options {
            margin-top: 0.8rem;
            padding-left: 1rem;
        }
        
        .question-option {
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
        }
        
        .correct-answer {
            color: #28a745;
            font-weight: 600;
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: #d4edda;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .question-answer {
            margin-top: 0.8rem;
        }
        
        .answer-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
        }
        
        .answer-content {
            background: white;
            padding: 1rem;
            border-radius: 6px;
            border-left: 4px solid #28a745;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .question-footer {
            margin-top: 1rem;
            padding-top: 0.8rem;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .question-class {
            font-size: 0.8rem;
            color: #666;
        }
        
        .question-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .summary-card {
            background: #e7f3ff;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 1.5rem;
            text-align: center;
        }
        
        .summary-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4a90e2;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.3rem;
        }
        
        .question-image {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            margin: 1rem 0;
        }
        
        .question-image img {
            display: block;
            margin: 0 auto;
            max-width: 100%;
            max-height: 300px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .question-image img:hover {
            transform: scale(1.02);
        }
        
        .file-preview-actions {
            margin-top: 0.5rem;
            text-align: center;
        }
        
        .image-preview, .pdf-preview {
            margin-top: 1rem;
        }
        
        .pdf-preview embed, .pdf-preview iframe {
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
            width: 100%;
            height: 400px;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .modal-content {
            animation: modalFadeIn 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="school-logo">       
            <img src="../assets/logo.png" alt="School Logo">
            <span>Admin Portal - Manage Subjects & Topics</span>
        </div>
        <button class="mobile-menu-toggle" id="menuToggle">☰</button>
        <div class="nav-links" id="navLinks">
            <a href="index.php">Dashboard</a>
            <a href="questions.php">Questions</a>
            <a href="exams.php">Exams</a>
            <a href="view_results.php">Results</a>
            <a href="staff.php">Staff</a>
            <a href="students.php">Students</a>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="content-card">
            <h2>📚 Manage Subjects & Topics</h2>
            
            <?php if ($message): ?>
                <div class="message <?= $message_type ?>"><?= $message ?></div>
            <?php endif; ?>

            <!-- Add Subject Form -->
            <div class="form-section">
                <h3>Add New Subject</h3>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="subject_name">Subject Name:</label>
                            <input type="text" id="subject_name" name="subject_name" required 
                                   placeholder="Enter subject name">
                        </div>
                        <div class="form-group">
                            <label for="description">Description (Optional):</label>
                            <textarea id="description" name="description" 
                                      placeholder="Enter subject description"></textarea>
                        </div>
                    </div>
                    <button type="submit" name="add_subject" class="btn btn-success">Add Subject</button>
                </form>
            </div>

            <!-- Add Topic Form -->
            <div class="form-section">
                <h3>Add New Topic</h3>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="subject_id">Subject:</label>
                            <select id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= $subject['id'] ?>" 
                                        <?= ($_POST['subject_id'] ?? '') == $subject['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($subject['subject_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="topic_name">Topic Name:</label>
                            <input type="text" id="topic_name" name="topic_name" required 
                                   value="<?= htmlspecialchars($_POST['topic_name'] ?? '') ?>"
                                   placeholder="Enter topic name">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="topic_description">Description (Optional):</label>
                        <textarea id="topic_description" name="topic_description" 
                                  placeholder="Enter topic description"><?= htmlspecialchars($_POST['topic_description'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" name="add_topic" class="btn btn-success">Add Topic</button>
                </form>
            </div>

            <!-- Current Subjects & Topics -->
            <div class="tables-grid">
                <!-- Current Subjects -->
                <div class="table-container">
                    <div class="table-header">
                        <h3>All Subjects</h3>
                    </div>
                    <div class="table-responsive">
                        <?php if (empty($subjects)): ?>
                            <div class="no-data">No subjects found. Add your first subject above.</div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Subject Name</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $subject): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($subject['subject_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($subject['description'] ?? 'No description') ?></td>
                                            <td class="action-btns">
                                                <button class="btn btn-sm" 
                                                        onclick="filterTopics(<?= $subject['id'] ?>)">
                                                    View Topics
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Current Topics with Filter -->
                <div class="table-container">
                    <div class="table-header">
                        <h3>All Topics</h3>
                        <div class="subject-filter">
                            <label for="topic_subject_filter">Filter by Subject:</label>
                            <select id="topic_subject_filter" onchange="filterTopics(this.value)">
                                <option value="">All Subjects</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= $subject['id'] ?>" 
                                            <?= $selected_subject_id == $subject['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($subject['subject_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <?php if (empty($topics)): ?>
                            <div class="no-data">
                                <?php if ($selected_subject_id): ?>
                                    No topics found for selected subject.
                                <?php else: ?>
                                    No topics found. Add your first topic above.
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Topic Name</th>
                                        <th>Subject</th>
                                        <th>Description</th>
                                        <th>Questions</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topics as $topic): 
                                        // Count questions for this topic
                                        $stmt = $pdo->prepare("
                                            SELECT COUNT(*) as question_count 
                                            FROM objective_questions 
                                            WHERE topic_id = ?
                                            UNION ALL
                                            SELECT COUNT(*) as question_count 
                                            FROM subjective_questions 
                                            WHERE topic_id = ?
                                        ");
                                        $stmt->execute([$topic['id'], $topic['id']]);
                                        $counts = $stmt->fetchAll();
                                        $total_questions = array_sum(array_column($counts, 'question_count'));
                                    ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($topic['topic_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($topic['subject_name']) ?></td>
                                            <td><?= htmlspecialchars($topic['description'] ?? 'No description') ?></td>
                                            <td>
                                                <span class="btn btn-sm" onclick="viewQuestions(<?= $topic['id'] ?>, '<?= htmlspecialchars($topic['topic_name']) ?>')">
                                                    📝 <?= $total_questions ?> Questions
                                                </span>
                                            </td>
                                            <td class="action-btns">
                                                <button class="btn btn-sm" 
                                                        onclick="editTopic(<?= $topic['id'] ?>)"
                                                        style="background: #ffc107; color: black;">
                                                    Edit
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this topic and all its questions? This cannot be undone.')">
                                                    <input type="hidden" name="topic_id" value="<?= $topic['id'] ?>">
                                                    <button type="submit" name="delete_topic" class="btn btn-sm btn-danger">
                                                        Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <a href="index.php" class="btn" style="margin-top: 2rem;">← Back to Dashboard</a>
        </div>
    </div>

    <!-- Questions Modal -->
    <div id="questionsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTopicName">Questions</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (isset($_GET['view_questions']) && !empty($current_topic_questions)): ?>
                    <!-- Objective Questions -->
                    <div class="question-section">
                        <div class="question-section-header">
                            <h4>Objective Questions</h4>
                            <span class="question-count"><?= count($current_topic_questions['objective']) ?> questions</span>
                        </div>
                        <div class="question-items">
                            <?php if (empty($current_topic_questions['objective'])): ?>
                                <div class="no-data">No objective questions found.</div>
                            <?php else: ?>
                                <?php foreach ($current_topic_questions['objective'] as $index => $question): ?>
                                    <div class="question-item">
                                        <div class="question-header">
                                            <div class="question-text">
                                                <?= ($index + 1) . '. ' . htmlspecialchars($question['question_text']) ?>
                                            </div>
                                            <div class="question-meta">
                                                <span>Marks: <?= $question['marks'] ?></span>
                                                <span>Difficulty: <?= ucfirst($question['difficulty_level']) ?></span>
                                                <span class="question-type-badge">Objective</span>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($question['question_image'])): ?>
                                        <div class="question-image">
                                            <div style="font-weight: 600; color: #333; margin-bottom: 0.5rem;">📷 Question Image:</div>
                                            <img src="../uploads/objective_questions/<?= htmlspecialchars($question['question_image']) ?>" 
                                                 alt="Question Image"
                                                 onerror="this.style.display='none'">
                                            <div class="file-preview-actions">
                                                <a href="../uploads/objective_questions/<?= htmlspecialchars($question['question_image']) ?>" 
                                                   target="_blank" 
                                                   class="btn btn-sm" 
                                                   style="background: #4a90e2; color: white; text-decoration: none;">
                                                    🔍 View Full Size
                                                </a>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="question-options">
                                            <div class="question-option"><strong>A:</strong> <?= htmlspecialchars($question['option_a']) ?></div>
                                            <div class="question-option"><strong>B:</strong> <?= htmlspecialchars($question['option_b']) ?></div>
                                            <div class="question-option"><strong>C:</strong> <?= htmlspecialchars($question['option_c']) ?></div>
                                            <div class="question-option"><strong>D:</strong> <?= htmlspecialchars($question['option_d']) ?></div>
                                            <div class="correct-answer">✅ Correct Answer: <?= $question['correct_answer'] ?></div>
                                        </div>
                                        
                                        <div class="question-footer">
                                            <div class="question-class">Class: <?= htmlspecialchars($question['class']) ?></div>
                                            <div class="question-actions">
                                                <button class="btn btn-sm" 
                                                        onclick="editObjectiveQuestion(<?= $question['id'] ?>)"
                                                        style="background: #ffc107; color: black;">
                                                    Edit
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this question?')">
                                                    <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                                    <input type="hidden" name="question_type" value="objective">
                                                    <button type="submit" name="delete_question" class="btn btn-sm btn-danger">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Subjective Questions -->
                    <div class="question-section">
                        <div class="question-section-header">
                            <h4>Subjective Questions</h4>
                            <span class="question-count"><?= count($current_topic_questions['subjective']) ?> questions</span>
                        </div>
                        <div class="question-items">
                            <?php if (empty($current_topic_questions['subjective'])): ?>
                                <div class="no-data">No subjective questions found.</div>
                            <?php else: ?>
                                <?php foreach ($current_topic_questions['subjective'] as $index => $question): ?>
                                    <div class="question-item">
                                        <div class="question-header">
                                            <div class="question-text">
                                                <?= ($index + 1) . '. ' . htmlspecialchars($question['question_text']) ?>
                                            </div>
                                            <div class="question-meta">
                                                <span>Marks: <?= $question['marks'] ?></span>
                                                <span>Difficulty: <?= ucfirst($question['difficulty_level']) ?></span>
                                                <span class="question-type-badge subjective">Subjective</span>
                                            </div>
                                        </div>
                                        
                                        <div class="question-answer">
                                            <div class="answer-label">Model Answer:</div>
                                            <div class="answer-content">
                                                <?= nl2br(htmlspecialchars($question['correct_answer'])) ?>
                                            </div>
                                        </div>
                                        
                                        <div class="question-footer">
                                            <div class="question-class">Class: <?= htmlspecialchars($question['class']) ?></div>
                                            <div class="question-actions">
                                                <button class="btn btn-sm" 
                                                        onclick="editSubjectiveQuestion(<?= $question['id'] ?>)"
                                                        style="background: #ffc107; color: black;">
                                                    Edit
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this question?')">
                                                    <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                                    <input type="hidden" name="question_type" value="subjective">
                                                    <button type="submit" name="delete_question" class="btn btn-sm btn-danger">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Theory Questions -->
                    <?php if (!empty($current_topic_questions['theory'])): ?>
                    <div class="question-section">
                        <div class="question-section-header">
                            <h4>Theory Questions</h4>
                            <span class="question-count"><?= count($current_topic_questions['theory']) ?> questions</span>
                        </div>
                        <div class="question-items">
                            <?php foreach ($current_topic_questions['theory'] as $index => $question): ?>
                                <div class="question-item">
                                    <div class="question-header">
                                        <div class="question-text">
                                            <?= ($index + 1) . '. ' ?>
                                            <?php if (!empty($question['question_text'])): ?>
                                                <?= htmlspecialchars($question['question_text']) ?>
                                            <?php else: ?>
                                                <em>File-based question</em>
                                            <?php endif; ?>
                                        </div>
                                        <div class="question-meta">
                                            <span>Marks: <?= $question['marks'] ?></span>
                                            <span class="question-type-badge theory">Theory</span>
                                            <?php if (!empty($question['question_file'])): ?>
                                                <span style="color: #4a90e2;">📎 File Attached</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($question['question_file'])): ?>
                                    <div class="question-answer">
                                        <div class="answer-label">Attached File:</div>
                                        <div class="answer-content">
                                            <?php
                                            $file_path = '../uploads/theory_questions/' . $question['question_file'];
                                            $file_extension = strtolower(pathinfo($question['question_file'], PATHINFO_EXTENSION));
                                            $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                            $is_pdf = $file_extension === 'pdf';
                                            ?>
                                            
                                            <?php if (!empty($question['question_description'])): ?>
                                                <strong>Description:</strong> <?= htmlspecialchars($question['question_description']) ?><br>
                                            <?php endif; ?>
                                            
                                            <strong>File:</strong> <?= htmlspecialchars($question['question_file']) ?>
                                            
                                            <div style="margin-top: 1rem;">
                                                <?php if ($is_image): ?>
                                                    <div class="image-preview">
                                                        <strong>Image Preview:</strong><br>
                                                        <img src="<?= $file_path ?>" 
                                                             alt="Question Image"
                                                             onerror="this.style.display='none'">
                                                    </div>
                                                    <div class="file-preview-actions">
                                                        <a href="<?= $file_path ?>" target="_blank" class="btn btn-sm" style="background: #4a90e2;">
                                                            🔍 View Full Size
                                                        </a>
                                                    </div>
                                                <?php elseif ($is_pdf): ?>
                                                    <div class="pdf-preview">
                                                        <strong>PDF Document:</strong><br>
                                                        <iframe src="<?= $file_path ?>#toolbar=0&navpanes=0&scrollbar=0" 
                                                                onerror="this.style.display='none'">
                                                            <p>Your browser does not support iframes. 
                                                               <a href="<?= $file_path ?>" target="_blank">Open PDF in new tab</a>
                                                            </p>
                                                        </iframe>
                                                        <div class="file-preview-actions">
                                                            <a href="<?= $file_path ?>" target="_blank" class="btn btn-sm" style="background: #4a90e2;">
                                                                📄 Open PDF in New Tab
                                                            </a>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="file-preview-actions">
                                                        <a href="<?= $file_path ?>" 
                                                           target="_blank" 
                                                           class="btn btn-sm" 
                                                           style="background: #4a90e2;">
                                                            📥 Download File
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($question['correct_answer'])): ?>
                                    <div class="question-answer">
                                        <div class="answer-label">Answer:</div>
                                        <div class="answer-content">
                                            <?= nl2br(htmlspecialchars($question['correct_answer'])) ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="question-footer">
                                        <div class="question-class">Class: <?= htmlspecialchars($question['class']) ?></div>
                                        <div class="question-actions">
                                            <button class="btn btn-sm" 
                                                    onclick="editTheoryQuestion(<?= $question['id'] ?>)"
                                                    style="background: #ffc107; color: black;">
                                                Edit
                                            </button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this question?')">
                                                <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                                <input type="hidden" name="question_type" value="theory">
                                                <button type="submit" name="delete_question" class="btn btn-sm btn-danger">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Summary -->
                    <div class="summary-card">
                        <h4>Question Summary</h4>
                        <div class="summary-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?= count($current_topic_questions['objective']) ?></div>
                                <div class="stat-label">Objective</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= count($current_topic_questions['subjective']) ?></div>
                                <div class="stat-label">Subjective</div>
                            </div>
                            <?php if (!empty($current_topic_questions['theory'])): ?>
                            <div class="stat-item">
                                <div class="stat-value"><?= count($current_topic_questions['theory']) ?></div>
                                <div class="stat-label">Theory</div>
                            </div>
                            <?php endif; ?>
                            <div class="stat-item">
                                <div class="stat-value" style="color: #333;">
                                    <?= count($current_topic_questions['objective']) + count($current_topic_questions['subjective']) + count($current_topic_questions['theory']) ?>
                                </div>
                                <div class="stat-label">Total</div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">No questions to display</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal() {
            document.getElementById('questionsModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('questionsModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Remove view_questions from URL without reloading
            const url = new URL(window.location);
            url.searchParams.delete('view_questions');
            url.searchParams.delete('topic_id');
            window.history.replaceState({}, '', url);
        }

        function viewQuestions(topicId, topicName) {
            // Update modal title
            document.getElementById('modalTopicName').textContent = `Questions: ${topicName}`;
            
            // Navigate to the same page with view_questions parameter
            const url = new URL(window.location);
            url.searchParams.set('view_questions', 'true');
            url.searchParams.set('topic_id', topicId);
            window.location.href = url.toString();
        }

        function filterTopics(subjectId) {
            if (subjectId) {
                window.location.href = `manage_subjects_topics.php?subject_id=${subjectId}`;
            } else {
                window.location.href = `manage_subjects_topics.php`;
            }
        }

        function editTopic(topicId) {
            window.location.href = `edit_topic.php?id=${topicId}`;
        }

        function editObjectiveQuestion(questionId) {
            window.location.href = `edit_question.php?id=${questionId}`;
        }

        function editSubjectiveQuestion(questionId) {
            window.location.href = `edit_question.php?id=${questionId}`;
        }

        function editTheoryQuestion(questionId) {
            window.location.href = `edit_theory_questions.php?id=${questionId}`;
        }

        // Auto-open modal if view_questions parameter is present
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_GET['view_questions']) && !empty($current_topic_questions)): ?>
                openModal();
            <?php endif; ?>

            // Close modal when clicking outside
            document.getElementById('questionsModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });

            // Mobile menu functionality
            const menuToggle = document.getElementById('menuToggle');
            const navLinks = document.getElementById('navLinks');
            
            if (menuToggle && navLinks) {
                menuToggle.addEventListener('click', function() {
                    navLinks.classList.toggle('active');
                });
            }

            // Close mobile menu when clicking outside
            document.addEventListener('click', function(e) {
                if (navLinks && navLinks.classList.contains('active') && 
                    !e.target.closest('.nav-links') && 
                    !e.target.closest('#menuToggle')) {
                    navLinks.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>