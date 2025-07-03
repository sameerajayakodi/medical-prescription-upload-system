<?php
require_once '../config/database.php';

if (!isPharmacy()) {
    redirect('../index.php');
}

$database = new Database();
$conn = $database->getConnection();

// Get all pending prescriptions
$stmt = $conn->prepare("
    SELECT p.*, u.name as user_name, u.email as user_email, u.contact_no as user_contact,
           COUNT(pi.id) as image_count
    FROM prescriptions p 
    JOIN users u ON p.user_id = u.id 
    LEFT JOIN prescription_images pi ON p.id = pi.prescription_id
    WHERE p.status IN ('pending', 'quoted')
    GROUP BY p.id 
    ORDER BY p.created_at DESC
");
$stmt->execute();
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pharmacy's quotations
$stmt = $conn->prepare("
    SELECT q.*, p.created_at as prescription_date, u.name as user_name 
    FROM quotations q 
    JOIN prescriptions p ON q.prescription_id = p.id 
    JOIN users u ON p.user_id = u.id 
    WHERE q.pharmacy_id = ? 
    ORDER BY q.created_at DESC
");
$stmt->execute([$_SESSION['pharmacy_id']]);
$quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_quotations FROM quotations WHERE pharmacy_id = ?");
$stmt->execute([$_SESSION['pharmacy_id']]);
$total_quotations = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) as accepted_quotations FROM quotations WHERE pharmacy_id = ? AND status = 'accepted'");
$stmt->execute([$_SESSION['pharmacy_id']]);
$accepted_quotations = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT SUM(total_amount) as total_revenue FROM quotations WHERE pharmacy_id = ? AND status = 'accepted'");
$stmt->execute([$_SESSION['pharmacy_id']]);
$total_revenue = $stmt->fetchColumn() ?: 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Dashboard - PrescriptionSystem</title>
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

    .stats-card {
        background: linear-gradient(45deg, #667eea, #764ba2);
        color: white;
        text-align: center;
        padding: 30px;
    }

    .stats-number {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 10px;
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

    .prescription-card {
        transition: transform 0.3s ease;
    }

    .prescription-card:hover {
        transform: translateY(-5px);
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
                <span class="navbar-text me-3">Welcome, <?php echo $_SESSION['pharmacy_name']; ?>!</span>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-store"></i> Pharmacy Dashboard</h1>
                    <p class="mb-0">View prescriptions and manage quotations</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="stats-number"><?php echo $total_quotations; ?></div>
                    <div>Total Quotations</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="stats-number"><?php echo $accepted_quotations; ?></div>
                    <div>Accepted Orders</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="stats-number">LKR <?php echo number_format($total_revenue, 0); ?></div>
                    <div>Total Revenue</div>
                </div>
            </div>
        </div>

        <!-- Available Prescriptions -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-prescription"></i> Available Prescriptions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($prescriptions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-prescription-bottle fa-3x text-muted mb-3"></i>
                            <h5>No prescriptions available</h5>
                            <p class="text-muted">Check back later for new prescriptions to quote</p>
                        </div>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($prescriptions as $prescription): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card prescription-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0">
                                                <?php echo htmlspecialchars($prescription['user_name']); ?></h6>
                                            <span class="badge status-<?php echo $prescription['status']; ?>">
                                                <?php echo ucfirst($prescription['status']); ?>
                                            </span>
                                        </div>

                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('M d, Y', strtotime($prescription['created_at'])); ?>
                                        </p>

                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars(substr($prescription['delivery_address'], 0, 50)); ?>...
                                        </p>

                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-clock"></i>
                                            <?php echo htmlspecialchars($prescription['delivery_time']); ?>
                                        </p>

                                        <p class="text-muted small mb-3">
                                            <i class="fas fa-images"></i> <?php echo $prescription['image_count']; ?>
                                            image(s)
                                        </p>

                                        <?php if ($prescription['note']): ?>
                                        <p class="small text-info mb-3">
                                            <i class="fas fa-sticky-note"></i>
                                            <?php echo htmlspecialchars($prescription['note']); ?>
                                        </p>
                                        <?php endif; ?>

                                        <div class="d-grid gap-2">
                                            <a href="view_prescription.php?id=<?php echo $prescription['id']; ?>"
                                                class="btn btn-custom btn-sm">
                                                <i class="fas fa-eye"></i> View & Quote
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Quotations -->
        <?php if (!empty($quotations)): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-calculator"></i> My Quotations</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($quotations, 0, 10) as $quotation): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($quotation['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($quotation['user_name']); ?></td>
                                        <td>LKR <?php echo number_format($quotation['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $quotation['status']; ?>">
                                                <?php echo ucfirst($quotation['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_quotation.php?id=<?php echo $quotation['id']; ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
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