<!-- suppliers/index.php -->
<?php
session_start();

// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

require_once "../connection/connection.php";

$message = '';
$suppliers = [];

// ==============================
// ADD SUPPLIER (with multiple contacts)
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_supplier') {

    $name = trim($_POST['supplier_name']);
    $mobile_numbers = $_POST['mobile_numbers'] ?? []; // Array of numbers
    
    // Remove empty values
    $mobile_numbers = array_filter($mobile_numbers, function($num) {
        return !empty(trim($num));
    });

    if (!empty($name) && !empty($mobile_numbers)) {

        $conn->begin_transaction();

        try {
            // Insert supplier
            $stmt = $conn->prepare("INSERT INTO Supplier (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $supplierId = $conn->insert_id;
            $stmt->close();

            // Insert each contact number
            $stmtContact = $conn->prepare("INSERT INTO SupplierContactNo (supplier_id, mobile_number) VALUES (?, ?)");
            foreach ($mobile_numbers as $mobile) {
                $mobile = trim($mobile);
                if (!empty($mobile)) {
                    $stmtContact->bind_param("is", $supplierId, $mobile);
                    $stmtContact->execute();
                }
            }
            $stmtContact->close();

            $conn->commit();
            $message = "<div class='alert-success'>Supplier added successfully.</div>";
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='alert-error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $message = "<div class='alert-error'>Please fill in supplier name and at least one contact number.</div>";
    }
}

// ==============================
// UPDATE SUPPLIER (with multiple contacts)
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_supplier') {

    $supplierId = $_POST['supplier_id'];
    $name = trim($_POST['supplier_name']);
    $mobile_numbers = $_POST['mobile_numbers'] ?? []; // Array of numbers
    $mobile_numbers = array_filter($mobile_numbers, function($num) {
        return !empty(trim($num));
    });

    $conn->begin_transaction();

    try {
        // Update supplier name
        $stmt = $conn->prepare("UPDATE Supplier SET name = ? WHERE supplier_id = ?");
        $stmt->bind_param("si", $name, $supplierId);
        $stmt->execute();
        $stmt->close();

        // Delete all existing contacts
        $stmtDelete = $conn->prepare("DELETE FROM SupplierContactNo WHERE supplier_id = ?");
        $stmtDelete->bind_param("i", $supplierId);
        $stmtDelete->execute();
        $stmtDelete->close();

        // Insert new contacts
        if (!empty($mobile_numbers)) {
            $stmtContact = $conn->prepare("INSERT INTO SupplierContactNo (supplier_id, mobile_number) VALUES (?, ?)");
            foreach ($mobile_numbers as $mobile) {
                $mobile = trim($mobile);
                if (!empty($mobile)) {
                    $stmtContact->bind_param("is", $supplierId, $mobile);
                    $stmtContact->execute();
                }
            }
            $stmtContact->close();
        }

        $conn->commit();
        $message = "<div class='alert-success'>Supplier updated successfully.</div>";
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div class='alert-error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// ==============================
// DELETE SUPPLIER
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_supplier') {
    $supplierId = $_POST['supplier_id'];
    
    $conn->begin_transaction();
    
    try {
        // First check if supplier has any medicine batches
        $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM MedicineBatch WHERE supplier_id = ?");
        $stmtCheck->bind_param("i", $supplierId);
        $stmtCheck->execute();
        $stmtCheck->bind_result($batchCount);
        $stmtCheck->fetch();
        $stmtCheck->close();
        
        if ($batchCount > 0) {
            $message = "<div class='alert-error'>Cannot delete supplier because they have $batchCount medicine batch(es) in inventory.</div>";
            $conn->rollback();
        } else {
            // Delete contacts first (foreign key constraint)
            $stmtDeleteContacts = $conn->prepare("DELETE FROM SupplierContactNo WHERE supplier_id = ?");
            $stmtDeleteContacts->bind_param("i", $supplierId);
            $stmtDeleteContacts->execute();
            $stmtDeleteContacts->close();
            
            // Delete the supplier
            $stmtDeleteSupplier = $conn->prepare("DELETE FROM Supplier WHERE supplier_id = ?");
            $stmtDeleteSupplier->bind_param("i", $supplierId);
            $stmtDeleteSupplier->execute();
            $stmtDeleteSupplier->close();
            
            $conn->commit();
            $message = "<div class='alert-success'>Supplier deleted successfully.</div>";
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div class='alert-error'>Error deleting supplier: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// ==============================
// FETCH SUPPLIERS (with multiple contacts)
// ==============================
$sql = "
    SELECT 
        s.supplier_id,
        s.name,
        GROUP_CONCAT(sc.mobile_number SEPARATOR ', ') AS mobile_numbers,
        COUNT(DISTINCT mb.batch_id) AS total_batches
    FROM Supplier s
    LEFT JOIN SupplierContactNo sc
        ON s.supplier_id = sc.supplier_id
    LEFT JOIN MedicineBatch mb
        ON s.supplier_id = mb.supplier_id
    GROUP BY s.supplier_id
    ORDER BY s.name ASC
";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}



?>

<!-- ----------------------------------------------------------------------- -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CIT Clinic Inventory - Suppliers</title>
    <link rel="stylesheet" href="../components/shared.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>

<body>

<?php include '../components/header.php'; ?>

<main class="suppliers-container">

    <h2 class="page-title">&nbsp;Suppliers Management</h2>
    <hr class="yellow-line">

    <?php echo $message; ?>

    <div class="toolbar">
        <input type="text" id="searchInput" class="search-input" placeholder="Search supplier...">
        <button class="add-btn" onclick="openAddModal()">Add Supplier</button>
    </div>

    <div class="table-container">
        <table class="suppliers-table" id="suppliersTable">
            <thead>
                <tr>
                    <th>Supplier Name</th>
                    <th>Contact Numbers</th>
                    <th>Total Medicine Batches</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="4" class="empty-state">No suppliers found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $sup): ?>
                        <tr class="data-row">
                            <td class="supplier-name"><strong><?php echo htmlspecialchars($sup['name']); ?></strong></td>
                            <td class="contact-numbers">
                                <?php 
                                $numbers = explode(', ', $sup['mobile_numbers']);
                                foreach ($numbers as $number):
                                    echo htmlspecialchars($number) . '<br>';
                                endforeach;
                                ?>
                            </td>
                            <td class="batch-count"><?php echo number_format($sup['total_batches']); ?></td>
                            <td class="actions">
                                <div class="action-buttons">
                                    <button class="btn-edit" onclick="openEditModal(
                                        '<?php echo $sup['supplier_id']; ?>',
                                        '<?php echo htmlspecialchars($sup['name'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($sup['mobile_numbers'], ENT_QUOTES); ?>'
                                    )">Edit</button>
                                    
                                    <button class="btn-delete" onclick="showDeleteConfirm('<?php echo $sup['supplier_id']; ?>')">Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
         </table>
    </div>
</main>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Supplier</h2>
            <button class="close-icon" onclick="closeAddModal()">&times;</button>
        </div>
        <form method="POST" id="addSupplierForm">
            <input type="hidden" name="action" value="add_supplier">
            
            <div class="input-group">
                <label>Supplier Name</label>
                <input type="text" name="supplier_name" required>
            </div>
            
            <div class="input-group">
                <label>Contact Numbers</label>
                <div id="addContactsContainer">
                    <div class="contact-input-group">
                        <input type="text" name="mobile_numbers[]" placeholder="Contact Number 1" required>
                    </div>
                </div>
                <button type="button" class="btn-add-contact" onclick="addContactField('addContactsContainer')">+ Add Another Number</button>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn-save">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Supplier</h2>
            <button class="close-icon" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST" id="editSupplierForm">
            <input type="hidden" name="action" value="update_supplier">
            <input type="hidden" name="supplier_id" id="edit_supplier_id">
            
            <div class="input-group">
                <label>Supplier Name</label>
                <input type="text" name="supplier_name" id="edit_supplier_name" required>
            </div>
            
            <div class="input-group">
                <label>Contact Numbers</label>
                <div id="editContactsContainer"></div>
                <button type="button" class="btn-add-contact" onclick="addContactField('editContactsContainer')">+ Add Another Number</button>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save">Update Supplier</button>
            </div>
        </form>
    </div>
</div>

<!-- CUSTOM CONFIRM MODAL FOR DELETE -->
<div class="confirm-modal-overlay" id="confirmModal">
    <div class="confirm-modal-content">
        <h3>Delete Supplier</h3>
        <p>Are you sure you want to delete this supplier?<br>This action cannot be undone.</p>
        <div class="confirm-modal-actions">
            <button class="confirm-btn-cancel" onclick="closeConfirmModal()">Cancel</button>
            <button class="confirm-btn-delete" id="confirmDeleteBtn">Delete</button>
        </div>
    </div>
</div>


<!-- JAVASCRIPT -->

<!-- JAVASCRIPT -->
<script>
// ==============================
// CONTACT NUMBERS FUNCTIONS
// ==============================

function addContactField(containerId) {
    const container = document.getElementById(containerId);
    const fieldCount = container.getElementsByClassName('contact-input-group').length + 1;
    const div = document.createElement('div');
    div.className = 'contact-input-group';
    div.innerHTML = `
        <input type="text" name="mobile_numbers[]" placeholder="Contact Number ${fieldCount}">
        <button type="button" class="btn-remove-contact" onclick="this.parentElement.remove()">Remove</button>
    `;
    container.appendChild(div);
}

// ==============================
// ADD MODAL FUNCTIONS
// ==============================

function openAddModal() {
    document.getElementById('addSupplierForm').reset();
    const container = document.getElementById('addContactsContainer');
    container.innerHTML = '<div class="contact-input-group"><input type="text" name="mobile_numbers[]" placeholder="Contact Number 1" required></div>';
    document.getElementById('addModal').style.display = 'flex';
}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

// ==============================
// EDIT MODAL FUNCTIONS
// ==============================

function openEditModal(id, name, mobileNumbersString) {
    document.getElementById('edit_supplier_id').value = id;
    document.getElementById('edit_supplier_name').value = name;
    
    const numbers = mobileNumbersString.split(', ');
    const container = document.getElementById('editContactsContainer');
    container.innerHTML = '';
    
    numbers.forEach((number, index) => {
        const div = document.createElement('div');
        div.className = 'contact-input-group';
        div.innerHTML = `
            <input type="text" name="mobile_numbers[]" value="${escapeHtml(number)}" placeholder="Contact Number ${index + 1}" required>
            <button type="button" class="btn-remove-contact" onclick="this.parentElement.remove()">Remove</button>
        `;
        container.appendChild(div);
    });
    
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// ==============================
// DELETE CONFIRMATION MODAL
// ==============================

let supplierIdToDelete = null;

function showDeleteConfirm(supplierId) {
    supplierIdToDelete = supplierId;
    document.getElementById('confirmModal').style.display = 'flex';
}

function closeConfirmModal() {
    document.getElementById('confirmModal').style.display = 'none';
    supplierIdToDelete = null;
}

// Handle delete confirmation
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (supplierIdToDelete) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_supplier';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'supplier_id';
        idInput.value = supplierIdToDelete;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        
        form.submit();
    }
});

// ==============================
// UTILITY FUNCTIONS
// ==============================

function escapeHtml(str) {
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// ==============================
// SEARCH FUNCTIONALITY
// ==============================

const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const value = this.value.toLowerCase();
        const rows = document.querySelectorAll('.data-row');
        
        rows.forEach(row => {
            const supplierName = row.cells[0].textContent.toLowerCase();
            const mobile = row.cells[1].textContent.toLowerCase();
            
            if (supplierName.includes(value) || mobile.includes(value)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
}

// ==============================
// CLOSE MODALS WHEN CLICKING OUTSIDE
// ==============================

window.onclick = function(event) {
    const addModal = document.getElementById('addModal');
    const editModal = document.getElementById('editModal');
    const confirmModal = document.getElementById('confirmModal');
    
    if (event.target === addModal) {
        closeAddModal();
    }
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === confirmModal) {
        closeConfirmModal();
    }
}
</script>

</body>
</html>