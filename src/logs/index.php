<!-- logs/index.php -->

<?php
    session_start();

    // 1. Security Check: Redirect to login if the session is not active
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login/login.php");
        exit;
    }

    $groupedLogs = [];

    try {
        // 2. Database Connection
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=citu_clinic_inventory;port=3306", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 3. SQL Query: Added `p.school_id` to fetch the real ID from the database
        $sql = "
            SELECT 
                d.dispense_id,
                d.dispense_date,
                p.patient_id,
                p.school_id,  -- ADDED THIS LINE
                p.first_name AS patient_first,
                p.last_name AS patient_last,
                p.patient_type,
                m.name AS medicine_name,
                di.quantity,
                d.purpose,
                u.first_name AS admin_first,
                u.last_name AS admin_last
            FROM Dispensation d
            JOIN DispensationItem di ON d.dispense_id = di.dispense_id
            JOIN Patient p ON d.patient_id = p.patient_id
            JOIN User u ON d.user_id = u.user_id
            JOIN MedicineBatch mb ON di.batch_id = mb.batch_id
            JOIN Medicine m ON mb.medicine_id = m.medicine_id
            ORDER BY d.dispense_date DESC
        ";
        
        $stmt = $pdo->query($sql);
        $rawLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Group the flat data by Transaction (dispense_id)
        foreach ($rawLogs as $row) {
            $id = $row['dispense_id'];
            
            // If we haven't seen this transaction yet, set up the main row data
            if (!isset($groupedLogs[$id])) {
                $groupedLogs[$id] = [
                    'dispense_date' => $row['dispense_date'],
                    'patient_id' => $row['patient_id'],
                    'school_id' => $row['school_id'],
                    'patient_first' => $row['patient_first'],
                    'patient_last' => $row['patient_last'],
                    'patient_type' => $row['patient_type'],
                    'purpose' => $row['purpose'],
                    'admin_first' => $row['admin_first'],
                    'admin_last' => $row['admin_last'],
                    'items' => [] 
                ];
            }
            
            // Add the medicine to this transaction's items array.
            $medName = $row['medicine_name'];
            if (!isset($groupedLogs[$id]['items'][$medName])) {
                $groupedLogs[$id]['items'][$medName] = 0;
            }
            $groupedLogs[$id]['items'][$medName] += $row['quantity'];
        }

    } catch (PDOException $e) {
        $error_message = "System error: Unable to retrieve logs.";
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIT Clinic Inventory - Dispensation Logs</title>
    
    <link rel="stylesheet" href="../components/shared.css">
    <link rel="stylesheet" href="style.css"> 
</head>
<body>

    <?php include '../components/header.php'; ?>

    <main class="logs-container">
        <h2 class="page-title">&nbsp;Patient Dispensation History</h2>
        <hr class="yellow-line">

        <div class="toolbar">
            <input type="text" id="searchInput" placeholder="Search by Student/Employee Name or ID..." class="search-input">
        </div>

        <div class="table-container">
            <table class="logs-table" id="logsTable">
                <thead>
                    <tr>
                        <th>DATE & TIME</th>
                        <th>PATIENT DETAILS</th>
                        <th>TYPE</th>
                        <th>MEDICINE</th>
                        <th>QTY</th>
                        <th>DISPENSATION NOTES</th>
                        <th>AUTHORIZED BY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($error_message)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #7b2c31; padding: 40px;">
                                <?php echo $error_message; ?>
                            </td>
                        </tr>
                    <?php elseif (empty($groupedLogs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #6b7280; padding: 40px;">
                                No dispensation history found in the system.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($groupedLogs as $log): 
                            $formattedDate = date('Y-m-d | H:i', strtotime($log['dispense_date']));
                            $rawType = strtoupper($log['patient_type']);
                            $badgeClass = ($rawType === 'STUDENT') ? 'badge-student' : 'badge-employee';
                        ?>
                            <tr class="data-row">
                                <td class="text-date"><?php echo $formattedDate; ?></td>
                                
                                <td>
                                    <span class="patient-name">
                                        <?php echo htmlspecialchars($log['patient_first'] . ' ' . $log['patient_last']); ?>
                                    </span>
                                    <!-- DISPLAY THE REAL DB ID INSTEAD OF THE GENERATED ONE -->
                                    <span class="patient-id">ID: <?php echo htmlspecialchars($log['school_id']); ?></span>
                                </td>
                                
                                <td>
                                    <span class="type-badge <?php echo $badgeClass; ?>">
                                        <?php echo $rawType; ?>
                                    </span>
                                </td>
                                
                                <!-- MEDICINE COLUMN -->
                                <td class="medicine-column">
                                    <?php foreach ($log['items'] as $medName => $qty): ?>
                                        <div class="med-row">
                                            <?php echo htmlspecialchars($medName); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>

                                <!-- QTY COLUMN -->
                                <td class="qty-column">
                                    <?php foreach ($log['items'] as $medName => $qty): ?>
                                        <div class="qty-row">
                                            <?php echo number_format($qty); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                
                                <td>
                                    <div class="dispensation-notes">
                                        <?php echo htmlspecialchars($log['purpose']); ?>
                                    </div>
                                </td>
                                   
                                
                                
                                <td>
                                    <span class="admin-name">
                                        <?php echo htmlspecialchars($log['admin_first'] . ' ' . $log['admin_last']); ?>
                                    </span>
                                    <span class="admin-label"></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- REAL-TIME SEARCH SCRIPT -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const tableRows = document.querySelectorAll('#logsTable tbody tr.data-row');

            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase().trim();

                tableRows.forEach(row => {
                    // The JS will automatically pick up the real school_id since it reads the text content of this cell
                    const patientDetails = row.cells[1].textContent.toLowerCase();

                    if (patientDetails.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>