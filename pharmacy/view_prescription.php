<?php
require_once '../config/database.php';
require_once '../config/email_helper.php';

if (!isPharmacy()) {
    redirect('../index.php');
}

$prescription_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';

if ($prescription_id <= 0) {
    redirect('dashboard.php');
}

$database = new Database();
$conn = $database->getConnection();

// Get prescription details
$stmt = $conn->prepare("
    SELECT p.*, u.name as user_name, u.email as user_email, u.contact_no as user_contact, u.address as user_address
    FROM prescriptions p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.id = ?
");
$stmt->execute([$prescription_id]);
$prescription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prescription) {
    redirect('dashboard.php');
}

// Get prescription images
$stmt = $conn->prepare("SELECT * FROM prescription_images WHERE prescription_id = ?");
$stmt->execute([$prescription_id]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to find correct image path
function getImagePath($stored_path) {
    $filename = basename($stored_path);
    $possible_paths = [
        '../user/' . $stored_path,
        '../' . $stored_path,
        $stored_path,
        '../user/uploads/prescriptions/' . $filename,
        '../../uploads/prescriptions/' . $filename,
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    return null;
}

// Handle quotation submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_quotation'])) {
    $drugs = $_POST['drugs'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $unit_prices = $_POST['unit_prices'] ?? [];
    
    if (empty($drugs) || empty($quantities) || empty($unit_prices)) {
        $error = 'Please add at least one drug to the quotation.';
    } else {
        try {
            $conn->beginTransaction();
            
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
                    
                    $quotation_items[] = [
                        'drug' => $drug,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'total_price' => $item_total
                    ];
                    
                    $total_amount += $item_total;
                }
            }
            
            if (empty($quotation_items)) {
                throw new Exception("Please add at least one valid drug to the quotation.");
            }
            
            // Check if quotation already exists
            $stmt = $conn->prepare("SELECT * FROM quotations WHERE prescription_id = ? AND pharmacy_id = ?");
            $stmt->execute([$prescription_id, $_SESSION['pharmacy_id']]);
            $existing_quotation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete existing quotation if exists
            if ($existing_quotation) {
                $stmt = $conn->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
                $stmt->execute([$existing_quotation['id']]);
                
                $stmt = $conn->prepare("DELETE FROM quotations WHERE id = ?");
                $stmt->execute([$existing_quotation['id']]);
            }
            
            // Insert new quotation
            $stmt = $conn->prepare("INSERT INTO quotations (prescription_id, pharmacy_id, total_amount) VALUES (?, ?, ?)");
            $stmt->execute([$prescription_id, $_SESSION['pharmacy_id'], $total_amount]);
            $quotation_id = $conn->lastInsertId();
            
            // Insert quotation items
            foreach ($quotation_items as $item) {
                $stmt = $conn->prepare("INSERT INTO quotation_items (quotation_id, drug_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$quotation_id, $item['drug'], $item['quantity'], $item['unit_price'], $item['total_price']]);
            }
            
            // Update prescription status
            $stmt = $conn->prepare("UPDATE prescriptions SET status = 'quoted' WHERE id = ?");
            $stmt->execute([$prescription_id]);
            
            $conn->commit();
            
            // Send email notification using the enhanced function
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
            } else {
                $success = 'Quotation submitted successfully! ⚠️ However, email notification may have failed. Please contact the customer directly.';
            }
            
            // Refresh existing quotation data
            $stmt = $conn->prepare("SELECT * FROM quotations WHERE prescription_id = ? AND pharmacy_id = ?");
            $stmt->execute([$prescription_id, $_SESSION['pharmacy_id']]);
            $existing_quotation = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Get existing quotation after potential submission
$stmt = $conn->prepare("SELECT * FROM quotations WHERE prescription_id = ? AND pharmacy_id = ?");
$stmt->execute([$prescription_id, $_SESSION['pharmacy_id']]);
$existing_quotation = $stmt->fetch(PDO::FETCH_ASSOC);

// Get existing quotation items if exists
$quotation_items = [];
if ($existing_quotation) {
    $stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
    $stmt->execute([$existing_quotation['id']]);
    $quotation_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Prescription - PrescriptionSystem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background-color: #f8f9fa;
    }

    .card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }

    .btn-custom {
        background: linear-gradient(45deg, #667eea, #764ba2);
        border: none;
        color: white;
        padding: 10px 20px;
        border-radius: 25px;
        font-weight: bold;
    }

    .btn-custom:hover {
        background: linear-gradient(45deg, #764ba2, #667eea);
        color: white;
    }

    .prescription-image {
        max-width: 100%;
        height: 200px;
        object-fit: cover;
        border-radius: 10px;
        cursor: pointer;
        transition: transform 0.3s ease;
    }

    .prescription-image:hover {
        transform: scale(1.05);
    }

    .quotation-form {
        background: #f8f9ff;
        padding: 20px;
        border-radius: 10px;
        border: 2px solid #667eea;
    }

    .drug-row {
        background: white;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        border: 1px solid #eee;
    }

    .total-display {
        font-size: 1.5rem;
        font-weight: bold;
        color: #667eea;
        text-align: center;
        padding: 20px;
        background: white;
        border-radius: 10px;
        margin: 20px 0;
        border: 2px solid #667eea;
    }

    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px 0;
        margin-bottom: 30px;
        border-radius: 0 0 15px 15px;
    }

    .status-badge {
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
    }

    .status-pending {
        background-color: #ffeaa7;
        color: #d63031;
    }

    .status-quoted {
        background-color: #74b9ff;
        color: white;
    }

    .status-accepted {
        background-color: #00b894;
        color: white;
    }

    .status-rejected {
        background-color: #e17055;
        color: white;
    }

    .patient-info {
        background: linear-gradient(45deg, #667eea, #764ba2);
        color: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .image-gallery {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .image-item {
        position: relative;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    }

    .pdf-preview {
        background: #f8f9fa;
        border: 2px dashed #667eea;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        height: 200px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .quotation-summary {
        background: #e8f4fd;
        border: 2px solid #667eea;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .image-placeholder {
        background: #f8f9fa;
        border: 2px solid #dee2e6;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        height: 200px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        color: #6c757d;
    }

    .alert-auto-dismiss {
        animation: slideIn 0.5s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-pills"></i> PrescriptionSystem
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <span class="navbar-text me-3">Welcome,
                    <?php echo htmlspecialchars($_SESSION['pharmacy_name']); ?>!</span>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-prescription"></i> Prescription Details</h1>
                    <p class="mb-0">Review prescription and provide quotation</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show alert-auto-dismiss" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show alert-auto-dismiss" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <!-- Patient Information -->
                <div class="patient-info">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-user"></i> Patient Information</h5>
                            <p class="mb-1"><strong>Name:</strong>
                                <?php echo htmlspecialchars($prescription['user_name']); ?></p>
                            <p class="mb-1"><strong>Contact:</strong>
                                <?php echo htmlspecialchars($prescription['user_contact']); ?></p>
                            <p class="mb-0"><strong>Email:</strong>
                                <?php echo htmlspecialchars($prescription['user_email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-calendar"></i> Prescription Details</h5>
                            <p class="mb-1"><strong>Date:</strong>
                                <?php echo date('F d, Y', strtotime($prescription['created_at'])); ?></p>
                            <p class="mb-1"><strong>Delivery Time:</strong>
                                <?php echo htmlspecialchars($prescription['delivery_time']); ?></p>
                            <p class="mb-0"><strong>Status:</strong>
                                <span class="badge status-<?php echo $prescription['status']; ?>">
                                    <?php echo ucfirst($prescription['status']); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <h5><i class="fas fa-map-marker-alt"></i> Delivery Address</h5>
                            <p class="mb-1"><?php echo htmlspecialchars($prescription['delivery_address']); ?></p>
                            <?php if ($prescription['note']): ?>
                            <h5><i class="fas fa-sticky-note"></i> Additional Notes</h5>
                            <p class="mb-0"><?php echo htmlspecialchars($prescription['note']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Prescription Images -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-images"></i> Prescription Images (<?php echo count($images); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($images)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-image fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No prescription images uploaded.</p>
                        </div>
                        <?php else: ?>
                        <div class="image-gallery">
                            <?php foreach ($images as $image): ?>
                            <div class="image-item">
                                <?php 
                                        $file_ext = strtolower(pathinfo($image['image_path'], PATHINFO_EXTENSION));
                                        $correct_path = getImagePath($image['image_path']);
                                        
                                        if (in_array($file_ext, ['jpg', 'jpeg', 'png'])): ?>
                                <?php if ($correct_path): ?>
                                <img src="<?php echo htmlspecialchars($correct_path); ?>" class="prescription-image"
                                    alt="Prescription Image" data-bs-toggle="modal"
                                    data-bs-target="#imageModal<?php echo $image['id']; ?>">
                                <?php else: ?>
                                <div class="image-placeholder">
                                    <i class="fas fa-image fa-3x mb-2"></i>
                                    <h6>Image Not Available</h6>
                                    <small><?php echo basename($image['image_path']); ?></small>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <div class="pdf-preview">
                                    <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                    <h6>PDF Document</h6>
                                    <?php if ($correct_path): ?>
                                    <a href="<?php echo htmlspecialchars($correct_path); ?>" target="_blank"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> View PDF
                                    </a>
                                    <?php else: ?>
                                    <small class="text-muted">File not available</small>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Image Modal -->
                            <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png']) && $correct_path): ?>
                            <div class="modal fade" id="imageModal<?php echo $image['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-prescription"></i> Prescription Image
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <img src="<?php echo htmlspecialchars($correct_path); ?>" class="img-fluid"
                                                alt="Prescription Image">
                                        </div>
                                        <div class="modal-footer">
                                            <a href="<?php echo htmlspecialchars($correct_path); ?>" target="_blank"
                                                class="btn btn-primary">
                                                <i class="fas fa-external-link-alt"></i> Open in New Tab
                                            </a>
                                            <button type="button" class="btn btn-secondary"
                                                data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Existing Quotation Summary -->
                <?php if ($existing_quotation): ?>
                <div class="quotation-summary">
                    <h5><i class="fas fa-check-circle text-success"></i> Quotation Submitted</h5>
                    <p class="mb-2"><strong>Total Amount:</strong> LKR
                        <?php echo number_format($existing_quotation['total_amount'], 2); ?></p>
                    <p class="mb-2"><strong>Status:</strong>
                        <span class="badge status-<?php echo $existing_quotation['status']; ?>">
                            <?php echo ucfirst($existing_quotation['status']); ?>
                        </span>
                    </p>
                    <p class="mb-0"><strong>Submitted:</strong>
                        <?php echo date('M d, Y H:i', strtotime($existing_quotation['created_at'])); ?></p>
                    <hr>
                    <p class="text-muted small mb-0">You can update your quotation below if needed.</p>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary" onclick="scrollToQuotation()">
                                <i class="fas fa-calculator"></i>
                                <?php echo $existing_quotation ? 'Update Quotation' : 'Create Quotation'; ?>
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Pharmacy Info -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-store"></i> Your Pharmacy</h5>
                    </div>
                    <div class="card-body">
                        <h6><?php echo htmlspecialchars($_SESSION['pharmacy_name']); ?></h6>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-calendar"></i>
                            Viewing prescription from
                            <?php echo date('F d, Y', strtotime($prescription['created_at'])); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quotation Form -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card" id="quotationSection">
                    <div class="card-header">
                        <h5><i class="fas fa-calculator"></i>
                            <?php echo $existing_quotation ? 'Update Quotation' : 'Create Quotation'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($existing_quotation): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            You have already submitted a quotation for this prescription.
                            You can update it below if needed.
                        </div>
                        <?php endif; ?>

                        <form method="POST" id="quotationForm">
                            <div class="quotation-form">
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <h6><i class="fas fa-pills"></i> Medicine Details</h6>
                                        <p class="text-muted small">Add medicines with their quantities and prices</p>
                                    </div>
                                </div>

                                <div id="drugsList">
                                    <?php if (!empty($quotation_items)): ?>
                                    <?php foreach ($quotation_items as $index => $item): ?>
                                    <div class="drug-row">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label class="form-label">Medicine Name *</label>
                                                <input type="text" name="drugs[]" class="form-control"
                                                    value="<?php echo htmlspecialchars($item['drug_name']); ?>"
                                                    placeholder="e.g., Paracetamol 500mg" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Quantity *</label>
                                                <input type="number" name="quantities[]"
                                                    class="form-control quantity-input"
                                                    value="<?php echo $item['quantity']; ?>" step="0.01" min="0.01"
                                                    placeholder="0.00" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Unit Price (LKR) *</label>
                                                <input type="number" name="unit_prices[]"
                                                    class="form-control price-input"
                                                    value="<?php echo $item['unit_price']; ?>" step="0.01" min="0.01"
                                                    placeholder="0.00" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Total (LKR)</label>
                                                <input type="text" class="form-control total-input"
                                                    value="<?php echo number_format($item['total_price'], 2); ?>"
                                                    readonly>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="button" class="btn btn-danger btn-sm d-block remove-drug">
                                                    <i class="fas fa-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <div class="drug-row">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label class="form-label">Medicine Name *</label>
                                                <input type="text" name="drugs[]" class="form-control"
                                                    placeholder="e.g., Paracetamol 500mg" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Quantity *</label>
                                                <input type="number" name="quantities[]"
                                                    class="form-control quantity-input" step="0.01" min="0.01"
                                                    placeholder="0.00" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Unit Price (LKR) *</label>
                                                <input type="number" name="unit_prices[]"
                                                    class="form-control price-input" step="0.01" min="0.01"
                                                    placeholder="0.00" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Total (LKR)</label>
                                                <input type="text" class="form-control total-input" value="0.00"
                                                    readonly>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="button" class="btn btn-danger btn-sm d-block remove-drug">
                                                    <i class="fas fa-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="text-center mt-3">
                                    <button type="button" class="btn btn-outline-primary" id="addDrug">
                                        <i class="fas fa-plus"></i> Add Medicine
                                    </button>
                                </div>

                                <div class="total-display">
                                    <i class="fas fa-calculator"></i> Total Amount: LKR <span
                                        id="grandTotal">0.00</span>
                                </div>

                                <div class="text-center">
                                    <button type="submit" name="submit_quotation" class="btn btn-custom btn-lg">
                                        <i class="fas fa-paper-plane"></i>
                                        <?php echo $existing_quotation ? 'Update Quotation' : 'Send Quotation'; ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-lg ms-2"
                                        onclick="resetForm()">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const drugsList = document.getElementById('drugsList');
        const addDrugBtn = document.getElementById('addDrug');
        const grandTotalElement = document.getElementById('grandTotal');

        // Add drug row
        addDrugBtn.addEventListener('click', function() {
            const drugRow = document.createElement('div');
            drugRow.className = 'drug-row';
            drugRow.innerHTML = `
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Medicine Name *</label>
                            <input type="text" name="drugs[]" class="form-control" placeholder="e.g., Paracetamol 500mg" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Quantity *</label>
                            <input type="number" name="quantities[]" class="form-control quantity-input" step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Unit Price (LKR) *</label>
                            <input type="number" name="unit_prices[]" class="form-control price-input" step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Total (LKR)</label>
                            <input type="text" class="form-control total-input" value="0.00" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-danger btn-sm d-block remove-drug">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                `;
            drugsList.appendChild(drugRow);
            attachEventListeners();
        });

        // Calculate totals
        function calculateTotal(row) {
            const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
            const unitPrice = parseFloat(row.querySelector('.price-input').value) || 0;
            const total = quantity * unitPrice;
            row.querySelector('.total-input').value = total.toFixed(2);
            calculateGrandTotal();
        }

        function calculateGrandTotal() {
            let grandTotal = 0;
            document.querySelectorAll('.total-input').forEach(input => {
                grandTotal += parseFloat(input.value) || 0;
            });
            grandTotalElement.textContent = grandTotal.toFixed(2);
        }

        // Attach event listeners
        function attachEventListeners() {
            // Remove drug buttons
            document.querySelectorAll('.remove-drug').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (document.querySelectorAll('.drug-row').length > 1) {
                        btn.closest('.drug-row').remove();
                        calculateGrandTotal();
                    } else {
                        alert('At least one medicine is required for the quotation.');
                    }
                });
            });

            // Quantity and price inputs
            document.querySelectorAll('.quantity-input, .price-input').forEach(input => {
                input.addEventListener('input', function() {
                    calculateTotal(this.closest('.drug-row'));
                });
            });

            // Add input validation
            document.querySelectorAll('.quantity-input, .price-input').forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.value && parseFloat(this.value) <= 0) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
            });
        }

        // Initial setup
        attachEventListeners();
        calculateGrandTotal();

        // Form validation
        document.getElementById('quotationForm').addEventListener('submit', function(e) {
            let isValid = true;
            const drugs = document.querySelectorAll('input[name="drugs[]"]');
            const quantities = document.querySelectorAll('input[name="quantities[]"]');
            const prices = document.querySelectorAll('input[name="unit_prices[]"]');

            // Check if at least one complete row is filled
            let hasCompleteRow = false;
            for (let i = 0; i < drugs.length; i++) {
                if (drugs[i].value.trim() && quantities[i].value && prices[i].value) {
                    if (parseFloat(quantities[i].value) > 0 && parseFloat(prices[i].value) > 0) {
                        hasCompleteRow = true;
                        break;
                    }
                }
            }

            if (!hasCompleteRow) {
                e.preventDefault();
                alert('Please add at least one complete medicine entry with valid quantity and price.');
                return false;
            }

            // Validate individual fields
            quantities.forEach(input => {
                if (input.value && parseFloat(input.value) <= 0) {
                    input.classList.add('is-invalid');
                    isValid = false;
                }
            });

            prices.forEach(input => {
                if (input.value && parseFloat(input.value) <= 0) {
                    input.classList.add('is-invalid');
                    isValid = false;
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fix the validation errors and try again.');
                return false;
            }

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;

            // Allow form submission
            return true;
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-auto-dismiss');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
    });

    // Utility functions
    function scrollToQuotation() {
        document.getElementById('quotationSection').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function resetForm() {
        if (confirm('Are you sure you want to reset the quotation form? This will clear all entered data.')) {
            document.getElementById('quotationForm').reset();

            // Keep only one drug row
            const drugsList = document.getElementById('drugsList');
            const drugRows = drugsList.querySelectorAll('.drug-row');

            for (let i = drugRows.length - 1; i > 0; i--) {
                drugRows[i].remove();
            }

            // Clear the remaining row
            const remainingRow = drugsList.querySelector('.drug-row');
            remainingRow.querySelectorAll('input').forEach(input => {
                if (input.classList.contains('total-input')) {
                    input.value = '0.00';
                } else {
                    input.value = '';
                }
                input.classList.remove('is-invalid');
            });

            // Reset total
            document.getElementById('grandTotal').textContent = '0.00';
        }
    }

    // Prevent double submission
    let isSubmitting = false;
    document.getElementById('quotationForm').addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }
        isSubmitting = true;
    });
    </script>
</body>

</html>