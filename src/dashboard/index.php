<?php
    session_start();

    // Security check: If they aren't logged in, kick them back to the login page
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login/index.php"); 
        exit;
    }

    // Grab the first name from the session (fallback to 'Admin' just in case)
    $firstName = $_SESSION['user'] ?? 'Admin';

    // Default stats to 0
    $totalStock = 0;
    $lowStock = 0;
    $expiringSoon = 0;

    try {
        // Connect to your database
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=citu_clinic_inventory;port=3306", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Calculate Total Stock (Sum of all batches)
        $stmtTotal = $pdo->query("
            SELECT IFNULL(SUM(quantity_in_stock), 0) AS total 
            FROM MedicineBatch
        ");
        $totalStock = (int) $stmtTotal->fetch()['total'];

        // 2. Calculate Low Stock (Count of medicines where total stock <= reorder_level)
        $stmtLow = $pdo->query("
            SELECT COUNT(*) AS low_count FROM (
                SELECT m.medicine_id
                FROM Medicine m
                LEFT JOIN MedicineBatch mb 
                    ON m.medicine_id = mb.medicine_id
                GROUP BY m.medicine_id, m.reorder_level
                HAVING IFNULL(SUM(mb.quantity_in_stock), 0) <= m.reorder_level
            ) AS low_stock_query
        ");
        $lowStock = (int) $stmtLow->fetch()['low_count'];

        // 3. Calculate Expiring Soon (Count of batches expiring within the next 60 days)
        $stmtExpiring = $pdo->query("
            SELECT COUNT(*) AS expiring_count 
            FROM MedicineBatch 
            WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
            AND quantity_in_stock > 0
        ");
        $expiringSoon = (int) $stmtExpiring->fetch()['expiring_count'];

    } catch (PDOException $e) {
        // If DB fails, show a dash instead of crashing the page
        $totalStock = $lowStock = $expiringSoon = "-"; 
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CIT Clinic Inventory</title>
    <link rel="stylesheet" href="../components/shared.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <?php include '../components/header.php'; ?>

<main class="dashboard-container">
    <h2 class="welcome-text">&nbsp;Welcome, <?php echo htmlspecialchars($firstName); ?>!</h2>
    <hr class="yellow-line">

    <div class="stat-cards">
        <div class="card stat-card total-stock">
            <p>Total Stock</p>
            <h3>
                <?php echo is_numeric($totalStock) ? number_format($totalStock) : $totalStock; ?>
            </h3>
        </div>

        <div class="card stat-card low-stock">
            <p>Low Stock Items</p>
            <h3><?php echo $lowStock; ?></h3>
        </div>

        <div class="card stat-card expiring-soon">
            <p>Expiring Soon (60 Days)</p>
            <h3><?php echo $expiringSoon; ?></h3>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="card current-inventory">
            <h3 class="section-title">Current Inventory</h3>
            <hr class="divider">
            </div>
        
        <div class="card recent-logs">
            <h3 class="section-title">Recent Activity Logs</h3>
            <hr class="divider">
            </div>
    </div>
</main>

</body>
</html>