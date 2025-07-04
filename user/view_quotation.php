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
        font-size: 6rem;
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

    .table-container {
        max-height: 400px;
        overflow-y: auto;
    }

    .table-container::-webkit-scrollbar {
        width: 4px;
    }

    .table-container::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .table-container::-webkit-scrollbar-thumb {
        background: #c1c1c1;
    }
    </style>
</head>

<body class="bg-gray-50">


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
    <div class="min-h-screen grid grid-cols-4">
        <!-- Left Side - Quotation Summary (1/4 width) -->
        <div class="bg-gray-100 text-gray-700 flex items-center justify-center p-8">
            <div class="text-center w-full">
                <div class="flex justify-center mb-8">
                    <span class="material-icons large-icon text-green-300">receipt_long</span>
                </div>
                <h2 class="text-2xl font-bold mb-4 text-gray-800">Quotation Summary</h2>
                <p class="text-lg text-gray-600 mb-6">
                    From <?php echo htmlspecialchars($quotation['pharmacy_name']); ?>
                </p>

                <!-- Status -->
                <div class="mb-6">
                    <span class="px-4 py-2 rounded-full text-sm font-medium status-<?php echo $quotation['status']; ?>">
                        <?php echo ucfirst($quotation['status']); ?>
                    </span>
                </div>

                <!-- Total Amount -->
                <div class="bg-white p-6 rounded-lg border mb-6">
                    <div class="text-xs text-gray-500 mb-2">Total Amount</div>
                    <div class="text-3xl font-bold text-blue-600">
                        LKR <?php echo number_format($quotation['total_amount'], 2); ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <?php if ($quotation['status'] == 'pending'): ?>
                <div class="space-y-3">
                    <a href="accept_quotation.php?id=<?php echo $quotation['id']; ?>"
                        class="btn block w-full px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium flex items-center justify-center space-x-2"
                        onclick="return confirm('Are you sure you want to accept this quotation?')">
                        <span class="material-icons">check</span>
                        <span>Accept Quotation</span>
                    </a>
                    <a href="reject_quotation.php?id=<?php echo $quotation['id']; ?>"
                        class="btn block w-full px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium flex items-center justify-center space-x-2"
                        onclick="return confirm('Are you sure you want to reject this quotation?')">
                        <span class="material-icons">close</span>
                        <span>Reject Quotation</span>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Back Button -->
                <div class="mt-6">
                    <a href="dashboard.php"
                        class="btn block w-full px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium flex items-center justify-center space-x-2">
                        <span class="material-icons">arrow_back</span>
                        <span>Back to Dashboard</span>
                    </a>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-1 gap-4 mt-8">
                    <div class="bg-white p-4 rounded-lg border">
                        <div class="text-2xl font-bold text-blue-600"><?php echo count($quotation_items); ?></div>
                        <div class="text-sm text-gray-600">Total Items</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border">
                        <div class="text-xs text-gray-500">Quoted on</div>
                        <div class="text-sm font-medium text-gray-800">
                            <?php echo date('M d, Y', strtotime($quotation['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Details Section (3/4 width) -->
        <div class="bg-white col-span-3 flex flex-col p-6 min-h-screen">
            <div class="w-full h-full flex flex-col space-y-6">
                <!-- Quotation Items Section -->
                <div class="bg-white border border-gray-200 rounded-lg flex flex-col" style="min-height: 50%;">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center space-x-2">
                            <span class="material-icons text-blue-600">medication</span>
                            <span>Medicine Details</span>
                        </h3>
                    </div>

                    <div class="table-container flex-1">
                        <table class="w-full">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Medicine</th>
                                    <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Quantity</th>
                                    <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Unit Price</th>
                                    <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($quotation_items as $item): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($item['drug_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo $item['quantity']; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        LKR <?php echo number_format($item['unit_price'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                        LKR <?php echo number_format($item['total_price'], 2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Information Cards Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Pharmacy Information -->
                    <div class="bg-white border border-gray-200 rounded-lg">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                                <span class="material-icons text-blue-600">local_pharmacy</span>
                                <span>Pharmacy Information</span>
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div class="flex items-start space-x-3">
                                    <span class="material-icons text-gray-400 mt-1">store</span>
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($quotation['pharmacy_name']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <span class="material-icons text-gray-400 mt-1">location_on</span>
                                    <div>
                                        <div class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($quotation['pharmacy_address']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <span class="material-icons text-gray-400 mt-1">phone</span>
                                    <div>
                                        <div class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($quotation['pharmacy_contact']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Information -->
                    <div class="bg-white border border-gray-200 rounded-lg">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                                <span class="material-icons text-blue-600">local_shipping</span>
                                <span>Delivery Information</span>
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div class="flex items-start space-x-3">
                                    <span class="material-icons text-gray-400 mt-1">home</span>
                                    <div>
                                        <div class="font-medium text-gray-700 text-sm">Delivery Address</div>
                                        <div class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($quotation['delivery_address']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <span class="material-icons text-gray-400 mt-1">schedule</span>
                                    <div>
                                        <div class="font-medium text-gray-700 text-sm">Preferred Time</div>
                                        <div class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($quotation['delivery_time']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <span class="material-icons text-gray-400 mt-1">event</span>
                                    <div>
                                        <div class="font-medium text-gray-700 text-sm">Prescription Date</div>
                                        <div class="text-sm text-gray-600">
                                            <?php echo date('M d, Y', strtotime($quotation['prescription_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>