<?php
    session_start();

    // Security check
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login/login.php"); 
        exit;
    }

    $firstName = $_SESSION['user'] ?? 'Admin';
    $userId = $_SESSION['user_id']; 

    $totalStock = 0; $lowStock = 0; $expiringSoon = 0;
    $inventorySnapshot = []; $recentLogs = []; $availableMedicines = [];
    $message = '';

    try {
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=citu_clinic_inventory;port=3306", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // --- HANDLE DISPENSATION (FEFO LOGIC) ---
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'dispense') {
            try {
                $pdo->beginTransaction();

                $pFirst = trim($_POST['patient_first']);
                $pLast = trim($_POST['patient_last']);
                $pType = $_POST['patient_type'];
                $purpose = trim($_POST['purpose']);
                $medicineIds = $_POST['medicine_id']; 
                $quantities = $_POST['quantity'];     

                // 1. Check if patient exists by First Name, Last Name, and Type
                $stmtPat = $pdo->prepare("SELECT patient_id FROM Patient WHERE first_name = ? AND last_name = ? AND patient_type = ?");
                $stmtPat->execute([$pFirst, $pLast, $pType]);
                $patient = $stmtPat->fetch();

                if ($patient) {
                    $patientId = $patient['patient_id'];
                } else {
                    $stmtNewPat = $pdo->prepare("INSERT INTO Patient (first_name, last_name, patient_type) VALUES (?, ?, ?)");
                    $stmtNewPat->execute([$pFirst, $pLast, $pType]);
                    $patientId = $pdo->lastInsertId();
                }

                // 2. Create the main Dispensation Record
                $stmtDisp = $pdo->prepare("INSERT INTO Dispensation (user_id, patient_id, dispense_date, purpose) VALUES (?, ?, NOW(), ?)");
                $stmtDisp->execute([$userId, $patientId, $purpose]);
                $dispenseId = $pdo->lastInsertId();

                // 3. FEFO Logic: Loop through requested medicines
                for ($i = 0; $i < count($medicineIds); $i++) {
                    $medId = $medicineIds[$i];
                    $qtyNeeded = (int)$quantities[$i];

                    if ($qtyNeeded <= 0) continue;

                    // Fetch batches with stock, ordered by closest expiry date! (IGNORES EXPIRED STOCK)
                    $stmtBatches = $pdo->prepare("
                        SELECT batch_id, quantity_in_stock 
                        FROM MedicineBatch 
                        WHERE medicine_id = ? 
                          AND quantity_in_stock > 0 
                          AND expiry_date >= CURDATE()
                        ORDER BY expiry_date ASC
                    ");
                    $stmtBatches->execute([$medId]);
                    $batches = $stmtBatches->fetchAll();

                    foreach ($batches as $batch) {
                        if ($qtyNeeded <= 0) break; 

                        $batchId = $batch['batch_id'];
                        $availableInBatch = (int)$batch['quantity_in_stock'];
                        $qtyToTake = min($qtyNeeded, $availableInBatch);

                        $stmtUpdateBatch = $pdo->prepare("UPDATE MedicineBatch SET quantity_in_stock = quantity_in_stock - ? WHERE batch_id = ?");
                        $stmtUpdateBatch->execute([$qtyToTake, $batchId]);

                        $stmtLogItem = $pdo->prepare("INSERT INTO DispensationItem (dispense_id, batch_id, quantity) VALUES (?, ?, ?)");
                        $stmtLogItem->execute([$dispenseId, $batchId, $qtyToTake]);

                        $qtyNeeded -= $qtyToTake;
                    }

                    if ($qtyNeeded > 0) {
                        throw new Exception("Not enough valid, unexpired stock available to fulfill the request.");
                    }
                }

                $pdo->commit();
                $message = "<div class='alert-success'>Dispensation successful! Inventory updated.</div>";

            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert-error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }

        // --- FETCH DASHBOARD STATS ---
        $totalStock = $pdo->query("SELECT SUM(quantity_in_stock) as total FROM MedicineBatch")->fetch()['total'] ?? 0;
        
        $lowStock = $pdo->query("
            SELECT COUNT(*) as low_count FROM (
                SELECT m.medicine_id FROM Medicine m LEFT JOIN MedicineBatch mb ON m.medicine_id = mb.medicine_id 
                GROUP BY m.medicine_id, m.reorder_level HAVING IFNULL(SUM(mb.quantity_in_stock), 0) <= m.reorder_level
            ) as low_stock_query
        ")->fetch()['low_count'] ?? 0;

        $expiringSoon = $pdo->query("
            SELECT COUNT(*) as exp_count FROM MedicineBatch 
            WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND expiry_date >= CURDATE() AND quantity_in_stock > 0
        ")->fetch()['exp_count'] ?? 0;

        // Fetch Inventory Snapshot
        $inventorySnapshot = $pdo->query("
            SELECT m.name AS medicine_name, IFNULL(SUM(mb.quantity_in_stock), 0) AS total_stock, m.reorder_level
            FROM Medicine m LEFT JOIN MedicineBatch mb ON m.medicine_id = mb.medicine_id
            GROUP BY m.medicine_id, m.name, m.reorder_level ORDER BY total_stock ASC LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Recent Logs (ONLY CURRENT DATE)
        $recentLogs = $pdo->query("
            SELECT d.dispense_date, p.first_name AS patient_first, p.last_name AS patient_last, m.name AS medicine_name, di.quantity
            FROM Dispensation d JOIN DispensationItem di ON d.dispense_id = di.dispense_id JOIN Patient p ON d.patient_id = p.patient_id
            JOIN MedicineBatch mb ON di.batch_id = mb.batch_id JOIN Medicine m ON mb.medicine_id = m.medicine_id
            WHERE DATE(d.dispense_date) = CURDATE() ORDER BY d.dispense_date DESC LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        // --- FETCH MEDICINES FOR DROPDOWN (ONLY UNEXPIRED STOCK) ---
        $availableMedicines = $pdo->query("
            SELECT m.medicine_id, m.name, SUM(mb.quantity_in_stock) as total_qty
            FROM Medicine m 
            JOIN MedicineBatch mb ON m.medicine_id = mb.medicine_id
            WHERE mb.expiry_date >= CURDATE()
            GROUP BY m.medicine_id, m.name 
            HAVING total_qty > 0 
            ORDER BY m.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $totalStock = $lowStock = $expiringSoon = "-"; 
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CIT Clinic Inventory</title>
    <link rel="stylesheet" href="../components/shared.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>

<body>

    <?php include '../components/header.php'; ?>

<main class="dashboard-container">
    <h2 class="welcome-text">&nbsp;Welcome, <?php echo htmlspecialchars($firstName); ?>!</h2>
    <hr class="yellow-line">

    <?php if (!empty($message)) echo $message; ?>

    <div class="stat-cards">
        <div class="card stat-card total-stock">
            <p>Total Stock</p>
            <h3><?php echo is_numeric($totalStock) ? number_format($totalStock) : $totalStock; ?></h3>
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 class="section-title" style="margin: 0;">Current Inventory Snapshot</h3>
                <button class="btn-dispense" onclick="openModal()">+ New Dispensation</button>
            </div>
            <hr class="divider">
            
            <table class="dashboard-table">
                <thead>
                    <tr><th>Medicine</th><th>Total Stock</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($inventorySnapshot)): ?>
                        <tr><td colspan="3" style="text-align: center; padding: 15px;">No inventory data available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($inventorySnapshot as $item): $isLow = $item['total_stock'] <= $item['reorder_level']; ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['medicine_name']); ?></strong></td>
                                <td class="<?php echo $isLow ? 'text-danger' : ''; ?>"><?php echo number_format($item['total_stock']); ?></td>
                                <td><span class="badge <?php echo $isLow ? 'low-stock' : 'in-stock'; ?>"><?php echo $isLow ? 'Low Stock' : 'Good'; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card recent-logs">
            <h3 class="section-title">Today's Activity Logs</h3>
            <hr class="divider">
            <table class="dashboard-table">
                <thead><tr><th>Time</th><th>Patient</th><th>Item Dispensed</th></tr></thead>
                <tbody>
                    <?php if (empty($recentLogs)): ?>
                        <tr><td colspan="3" style="text-align: center; padding: 15px; color: #6b7280;">No dispensations recorded today.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td style="font-size: 0.9em; color: #6b7280;"><?php echo date('g:i A', strtotime($log['dispense_date'])); ?></td>
                                <td><?php echo htmlspecialchars($log['patient_first'] . ' ' . $log['patient_last']); ?></td>
                                <td><strong><?php echo htmlspecialchars($log['medicine_name']); ?></strong> <span class="text-danger">(-<?php echo $log['quantity']; ?>)</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- DISPENSATION MODAL -->
<div id="dispenseModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>New Dispensation</h2>
            <button type="button" class="close-icon" onclick="closeModal()">&times;</button>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="dispense">
            
            <h3 style="font-size: 15px; color: #7b2c31; margin-bottom: 10px;">1. Patient Details</h3>
            <div class="form-grid" style="margin-bottom: 20px;">
                <div class="input-group">
                    <label>First Name</label>
                    <input type="text" name="patient_first" required placeholder="e.g. John">
                </div>
                <div class="input-group">
                    <label>Last Name</label>
                    <input type="text" name="patient_last" required placeholder="e.g. Doe">
                </div>
                <div class="input-group">
                    <label>Patient Type</label>
                    <select name="patient_type" required>
                        <option value="Student">Student</option>
                        <option value="Employee">Employee</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Purpose / Diagnosis</label>
                    <input type="text" name="purpose" required placeholder="e.g. Headache">
                </div>
            </div>

            <h3 style="font-size: 15px; color: #7b2c31; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                2. Medicines to Dispense
                <button type="button" class="btn-add-row" onclick="addMedicineRow()">+ Add Another</button>
            </h3>
            
            <div id="medicine-container">
                <div class="medicine-row">
                    <div class="input-group" style="flex: 2;">
                        <label>Select Medicine</label>
                        <select name="medicine_id[]" required>
                            <option value="" disabled selected>Select medicine...</option>
                            <?php foreach ($availableMedicines as $med): ?>
                                <option value="<?php echo $med['medicine_id']; ?>">
                                    <?php echo htmlspecialchars($med['name']); ?> (Stock: <?php echo $med['total_qty']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group" style="flex: 1;">
                        <label>Quantity</label>
                        <input type="number" name="quantity[]" required min="1" placeholder="Qty">
                    </div>
                    <div style="width: 30px;"></div>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-save">Confirm Dispensation</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() { document.getElementById('dispenseModal').style.display = 'flex'; }
    function closeModal() { document.getElementById('dispenseModal').style.display = 'none'; }

    function addMedicineRow() {
        const container = document.getElementById('medicine-container');
        const row = document.createElement('div');
        row.className = 'medicine-row';
        row.style.marginTop = '10px';
        row.innerHTML = `
            <div class="input-group" style="flex: 2;">
                <select name="medicine_id[]" required>
                    <option value="" disabled selected>Select medicine...</option>
                    <?php foreach ($availableMedicines as $med): ?>
                        <option value="<?php echo $med['medicine_id']; ?>"><?php echo htmlspecialchars($med['name']); ?> (Stock: <?php echo $med['total_qty']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="input-group" style="flex: 1;">
                <input type="number" name="quantity[]" required min="1" placeholder="Qty">
            </div>
            <button type="button" class="btn-remove-row" onclick="this.parentElement.remove()">&times;</button>
        `;
        container.appendChild(row);
    }
</script>

</body>
</html>