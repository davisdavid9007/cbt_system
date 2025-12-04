<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions - Mighty School For Valours</title>
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
        .dashboard-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .dashboard-card h2 {
            color: #333;
            margin-bottom: 2rem;
            text-align: center;
            font-size: 2rem;
        }
        .question-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .question-card {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .question-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        .question-card.objective {
            border-color: #4a90e2;
        }
        .question-card.subjective {
            border-color: #2ecc71;
        }
        .question-card.theory {
            border-color: #ff6b6b;
        }
        .question-card.passage {
            border-color: #f39c12;
        }
        .question-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .question-card h3 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        .question-card p {
            color: #666;
            line-height: 1.6;
        }
        .stats-section {
            background: #e7f3ff;
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        .stats-section h3 {
            color: #004085;
            margin-bottom: 1rem;
            text-align: center;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .stat-item {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #4a90e2;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #4a90e2;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #4a90e2;
            text-decoration: none;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border: 2px solid #4a90e2;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .back-link:hover {
            background: #4a90e2;
            color: white;
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
            <span>Admin Panel - Manage Questions</span>
        </div>
    </div>

    <div class="container">
        <div class="dashboard-card">
            <h2>📚 Question Management</h2>
            
            <div class="question-types">
                <div class="question-card objective" onclick="location.href='upload_objective.php'">
                    <div class="question-icon">🔘</div>
                    <h3>Objective Questions</h3>
                    <p>Multiple choice questions with options A, B, C, D. Upload via Excel file for bulk import.</p>
                </div>
                
                <div class="question-card subjective" onclick="location.href='upload_subjective.php'">
                    <div class="question-icon">✍️</div>
                    <h3>Subjective Questions</h3>
                    <p>Fill-in-the-blank questions where students write answers in designated spaces.</p>
                </div>
                
                <div class="question-card theory" onclick="location.href='upload_theory.php'">
                    <div class="question-icon">📝</div>
                    <h3>Theory Questions</h3>
                    <p>Essay and long-answer questions. Students write detailed answers on answer sheets.</p>
                </div>
                
                <div class="question-card passage" onclick="location.href='upload_passage_questions.php'">
                    <div class="question-icon">📖</div>
                    <h3>Passage Questions</h3>
                    <p>Questions based on reading passages. Can include multiple questions from a single passage.</p>
                </div>
            </div>

            <?php
            // Get question statistics
            $objective_count = $pdo->query("SELECT COUNT(*) FROM objective_questions")->fetchColumn();
            $subjective_count = $pdo->query("SELECT COUNT(*) FROM subjective_questions")->fetchColumn();
            $theory_count = $pdo->query("SELECT COUNT(*) FROM theory_questions")->fetchColumn();
            $passage_count = $pdo->query("SELECT COUNT(*) FROM passages")->fetchColumn();
            $total_questions = $objective_count + $subjective_count + $theory_count + $passage_count;
            ?>

            <div class="stats-section">
                <h3>📊 Question Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_questions; ?></div>
                        <div class="stat-label">Total Questions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $objective_count; ?></div>
                        <div class="stat-label">Objective Questions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $subjective_count; ?></div>
                        <div class="stat-label">Subjective Questions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $theory_count; ?></div>
                        <div class="stat-label">Theory Questions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $passage_count; ?></div>
                        <div class="stat-label">Passage Questions</div>
                    </div>
                </div>
            </div>

            <a href="index.php" class="back-link">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
