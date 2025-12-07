<?php
/**
 * Security Manager for CARMEN API
 * 
 * @package CARMEN
 * @version 0.1.3
 * 
 * WHO: Security Manager
 * WHAT: Handles API security (authentication, validation, rate limiting, CSRF)
 * WHEN: 2025-12-06
 * WHY: Protect API endpoints from attacks (based on DeepSeek & WOLFITH-GROK recommendations)
 * 
 * Implementation based on: WOLFITH-GROK security code recommendations
 */

class SecurityManager {
    private static $rateLimitRequests = [];
    private static $rateLimitWindow = 60; // seconds
    private static $rateLimitMax = 60; // requests per window
    
    /**
     * Initialize security headers
     */
    public static function setSecurityHeaders(): void {
        header("Content-Security-Policy: default-src 'self'");
        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("X-XSS-Protection: 1; mode=block");
    }
    
    /**
     * Validate and sanitize input message
     * 
     * @param mixed $input Raw input
     * @return string Sanitized message
     * @throws Exception If input is invalid
     */
    public static function validateMessage($input): string {
        // Filter and sanitize
        $message = filter_var($input, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        
        // Remove null bytes and trim
        $message = trim(str_replace("\0", '', $message));
        
        // Validate length
        if (empty($message)) {
            throw new Exception('Message cannot be empty');
        }
        
        if (strlen($message) > 10000) {
            throw new Exception('Message too long (max 10000 characters)');
        }
        
        return $message;
    }
    
    /**
     * Check API key authentication
     * 
     * @param string $providedKey API key from request
     * @return bool True if valid
     */
    public static function checkApiKey(string $providedKey = ''): bool {
        $apiKey = $providedKey ?: ($_SERVER['HTTP_X_API_KEY'] ?? '');
        $validKey = getenv('CARMEN_API_KEY') ?: '';
        
        if (empty($validKey)) {
            // If no API key configured, allow access (development mode)
            // In production, this should require a key
            error_log("WARNING: CARMEN_API_KEY not configured - allowing access");
            return true;
        }
        
        // Use hash_equals for secure comparison (prevents timing attacks)
        return hash_equals($validKey, $apiKey);
    }
    
    /**
     * Check CSRF token
     * 
     * @param string $providedToken CSRF token from request
     * @return bool True if valid
     */
    public static function checkCsrfToken(string $providedToken = ''): bool {
        if (!session_id()) {
            session_start();
        }
        
        $token = $providedToken ?: ($_POST['csrf_token'] ?? '');
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        if (empty($sessionToken)) {
            // Generate token if not exists
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Generate CSRF token
     * 
     * @return string CSRF token
     */
    public static function generateCsrfToken(): string {
        if (!session_id()) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Check rate limit for IP address
     * 
     * @param string $ip IP address (defaults to REMOTE_ADDR)
     * @return bool True if under limit
     */
    public static function checkRateLimit(string $ip = ''): bool {
        $ip = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $now = time();
        $key = $ip . '_' . floor($now / self::$rateLimitWindow);
        
        // Initialize or increment
        if (!isset(self::$rateLimitRequests[$key])) {
            self::$rateLimitRequests[$key] = 0;
        }
        
        self::$rateLimitRequests[$key]++;
        
        // Clean old entries (simple cleanup, in production use Redis)
        foreach (self::$rateLimitRequests as $k => $count) {
            $keyTime = (int)explode('_', $k)[1];
            if ($now - ($keyTime * self::$rateLimitWindow) > self::$rateLimitWindow) {
                unset(self::$rateLimitRequests[$k]);
            }
        }
        
        return self::$rateLimitRequests[$key] <= self::$rateLimitMax;
    }
    
    /**
     * Validate all security checks
     * 
     * @param array $options Options (skip_api_key, skip_csrf, skip_rate_limit)
     * @throws Exception If security check fails
     */
    public static function validateSecurity(array $options = []): void {
        // Set security headers
        self::setSecurityHeaders();
        
        // Check API key (unless skipped)
        if (!($options['skip_api_key'] ?? false)) {
            if (!self::checkApiKey()) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized - Invalid API key']);
                exit;
            }
        }
        
        // Check CSRF (only for POST requests, unless skipped)
        if (!($options['skip_csrf'] ?? false) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!self::checkCsrfToken()) {
                http_response_code(403);
                echo json_encode(['error' => 'CSRF validation failed']);
                exit;
            }
        }
        
        // Check rate limit (unless skipped)
        if (!($options['skip_rate_limit'] ?? false)) {
            if (!self::checkRateLimit()) {
                http_response_code(429);
                echo json_encode(['error' => 'Rate limit exceeded']);
                exit;
            }
        }
    }
    
    /**
     * Sanitize output for HTML display
     * 
     * @param string $output Raw output
     * @return string Sanitized output
     */
    public static function sanitizeOutput(string $output): string {
        return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate JSON input
     * 
     * @param string $json JSON string
     * @return array Decoded array
     * @throws Exception If invalid JSON
     */
    public static function validateJson(string $json): array {
        $decoded = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }
        
        return $decoded;
    }
}

