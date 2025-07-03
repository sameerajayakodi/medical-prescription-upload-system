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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background-color: #f8f9fa;
    }

    .upload-container {
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        padding: 40px;
        margin: 30px 0;
    }

    .btn-custom {
        background: linear-gradient(45deg, #667eea, #764ba2);
        border: none;
        color: white;
        padding: 12px 30px;
        border-radius: 25px;
        font-weight: bold;
    }

    .btn-custom:hover {
        background: linear-gradient(45deg, #764ba2, #667eea);
        color: white;
    }

    .form-control {
        border-radius: 10px;
        border: 2px solid #eee;
        padding: 12px 15px;
    }

    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .file-upload-area {
        border: 2px dashed #667eea;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        background: #f8f9ff;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .file-upload-area:hover {
        background: #f0f4ff;
        border-color: #764ba2;
    }

    .file-upload-area.dragover {
        background: #e6f3ff;
        border-color: #007bff;
    }

    .preview-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 20px;
    }

    .preview-item {
        position: relative;
        width: 150px;
        height: 150px;
        border: 2px solid #ddd;
        border-radius: 10px;
        overflow: hidden;
    }

    .preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .remove-btn {
        position: absolute;
        top: 5px;
        right: 5px;
        background: rgba(255, 0, 0, 0.8);
        color: white;
        border: none;
        border-radius: 50%;
        width: 25px;
        height: 25px;
        font-size: 12px;
        cursor: pointer;
    }

    .debug-info {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        padding: 15px;
        border-radius: 8px;
        margin: 20px 0;
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

    <div class="container">
        <div class="upload-container">
            <div class="text-center mb-4">
                <h2><i class="fas fa-upload"></i> Upload Prescription</h2>
                <p class="text-muted">Upload your prescription images and provide delivery details</p>
            </div>

            <!-- Debug Info -->
            <div class="debug-info">
                <h5>📁 Upload Directory Info</h5>
                <p><strong>Upload Directory:</strong>
                    <?php echo realpath('uploads/prescriptions/') ?: 'uploads/prescriptions/'; ?></p>
                <p><strong>Directory Exists:</strong>
                    <?php echo file_exists('uploads/prescriptions/') ? 'YES' : 'NO'; ?></p>
                <p><strong>Directory Writable:</strong>
                    <?php echo is_writable('uploads/prescriptions/') ? 'YES' : 'NO'; ?></p>
                <p><strong>Max File Size:</strong> <?php echo ini_get('upload_max_filesize'); ?></p>
                <p><strong>Max Post Size:</strong> <?php echo ini_get('post_max_size'); ?></p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <br><a href="dashboard.php" class="alert-link">Go to Dashboard</a>
                <br><a href="../pharmacy/view_prescription.php?id=<?php echo $prescription_id ?? 'latest'; ?>"
                    class="alert-link">View in Pharmacy (Test)</a>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="mb-4">
                    <label class="form-label">Prescription Images (Max 5 images)</label>
                    <div class="file-upload-area" id="fileUploadArea">
                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                        <h5>Drop files here or click to browse</h5>
                        <p class="text-muted">Supported formats: JPG, PNG, PDF (Max 5MB each)</p>
                        <input type="file" name="prescription_images[]" id="fileInput" multiple
                            accept=".jpg,.jpeg,.png,.pdf" style="display: none;">
                    </div>
                    <div class="preview-container" id="previewContainer"></div>
                </div>

                <div class="mb-3">
                    <label for="note" class="form-label">Additional Notes (Optional)</label>
                    <textarea class="form-control" id="note" name="note" rows="3"
                        placeholder="Any special instructions or notes for the pharmacy"><?php echo isset($_POST['note']) ? $_POST['note'] : ''; ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="delivery_address" class="form-label">Delivery Address</label>
                    <textarea class="form-control" id="delivery_address" name="delivery_address" rows="3" required
                        placeholder="Enter your delivery address"><?php echo isset($_POST['delivery_address']) ? $_POST['delivery_address'] : ''; ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="delivery_time" class="form-label">Preferred Delivery Time</label>
                    <select class="form-control" id="delivery_time" name="delivery_time" required>
                        <option value="">Select delivery time slot</option>
                        <?php foreach ($time_slots as $slot): ?>
                        <option value="<?php echo $slot; ?>"
                            <?php echo (isset($_POST['delivery_time']) && $_POST['delivery_time'] == $slot) ? 'selected' : ''; ?>>
                            <?php echo $slot; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-custom btn-lg">
                        <i class="fas fa-upload"></i> Upload Prescription
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-lg ms-3">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const fileInput = document.getElementById('fileInput');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const previewContainer = document.getElementById('previewContainer');
    let selectedFiles = [];

    fileUploadArea.addEventListener('click', () => fileInput.click());

    fileUploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileUploadArea.classList.add('dragover');
    });

    fileUploadArea.addEventListener('dragleave', () => {
        fileUploadArea.classList.remove('dragover');
    });

    fileUploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        fileUploadArea.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    function handleFiles(files) {
        if (selectedFiles.length + files.length > 5) {
            alert('Maximum 5 files allowed');
            return;
        }

        for (let file of files) {
            if (file.size > 5 * 1024 * 1024) {
                alert(`File ${file.name} is too large. Maximum size is 5MB.`);
                continue;
            }

            if (!['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'].includes(file.type)) {
                alert(`File ${file.name} is not supported. Only JPG, PNG, and PDF files are allowed.`);
                continue;
            }

            selectedFiles.push(file);
            createPreview(file);
        }

        updateFileInput();
    }

    function createPreview(file) {
        const previewItem = document.createElement('div');
        previewItem.className = 'preview-item';

        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            previewItem.appendChild(img);
        } else {
            const icon = document.createElement('div');
            icon.innerHTML = '<i class="fas fa-file-pdf fa-3x text-danger"></i><br>' + file.name;
            icon.style.display = 'flex';
            icon.style.flexDirection = 'column';
            icon.style.justifyContent = 'center';
            icon.style.alignItems = 'center';
            icon.style.height = '100%';
            icon.style.fontSize = '12px';
            previewItem.appendChild(icon);
        }

        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-btn';
        removeBtn.innerHTML = '×';
        removeBtn.onclick = () => removeFile(file, previewItem);
        previewItem.appendChild(removeBtn);

        previewContainer.appendChild(previewItem);
    }

    function removeFile(file, previewItem) {
        selectedFiles = selectedFiles.filter(f => f !== file);
        previewContainer.removeChild(previewItem);
        updateFileInput();
    }

    function updateFileInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        fileInput.files = dt.files;
    }

    // Show loading state on form submission
    document.getElementById('uploadForm').addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        submitBtn.disabled = true;

        // Re-enable after 10 seconds in case of error
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 10000);
    });
    </script>
</body>

</html>