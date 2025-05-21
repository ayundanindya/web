<?php
// Function to get purchase history
function getPurchaseHistory($accountId, $conn) {
    try {
        $stmt = $conn->prepare("SELECT p.*, pr.name as product_name 
                               FROM purchase_history p
                               LEFT JOIN product pr ON p.product_id = pr.id
                               WHERE p.accid = ?
                               ORDER BY p.purchase_time DESC");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $purchases = [];
        while ($row = $result->fetch_assoc()) {
            $purchases[] = $row;
        }
        $stmt->close();
        return $purchases;
    } catch (Exception $e) {
        return [];
    }
}

// Function to get donation history (balance received from agents)
function getDonationHistory($accountId, $conn) {
    try {
        $stmt = $conn->prepare("SELECT transaction_id, amount, transaction_date 
                               FROM topup_history 
                               WHERE recipient_id = ?
                               ORDER BY transaction_date DESC");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $donations = [];
        while ($row = $result->fetch_assoc()) {
            $donations[] = $row;
        }
        $stmt->close();
        return $donations;
    } catch (Exception $e) {
        return [];
    }
}

// Function to get topup history for agent
function getTopupHistory($agentId, $conn) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                th.id, 
                th.agent_id, 
                th.recipient_id, 
                th.amount, 
                th.transaction_id, 
                th.transaction_date, 
                th.remaining_balance,
                th.note,
                a.account as recipient_username,
                (a.money - th.amount) as old_balance,
                a.money as new_balance
            FROM 
                topup_history th
            JOIN 
                account a ON th.recipient_id = a.id
            WHERE 
                th.agent_id = ?
            ORDER BY 
                th.transaction_date DESC
            LIMIT 50
        ");
        $stmt->bind_param("i", $agentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        return $history;
    } catch (Exception $e) {
        return [];
    }
}
?>