<?php
    session_start();

    // Security check: ensure user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login/login.php");
        exit;
    }

    $medicines = [];

    try {
        // Connect to your database
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=citu_clinic_inventory;port=3306", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL Query to JOIN the three tables: MedicineBatch, Medicine, and Supplier
        $sql = "
            SELECT 
                m.name AS medicine_name,
                m.purpose AS category,
                mb.quantity_in_stock AS stock_level,
                m.reorder_level,
                s.name AS supplier_name,
                mb.expiry_date
            FROM MedicineBatch mb
            JOIN Medicine m ON mb.medicine_id = m.medicine_id
            JOIN Supplier s ON mb.supplier_id = s.supplier_id
            ORDER BY m.name ASC, mb.expiry_date ASC
        ";
        
        $stmt = $pdo->query($sql);
        $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CIT Clinic Inventory - Supplies</title>
    <link rel="stylesheet" href="../components/shared.css">
    <link rel="stylesheet" href="style.css"> 
    
</head>

<body>

    <?php include '../components/header.php'; ?>

<main class="supplies-container">
    <h2 class="page-title">&nbsp;Medicine Supplies</h2>
    <hr class="yellow-line">

    <div class="toolbar">
        <input type="text" placeholder="Search by name or category..." class="search-input">
        <select class="category-select">
            <option>All Categories</option>
            </select>
        <button class="add-btn">Add New Medicine</button>
    </div>

    <div class="table-container">
        <table class="supplies-table">
            <thead>
                <tr>
                    <th>Medicine Name</th>
                    <th>Category</th>
                    <th>Stock Level</th>
                    <th>Reorder Level</th>
                    <th>Supplier</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($medicines)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">No inventory records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($medicines as $med): 
                        // Calculate Status Logic
                        $currentDate = new DateTime();
                        $expiryDate = new DateTime($med['expiry_date']);
                        
                        // Calculate days until expiration
                        $interval = $currentDate->diff($expiryDate);
                        $daysUntilExpiry = $interval->invert ? -$interval->days : $interval->days;

                        $statusClass = '';
                        $statusText = '';
                        $stockClass = '';
                        $rowClass = ''; 

                        // Prioritized Logic: Expired > Low Stock > Expiring Soon > In Stock
                        if ($daysUntilExpiry < 0) {
                            $statusClass = 'expired';
                            $statusText = 'Expired';
                            $stockClass = 'text-danger';
                            $rowClass = 'row-danger'; 
                        } elseif ($med['stock_level'] <= $med['reorder_level']) {
                            // Moved Low Stock above Expiring Soon to match your mockup!
                            $statusClass = 'low-stock';
                            $statusText = 'Low Stock';
                            $stockClass = 'text-danger';
                            $rowClass = 'row-danger'; 
                        } elseif ($daysUntilExpiry <= 60) {
                            $statusClass = 'expiring';
                            $statusText = 'Expiring Soon';
                        } else {
                            $statusClass = 'in-stock';
                            $statusText = 'In Stock';
                        }
                    ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td><strong><?php echo htmlspecialchars($med['medicine_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($med['category']); ?></td>
                            <td class="<?php echo $stockClass; ?>"><?php echo number_format($med['stock_level']); ?></td>
                            <td><?php echo number_format($med['reorder_level']); ?></td>
                            <td><?php echo htmlspecialchars($med['supplier_name']); ?></td>
                            <td><?php echo $expiryDate->format('Y-m-d'); ?></td>
                            <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

</body>
</html>