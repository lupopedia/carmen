<?php
/**
 * CARMEN API Endpoint
 * 
 * @package CARMEN
 * @version 0.1.3
 * 
 * WHO: CARMEN API Endpoint
 * WHAT: Handles API requests for CARMEN processing
 * WHEN: 2025-12-06
 * WHY: Provide AJAX endpoint for chat interface
 * 
 * Security: Based on DeepSeek & WOLFITH-GROK recommendations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load security manager
require_once __DIR__ . '/includes/security.php';

// Validate security (allow skipping CSRF for API calls, but require API key)
try {
    SecurityManager::validateSecurity([
        'skip_csrf' => true, // API calls use API key instead
        'skip_rate_limit' => false, // Enforce rate limiting
        'skip_api_key' => false // Require API key (or allow if not configured)
    ]);
} catch (Exception $e) {
    // Security check failed - response already sent, just exit
    exit;
}

require_once __DIR__ . '/includes/carmen_processor.php';

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? 'process';

try {
    switch ($action) {
        case 'process':
            // Validate and sanitize message
            try {
                $message = SecurityManager::validateMessage($_POST['message'] ?? '');
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            
            // Process message
            $result = processCARMENMessage($message);
            
            // Sanitize output before sending
            if (isset($result['unified_response'])) {
                // Keep original for JSON, but could sanitize if needed for HTML display
                // $result['unified_response'] = SecurityManager::sanitizeOutput($result['unified_response']);
            }
            
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

