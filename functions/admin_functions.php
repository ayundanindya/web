<?php
// Function to get account statistics for admin panel
function getAccountStats($conn) {
    $stats = [
        'total' => 0,
        'new_today' => 0,
        'active' => 0,
        'sales_today' => 0
    ];
    
    try {
        // Total accounts
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM account");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['total'] = $row['total'];
        }
        $stmt->close();
        
        // New accounts today
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM account WHERE DATE(regtime) = CURDATE()");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['new_today'] = $row['total'];
        }
        $stmt->close();
        
        // Active players (logged in within last 24 hours)
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM account WHERE logintime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['active'] = $row['total'];
        }
        $stmt->close();
        
        // Today's sales
        $stmt = $conn->prepare("SELECT SUM(amount) as total FROM purchase_history WHERE DATE(purchase_time) = CURDATE()");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['sales_today'] = $row['total'] ?: 0;
        }
        $stmt->close();
        
        return $stats;
    } catch (Exception $e) {
        return $stats;
    }
}

// Function to get banned users
function getBannedUsers($conn, $limit = 10) {
    try {
        $stmt = $conn->prepare("SELECT b.*, a.account FROM banned_users b 
                               JOIN account a ON b.user_id = a.id 
                               ORDER BY b.start_date DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $bannedUsers = [];
        while ($row = $result->fetch_assoc()) {
            $bannedUsers[] = $row;
        }
        $stmt->close();
        return $bannedUsers;
    } catch (Exception $e) {
        return [];
    }
}
?>