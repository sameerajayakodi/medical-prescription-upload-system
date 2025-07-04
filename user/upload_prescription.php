<?php
require_once '../config/database.php';

if (!isUser()) {
    redirect('../index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $note = sanitize($_POST['note']);
    $delivery_address = sanitize($_POST['delivery_address']);
    $delivery_time = sanitize($_POST['delivery_time']);
    
    // Validation
    if (empty($delivery_address) || empty($delivery_time)) {
        $error = 'Delivery address and time are required.';
    } elseif (empty($_FILES['prescription_images']['name'][0])) {
        $error = 'At least one prescription image is required.';
    } else {
        $database = new Database();
        $conn = $database->getConnection();
        
        try {
            $conn->beginTransaction();
            
            // Insert prescription
            $stmt = $conn->prepare("INSERT INTO prescriptions (user_id, note, delivery_address, delivery_time) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $note, $delivery_address, $delivery_time]);
            $prescription_id = $conn->lastInsertId();
            
            // Handle file uploads
            $uploaded_files = [];
            $files = $_FILES['prescription_images'];
            
            // Create upload directory if it doesn't exist
            $upload_dir = '../uploads/prescriptions/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] == 0) {
                    $file_name = $files['name'][$i];
                    $file_tmp = $files['tmp_name'][$i];
                    $file_size = $files['size'][$i];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    // Validate file
                    if (!in_array($file_ext, ALLOWED_EXTENSIONS)) {
                        throw new Exception("Invalid file type: $file_name");
                    }
                    
                    if ($file_size > MAX_FILE_SIZE) {
                        throw new Exception("File too large: $file_name");
                    }
                    
                    // Generate unique filename
                    $new_filename = uniqid() . '_' . $prescription_id . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // Insert image record - store the relative path
                        $stmt = $conn->prepare("INSERT INTO prescription_images (prescription_id, image_path) VALUES (?, ?)");
                        $stmt->execute([$prescription_id, $upload_path]);
                        $uploaded_files[] = $upload_path;
                    } else {
                        throw new Exception("Failed to upload file: $file_name");
                    }
                }
            }
            
            if (empty($uploaded_files)) {
                throw new Exception("No files were uploaded successfully.");
            }
            
            $conn->commit();
            $success = 'Prescription uploaded successfully! You will receive quotations from pharmacies soon.';
            
        } catch (Exception $e) {
            $conn->rollBack();
            
            // Clean up uploaded files
            foreach ($uploaded_files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            
            $error = $e->getMessage();
        }
    }
}

// Generate time slots (2-hour intervals)
$time_slots = [];
for ($hour = 8; $hour <= 22; $hour += 2) {
    $start = sprintf('%02d:00', $hour);
    $end = sprintf('%02d:00', min($hour + 2, 24));
    $time_slots[] = "$start - $end";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Prescription - PrescriptionSystem</title>
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

    .file-upload-area {
        border: 2px dashed #3B82F6;
        border-radius: 12px;
        background: #f8faff;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .file-upload-area:hover {
        background: #f0f4ff;
        border-color: #2563eb;
    }

    .file-upload-area.dragover {
        background: #e6f3ff;
        border-color: #1d4ed8;
    }

    /* Ensure no overflow on html and body */
    html,
    body {
        height: 100%;
        overflow: hidden;
    }
    </style>
</head>

<body class="bg-gray-50 h-full">
    <!-- Website Name - Top Left Corner -->
    <div class="absolute top-6 left-6 z-10 flex items-center space-x-2">
        <span class="text-lg font-medium text-gray-900">PrescriptionSystem</span>
    </div>

    <!-- User Profile - Top Right Corner -->
    <div class="absolute top-6 right-6 z-10 flex items-center space-x-4">
        <a href="dashboard.php" class="text-gray-600 hover:text-gray-800 text-sm font-medium">
            Dashboard
        </a>
        <div class="flex items-center space-x-2">
            <span class="material-icons text-gray-600">account_circle</span>
            <span class="text-sm text-gray-700">Welcome, <?php echo $_SESSION['user_name']; ?>!</span>
        </div>
        <a href="../logout.php" class="text-gray-600 hover:text-gray-800 text-sm font-medium">
            Logout
        </a>
    </div>

    <!-- Split Screen Content -->
    <div class="h-screen grid grid-cols-1 md:grid-cols-2 pt-20 pb-4">
        <!-- Left Side - Preview Area -->
        <div class="bg-gray-100 text-gray-700 h-full flex flex-col">
            <!-- Main Upload Section -->
            <div class="flex-grow flex items-center justify-center p-6">
                <div class="text-center max-w-md w-full">
                    <div class="flex justify-center mb-6">
                        <span class="material-icons text-blue-400 text-20xl">local_pharmacy</span>
                    </div>
                    <h1 class="text-3xl font-bold mb-4 text-gray-800">Upload Prescription</h1>
                    <p class="text-base text-gray-600 mb-8">
                        Upload your prescription images and get quotes from multiple pharmacies instantly.
                    </p>

                    <!-- Upload Stats -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white p-4 rounded-lg border text-center shadow-sm">
                            <div class="text-2xl font-bold text-blue-600" id="previewCount">0</div>
                            <div class="text-sm text-gray-600">Images Selected</div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Preview Section at Bottom -->
            <div class="h-1/3 overflow-y-auto border-t border-gray-300 bg-gray-50 p-4">
                <h6 class="text-sm font-medium text-gray-700 mb-3 flex items-center">
                    <span class="material-icons text-gray-600 mr-2">image</span>
                    Uploaded Images (<span id="imageCount">0</span>)
                </h6>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="previewContainer"></div>
            </div>
        </div>

        <!-- Right Side - Form Area -->
        <div class="bg-white flex items-center justify-center p-6 overflow-y-auto">
            <div class="w-full max-w-lg">
                <!-- Alerts -->
                <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-center space-x-2">
                    <span class="material-icons text-red-600 text-sm">error</span>
                    <span class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg flex items-center space-x-2">
                    <span class="material-icons text-green-600 text-sm">check_circle</span>
                    <div class="text-green-700 text-sm">
                        <?php echo htmlspecialchars($success); ?>
                        <div class="mt-2">
                            <a href="dashboard.php" class="text-green-600 hover:text-green-700 font-medium">Go to
                                Dashboard</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Form -->
                <div class="bg-white rounded-lg p-6">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm" class="space-y-4">
                        <!-- File Upload Section -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prescription Images</label>
                            <div class="file-upload-area p-4 text-center" id="fileUploadArea">
                                <div class="flex justify-center mb-2">
                                    <span class="material-icons text-blue-500 text-3xl">add_photo_alternate</span>
                                </div>
                                <h5 class="text-base font-medium text-gray-800 mb-1">Drop files here or click to browse
                                </h5>
                                <p class="text-xs text-gray-600 mb-1">JPG, PNG, PDF (Max 5MB each)</p>
                                <p class="text-xs text-gray-500">Maximum 5 images</p>
                            </div>
                            <input type="file" name="prescription_images[]" id="fileInput" multiple
                                accept=".jpg,.jpeg,.png,.pdf" class="hidden">
                            <div class="text-xs text-gray-600 mt-2">
                                Selected files: <span id="fileCount">0</span>
                            </div>
                        </div>

                        <!-- Additional Notes -->
                        <div>
                            <label for="note" class="block text-sm font-medium text-gray-700 mb-1">Additional Notes
                                (Optional)</label>
                            <textarea id="note" name="note" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                placeholder="Any special instructions or notes for the pharmacy"><?php echo isset($_POST['note']) ? htmlspecialchars($_POST['note']) : ''; ?></textarea>
                        </div>

                        <!-- Delivery Address -->
                        <div>
                            <label for="delivery_address" class="block text-sm font-medium text-gray-700 mb-1">Delivery
                                Address</label>
                            <textarea id="delivery_address" name="delivery_address" rows="2" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                placeholder="Enter your delivery address"><?php echo isset($_POST['delivery_address']) ? htmlspecialchars($_POST['delivery_address']) : ''; ?></textarea>
                        </div>

                        <!-- Delivery Time -->
                        <div>
                            <label for="delivery_time" class="block text-sm font-medium text-gray-700 mb-1">Preferred
                                Delivery Time</label>
                            <select id="delivery_time" name="delivery_time" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                <option value="">Select delivery time slot</option>
                                <?php foreach ($time_slots as $slot): ?>
                                <option value="<?php echo $slot; ?>"
                                    <?php echo (isset($_POST['delivery_time']) && $_POST['delivery_time'] == $slot) ? 'selected' : ''; ?>>
                                    <?php echo $slot; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Submit Buttons - Two buttons in one row -->
                        <div class="pt-4 flex space-x-3">
                            <button type="submit" id="submitBtn"
                                class="btn flex-1 px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm flex items-center justify-center space-x-2">
                                <span class="material-icons text-sm">cloud_upload</span>
                                <span>Upload Prescription</span>
                            </button>
                            <a href="dashboard.php"
                                class="btn flex-1 px-4 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium text-sm flex items-center justify-center space-x-2">
                                <span class="material-icons text-sm">arrow_back</span>
                                <span>Back to Dashboard</span>
                            </a>
                        </div>
                    </form>


                </div>
            </div>
        </div>
    </div>

    <script>
    // Simple, working file upload implementation
    const fileInput = document.getElementById('fileInput');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const previewContainer = document.getElementById('previewContainer');
    const submitBtn = document.getElementById('submitBtn');

    // Click to select files
    fileUploadArea.addEventListener('click', function() {
        fileInput.click();
    });

    // Drag and drop
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });

    fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });

    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');

        const files = e.dataTransfer.files;
        fileInput.files = files;
        updatePreviews();
    });

    // File input change
    fileInput.addEventListener('change', function() {
        updatePreviews();
    });

    function updatePreviews() {
        const files = fileInput.files;
        previewContainer.innerHTML = '';

        // Update counters
        document.getElementById('fileCount').textContent = files.length;
        document.getElementById('previewCount').textContent = files.length;
        document.getElementById('imageCount').textContent = files.length;

        // Create previews
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const preview = document.createElement('div');
            preview.className = 'relative w-full h-56 border-2 border-gray-300 overflow-hidden bg-white';

            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.className = 'w-full h-full object-cover';
                preview.appendChild(img);
            } else {
                preview.className += ' flex items-center justify-center bg-gray-100';
                const icon = document.createElement('span');
                icon.className = 'material-icons text-red-500 text-6xl';
                icon.textContent = 'picture_as_pdf';
                preview.appendChild(icon);
            }

            previewContainer.appendChild(preview);
        }
    }

    // Form validation
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        if (!fileInput.files || fileInput.files.length === 0) {
            e.preventDefault();
            alert('Please select at least one prescription image.');
            return;
        }

        submitBtn.innerHTML =
            '<span class="material-icons animate-spin">refresh</span><span>Uploading...</span>';
        submitBtn.disabled = true;
    });
    </script>
</body>

</html>