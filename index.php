<?php
require_once 'config/database.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Prescription Upload System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        font-family: 'Arial', sans-serif;
    }

    .hero-section {
        padding: 100px 0;
        text-align: center;
        color: white;
    }

    .card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
    }

    .btn-custom {
        background: linear-gradient(45deg, #667eea, #764ba2);
        border: none;
        padding: 12px 30px;
        border-radius: 25px;
        color: white;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .btn-custom:hover {
        background: linear-gradient(45deg, #764ba2, #667eea);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    }

    .feature-icon {
        font-size: 3rem;
        color: #667eea;
        margin-bottom: 20px;
    }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-pills"></i> PrescriptionSystem
            </a>
            <div class="navbar-nav ms-auto">
                <?php if (isLoggedIn()): ?>
                <?php if (isUser()): ?>
                <a class="nav-link" href="user/dashboard.php">Dashboard</a>
                <?php else: ?>
                <a class="nav-link" href="pharmacy/dashboard.php">Dashboard</a>
                <?php endif; ?>
                <a class="nav-link" href="logout.php">Logout</a>
                <?php else: ?>
                <a class="nav-link" href="user/login.php">User Login</a>
                <a class="nav-link" href="pharmacy/login.php">Pharmacy Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container">
            <h1 class="display-4 mb-4">
                <i class="fas fa-prescription-bottle"></i>
                Medical Prescription Upload System
            </h1>
            <p class="lead mb-5">Upload your prescription, get quotes from pharmacies, and have your medicines delivered
                to your doorstep.</p>

            <?php if (!isLoggedIn()): ?>
            <div class="row justify-content-center">
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <h5 class="card-title">For Patients</h5>
                            <p class="card-text">Upload your prescription and get quotes from multiple pharmacies.</p>
                            <a href="user/register.php" class="btn btn-custom me-2">Register</a>
                            <a href="user/login.php" class="btn btn-outline-primary">Login</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon">
                                <i class="fas fa-store"></i>
                            </div>
                            <h5 class="card-title">For Pharmacies</h5>
                            <p class="card-text">View prescriptions and provide competitive quotes to customers.</p>
                            <a href="pharmacy/register.php" class="btn btn-custom me-2">Register</a>
                            <a href="pharmacy/login.php" class="btn btn-outline-primary">Login</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title text-dark">Welcome back!</h5>
                            <p class="card-text text-muted">Continue to your dashboard to manage your prescriptions.</p>
                            <?php if (isUser()): ?>
                            <a href="user/dashboard.php" class="btn btn-custom">Go to Dashboard</a>
                            <?php else: ?>
                            <a href="pharmacy/dashboard.php" class="btn btn-custom">Go to Dashboard</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-4 text-center mb-4">
                    <div class="feature-icon">
                        <i class="fas fa-upload"></i>
                    </div>
                    <h5>Easy Upload</h5>
                    <p>Simply upload your prescription images and provide delivery details.</p>
                </div>
                <div class="col-md-4 text-center mb-4">
                    <div class="feature-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <h5>Get Quotes</h5>
                    <p>Receive detailed quotes from verified pharmacies in your area.</p>
                </div>
                <div class="col-md-4 text-center mb-4">
                    <div class="feature-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h5>Fast Delivery</h5>
                    <p>Choose your preferred delivery time and get medicines delivered.</p>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>