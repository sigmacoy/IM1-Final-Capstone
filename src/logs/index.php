<?php
    session_start();

    // 1. Security Check: Redirect to login if the session is not active
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login/login.php");
        exit;
    }

    $logs = [];

    try {
        // 2. Database Connection
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=citu_clinic_inventory;port=3306", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 3. SQL Query: Fetching joined data to provide full transaction context
        $sql = "
            SELECT 
                d.dispense_date,
                p.patient_id,
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
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Fallback for database errors
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
            <input type="text" placeholder="Search by Student/Employee Name or ID..." class="search-input">
        </div>

        <div class="table-container">
            <table class="logs-table">
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
                    <?php elseif (empty($logs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #6b7280; padding: 40px;">
                                No dispensation history found in the system.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): 
                            // Format date for the 'text-date' class: YYYY-MM-DD | HH:MM
                            $formattedDate = date('Y-m-d | H:i', strtotime($log['dispense_date']));
                            
                            // Badge logic: Uppercase and conditional CSS classes
                            $rawType = strtoupper($log['patient_type']);
                            $badgeClass = ($rawType === 'STUDENT') ? 'badge-student' : 'badge-employee';

                            // Logical ID Generation based on Patient Type
                            $displayID = ($rawType === 'STUDENT') 
                                ? '21-0001-' . str_pad($log['patient_id'], 3, '0', STR_PAD_LEFT) 
                                : 'EMP-' . str_pad($log['patient_id'], 4, '0', STR_PAD_LEFT);
                        ?>
                            <tr>
                                <td class="text-date"><?php echo $formattedDate; ?></td>
                                
                                <td>
                                    <span class="patient-name">
                                        <?php echo htmlspecialchars($log['patient_first'] . ' ' . $log['patient_last']); ?>
                                    </span>
                                    <span class="patient-id">ID: <?php echo $displayID; ?></span>
                                </td>
                                
                                <td>
                                    <span class="type-badge <?php echo $badgeClass; ?>">
                                        <?php echo $rawType; ?>
                                    </span>
                                </td>
                                
                                <td class="medicine-text">
                                    <?php echo htmlspecialchars($log['medicine_name']); ?>
                                </td>
                                
                                <td class="qty-bold">
                                    <?php echo number_format($log['quantity']); ?>
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
                                    <span class="admin-label">(Admin)</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>