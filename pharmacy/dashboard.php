<?php
require_once '../config/database.php';

if (!isPharmacy()) {
    redirect('../index.php');
}

$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("
    SELECT p.*, u.name as user_name, u.contact_no as user_contact,
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

$stmt = $conn->prepare("
    SELECT q.*, p.created_at as prescription_date, u.name as user_name 
    FROM quotations q 
    JOIN prescriptions p ON q.prescription_id = p.id 
    JOIN users u ON p.user_id = u.id 
    WHERE q.pharmacy_id = ? 
    ORDER BY q.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['pharmacy_id']]);
$quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT COUNT(*) FROM quotations WHERE pharmacy_id = ?");
$stmt->execute([$_SESSION['pharmacy_id']]);
$total_quotations = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM quotations WHERE pharmacy_id = ? AND status = 'accepted'");
$stmt->execute([$_SESSION['pharmacy_id']]);
$accepted_quotations = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT SUM(total_amount) FROM quotations WHERE pharmacy_id = ? AND status = 'accepted'");
$stmt->execute([$_SESSION['pharmacy_id']]);
$total_revenue = $stmt->fetchColumn() ?: 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Dashboard - PrescriptionSystem</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .material-icons {
        font-size: 20px;
    }

    .large-icon {
        font-size: 8rem;
    }

    input:focus,
    textarea:focus,
    select:focus {
        outline: none;
        border-color: #3B82F6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn {
        transition: all 0.2s ease;
    }

    .btn:hover {
        transform: translateY(-1px);
    }

    .main-container {
        height: 100vh;
    }

    .table-container-large {
        height: calc(60vh - 24px);
        overflow-y: auto;
    }

    .table-container-small {
        height: calc(40vh - 24px);
        overflow-y: auto;
    }

    .table-container-large::-webkit-scrollbar,
    .table-container-small::-webkit-scrollbar {
        width: 4px;
    }

    .table-container-large::-webkit-scrollbar-track,
    .table-container-small::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .table-container-large::-webkit-scrollbar-thumb,
    .table-container-small::-webkit-scrollbar-thumb {
        background: #c1c1c1;
    }

    .status-pending {
        background-color: #fef3c7;
        color: #d97706;
    }

    .status-quoted {
        background-color: #dbeafe;
        color: #2563eb;
    }

    .status-accepted {
        background-color: #d1fae5;
        color: #059669;
    }

    .status-rejected {
        background-color: #fee2e2;
        color: #dc2626;
    }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Website Name - Top Left Corner -->
    <div class="absolute top-6 left-6 z-10 flex items-center space-x-2">
        <span class="text-lg font-medium text-gray-900">PrescriptionSystem</span>
    </div>

    <!-- Pharmacy Profile - Top Right Corner -->
    <div class="absolute top-6 right-6 z-10 flex items-center space-x-3">
        <div class="flex items-center space-x-2">
            <span class="material-icons text-gray-600">local_pharmacy</span>
            <span class="text-sm text-gray-700">Welcome,
                <?php echo htmlspecialchars($_SESSION['pharmacy_name']); ?>!</span>
        </div>
        <a href="../logout.php" class="text-gray-600 hover:text-gray-800 text-sm font-medium">
            Logout
        </a>
    </div>

    <!-- Main Container -->
    <div class="main-container grid grid-cols-4">
        <!-- Left Side - Dashboard Overview (1/4 width) -->
        <div class="bg-gray-100 text-gray-700 flex items-center justify-center p-8">
            <div class="text-center w-full">
                <div class="flex justify-center mb-8">
                    <span class="material-icons large-icon text-green-300">local_pharmacy</span>
                </div>
                <h2 class="text-2xl font-bold mb-6 text-gray-800">Pharmacy Dashboard</h2>
                <p class="text-lg text-gray-600 mb-8">
                    Manage prescriptions and quotations from your pharmacy.
                </p>



                <!-- Stats -->
                <div class="grid grid-cols-1 gap-4">
                    <div class="bg-white p-4 rounded-lg border">
                        <div class="text-2xl font-bold text-blue-600"><?php echo $total_quotations; ?></div>
                        <div class="text-sm text-gray-600">Total Quotations</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border">
                        <div class="text-2xl font-bold text-green-600"><?php echo $accepted_quotations; ?></div>
                        <div class="text-sm text-gray-600">Accepted Orders</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border">
                        <div class="text-lg font-bold text-purple-600">LKR
                            <?php echo number_format($total_revenue, 0); ?></div>
                        <div class="text-sm text-gray-600">Total Revenue</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Tables Section (3/4 width) -->
        <div class="bg-white col-span-3 flex flex-col p-6 h-screen">
            <div class="w-full h-full flex flex-col space-y-4">
                <!-- Available Prescriptions Section (60% height) -->
                <div class="bg-white  flex flex-col" style="height: 60%;">
                    <div class="p-4 ">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center space-x-2">
                            <span class="material-icons text-blue-600">medical_services</span>
                            <span>Available Prescriptions</span>
                        </h3>
                    </div>

                    <?php if (empty($prescriptions)): ?>
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <span class="material-icons text-gray-400" style="font-size: 4rem;">assignment</span>
                            <h5 class="text-xl font-medium text-gray-700 mt-4 mb-2">No prescriptions available</h5>
                            <p class="text-gray-600">Check back later for new prescriptions to quote</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="table-container-large flex-1 p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 h-full overflow-y-auto">
                            <?php foreach ($prescriptions as $prescription): ?>
                            <div class="prescription-card bg-white border border-gray-200  p-4 h-fit">
                                <div class="flex justify-between items-start mb-3">
                                    <h4 class="font-semibold text-gray-800 flex items-center space-x-1">
                                        <span class="material-icons text-sm text-gray-500">person</span>
                                        <span><?php echo htmlspecialchars($prescription['user_name']); ?></span>
                                    </h4>
                                    <span
                                        class="px-2 py-1 text-xs rounded status-<?php echo $prescription['status']; ?>">
                                        <?php echo ucfirst($prescription['status']); ?>
                                    </span>
                                </div>

                                <div class="space-y-2 mb-4">
                                    <p class="text-sm text-gray-600 flex items-center space-x-1">
                                        <span class="material-icons text-xs">event</span>
                                        <span><?php echo date('M d, Y', strtotime($prescription['created_at'])); ?></span>
                                    </p>
                                    <p class="text-sm text-gray-600 flex items-center space-x-1">
                                        <span class="material-icons text-xs">schedule</span>
                                        <span><?php echo htmlspecialchars($prescription['delivery_time']); ?></span>
                                    </p>
                                    <p class="text-sm text-gray-600 flex items-center space-x-1">
                                        <span class="material-icons text-xs">image</span>
                                        <span><?php echo $prescription['image_count']; ?> image(s)</span>
                                    </p>
                                </div>

                                <?php if ($prescription['note']): ?>
                                <div class="bg-blue-50 text-blue-700 text-xs p-2 rounded mb-3">
                                    <strong>Note:</strong> <?php echo htmlspecialchars($prescription['note']); ?>
                                </div>
                                <?php endif; ?>

                                <a href="view_prescription.php?id=<?php echo $prescription['id']; ?>"
                                    class="btn block w-full text-center bg-green-600 hover:bg-green-700 text-white text-sm py-2 rounded-lg font-medium flex items-center justify-center space-x-1">
                                    <span class="material-icons text-sm">visibility</span>
                                    <span>View & Quote</span>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Quotations Section (40% height) -->
                <div class="bg-white  flex flex-col" style="height: 40%;">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center space-x-2">
                            <span class="material-icons text-blue-600">calculate</span>
                            <span>Recent Quotations</span>
                        </h3>
                    </div>

                    <?php if (empty($quotations)): ?>
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <span class="material-icons text-gray-400" style="font-size: 3rem;">calculate</span>
                            <h5 class="text-lg font-medium text-gray-700 mt-4 mb-2">No quotations yet</h5>
                            <p class="text-gray-600">Your quotations will appear here once submitted</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="table-container-small flex-1">
                        <table class="w-full">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Date</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Customer</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Amount</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($quotations as $quotation): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($quotation['created_at'])); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($quotation['user_name']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">
                                        LKR <?php echo number_format($quotation['total_amount'], 2); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="px-2 py-1 text-xs rounded status-<?php echo $quotation['status']; ?>">
                                            <?php echo ucfirst($quotation['status']); ?>
                                        </span>
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
</body>

</html>