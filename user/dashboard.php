<?php
require_once '../config/database.php';

if (!isUser()) {
    redirect('../index.php');
}

$database = new Database();
$conn = $database->getConnection();

// Get user's prescriptions with quotation count
$stmt = $conn->prepare("
    SELECT p.*, COUNT(q.id) as quotation_count 
    FROM prescriptions p 
    LEFT JOIN quotations q ON p.id = q.prescription_id 
    WHERE p.user_id = ? 
    GROUP BY p.id 
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's quotations
$stmt = $conn->prepare("
    SELECT q.*, p.created_at as prescription_date, pu.pharmacy_name 
    FROM quotations q 
    JOIN prescriptions p ON q.prescription_id = p.id 
    JOIN pharmacy_users pu ON q.pharmacy_id = pu.id 
    WHERE p.user_id = ? 
    ORDER BY q.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - PrescriptionSystem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background-color: #f8f9fa;
    }

    .dashboard-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px 0;
        margin-bottom: 30px;
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

    .status-badge {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
    }

    .status-pending {
        background-color: #ffeaa7;
        color: #fdcb6e;
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
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-pills"></i> PrescriptionSystem
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Welcome, <?php echo $_SESSION['user_name']; ?>!</span>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-tachometer-alt"></i> User Dashboard</h1>
                    <p class="mb-0">Manage your prescriptions and view quotations</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="upload_prescription.php" class="btn btn-light btn-lg">
                        <i class="fas fa-plus"></i> Upload New Prescription
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-prescription"></i> My Prescriptions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($prescriptions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-prescription-bottle fa-3x text-muted mb-3"></i>
                            <h5>No prescriptions uploaded yet</h5>
                            <p class="text-muted">Upload your first prescription to get started</p>
                            <a href="upload_prescription.php" class="btn btn-custom">
                                <i class="fas fa-plus"></i> Upload Prescription
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Delivery Address</th>
                                        <th>Delivery Time</th>
                                        <th>Status</th>
                                        <th>Quotations</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prescriptions as $prescription): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($prescription['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($prescription['delivery_address']); ?></td>
                                        <td><?php echo htmlspecialchars($prescription['delivery_time']); ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $prescription['status']; ?>">
                                                <?php echo ucfirst($prescription['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $prescription['quotation_count']; ?>
                                                Quote(s)</span>
                                        </td>
                                        <td>
                                            <a href="view_prescription.php?id=<?php echo $prescription['id']; ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($quotations)): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-calculator"></i> Recent Quotations</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Pharmacy</th>
                                        <th>Total Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($quotations, 0, 5) as $quotation): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($quotation['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($quotation['pharmacy_name']); ?></td>
                                        <td>LKR <?php echo number_format($quotation['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $quotation['status']; ?>">
                                                <?php echo ucfirst($quotation['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_quotation.php?id=<?php echo $quotation['id']; ?>"
                                                class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($quotation['status'] == 'pending'): ?>
                                            <a href="accept_quotation.php?id=<?php echo $quotation['id']; ?>"
                                                class="btn btn-sm btn-success ms-1"
                                                onclick="return confirm('Are you sure you want to accept this quotation?')">
                                                <i class="fas fa-check"></i> Accept
                                            </a>
                                            <a href="reject_quotation.php?id=<?php echo $quotation['id']; ?>"
                                                class="btn btn-sm btn-danger ms-1"
                                                onclick="return confirm('Are you sure you want to reject this quotation?')">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>