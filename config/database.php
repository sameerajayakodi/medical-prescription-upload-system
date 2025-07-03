<?php
// Database Configuration
class Database {
    private $host = 'localhost';
    private $db_name = 'prescription_system';
    private $username = 'root';
    private $password = 'acpt';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Session management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) || isset($_SESSION['pharmacy_id']);
}

function isUser() {
    return isset($_SESSION['user_id']);
}

function isPharmacy() {
    return isset($_SESSION['pharmacy_id']);
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Email configuration (simple mail function)
function sendEmail($to, $subject, $message) {
    $headers = "From: noreply@prescriptionsystem.com\r\n";
    $headers .= "Reply-To: noreply@prescriptionsystem.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// File upload configuration
define('UPLOAD_DIR', 'uploads/prescriptions/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
?>