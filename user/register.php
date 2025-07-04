<?php
require_once '../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $address = sanitize($_POST['address']);
    $contact_no = sanitize($_POST['contact_no']);
    $date_of_birth = sanitize($_POST['date_of_birth']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

   
    if (empty($name) || empty($email) || empty($address) || empty($contact_no) || empty($date_of_birth) || empty($password)) {
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

     
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Email already registered.';
        } else {
           
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, address, contact_no, date_of_birth, password) VALUES (?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$name, $email, $address, $contact_no, $date_of_birth, $hashed_password])) {
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
    <title>User Registration</title>
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

    input:focus,
    textarea:focus {
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
    }
    </style>
</head>

<body class="bg-gray-50">

    <div class="absolute top-6 left-6 z-10 flex items-center space-x-2">

        <span class="text-lg font-medium text-gray-900">PrescriptionSystem</span>
    </div>


    <div class="split-section grid grid-cols-1 md:grid-cols-2">

        <div class="bg-gra-y text-gray-700 flex items-center justify-center p-8">
            <div class="text-center max-w-md">
                <div class="flex justify-center mb-8">
                    <span class="material-icons large-icon text-blue-300">person_add</span>
                </div>
                <h1 class="text-4xl font-bold mb-6 text-gray-800">User Registration</h1>
                <p class="text-xl text-gray-600">
                    Create your account to upload prescriptions and get quotes from multiple pharmacies.
                </p>
            </div>
        </div>


        <div class="bg-white flex items-center justify-center p-6">
            <div class="w-full max-w-lg">

                <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-center space-x-2">
                    <span class="material-icons text-red-600 text-sm">error</span>
                    <span class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg flex items-center space-x-2">
                    <span class="material-icons text-green-600 text-sm">check_circle</span>
                    <span class="text-green-700 text-sm"><?php echo htmlspecialchars($success); ?></span>
                </div>
                <?php endif; ?>

                <div class="bg-white rounded-lg  p-6">
                    <form method="POST" class="space-y-3">

                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" id="name" name="name" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email
                                Address</label>
                            <input type="email" id="email" name="email" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>


                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea id="address" name="address" rows="2" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>


                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="contact_no" class="block text-sm font-medium text-gray-700 mb-1">Contact
                                    Number</label>
                                <input type="tel" id="contact_no" name="contact_no" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                    value="<?php echo isset($_POST['contact_no']) ? htmlspecialchars($_POST['contact_no']) : ''; ?>">
                            </div>
                            <div>
                                <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of
                                    Birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                    value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                            </div>
                        </div>


                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="password"
                                    class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <input type="password" id="password" name="password" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                            <div>
                                <label for="confirm_password"
                                    class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                        </div>


                        <div class="pt-4">
                            <button type="submit"
                                class="btn block w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-base flex items-center justify-center space-x-2">

                                <span>Register</span>
                            </button>
                        </div>
                    </form>


                    <div class="text-center mt-4 space-y-2">
                        <p class="text-sm text-gray-600">
                            Already have an account?
                            <a href="login.php" class="text-blue-600 hover:text-blue-700 font-medium">Login here</a>
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