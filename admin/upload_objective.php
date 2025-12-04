<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Include PhpSpreadsheet
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class = $_POST['class'];
    $subject_id = $_POST['subject_id'];
    $topic_id = $_POST['topic_id'];
    
    // Handle Excel file upload
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
                    $optionA = $worksheet->getCell('B' . $row)->getCalculatedValue();
                    $optionB = $worksheet->getCell('C' . $row)->getCalculatedValue();
                    $optionC = $worksheet->getCell('D' . $row)->getCalculatedValue();
                    $optionD = $worksheet->getCell('E' . $row)->getCalculatedValue();
                    $correctAnswer = strtoupper(trim($worksheet->getCell('F' . $row)->getCalculatedValue()));
                    $difficulty = strtolower(trim($worksheet->getCell('G' . $row)->getCalculatedValue()));
                    $marks = $worksheet->getCell('H' . $row)->getCalculatedValue();
                    
                    // Skip empty rows
                    if (empty($questionText) || empty($optionA) || empty($optionB) || empty($optionC) || empty($optionD) || empty($correctAnswer)) {
                        continue;
                    }
                    
                    // Validate correct answer
                    if (!in_array($correctAnswer, ['A', 'B', 'C', 'D'])) {
                        $errors[] = "Row $row: Invalid correct answer '$correctAnswer'. Must be A, B, C, or D.";
                        $errorCount++;
                        continue;
                    }
                    
                    // Set defaults for optional fields
                    if (empty($difficulty) || !in_array($difficulty, ['easy', 'medium', 'hard'])) {
                        $difficulty = 'medium';
                    }
                    
                    if (empty($marks) || !is_numeric($marks)) {
                        $marks = 1;
                    }
                    
                    // Insert into database
                    try {
                        $stmt = $pdo->prepare("INSERT INTO objective_questions (question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty_level, marks, subject_id, topic_id, class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $questionText,
                            $optionA,
                            $optionB,
                            $optionC,
                            $optionD,
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
                    $message = "Successfully imported $successCount questions!";
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
    <title>Upload Objective Questions - Mighty School For Valours</title>
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
        .logo span {
            color: #ff6b6b;
        }
        .container {
            max-width: 800px;
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
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 600;
        }
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #4a90e2;
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
            color: #4a90e2;
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
        .no-topics {
            color: #666;
            font-style: italic;
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
            <span>Admin Panel - Upload Objective Questions</span>
        </div>
    </div>

    <div class="container">
        <div class="upload-card">
            <h2>📝 Upload Objective Questions</h2>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="excel-format">
                <h4>📋 Excel File Format Required:</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Column A</th>
                            <th>Column B</th>
                            <th>Column C</th>
                            <th>Column D</th>
                            <th>Column E</th>
                            <th>Column F</th>
                            <th>Column G</th>
                        </tr>
                        <tr>
                            <th>Question</th>
                            <th>Option A</th>
                            <th>Option B</th>
                            <th>Option C</th>
                            <th>Option D</th>
                            <th>Correct Answer (A/B/C/D)</th>
                            <th>Marks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>What is 2 + 2?</td>
                            <td>3</td>
                            <td>4</td>
                            <td>5</td>
                            <td>6</td>
                            <td>B</td>
                            <td>1</td>
                        </tr>
                    </tbody>
                </table>
                <a href="download_template.php" class="download-template">📥 Download Excel Template</a>
            </div>

            <form method="POST" enctype="multipart/form-data">
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
                        <option value="JSS1">JS 1</option>
                        <option value="JSS2">JS 2</option>
                        <option value="JSS3">JS 3</option>
                        <option value="SS1">SS 1</option>
                        <option value="SS2">SS 2</option>
                        <option value="SS3">SS 3</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="subject_id">Subject:</label>
                    <select id="subject_id" name="subject_id" required onchange="populateTopics()">
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="topic_id">Topic:</label>
                    <select id="topic_id" name="topic_id" required>
                        <option value="">First select a subject</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Upload Excel File:</label>
                    <div class="file-upload" onclick="document.getElementById('excel_file').click()">
                        <p>📁 Click to upload Excel file</p>
                        <p style="font-size: 0.9rem; color: #666;">(.xlsx or .xls format)</p>
                        <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls" required onchange="updateFileName()">
                        <span id="file-name" style="font-size: 0.9rem; color: #4a90e2;"></span>
                    </div>
                </div>

                <button type="submit" class="upload-btn">🚀 Upload Questions</button>
            </form>

            <a href="questions.php" class="back-link">← Back to Questions</a>
        </div>
    </div>

    <script>
        // PHP data passed to JavaScript
        const topicsBySubject = <?php echo json_encode($topics_by_subject); ?>;

        // Function to populate topics based on selected subject
        function populateTopics() {
            const subjectSelect = document.getElementById('subject_id');
            const topicSelect = document.getElementById('topic_id');
            const selectedSubjectId = subjectSelect.value;
            
            // Clear existing options
            topicSelect.innerHTML = '';
            
            if (!selectedSubjectId) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'First select a subject';
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
            console.log('Topics data loaded:', topicsBySubject);
            
            // Add event listener to subject dropdown
            const subjectSelect = document.getElementById('subject_id');
            if (subjectSelect) {
                subjectSelect.addEventListener('change', populateTopics);
            }
        });
    </script>
</body>
</html>