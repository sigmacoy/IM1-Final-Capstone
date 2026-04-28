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
                </tbody>
        </table>
    </div>
</main>

</body>
</html>