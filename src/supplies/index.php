<!-- supplies/index.php -->
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
                </tbody>
        </table>
    </div>
</main>

</body>
</html>