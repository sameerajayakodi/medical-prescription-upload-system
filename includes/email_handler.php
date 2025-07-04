<?php

require_once '../config/database.php';

class QuotationEmailHandler {
    private $from_email = "sameerajayakodi456@gmail.com";
    private $from_name = "PrescriptionSystem";
    
    public function sendQuotationEmail($prescription_id, $pharmacy_id) {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
           
            $stmt = $conn->prepare("
                SELECT p.*, u.name as user_name, u.email as user_email, u.contact_no as user_contact
                FROM prescriptions p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.id = ?
            ");
            $stmt->execute([$prescription_id]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$prescription) {
                throw new Exception("Prescription not found");
            }
            
            
            if (empty($prescription['user_email'])) {
                throw new Exception("User email not found. Please update user profile with email address.");
            }
            
            
            $stmt = $conn->prepare("SELECT * FROM pharmacy_users WHERE id = ?");
            $stmt->execute([$pharmacy_id]);
            $pharmacy_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pharmacy_data) {
                throw new Exception("Pharmacy not found");
            }
            
            
            $pharmacy = array(
                'id' => $pharmacy_data['id'],
                'name' => $pharmacy_data['pharmacy_name'],
                'address' => $pharmacy_data['address'],
                'contact_no' => $pharmacy_data['contact_no'],
                'email' => $pharmacy_data['email'],
                'license_no' => $pharmacy_data['license_no']
            );
        
            $stmt = $conn->prepare("SELECT * FROM quotations WHERE prescription_id = ? AND pharmacy_id = ?");
            $stmt->execute([$prescription_id, $pharmacy_id]);
            $quotation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$quotation) {
                throw new Exception("Quotation not found");
            }
            
     
            $stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
            $stmt->execute([$quotation['id']]);
            $quotation_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
      
            $to = $prescription['user_email'];
            $subject = "Quotation Received - PrescriptionSystem";
            $message = $this->generateEmailContent($prescription, $pharmacy, $quotation, $quotation_items);
            $headers = $this->generateHeaders();
         
            if (mail($to, $subject, $message, $headers)) {
                return array('success' => true, 'message' => 'Quotation email sent successfully to ' . $to);
            } else {
                return array('success' => false, 'message' => 'Failed to send email');
            }
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Error: ' . $e->getMessage());
        }
    }
    
    private function generateEmailContent($prescription, $pharmacy, $quotation, $quotation_items) {
        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6; 
            color: #1f2937; 
            margin: 0; 
            padding: 0; 
            background-color: #f9fafb;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background-color: white; 
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            padding: 30px 20px; 
            text-align: center; 
        }
        .header h1 { 
            margin: 0; 
            font-size: 28px; 
            font-weight: 600; 
            letter-spacing: -0.5px;
        }
        .header p { 
            margin: 5px 0 0 0; 
            font-size: 16px; 
            opacity: 0.9;
        }
        .content { 
            padding: 30px 20px; 
        }
        .greeting { 
            font-size: 18px; 
            margin-bottom: 20px; 
            color: #374151;
        }
        .info-box { 
            background-color: #f8fafc; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px; 
            padding: 20px; 
            margin: 20px 0; 
        }
        .info-box h3 { 
            margin: 0 0 15px 0; 
            color: #1e293b; 
            font-size: 16px; 
            font-weight: 600;
        }
        .info-row { 
            display: flex; 
            justify-content: space-between; 
            margin: 8px 0; 
            padding: 0;
        }
        .info-label { 
            font-weight: 500; 
            color: #64748b; 
        }
        .info-value { 
            color: #1e293b; 
            font-weight: 500;
        }
        .table-container { 
            margin: 25px 0; 
        }
        .quotation-table { 
            width: 100%; 
            border-collapse: collapse; 
            background-color: white; 
            border-radius: 8px; 
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .quotation-table th { 
            background-color: #f1f5f9; 
            color: #475569; 
            font-weight: 600; 
            padding: 15px 12px; 
            text-align: left; 
            font-size: 14px;
        }
        .quotation-table td { 
            padding: 12px; 
            border-bottom: 1px solid #e2e8f0; 
            color: #374151;
        }
        .quotation-table tr:last-child td { 
            border-bottom: none; 
        }
        .total-row { 
            background-color: #ecfdf5; 
            font-weight: 600; 
            color: #059669;
        }
        .total-amount { 
            font-size: 20px; 
            text-align: center; 
            padding: 20px; 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white; 
            border-radius: 8px; 
            margin: 20px 0;
            font-weight: 600;
        }
        .footer { 
            background-color: #f8fafc; 
            padding: 25px 20px; 
            border-top: 1px solid #e2e8f0; 
            font-size: 14px; 
            color: #64748b;
        }
        .brand { 
            font-size: 18px; 
            font-weight: 600; 
            color: #1e293b; 
            margin-bottom: 10px;
        }
        .timestamp { 
            font-size: 12px; 
            color: #94a3b8; 
            margin-top: 15px;
        }
        @media (max-width: 600px) {
            .container { margin: 0 10px; }
            .content { padding: 20px 15px; }
            .header { padding: 25px 15px; }
            .info-row { flex-direction: column; }
            .info-label, .info-value { margin: 2px 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Prescription Quotation</h1>
            <p>Your quotation is ready for review</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Hello <strong>' . htmlspecialchars($prescription['user_name']) . '</strong>,
            </div>
            
            <p>We have prepared a quotation for your prescription from <strong>' . htmlspecialchars($pharmacy['name']) . '</strong>.</p>
            
            <div class="info-box">
                <h3>Pharmacy Information</h3>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value">' . htmlspecialchars($pharmacy['name']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value">' . htmlspecialchars($pharmacy['address']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Contact:</span>
                    <span class="info-value">' . htmlspecialchars($pharmacy['contact_no']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value">' . htmlspecialchars($pharmacy['email']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">License No:</span>
                    <span class="info-value">' . htmlspecialchars($pharmacy['license_no']) . '</span>
                </div>
            </div>
            
            <div class="table-container">
                <table class="quotation-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($quotation_items as $item) {
            $html .= '
                        <tr>
                            <td>' . htmlspecialchars($item['drug_name']) . '</td>
                            <td>' . number_format($item['quantity'], 0) . '</td>
                            <td>LKR ' . number_format($item['unit_price'], 2) . '</td>
                            <td>LKR ' . number_format($item['total_price'], 2) . '</td>
                        </tr>';
        }
        
        $html .= '
                    </tbody>
                </table>
            </div>
            
            <div class="total-amount">
                Total: LKR ' . number_format($quotation['total_amount'], 2) . '
            </div>
            
            <div class="info-box">
                <h3>Delivery Details</h3>
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value">' . htmlspecialchars($prescription['delivery_address']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Preferred Time:</span>
                    <span class="info-value">' . htmlspecialchars($prescription['delivery_time']) . '</span>
                </div>';
        
        if ($prescription['note']) {
            $html .= '
                <div class="info-row">
                    <span class="info-label">Note:</span>
                    <span class="info-value">' . htmlspecialchars($prescription['note']) . '</span>
                </div>';
        }
        
        $html .= '
            </div>
        </div>
        
        <div class="footer">
            <div class="brand">PrescriptionSystem</div>
            <p>Please log in to your account to accept this quotation and proceed with your order.</p>
            <div class="timestamp">
                Quotation sent on ' . date('F j, Y \a\t g:i A') . '
            </div>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    private function generateHeaders() {
        return "MIME-Version: 1.0\r\n" .
               "Content-Type: text/html; charset=UTF-8\r\n" .
               "From: " . $this->from_name . " <" . $this->from_email . ">\r\n" .
               "Reply-To: " . $this->from_email . "\r\n" .
               "X-Mailer: PHP/" . phpversion();
    }
    
    
    public function testEmail($test_email = "hasinihiru3@gmail.com") {
        $subject = "Test Mail - PrescriptionSystem";
        $message = "
        <html>
        <head><title>Test Email</title></head>
        <body>
            <h2>Test Email from PrescriptionSystem</h2>
            <p>This is a test email to verify the email configuration.</p>
            <p>If you receive this email, the system is working correctly.</p>
            <p>Sent on: " . date('Y-m-d H:i:s') . "</p>
        </body>
        </html>";
        
        $headers = $this->generateHeaders();
        
        if (mail($test_email, $subject, $message, $headers)) {
            return array('success' => true, 'message' => 'Test email sent successfully to ' . $test_email);
        } else {
            return array('success' => false, 'message' => 'Failed to send test email');
        }
    }
}

?>