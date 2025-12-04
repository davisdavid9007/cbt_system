<?php
// get_questions_by_topic.php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

if (!isset($_GET['topic_id']) || empty($_GET['topic_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Topic ID is required');
}

$topic_id = $_GET['topic_id'];

// Get topic info
$stmt = $pdo->prepare("SELECT topic_name, subject_id FROM topics WHERE id = ?");
$stmt->execute([$topic_id]);
$topic = $stmt->fetch();

if (!$topic) {
    echo '<div class="no-data">Topic not found.</div>';
    exit;
}

// Get subject info
$stmt = $pdo->prepare("SELECT subject_name, class FROM subjects WHERE id = ?");
$stmt->execute([$topic['subject_id']]);
$subject = $stmt->fetch();

// Get questions for the topic
$current_topic_questions = [];

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
?>

<div style="margin-bottom: 1.5rem; padding: 1rem; background: #e7f3ff; border-radius: 8px;">
    <h4 style="margin: 0 0 0.5rem 0;">Topic Information</h4>
    <p style="margin: 0.2rem 0;"><strong>Subject:</strong> <?= htmlspecialchars($subject['subject_name']) ?> (Class <?= $subject['class'] ?>)</p>
</div>

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
                    <div class="question-image" style="margin: 1rem 0;">
                        <div style="font-weight: 600; color: #333; margin-bottom: 0.5rem;">📷 Question Image:</div>
                        <img src="../uploads/objective_questions/<?= htmlspecialchars($question['question_image']) ?>" 
                             alt="Question Image" 
                             style="max-width: 100%; max-height: 300px; border: 1px solid #ddd; border-radius: 8px;"
                             onerror="this.style.display='none'">
                        <div style="text-align: center; margin-top: 0.5rem;">
                            <a href="../uploads/objective_questions/<?= htmlspecialchars($question['question_image']) ?>" 
                               target="_blank" 
                               class="btn btn-sm" 
                               style="background: #4a90e2; color: white; text-decoration: none; padding: 0.3rem 0.8rem; border-radius: 4px; font-size: 0.8rem;">
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
                                <button type="submit" name="delete_question" class="btn btn-sm btn-danger" 
                                        style="background: #dc3545; color: white;">
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
                                <button type="submit" name="delete_question" class="btn btn-sm btn-danger" 
                                        style="background: #dc3545; color: white;">
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
                
                <!-- Display file information if present -->
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
                                <!-- Display image inline -->
                                <div class="image-preview">
                                    <strong>Image Preview:</strong><br>
                                    <img src="<?= $file_path ?>" 
                                         alt="Question Image" 
                                         style="max-width: 100%; max-height: 400px; border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;"
                                         onerror="this.style.display='none'">
                                </div>
                                <div class="file-preview-actions">
                                    <a href="<?= $file_path ?>" target="_blank" class="btn btn-sm" style="background: #4a90e2;">
                                        🔍 View Full Size
                                    </a>
                                </div>
                            <?php elseif ($is_pdf): ?>
                                <!-- Display PDF in embed or link to open in new tab -->
                                <div class="pdf-preview">
                                    <strong>PDF Document:</strong><br>
                                    <iframe src="<?= $file_path ?>#toolbar=0&navpanes=0&scrollbar=0" 
                                            width="100%" 
                                            height="400px" 
                                            style="border: 1px solid #ddd; border-radius: 5px; margin-top: 0.5rem;"
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
                                <!-- For other file types, show download link -->
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
                            <button type="submit" name="delete_question" class="btn btn-sm btn-danger" 
                                    style="background: #dc3545; color: white;">
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

<script>
function editObjectiveQuestion(questionId) {
    window.location.href = `edit_question.php?id=${questionId}`;
}

function editSubjectiveQuestion(questionId) {
    window.location.href = `edit_question.php?id=${questionId}`;
}

function editTheoryQuestion(questionId) {
    window.location.href = `edit_theory_questions.php?id=${questionId}`;
}
</script>