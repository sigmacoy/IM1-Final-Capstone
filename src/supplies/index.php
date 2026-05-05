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

            // Check if the medicine already exists in the catalog
            $stmtCheck = $pdo->prepare("SELECT medicine_id FROM Medicine WHERE name = ?");
            $stmtCheck->execute([$medName]);
            $existingMed = $stmtCheck->fetch();

            if ($existingMed) {
                $medicineId = $existingMed['medicine_id'];
            } else {
                // Insert brand new medicine
                $stmtMed = $pdo->prepare("INSERT INTO Medicine (name, purpose, reorder_level) VALUES (?, ?, ?)");
                $stmtMed->execute([$medName, $category, $reorderLvl]);
                $medicineId = $pdo->lastInsertId();
            }

            // Insert physical box
            $stmtBatch = $pdo->prepare("INSERT INTO MedicineBatch (medicine_id, supplier_id, quantity_in_stock, expiry_date) VALUES (?, ?, ?, ?)");
            $stmtBatch->execute([$medicineId, $supplierId, $quantity, $expiryDate]);

            $pdo->commit();
            $message = "<div class='alert-success'>Successfully added " . number_format($quantity) . " units of " . htmlspecialchars($medName) . ".</div>";
        }

        // --- HANDLE DISCARD EXPIRED MEDICINE ---
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'discard_batch') {
            $batchId = $_POST['batch_id'];
            
            // Set stock to 0 instead of deleting to protect historical Dispensation Logs
            $stmtDiscard = $pdo->prepare("UPDATE MedicineBatch SET quantity_in_stock = 0 WHERE batch_id = ?");
            $stmtDiscard->execute([$batchId]);
            
            $message = "<div class='alert-success'>Expired medicine has been discarded and removed from active inventory.</div>";
        }

        // --- FETCH SUPPLIERS FOR DROPDOWN ---
        $stmtSuppliers = $pdo->query("SELECT * FROM Supplier ORDER BY name ASC");
        $suppliers = $stmtSuppliers->fetchAll(PDO::FETCH_ASSOC);

        // --- FETCH INVENTORY TABLE DATA (Only items with stock > 0) ---
        $sql = "
            SELECT 
                mb.batch_id,
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

        // --- FETCH UNIQUE CATEGORIES AND SPLIT THEM ---
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
        <input type="text" id="searchInput" placeholder="Search by name or category..." class="search-input">
        
        <!-- THE DYNAMIC DROPDOWN -->
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
                        <td colspan="7" style="text-align: center; padding: 20px;">No inventory records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($medicines as $med): 
                        $currentDate = new DateTime();
                        $expiryDate = new DateTime($med['expiry_date']);
                        // Remove the time portion to calculate exact days accurately
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
                                <form method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to discard this expired batch? It will be removed from active inventory.\");'>
                                    <input type='hidden' name='action' value='discard_batch'>
                                    <input type='hidden' name='batch_id' value='" . $med['batch_id'] . "'>
                                    <button type='submit' class='btn-discard'>Discard</button>
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
                        <tr class="<?php echo $rowClass; ?> data-row">
                            <td><strong><?php echo htmlspecialchars($med['medicine_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($med['category']); ?></td>
                            <td class="<?php echo $stockClass; ?>"><?php echo number_format($med['stock_level']); ?></td>
                            <td><?php echo number_format($med['reorder_level']); ?></td>
                            <td><?php echo htmlspecialchars($med['supplier_name']); ?></td>
                            <td><?php echo $expiryDate->format('Y-m-d'); ?></td>
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
                <div class="input-group">
                    <label>Medicine Name</label>
                    <input type="text" name="medicine_name" required placeholder="e.g. Ibuprofen">
                </div>
                
                <div class="input-group">
                    <label>Category / Purpose</label>
                    <input type="text" name="category" required placeholder="e.g. Pain Reliever">
                </div>

                <div class="input-group">
                    <label>Quantity in Stock</label>
                    <input type="number" name="quantity" required min="1" placeholder="0">
                </div>

                <div class="input-group">
                    <label>Reorder Level (Warning)</label>
                    <input type="number" name="reorder_level" required min="0" placeholder="e.g. 50">
                </div>

                <div class="input-group full-width">
                    <label>Supplier</label>
                    <select name="supplier_id" required>
                        <option value="" disabled selected>Select an available supplier...</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?php echo $sup['supplier_id']; ?>">
                                <?php echo htmlspecialchars($sup['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group full-width">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" required>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-save">Save to Inventory</button>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPTS FOR SEARCH, FILTER, AND MODAL -->
<script>
    function openModal() { document.getElementById('addMedicineModal').style.display = 'flex'; }
    function closeModal() { document.getElementById('addMedicineModal').style.display = 'none'; }

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const categorySelect = document.querySelector('.category-select'); 
        const tableRows = document.querySelectorAll('#suppliesTable tbody tr.data-row');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase().trim(); 
            const selectedCategory = categorySelect.value.toLowerCase();

            tableRows.forEach(row => {
                const medicineName = row.cells[0].textContent.toLowerCase().trim();
                const category = row.cells[1].textContent.toLowerCase().trim();

                const matchesSearch = medicineName.includes(searchTerm) || category.includes(searchTerm);
                
                // For the dropdown match, we use .includes() so "Antihistamine" will match "Antihistamine / Allergies"
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