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
    <title>Pharmacy Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
    body {
        font-family: 'Inter', sans-serif;
    }

    .material-icons {
        font-size: 20px;
    }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">


    <header class="bg-white shadow-sm border-b py-4 px-6 flex justify-between items-center">
        <h1 class="text-xl font-semibold text-gray-900 flex items-center space-x-2">
            <span class="material-icons text-blue-600">store</span>
            <span>Pharmacy Dashboard</span>
        </h1>
        <div class="text-sm text-gray-600 flex items-center space-x-4">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['pharmacy_name']); ?>!</span>
            <a href="../logout.php" class="hover:text-gray-900">Logout</a>
        </div>
    </header>

    <main class="p-6 space-y-8">
        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-blue-600"><?php echo $total_quotations; ?></div>
                <div class="text-sm text-gray-600 mt-1">Total Quotations</div>
            </div>
            <div class="bg-white rounded-2xl shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-green-600"><?php echo $accepted_quotations; ?></div>
                <div class="text-sm text-gray-600 mt-1">Accepted Orders</div>
            </div>
            <div class="bg-white rounded-2xl shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-purple-600">LKR <?php echo number_format($total_revenue, 0); ?>
                </div>
                <div class="text-sm text-gray-600 mt-1">Total Revenue</div>
            </div>
        </div>

        <!-- Prescriptions -->
        <section class="bg-white rounded-2xl shadow-md">
            <div class="border-b px-6 py-4 flex items-center space-x-2">
                <span class="material-icons text-gray-600">medical_services</span>
                <h2 class="text-lg font-medium text-gray-900">Available Prescriptions</h2>
            </div>
            <div class="p-6">
                <?php if (empty($prescriptions)): ?>
                <div class="text-center py-12">
                    <span class="material-icons text-green-300 text-6xl mb-4">assignment</span>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No prescriptions available</h3>
                    <p class="text-gray-600">Check back later for new prescriptions to quote</p>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($prescriptions as $prescription): ?>
                    <div
                        class="bg-white rounded-2xl shadow hover:shadow-lg transform hover:-translate-y-1 transition p-6">
                        <div class="flex justify-between mb-2">
                            <h3 class="font-semibold text-gray-800">
                                <?php echo htmlspecialchars($prescription['user_name']); ?></h3>
                            <span
                                class="text-xs px-2 py-1 rounded-full bg-<?php echo $prescription['status'] == 'pending' ? 'yellow' : 'blue'; ?>-100 text-<?php echo $prescription['status'] == 'pending' ? 'yellow' : 'blue'; ?>-800">
                                <?php echo ucfirst($prescription['status']); ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-600">Date:
                            <?php echo date('M d, Y', strtotime($prescription['created_at'])); ?></p>
                        <p class="text-sm text-gray-600">Delivery:
                            <?php echo htmlspecialchars($prescription['delivery_time']); ?></p>
                        <p class="text-sm text-gray-600">Images: <?php echo $prescription['image_count']; ?></p>
                        <?php if ($prescription['note']): ?>
                        <div class="bg-blue-50 text-blue-700 text-sm p-2 rounded mt-2">
                            <?php echo htmlspecialchars($prescription['note']); ?>
                        </div>
                        <?php endif; ?>
                        <a href="view_prescription.php?id=<?php echo $prescription['id']; ?>"
                            class="block w-full mt-4 text-center bg-green-600 hover:bg-green-700 text-white text-sm py-2 rounded-lg transition">View
                            & Quote</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Quotations -->
        <?php if (!empty($quotations)): ?>
        <section class="bg-white rounded-2xl shadow-md">
            <div class="border-b px-6 py-4 flex items-center space-x-2">
                <span class="material-icons text-gray-600">calculate</span>
                <h2 class="text-lg font-medium text-gray-900">Recent Quotations</h2>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-700 border-b">
                            <th class="py-2 px-4">Date</th>
                            <th class="py-2 px-4">Customer</th>
                            <th class="py-2 px-4">Amount</th>
                            <th class="py-2 px-4">Status</th>
                            <th class="py-2 px-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotations as $quotation): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-4"><?php echo date('M d, Y', strtotime($quotation['created_at'])); ?>
                            </td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($quotation['user_name']); ?></td>
                            <td class="py-2 px-4">LKR <?php echo number_format($quotation['total_amount'], 2); ?></td>
                            <td class="py-2 px-4">
                                <span
                                    class="px-2 py-1 text-xs rounded-full bg-<?php echo $quotation['status'] == 'accepted' ? 'green' : ($quotation['status'] == 'rejected' ? 'red' : 'blue'); ?>-100 text-<?php echo $quotation['status'] == 'accepted' ? 'green' : ($quotation['status'] == 'rejected' ? 'red' : 'blue'); ?>-800">
                                    <?php echo ucfirst($quotation['status']); ?>
                                </span>
                            </td>
                            <td class="py-2 px-4">
                                <a href="view_quotation.php?id=<?php echo $quotation['id']; ?>"
                                    class="text-blue-600 hover:underline">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </main>
</body>

</html>