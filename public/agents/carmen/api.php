<?php
/**
 * CARMEN API Endpoint
 * 
 * @package CARMEN
 * @version 0.1.1
 * 
 * WHO: CARMEN API Endpoint
 * WHAT: Handles API requests for CARMEN processing
 * WHEN: 2025-12-06
 * WHY: Provide AJAX endpoint for chat interface
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/includes/carmen_processor.php';

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? 'process';

try {
    switch ($action) {
        case 'process':
            // Process message
            $message = $_POST['message'] ?? '';
            
            if (empty($message)) {
                throw new \Exception('Message is required');
            }
            
            $result = processCARMENMessage($message);
            echo json_encode($result);
            break;
            
        case 'status':
            // Check LLM provider status
            $status = checkLLMStatus();
            echo json_encode($status);
            break;
            
        default:
            throw new \Exception('Invalid action');
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

