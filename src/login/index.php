<?php
    session_start();
    $error = '';
    $showModal = false; // Flag to keep the modal open if there's an error

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        try {
            // Updated to connect to the correct database: citu_clinic_inventory
            $pdo = new PDO("mysql:host=127.0.0.1;dbname=citu_clinic_inventory;port=3306", "root", "");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Fixed SQL Query: Only search for the exact email/username entered
            $stmt = $pdo->prepare("SELECT * FROM User WHERE email = ?");
            $stmt->execute([$email]); 
            $user = $stmt->fetch();

            // todo change it to hashing to make it more safer
            if ($user && $password === $user['password']) { 
                // Best practice: store the user_id in the session for future database inserts (like Dispensation)
                $_SESSION['user_id'] = $user['user_id']; 
                $_SESSION['user'] = $user['first_name'];
                
                header("Location: ../dashboard/index.php"); 
                exit;
            } else {
                $error = "Invalid credentials.";
                $showModal = true; // Trigger the modal to stay open
            }
        } catch (PDOException $e) {
            $error = "Connection failed. Please check your database.";
            $showModal = true;
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
            overlay.style.display = overlay.style.display === 'none' ? 'flex' : 'none';
        }
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