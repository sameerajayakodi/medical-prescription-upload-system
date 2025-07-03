<?php
// ==== FIXED EMAIL HELPER FUNCTIONS ====
// Replace your entire email_helper.php with this:

/**
 * Send email using XAMPP's mail configuration
 */
function sendEmailNotification($to, $subject, $message, $fromEmail = 'sameerajayakodi456@gmail.com') {
    try {
        // Validate email address
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("❌ Invalid email address: " . $to);
            return false;
        }
        
        // Clean subject line (remove line breaks)
        $subject = str_replace(array("\r", "\n"), '', $subject);
        
        // Prepare headers for HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: PrescriptionSystem <" . $fromEmail . ">" . "\r\n";
        $headers .= "Reply-To: " . $fromEmail . "\r\n";
        $headers .= "Return-Path: " . $fromEmail . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers .= "X-Priority: 3" . "\r\n";
        
        // Log email attempt
        error_log("📧 Attempting to send email to: " . $to . " from: " . $fromEmail);
        
        // Attempt to send email
        $success = @mail($to, $subject, $message, $headers);
        
        if ($success) {
            error_log("✅ Email sent successfully to: " . $to . " from: " . $fromEmail);
            return true;
        } else {
            error_log("❌ Email failed from: " . $fromEmail . " to: " . $to . " - Trying alternative sender");
            
            // Try with alternative sender
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
            $headers .= "From: PrescriptionSystem <hasinihiru3@gmail.com>" . "\r\n";
            $headers .= "Reply-To: hasinihiru3@gmail.com" . "\r\n";
            $headers .= "Return-Path: hasinihiru3@gmail.com" . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            
            $success = @mail($to, $subject, $message, $headers);
            
            if ($success) {
                error_log("✅ Email sent with alternative sender to: " . $to);
                return true;
            } else {
                error_log("❌ Email completely failed to: " . $to);
                
                // Log the last error
                $last_error = error_get_last();
                if ($last_error) {
                    error_log("❌ PHP Error: " . $last_error['message']);
                }
                
                return false;
            }
        }
        
    } catch (Exception $e) {
        error_log("❌ Email Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Create quotation email template - FIXED VERSION
 */
function createQuotationEmailTemplate($prescription, $quotation_items, $total_amount, $quotation_id, $pharmacy_name) {
    try {
        // Ensure all required data exists with defaults
        $customer_name = htmlspecialchars($prescription['user_name'] ?? 'Valued Customer', ENT_QUOTES, 'UTF-8');
        $customer_email = htmlspecialchars($prescription['user_email'] ?? '', ENT_QUOTES, 'UTF-8');
        $delivery_address = htmlspecialchars($prescription['delivery_address'] ?? 'Address not provided', ENT_QUOTES, 'UTF-8');
        $delivery_time = htmlspecialchars($prescription['delivery_time'] ?? 'Not specified', ENT_QUOTES, 'UTF-8');
        $prescription_id = intval($prescription['id'] ?? 0);
        $created_at = $prescription['created_at'] ?? date('Y-m-d H:i:s');
        $pharmacy_name_safe = htmlspecialchars($pharmacy_name ?? 'Pharmacy', ENT_QUOTES, 'UTF-8');
        $total_amount_safe = number_format(floatval($total_amount), 2);
        $quotation_id_safe = intval($quotation_id);
        
        // Build detailed quotation table
        $items_html = '';
        
        if (!empty($quotation_items) && is_array($quotation_items)) {
            foreach ($quotation_items as $item) {
                // FIXED: Handle both 'drug_name' and 'drug' keys
                $drug_name = htmlspecialchars(
                    $item['drug_name'] ?? $item['drug'] ?? 'Unknown Medicine', 
                    ENT_QUOTES, 
                    'UTF-8'
                );
                $quantity = number_format(floatval($item['quantity'] ?? 0), 2);
                $unit_price = number_format(floatval($item['unit_price'] ?? 0), 2);
                $total_price = number_format(floatval($item['total_price'] ?? 0), 2);
                
                $items_html .= "
                    <tr>
                        <td style='padding: 10px; border: 1px solid #ddd; background-color: #f9f9f9;'>" . 
                        $drug_name . "</td>
                        <td style='padding: 10px; border: 1px solid #ddd; text-align: center;'>" . 
                        $quantity . "</td>
                        <td style='padding: 10px; border: 1px solid #ddd; text-align: right;'>LKR " . 
                        $unit_price . "</td>
                        <td style='padding: 10px; border: 1px solid #ddd; text-align: right; font-weight: bold;'>LKR " . 
                        $total_price . "</td>
                    </tr>";
            }
        }
        
        // If no items, add a placeholder
        if (empty($items_html)) {
            $items_html = "<tr><td colspan='4' style='text-align: center; padding: 20px;'>No items found</td></tr>";
        }
        
        $base_url = getBaseUrlHelper();
        
        $email_html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f4f4f4;
                }
                .container { 
                    max-width: 600px; 
                    margin: 20px auto; 
                    background: white; 
                    border-radius: 10px; 
                    overflow: hidden;
                    box-shadow: 0 0 20px rgba(0,0,0,0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                    color: white; 
                    padding: 30px 20px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 24px; 
                }
                .content { 
                    padding: 30px; 
                }
                .pharmacy-box {
                    background: #e8f4fd;
                    border: 2px solid #667eea;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 20px 0;
                    text-align: center;
                }
                .quotation-table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 20px 0;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                .quotation-table th { 
                    background: #667eea; 
                    color: white; 
                    padding: 15px 10px; 
                    text-align: left;
                    font-weight: bold;
                }
                .quotation-table td { 
                    padding: 10px; 
                    border: 1px solid #ddd; 
                }
                .total-row { 
                    background: #d4edda !important; 
                    font-weight: bold; 
                    font-size: 16px;
                }
                .delivery-info {
                    background: #fff3cd;
                    border: 2px solid #ffc107;
                    border-radius: 8px;
                    padding: 15px;
                    margin: 20px 0;
                }
                .button {
                    display: inline-block;
                    background: #667eea;
                    color: white;
                    padding: 15px 30px;
                    text-decoration: none;
                    border-radius: 5px;
                    font-weight: bold;
                    text-align: center;
                    margin: 20px 0;
                }
                .footer { 
                    background: #f8f9fa; 
                    padding: 20px; 
                    text-align: center; 
                    color: #666; 
                    font-size: 12px; 
                    border-top: 1px solid #eee;
                }
                .step {
                    background: #f8f9fa;
                    border-left: 4px solid #667eea;
                    padding: 10px 15px;
                    margin: 10px 0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 New Quotation Available!</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Your prescription quotation is ready</p>
                </div>
                
                <div class='content'>
                    <h2 style='color: #333;'>Dear " . $customer_name . ",</h2>
                    
                    <p>Great news! We have received a quotation for your prescription from:</p>
                    
                    <div class='pharmacy-box'>
                        <h3 style='margin: 0 0 10px 0; color: #667eea;'>🏪 " . $pharmacy_name_safe . "</h3>
                        <p style='margin: 0; color: #666;'>
                            Quotation ID: <strong>#QUO-" . str_pad($quotation_id_safe, 6, '0', STR_PAD_LEFT) . "</strong><br>
                            Date: " . date('F d, Y \a\t g:i A') . "
                        </p>
                    </div>
                    
                    <h3 style='color: #333;'>💊 Quotation Details:</h3>
                    <table class='quotation-table'>
                        <thead>
                            <tr>
                                <th>Medicine Name</th>
                                <th style='text-align: center;'>Quantity</th>
                                <th style='text-align: right;'>Unit Price</th>
                                <th style='text-align: right;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            " . $items_html . "
                            <tr class='total-row'>
                                <td colspan='3' style='text-align: right; padding: 15px;'>
                                    <strong>TOTAL AMOUNT:</strong>
                                </td>
                                <td style='text-align: right; padding: 15px; font-size: 18px;'>
                                    <strong>LKR " . $total_amount_safe . "</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class='delivery-info'>
                        <h4 style='margin: 0 0 10px 0; color: #856404;'>📍 Delivery Information:</h4>
                        <p style='margin: 5px 0;'><strong>Address:</strong> " . $delivery_address . "</p>
                        <p style='margin: 5px 0;'><strong>Preferred Time:</strong> " . $delivery_time . "</p>
                    </div>
                    
                    <h3 style='color: #333;'>📋 Next Steps:</h3>
                    <div class='step'>
                        <strong>Step 1:</strong> Review the quotation details above
                    </div>
                    <div class='step'>
                        <strong>Step 2:</strong> Login to your account to accept or request changes
                    </div>
                    <div class='step'>
                        <strong>Step 3:</strong> Once accepted, the pharmacy will prepare and deliver your order
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='" . $base_url . "/user/dashboard.php' class='button'>
                            🔗 View & Respond to Quotation
                        </a>
                    </div>
                    
                    <p><strong>Important:</strong> This quotation is valid for 7 days from the date issued. Please respond at your earliest convenience.</p>
                    
                    <p>If you have any questions about this quotation, please feel free to contact us.</p>
                    
                    <p>Thank you for choosing PrescriptionSystem!</p>
                    
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    
                    <p style='font-size: 12px; color: #666;'>
                        <strong>Prescription Details:</strong><br>
                        Original Submission: " . date('F d, Y \a\t g:i A', strtotime($created_at)) . "<br>
                        Prescription ID: #" . str_pad($prescription_id, 6, '0', STR_PAD_LEFT) . "
                    </p>
                </div>
                
                <div class='footer'>
                    <p><strong>PrescriptionSystem</strong><br>
                    This is an automated message. Please do not reply directly to this email.</p>
                    <p>© " . date('Y') . " PrescriptionSystem. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $email_html;
        
    } catch (Exception $e) {
        error_log("❌ Email template creation failed: " . $e->getMessage());
        return "<html><body><h2>Error creating email template</h2><p>" . htmlspecialchars($e->getMessage()) . "</p></body></html>";
    }
}

/**
 * Get base URL for links in email
 */
function getBaseUrlHelper() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $directory = dirname(dirname($script)); // Go up two levels from pharmacy/view_prescription.php
    return $protocol . '://' . $host . $directory;
}

/**
 * Send quotation notification email - MAIN FUNCTION
 */
function sendQuotationEmail($prescription, $quotation_items, $total_amount, $quotation_id, $pharmacy_name) {
    try {
        // Log the attempt
        error_log("🚀 sendQuotationEmail called with:");
        error_log("  - Prescription ID: " . ($prescription['id'] ?? 'N/A'));
        error_log("  - Customer Email: " . ($prescription['user_email'] ?? 'N/A'));
        error_log("  - Items Count: " . count($quotation_items));
        error_log("  - Total Amount: " . $total_amount);
        error_log("  - Pharmacy: " . $pharmacy_name);
        
        // Validate required data
        if (!isset($prescription['user_email']) || empty($prescription['user_email'])) {
            error_log("❌ No customer email found in prescription data");
            return false;
        }
        
        if (empty($quotation_items)) {
            error_log("❌ No quotation items provided");
            return false;
        }
        
        if (empty($pharmacy_name)) {
            error_log("❌ No pharmacy name provided");
            return false;
        }
        
        // Create email subject and message
        $email_subject = "💊 New Quotation from " . $pharmacy_name . " - PrescriptionSystem";
        $email_message = createQuotationEmailTemplate($prescription, $quotation_items, $total_amount, $quotation_id, $pharmacy_name);
        
        // Send the email
        $result = sendEmailNotification($prescription['user_email'], $email_subject, $email_message, 'sameerajayakodi456@gmail.com');
        
        // Log the result
        if ($result) {
            error_log("✅ sendQuotationEmail completed successfully");
        } else {
            error_log("❌ sendQuotationEmail failed");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ sendQuotationEmail Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Test email configuration
 */
function testEmailConfiguration($test_email) {
    $subject = "Test Email - PrescriptionSystem";
    $message = "
        <h2>Email Test Successful!</h2>
        <p>This is a test email from PrescriptionSystem.</p>
        <p>If you received this email, your email configuration is working correctly.</p>
        <p>Timestamp: " . date('Y-m-d H:i:s') . "</p>
    ";
    
    return sendEmailNotification($test_email, $subject, $message);
}

// Log when this file is included
error_log("📧 Email helper functions loaded successfully (FIXED VERSION)");

// ==== FIXED QUOTATION PROCESSING CODE ====
// Replace the quotation processing section in your main file with this:

/*
// Handle quotation submission - FIXED VERSION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_quotation'])) {
    $drugs = $_POST['drugs'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $unit_prices = $_POST['unit_prices'] ?? [];
    
    error_log("🔄 Quotation submission started");
    error_log("  - Drugs: " . count($drugs));
    error_log("  - Quantities: " . count($quantities));
    error_log("  - Unit Prices: " . count($unit_prices));
    
    if (empty($drugs) || empty($quantities) || empty($unit_prices)) {
        $error = 'Please add at least one drug to the quotation.';
        error_log("❌ Empty form data");
    } else {
        try {
            $conn->beginTransaction();
            error_log("📊 Transaction started");
            
            $total_amount = 0;
            $quotation_items = [];
            
            // Calculate total and validate items
            for ($i = 0; $i < count($drugs); $i++) {
                if (!empty($drugs[$i]) && !empty($quantities[$i]) && !empty($unit_prices[$i])) {
                    $drug = sanitize($drugs[$i]); // Using sanitize from database.php
                    $quantity = floatval($quantities[$i]);
                    $unit_price = floatval($unit_prices[$i]);
                    $item_total = $quantity * $unit_price;
                    
                    if ($quantity <= 0 || $unit_price <= 0) {
                        throw new Exception("Invalid quantity or price for drug: $drug");
                    }
                    
                    // FIXED: Use 'drug_name' to match database structure
                    $quotation_items[] = [
                        'drug_name' => $drug,  // Changed from 'drug' to 'drug_name'
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'total_price' => $item_total
                    ];
                    
                    $total_amount += $item_total;
                    
                    error_log("  - Item " . ($i + 1) . ": $drug - Qty: $quantity - Price: $unit_price");
                }
            }
            
            if (empty($quotation_items)) {
                throw new Exception("Please add at least one valid drug to the quotation.");
            }
            
            error_log("💰 Total amount calculated: " . $total_amount);
            error_log("📦 Total items: " . count($quotation_items));
            
            // Check if quotation already exists
            $stmt = $conn->prepare("SELECT * FROM quotations WHERE prescription_id = ? AND pharmacy_id = ?");
            $stmt->execute([$prescription_id, $_SESSION['pharmacy_id']]);
            $existing_quotation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete existing quotation if exists
            if ($existing_quotation) {
                error_log("🗑️ Deleting existing quotation ID: " . $existing_quotation['id']);
                
                $stmt = $conn->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
                $stmt->execute([$existing_quotation['id']]);
                
                $stmt = $conn->prepare("DELETE FROM quotations WHERE id = ?");
                $stmt->execute([$existing_quotation['id']]);
            }
            
            // Insert new quotation
            $stmt = $conn->prepare("INSERT INTO quotations (prescription_id, pharmacy_id, total_amount) VALUES (?, ?, ?)");
            $stmt->execute([$prescription_id, $_SESSION['pharmacy_id'], $total_amount]);
            $quotation_id = $conn->lastInsertId();
            
            error_log("✅ Quotation inserted with ID: " . $quotation_id);
            
            // Insert quotation items
            foreach ($quotation_items as $item) {
                $stmt = $conn->prepare("INSERT INTO quotation_items (quotation_id, drug_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$quotation_id, $item['drug_name'], $item['quantity'], $item['unit_price'], $item['total_price']]);
            }
            
            error_log("✅ Quotation items inserted");
            
            // Update prescription status
            $stmt = $conn->prepare("UPDATE prescriptions SET status = 'quoted' WHERE id = ?");
            $stmt->execute([$prescription_id]);
            
            error_log("✅ Prescription status updated to 'quoted'");
            
            $conn->commit();
            error_log("✅ Transaction committed");
            
            // Send email notification
            error_log("📧 Sending email notification...");
            $email_sent = sendQuotationEmail(
                $prescription, 
                $quotation_items, 
                $total_amount, 
                $quotation_id, 
                $_SESSION['pharmacy_name']
            );
            
            // Set success message
            if ($email_sent) {
                $success = 'Quotation submitted successfully! ✅ Email notification sent to customer.';
                error_log("✅ Email sent successfully");
            } else {
                $success = 'Quotation submitted successfully! ⚠️ However, email notification may have failed. Please contact the customer directly.';
                error_log("⚠️ Email failed but quotation was saved");
            }
            
            // Refresh existing quotation data
            $stmt = $conn->prepare("SELECT * FROM quotations WHERE prescription_id = ? AND pharmacy_id = ?");
            $stmt->execute([$prescription_id, $_SESSION['pharmacy_id']]);
            $existing_quotation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("✅ Quotation submission completed successfully");
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = $e->getMessage();
            error_log("❌ Quotation submission failed: " . $error);
        }
    }
}
*/
?>