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

        $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
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
    <title>User Login</title>
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
        font-size: 12rem;
    }

    input:focus {
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

    .split-section {
        height: 100vh;
        overflow: hidden;
    }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Website Name - Top Left Corner -->
    <div class="absolute top-6 left-6 z-10 flex items-center space-x-2">

        <span class="text-lg font-medium text-gray-900">PrescriptionSystem</span>
    </div>

    <!-- Split Screen Content -->
    <div class="split-section grid grid-cols-1 md:grid-cols-2">
        <!-- Left Side - Login Info -->
        <div class="bg-gray-100 text-gray-700 flex items-center justify-center p-8">
            <div class="text-center max-w-md">
                <div class="flex justify-center mb-8">
                    <span class="material-icons large-icon text-blue-300">login</span>
                </div>
                <h1 class="text-4xl font-bold mb-6 text-gray-800">User Login</h1>
                <p class="text-xl text-gray-600">
                    Sign in to your account to upload prescriptions and manage your orders.
                </p>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="bg-white flex items-center justify-center p-8">
            <div class="w-full max-w-lg">
                <!-- Alerts -->
                <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-center space-x-2">
                    <span class="material-icons text-red-600 text-sm">error</span>
                    <span class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <!-- Login Form -->
                <div class="bg-white rounded-lg p-6">
                    <form method="POST" class="space-y-3">
                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email
                                Address</label>
                            <input type="email" id="email" name="email" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" id="password" name="password" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-4">
                            <button type="submit"
                                class="btn block w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-base flex items-center justify-center space-x-2">
                                <span>Login</span>
                            </button>
                        </div>
                    </form>



                    <!-- Footer Links -->
                    <div class="text-center mt-4 space-y-2">
                        <p class="text-sm text-gray-600">
                            Don't have an account?
                            <a href="register.php" class="text-blue-600 hover:text-blue-700 font-medium">Register
                                here</a>
                        </p>
                        <p class="text-sm">
                            <a href="../index.php" class="text-gray-600 hover:text-gray-700">Back to Home</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>