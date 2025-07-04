<?php
require_once '../config/database.php';
require_once '../includes/email_handler.php';

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
    // The database stores the filename like "6867607891252_1.jpeg"
    // We need to construct the full path to uploads/prescriptions/
    
    // Clean the stored path - remove any leading slashes or path separators
    $filename = trim($stored_path, '/\\');
    
    // Since images are stored in root folder > uploads > prescriptions
    // and this file is in pharmacy folder, we need to go up one level
    $possible_paths = [
        '../../uploads/prescriptions/' . $filename,
        '../../uploads/prescriptions/' . $filename,
        '../' . $stored_path,
        $stored_path,
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // Debug: Let's also check what paths we're trying
    error_log("Image not found. Tried paths: " . implode(", ", $possible_paths));
    error_log("Original stored_path: " . $stored_path);
    
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
        
        // NEW: Send email notification to user
        $emailHandler = new QuotationEmailHandler();
        $emailResult = $emailHandler->sendQuotationEmail($prescription_id, $_SESSION['pharmacy_id']);
        
        if ($emailResult['success']) {
            $success = 'Quotation submitted successfully and email sent to patient!';
        } else {
            $success = 'Quotation submitted successfully, but email failed: ' . $emailResult['message'];
        }
        
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
    <title>Prescription Details - PrescriptionSystem</title>
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

    .image-container {
        position: relative;
        cursor: pointer;
        transition: transform 0.2s ease;
    }

    .image-container:hover {
        transform: scale(1.05);
    }

    .image-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.2s ease;
        border-radius: 0.375rem;
    }

    .image-container:hover .image-overlay {
        opacity: 1;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal img {
        max-width: 90%;
        max-height: 90%;
        border-radius: 0.5rem;
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

    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <img id="modalImage" src="" alt="Prescription">
        <button onclick="closeModal()" class="absolute top-4 right-4 text-white hover:text-gray-300">
            <span class="material-icons text-4xl">close</span>
        </button>
    </div>

    <!-- Main Container -->
    <div class="h-screen grid grid-cols-4">
        <!-- Left Side - Prescription Summary (1/4 width) -->
        <div class="bg-gray-100 text-gray-700 flex items-center justify-center p-8">
            <div class="text-center w-full">
                <div class="flex justify-center mb-6">
                    <span class="material-icons large-icon text-blue-300">medical_services</span>
                </div>
                <h2 class="text-2xl font-bold mb-4 text-gray-800">Prescription</h2>
                <p class="text-lg text-gray-600 mb-6">
                    <?php echo htmlspecialchars($prescription['user_name']); ?>
                </p>

                <!-- Status -->
                <div class="mb-6">
                    <span
                        class="px-4 py-2 rounded-full text-sm font-medium status-<?php echo $prescription['status']; ?>">
                        <?php echo ucfirst($prescription['status']); ?>
                    </span>
                </div>

                <!-- Total -->
                <?php if ($existing_quotation): ?>
                <div class="bg-white p-4 rounded-lg border mb-6">
                    <div class="text-xs text-gray-500 mb-1">Total Amount</div>
                    <div class="text-2xl font-bold text-blue-600">
                        LKR <?php echo number_format($existing_quotation['total_amount'], 2); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Back Button -->
                <a href="dashboard.php"
                    class="btn block w-full px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium flex items-center justify-center space-x-2">
                    <span class="material-icons">arrow_back</span>
                    <span>Back to Dashboard</span>
                </a>

                <!-- Stats -->
                <div class="grid grid-cols-2 gap-3 mt-6">
                    <div class="bg-white p-3 rounded-lg border">
                        <div class="text-lg font-bold text-blue-600"><?php echo count($images); ?></div>
                        <div class="text-xs text-gray-600">Images</div>
                    </div>
                    <div class="bg-white p-3 rounded-lg border">
                        <div class="text-xs text-gray-500">
                            <?php echo date('M d', strtotime($prescription['created_at'])); ?></div>
                        <div class="text-xs text-gray-600">Date</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Main Content (3/4 width) -->
        <div class="bg-white col-span-3 flex flex-col h-screen">
            <!-- Alerts -->
            <?php if ($error): ?>
            <div class="m-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-center space-x-2">
                <span class="material-icons text-red-600 text-sm">error</span>
                <span class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="m-4 p-3 bg-green-50 border border-green-200 rounded-lg flex items-center space-x-2">
                <span class="material-icons text-green-600 text-sm">check_circle</span>
                <span class="text-green-700 text-sm"><?php echo htmlspecialchars($success); ?></span>
            </div>
            <?php endif; ?>

            <div class="flex-1 p-6 grid grid-cols-2 gap-6 h-full">
                <!-- Left Column - Images & Info -->
                <div class="space-y-4">
                    <!-- Images -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4 h-1/2">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center space-x-2">
                            <span class="material-icons text-blue-600">image</span>
                            <span>Images (<?php echo count($images); ?>)</span>
                        </h3>

                        <?php if (empty($images)): ?>
                        <div class="h-full flex items-center justify-center">
                            <div class="text-center">
                                <span class="material-icons text-gray-400 text-3xl">image_not_supported</span>
                                <p class="text-sm text-gray-600 mt-2">No images</p>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="grid grid-cols-2 gap-2 h-5/6">
                            <?php foreach (array_slice($images, 0, 4) as $image): ?>
                            <?php 
                            $file_ext = strtolower(pathinfo($image['image_path'], PATHINFO_EXTENSION));
                            $correct_path = getImagePath($image['image_path']);
                            ?>
                            <div class="bg-gray-100 rounded border aspect-square overflow-hidden image-container"
                                onclick="openModal('<?php echo htmlspecialchars($correct_path); ?>')">
                                <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) && $correct_path): ?>
                                <img src="<?php echo htmlspecialchars($correct_path); ?>" alt="Prescription"
                                    class="w-full h-full object-cover">
                                <div class="image-overlay">
                                    <span class="material-icons text-white text-2xl">zoom_in</span>
                                </div>
                                <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <div class="text-center">
                                        <span class="material-icons text-gray-400 text-2xl">description</span>
                                        <p class="text-xs text-gray-500 mt-1">File not found</p>
                                        <p class="text-xs text-gray-400">
                                            <?php echo htmlspecialchars($image['image_path']); ?></p>
                                        <p class="text-xs text-gray-300 mt-1">Expected:
                                            ../uploads/prescriptions/<?php echo htmlspecialchars($image['image_path']); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Patient Info -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4 h-1/2">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center space-x-2">
                            <span class="material-icons text-blue-600">person</span>
                            <span>Patient Info</span>
                        </h3>

                        <div class="space-y-3">
                            <div>
                                <span class="text-xs text-gray-500">Contact:</span>
                                <div class="text-sm text-gray-900">
                                    <?php echo htmlspecialchars($prescription['user_contact']); ?></div>
                            </div>
                            <div>
                                <span class="text-xs text-gray-500">Address:</span>
                                <div class="text-sm text-gray-900">
                                    <?php echo htmlspecialchars($prescription['delivery_address']); ?></div>
                            </div>
                            <div>
                                <span class="text-xs text-gray-500">Time:</span>
                                <div class="text-sm text-gray-900">
                                    <?php echo htmlspecialchars($prescription['delivery_time']); ?></div>
                            </div>
                            <?php if ($prescription['note']): ?>
                            <div>
                                <span class="text-xs text-gray-500">Note:</span>
                                <div class="text-sm text-gray-900">
                                    <?php echo htmlspecialchars($prescription['note']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Quotation Form -->
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center space-x-2">
                        <span class="material-icons text-blue-600">calculate</span>
                        <span>Quotation</span>
                    </h3>

                    <form method="POST" class="h-5/6 flex flex-col">
                        <div id="drugsList" class="flex-1 space-y-3 overflow-y-auto mb-4">
                            <?php if (!empty($quotation_items)): ?>
                            <?php foreach ($quotation_items as $item): ?>
                            <div class="drug-row bg-gray-50 p-3 rounded border">
                                <div class="grid grid-cols-12 gap-2">
                                    <div class="col-span-5">
                                        <input type="text" name="drugs[]" placeholder="Medicine"
                                            value="<?php echo htmlspecialchars($item['drug_name']); ?>"
                                            class="w-full px-2 py-1 border border-gray-300 rounded text-sm" required>
                                    </div>
                                    <div class="col-span-2">
                                        <input type="number" name="quantities[]" placeholder="Qty"
                                            value="<?php echo $item['quantity']; ?>"
                                            class="w-full px-2 py-1 border border-gray-300 rounded text-sm" step="0.01"
                                            min="0.01" required>
                                    </div>
                                    <div class="col-span-3">
                                        <input type="number" name="unit_prices[]" placeholder="Price"
                                            value="<?php echo $item['unit_price']; ?>"
                                            class="w-full px-2 py-1 border border-gray-300 rounded text-sm" step="0.01"
                                            min="0.01" required>
                                    </div>
                                    <div class="col-span-2">
                                        <button type="button"
                                            class="btn w-full px-2 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded text-sm remove-drug">
                                            <span class="material-icons text-xs">delete</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="drug-row bg-gray-50 p-3 rounded border">
                                <div class="grid grid-cols-12 gap-2">
                                    <div class="col-span-5">
                                        <input type="text" name="drugs[]" placeholder="Medicine"
                                            class="w-full px-2 py-1 border border-gray-300 rounded text-sm" required>
                                    </div>
                                    <div class="col-span-2">
                                        <input type="number" name="quantities[]" placeholder="Qty"
                                            class="w-full px-2 py-1 border border-gray-300 rounded text-sm" step="0.01"
                                            min="0.01" required>
                                    </div>
                                    <div class="col-span-3">
                                        <input type="number" name="unit_prices[]" placeholder="Price"
                                            class="w-full px-2 py-1 border border-gray-300 rounded text-sm" step="0.01"
                                            min="0.01" required>
                                    </div>
                                    <div class="col-span-2">
                                        <button type="button"
                                            class="btn w-full px-2 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded text-sm remove-drug">
                                            <span class="material-icons text-xs">delete</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="space-y-3">
                            <button type="button" id="addDrug"
                                class="btn w-full px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded text-gray-700 text-sm flex items-center justify-center space-x-1">
                                <span class="material-icons text-sm">add</span>
                                <span>Add Medicine</span>
                            </button>

                            <div class="bg-green-50 border border-green-200 p-3 rounded text-center">
                                <div class="text-2xl font-bold text-green-800">
                                    LKR <span id="grandTotal">0.00</span>
                                </div>
                            </div>

                            <button type="submit" name="submit_quotation"
                                class="btn w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded font-medium flex items-center justify-center space-x-2">
                                <span>Submit Quotation</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    const drugsList = document.getElementById('drugsList');
    const addDrugBtn = document.getElementById('addDrug');
    const grandTotalElement = document.getElementById('grandTotal');

    // Image modal functions
    function openModal(imageSrc) {
        document.getElementById('modalImage').src = imageSrc;
        document.getElementById('imageModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('imageModal').classList.remove('active');
    }

    // Close modal when clicking outside the image
    document.getElementById('imageModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    addDrugBtn.addEventListener('click', function() {
        const drugRow = document.createElement('div');
        drugRow.className = 'drug-row bg-gray-50 p-3 rounded border';
        drugRow.innerHTML = `
            <div class="grid grid-cols-12 gap-2">
                <div class="col-span-5">
                    <input type="text" name="drugs[]" placeholder="Medicine"
                        class="w-full px-2 py-1 border border-gray-300 rounded text-sm" required>
                </div>
                <div class="col-span-2">
                    <input type="number" name="quantities[]" placeholder="Qty"
                        class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                        step="0.01" min="0.01" required>
                </div>
                <div class="col-span-3">
                    <input type="number" name="unit_prices[]" placeholder="Price"
                        class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                        step="0.01" min="0.01" required>
                </div>
                <div class="col-span-2">
                    <button type="button"
                        class="btn w-full px-2 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded text-sm remove-drug">
                        <span class="material-icons text-xs">delete</span>
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