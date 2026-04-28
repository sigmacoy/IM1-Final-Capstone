<?php
    session_start();
    $error = '';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = $_POST['email'];
        $password = $_POST['password'];

        // Database connection
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=im1_capstone_db;port=3306", "root", "");
        $stmt = $pdo->prepare("SELECT * FROM User WHERE email = ? OR email = ?");
        $stmt->execute([$email, 'admin']); // Allows 'admin' or 'admin@cit.edu'
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user['first_name'];
            header("Location: ../dashboard/index.php"); // Redirect on success
            exit;
        } else {
            $error = "Invalid credentials.";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CIT Clinic Inventory</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="logo-container">
            <img src="../icons/cit-logo.png" alt="CIT Logo" class="logo">
            <h1>CIT-U CLINIC INVENTORY</h1>
        </div>
        <button onclick="toggleLogin()" class="login-btn">Admin Login</button>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h2>PRECISION IN HEALTHCARE</h2>
            <p>Managing the medical resources of Cebu Institute of Technology – University with digital<br>accuracy and real-time efficiency.</p>
            <div class="access-label">ACCESS SYSTEM</div>
        </div>
    </section>

    <section class="features">
        <div class="feature-card">
            <h3>AUTOMATED ALERT</h3>
            <p>Never run out of essential<br>medicine. Get notified instantly<br>when stock levels reach their<br>minimum threshold.</p>
        </div>
        <div class="feature-card">
            <h3>EXPIRY TRACKING</h3>
            <p>Monitor batch dates easily to<br>ensure no expired medicine is<br>ever dispensed to students.</p>
        </div>
        <div class="feature-card">
            <h3>FAST REQUEST</h3>
            <p>Approve and log supply requests<br>digitally. No more manual paper<br>forms or messy filing.</p>
        </div>
    </section>

    <section class="stats">
        <div class="stat-item">
            <h2>450+</h2>
            <p>Item Tracked</p>
        </div>
        <div class="stat-item">
            <h2>0</h2>
            <p>Paper Waste</p>
        </div>
        <div class="stat-item">
            <h2>100%</h2>
            <p>Accuracy</p>
        </div>
    </section>

    <footer>
        <p><strong>Cebu Institute of Technology – University</strong><br>N. Bacalso Avenue, Cebu City, Philippines 6000</p>
        <p class="copyright">© 2026 Clinic Inventory Management System. All Rights Reserved.</p>
    </footer>

    <div id="loginOverlay" class="overlay" style="display: none;">
        <div class="modal">
            <h2>Login</h2>
            <?php if ($error) echo "<p class='error'>$error</p>"; ?>
            <form method="POST" action="">
                <input type="text" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" class="submit-btn">Login</button>
                <button type="button" onclick="toggleLogin()" class="close-btn">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function toggleLogin() {
            const overlay = document.getElementById('loginOverlay');
            overlay.style.display = overlay.style.display === 'none' ? 'flex' : 'none';
        }
    </script>
</body>
</html>