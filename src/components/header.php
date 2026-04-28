<?php
    // components/header.php
    // Get the current folder name (dashboard, supplies, or logs)
    $current_folder = basename(dirname($_SERVER['PHP_SELF']));
?>

<header class="main-header">
    <div class="logo-container">
        <img src="../images/cit-logo-white.png" alt="CIT Logo" class="logo">
        <h1>CIT-U CLINIC INVENTORY</h1>
    </div>
    <nav class="nav-links">
        <a href="../dashboard/index.php" class="<?= ($current_folder == 'dashboard') ? 'active' : '' ?>">Dashboard</a>
        <a href="../supplies/index.php" class="<?= ($current_folder == 'supplies') ? 'active' : '' ?>">Supplies</a>
        <a href="../logs/index.php" class="<?= ($current_folder == 'logs') ? 'active' : '' ?>">Logs</a>
        <a href="../login/index.php" class="logout-btn">Logout</a>
    </nav>
</header>