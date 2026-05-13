<?php
require_once 'connection/connection.php';

// User data
$first_name = "John Arcel";
$last_name = "Sabagkit";
$email = "admin@cit.edu";
$plain_password = "123";

// Hash the password
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

// Check if user already exists (using user_id as primary key)
$check_sql = "SELECT user_id FROM user WHERE email = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo "⚠️ User with email '{$email}' already exists!\n";
    echo "No insertion performed.\n";
} else {
    // Insert the user with correct column names
    $insert_sql = "INSERT INTO user (first_name, last_name, email, password) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ssss", $first_name, $last_name, $email, $hashed_password);
    
    if ($insert_stmt->execute()) {
        $user_id = $insert_stmt->insert_id;
        echo "✅ Admin user inserted successfully!\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "User ID: {$user_id}\n";
        echo "First Name: {$first_name}\n";
        echo "Last Name: {$last_name}\n";
        echo "Email: {$email}\n";
        echo "Plain Password: {$plain_password}\n";
        echo "Hashed Password: {$hashed_password}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    } else {
        echo "❌ Error inserting user: " . $insert_stmt->error . "\n";
    }
    $insert_stmt->close();
}

$check_stmt->close();
$conn->close();
?>