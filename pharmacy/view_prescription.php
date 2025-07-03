<?php
require_once '../config/database.php';

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
    SELECT p.*, u.name as user_name, u.contact_no as user_contact
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
    
    try {
        $conn->beginTransaction();
        
        $total_amount = 0;
        $quotation_items = [];
        
        for ($i = 0; $i < count($drugs); $i++) {
            if (!empty($drugs[$i]) && !empty($quantities[$i]) && !empty($unit_prices[$i])) {
                $drug = sanitize($drugs[$i]);
                $quantity = floatval($quantities[$i]);
                $unit_price = floatval($unit_prices[$i]);
                $item_total = $quantity * $unit_price;
                
                $quotation_items[] = [
                    'drug' => $drug,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'total_price' => $item_total
                ];
                
                $total_amount += $item_total;
            }
        }
        
        // Delete existing quotation
        $stmt = $conn->prepare("DELETE qi FROM quotation_items qi JOIN quotations q ON qi.quotation_id = q.id WHERE q.prescription_id = ? AND q.pharmacy_id = ?");
        $stmt->execute([$prescription_id, $_SESSION['pharmacy_id']]);
        
        $stmt = $conn->prepare("DELETE FROM quotations WHERE prescription_id = ? AND pharmacy_id = ?");
        $stmt->execute([$prescription_id, $_SESSION['pharmacy_id']]);
        
        // Insert new quotation
        $stmt = $conn->prepare("INSERT INTO quotations (prescription_id, pharmacy_id, total_amount) VALUES (?, ?, ?)");
        $stmt->execute([$prescription_id, $_SESSION['pharmacy_id'], $total_amount]);
        $quotation_id = $conn->lastInsertId();
        
        // Insert quotation items
        foreach ($quotation_items as $item) {
            $stmt = $conn->prepare("INSERT INTO quotation_items (quotation_id, drug_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$quotation_id, $item['drug'], $item['quantity'], $item['unit_price'], $item['total_price']]);
        }
        
        $stmt = $conn->prepare("UPDATE prescriptions SET status = 'quoted' WHERE id = ?");
        $stmt->execute([$prescription_id]);
        
        $conn->commit();
        $success = 'Quotation submitted successfully!';
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Get existing quotation
$stmt = $conn->prepare("SELECT * FROM quotations WHERE prescription_id = ? AND pharmacy_id = ?");
$stmt->execute([$prescription_id, $_SESSION['pharmacy_id']]);
$existing_quotation = $stmt->fetch(PDO::FETCH_ASSOC);

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
    <title>Prescription Details</title>
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

    input:focus {
        outline: none;
        border-color: #3B82F6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .btn {
        transition: all 0.2s;
    }

    .btn:hover {
        transform: translateY(-1px);
    }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="w-full px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <span class="material-icons text-gray-600">medical_services</span>
                    <h1 class="text-xl font-semibold text-gray-900">Prescription Details</h1>
                </div>
                <a href="dashboard.php"
                    class="btn flex items-center space-x-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                    <span class="material-icons">arrow_back</span>
                    <span>Back</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="w-full px-6 py-6">
        <!-- Alerts -->
        <?php if ($error): ?>
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg flex items-center space-x-3">
            <span class="material-icons text-red-600">error</span>
            <span class="text-red-700"><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg flex items-center space-x-3">
            <span class="material-icons text-green-600">check_circle</span>
            <span class="text-green-700"><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Panel -->
            <div class="space-y-6">
                <!-- Patient Information -->
                <div class="card">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <div class="flex items-center space-x-3">
                            <span class="material-icons text-gray-600">person</span>
                            <h2 class="text-lg font-medium text-gray-900">Patient Information</h2>
                        </div>
                    </div>
                    <div class="px-6 py-4">
                        <div class="space-y-4">
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Name</span>
                                <span
                                    class="text-sm text-gray-900"><?php echo htmlspecialchars($prescription['user_name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Contact</span>
                                <span
                                    class="text-sm text-gray-900"><?php echo htmlspecialchars($prescription['user_contact']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Date</span>
                                <span
                                    class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($prescription['created_at'])); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Status</span>
                                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                    <?php echo ucfirst($prescription['status']); ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Address</span>
                                <span
                                    class="text-sm text-gray-900 text-right max-w-xs"><?php echo htmlspecialchars($prescription['delivery_address']); ?></span>
                            </div>
                            <?php if ($prescription['note']): ?>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Notes</span>
                                <span
                                    class="text-sm text-gray-900 text-right max-w-xs"><?php echo htmlspecialchars($prescription['note']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Prescription Images -->
                <div class="card">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <div class="flex items-center space-x-3">
                            <span class="material-icons text-gray-600">image</span>
                            <h2 class="text-lg font-medium text-gray-900">Prescription Images</h2>
                        </div>
                    </div>
                    <div class="px-6 py-4">
                        <?php if (empty($images)): ?>
                        <div class="text-center py-8">
                            <span class="material-icons text-gray-400 text-4xl mb-2">image_not_supported</span>
                            <p class="text-sm text-gray-500">No images uploaded</p>
                        </div>
                        <?php else: ?>
                        <div class="grid grid-cols-2 gap-4">
                            <?php foreach ($images as $image): ?>
                            <?php 
                            $file_ext = strtolower(pathinfo($image['image_path'], PATHINFO_EXTENSION));
                            $correct_path = getImagePath($image['image_path']);
                            ?>
                            <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden">
                                <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png']) && $correct_path): ?>
                                <img src="<?php echo htmlspecialchars($correct_path); ?>" alt="Prescription"
                                    class="w-full h-full object-cover cursor-pointer hover:opacity-90 transition-opacity">
                                <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <span class="material-icons text-gray-400 text-3xl">description</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Panel -->
            <div class="space-y-6">
                <!-- Existing Quotation Alert -->
                <?php if ($existing_quotation): ?>
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center space-x-3 mb-2">
                        <span class="material-icons text-blue-600">info</span>
                        <h3 class="font-medium text-blue-900">Quotation Submitted</h3>
                    </div>
                    <p class="text-sm text-blue-700">Total: LKR
                        <?php echo number_format($existing_quotation['total_amount'], 2); ?></p>
                    <p class="text-xs text-blue-600 mt-1">You can update your quotation below if needed.</p>
                </div>
                <?php endif; ?>

                <!-- Quotation Form -->
                <div class="card">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <div class="flex items-center space-x-3">
                            <span class="material-icons text-gray-600">calculate</span>
                            <h2 class="text-lg font-medium text-gray-900">
                                <?php echo $existing_quotation ? 'Update' : 'Create'; ?> Quotation
                            </h2>
                        </div>
                    </div>
                    <div class="px-6 py-4">
                        <form method="POST" class="space-y-4">
                            <div id="drugsList" class="space-y-4">
                                <?php if (!empty($quotation_items)): ?>
                                <?php foreach ($quotation_items as $item): ?>
                                <div class="drug-row bg-gray-50 p-4 rounded-lg">
                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                        <div class="md:col-span-5">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Medicine</label>
                                            <input type="text" name="drugs[]"
                                                value="<?php echo htmlspecialchars($item['drug_name']); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                                required>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                                            <input type="number" name="quantities[]"
                                                value="<?php echo $item['quantity']; ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                                step="0.01" min="0.01" required>
                                        </div>
                                        <div class="md:col-span-3">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Price
                                                (LKR)</label>
                                            <input type="number" name="unit_prices[]"
                                                value="<?php echo $item['unit_price']; ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                                step="0.01" min="0.01" required>
                                        </div>
                                        <div class="md:col-span-2 flex items-end">
                                            <button type="button"
                                                class="btn w-full px-3 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-md remove-drug">
                                                <span class="material-icons text-sm">delete</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="drug-row bg-gray-50 p-4 rounded-lg">
                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                        <div class="md:col-span-5">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Medicine</label>
                                            <input type="text" name="drugs[]"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                                required>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                                            <input type="number" name="quantities[]"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                                step="0.01" min="0.01" required>
                                        </div>
                                        <div class="md:col-span-3">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Price
                                                (LKR)</label>
                                            <input type="number" name="unit_prices[]"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                                step="0.01" min="0.01" required>
                                        </div>
                                        <div class="md:col-span-2 flex items-end">
                                            <button type="button"
                                                class="btn w-full px-3 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-md remove-drug">
                                                <span class="material-icons text-sm">delete</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex justify-center">
                                <button type="button" id="addDrug"
                                    class="btn flex items-center space-x-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                                    <span class="material-icons">add</span>
                                    <span>Add Medicine</span>
                                </button>
                            </div>

                            <div class="bg-green-50 p-4 rounded-lg text-center">
                                <div class="text-2xl font-bold text-green-800">
                                    Total: LKR <span id="grandTotal">0.00</span>
                                </div>
                            </div>

                            <button type="submit" name="submit_quotation"
                                class="btn w-full flex items-center justify-center space-x-2 px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                                <span class="material-icons">send</span>
                                <span>Submit Quotation</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const drugsList = document.getElementById('drugsList');
    const addDrugBtn = document.getElementById('addDrug');
    const grandTotalElement = document.getElementById('grandTotal');

    addDrugBtn.addEventListener('click', function() {
        const drugRow = document.createElement('div');
        drugRow.className = 'drug-row bg-gray-50 p-4 rounded-lg';
        drugRow.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Medicine</label>
                        <input type="text" name="drugs[]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                               required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                        <input type="number" name="quantities[]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                               step="0.01" min="0.01" required>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Price (LKR)</label>
                        <input type="number" name="unit_prices[]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                               step="0.01" min="0.01" required>
                    </div>
                    <div class="md:col-span-2 flex items-end">
                        <button type="button" class="btn w-full px-3 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-md remove-drug">
                            <span class="material-icons text-sm">delete</span>
                        </button>
                    </div>
                </div>
            `;
        drugsList.appendChild(drugRow);
        attachEventListeners();
    });

    function calculateTotal() {
        let total = 0;
        const rows = document.querySelectorAll('.drug-row');

        rows.forEach(row => {
            const qty = parseFloat(row.querySelector('input[name="quantities[]"]').value) || 0;
            const price = parseFloat(row.querySelector('input[name="unit_prices[]"]').value) || 0;
            total += qty * price;
        });

        grandTotalElement.textContent = total.toFixed(2);
    }

    function attachEventListeners() {
        document.querySelectorAll('.remove-drug').forEach(btn => {
            btn.addEventListener('click', function() {
                if (document.querySelectorAll('.drug-row').length > 1) {
                    btn.closest('.drug-row').remove();
                    calculateTotal();
                }
            });
        });

        document.querySelectorAll('input[name="quantities[]"], input[name="unit_prices[]"]').forEach(input => {
            input.addEventListener('input', calculateTotal);
        });
    }

    attachEventListeners();
    calculateTotal();
    </script>
</body>

</html>