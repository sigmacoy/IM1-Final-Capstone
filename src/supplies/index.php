<!-- supplies/index.php -->
<?php
    session_start();

    // Security check: ensure user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login/login.php");
        exit;
    }

    $medicines = [];
    $suppliers = [];
    $finalCategories = []; 
    $message = '';

    try {
        // Connect to your database
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=citu_clinic_inventory;port=3306", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // --- HANDLE ADD MEDICINE FORM SUBMISSION ---
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_medicine') {
            $medName = trim($_POST['medicine_name']);
            $category = trim($_POST['category']);
            $reorderLvl = $_POST['reorder_level'];
            $supplierId = $_POST['supplier_id'];
            $quantity = $_POST['quantity'];
            $expiryDate = $_POST['expiry_date'];

            $pdo->beginTransaction();

            $stmtCheck = $pdo->prepare("SELECT medicine_id FROM Medicine WHERE name = ?");
            $stmtCheck->execute([$medName]);
            $existingMed = $stmtCheck->fetch();

            if ($existingMed) {
                $medicineId = $existingMed['medicine_id'];
            } else {
                $stmtMed = $pdo->prepare("INSERT INTO Medicine (name, purpose, reorder_level) VALUES (?, ?, ?)");
                $stmtMed->execute([$medName, $category, $reorderLvl]);
                $medicineId = $pdo->lastInsertId();
            }
        
            $batchNumber = 'BATCH-' . date('Ymd') . '-' . rand(100, 999);

            $stmtBatch = $pdo->prepare("INSERT INTO MedicineBatch (medicine_id, supplier_id, batch_number, quantity_in_stock, expiry_date, date_received) VALUES (?, ?, ?, ?, ?, CURDATE())");
            $stmtBatch->execute([$medicineId, $supplierId, $batchNumber, $quantity, $expiryDate]);

            $pdo->commit();
            $message = "<div class='alert-success'>Successfully added " . number_format($quantity) . " units of " . htmlspecialchars($medName) . ".</div>";
        }

        // --- HANDLE DISCARD EXPIRED MEDICINE ---
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'discard_batch') {
            $batchId = $_POST['batch_id'];
            
            $stmtDiscard = $pdo->prepare("UPDATE MedicineBatch SET quantity_in_stock = 0 WHERE batch_id = ?");
            $stmtDiscard->execute([$batchId]);
            
            $message = "<div class='alert-success'>Expired medicine has been safely discarded.</div>";
        }

        // --- HANDLE MANUAL STOCK ADJUSTMENT ---
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'adjust_stock') {
            $batchId = $_POST['edit_batch_id'];
            $newQuantity = (int)$_POST['new_quantity'];
            
            // Update the physical stock (Reason field removed)
            $stmtAdjust = $pdo->prepare("UPDATE MedicineBatch SET quantity_in_stock = ? WHERE batch_id = ?");
            $stmtAdjust->execute([$newQuantity, $batchId]);
            
            $message = "<div class='alert-success'>Inventory manually adjusted to " . number_format($newQuantity) . " units.</div>";
        }

        // --- FETCH SUPPLIERS FOR DROPDOWN ---
        $stmtSuppliers = $pdo->query("SELECT * FROM Supplier ORDER BY name ASC");
        $suppliers = $stmtSuppliers->fetchAll(PDO::FETCH_ASSOC);

        // --- FETCH INVENTORY TABLE DATA ---
        $sql = "
            SELECT 
                mb.batch_id,
                mb.batch_number,
                m.name AS medicine_name,
                m.purpose AS category,
                mb.quantity_in_stock AS stock_level,
                m.reorder_level,
                s.name AS supplier_name,
                mb.expiry_date
            FROM MedicineBatch mb
            JOIN Medicine m ON mb.medicine_id = m.medicine_id
            JOIN Supplier s ON mb.supplier_id = s.supplier_id
            WHERE mb.quantity_in_stock > 0
            ORDER BY m.name ASC, mb.expiry_date ASC
        ";
        $stmt = $pdo->query($sql);
        $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- FETCH UNIQUE CATEGORIES ---
        $stmtCategories = $pdo->query("SELECT DISTINCT purpose FROM Medicine WHERE purpose IS NOT NULL AND purpose != ''");
        $rawCategories = $stmtCategories->fetchAll(PDO::FETCH_COLUMN); 
        
        foreach ($rawCategories as $rawCat) {
            $pieces = explode('/', $rawCat);
            foreach ($pieces as $piece) {
                $cleanCategory = trim($piece);
                if (!empty($cleanCategory) && strtolower($cleanCategory) !== 'all categories' && !in_array($cleanCategory, $finalCategories)) {
                    $finalCategories[] = $cleanCategory;
                }
            }
        }
        sort($finalCategories);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "<div class='alert-error'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CIT Clinic Inventory - Supplies</title>
    <link rel="stylesheet" href="../components/shared.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>"> 
</head>

<body>

    <?php include '../components/header.php'; ?>

<main class="supplies-container">
    <h2 class="page-title">&nbsp;Medicine Supplies</h2>
    <hr class="yellow-line">

    <?php if (!empty($message)) { echo $message; } ?>

    <div class="toolbar">
        <input type="text" id="searchInput" placeholder="Search by name or batch..." class="search-input">
        
        <select class="category-select">
            <option value="all categories">All Categories</option>
            <?php foreach ($finalCategories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>">
                    <?php echo htmlspecialchars($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="add-btn" onclick="openModal()">Add New Medicine</button>
    </div>

    <div class="table-container">
        <table class="supplies-table" id="suppliesTable">
            <thead>
                <tr>
                    <th>Medicine Name</th>
                    <th>Batch Number</th>
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
                    <tr class="no-data-row">
                        <td colspan="8" style="text-align: center; padding: 20px;">No inventory records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($medicines as $med): 
                        $currentDate = new DateTime();
                        $expiryDate = new DateTime($med['expiry_date']);
                        $currentDate->setTime(0, 0, 0);
                        $expiryDate->setTime(0, 0, 0);
                        
                        $interval = $currentDate->diff($expiryDate);
                        $daysUntilExpiry = $interval->invert ? -$interval->days : $interval->days;

                        $statusHtml = '';
                        $stockClass = '';
                        $rowClass = ''; 

                        if ($daysUntilExpiry < 0) {
                            $stockClass = 'text-danger';
                            $rowClass = 'row-danger'; 
                            $statusHtml = "
                                <form method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to discard this expired batch?\");'>
                                    <input type='hidden' name='action' value='discard_batch'>
                                    <input type='hidden' name='batch_id' value='" . $med['batch_id'] . "'>
                                    <button type='submit' class='btn-discard' onclick='event.stopPropagation();'>Discard</button>
                                </form>
                            ";
                        } elseif ($med['stock_level'] <= $med['reorder_level']) {
                            $stockClass = 'text-danger';
                            $rowClass = 'row-danger'; 
                            $statusHtml = "<span class='badge low-stock'>Low Stock</span>";
                        } elseif ($daysUntilExpiry <= 60) {
                            $statusHtml = "<span class='badge expiring'>Expiring Soon</span>";
                        } else {
                            $statusHtml = "<span class='badge in-stock'>In Stock</span>";
                        }
                    ?>
                        <tr class="<?php echo $rowClass; ?> data-row hover-row" 
                            style="cursor: pointer;"
                            onclick="openEditModal('<?php echo $med['batch_id']; ?>', '<?php echo addslashes($med['medicine_name']); ?>', '<?php echo addslashes($med['batch_number']); ?>', <?php echo $med['stock_level']; ?>)">
                            
                            <td><strong><?php echo htmlspecialchars($med['medicine_name']); ?></strong></td>
                            <td style="color: #6b7280; font-family: monospace; font-size: 0.9em;"><?php echo htmlspecialchars($med['batch_number']); ?></td>
                            <td><?php echo htmlspecialchars($med['category']); ?></td>
                            <td class="<?php echo $stockClass; ?>"><?php echo number_format($med['stock_level']); ?></td>
                            <td><?php echo number_format($med['reorder_level']); ?></td>
                            <td><?php echo htmlspecialchars($med['supplier_name']); ?></td>
                            <td><?php echo date('F d, Y', strtotime($med['expiry_date'])); ?></td>
                            <td><?php echo $statusHtml; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- ADD MEDICINE MODAL -->
<div id="addMedicineModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Medicine</h2>
            <button type="button" class="close-icon" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_medicine">
            <div class="form-grid">
                <div class="input-group"><label>Medicine Name</label><input type="text" name="medicine_name" required placeholder="e.g. Ibuprofen"></div>
                <div class="input-group"><label>Category / Purpose</label><input type="text" name="category" required placeholder="e.g. Pain Reliever"></div>
                <div class="input-group"><label>Quantity in Stock</label><input type="number" name="quantity" required min="1" placeholder="0"></div>
                <div class="input-group"><label>Reorder Level (Warning)</label><input type="number" name="reorder_level" required min="0" placeholder="e.g. 50"></div>
                <div class="input-group full-width">
                    <label>Supplier</label>
                    <select name="supplier_id" required>
                        <option value="" disabled selected>Select an available supplier...</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?php echo $sup['supplier_id']; ?>"><?php echo htmlspecialchars($sup['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group full-width"><label>Expiry Date</label><input type="date" name="expiry_date" required></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-save">Save to Inventory</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT/ADJUST STOCK MODAL -->
<div id="editStockModal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2>Adjust Stock Level</h2>
            <button type="button" class="close-icon" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="adjust_stock">
            <input type="hidden" name="edit_batch_id" id="editBatchId">
            
            <div class="input-group" style="margin-bottom: 15px;">
                <label>Medicine & Batch</label>
                <input type="text" id="editMedNameDisplay" readonly style="background-color: #f3f4f6; color: #6b7280; outline: none; border: 1px solid #d1d5db;">
            </div>

            <div class="input-group" style="margin-bottom: 25px;">
                <label>Correct Physical Quantity</label>
                <input type="number" name="new_quantity" id="editQuantity" required min="0">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save">Confirm Adjustment</button>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPTS -->
<script>
    // Logic for "Add Medicine" Modal
    function openModal() { document.getElementById('addMedicineModal').style.display = 'flex'; }
    function closeModal() { document.getElementById('addMedicineModal').style.display = 'none'; }

    // Logic for "Adjust Stock" Modal
    function openEditModal(batchId, medName, batchNum, currentQty) {
        document.getElementById('editBatchId').value = batchId;
        document.getElementById('editMedNameDisplay').value = medName + ' (' + batchNum + ')';
        document.getElementById('editQuantity').value = currentQty;
        document.getElementById('editStockModal').style.display = 'flex';
    }
    function closeEditModal() { document.getElementById('editStockModal').style.display = 'none'; }

    // Logic for Search & Category Filter
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const categorySelect = document.querySelector('.category-select'); 
        const tableRows = document.querySelectorAll('#suppliesTable tbody tr.data-row');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase().trim(); 
            const selectedCategory = categorySelect.value.toLowerCase();

            tableRows.forEach(row => {
                const medicineName = row.cells[0].textContent.toLowerCase().trim();
                const batchNumber = row.cells[1].textContent.toLowerCase().trim();
                const category = row.cells[2].textContent.toLowerCase().trim();

                const matchesSearch = medicineName.includes(searchTerm) || batchNumber.includes(searchTerm);
                const matchesCategory = (selectedCategory === 'all categories' || category.includes(selectedCategory));

                if (matchesSearch && matchesCategory) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterTable);
        categorySelect.addEventListener('change', filterTable);
    });
</script>

</body>
</html>