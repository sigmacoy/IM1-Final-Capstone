<?php
    // insert.php
    $host = '127.0.0.1';
    $dbname = 'im1_capstone_db';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;port=3306", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $users = [
            ['Admin', 'User', 'admin@cit.edu', '123'],
            ['Admin', 'User', 'admin', '123']
        ];

        $stmt = $pdo->prepare("INSERT INTO User (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");

        foreach ($users as $user) {
            $hashedPassword = password_hash($user[3], PASSWORD_DEFAULT);
            $stmt->execute([$user[0], $user[1], $user[2], $hashedPassword]);
        }

        echo "Users inserted successfully!";
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
?>