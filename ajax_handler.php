<?php
// ajax_handler.php
// Untuk permintaan AJAX dari dashboard

session_start();
include("config/database.php");

// Verifikasi dengan gmsenditem.php (jika diperlukan)
define('API_SECRET_KEY', 'v7r4gn4r0kXD_s3cur3_4P1_k3y_2025#');

// Cek autentikasi dan pastikan user_id ada di session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Cek permission
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, status, account FROM account WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

// Jika user tidak ditemukan, kembalikan error
if (!$userData) {
    echo json_encode(['success' => false, 'message' => 'User data not found']);
    exit;
}

// Log pengguna untuk debugging
error_log("User ID: " . $userId . ", Status: " . $userData['status'] . ", Account: " . $userData['account']);

// Ambil request
$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

// Search character
if ($action === 'search_characters') {
    $query = $_POST['query'] ?? '';
    
    if (strlen($query) < 2) {
        $response = ['success' => false, 'message' => 'Search query too short'];
    } else {
        try {
            $searchTerm = '%' . $query . '%';
            
            // Jika search (mungkin langsung mencari charid)
            if (is_numeric($query)) {
                $exactCharid = (int)$query;
                // Exact charid
                $stmt = $conn->prepare("SELECT c.charid as id, c.name, c.rolelv, a.account as account_name 
                                      FROM ro_xd_r2.charbase c
                                      JOIN account a ON c.accid = a.id
                                      WHERE c.charid = ?");
                $stmt->bind_param("i", $exactCharid);
            } else {
                // Search name atau account
                $stmt = $conn->prepare("SELECT c.charid as id, c.name, c.rolelv, a.account as account_name 
                                      FROM ro_xd_r2.charbase c
                                      JOIN account a ON c.accid = a.id
                                      WHERE c.name LIKE ? OR a.account LIKE ?
                                      ORDER BY c.name ASC LIMIT 50");
                $stmt->bind_param("ss", $searchTerm, $searchTerm);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $characters = [];
            while ($row = $result->fetch_assoc()) {
                $characters[] = $row;
            }
            
            $response = ['success' => true, 'characters' => $characters];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
// Search account
else if ($action === 'search_accounts') {
    // Hanya admin yang boleh mencari account
    if ($userData['status'] != 99) {
        $response = ['success' => false, 'message' => 'Permission denied'];
        echo json_encode($response);
        exit;
    }
    
    $query = $_POST['query'] ?? '';
    
    if (strlen($query) < 2) {
        $response = ['success' => false, 'message' => 'Search query too short'];
    } else {
        try {
            $searchTerm = '%' . $query . '%';
            $stmt = $conn->prepare("SELECT id, account, money FROM account 
                                  WHERE account LIKE ? ORDER BY account ASC LIMIT 50");
            $stmt->bind_param("s", $searchTerm);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $accounts = [];
            while ($row = $result->fetch_assoc()) {
                $accounts[] = $row;
            }
            
            $response = ['success' => true, 'accounts' => $accounts];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
// Kirim item ke player - disesuaikan dengan gmsenditem.php
else if ($action === 'send_item') {
    // Hanya admin yang boleh mengirim item
    if ($userData['status'] != 99) {
        $response = ['success' => false, 'message' => 'Permission denied'];
        echo json_encode($response);
        exit;
    }
    
    $characterId = filter_var($_POST['character_id'] ?? 0, FILTER_VALIDATE_INT);
    $itemId = filter_var($_POST['item_id'] ?? 0, FILTER_VALIDATE_INT);
    $amount = filter_var($_POST['amount'] ?? 1, FILTER_VALIDATE_INT);
    $title = $_POST['title'] ?? 'Admin Gift';
    $description = $_POST['description'] ?? 'Special item from administration team';
    
    if ($characterId <= 0 || $itemId <= 0 || $amount <= 0 || $amount > 100) {
        $response = ['success' => false, 'message' => 'Invalid input parameters'];
    } else {
        try {
            // Cek karakter valid
            $stmt = $conn->prepare("SELECT charid, name FROM ro_xd_r2.charbase WHERE charid = ?");
            $stmt->bind_param("i", $characterId);
            $stmt->execute();
            $result = $stmt->get_result();
            $charData = $result->fetch_assoc();
            
            if (!$charData) {
                $response = ['success' => false, 'message' => 'Character ID not found'];
            } else {
                $apiUrl = 'https://gm.ragnaroketernal.asia/gmsenditem.php';
                
                // Log request untuk debug
                $adminId = $_SESSION['user_id'];
                $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, created_at) 
                                      VALUES (?, 'send_item_request', ?, NOW())");
                $debugInfo = json_encode([
                    'charid' => $characterId,
                    'character_name' => $charData['name'],
                    'itemcode' => $itemId,
                    'amount' => $amount,
                    'title' => $title,
                    'desc' => $description
                ]);
                $stmt->bind_param("is", $adminId, $debugInfo);
                $stmt->execute();
                
                // Inisialisasi curl
                $ch = curl_init();
                
                // Set URL dan opsi curl yang benar
                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                // Penting: Gunakan format parameter yang sama persis seperti curl command
                curl_setopt($ch, CURLOPT_POSTFIELDS, 
                    "charid=" . $characterId . 
                    "&itemcode=" . $itemId . 
                    "&amount=" . $amount . 
                    "&title=" . urlencode($title) . 
                    "&desc=" . urlencode($description)
                );
                
                // Eksekusi curl
                $result = curl_exec($ch);
                $error = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                // Log response untuk debug
                $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, created_at) 
                                      VALUES (?, 'send_item_response', ?, NOW())");
                $responseInfo = json_encode([
                    'http_code' => $httpCode,
                    'curl_error' => $error,
                    'response' => $result
                ]);
                $stmt->bind_param("is", $adminId, $responseInfo);
                $stmt->execute();
                
                if (!empty($error)) {
                    throw new Exception("Connection error: " . $error);
                }
                
                $apiResponse = json_decode($result, true);
                
                if (isset($apiResponse['status']) && $apiResponse['status'] === 'success') {
                    // Log success
                    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, created_at) 
                                          VALUES (?, 'send_item_success', ?, NOW())");
                    $successInfo = json_encode([
                        'character_id' => $characterId,
                        'character_name' => $charData['name'],
                        'item_id' => $itemId,
                        'amount' => $amount
                    ]);
                    $stmt->bind_param("is", $adminId, $successInfo);
                    $stmt->execute();
                    
                    $response = ['success' => true, 'message' => 'Item sent successfully to ' . $charData['name']];
                } else {
                    $errorMsg = $apiResponse['message'] ?? 'Unknown error';
                    throw new Exception("API Error: " . $errorMsg);
                }
            }
        } catch (Exception $e) {
            // Log error
            $adminId = $_SESSION['user_id'];
            $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, created_at) 
                                  VALUES (?, 'send_item_error', ?, NOW())");
            $errorDetails = $e->getMessage();
            $stmt->bind_param("is", $adminId, $errorDetails);
            $stmt->execute();
            
            $response = ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }
}
// Handler untuk mengirim balance dari agent ke player
else if ($action === 'send_balance_agent') {
    // Hanya admin (99) dan agenttopup (20) yang boleh mengirim balance
    if ($userData['status'] != 99 && $userData['status'] != 20) {
        error_log("Permission denied for send_balance_agent. User status: " . $userData['status']);
        $response = ['success' => false, 'message' => 'Permission denied'];
        echo json_encode($response);
        exit;
    }
    
    $recipientUsername = $_POST['username'] ?? '';
    $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_INT);
    $note = $_POST['note'] ?? 'Balance transfer';
    
    // Log request untuk debugging
    error_log("Send Balance Agent Request - Username: $recipientUsername, Amount: $amount, Note: $note");
    
    // Validasi input
    if (empty($recipientUsername) || $amount <= 0) {
        $response = ['success' => false, 'message' => 'Invalid input parameters'];
    } else {
        try {
            // Begin transaction
            $conn->begin_transaction();
            
            // Get sender account data
            $stmt = $conn->prepare("SELECT money FROM account WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $senderData = $result->fetch_assoc();
            
            if (!$senderData) {
                throw new Exception("Sender account not found");
            }
            
            // Check if sender has enough balance
            if ($senderData['money'] < $amount) {
                throw new Exception("Insufficient balance");
            }
            
            // Get recipient account data
            $stmt = $conn->prepare("SELECT id, account, money FROM account WHERE account = ? FOR UPDATE");
            $stmt->bind_param("s", $recipientUsername);
            $stmt->execute();
            $result = $stmt->get_result();
            $recipientData = $result->fetch_assoc();
            
            if (!$recipientData) {
                throw new Exception("Recipient account not found");
            }
            
            // Simpan old balance dan new balance
            $oldRecipientBalance = $recipientData['money'];
            $newRecipientBalance = $oldRecipientBalance + $amount;
            
            // Update sender balance
            $newSenderBalance = $senderData['money'] - $amount;
            $stmt = $conn->prepare("UPDATE account SET money = ? WHERE id = ?");
            $stmt->bind_param("ii", $newSenderBalance, $userId);
            $stmt->execute();
            
            if ($stmt->affected_rows != 1) {
                throw new Exception("Failed to update sender balance");
            }
            
            // Update recipient balance
            $stmt = $conn->prepare("UPDATE account SET money = ? WHERE id = ?");
            $stmt->bind_param("ii", $newRecipientBalance, $recipientData['id']);
            $stmt->execute();
            
            if ($stmt->affected_rows != 1) {
                throw new Exception("Failed to update recipient balance");
            }
            
            // Generate transaction ID
            $transactionId = 'TOPUP_' . time() . '_' . rand(1000, 9999);
            
            // Record transaction dengan old_balance dan new_balance
            $stmt = $conn->prepare("INSERT INTO topup_history (agent_id, recipient_id, amount, transaction_id, transaction_date, remaining_balance, note, old_balance, new_balance) 
                                   VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)");
            $stmt->bind_param("iiisisii", $userId, $recipientData['id'], $amount, $transactionId, $newSenderBalance, $note, $oldRecipientBalance, $newRecipientBalance);
            $stmt->execute();
            
            // Log admin action
            $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, created_at) 
                                   VALUES (?, 'send_balance', ?, NOW())");
            $details = json_encode([
                'recipient' => $recipientUsername,
                'recipient_id' => $recipientData['id'],
                'amount' => $amount,
                'old_balance' => $oldRecipientBalance,
                'new_balance' => $newRecipientBalance,
                'transaction_id' => $transactionId
            ]);
            $stmt->bind_param("is", $userId, $details);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $response = ['success' => true, 'message' => 'Balance sent successfully to ' . $recipientUsername];
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            error_log("Error sending balance: " . $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
// Handler untuk menambah balance ke account
else if ($action === 'add_balance') {
    // Hanya admin yang boleh menambah balance
    if ($userData['status'] != 99) {
        error_log("Permission denied for add_balance. User status: " . $userData['status']);
        $response = ['success' => false, 'message' => 'Permission denied'];
        echo json_encode($response);
        exit;
    }
    
    $accountId = filter_var($_POST['account_id'] ?? 0, FILTER_VALIDATE_INT);
    $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_INT);
    $note = $_POST['note'] ?? 'Admin balance addition';
    
    // Log request untuk debugging
    error_log("Add Balance Request - Account ID: $accountId, Amount: $amount, Note: $note");
    
    // Validasi input
    if ($accountId <= 0 || $amount <= 0) {
        $response = ['success' => false, 'message' => 'Invalid input parameters'];
    } else {
        try {
            // Begin transaction
            $conn->begin_transaction();
            
            // Get current balance
            $stmt = $conn->prepare("SELECT money FROM account WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $accountId);
            $stmt->execute();
            $result = $stmt->get_result();
            $accountData = $result->fetch_assoc();
            
            if (!$accountData) {
                throw new Exception("Account not found");
            }
            
            // Update balance
            $newBalance = $accountData['money'] + $amount;
            $stmt = $conn->prepare("UPDATE account SET money = ? WHERE id = ?");
            $stmt->bind_param("ii", $newBalance, $accountId);
            $stmt->execute();
            
            if ($stmt->affected_rows != 1) {
                throw new Exception("Failed to update balance");
            }
            
            // Generate transaction ID
            $transactionId = 'ADMIN_TOPUP_' . time() . '_' . rand(1000, 9999);
            
            // Record transaction
            $adminId = $_SESSION['user_id'];
            $stmt = $conn->prepare("INSERT INTO topup_history (agent_id, recipient_id, amount, transaction_id, transaction_date, remaining_balance, note) 
                                   VALUES (?, ?, ?, ?, NOW(), ?, ?)");
            $stmt->bind_param("iiisis", $adminId, $accountId, $amount, $transactionId, $newBalance, $note);
            $stmt->execute();
            
            // Log admin action
            $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, created_at) 
                                   VALUES (?, 'add_balance', ?, NOW())");
            $details = json_encode([
                'account_id' => $accountId,
                'amount' => $amount,
                'transaction_id' => $transactionId
            ]);
            $stmt->bind_param("is", $adminId, $details);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $response = ['success' => true, 'message' => 'Balance added successfully'];
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            error_log("Error adding balance: " . $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
// Rebate Management - GET Rebates
else if ($action === 'get_rebates') {
    // Jika user adalah admin, ambil semua rebates
    if ($userData['status'] == 99) {
        $stmt = $conn->prepare("SELECT * FROM rebate_settings ORDER BY id DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $rebates = [];
        while ($row = $result->fetch_assoc()) {
            $rebates[] = $row;
        }
        
        $response = ['success' => true, 'rebates' => $rebates];
    } 
    // Jika user biasa, hanya ambil yang aktif
    else {
        $stmt = $conn->prepare("SELECT * FROM rebate_settings WHERE status = 'active' 
                               AND NOW() BETWEEN start_date AND end_date ORDER BY id DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $rebates = [];
        while ($row = $result->fetch_assoc()) {
            $rebates[] = $row;
        }
        
        $response = ['success' => true, 'rebates' => $rebates];
    }
}
// Create Rebate - Admin only
else if ($action === 'create_rebate') {
    if ($userData['status'] != 99) {
        $response = ['success' => false, 'message' => 'Permission denied'];
    } else {
        $name = $_POST['name'] ?? '';
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $status = $_POST['status'] ?? 'inactive';
        
        try {
            $stmt = $conn->prepare("INSERT INTO rebate_settings (name, start_date, end_date, status) 
                                 VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $startDate, $endDate, $status);
            $stmt->execute();
            $rebateId = $conn->insert_id;
            
            $response = ['success' => true, 'message' => 'Rebate created successfully', 'rebate_id' => $rebateId];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Error creating rebate: ' . $e->getMessage()];
        }
    }
}
// Get Rebate Tiers
else if ($action === 'get_rebate_tiers') {
    $rebateId = $_POST['rebate_id'] ?? 0;
    
    try {
        // Ambil rebate info terlebih dahulu
        $stmt = $conn->prepare("SELECT * FROM rebate_settings WHERE id = ?");
        $stmt->bind_param("i", $rebateId);
        $stmt->execute();
        $rebate = $stmt->get_result()->fetch_assoc();
        
        if (!$rebate) {
            $response = ['success' => false, 'message' => 'Rebate not found'];
        } else {
            // Ambil tiers
            $stmt = $conn->prepare("SELECT t.*, 
                                (SELECT COUNT(*) FROM rebate_rewards WHERE tier_id = t.id) as reward_count
                                FROM rebate_tiers t
                                WHERE t.rebate_id = ?
                                ORDER BY t.spend_amount ASC");
            $stmt->bind_param("i", $rebateId);
            $stmt->execute();
            $tiersResult = $stmt->get_result();
            
            $tiers = [];
            while ($row = $tiersResult->fetch_assoc()) {
                // Cek apakah user sudah klaim tier ini
                if (isset($_SESSION['user_id'])) {
                    $userId = $_SESSION['user_id'];
                    $stmtClaimed = $conn->prepare("SELECT id FROM player_rebate_claims 
                                           WHERE player_id = ? AND rebate_id = ? AND tier_id = ?");
                    $stmtClaimed->bind_param("iii", $userId, $rebateId, $row['id']);
                    $stmtClaimed->execute();
                    $row['claimed'] = ($stmtClaimed->get_result()->num_rows > 0);
                } else {
                    $row['claimed'] = false;
                }
                
                $tiers[] = $row;
            }
            
            $response = ['success' => true, 'rebate' => $rebate, 'tiers' => $tiers];
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Error getting tiers: ' . $e->getMessage()];
    }
}
// Add Tier - Admin only
else if ($action === 'add_rebate_tier') {
    if ($userData['status'] != 99) {
        $response = ['success' => false, 'message' => 'Permission denied'];
    } else {
        $rebateId = $_POST['rebate_id'] ?? 0;
        $spendAmount = $_POST['spend_amount'] ?? 0;
        
        try {
            $stmt = $conn->prepare("INSERT INTO rebate_tiers (rebate_id, spend_amount) VALUES (?, ?)");
            $stmt->bind_param("ii", $rebateId, $spendAmount);
            $stmt->execute();
            $tierId = $conn->insert_id;
            
            $response = ['success' => true, 'message' => 'Tier added successfully', 'tier_id' => $tierId];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Error adding tier: ' . $e->getMessage()];
        }
    }
}
// Add Reward to Tier - Admin only
else if ($action === 'add_rebate_reward') {
    if ($userData['status'] != 99) {
        $response = ['success' => false, 'message' => 'Permission denied'];
    } else {
        $tierId = $_POST['tier_id'] ?? 0;
        $itemId = $_POST['item_id'] ?? 0;
        $quantity = $_POST['quantity'] ?? 1;
        
        try {
            $stmt = $conn->prepare("INSERT INTO rebate_rewards (tier_id, item_id, quantity) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $tierId, $itemId, $quantity);
            $stmt->execute();
            $rewardId = $conn->insert_id;
            
            $response = ['success' => true, 'message' => 'Reward added successfully', 'reward_id' => $rewardId];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Error adding reward: ' . $e->getMessage()];
        }
    }
}
// Get Rewards for a Tier
else if ($action === 'get_tier_rewards') {
    $tierId = $_POST['tier_id'] ?? 0;
    
    try {
        $stmt = $conn->prepare("SELECT r.*, i.name as item_name 
                              FROM rebate_rewards r 
                              LEFT JOIN items i ON r.item_id = i.id
                              WHERE r.tier_id = ?");
        $stmt->bind_param("i", $tierId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $rewards = [];
        while ($row = $result->fetch_assoc()) {
            // Fallback jika nama item tidak ditemukan
            if (!$row['item_name']) {
                $row['item_name'] = 'Item #' . $row['item_id'];
            }
            $rewards[] = $row;
        }
        
        $response = ['success' => true, 'rewards' => $rewards];
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Error getting rewards: ' . $e->getMessage()];
    }
}
// Claim Rebate Reward
else if ($action === 'claim_rebate') {
    $rebateId = $_POST['rebate_id'] ?? 0;
    $tierId = $_POST['tier_id'] ?? 0;
    $playerId = $_SESSION['user_id'];
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Check if rebate exists and is active
        $stmt = $conn->prepare("SELECT * FROM rebate_settings WHERE id = ? AND status = 'active'");
        $stmt->bind_param("i", $rebateId);
        $stmt->execute();
        $rebate = $stmt->get_result()->fetch_assoc();
        
        if (!$rebate) {
            throw new Exception("Rebate not found or not active");
        }
        
        // Check if tier exists
        $stmt = $conn->prepare("SELECT * FROM rebate_tiers WHERE id = ? AND rebate_id = ?");
        $stmt->bind_param("ii", $tierId, $rebateId);
        $stmt->execute();
        $tier = $stmt->get_result()->fetch_assoc();
        
        if (!$tier) {
            throw new Exception("Tier not found");
        }
        
        // Check if already claimed
        $stmt = $conn->prepare("SELECT id FROM player_rebate_claims 
                               WHERE player_id = ? AND rebate_id = ? AND tier_id = ?");
        $stmt->bind_param("iii", $playerId, $rebateId, $tierId);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("You have already claimed this reward");
        }
        
        // Record the claim
        $stmt = $conn->prepare("INSERT INTO player_rebate_claims (player_id, rebate_id, tier_id, claim_date) 
                               VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iii", $playerId, $rebateId, $tierId);
        $stmt->execute();
        
        // Get rewards for this tier
        $stmt = $conn->prepare("SELECT r.*, i.name as item_name 
                              FROM rebate_rewards r 
                              LEFT JOIN items i ON r.item_id = i.id
                              WHERE r.tier_id = ?");
        $stmt->bind_param("i", $tierId);
        $stmt->execute();
        $rewardsResult = $stmt->get_result();
        
        // Send rewards to player
        $rewards = [];
        while ($reward = $rewardsResult->fetch_assoc()) {
            $rewards[] = $reward;
            
            // TODO: Add code here to send the actual item to the player
            // This could be via the GM API or direct database insert
        }
        
        // Commit transaction
        $conn->commit();
        
        $response = ['success' => true, 'message' => 'Reward claimed successfully', 'rewards' => $rewards];
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
}
// Claim Gift Code
else if ($action === 'claim_gift_code') {
    $code = $_POST['code'] ?? '';
    $charId = (int)($_POST['charid'] ?? 0);

    // Validasi input
    if (empty($code) || $charId <= 0) {
        $response = ['success' => false, 'message' => 'Invalid input'];
        echo json_encode($response);
        exit;
    }

    try {
        // Validasi bahwa charid milik akun yang login
        $stmt = $conn_ro->prepare("SELECT accid FROM charbase WHERE charid = ?");
        $stmt->bind_param("i", $charId);
        $stmt->execute();
        $result = $stmt->get_result();
        $charData = $result->fetch_assoc();

        if (!$charData || $charData['accid'] != $_SESSION['accid']) {
            $response = ['success' => false, 'message' => 'Character does not belong to your account'];
            echo json_encode($response);
            exit;
        }

        // Validasi gift code
        $stmt = $conn->prepare("SELECT * FROM gift_codes WHERE code = ? AND start_time <= NOW() AND end_time >= NOW()");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $giftCode = $result->fetch_assoc();

        if (!$giftCode) {
            $response = ['success' => false, 'message' => 'Invalid or expired gift code'];
            echo json_encode($response);
            exit;
        }

        // Cek apakah gift code sudah diklaim
        $stmt = $conn->prepare("SELECT id FROM gift_code_claims WHERE gift_code_id = ? AND accid = ?");
        $stmt->bind_param("ii", $giftCode['id'], $_SESSION['accid']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $response = ['success' => false, 'message' => 'You have already claimed this gift code'];
            echo json_encode($response);
            exit;
        }

        // Tambahkan klaim
        $stmt = $conn->prepare("INSERT INTO gift_code_claims (gift_code_id, accid) VALUES (?, ?)");
        $stmt->bind_param("ii", $giftCode['id'], $_SESSION['accid']);
        $stmt->execute();

        // Kirim item ke karakter
        $items = json_decode($giftCode['item_list'], true);
        foreach ($items as $item) {
            // Tambahkan logika untuk memberikan item ke karakter
        }

        $response = ['success' => true, 'message' => 'Gift code claimed successfully', 'items' => $items];
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }

    echo json_encode($response);
} else if ($_POST['action'] === 'create_gift_code') {
    $name = $_POST['giftcode_name'];
    $items = json_encode($_POST['items']);
    $isGlobal = $_POST['is_global'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];

    $stmt = $conn->prepare("INSERT INTO gift_codes (name, items, is_global, start_time, end_time) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiss", $name, $items, $isGlobal, $startTime, $endTime);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create gift code']);
    }
    $stmt->close();
} else if ($_GET['action'] === 'get_gift_codes') {
    $result = $conn->query("SELECT * FROM gift_codes");
    $giftcodes = [];
    while ($row = $result->fetch_assoc()) {
        $giftcodes[] = $row;
    }
    echo json_encode(['success' => true, 'giftcodes' => $giftcodes]);
} else if ($_GET['action'] === 'delete_gift_code') {
    $id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM gift_codes WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete gift code']);
    }
    $stmt->close();
}

// Output final JSON response - PENTING: selalu tambahkan ini di akhir file
echo json_encode($response);