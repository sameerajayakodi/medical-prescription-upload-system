<?php
require_once 'config/database.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Prescription System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .material-icons {
        font-size: 24px;
    }

    .large-icon {
        font-size: 12rem;
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
   
    <?php if (!isLoggedIn()): ?>
    <div class="split-section grid grid-cols-1 md:grid-cols-2">
      
        <div class="bg-white text-gray-700 flex items-center justify-center p-8">
            <div class="text-center max-w-md">
                <div class="flex justify-center mb-8">
                    <span class="material-icons large-icon text-blue-300">person</span>
                </div>
                <h1 class="text-4xl font-bold mb-6 text-gray-800">For Patients</h1>
                <p class="text-xl mb-8 text-gray-600">
                    Upload your prescription and get quotes from multiple pharmacies.
                    Have your medicines delivered to your doorstep.
                </p>
                <div class="space-y-6">
                    <a href="user/register.php"
                        class="btn block w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-base">
                        Register as Patient
                    </a>
                    <a href="user/login.php"
                        class="btn block w-full px-6 py-3 bg-white hover:bg-gray-100 text-blue-600 rounded-lg font-medium text-base border-2 border-blue-600">
                        Login
                    </a>
                </div>
            </div>
        </div>

       
        <div class="bg-gray-100 text-gray-700 flex items-center justify-center p-8">
            <div class="text-center max-w-md">
                <div class="flex justify-center mb-8">
                    <span class="material-icons large-icon text-green-300">local_pharmacy</span>
                </div>
                <h1 class="text-4xl font-bold mb-6 text-gray-800">For Pharmacies</h1>
                <p class="text-xl mb-8 text-gray-600">
                    View prescription requests and provide competitive quotes to customers.
                    Grow your business with our platform.
                </p>
                <div class="space-y-6">
                    <a href="pharmacy/register.php"
                        class="btn block w-full px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium text-base">
                        Register as Pharmacy
                    </a>
                    <a href="pharmacy/login.php"
                        class="btn block w-full px-6 py-3 bg-white hover:bg-gray-100 text-green-600 rounded-lg font-medium text-base border-2 border-green-600">
                        Login
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>

    <div class="split-section flex items-center justify-center bg-gray-100">
        <div class="text-center max-w-md bg-white p-12 rounded-lg shadow-lg">
            <div class="flex justify-center mb-6">
                <span class="material-icons text-6xl text-blue-600">home</span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-4">Welcome Back!</h1>
            <p class="text-gray-600 mb-8">Continue to your dashboard to manage your prescriptions.</p>
            <?php if (isUser()): ?>
            <a href="user/dashboard.php"
                class="btn w-full px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold text-lg">
                Go to Dashboard
            </a>
            <?php else: ?>
            <a href="pharmacy/dashboard.php"
                class="btn w-full px-8 py-4 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold text-lg">
                Go to Dashboard
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</body>

</html>