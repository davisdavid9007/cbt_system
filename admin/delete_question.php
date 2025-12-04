<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_id = $_POST['question_id'] ?? 0;
    
    try {
        // Check if question exists
        $stmt = $pdo->prepare("SELECT id FROM objective_questions WHERE id = ?");
        $stmt->execute([$question_id]);
        $question = $stmt->fetch();
        
        if (!$question) {
            throw new Exception('Question not found');
        }
        
        // Delete the question
        $stmt = $pdo->prepare("DELETE FROM objective_questions WHERE id = ?");
        $stmt->execute([$question_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Question deleted successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting question: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>