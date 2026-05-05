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

        // 4. Current Inventory
        $currentInventory = [];
        $stmtInventory = $pdo->query("
            SELECT 
                m.medicine_id,
                m.name,
                m.reorder_level,
                IFNULL(SUM(mb.quantity_in_stock), 0) AS total_stock
            FROM Medicine m
            LEFT JOIN MedicineBatch mb 
                ON m.medicine_id = mb.medicine_id
            GROUP BY m.medicine_id, m.name, m.reorder_level
            ORDER BY total_stock ASC
        ");
        $currentInventory = $stmtInventory->fetchAll(PDO::FETCH_ASSOC);

        // 5. Recent Activity Logs
        $recentLogs = [];

        $stmtLogs = $pdo->query("
            SELECT 
                d.dispense_id,
                d.dispense_date,
                d.purpose,
                m.name AS medicine_name,
                di.quantity
            FROM Dispensation d
            JOIN DispensationItem di 
                ON d.dispense_id = di.dispense_id
            JOIN MedicineBatch mb 
                ON di.batch_id = mb.batch_id
            JOIN Medicine m 
                ON mb.medicine_id = m.medicine_id
            ORDER BY d.dispense_date DESC
            LIMIT 5
        ");

        $recentLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        die("DB ERROR: " . $e->getMessage());
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

    <div class="stats-wrapper">
        <div class="stat-cards">
            <div class="card stat-card total-stock" style="border-radius: 20px !important; overflow: hidden;" >
                <p>Total Stock</p>
                <h3><?php echo number_format($totalStock); ?></h3>
            </div>

            <div class="card stat-card low-stock" style="border-radius: 20px !important; overflow: hidden;" >
                <p>Low Stock Items</p>
                <h3><?php echo $lowStock; ?></h3>
            </div>

            <div class="card stat-card expiring-soon" style="border-radius: 20px !important; overflow: hidden;" >
                <p>Expiring Soon (60 Days)</p>
                <h3><?php echo $expiringSoon; ?></h3>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="card current-inventory">
            <h3 class="section-title">Current Inventory</h3>
            <hr class="divider">

            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Stock</th>
                        <th>Reorder</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currentInventory as $item): 
                        $isLow = $item['total_stock'] <= $item['reorder_level'];
                    ?>
                        <tr class="<?php echo $isLow ? 'low-stock-row' : ''; ?>">
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo $item['total_stock']; ?></td>
                            <td><?php echo $item['reorder_level']; ?></td>
                            <td>
                                <button class="dispense-btn">Dispense</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card recent-logs">
            <h3 class="section-title">Recent Activity Logs</h3>
            <hr class="divider">

            <?php if (empty($recentLogs)): ?>
                <p style="text-align:center; color:gray;">No dispensations yet.</p>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($recentLogs as $log): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($log['medicine_name']); ?></strong>
                            — <?php echo $log['quantity']; ?> pcs

                            <?php if (!empty($log['purpose'])): ?>
                                <br>
                                <em><?php echo htmlspecialchars($log['purpose']); ?></em>
                            <?php endif; ?>

                            <br>
                            <small>
                                <?php echo date("M d, Y h:i A", strtotime($log['dispense_date'])); ?>
                            </small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
</main>

</body>
</html>