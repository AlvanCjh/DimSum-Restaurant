<?php
session_start();
require_once '../connection.php';

// Security: Only chef role can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef') {
    header("Location: login.php");
    exit;
}

// Check if order_id is provided
if (!isset($_POST['order_id'])) {
    header("Location: dashboard.php");
    exit;
}

$order_id = (int)$_POST['order_id'];

try {
    $pdo->beginTransaction();
    
    // Update all order_items for this order to mark them as prepared
    $sql_update_items = "UPDATE order_items 
                         SET prepared_at = NOW() 
                         WHERE order_id = ? AND prepared_at IS NULL";
    $stmt_update_items = $pdo->prepare($sql_update_items);
    $stmt_update_items->execute([$order_id]);
    
    // Update order status to 'prepared'
    $sql_update_order = "UPDATE orders 
                         SET status = 'prepared' 
                         WHERE id = ?";
    $stmt_update_order = $pdo->prepare($sql_update_order);
    $stmt_update_order->execute([$order_id]);
    
    $pdo->commit();
    
    // Redirect back to dashboard with success message
    header("Location: dashboard.php?success=1");
    exit;
    
} catch (PDOException $e) {
    $pdo->rollBack();
    header("Location: dashboard.php?error=1");
    exit;
}
?>