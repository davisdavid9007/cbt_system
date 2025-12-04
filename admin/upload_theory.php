<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Include PhpSpreadsheet for bulk upload
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

$message = '';
$message_type = '';

// Handle single question upload (text input)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_single'])) {
    $class = $_POST['class'];
    $subject_id = $_POST['subject_id'];
    $topic_id = $_POST['topic_id'];
    $marks = $_POST['marks'];
    $question_text = trim($_POST['question_text']);
    $difficulty_level = $_POST['difficulty_level'];

    try {
        $stmt = $pdo->prepare("
            INSERT INTO theory_questions 
            (class, subject_id, topic_id, marks, question_text, difficulty_level, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$class, $subject_id, $topic_id, $marks, $question_text, $difficulty_level]);
        
        $message = "Theory question added successfully!";
        $message_type = "success";
        
        // Clear form
        $_POST = [];
        
    } catch (Exception $e) {
        $message = "Error adding question: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_file'])) {
    $class = $_POST['class_file'];
    $subject_id = $_POST['subject_id_file'];
    $topic_id = $_POST['topic_id_file'];
    $marks = $_POST['marks_file'];
    $question_description = trim($_POST['question_description']);
    
    // Handle file upload
    if (isset($_FILES['question_file']) && $_FILES['question_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/theory_questions/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $fileType = $_FILES['question_file']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            $fileName = uniqid() . '_' . $_FILES['question_file']['name'];
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['question_file']['tmp_name'], $filePath)) {
                // Insert into database
                $stmt = $pdo->prepare("
                    INSERT INTO theory_questions 
                    (class, subject_id, topic_id, marks, question_text, question_file, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                if ($stmt->execute([$class, $subject_id, $topic_id, $marks, $question_description, $fileName])) {
                    $message = "Theory question with file uploaded successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error saving to database.";
                    $message_type = "error";
                }
            } else {
                $message = "Error uploading file.";
                $message_type = "error";
            }
        } else {
            $message = "Invalid file type. Please upload PDF or image files only.";
            $message_type = "error";
        }
    } else {
        $message = "Please select a file to upload.";
        $message_type = "error";
    }
}

// Handle bulk upload via Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_bulk'])) {
    $class = $_POST['class_bulk'];
    $subject_id = $_POST['subject_id_bulk'];
    $topic_id = $_POST['topic_id_bulk'];
    
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/excel_files/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = uniqid() . '_' . $_FILES['excel_file']['name'];
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $filePath)) {
            try {
                // Load the Excel file
                $spreadsheet = IOFactory::load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestRow = $worksheet->getHighestRow();
                
                $successCount = 0;
                $errorCount = 0;
                $errors = [];
                
                // Start from row 2 (assuming row 1 is headers)
                for ($row = 2; $row <= $highestRow; $row++) {
                    $questionText = $worksheet->getCell('A' . $row)->getCalculatedValue();
                    $marks = $worksheet->getCell('B' . $row)->getCalculatedValue();
                    
                    // Skip empty rows
                    if (empty($questionText)) {
                        continue;
                    }
                    
                    // Set defaults
                    $difficulty = 'medium';
                    
                    if (empty($marks) || !is_numeric($marks)) {
                        $marks = 10; // Default marks for theory questions
                    }
                    
                    // Insert into database
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO theory_questions 
                            (class, subject_id, topic_id, marks, question_text, difficulty_level, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $class,
                            $subject_id,
                            $topic_id,
                            $marks,
                            $questionText,
                            $difficulty
                        ]);
                        $successCount++;
                    } catch (PDOException $e) {
                        $errors[] = "Row $row: Database error - " . $e->getMessage();
                        $errorCount++;
                    }
                }
                
                // Prepare result message
                if ($successCount > 0) {
                    $message = "Successfully imported $successCount theory questions!";
                    $message_type = "success";
                    
                    if ($errorCount > 0) {
                        $message .= " $errorCount questions failed to import.";
                        $message_type = "warning";
                    }
                } else {
                    $message = "No questions were imported. Please check your Excel file format.";
                    $message_type = "error";
                }
                
                // Add detailed errors if any
                if (!empty($errors)) {
                    $message .= "<br><br><strong>Errors:</strong><br>" . implode("<br>", array_slice($errors, 0, 10));
                    if (count($errors) > 10) {
                        $message .= "<br>... and " . (count($errors) - 10) . " more errors";
                    }
                }
                
            } catch (Exception $e) {
                $message = "Error processing Excel file: " . $e->getMessage();
                $message_type = "error";
            }
            
            // Clean up - delete the uploaded file
            unlink($filePath);
            
        } else {
            $message = "Error uploading file.";
            $message_type = "error";
        }
    } else {
        $message = "Please select an Excel file to upload.";
        $message_type = "error";
    }
}

// Handle template download
if (isset($_GET['download_template'])) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set headers
    $sheet->setCellValue('A1', 'Question Text');
    $sheet->setCellValue('B1', 'Marks');
    
    // Set sample data
    $sheet->setCellValue('A2', 'Explain the theory of relativity and its implications in modern physics.');
    $sheet->setCellValue('B2', '15');
    
    $sheet->setCellValue('A3', 'Describe the process of photosynthesis with detailed chemical equations.');
    $sheet->setCellValue('B3', '20');
    
    // Auto size columns for better visibility
    foreach (range('A', 'B') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="theory_questions_template.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
}

// Get subjects and topics for dropdowns
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();
$all_topics = $pdo->query("SELECT * FROM topics ORDER BY subject_id, topic_name")->fetchAll();

// Group topics by subject_id for JavaScript
$topics_by_subject = [];
foreach ($all_topics as $topic) {
    $topics_by_subject[$topic['subject_id']][] = $topic;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Theory Questions - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%);
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
        .upload-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .upload-card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 600;
        }
        input, select, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        .form-group textarea {
            height: 150px;
            resize: vertical;
        }
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b6b;
        }
        .file-upload {
            border: 2px dashed #ff6b6b;
            padding: 2rem;
            text-align: center;
            border-radius: 10px;
            background: #f8f9fa;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .file-upload:hover {
            background: #e9ecef;
        }
        .file-upload input {
            display: none;
        }
        .upload-btn {
            background: linear-gradient(45deg, #ff6b6b, #ff5252);
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
        .upload-btn:hover {
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
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .excel-format {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        .excel-format h4 {
            color: #004085;
            margin-bottom: 0.5rem;
        }
        .excel-format table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.5rem;
        }
        .excel-format th,
        .excel-format td {
            border: 1px solid #b3d9ff;
            padding: 0.5rem;
            text-align: left;
        }
        .excel-format th {
            background: #004085;
            color: white;
        }
        .download-template {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 0.5rem;
            font-weight: 600;
        }
        .download-template:hover {
            background: #218838;
        }
        .upload-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #eee;
        }
        .upload-section h3 {
            color: #333;
            margin-bottom: 1rem;
            text-align: center;
        }
        .upload-option {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border-left: 4px solid #ff6b6b;
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
    </style>
</head>
<body>
    <div class="header">
        <div class="school-logo">       
            <img src="../assets/logo.png" alt="School Logo">
            <span>Admin Panel - Upload Theory Questions</span>
        </div>
    </div>

    <div class="container">
        <div class="upload-card">
            <h2>📚 Upload Theory Questions</h2>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            
            <!-- Single Question Upload - Text Input -->
            <div class="upload-section">
                <h3>✏️ Type Theory Question</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="class">Class:</label>
                            <select id="class" name="class" required>
                                <option value="">Select Class</option>
                                <option value="Creche">Creche</option>
                                <option value="Nur 2">Nur 2</option>
                                <option value="Kg">Kg</option>
                                <option value="Basic 1">Basic 1</option>
                                <option value="Basic 2">Basic 2</option>
                                <option value="Basic 3">Basic 3</option>
                                <option value="Basic 4">Basic 4</option>
                                <option value="Basic 5">Basic 5</option>
                                <option value="JS 1">JS 1</option>
                                <option value="JS 2">JS 2</option>
                                <option value="JS 3">JS 3</option>
                                <option value="SS 1">SS 1</option>
                                <option value="SS 2">SS 2</option>
                                <option value="SS 3">SS 3</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="subject_id">Subject:</label>
                            <select id="subject_id" name="subject_id" required onchange="populateTopics()">
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>"
                                        <?php echo ($_POST['subject_id'] ?? '') == $subject['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="topic_id">Topic:</label>
                        <select id="topic_id" name="topic_id" required>
                            <option value="">Select Subject First</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="marks">Marks:</label>
                            <input type="number" id="marks" name="marks" min="1" max="100" value="<?php echo $_POST['marks'] ?? 10; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="difficulty_level">Difficulty Level:</label>
                            <select id="difficulty_level" name="difficulty_level" required>
                                <option value="easy" <?php echo ($_POST['difficulty_level'] ?? '') === 'easy' ? 'selected' : ''; ?>>Easy</option>
                                <option value="medium" <?php echo ($_POST['difficulty_level'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="hard" <?php echo ($_POST['difficulty_level'] ?? '') === 'hard' ? 'selected' : ''; ?>>Hard</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="question_text">Question Text:</label>
                        <textarea id="question_text" name="question_text" placeholder="Type the theory question that students will see on screen..." required><?php echo htmlspecialchars($_POST['question_text'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" name="submit_single" class="upload-btn">✅ Upload Typed Question</button>
                </form>
            </div>

            <!-- File Upload Section -->
            <div class="upload-section">
                <h3>📎 Upload Question File</h3>
                <div class="upload-option">
                    <p><strong>Note:</strong> Use this option if the question contains diagrams, complex equations, or is in PDF/image format.</p>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="class_file">Class:</label>
                            <select id="class_file" name="class_file" required>
                                <option value="">Select Class</option>
                                <option value="Creche">Creche</option>
                                <option value="Nur 2">Nur 2</option>
                                <option value="Kg">Kg</option>
                                <option value="Basic 1">Basic 1</option>
                                <option value="Basic 2">Basic 2</option>
                                <option value="Basic 3">Basic 3</option>
                                <option value="Basic 4">Basic 4</option>
                                <option value="Basic 5">Basic 5</option>
                                <option value="JS 1">JS 1</option>
                                <option value="JS 2">JS 2</option>
                                <option value="JS 3">JS 3</option>
                                <option value="SS 1">SS 1</option>
                                <option value="SS 2">SS 2</option>
                                <option value="SS 3">SS 3</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="subject_id_file">Subject:</label>
                            <select id="subject_id_file" name="subject_id_file" required onchange="populateTopicsFile()">
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="topic_id_file">Topic:</label>
                        <select id="topic_id_file" name="topic_id_file" required>
                            <option value="">First select a subject</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="marks_file">Marks:</label>
                        <input type="number" id="marks_file" name="marks_file" min="1" max="100" required>
                    </div>

                    <div class="form-group">
                        <label for="question_description">Question Description (Optional):</label>
                        <textarea id="question_description" name="question_description" placeholder="Brief description of the question file..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Upload Question File (PDF or Image):</label>
                        <div class="file-upload" onclick="document.getElementById('question_file').click()">
                            <p>📁 Click to upload question file</p>
                            <p style="font-size: 0.9rem; color: #666;">(PDF, JPG, PNG, GIF)</p>
                            <input type="file" id="question_file" name="question_file" accept=".pdf,.jpg,.jpeg,.png,.gif" required onchange="updateFileName()">
                            <span id="file-name" style="font-size: 0.9rem; color: #ff6b6b;"></span>
                        </div>
                    </div>

                    <button type="submit" name="submit_file" class="upload-btn">📎 Upload Question File</button>
                </form>
            </div>

            <a href="questions.php" class="back-link">← Back to Questions</a>
        </div>
    </div>

    <script>
        // PHP data passed to JavaScript
        const topicsBySubject = <?php echo json_encode($topics_by_subject); ?>;

        // Function to populate topics for single question form
        function populateTopics() {
            const subjectSelect = document.getElementById('subject_id');
            const topicSelect = document.getElementById('topic_id');
            const selectedSubjectId = subjectSelect.value;
            
            // Clear existing options
            topicSelect.innerHTML = '';
            
            if (!selectedSubjectId) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Select Subject First';
                topicSelect.appendChild(option);
                return;
            }
            
            // Get topics for selected subject
            const topics = topicsBySubject[selectedSubjectId];
            
            if (topics && topics.length > 0) {
                // Add default option
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Select Topic';
                topicSelect.appendChild(defaultOption);
                
                // Add topic options
                topics.forEach(topic => {
                    const option = document.createElement('option');
                    option.value = topic.id;
                    option.textContent = topic.topic_name;
                    topicSelect.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No topics available for this subject';
                topicSelect.appendChild(option);
            }
        }

        // Function to populate topics for bulk upload form
        function populateTopicsBulk() {
            const subjectSelect = document.getElementById('subject_id_bulk');
            const topicSelect = document.getElementById('topic_id_bulk');
            const selectedSubjectId = subjectSelect.value;
            
            // Clear existing options
            topicSelect.innerHTML = '';
            
            if (!selectedSubjectId) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Select Subject First';
                topicSelect.appendChild(option);
                return;
            }
            
            // Get topics for selected subject
            const topics = topicsBySubject[selectedSubjectId];
            
            if (topics && topics.length > 0) {
                // Add default option
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Select Topic';
                topicSelect.appendChild(defaultOption);
                
                // Add topic options
                topics.forEach(topic => {
                    const option = document.createElement('option');
                    option.value = topic.id;
                    option.textContent = topic.topic_name;
                    topicSelect.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No topics available for this subject';
                topicSelect.appendChild(option);
            }
        }

        // Function to populate topics for file upload form
        function populateTopicsFile() {
            const subjectSelect = document.getElementById('subject_id_file');
            const topicSelect = document.getElementById('topic_id_file');
            const selectedSubjectId = subjectSelect.value;
            
            // Clear existing options
            topicSelect.innerHTML = '';
            
            if (!selectedSubjectId) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Select Subject First';
                topicSelect.appendChild(option);
                return;
            }
            
            // Get topics for selected subject
            const topics = topicsBySubject[selectedSubjectId];
            
            if (topics && topics.length > 0) {
                // Add default option
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Select Topic';
                topicSelect.appendChild(defaultOption);
                
                // Add topic options
                topics.forEach(topic => {
                    const option = document.createElement('option');
                    option.value = topic.id;
                    option.textContent = topic.topic_name;
                    topicSelect.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No topics available for this subject';
                topicSelect.appendChild(option);
            }
        }

        function updateFileName() {
            const fileInput = document.getElementById('question_file');
            const fileName = document.getElementById('file-name');
            if (fileInput.files.length > 0) {
                fileName.textContent = 'Selected: ' + fileInput.files[0].name;
            }
        }

        function updateExcelFileName() {
            const fileInput = document.getElementById('excel_file');
            const fileName = document.getElementById('excel-file-name');
            if (fileInput.files.length > 0) {
                fileName.textContent = 'Selected: ' + fileInput.files[0].name;
            }
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners
            const subjectSelect = document.getElementById('subject_id');
            const subjectSelectBulk = document.getElementById('subject_id_bulk');
            const subjectSelectFile = document.getElementById('subject_id_file');
            
            if (subjectSelect) {
                subjectSelect.addEventListener('change', populateTopics);
            }
            if (subjectSelectBulk) {
                subjectSelectBulk.addEventListener('change', populateTopicsBulk);
            }
            if (subjectSelectFile) {
                subjectSelectFile.addEventListener('change', populateTopicsFile);
            }
        });
    </script>
</body>
</html>