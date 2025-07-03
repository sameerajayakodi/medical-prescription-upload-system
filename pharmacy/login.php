<?php
require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        $database = new Database();
        $conn = $database->getConnection();

        $stmt = $conn->prepare("SELECT id, pharmacy_name, email, password FROM pharmacy_users WHERE email = ?");
        $stmt->execute([$email]);
        $pharmacy = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pharmacy && password_verify($password, $pharmacy['password'])) {
            $_SESSION['pharmacy_id'] = $pharmacy['id'];
            $_SESSION['pharmacy_name'] = $pharmacy['pharmacy_name'];
            $_SESSION['pharmacy_email'] = $pharmacy['email'];
            redirect('dashboard.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Login - PrescriptionSystem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
    }

    .login-container {
        background: white;
        border-radius: 15px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        padding: 40px;
        max-width: 400px;
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
            <div class="col-md-6">
                <div class="login-container">
                    <div class="text-center mb-4">
                        <h2><i class="fas fa-store"></i> Pharmacy Login</h2>
                        <p class="text-muted">Sign in to your pharmacy account</p>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-custom">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </button>
                        </div>
                    </form>

                    <div class="text-center">
                        <p class="mb-0">Don't have an account? <a href="register.php">Register here</a></p>
                        <p class="mt-2"><a href="../index.php">Back to Home</a></p>
                    </div>

                    <div class="mt-4 p-3 bg-light rounded">
                        <small class="text-muted">
                            <strong>Demo Account:</strong><br>
                            Email: pharmacy@medicare.com<br>
                            Password: password
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>