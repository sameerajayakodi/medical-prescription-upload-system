<?php
require_once '../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pharmacy_name = sanitize($_POST['pharmacy_name']);
    $email = sanitize($_POST['email']);
    $address = sanitize($_POST['address']);
    $contact_no = sanitize($_POST['contact_no']);
    $license_no = sanitize($_POST['license_no']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($pharmacy_name) || empty($email) || empty($address) || empty($contact_no) || empty($license_no) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $database = new Database();
        $conn = $database->getConnection();

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM pharmacy_users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Email already registered.';
        } else {
            // Insert new pharmacy
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO pharmacy_users (pharmacy_name, email, address, contact_no, license_no, password) VALUES (?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$pharmacy_name, $email, $address, $contact_no, $license_no, $hashed_password])) {
                $success = 'Registration successful! You can now login.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Registration - PrescriptionSystem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
    }

    .registration-container {
        background: white;
        border-radius: 15px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        padding: 40px;
        max-width: 600px;
        width: 100%;
    }

    .btn-custom {
        background: linear-gradient(45deg, #667eea, #764ba2);
        border: none;
        padding: 12px 30px;
        border-radius: 25px;
        color: white;
        font-weight: bold;
        width: 100%;
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
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="registration-container">
                    <div class="text-center mb-4">
                        <h2><i class="fas fa-store"></i> Pharmacy Registration</h2>
                        <p class="text-muted">Register your pharmacy to provide quotations</p>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="pharmacy_name" class="form-label">Pharmacy Name</label>
                                <input type="text" class="form-control" id="pharmacy_name" name="pharmacy_name" required
                                    value="<?php echo isset($_POST['pharmacy_name']) ? $_POST['pharmacy_name'] : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                    value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"
                                required><?php echo isset($_POST['address']) ? $_POST['address'] : ''; ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_no" class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="contact_no" name="contact_no" required
                                    value="<?php echo isset($_POST['contact_no']) ? $_POST['contact_no'] : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="license_no" class="form-label">License Number</label>
                                <input type="text" class="form-control" id="license_no" name="license_no" required
                                    value="<?php echo isset($_POST['license_no']) ? $_POST['license_no'] : ''; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password"
                                    name="confirm_password" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-custom">
                                <i class="fas fa-store"></i> Register Pharmacy
                            </button>
                        </div>
                    </form>

                    <div class="text-center">
                        <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
                        <p class="mt-2"><a href="../index.php">Back to Home</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>