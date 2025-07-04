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

    <!-- User Profile - Top Right Corner -->
    <div class="absolute top-6 right-6 z-10 flex items-center space-x-3">
        <div class="flex items-center space-x-2">
            <span class="material-icons text-gray-600">account_circle</span>
            <span class="text-sm text-gray-700">Welcome, <?php echo $_SESSION['user_name']; ?>!</span>
        </div>
        <a href="../logout.php" class="text-gray-600 hover:text-gray-800 text-sm font-medium">
            Logout
        </a>
    </div>

    <!-- Main Container -->
    <div class="main-container grid grid-cols-4">
        <!-- Left Side - Upload Section (1/4 width) -->
        <div class="bg-gray-100 text-gray-700 flex items-center justify-center p-8">
            <div class="text-center w-full">
                <div class="flex justify-center mb-8">
                    <span class="material-icons large-icon text-blue-300">cloud_upload</span>
                </div>
                <h2 class="text-2xl font-bold mb-6 text-gray-800">Upload Prescription</h2>
                <p class="text-lg text-gray-600 mb-8">
                    Upload your prescription to get quotes from multiple pharmacies.
                </p>

                <!-- Upload Button -->
                <a href="upload_prescription.php"
                    class="btn block w-full px-6 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-lg flex items-center justify-center space-x-2">
                    <span class="material-icons">add</span>
                    <span>Upload New Prescription</span>
                </a>

                <!-- Stats -->
                <div class="grid grid-cols-1 gap-4 mt-8">
                    <div class="bg-white p-4 rounded-lg border">
                        <div class="text-2xl font-bold text-blue-600"><?php echo count($prescriptions); ?></div>
                        <div class="text-sm text-gray-600">Total Prescriptions</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border">
                        <div class="text-2xl font-bold text-green-600"><?php echo count($quotations); ?></div>
                        <div class="text-sm text-gray-600">Total Quotations</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Tables Section (3/4 width) -->
        <div class="bg-white col-span-3 flex flex-col p-6 h-screen">
            <div class="w-full h-full flex flex-col space-y-4">
                <!-- My Prescriptions Section (60% height) -->
                <div class="bg-white border border-gray-200 rounded-lg flex flex-col" style="height: 60%;">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center space-x-2">
                            <span class="material-icons text-blue-600">receipt</span>
                            <span>My Prescriptions</span>
                        </h3>
                    </div>

                    <?php if (empty($prescriptions)): ?>
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <span class="material-icons text-gray-400" style="font-size: 4rem;">medication</span>
                            <h5 class="text-xl font-medium text-gray-700 mt-4 mb-2">No prescriptions uploaded yet</h5>
                            <p class="text-gray-600">Upload your first prescription to get started</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="table-container-large flex-1">
                        <table class="w-full">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Date</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Delivery Address
                                    </th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Delivery Time</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Quotations</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($prescriptions as $prescription): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($prescription['created_at'])); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($prescription['delivery_address']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($prescription['delivery_time']); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="px-2 py-1 text-xs rounded status-<?php echo $prescription['status']; ?>">
                                            <?php echo ucfirst($prescription['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                            <?php echo $prescription['quotation_count']; ?> Quote(s)
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="view_prescription.php?id=<?php echo $prescription['id']; ?>"
                                            class="btn inline-flex items-center space-x-1 px-3 py-1 bg-gray-600 hover:bg-gray-700 text-white rounded text-xs">
                                            <span class="material-icons" style="font-size: 14px;">visibility</span>
                                            <span>View</span>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Quotations Section (40% height) -->
                <div class="bg-white border border-gray-200 rounded-lg flex flex-col" style="height: 40%;">
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
                            <p class="text-gray-600">Quotations will appear here once pharmacies respond</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="table-container-small flex-1">
                        <table class="w-full">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Date</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Pharmacy</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Total Amount</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($quotations as $quotation): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($quotation['created_at'])); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($quotation['pharmacy_name']); ?>
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
                                    <td class="px-4 py-3">
                                        <div class="flex items-center space-x-2">
                                            <a href="view_quotation.php?id=<?php echo $quotation['id']; ?>"
                                                class="btn inline-flex items-center space-x-1 px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs">
                                                <span class="material-icons" style="font-size: 14px;">visibility</span>
                                                <span>View</span>
                                            </a>
                                            <?php if ($quotation['status'] == 'pending'): ?>
                                            <a href="accept_quotation.php?id=<?php echo $quotation['id']; ?>"
                                                class="btn inline-flex items-center space-x-1 px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs"
                                                onclick="return confirm('Are you sure you want to accept this quotation?')">
                                                <span class="material-icons" style="font-size: 14px;">check</span>
                                                <span>Accept</span>
                                            </a>
                                            <a href="reject_quotation.php?id=<?php echo $quotation['id']; ?>"
                                                class="btn inline-flex items-center space-x-1 px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs"
                                                onclick="return confirm('Are you sure you want to reject this quotation?')">
                                                <span class="material-icons" style="font-size: 14px;">close</span>
                                                <span>Reject</span>
                                            </a>
                                            <?php endif; ?>
                                        </div>
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