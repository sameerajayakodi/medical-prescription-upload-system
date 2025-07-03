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

// Get quotation details
$stmt = $conn->prepare("
    SELECT q.*, p.user_id, p.delivery_address, p.delivery_time, p.created_at as prescription_date,
           pu.pharmacy_name, pu.address as pharmacy_address, pu.contact_no as pharmacy_contact
    FROM quotations q 
    JOIN prescriptions p ON q.prescription_id = p.id 
    JOIN pharmacy_users pu ON q.pharmacy_id = pu.id
    WHERE q.id = ? AND p.user_id = ?
");
$stmt->execute([$quotation_id, $_SESSION['user_id']]);
$quotation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quotation) {
    redirect('dashboard.php');
}

// Get quotation items
$stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$stmt->execute([$quotation_id]);
$quotation_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Quotation - PrescriptionSystem</title>
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

    .quotation-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
    }

    .total-amount {
        font-size: 2rem;
        font-weight: bold;
        color: #667eea;
        text-align: center;
        padding: 20px;
        background: #f8f9ff;
        border-radius: 10px;
        margin: 20px 0;
        border: 2px solid #667eea;
    }

    .status-badge {
        font-size: 1rem;
        padding: 0.5rem 1rem;
        border-radius: 25px;
    }

    .status-pending {
        background-color: #ffeaa7;
        color: #fdcb6e;
    }

    .status-accepted {
        background-color: #00b894;
        color: white;
    }

    .status-rejected {
        background-color: #e17055;
        color: white;
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
                <span class="navbar-text me-3">Welcome, <?php echo $_SESSION['user_name']; ?>!</span>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="quotation-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-file-invoice-dollar"></i> Quotation Details</h1>
                    <p class="mb-0">From <?php echo htmlspecialchars($quotation['pharmacy_name']); ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge status-<?php echo $quotation['status']; ?> fs-6">
                        <?php echo ucfirst($quotation['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Quotation Items -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-pills"></i> Medicines</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Medicine</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quotation_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['drug_name']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>LKR <?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td>LKR <?php echo number_format($item['total_price'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Total Amount -->
                <div class="total-amount">
                    Total Amount: LKR <?php echo number_format($quotation['total_amount'], 2); ?>
                </div>

                <!-- Action Buttons -->
                <?php if ($quotation['status'] == 'pending'): ?>
                <div class="text-center">
                    <a href="accept_quotation.php?id=<?php echo $quotation['id']; ?>"
                        class="btn btn-success btn-lg me-3"
                        onclick="return confirm('Are you sure you want to accept this quotation?')">
                        <i class="fas fa-check"></i> Accept Quotation
                    </a>
                    <a href="reject_quotation.php?id=<?php echo $quotation['id']; ?>" class="btn btn-danger btn-lg"
                        onclick="return confirm('Are you sure you want to reject this quotation?')">
                        <i class="fas fa-times"></i> Reject Quotation
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <!-- Pharmacy Information -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-store"></i> Pharmacy Information</h5>
                    </div>
                    <div class="card-body">
                        <h6><?php echo htmlspecialchars($quotation['pharmacy_name']); ?></h6>
                        <p class="text-muted mb-2">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($quotation['pharmacy_address']); ?>
                        </p>
                        <p class="text-muted mb-2">
                            <i class="fas fa-phone"></i>
                            <?php echo htmlspecialchars($quotation['pharmacy_contact']); ?>
                        </p>
                        <p class="text-muted mb-0">
                            <i class="fas fa-calendar"></i>
                            Quoted on <?php echo date('F d, Y', strtotime($quotation['created_at'])); ?>
                        </p>
                    </div>
                </div>

                <!-- Delivery Information -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-truck"></i> Delivery Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Address:</strong><br>
                            <?php echo htmlspecialchars($quotation['delivery_address']); ?></p>
                        <p><strong>Preferred Time:</strong><br>
                            <?php echo htmlspecialchars($quotation['delivery_time']); ?></p>
                        <p><strong>Prescription Date:</strong><br>
                            <?php echo date('F d, Y', strtotime($quotation['prescription_date'])); ?></p>
                    </div>
                </div>

                <!-- Back Button -->
                <div class="d-grid">
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>