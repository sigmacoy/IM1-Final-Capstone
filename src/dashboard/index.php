<!-- dashboard/index.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CIT Clinic Inventory</title>
    <link rel="stylesheet" href="../components/shared.css">
    <link rel="stylesheet" href="style.css"> </head>
</head>

<body>

    <?php include '../components/header.php'; ?>

<main class="dashboard-container">
    <h2 class="welcome-text">&nbsp;Welcome, Admin!</h2>
    <hr class="yellow-line">

    <div class="stat-cards">
        <div class="card stat-card total-stock">
            <p>Total Stock</p>
            <h3>0</h3>
        </div>
        <div class="card stat-card low-stock">
            <p>Low Stock</p>
            <h3>0</h3>
        </div>
        <div class="card stat-card expiring-soon">
            <p>Expiring Soon</p>
            <h3>0</h3>
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