<?php
// filepath: rebate_functions.php

// Fungsi untuk mendapatkan semua rebate
function getAllRebates($conn) {
    $stmt = $conn->prepare("SELECT * FROM rebate_settings ORDER BY id DESC");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getActiveRebates($conn) {
    $stmt = $conn->prepare("SELECT * FROM rebate_settings WHERE status = 'active' AND NOW() BETWEEN start_date AND end_date");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getTiersByRebateId($conn, $rebateId) {
    $stmt = $conn->prepare("SELECT * FROM rebate_tiers WHERE rebate_id = ? ORDER BY spend_amount ASC");
    $stmt->bind_param("i", $rebateId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getRewardsByTierId($conn, $tierId) {
    $stmt = $conn->prepare("SELECT * FROM rebate_rewards WHERE tier_id = ?");
    $stmt->bind_param("i", $tierId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getPlayerSpending($conn, $playerId, $startDate, $endDate) {
    // Fungsi ini perlu disesuaikan dengan struktur tabel pembelian yang Anda miliki
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM purchase_history 
                           WHERE player_id = ? AND purchase_date BETWEEN ? AND ?");
    $stmt->bind_param("iss", $playerId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'] ?? 0;
}

function hasClaimedRebate($conn, $playerId, $rebateId, $tierId) {
    $stmt = $conn->prepare("SELECT id FROM player_rebate_claims 
                           WHERE player_id = ? AND rebate_id = ? AND tier_id = ?");
    $stmt->bind_param("iii", $playerId, $rebateId, $tierId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}
?>