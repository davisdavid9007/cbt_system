<?php
// File: admin/upload_subjective.php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Include PhpSpreadsheet
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

$message = '';
$message_type = '';

// Handle single question upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_single'])) {
    $question_text = trim($_POST['question_text']);
    $correct_answer = trim($_POST['correct_answer']);
    $marks = intval($_POST['marks']);
    $subject_id = intval($_POST['subject_id']);
    $topic_id = intval($_POST['topic_id']);
    $class = $_POST['class'];
    $difficulty_level = $_POST['difficulty_level'];

    try {
        $stmt = $pdo->prepare("
            INSERT INTO subjective_questions 
            (question_text, correct_answer, difficulty_level, marks, subject_id, topic_id, class, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$question_text, $correct_answer, $difficulty_level, $marks, $subject_id, $topic_id, $class]);
        
        $message = "Subjective question added successfully!";
        $message_type = "success";
        
        // Clear form
        $_POST = [];
        
    } catch (Exception $e) {
        $message = "Error adding question: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle bulk upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_bulk'])) {
    $subject_id = intval($_POST['subject_id_bulk']);
    $topic_id = intval($_POST['topic_id_bulk']);
    $class = $_POST['class_bulk'];
    
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
                    $correctAnswer = $worksheet->getCell('B' . $row)->getCalculatedValue();
                    $marks = $worksheet->getCell('C' . $row)->getCalculatedValue();
                    
                    // Skip empty rows
                    if (empty($questionText) || empty($correctAnswer)) {
                        continue;
                    }
                    
                    // Set defaults
                    $difficulty = 'medium';
                    
                    if (empty($marks) || !is_numeric($marks)) {
                        $marks = 5; // Default marks for subjective questions
                    }
                    
                    // Insert into database
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO subjective_questions 
                            (question_text, correct_answer, difficulty_level, marks, subject_id, topic_id, class, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $questionText,
                            $correctAnswer,
                            $difficulty,
                            $marks,
                            $subject_id,
                            $topic_id,
                            $class
                        ]);
                        $successCount++;
                    } catch (PDOException $e) {
                        $errors[] = "Row $row: Database error - " . $e->getMessage();
                        $errorCount++;
                    }
                }
                
                // Prepare result message
                if ($successCount > 0) {
                    $message = "Successfully imported $successCount subjective questions!";
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
    $sheet->setCellValue('B1', 'Correct Answer/Model Answer');
    $sheet->setCellValue('C1', 'Marks');
    
    // Set sample data
    $sheet->setCellValue('A2', 'Explain the process of photosynthesis in plants.');
    $sheet->setCellValue('B2', 'Photosynthesis is the process by which plants convert light energy into chemical energy, using carbon dioxide and water to produce glucose and oxygen.');
    $sheet->setCellValue('C2', '10');
    
    $sheet->setCellValue('A3', 'Describe the main causes of World War II.');
    $sheet->setCellValue('B3', 'The main causes include Treaty of Versailles, rise of fascism, economic depression, and failure of appeasement policy.');
    $sheet->setCellValue('C3', '15');
    
    // Auto size columns for better visibility
    foreach (range('A', 'C') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="subjective_questions_template.xlsx"');
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
    <title>Upload Subjective Questions - Admin Panel</title>
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
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #4a90e2;
        }
        textarea {
            height: 120px;
            resize: vertical;
        }
        .upload-btn {
            background: linear-gradient(45deg, #4a90e2, #357abd);
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
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #4a90e2;
            text-decoration: none;
            font-weight: 600;
        }
        .file-upload {
            border: 2px dashed #4a90e2;
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
            <span>Admin Panel - Upload Subjective Questions</span>
        </div>
    </div>

    <div class="container">
        <div class="upload-card">
            <h2>📝 Upload Subjective Questions</h2>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Bulk Upload Section -->
            <div class="upload-section">
                <h3>📁 Bulk Upload via Excel</h3>
                
                <div class="excel-format">
                    <h4>📋 Excel File Format Required:</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Column A</th>
                                <th>Column B</th>
                                <th>Column C</th>
                            </tr>
                            <tr>
                                <th>Question Text</th>
                                <th>Correct Answer/Model Answer</th>
                                <th>Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Explain the process of photosynthesis...</td>
                                <td>Photosynthesis is the process by which plants convert light energy...</td>
                                <td>10</td>
                            </tr>
                        </tbody>
                    </table>
                    <a href="?download_template=1" class="download-template">📥 Download Excel Template</a>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="class_bulk">Class:</label>
                            <select id="class_bulk" name="class_bulk" required>
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
                            <label for="subject_id_bulk">Subject:</label>
                            <select id="subject_id_bulk" name="subject_id_bulk" required onchange="populateTopicsBulk()">
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
                        <label for="topic_id_bulk">Topic:</label>
                        <select id="topic_id_bulk" name="topic_id_bulk" required>
                            <option value="">First select a subject</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Upload Excel File:</label>
                        <div class="file-upload" onclick="document.getElementById('excel_file').click()">
                            <p>📁 Click to upload Excel file</p>
                            <p style="font-size: 0.9rem; color: #666;">(.xlsx or .xls format)</p>
                            <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls" onchange="updateFileName()">
                            <span id="file-name" style="font-size: 0.9rem; color: #4a90e2;"></span>
                        </div>
                    </div>

                    <button type="submit" name="submit_bulk" class="upload-btn">🚀 Upload Questions via Excel</button>
                </form>
            </div>

            <!-- Single Question Upload Section -->
            <div class="upload-section">
                <h3>✏️ Add Single Question</h3>
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

                    <div class="form-group">
                        <label for="question_text">Question Text:</label>
                        <textarea id="question_text" name="question_text" placeholder="Enter the question text that students will see on screen..." required><?php echo htmlspecialchars($_POST['question_text'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="correct_answer">Correct Answer/Model Answer:</label>
                        <textarea id="correct_answer" name="correct_answer" placeholder="Enter the model answer for evaluation reference..." required><?php echo htmlspecialchars($_POST['correct_answer'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="marks">Marks:</label>
                            <input type="number" id="marks" name="marks" min="1" max="50" value="<?php echo $_POST['marks'] ?? 5; ?>" required>
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

                    <button type="submit" name="submit_single" class="upload-btn">✅ Upload Single Question</button>
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

        function updateFileName() {
            const fileInput = document.getElementById('excel_file');
            const fileName = document.getElementById('file-name');
            if (fileInput.files.length > 0) {
                fileName.textContent = 'Selected: ' + fileInput.files[0].name;
            }
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners
            const subjectSelect = document.getElementById('subject_id');
            const subjectSelectBulk = document.getElementById('subject_id_bulk');
            
            if (subjectSelect) {
                subjectSelect.addEventListener('change', populateTopics);
            }
            if (subjectSelectBulk) {
                subjectSelectBulk.addEventListener('change', populateTopicsBulk);
            }
        });
    </script>
</body>
</html>