<?php
session_start();
require_once '../connection/connection.php';

// Initialize variables
$error = '';
$showModal = false;

// Only process login if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get credentials from the login form
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT * FROM user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        // Verify password using password_verify()
        if ($user && password_verify($password, $user['password'])) {
            // Password is correct - store user data in session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            
            header("Location: ../dashboard/index.php");
            exit;
        } else {
            $error = "Invalid credentials.";
            $showModal = true;
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $error = "Connection failed. Please check the database.";
        $showModal = true;
    }
}

// If there's an error or the modal should be shown, keep it open
$display_modal = ($showModal || $error) ? 'flex' : 'none';
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
            <img src="../images/cit-logo.png" alt="CIT Logo" class="logo">
            <h1>CIT-U CLINIC INVENTORY</h1>
        </div>
        <button onclick="toggleLogin()" class="login-btn">Admin Login</button>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h2>PRECISION IN HEALTHCARE</h2>
            <p>Managing the medical resources of Cebu Institute of Technology – University with digital<br>accuracy and real-time efficiency.</p>
            <button onclick="toggleLogin()" class="access-btn">ACCESS SYSTEM</button>
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
            const currentDisplay = overlay.style.display;
            // Toggle between 'none' and 'flex'
            overlay.style.display = currentDisplay === 'none' ? 'flex' : 'none';
        }

        // If there was an error, make sure the modal is visible
        <?php if ($showModal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('loginOverlay').style.display = 'flex';
        });
        <?php endif; ?>
    </script>
</body>

<script>
function animateNumbers() {
    const statItems = document.querySelectorAll('.stat-item h2');
    
    statItems.forEach(item => {
        const target = parseFloat(item.innerText);
        const isPercent = item.innerText.includes('%');
        const isPlus = item.innerText.includes('+');
        let start = 0;
        let current = start;
        const increment = target / 50; // 50 steps
        let duration = 2000; // 2 seconds
        
        let timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                let display = Math.floor(current);
                if (isPercent) display = display + '%';
                if (isPlus) display = display + '+';
                item.innerText = display;
                clearInterval(timer);
            } else {
                let display = Math.floor(current);
                if (isPercent) display = display + '%';
                if (isPlus) display = display + '+';
                item.innerText = display;
            }
        }, duration / 50);
    });
}

// Trigger when stats come into view
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateNumbers();
            observer.disconnect();
        }
    });
});

const statsSection = document.querySelector('.stats');
if (statsSection) {
    observer.observe(statsSection);
}
</script>

</html>