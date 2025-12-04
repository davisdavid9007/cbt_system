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
    $exam_type = $_POST['exam_type']; // 'single' or 'bulk'

    if ($exam_type === 'single') {
        // Single exam creation (existing functionality)
        $exam_name = $_POST['exam_name'];
        $class = $_POST['class'];
        $subject_id = $_POST['subject_id'];
        $topics = isset($_POST['topics']) ? $_POST['topics'] : [];
        $objective_count = $_POST['objective_count'];
        $theory_count = $_POST['theory_count'];
        $duration_minutes = $_POST['duration_minutes'];

        // Convert topics array to JSON
        $topics_json = json_encode($topics);

        // Insert exam into database
        $stmt = $pdo->prepare("INSERT INTO exams (exam_name, class, subject_id, topics, objective_count, theory_count, duration_minutes, is_active, exam_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'single')");
        $stmt->execute([$exam_name, $class, $subject_id, $topics_json, $objective_count, $theory_count, $duration_minutes, 1]);

        $message = "Single exam created successfully!";
        $message_type = "success";
    } elseif ($exam_type === 'bulk') {
        // Bulk exam creation - FIXED: Create as GROUP exams
        $bulk_exam_name = $_POST['bulk_exam_name'];
        $classes = $_POST['classes'] ?? [];
        $subjects = $_POST['bulk_subjects'] ?? [];
        $objective_count = $_POST['bulk_objective_count'];
        $theory_count = $_POST['bulk_theory_count'];
        $duration_minutes = $_POST['bulk_duration_minutes'];

        $created_count = 0;
        $error_count = 0;
        $error_details = [];

        // Check if we have classes and subjects selected
        if (empty($classes)) {
            $message = "❌ Please select at least one class!";
            $message_type = "error";
        } elseif (empty($subjects)) {
            $message = "❌ Please select at least one subject!";
            $message_type = "error";
        } else {
            foreach ($classes as $class) {
                try {
                    // Create a subject group for this class combination
                    $group_name = $bulk_exam_name . " - " . $class;

                    $stmt = $pdo->prepare("INSERT INTO subject_groups (group_name, total_duration_minutes, is_active) VALUES (?, ?, ?)");
                    $result = $stmt->execute([$group_name, $duration_minutes, 1]);

                    if (!$result) {
                        throw new Exception("Failed to create subject group");
                    }

                    $group_id = $pdo->lastInsertId();

                    // Add subjects to the group
                    $display_order = 1;
                    foreach ($subjects as $subject_id) {
                        $stmt = $pdo->prepare("INSERT INTO subject_group_members (group_id, subject_id, question_count, display_order) VALUES (?, ?, ?, ?)");
                        $result = $stmt->execute([$group_id, $subject_id, $objective_count, $display_order]);

                        if (!$result) {
                            throw new Exception("Failed to add subject $subject_id to group");
                        }

                        $display_order++;
                    }

                    // Create the GROUP exam (not single exam) - FIXED: subject_id is NULL for group exams
                    $exam_name = $bulk_exam_name . " - " . $class;

                    // For group exams, subject_id should be NULL and group_id should be set
                    $stmt = $pdo->prepare("INSERT INTO exams (exam_name, class, duration_minutes, objective_count, theory_count, is_active, exam_type, group_id, subject_id) VALUES (?, ?, ?, ?, ?, ?, 'group', ?, NULL)");
                    $result = $stmt->execute([$exam_name, $class, $duration_minutes, $objective_count, $theory_count, 1, $group_id]);

                    if (!$result) {
                        throw new Exception("Failed to create exam record");
                    }

                    $created_count++;
                } catch (Exception $e) {
                    $error_count++;
                    $error_msg = "❌ Error creating group exam for class $class: " . $e->getMessage();
                    $error_details[] = $error_msg;

                    // Get PDO error info for more details
                    $errorInfo = $stmt->errorInfo();
                    if ($errorInfo[0] != '00000') {
                        $error_details[] = "PDO Error: " . $errorInfo[2];
                    }
                }
            }

            if ($error_count > 0) {
                $message = "Created $created_count group exams successfully. $error_count group exams failed to create.";
                $message_type = "warning";

                // Show detailed errors
                if (count($error_details) > 0) {
                    $message .= "<br><br><strong>Error Details:</strong><br>" . implode("<br>", $error_details);
                }
            } else {
                $message = "✅ Successfully created $created_count group exams!";
                $message_type = "success";
            }
        }
    }
}

$subjects = $pdo->query("SELECT * FROM subjects")->fetchAll();
$topics = $pdo->query("SELECT * FROM topics")->fetchAll();

// Get all classes for bulk creation
$all_classes = ['JSS1_Emerald', 'JSS1_Pinnacle', 'JSS2_Emerald', 'JSS2_Pinnacle', 'JSS3_Paradise_Petals', 'SSS1_Aristotle', 'SSS1_Einstein', 'SSS2_Faraday', 'SSS2_Plato', 'SSS3_Newton', 'SSS3_Socrates'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Exam - Tip Top Schools</title>
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
            border-color: #2ecc71;
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
        }

        .topic-checkbox input {
            width: auto;
            margin-right: 0.5rem;
        }

        .create-btn {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.3s ease;
        }

        .create-btn:hover {
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

        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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

        /* New styles for bulk creation */
        .exam-type-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #eee;
        }

        .exam-type-tab {
            padding: 1rem 2rem;
            background: #f8f9fa;
            border: none;
            border-radius: 10px 10px 0 0;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
        }

        .exam-type-tab.active {
            background: #2ecc71;
            color: white;
        }

        .exam-type-content {
            display: none;
        }

        .exam-type-content.active {
            display: block;
        }

        .bulk-selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .bulk-checkbox-group {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            border: 2px solid #ddd;
        }

        .bulk-checkbox-group h4 {
            margin-bottom: 0.5rem;
            color: #333;
        }

        .bulk-checkbox-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .bulk-checkbox-item input {
            width: auto;
            margin-right: 0.5rem;
        }

        .select-all-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .bulk-summary {
            background: #e8f5e8;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid #2ecc71;
        }

        .bulk-summary h4 {
            color: #155724;
            margin-bottom: 0.5rem;
        }

        /* Add this new style for group exam info */
        .group-exam-info {
            background: #e8f4fd;
            border: 2px solid #3498db;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .group-exam-info h4 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="logo">
            <h1>Tip Top Schools</h1>
            <span>Admin Panel - Create Exam</span>
        </div>
    </div>

    <div class="container">
        <div class="exam-card">
            <h2>🎯 Create Exams</h2>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="exam-type-tabs">
                <button type="button" class="exam-type-tab active" onclick="showExamType('single')">Single Exam</button>
                <button type="button" class="exam-type-tab" onclick="showExamType('bulk')">Group Exams (Bulk)</button>
            </div>

            <!-- Single Exam Form -->
            <div id="single-exam" class="exam-type-content active">
                <form method="POST">
                    <input type="hidden" name="exam_type" value="single">

                    <div class="form-group">
                        <label for="exam_name">Exam Name:</label>
                        <input type="text" id="exam_name" name="exam_name" placeholder="e.g., First Term Mathematics Exam" required>
                    </div>

                    <div class="form-group">
                        <label for="class">Class:</label>
                        <select id="class" name="class" required>
                            <option value="">Select Class</option>
                            <?php foreach ($all_classes as $class): ?>
                                <option value="<?php echo $class; ?>"><?php echo $class; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject_id">Subject:</label>
                        <select id="subject_id" name="subject_id" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Select Topics:</label>
                        <div class="topics-container" id="topics-container">
                            <?php
                            // Get all topics with their subject information
                            $topics = $pdo->query("
                                SELECT t.*, s.subject_name 
                                FROM topics t 
                                LEFT JOIN subjects s ON t.subject_id = s.id 
                                ORDER BY s.subject_name, t.topic_name
                            ")->fetchAll();

                            if (empty($topics)): ?>
                                <div class="no-topics-message">No topics available. Please add topics first.</div>
                            <?php else: ?>
                                <?php foreach ($topics as $topic): ?>
                                    <div class="topic-checkbox" data-subject-id="<?php echo $topic['subject_id']; ?>">
                                        <input type="checkbox" id="topic_<?php echo $topic['id']; ?>" name="topics[]" value="<?php echo $topic['id']; ?>">
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
                        <input type="number" id="objective_count" name="objective_count" min="1" max="100" value="20" required>
                    </div>

                    <div class="form-group">
                        <label for="theory_count">Number of Theory Questions:</label>
                        <input type="number" id="theory_count" name="theory_count" min="0" max="20" value="5" required>
                    </div>

                    <div class="form-group">
                        <label for="duration_minutes">Duration (minutes):</label>
                        <input type="number" id="duration_minutes" name="duration_minutes" min="5" max="180" value="60" required>
                    </div>

                    <button type="submit" class="create-btn">✅ Create Single Exam</button>
                </form>
            </div>

            <!-- Bulk Exam Form - UPDATED FOR GROUP EXAMS -->
            <div id="bulk-exam" class="exam-type-content">
                <div class="group-exam-info">
                    <h4>📚 About Group Exams</h4>
                    <p>Group exams combine multiple subjects into one exam (UTME style). Students will see all subjects in one interface and can navigate between them.</p>
                </div>

                <form method="POST">
                    <input type="hidden" name="exam_type" value="bulk">

                    <div class="form-group">
                        <label for="bulk_exam_name">Group Exam Name:</label>
                        <input type="text" id="bulk_exam_name" name="bulk_exam_name" placeholder="e.g., End of Term Examination" required>
                        <small style="color: #666;">This will be the main name for the group exam</small>
                    </div>

                    <div class="form-group">
                        <label>Select Classes for Group Exams:</label>
                        <div class="bulk-selection-grid">
                            <div class="bulk-checkbox-group">
                                <h4>Classes</h4>
                                <button type="button" class="select-all-btn" onclick="toggleAllCheckboxes('classes', true)">Select All</button>
                                <button type="button" class="select-all-btn" onclick="toggleAllCheckboxes('classes', false)">Deselect All</button>
                                <?php foreach ($all_classes as $class): ?>
                                    <div class="bulk-checkbox-item">
                                        <input type="checkbox" id="class_<?php echo $class; ?>" name="classes[]" value="<?php echo $class; ?>">
                                        <label for="class_<?php echo $class; ?>"><?php echo $class; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="bulk-checkbox-group">
                                <h4>Subjects to Include</h4>
                                <button type="button" class="select-all-btn" onclick="toggleAllCheckboxes('subjects', true)">Select All</button>
                                <button type="button" class="select-all-btn" onclick="toggleAllCheckboxes('subjects', false)">Deselect All</button>
                                <?php foreach ($subjects as $subject): ?>
                                    <div class="bulk-checkbox-item">
                                        <input type="checkbox" id="subject_<?php echo $subject['id']; ?>" name="bulk_subjects[]" value="<?php echo $subject['id']; ?>">
                                        <label for="subject_<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div id="bulk-summary" class="bulk-summary" style="display: none;">
                        <h4>📊 Group Exam Creation Summary</h4>
                        <div id="summary-content"></div>
                    </div>

                    <div class="form-group">
                        <label for="bulk_objective_count">Objective Questions per Subject:</label>
                        <input type="number" id="bulk_objective_count" name="bulk_objective_count" min="1" max="100" value="20" required>
                        <small style="color: #666;">Number of objective questions for EACH subject</small>
                    </div>

                    <div class="form-group">
                        <label for="bulk_theory_count">Theory Questions per Subject:</label>
                        <input type="number" id="bulk_theory_count" name="bulk_theory_count" min="0" max="20" value="5" required>
                        <small style="color: #666;">Number of theory questions for EACH subject</small>
                    </div>

                    <div class="form-group">
                        <label for="bulk_duration_minutes">Total Duration (minutes):</label>
                        <input type="number" id="bulk_duration_minutes" name="bulk_duration_minutes" min="5" max="360" value="120" required>
                        <small style="color: #666;">Total time for the entire group exam</small>
                    </div>

                    <button type="submit" class="create-btn">🚀 Create Group Exams</button>
                </form>
            </div>

            <a href="manage_exams.php" class="back-link">← Back to Exam Dashboard</a>
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

        // Function to show/hide exam type forms
        function showExamType(type) {
            // Hide all content
            document.querySelectorAll('.exam-type-content').forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.exam-type-tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected content and activate tab
            document.getElementById(type + '-exam').classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // Function to toggle all checkboxes
        function toggleAllCheckboxes(type, checked) {
            const checkboxes = document.querySelectorAll(`input[name="${type}[]"]`);
            checkboxes.forEach(checkbox => {
                checkbox.checked = checked;
            });
            updateBulkSummary();
        }

        // Updated bulk summary function for group exams
        function updateBulkSummary() {
            const selectedClasses = document.querySelectorAll('input[name="classes[]"]:checked');
            const selectedSubjects = document.querySelectorAll('input[name="bulk_subjects[]"]:checked');
            const summaryDiv = document.getElementById('bulk-summary');
            const summaryContent = document.getElementById('summary-content');

            if (selectedClasses.length > 0 && selectedSubjects.length > 0) {
                const totalGroupExams = selectedClasses.length;
                const totalSubjects = selectedSubjects.length;
                const totalDuration = document.getElementById('bulk_duration_minutes').value;
                const questionsPerSubject = document.getElementById('bulk_objective_count').value;

                summaryContent.innerHTML = `
                    <p><strong>Group Exams to Create:</strong> ${totalGroupExams}</p>
                    <p><strong>Classes:</strong> ${selectedClasses.length} selected</p>
                    <p><strong>Subjects per Exam:</strong> ${totalSubjects}</p>
                    <p><strong>Total Duration:</strong> ${totalDuration} minutes</p>
                    <p><strong>Questions per Subject:</strong> ${questionsPerSubject} objective + ${document.getElementById('bulk_theory_count').value} theory</p>
                    <p><strong>Example group exam names:</strong></p>
                    <ul>
                        <li>${document.getElementById('bulk_exam_name').value} - ${selectedClasses[0].value}</li>
                        ${selectedClasses[1] ? `<li>${document.getElementById('bulk_exam_name').value} - ${selectedClasses[1].value}</li>` : ''}
                    </ul>
                    <p><strong>Subjects included:</strong> ${Array.from(selectedSubjects).map(s => getSubjectName(s.value)).join(', ')}</p>
                `;
                summaryDiv.style.display = 'block';
            } else {
                summaryDiv.style.display = 'none';
            }
        }

        // Helper function to get subject name (you might want to preload this data)
        function getSubjectName(subjectId) {
            // This is a simplified version - you might want to preload subject names
            const subjectSelect = document.getElementById('subject_id');
            const option = subjectSelect.querySelector(`option[value="${subjectId}"]`);
            return option ? option.textContent : 'Subject';
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const subjectSelect = document.getElementById('subject_id');
            if (subjectSelect) {
                subjectSelect.addEventListener('change', filterTopics);
                filterTopics();
            }

            // Add event listeners for bulk form
            document.querySelectorAll('input[name="classes[]"], input[name="bulk_subjects[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', updateBulkSummary);
            });

            document.getElementById('bulk_exam_name').addEventListener('input', updateBulkSummary);
            document.getElementById('bulk_duration_minutes').addEventListener('input', updateBulkSummary);
            document.getElementById('bulk_objective_count').addEventListener('input', updateBulkSummary);
            document.getElementById('bulk_theory_count').addEventListener('input', updateBulkSummary);
        });
    </script>
</body>

</html>