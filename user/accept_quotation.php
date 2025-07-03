<?php
require_once '../config/database.php';

if (!isUser()) {
    redirect('../index.php');
}

$quotation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($quotation_id <= 0) {
    redirect('dashboard.php');
}

$database = new Database();
$conn = $database->getConnection();

// Verify quotation belongs to the user
$stmt = $conn->prepare("
    SELECT q.*, p.user_id, pu.pharmacy_name, pu.email as pharmacy_email
    FROM quotations q 
    JOIN prescriptions p ON q.prescription_id = p.id 
    JOIN pharmacy_users pu ON q.pharmacy_id = pu.id
    WHERE q.id = ? AND p.user_id = ? AND q.status = 'pending'
");
$stmt->execute([$quotation_id, $_SESSION['user_id']]);
$quotation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quotation) {
    redirect('dashboard.php');
}

try {
    $conn->beginTransaction();
    
    // Update quotation status
    $stmt = $conn->prepare("UPDATE quotations SET status = 'accepted' WHERE id = ?");
    $stmt->execute([$quotation_id]);
    
    // Update prescription status
    $stmt = $conn->prepare("UPDATE prescriptions SET status = 'accepted' WHERE id = ?");
    $stmt->execute([$quotation['prescription_id']]);
    
    // Reject other quotations for the same prescription
    $stmt = $conn->prepare("UPDATE quotations SET status = 'rejected' WHERE prescription_id = ? AND id != ?");
    $stmt->execute([$quotation['prescription_id'], $quotation_id]);
    
    $conn->commit();
    
    // Send email notification to pharmacy
    $email_subject = "Quotation Accepted - " . $_SESSION['user_name'];
    $email_message = "
        <h3>Quotation Accepted</h3>
        <p>Dear " . htmlspecialchars($quotation['pharmacy_name']) . ",</p>
        <p>" . htmlspecialchars($_SESSION['user_name']) . " has accepted your quotation.</p>
        <p><strong>Quotation Amount: LKR " . number_format($quotation['total_amount'], 2) . "</strong></p>
        <p>Please proceed with preparing the medicines for delivery.</p>
        <p>Customer Contact: " . htmlspecialchars($_SESSION['user_email']) . "</p>
        <p>Thank you for using our service!</p>
    ";
    
    sendEmail($quotation['pharmacy_email'], $email_subject, $email_message);
    
    $_SESSION['success_message'] = 'Quotation accepted successfully! The pharmacy has been notified.';
    
} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['error_message'] = 'Failed to accept quotation. Please try again.';
}

redirect('dashboard.php');
?>