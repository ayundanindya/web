<?php
session_start();

// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login");
    exit;
}

include("config/database.php");

// Get user data
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, account, email, safetycode, status, money FROM account WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

// Map status to role names
$roleMap = [
    1 => 'player',
    20 => 'agenttopup',
    50 => 'gamemaster',
    99 => 'administrator'
];

// Default role is 'player' if status is not recognized
$userRole = isset($roleMap[$userData['status']]) ? $roleMap[$userData['status']] : 'player';

// Get current section or default to profile
$section = isset($_GET['section']) ? $_GET['section'] : 'profile';

// Function to get character list from ro_xd_r2.charbase
function getCharacterList($accountId, $conn) {
    try {
        // Debug: Log the account ID
        error_log("Getting characters for account ID: " . $accountId);
        
        // PERBAIKAN: Gunakan kolom accid, bukan accountid
        $stmt = $conn->prepare("SELECT c.charid as id, c.name, c.rolelv, c.profession 
                               FROM ro_xd_r2.charbase c 
                               WHERE c.accid = ?");
        
        // Debug: Check if prepare statement succeeded
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            return [];
        }
        
        $stmt->bind_param("i", $accountId);
        
        // Debug: Check if execution succeeded
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            return [];
        }
        
        $result = $stmt->get_result();
        $characters = [];
        
        // Debug: Check if we got any results
        if ($result->num_rows === 0) {
            error_log("No characters found for account ID: " . $accountId);
        }
        
        while ($row = $result->fetch_assoc()) {
            $characters[] = $row;
            // Debug: Log each character found
            error_log("Found character: " . $row['name'] . " (ID: " . $row['id'] . ")");
        }
        
        $stmt->close();
        return $characters;
    } catch (Exception $e) {
        error_log("Exception in getCharacterList: " . $e->getMessage());
        return [];
    }
}

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
}// Function to get all products from shop
function getShopProducts($conn) {
    try {
        $stmt = $conn->prepare("SELECT * FROM product WHERE status = 1 ORDER BY id");
        $stmt->execute();
        $result = $stmt->get_result();
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $stmt->close();
        return $products;
    } catch (Exception $e) {
        return [];
    }
}

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

// Get character list for logged in user
$characters = getCharacterList($userId, $conn);

// Get purchase history for logged in user
$purchases = getPurchaseHistory($userId, $conn);

// Get donation history for logged in user
$donations = getDonationHistory($userId, $conn);

// Get shop products
$shopProducts = getShopProducts($conn);

// Get account stats for admin panel
$accountStats = ($userRole == 'administrator') ? getAccountStats($conn) : [];

// Inisialisasi $topupHistory
$topupHistory = [];

// Hanya ambil topup history jika user adalah agent atau admin
if ($userRole == 'agenttopup' || $userRole == 'administrator') {
    $topupHistory = getTopupHistory($userId, $conn);
}

// Inisialisasi $bannedUsers
$bannedUsers = [];

// Hanya ambil banned users jika user adalah game master atau administrator
if ($userRole == 'gamemaster' || $userRole == 'administrator') {
    $bannedUsers = getBannedUsers($conn);
}

// Job class data
$jobClasses = [
    1 => "Novice",
    11 => "Swordman",
    12 => "Knight",
    13 => "LordKnight",
    14 => "RuneKnight",
    21 => "Magician",
    22 => "Wizard",
    23 => "HighWizard",
    24 => "Warlock",
    31 => "Thief",
    32 => "Assassin",
    33 => "AssassinCross",
    34 => "GuillotineCross",
    41 => "Archer",
    42 => "Hunter",
    43 => "Sniper",
    44 => "Ranger",
    51 => "Acolyte",
    52 => "Priest",
    53 => "HighPriest",
    54 => "Archbishop",
    61 => "Merchant",
    62 => "Blacksmith",
    63 => "Whitesmith",
    64 => "Mechanic",
    72 => "Crusader",
    73 => "Paladin",
    74 => "RoyalGuard",
    82 => "Sage",
    83 => "Professor",
    84 => "Sorcerer",
    92 => "Rogue",
    93 => "Stalker",
    94 => "ShadowChaser",
    102 => "Bard",
    103 => "Clown",
    104 => "Minstrel",
    112 => "Dancer",
    113 => "Gypsy",
    114 => "Wanderer",
    122 => "Monk",
    123 => "Champion",
    124 => "Shura",
    132 => "Alchemist",
    133 => "Creator",
    134 => "Genetic",
    500 => "RiskSkill"
];

// Function to get profession name
function getProfessionName($professionId, $jobClasses) {
    return isset($jobClasses[$professionId]) ? $jobClasses[$professionId] : "Unknown";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - V-Ragnarok Mobile</title>
    <link rel="shortcut icon" href="/images/favicon.ico" type="image/x-icon">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 for popups -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(to bottom, #6a11cb, #2575fc);
            color: #333;
            min-height: 100vh;
        }
        
        .dashboard-container {
            display: flex;
            flex-wrap: wrap;
            max-width: 1200px;
            margin: 20px auto;
            gap: 20px;
        }
        
        .sidebar {
            flex: 1;
            min-width: 250px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 20px;
        }
        
        .main-content {
            flex: 3;
            min-width: 300px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(145deg, #6a11cb, #8844e0);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        
        .user-details {
            flex-grow: 1;
        }
        
        .user-name {
            font-size: 18px;
            font-weight: bold;
            color: #4a5568;
            margin: 0;
        }
        
        .user-role {
            font-size: 14px;
            color: #6a11cb;
            margin: 5px 0 0;
            display: inline-block;
            padding: 2px 10px;
            background: rgba(106, 17, 203, 0.1);
            border-radius: 20px;
        }
        
        .user-balance {
            font-size: 14px;
            color: #4caf50;
            margin: 5px 0 0;
        }
        
        .nav-menu {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-menu li {
            margin-bottom: 5px;
        }
        
        .nav-menu li a {
            display: block;
            padding: 12px 15px;
            text-decoration: none;
            color: #4a5568;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .nav-menu li a:hover {
            background: #f5f5f5;
            color: #6a11cb;
        }
        
        .nav-menu li a.active {
            background: rgba(106, 17, 203, 0.1);
            color: #6a11cb;
            font-weight: bold;
        }
        
        .nav-menu li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .section-title {
            color: #6a11cb;
            font-size: 24px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .logout-btn {
            display: block;
            margin-top: 20px;
            padding: 12px 15px;
            background: #f44336;
            color: white;
            text-decoration: none;
            text-align: center;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #d32f2f;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #4a5568;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .btn {
            background: linear-gradient(145deg, #6a11cb, #8844e0);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: linear-gradient(145deg, #5a0cb6, #7634d0);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .btn-danger {
            background: linear-gradient(145deg, #f44336, #d32f2f);
        }
        
        .btn-danger:hover {
            background: linear-gradient(145deg, #d32f2f, #b71c1c);
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        table th {
            background-color: #f5f5f5;
            font-weight: bold;
            color: #4a5568;
        }
        
        table tr:hover {
            background-color: #f9f9f9;
        }
        
        .discord-promo {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px 20px;
            text-align: center;
            background: linear-gradient(145deg, #7289da, #5b6eae);
            color: white;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .discord-promo i {
            font-size: 60px;
            margin-bottom: 20px;
        }
        
        .discord-promo h3 {
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .discord-promo p {
            margin-bottom: 20px;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .discord-btn {
            display: inline-block;
            background-color: white;
            color: #7289da;
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .discord-btn:hover {
            background-color: #f5f5f5;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .shop-items {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .shop-item {
            position: relative;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .shop-item-info-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        .shop-item-info-btn:hover {
            background-color: #6a11cb;
            color: white;
            transform: scale(1.1);
        }

        .shop-item-info-btn i {
            font-size: 16px;
            color: #6a11cb;
        }
        .shop-item-info-btn:hover i {
            color: white;
        }
        .shop-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .shop-item-image {
            height: 150px;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .shop-item-image img {
            max-width: 100%;
            max-height: 100%;
        }
        
        .shop-item-content {
            padding: 15px;
        }
        
        .shop-item-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
            color: #4a5568;
        }
        
        .shop-item-price {
            color: #6a11cb;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .shop-item-btn {
            display: block;
            background: linear-gradient(145deg, #6a11cb, #8844e0);
            color: white;
            text-align: center;
            padding: 8px 0;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s ease;
        }
        
        .shop-item-btn:hover {
            background: linear-gradient(145deg, #5a0cb6, #7634d0);
        }
        
        .tab-buttons {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 20px;
            overflow-x: auto;
        }
        
        .tab-button {
            padding: 10px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #6b7280;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: max-content;
        }
        
        .tab-button.active {
            color: #6a11cb;
            border-bottom-color: #6a11cb;
        }
        
        .tab-button:hover:not(.active) {
            color: #4a5568;
            background-color: #f9f9f9;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .admin-stat-boxes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .admin-stat-box {
            background: linear-gradient(145deg, #f3f4f6, #e5e7eb);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .admin-stat-box i {
            font-size: 30px;
            color: #6a11cb;
            margin-bottom: 10px;
        }
        
        .admin-stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #4a5568;
            margin-bottom: 5px;
        }
        
        .admin-stat-label {
            color: #6b7280;
            font-size: 14px;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-success {
            background-color: #4caf50;
            color: white;
        }
        
        .badge-danger {
            background-color: #f44336;
            color: white;
        }
        
        .badge-warning {
            background-color: #ff9800;
            color: white;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                order: 2;
            }
            
            .main-content {
                order: 1;
            }
            
            .admin-stat-boxes {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }

        /* Tambahan CSS untuk memastikan modal tampil dengan benar */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-dialog {
            background: white;
            margin: 10% auto;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .modal-content {
            position: relative;
        }

        .modal-header {
            padding: 15px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px;
            text-align: right;
            border-top: 1px solid #e5e5e5;
        }

        .close {
            float: right;
            font-size: 24px;
            font-weight: 700;
            line-height: 1;
            color: #000;
            opacity: 0.5;
            background: none;
            border: none;
            cursor: pointer;
        }

        .close:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="sidebar">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo substr($userData['account'], 0, 1); ?>
            </div>
            <div class="user-details">
                <p class="user-name"><?php echo htmlspecialchars($userData['account']); ?></p>
                <span class="user-role"><?php echo ucfirst(htmlspecialchars($userRole)); ?></span>
                <?php if ($userRole == 'agenttopup' || $userRole == 'administrator'): ?>
                    <p class="user-balance">Balance: <?php echo number_format($userData['money']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <ul class="nav-menu">
            <li>
                <a href="?section=profile" class="<?php echo ($section == 'profile') ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> Account Profile
                </a>
            </li>
            <li>
                <a href="?section=donate" class="<?php echo ($section == 'donate') ? 'active' : ''; ?>">
                    <i class="fas fa-gift"></i> Donate
                </a>
            </li>
            <li>
                <a href="?section=shop" class="<?php echo ($section == 'shop') ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i> Shop
                </a>
            </li>
            <li>
            <a href="?section=giftcode" class="<?php echo ($section == 'giftcode') ? 'active' : ''; ?>">
                <i class="fas fa-gift"></i> Gift Code
                </a>
            </li>
                <li>
                <a href="?section=donation-history" class="<?php echo ($section == 'donation-history') ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> Donation History
                </a>
            </li>
            <li>
                <a href="?section=purchase-history" class="<?php echo ($section == 'purchase-history') ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i> Purchase History
                </a>
            </li>
            <?php if ($userRole == 'gamemaster' || $userRole == 'administrator'): ?>
                <li>
                    <a href="?section=gm" class="<?php echo ($section == 'gm') ? 'active' : ''; ?>">
                        <i class="fas fa-gavel"></i> Game Master
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($userRole == 'agenttopup' || $userRole == 'administrator'): ?>
                <li>
                    <a href="?section=agent" class="<?php echo ($section == 'agent') ? 'active' : ''; ?>">
                        <i class="fas fa-coins"></i> Agent Topup
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($userRole == 'administrator'): ?>
                <li>
                    <a href="?section=admin" class="<?php echo ($section == 'admin') ? 'active' : ''; ?>">
                        <i class="fas fa-shield-alt"></i> Admin Panel
                    </a>
                </li>
                <li>
            <?php endif; ?>
            <li>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>
    
<div class="main-content">
    <?php if ($section == 'profile'): ?>
        <!-- Account Profile Section -->
        <h2 class="section-title">Account Profile</h2>
        
        <div class="card">
            <h3>Change Password</h3>
            <form id="change-password-form">
                <div class="form-group">
                    <label for="current-password">Current Password</label>
                    <input type="password" id="current-password" name="current-password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="new-password">New Password</label>
                    <input type="password" id="new-password" name="new-password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="confirm-password">Confirm New Password</label>
                    <input type="password" id="confirm-password" name="confirm-password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Confirmation</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="pin-code">PIN Code</label>
                    <input type="password" id="pin-code" name="pin-code" class="form-control" required>
                </div>
                <button type="submit" class="btn">Change Password</button>
            </form>
        </div>
    <?php elseif ($section == 'donation-history'): ?>
        <!-- Donation History Section -->
        <h2 class="section-title">Donation History</h2>
        
        <div class="card">
            <h3>Donation History</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transaction ID</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Ambil data donasi dari database
                        $stmt = $conn->prepare("SELECT purchase_time AS transaction_date, transaction_id, amount FROM purchase_history WHERE accid = ? ORDER BY purchase_time DESC");
                        $stmt->bind_param("i", $userId);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0):
                            while ($donation = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($donation['transaction_date']); ?></td>
                                    <td><?php echo htmlspecialchars($donation['transaction_id']); ?></td>
                                    <td><?php echo number_format($donation['amount']); ?></td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center;">No donations yet</td>
                            </tr>
                        <?php endif;
                        $stmt->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    
    <?php elseif ($section == 'giftcode'): ?>
        <!-- Gift Code Section -->
        <h2 class="section-title">Gift Code</h2>
        
        <div class="card">
            <h3>Claim Gift Code</h3>
            <form id="claim-gift-code-form">
                <div class="form-group">
                    <label for="gift-code">Gift Code</label>
                    <input type="text" id="gift-code" name="code" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="charid">Select Character</label>
                    <select id="charid" name="charid" class="form-control" required>
                        <option value="">-- Select Character --</option>
                        <?php foreach ($characters as $character): ?>
                            <option value="<?php echo $character['id']; ?>">
                                <?php echo $character['name']; ?> (Level: <?php echo $character['rolelv']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Claim</button>
            </form>
        </div>             
                <div class="card">
                    <h3>Your Characters</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Level</th>
                                    <th>Class</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($characters) > 0): ?>
                                    <?php foreach ($characters as $character): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($character['name']); ?></td>
                                            <td><?php echo $character['rolelv']; ?></td>
                                            <td><?php echo getProfessionName($character['profession'], $jobClasses); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3">No characters found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                
            <?php elseif ($section == 'donate'): ?>
                <!-- Donate Section -->
                <h2 class="section-title">Donate to V-Ragnarok Mobile</h2>
                
                <div class="discord-promo">
                    <i class="fab fa-discord"></i>
                    <h3>Support Our Game Through Donations</h3>
                    <p>Donations help us maintain server quality and develop new content. For detailed information about donation methods and rewards, please join our Discord community.</p>
                    <a href="https://discord.gg/v-ragnarok" class="discord-btn">Join Our Discord</a>
                </div>
                
            <?php elseif ($section == 'shop'): ?>
            <!-- Shop Section -->
            <h2 class="section-title">V-Ragnarok Shop</h2>
            
            <div class="card" style="margin-bottom: 20px;">
                <h3>Your Balance: 
                    <span style="color: #6a11cb;"><?php echo number_format($userData['money']); ?></span>
                    <small>$</small>
                </h3>
            </div>
            <div class="shop-items">
                <?php if (count($shopProducts) > 0): ?>
                    <?php foreach ($shopProducts as $product): ?>
                        <div class="shop-item">
                            <!-- Simpan deskripsi dalam data attribute -->
                            <div class="shop-item-info-btn"
                                 data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                data-product-description="<?php echo htmlspecialchars($product['description']); ?>"
                                onclick="showProductInfoFromElement(this)">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            
                            <div class="shop-item-image">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/150?text=<?php echo urlencode($product['name']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="shop-item-content">
                                <h3 class="shop-item-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="shop-item-price"><?php echo number_format($product['price']); ?> $</div>
                                <a href="#" class="shop-item-btn" onclick="purchaseItem(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>)">Purchase</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 50px 0;">
                        <p>No products available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php elseif ($section == 'gm' && ($userRole == 'gamemaster' || $userRole == 'administrator')): ?>
                <!-- Game Master Panel Section -->
                <h2 class="section-title">Game Master Panel</h2>
                
                <div class="card">
                    <h3>Ban Player</h3>
                    <form id="ban-player-form">
                        <div class="form-group">
                            <label for="ban-type">Ban By</label>
                            <select id="ban-type" name="ban-type" class="form-control">
                                <option value="char_id">Character ID</option>
                                <option value="char_name">Character Name</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ban-value">Character ID / Name</label>
                            <input type="text" id="ban-value" name="ban-value" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="ban-reason">Ban Reason</label>
                            <textarea id="ban-reason" name="ban-reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="ban-duration">Ban Duration (days)</label>
                            <input type="number" id="ban-duration" name="ban-duration" class="form-control" min="1" value="7" required>
                        </div>
                        <button type="submit" class="btn btn-danger">Ban Player</button>
                    </form>
                </div>
                
                <div class="card">
                    <h3>Currently Banned Players</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Ban Date</th>
                                    <th>Expires</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($bannedUsers) && is_array($bannedUsers) && count($bannedUsers) > 0): ?>
                                    <?php foreach ($bannedUsers as $banned): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($banned['account'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($banned['start_date'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($banned['end_date'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($banned['reason'] ?? ''); ?></td>
                                        <td>
                                            <button class="btn btn-sm" onclick="unbanUser(<?php echo (int)($banned['id'] ?? 0); ?>)">
                                                Unban
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">No banned users found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            <?php elseif ($section == 'agent' && ($userRole == 'agenttopup' || $userRole == 'administrator')): ?>
                <!-- Agent Topup Section -->
                <h2 class="section-title">Agent Topup Panel</h2>
                
                <div class="card">
                    <h3>Your Balance: <span style="color: #6a11cb;"><?php echo number_format($userData['money']); ?></span></h3>
                </div>
                
                <div class="card">
                    <h3>Send Balance to Player</h3>
                    <form id="send-balance-form">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="amount">Amount</label>
                            <input type="number" id="amount" name="amount" class="form-control" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="note">Note (optional)</label>
                            <input type="text" id="note" name="note" class="form-control">
                        </div>
                        <button type="submit" class="btn">Send Balance</button>
                    </form>
                </div>
                
                <div class="card">
                    <h3>Balance Transfer History</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Username</th>
                                    <th>Amount</th>
                                    <th>Old Balance</th>
                                    <th>New Balance</th>
                                    <th>Your Remaining Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($topupHistory) > 0): ?>
                                    <?php foreach ($topupHistory as $transfer): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($transfer['transaction_date']) ?></td>
                                            <td><?= htmlspecialchars($transfer['recipient_username']) ?></td>
                                            <td><?= number_format($transfer['amount']) ?></td>
                                            <td><?= number_format($transfer['old_balance']) ?></td>
                                            <td><?= number_format($transfer['new_balance']) ?></td>
                                            <td><?= number_format($transfer['remaining_balance']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No transaction history found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            <?php elseif ($section == 'admin' && $userRole == 'administrator'): ?>
                <!-- Admin Panel Section -->
                <h2 class="section-title">Admin Panel</h2>
                
                <div class="admin-stat-boxes">
                    <div class="admin-stat-box">
                        <i class="fas fa-users"></i>
                        <div class="admin-stat-value"><?php echo number_format($accountStats['total']); ?></div>
                        <div class="admin-stat-label">Total Accounts</div>
                    </div>
                    
                    <div class="admin-stat-box">
                        <i class="fas fa-user-plus"></i>
                        <div class="admin-stat-value"><?php echo $accountStats['new_today']; ?></div>
                        <div class="admin-stat-label">New Today</div>
                    </div>
                    
                    <div class="admin-stat-box">
                        <i class="fas fa-comment"></i>
                        <div class="admin-stat-value"><?php echo $accountStats['active']; ?></div>
                        <div class="admin-stat-label">Active Players</div>
                    </div>
                    
                    <div class="admin-stat-box">
                        <i class="fas fa-shopping-cart"></i>
                        <div class="admin-stat-value"><?php echo number_format($accountStats['sales_today']); ?></div>
                        <div class="admin-stat-label">Today's Sales</div>
                    </div>
                </div>
                
                <div class="tab-buttons">
                <button class="tab-button active" data-tab="user-search">User Management</button>
                <button class="tab-button" data-tab="role-management">Role Management</button>
                <button class="tab-button" data-tab="send-items">Send Items & Money</button>
                <button class="tab-button" data-tab="transactions">Transaction History</button>
                <button class="tab-button" data-tab="Product-Management">Configuration Product</button>
                <button class="tab-button" data-tab="giftcode-management">Gift Code Management</button>
                </div>
                <div id="user-search" class="tab-content active">
                    <div class="card">
                        <h3>Search Users</h3>
                        <form id="search-user-form">
                            <div class="form-group">
                                <label for="search-username">Username</label>
                                <input type="text" id="search-username" name="search-username" class="form-control">
                            </div>
                            <button type="submit" class="btn">Search</button>
                        </form>
                    </div>
                    
                    <div class="card">
                        <h3>Change User Password</h3>
                        <form id="admin-change-password-form">
                            <div class="form-group">
                                <label for="admin-username">Username</label>
                                <input type="text" id="admin-username" name="admin-username" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="admin-new-password">New Password</label>
                                <input type="text" id="admin-new-password" name="admin-new-password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn">Change Password</button>
                        </form>
                    </div>
                    
                    <div class="card">
                        <h3>Change User PIN Code</h3>
                        <form id="admin-change-pin-form">
                            <div class="form-group">
                                <label for="pin-username">Username</label>
                                <input type="text" id="pin-username" name="pin-username" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="new-pin">New PIN Code</label>
                                <input type="text" id="new-pin" name="new-pin" class="form-control" required pattern="\d{1,6}" title="PIN must be up to 6 digits">
                            </div>
                            <button type="submit" class="btn">Change PIN</button>
                        </form>
                    </div>
                </div>
                
                <div id="Product-Management" class="tab-content">
                <div class="card">
                    <h3>Add New Product</h3>
                    <form id="add-product-form">
                        <div class="form-group">
                            <label for="product-name">Product Name</label>
                            <input type="text" id="product-name" name="product-name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="product-description">Description</label>
                            <textarea id="product-description" name="product-description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="product-price">Price</label>
                            <input type="number" id="product-price" name="product-price" class="form-control" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="product-items">Items</label>
                            <div id="items-container">
                                <div class="item-row" style="display: flex; margin-bottom: 10px;">
                                    <input type="number" class="form-control item-id" placeholder="Item ID" style="width: 40%; margin-right: 10px;" required>
                                    <input type="number" class="form-control item-count" placeholder="Count" style="width: 30%; margin-right: 10px;" min="1" value="1" required>
                                    <button type="button" class="btn remove-item" style="background-color: #f44336; display: none;"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                            <button type="button" id="add-item-btn" class="btn" style="margin-top: 10px;"><i class="fas fa-plus"></i> Add Another Item</button>
                            <input type="hidden" id="product-items" name="product-items">
                        </div>
                        <div class="form-group">
                            <label for="product-image">Image URL (optional)</label>
                            <input type="text" id="product-image" name="product-image" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="product-limit-type">Purchase Limit Type</label>
                            <select id="product-limit-type" name="product-limit-type" class="form-control">
                                <option value="">No Limit</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="product-limit-amount">Limit Amount</label>
                            <input type="number" id="product-limit-amount" name="product-limit-amount" class="form-control" min="1" value="1">
                            <small class="form-text text-muted">How many times a user can purchase this product within the limit period</small>
                        </div>
                        <button type="submit" class="btn">Add Product</button>
                    </form>
                </div>
                
                <div class="card">
                    <h3>Manage Products</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Items</th>
                                    <th>Limit</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="product-list">
                                <?php
                                // Get all products
                                $products = [];
                                try {
                                    $stmt = $conn->prepare("SELECT * FROM product ORDER BY id DESC");
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while ($row = $result->fetch_assoc()) {
                                        $products[] = $row;
                                    }
                                    $stmt->close();
                                } catch (Exception $e) {
                                    // Handle error
                                }
                                
                                if (count($products) > 0):
                                    foreach ($products as $product):
                                ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo number_format($product['price']); ?></td>
                                    <td>
                                        <button class="btn btn-sm" onclick="viewItems('<?php echo htmlspecialchars(addslashes($product['items'])); ?>')">
                                            View Items
                                        </button>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($product['limit_type'])) {
                                            echo ucfirst($product['limit_type']) . ': ' . $product['limit_amount'];
                                        } else {
                                            echo 'No limit';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $product['status'] == 1 ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $product['status'] == 1 ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm" onclick="editProduct(<?php echo $product['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm <?php echo $product['status'] == 1 ? 'btn-danger' : 'btn-success'; ?>" 
                                                onclick="toggleProductStatus(<?php echo $product['id']; ?>, <?php echo $product['status']; ?>)">
                                            <?php echo $product['status'] == 1 ? 'Disable' : 'Enable'; ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php
                                    endforeach;
                                else:
                                ?>
                                <tr>
                                    <td colspan="7">No products found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

                <div id="role-management" class="tab-content">
                    <div class="card">
                        <h3>Change User Role</h3>
                        <form id="change-role-form">
                            <div class="form-group">
                                <label for="role-username">Username</label>
                                <input type="text" id="role-username" name="role-username" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="new-role">New Role</label>
                                <select id="new-role" name="new-role" class="form-control">
                                    <option value="1">Player (1)</option>
                                    <option value="20">Agent Topup (20)</option>
                                    <option value="50">Game Master (50)</option>
                                    <option value="99">Administrator (99)</option>
                                </select>
                            </div>
                            <button type="submit" class="btn">Update Role</button>
                        </form>
                    </div>
                    
                    <div class="card">
                        <h3>User List by Roles</h3>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Registration Date</th>
                                        <th>Last Login</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- User list data will be loaded here -->
                                    <tr>
                                        <td colspan="4">No data available</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div id="giftcode-management" class="tab-content">
                <div class="card">
                <h3>Create Gift Code</h3>
                <form id="create-gift-code-form">
                    <div class="form-group">
                        <label for="giftcode-name">Gift Code Name</label>
                        <input type="text" id="giftcode-name" name="giftcode_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="item-list">Items</label>
                        <div id="items-container">
                            <div class="item-row" style="display: flex; margin-bottom: 10px;">
                                <input type="number" class="form-control" placeholder="Item ID" name="items[0][id]" required>
                                <input type="number" class="form-control" placeholder="Quantity" name="items[0][count]" min="1" value="1" required>
                            </div>
                        </div>
                        <button type="button" id="add-item-btn" class="btn btn-secondary" style="margin-top: 10px;">+ Add Item</button>
                    </div>
                    <div class="form-group">
                        <label for="is-global">Is Global?</label>
                        <select id="is-global" name="is_global" class="form-control">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="start-time">Start Time</label>
                        <input type="datetime-local" id="start-time" name="start_time" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="end-time">End Time</label>
                        <input type="datetime-local" id="end-time" name="end_time" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Gift Code</button>
                </form>
            </div>                
            <div class="card">
                    <h3>Manage Gift Codes</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Gift Code</th>
                                    <th>Items</th>
                                    <th>Is Global</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="giftcode-list">
                                <!-- Gift codes will be dynamically loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
                <div id="send-items" class="tab-content">
                    <div class="card">
                        <h3>Send Item to Player</h3>
                        <div class="form-group">
                            <label for="item-username">Search User or Character:</label>
                            <input type="text" id="item-username" name="item-username" class="form-control" placeholder="Enter username or character name">
                            <button id="send-item-button" class="btn" style="margin-top: 10px;">Search</button>
                        </div>
                        
                        <div id="send-item-form" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                            <div class="form-group">
                                <label>Selected Character:</label>
                                <input type="text" id="selected-character-name" class="form-control" readonly>
                                <input type="hidden" id="selected-character-id">
                            </div>
                            
                            <div class="form-group">
                                <label for="item-id">Item ID:</label>
                                <input type="number" id="item-id" name="item-id" class="form-control" placeholder="Enter item ID" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="item-quantity">Quantity:</label>
                                <input type="number" id="item-quantity" name="item-quantity" class="form-control" min="1" max="100" value="1" required>
                            </div>
                            
                            <div class="form-group" style="display: flex; gap: 10px;">
                                <button id="send-item-submit" class="btn">Send Item</button>
                                <button id="cancel-item-send" class="btn btn-danger" onclick="document.getElementById('send-item-form').style.display='none'">Cancel</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3>Send Money to Player</h3>
                        <div class="form-group">
                            <label for="money-username">Search Account:</label>
                            <input type="text" id="money-username" name="money-username" class="form-control" placeholder="Enter account name">
                            <button id="send-money-button" class="btn" style="margin-top: 10px;">Search</button>
                        </div>
                        
                        <div id="send-money-form" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                            <div class="form-group">
                                <label>Selected Account:</label>
                                <input type="text" id="selected-account-name" class="form-control" readonly>
                                <input type="hidden" id="selected-account-id">
                            </div>
                            
                            <div class="form-group">
                                <label for="money-amount">Amount:</label>
                                <input type="number" id="money-amount" name="money-amount" class="form-control" min="1" required>
                            </div>
                            
                            <div class="form-group" style="display: flex; gap: 10px;">
                                <button id="send-money-submit" class="btn">Send Money</button>
                                <button id="cancel-money-send" class="btn btn-danger" onclick="document.getElementById('send-money-form').style.display='none'">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="transactions" class="tab-content">
                    <div class="card">
                        <h3>Shop Transactions</h3>
                        <form id="search-transactions-form">
                            <div class="form-group">
                                <label for="trans-username">Username (optional)</label>
                                <input type="text" id="trans-username" name="trans-username" class="form-control">
                            </div>
                            <button type="submit" class="btn">Search</button>
                        </form>
                        <div class="table-container mt-3">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Username</th>
                                        <th>Item</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5">No transactions found</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3>Top Donations</h3>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Total Donated</th>
                                        <th>Last Donation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="3">No donation data available</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($section == 'rebates' && $userRole == 'administrator'): ?>
<?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tab = this.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked button and corresponding content
                    this.classList.add('active');
                    document.getElementById(tab).classList.add('active');
                });
            });
            
            // Handle change password form submission
            const changePasswordForm = document.getElementById('change-password-form');
            if (changePasswordForm) {
                changePasswordForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Get form values
                    const currentPassword = document.getElementById('current-password').value;
                    const newPassword = document.getElementById('new-password').value;
                    const confirmPassword = document.getElementById('confirm-password').value;
                    const email = document.getElementById('email').value;
                    const pinCode = document.getElementById('pin-code').value;
                    
                    // Simple validation
                    if (newPassword !== confirmPassword) {
                        Swal.fire('Error', 'New password and confirmation do not match', 'error');
                        return;
                    }
                    
                    // Here you would typically send an AJAX request to process the form
                    
                    // Show success message (for now as placeholder)
                    Swal.fire('Success', 'Password has been changed successfully', 'success');
                    
                    // Reset form
                    this.reset();
                });
            }
            
            // Game Master - Ban Player Form
            const banPlayerForm = document.getElementById('ban-player-form');
            if (banPlayerForm) {
                banPlayerForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Get form values
                    const banType = document.getElementById('ban-type').value;
                    const banValue = document.getElementById('ban-value').value;
                    const banReason = document.getElementById('ban-reason').value;
                    const banDuration = document.getElementById('ban-duration').value;
                    
                    // Here you would typically send an AJAX request to process the ban
                    
                    // Show success message (for now as placeholder)
                    Swal.fire('Success', 'Player has been banned successfully', 'success');
                    
                    // Reset form
                    this.reset();
                });
            }
            
            // Agent Topup - Send Balance Form
            const sendBalanceForm = document.getElementById('send-balance-form');
            if (sendBalanceForm) {
                sendBalanceForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Get form values
                    const username = document.getElementById('username').value;
                    const amount = document.getElementById('amount').value;
                    const note = document.getElementById('note').value || 'Balance transfer';
                    
                    // Validate input
                    if (!username || !amount) {
                        Swal.fire('Error', 'Please fill all required fields', 'error');
                        return;
                    }
                    
                    if (isNaN(amount) || parseInt(amount) <= 0) {
                        Swal.fire('Error', 'Invalid amount', 'error');
                        return;
                    }
                    
                    // Show confirmation
                    Swal.fire({
                        title: 'Confirm Balance Transfer',
                        html: `
                            <p>You are about to send:</p>
                            <ul>
                                <li><strong>Amount:</strong> ${amount}</li>
                                <li><strong>To Account:</strong> ${username}</li>
                            </ul>
                            <p>Are you sure?</p>
                        `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Send',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Show loading
                            Swal.fire({
                                title: 'Sending...',
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            
                            // Send AJAX request
                            const formData = new FormData();
                            formData.append('action', 'send_balance_agent');
                            formData.append('username', username);
                            formData.append('amount', amount);
                            formData.append('note', note);
                            
                            // Debug logging
                            console.log("Sending balance request:", {
                                username: username,
                                amount: amount,
                                note: note
                            });
                            
                            fetch('ajax_handler.php', {
                                method: 'POST',
                                body: formData,
                                credentials: 'same-origin'
                            })
                            .then(response => {
                                if (!response.ok) {
                                    console.error('Server responded with status:', response.status);
                                    return response.text().then(text => {
                                        throw new Error(text || 'Server error');
                                    });
                                }
                                return response.text();
                            })
                            .then(rawResponse => {
                                console.log('Raw server response:', rawResponse);
                                try {
                                    const data = JSON.parse(rawResponse);
                                    
                                    if (data.success) {
                                        Swal.fire({
                                            title: 'Success',
                                            text: `Balance of ${amount} has been sent to ${username}`,
                                            icon: 'success'
                                        });
                                        
                                        // Reset form
                                        document.getElementById('username').value = '';
                                        document.getElementById('amount').value = '';
                                        document.getElementById('note').value = '';
                                        
                                    } else {
                                        Swal.fire('Error', data.message || 'Failed to send balance', 'error');
                                    }
                                } catch (e) {
                                    console.error('Error parsing JSON response:', e);
                                    Swal.fire('Error', 'Server returned invalid response', 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.fire('Error', 'An error occurred: ' + error.message, 'error');
                            });
                        }
                    });
                });
            }
            
            // Admin functions - all form handlers would be similar to above
        });

        // Function to show product info from element
        function showProductInfoFromElement(element) {
            // Prevent event bubbling
            event.stopPropagation();
            
            // Get data from data attributes
            const productName = element.getAttribute('data-product-name');
            const productDescription = element.getAttribute('data-product-description');
            
            Swal.fire({
                title: productName,
                html: `<div style="text-align: left; max-height: 300px; overflow-y: auto;">${productDescription.replace(/\n/g, '<br>')}</div>`,
                showCloseButton: true,
                showConfirmButton: false,
                customClass: {
                    popup: 'product-info-popup'
                }
            });
        }
        // Function to unban a user
        function unbanUser(banId) {
            Swal.fire({
                title: 'Confirm Unban',
                text: 'Are you sure you want to unban this user?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, unban!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Here you would typically send an AJAX request to unban the user
                    
                    // Show success message and refresh page (for now as placeholder)
                    Swal.fire('Success', 'User has been unbanned!', 'success')
                    .then(() => {
                        window.location.reload();
                    });
                }
            });
        }

        // Function to purchase item
        function purchaseItem(productId, productName, price) {
            // First select character if user has multiple characters
            const userBalance = <?php echo $userData['money']; ?>;
            
            if (userBalance < price) {
                Swal.fire('Insufficient Balance', 'You do not have enough balance to purchase this item.', 'error');
                return;
            }
            
            <?php if (count($characters) > 0): ?>
                if (<?php echo count($characters); ?> > 1) {
                    // Show character selection
                    let characterOptions = '';
                    <?php foreach ($characters as $character): ?>
                        // Pastikan menggunakan id yang benar (yang sebenarnya adalah charid)
                        characterOptions += `<option value="<?php echo $character['id']; ?>"><?php echo htmlspecialchars($character['name']); ?> (Lv. <?php echo $character['rolelv']; ?>)</option>`;
                    <?php endforeach; ?>
                    
                    Swal.fire({
                        title: 'Select Character',
                        html: `<select id="character-select" class="swal2-input">${characterOptions}</select>`,
                        showCancelButton: true,
                        confirmButtonText: 'Purchase',
                        preConfirm: () => {
                            return document.getElementById('character-select').value;
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            confirmPurchase(productId, productName, price, result.value);
                        }
                    });
                } else {
                    // Only one character, use it directly
                    confirmPurchase(productId, productName, price, <?php echo !empty($characters) ? $characters[0]['id'] : 0; ?>);
                }
            <?php else: ?>
                Swal.fire('No Character', 'You need to create a character first.', 'error');
            <?php endif; ?>
        }
            // Function to show product info
            function showProductInfo(productName, productDescription) {
                // Prevent event bubbling
                event.stopPropagation();
                
                Swal.fire({
                    title: productName,
                    html: `<div style="text-align: left; max-height: 300px; overflow-y: auto;">${productDescription.replace(/\n/g, '<br>')}</div>`,
                    showCloseButton: true,
                    showConfirmButton: false,
                    customClass: {
                        popup: 'product-info-popup'
                    }
                });
            }
        // Function to confirm purchase
        function confirmPurchase(productId, productName, price, characterId) {
            Swal.fire({
                title: 'Confirm Purchase',
                text: `Are you sure you want to purchase ${productName} for ${price} credits?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Purchase',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send AJAX request to process purchase
                    const formData = new FormData();
                    formData.append('action', 'purchase');
                    formData.append('product_id', productId);
                    formData.append('character_id', characterId);
                    
                    fetch('purchase_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success',
                                text: `You have successfully purchased ${productName}!`,
                                icon: 'success'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Error', data.message || 'Failed to process purchase', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error', 'An error occurred during the purchase', 'error');
                    });
                }
            });
        }

                // Initialize dynamic items form
                document.addEventListener('DOMContentLoaded', function() {
                    const addItemBtn = document.getElementById('add-item-btn');
                    const itemsContainer = document.getElementById('items-container');
                    
                    if (addItemBtn) {
                        // Add new item row
                        addItemBtn.addEventListener('click', function() {
                            const newRow = document.createElement('div');
                            newRow.className = 'item-row';
                            newRow.style = 'display: flex; margin-bottom: 10px;';
                            
                            newRow.innerHTML = `
                                <input type="number" class="form-control item-id" placeholder="Item ID" style="width: 40%; margin-right: 10px;" required>
                                <input type="number" class="form-control item-count" placeholder="Count" style="width: 30%; margin-right: 10px;" min="1" value="1" required>
                                <button type="button" class="btn remove-item" style="background-color: #f44336;"><i class="fas fa-trash"></i></button>
                            `;
                            
                            itemsContainer.appendChild(newRow);
                            
                            // Show remove button on first row if we now have multiple rows
                            if (itemsContainer.querySelectorAll('.item-row').length > 1) {
                                const firstRowRemoveBtn = itemsContainer.querySelector('.item-row:first-child .remove-item');
                                if (firstRowRemoveBtn) {
                                    firstRowRemoveBtn.style.display = 'block';
                                }
                            }
                            
                            // Add event listener to new remove button
                            const removeBtn = newRow.querySelector('.remove-item');
                            removeBtn.addEventListener('click', function() {
                                newRow.remove();
                                
                                // Hide remove button on first row if it's the only one left
                                if (itemsContainer.querySelectorAll('.item-row').length === 1) {
                                    const firstRowRemoveBtn = itemsContainer.querySelector('.item-row:first-child .remove-item');
                                    if (firstRowRemoveBtn) {
                                        firstRowRemoveBtn.style.display = 'none';
                                    }
                                }
                            });
                        });
                        
                        // Handle form submission to convert items to JSON
                        const addProductForm = document.getElementById('add-product-form');
                        if (addProductForm) {
                            addProductForm.addEventListener('submit', function(e) {
                                e.preventDefault();
                                
                                // Convert items to JSON
                                const itemRows = itemsContainer.querySelectorAll('.item-row');
                                const items = [];
                                
                                let isValid = true;
                                
                                itemRows.forEach(row => {
                                    const idInput = row.querySelector('.item-id');
                                    const countInput = row.querySelector('.item-count');
                                    
                                    const id = parseInt(idInput.value);
                                    const count = parseInt(countInput.value);
                                    
                                    if (!id || isNaN(id) || id <= 0) {
                                        Swal.fire('Error', 'Please enter a valid Item ID', 'error');
                                        isValid = false;
                                        return;
                                    }
                                    
                                    if (!count || isNaN(count) || count <= 0) {
                                        Swal.fire('Error', 'Please enter a valid Count', 'error');
                                        isValid = false;
                                        return;
                                    }
                                    
                                    items.push({ id: id, count: count });
                                });
                                
                                if (!isValid) return;
                                
                                // Set the JSON string to hidden input
                                document.getElementById('product-items').value = JSON.stringify(items);
                                
                                // Continue with the rest of your form submission code
                                const name = document.getElementById('product-name').value;
                                const description = document.getElementById('product-description').value;
                                const price = document.getElementById('product-price').value;
                                const image = document.getElementById('product-image').value;
                                const limitType = document.getElementById('product-limit-type').value;
                                const limitAmount = document.getElementById('product-limit-amount').value;
                                
                                // Validate required fields
                                if (!name || !price) {
                                    Swal.fire('Error', 'Please fill all required fields', 'error');
                                    return;
                                }
                                
                                // Create form data
                                const formData = new FormData();
                                formData.append('action', 'add');
                                formData.append('name', name);
                                formData.append('description', description);
                                formData.append('price', price);
                                formData.append('items', document.getElementById('product-items').value);
                                formData.append('image', image);
                                formData.append('limit_type', limitType);
                                formData.append('limit_amount', limitAmount);
                                
                                // Send AJAX request
                                fetch('product_handler.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        Swal.fire({
                                            title: 'Success',
                                            text: 'Product has been added successfully',
                                            icon: 'success',
                                            confirmButtonText: 'OK'
                                        }).then(() => {
                                            // Reset form and refresh product list
                                            this.reset();
                                            window.location.reload();
                                        });
                                    } else {
                                        Swal.fire('Error', data.message || 'Failed to add product', 'error');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    Swal.fire('Error', 'An error occurred while adding the product', 'error');
                                });
                            });
                        }
                    }
                    
                    // Function to populate item fields when editing
                    window.populateItemFields = function(itemsJson) {
                        // Clear existing items except the first one
                        const itemRows = itemsContainer.querySelectorAll('.item-row');
                        for (let i = 1; i < itemRows.length; i++) {
                            itemRows[i].remove();
                        }
                        
                        // Reset first row
                        const firstRow = itemsContainer.querySelector('.item-row');
                        if (firstRow) {
                            firstRow.querySelector('.item-id').value = '';
                            firstRow.querySelector('.item-count').value = '1';
                            firstRow.querySelector('.remove-item').style.display = 'none';
                        }
                        
                        try {
                            const items = JSON.parse(itemsJson);
                            
                            if (Array.isArray(items) && items.length > 0) {
                                // Set first item
                                if (firstRow) {
                                    firstRow.querySelector('.item-id').value = items[0].id;
                                    firstRow.querySelector('.item-count').value = items[0].count;
                                }
                                
                                // Add additional items
                                for (let i = 1; i < items.length; i++) {
                                    addItemBtn.click(); // Trigger add button to create new row
                                    const newRow = itemsContainer.querySelectorAll('.item-row')[i];
                                    if (newRow) {
                                        newRow.querySelector('.item-id').value = items[i].id;
                                        newRow.querySelector('.item-count').value = items[i].count;
                                    }
                                }
                                
                                // Show remove button on first row if we have multiple items
                                if (items.length > 1 && firstRow) {
                                    firstRow.querySelector('.remove-item').style.display = 'block';
                                }
                            }
                        } catch (e) {
                            console.error('Error parsing items JSON:', e);
                        }
                    };
                });



        // Handle product form submission
        document.addEventListener('DOMContentLoaded', function() {
            const addProductForm = document.getElementById('add-product-form');
            if (addProductForm) {
                addProductForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Get form values
                    const name = document.getElementById('product-name').value;
                    const description = document.getElementById('product-description').value;
                    const price = document.getElementById('product-price').value;
                    const items = document.getElementById('product-items').value;
                    const image = document.getElementById('product-image').value;
                    const limitType = document.getElementById('product-limit-type').value;
                    const limitAmount = document.getElementById('product-limit-amount').value;
                    
                    // Validate JSON format for items
                    try {
                        JSON.parse(items);
                    } catch (error) {
                        Swal.fire('Error', 'Items must be valid JSON format', 'error');
                        return;
                    }
                    
                    // Create form data
                    const formData = new FormData();
                    formData.append('action', 'add');
                    formData.append('name', name);
                    formData.append('description', description);
                    formData.append('price', price);
                    formData.append('items', items);
                    formData.append('image', image);
                    formData.append('limit_type', limitType);
                    formData.append('limit_amount', limitAmount);
                    
                    // Send AJAX request
                    fetch('product_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success',
                                text: 'Product has been added successfully',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                // Reset form and refresh product list
                                this.reset();
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Error', data.message || 'Failed to add product', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error', 'An error occurred while adding the product', 'error');
                    });
                });
            }
        });

        // Function to edit product
            function editProduct(productId) {
                // First fetch the product details
                const formData = new FormData();
                formData.append('action', 'get');
                formData.append('product_id', productId);
                
                fetch('product_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const product = data.product;
                        
                        // Create a modal with dynamic item fields
                        Swal.fire({
                            title: 'Edit Product',
                            html: `
                                <form id="edit-product-form" class="swal2-form">
                                    <div class="form-group">
                                        <label for="edit-name">Product Name</label>
                                        <input id="edit-name" class="swal2-input" value="${escapeHtml(product.name)}" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-description">Description</label>
                                        <textarea id="edit-description" class="swal2-textarea" rows="3">${escapeHtml(product.description || '')}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-price">Price</label>
                                        <input id="edit-price" class="swal2-input" type="number" value="${product.price}" min="1" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Items</label>
                                        <div id="edit-items-container">
                                            <div class="item-row" style="display: flex; margin-bottom: 10px;">
                                                <input type="number" class="form-control edit-item-id" placeholder="Item ID" style="width: 40%; margin-right: 10px;" required>
                                                <input type="number" class="form-control edit-item-count" placeholder="Count" style="width: 30%; margin-right: 10px;" min="1" value="1" required>
                                                <button type="button" class="btn edit-remove-item" style="background-color: #f44336; display: none;"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                        <button type="button" id="edit-add-item-btn" class="btn" style="margin-top: 10px;"><i class="fas fa-plus"></i> Add Another Item</button>
                                        <input type="hidden" id="edit-items-json">
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-image">Image URL (optional)</label>
                                        <input id="edit-image" class="swal2-input" value="${escapeHtml(product.image || '')}">
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-limit-type">Purchase Limit Type</label>
                                        <select id="edit-limit-type" class="swal2-select">
                                            <option value="" ${!product.limit_type ? 'selected' : ''}>No Limit</option>
                                            <option value="daily" ${product.limit_type === 'daily' ? 'selected' : ''}>Daily</option>
                                            <option value="weekly" ${product.limit_type === 'weekly' ? 'selected' : ''}>Weekly</option>
                                            <option value="monthly" ${product.limit_type === 'monthly' ? 'selected' : ''}>Monthly</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-limit-amount">Limit Amount</label>
                                        <input id="edit-limit-amount" class="swal2-input" type="number" value="${product.limit_amount || 1}" min="1">
                                    </div>
                                </form>
                            `,
                            didOpen: () => {
                                // Setup dynamic items in edit form
                                const editAddItemBtn = document.getElementById('edit-add-item-btn');
                                const editItemsContainer = document.getElementById('edit-items-container');
                                
                                if (editAddItemBtn) {
                                    // Add new item row
                                    editAddItemBtn.addEventListener('click', function() {
                                        const newRow = document.createElement('div');
                                        newRow.className = 'item-row';
                                        newRow.style = 'display: flex; margin-bottom: 10px;';
                                        
                                        newRow.innerHTML = `
                                            <input type="number" class="form-control edit-item-id" placeholder="Item ID" style="width: 40%; margin-right: 10px;" required>
                                            <input type="number" class="form-control edit-item-count" placeholder="Count" style="width: 30%; margin-right: 10px;" min="1" value="1" required>
                                            <button type="button" class="btn edit-remove-item" style="background-color: #f44336;"><i class="fas fa-trash"></i></button>
                                        `;
                                        
                                        editItemsContainer.appendChild(newRow);
                                        
                                        // Show remove button on first row if we now have multiple rows
                                        if (editItemsContainer.querySelectorAll('.item-row').length > 1) {
                                            const firstRowRemoveBtn = editItemsContainer.querySelector('.item-row:first-child .edit-remove-item');
                                            if (firstRowRemoveBtn) {
                                                firstRowRemoveBtn.style.display = 'block';
                                            }
                                        }
                                        
                                        // Add event listener to new remove button
                                        const removeBtn = newRow.querySelector('.edit-remove-item');
                                        removeBtn.addEventListener('click', function() {
                                            newRow.remove();
                                            
                                            // Hide remove button on first row if it's the only one left
                                            if (editItemsContainer.querySelectorAll('.item-row').length === 1) {
                                                const firstRowRemoveBtn = editItemsContainer.querySelector('.item-row:first-child .edit-remove-item');
                                                if (firstRowRemoveBtn) {
                                                    firstRowRemoveBtn.style.display = 'none';
                                                }
                                            }
                                        });
                                    });
                                }
                                
                                // Populate items from JSON
                                try {
                                    const items = JSON.parse(product.items);
                                    
                                    if (Array.isArray(items) && items.length > 0) {
                                        // Set first item
                                        const firstRow = editItemsContainer.querySelector('.item-row');
                                        if (firstRow) {
                                            firstRow.querySelector('.edit-item-id').value = items[0].id;
                                            firstRow.querySelector('.edit-item-count').value = items[0].count;
                                        }
                                        
                                        // Add additional items
                                        for (let i = 1; i < items.length; i++) {
                                            editAddItemBtn.click(); // Trigger add button to create new row
                                            const newRow = editItemsContainer.querySelectorAll('.item-row')[i];
                                            if (newRow) {
                                                newRow.querySelector('.edit-item-id').value = items[i].id;
                                                newRow.querySelector('.edit-item-count').value = items[i].count;
                                            }
                                        }
                                        
                                        // Show remove button on first row if we have multiple items
                                        if (items.length > 1 && firstRow) {
                                            firstRow.querySelector('.edit-remove-item').style.display = 'block';
                                        }
                                    }
                                } catch (e) {
                                    console.error('Error parsing items JSON:', e);
                                }
                            },
                            showCancelButton: true,
                            confirmButtonText: 'Save Changes',
                            cancelButtonText: 'Cancel',
                            width: 800,
                            preConfirm: () => {
                                // Convert items to JSON
                                const editItemsContainer = document.getElementById('edit-items-container');
                                const itemRows = editItemsContainer.querySelectorAll('.item-row');
                                const items = [];
                                
                                let isValid = true;
                                
                                itemRows.forEach(row => {
                                    const idInput = row.querySelector('.edit-item-id');
                                    const countInput = row.querySelector('.edit-item-count');
                                    
                                    const id = parseInt(idInput.value);
                                    const count = parseInt(countInput.value);
                                    
                                    if (!id || isNaN(id) || id <= 0) {
                                        Swal.showValidationMessage('Please enter a valid Item ID');
                                        isValid = false;
                                        return;
                                    }
                                    
                                    if (!count || isNaN(count) || count <= 0) {
                                        Swal.showValidationMessage('Please enter a valid Count');
                                        isValid = false;
                                        return;
                                    }
                                    
                                    items.push({ id: id, count: count });
                                });
                                
                                if (!isValid) return false;
                                
                                const itemsJson = JSON.stringify(items);
                                
                                return {
                                    name: document.getElementById('edit-name').value,
                                    description: document.getElementById('edit-description').value,
                                    price: document.getElementById('edit-price').value,
                                    items: itemsJson,
                                    image: document.getElementById('edit-image').value,
                                    limit_type: document.getElementById('edit-limit-type').value,
                                    limit_amount: document.getElementById('edit-limit-amount').value
                                };
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const updatedProduct = result.value;
                                
                                // Validate required fields
                                if (!updatedProduct.name || !updatedProduct.price) {
                                    Swal.fire('Error', 'Please fill all required fields', 'error');
                                    return;
                                }
                                
                                // Send update request
                                const updateData = new FormData();
                                updateData.append('action', 'edit');
                                updateData.append('product_id', productId);
                                updateData.append('name', updatedProduct.name);
                                updateData.append('description', updatedProduct.description);
                                updateData.append('price', updatedProduct.price);
                                updateData.append('items', updatedProduct.items);
                                updateData.append('image', updatedProduct.image);
                                updateData.append('limit_type', updatedProduct.limit_type);
                                updateData.append('limit_amount', updatedProduct.limit_amount);
                                
                                fetch('product_handler.php', {
                                    method: 'POST',
                                    body: updateData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        Swal.fire({
                                            title: 'Success',
                                            text: 'Product has been updated successfully',
                                            icon: 'success'
                                        }).then(() => {
                                            window.location.reload();
                                        });
                                    } else {
                                        Swal.fire('Error', data.message || 'Failed to update product', 'error');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    Swal.fire('Error', 'An error occurred while updating the product', 'error');
                                });
                            }
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Failed to fetch product details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'An error occurred while fetching product details', 'error');
                });
            }


        // Function to toggle product status
        function toggleProductStatus(productId, currentStatus) {
            const action = currentStatus == 1 ? 'disable' : 'enable';
            const newStatus = currentStatus == 1 ? 0 : 1;
            
            Swal.fire({
                title: 'Confirm',
                text: `Are you sure you want to ${action} this product?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'toggle_status');
                    formData.append('product_id', productId);
                    formData.append('status', newStatus);
                    
                    fetch('product_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success',
                                text: `Product has been ${action}d successfully`,
                                icon: 'success'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Error', data.message || `Failed to ${action} product`, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error', `An error occurred while ${action}ing the product`, 'error');
                    });
                }
            });
        }

        // Tab Send Items functionality
        
        // Function to search characters
        function searchCharacters() {
            const query = document.getElementById('item-username').value.trim();
            if (query.length < 2) {
                Swal.fire('Error', 'Please enter at least 2 characters', 'error');
                return;
            }
            
            // Show loading
            Swal.fire({
                title: 'Searching...',
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Send AJAX request
            const formData = new FormData();
            formData.append('action', 'search_characters');
            formData.append('query', query);
            
            fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    if (data.characters.length > 0) {
                        // Show character selection dialog
                        let html = '<div style="max-height: 300px; overflow-y: auto;"><table style="width:100%;">';
                        html += '<thead><tr><th>Name</th><th>Level</th><th>Account</th><th>Action</th></tr></thead><tbody>';
                        
                        data.characters.forEach(char => {
                            html += `<tr>
                                <td>${escapeHtml(char.name)}</td>
                                <td>${char.rolelv}</td>
                                <td>${escapeHtml(char.account_name)}</td>
                                <td><button class="swal2-confirm swal2-styled" onclick="selectCharacter(${char.id}, '${escapeHtml(char.name)}')">Select</button></td>
                            </tr>`;
                        });
                        
                        html += '</tbody></table></div>';
                        
                        Swal.fire({
                            title: 'Select Character',
                            html: html,
                            showConfirmButton: false,
                            showCloseButton: true,
                            customClass: {
                                container: 'character-select-dialog'
                            }
                        });
                    } else {
                        Swal.fire('No Results', 'No characters found matching your search', 'info');
                    }
                } else {
                    Swal.fire('Error', data.message || 'Failed to search characters', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'An error occurred during search', 'error');
            });
        }
        
        // Function to select a character for item sending
        window.selectCharacter = function(characterId, characterName) {
            document.getElementById('selected-character-id').value = characterId;
            document.getElementById('selected-character-name').value = characterName;
            document.getElementById('send-item-form').style.display = 'block';
            
            // Close the Swal dialog
            Swal.close();
        }
        
        // Function to send item to character
        function sendItem() {
            const characterId = document.getElementById('selected-character-id').value;
            const characterName = document.getElementById('selected-character-name').value;
            const itemId = document.getElementById('item-id').value;
            const amount = document.getElementById('item-quantity').value;
            const title = "Admin Gift";
            const description = "Special item from administration team";
            
            // Validate input
            if (!characterId || !itemId || !amount) {
                Swal.fire('Error', 'Please fill all required fields', 'error');
                return;
            }
            
            if (isNaN(itemId) || parseInt(itemId) <= 0) {
                Swal.fire('Error', 'Invalid item ID', 'error');
                return;
            }
            
            if (isNaN(amount) || parseInt(amount) < 1 || parseInt(amount) > 100) {
                Swal.fire('Error', 'Amount must be between 1 and 100', 'error');
                return;
            }
            
            // Show confirmation
            Swal.fire({
                title: 'Confirm Item Send',
                html: `
                    <p>You are about to send:</p>
                    <ul>
                        <li><strong>Item ID:</strong> ${itemId}</li>
                        <li><strong>Amount:</strong> ${amount}</li>
                        <li><strong>To Character:</strong> ${characterName}</li>
                    </ul>
                    <p>Are you sure?</p>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Send',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Sending...',
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Generate transaction ID
                    const transactionId = 'ADMIN_ITEM_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
                    
                    // Send AJAX request
                    const formData = new FormData();
                    formData.append('action', 'send_item');
                    formData.append('character_id', characterId);
                    formData.append('item_id', itemId);
                    formData.append('amount', amount);
                    formData.append('title', title);
                    formData.append('description', description);
                    formData.append('transaction_id', transactionId);
                    
                    fetch('ajax_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success',
                                text: 'Item has been sent successfully',
                                icon: 'success'
                            });
                            
                            // Reset form
                            document.getElementById('selected-character-id').value = '';
                            document.getElementById('selected-character-name').value = '';
                            document.getElementById('item-id').value = '';
                            document.getElementById('item-quantity').value = '1';
                            document.getElementById('send-item-form').style.display = 'none';
                            
                        } else {
                            Swal.fire('Error', data.message || 'Failed to send item', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error', 'An error occurred while sending the item', 'error');
                    });
                }
            });
        }
        
        // Function to search accounts for money sending
        function searchAccounts() {
            const query = document.getElementById('money-username').value.trim();
            if (query.length < 2) {
                Swal.fire('Error', 'Please enter at least 2 characters', 'error');
                return;
            }
            
            // Show loading
            Swal.fire({
                title: 'Searching...',
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Send AJAX request
            const formData = new FormData();
            formData.append('action', 'search_accounts');
            formData.append('query', query);
            
            fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    if (data.accounts.length > 0) {
                        // Show account selection dialog
                        let html = '<div style="max-height: 300px; overflow-y: auto;"><table style="width:100%;">';
                        html += '<thead><tr><th>Username</th><th>Current Balance</th><th>Action</th></tr></thead><tbody>';
                        
                        data.accounts.forEach(account => {
                            html += `<tr>
                                <td>${escapeHtml(account.account)}</td>
                                <td>${account.money}</td>
                                <td><button class="swal2-confirm swal2-styled" onclick="selectAccount(${account.id}, '${escapeHtml(account.account)}')">Select</button></td>
                            </tr>`;
                        });
                        
                        html += '</tbody></table></div>';
                        
                        Swal.fire({
                            title: 'Select Account',
                            html: html,
                            showConfirmButton: false,
                            showCloseButton: true,
                            customClass: {
                                container: 'account-select-dialog'
                            }
                        });
                    } else {
                        Swal.fire('No Results', 'No accounts found matching your search', 'info');
                    }
                } else {
                    Swal.fire('Error', data.message || 'Failed to search accounts', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'An error occurred during search', 'error');
            });
        }
        
        // Function to select an account for money sending
        window.selectAccount = function(accountId, accountName) {
            document.getElementById('selected-account-id').value = accountId;
            document.getElementById('selected-account-name').value = accountName;
            document.getElementById('send-money-form').style.display = 'block';
            
            // Close the Swal dialog
            Swal.close();
        }
        
        // Function to send money to account
        function sendMoney() {
            const accountId = document.getElementById('selected-account-id').value;
            const accountName = document.getElementById('selected-account-name').value;
            const amount = document.getElementById('money-amount').value;
            const note = "Admin balance addition";
            
            // Validate input
            if (!accountId || !amount) {
                Swal.fire('Error', 'Please fill all required fields', 'error');
                return;
            }
            
            if (isNaN(amount) || parseInt(amount) <= 0) {
                Swal.fire('Error', 'Invalid amount', 'error');
                return;
            }
            
            // Show confirmation
            Swal.fire({
                title: 'Confirm Money Send',
                html: `
                    <p>You are about to send:</p>
                    <ul>
                        <li><strong>Amount:</strong> ${amount}</li>
                        <li><strong>To Account:</strong> ${accountName}</li>
                    </ul>
                    <p>Are you sure?</p>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Send',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Sending...',
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Send AJAX request
                    const formData = new FormData();
                    formData.append('action', 'add_balance');
                    formData.append('account_id', accountId);
                    formData.append('amount', amount);
                    formData.append('note', note);
                    
                    // Tambahkan debugging untuk melihat response mentah
                    fetch('ajax_handler.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin' // Pastikan cookie/session dikirim
                    })
                    .then(response => {
                        // Cek response mentah terlebih dahulu
                        if (!response.ok) {
                            console.error('Server responded with status:', response.status);
                        }
                        return response.text(); // Ambil respons sebagai text dahulu
                    })
                    .then(rawResponse => {
                        console.log('Raw response:', rawResponse);
                        try {
                            // Coba parse sebagai JSON
                            const data = JSON.parse(rawResponse);
                            
                            if (data.success) {
                                Swal.fire({
                                    title: 'Success',
                                    text: 'Money has been sent successfully',
                                    icon: 'success'
                                });
                                
                                // Reset form
                                document.getElementById('selected-account-id').value = '';
                                document.getElementById('selected-account-name').value = '';
                                document.getElementById('money-amount').value = '';
                                document.getElementById('send-money-form').style.display = 'none';
                            } else {
                                Swal.fire('Error', data.message || 'Failed to send money', 'error');
                            }
                        } catch (e) {
                            // Jika gagal parse JSON, tampilkan response mentah
                            console.error('Failed to parse JSON:', e);
                            Swal.fire('Error', 'Server returned invalid response: ' + rawResponse, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error', 'An error occurred while sending the money', 'error');
                    });
                }
            });
        }
        
        // Helper function to escape HTML
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        // Set up event listeners
        document.getElementById('send-item-button').addEventListener('click', searchCharacters);
        document.getElementById('send-item-submit').addEventListener('click', sendItem);
        document.getElementById('send-money-button').addEventListener('click', searchAccounts);
        document.getElementById('send-money-submit').addEventListener('click', sendMoney);
        
    </script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const addItemBtn = document.getElementById('add-item-btn');
            const itemsContainer = document.getElementById('items-container');

            // Tambahkan item baru saat tombol Add Item diklik
            addItemBtn.addEventListener('click', function () {
                const itemIndex = itemsContainer.children.length; // Hitung jumlah item yang ada
                const newRow = document.createElement('div');
                newRow.className = 'item-row';
                newRow.style = 'display: flex; margin-bottom: 10px;';
                newRow.innerHTML = `
                    <input type="number" class="form-control" placeholder="Item ID" name="items[${itemIndex}][id]" required>
                    <input type="number" class="form-control" placeholder="Quantity" name="items[${itemIndex}][count]" min="1" value="1" required>
                    <button type="button" class="btn btn-danger remove-item-btn" style="margin-left: 10px;">Remove</button>
                `;
                itemsContainer.appendChild(newRow);

                // Tambahkan event listener untuk tombol Remove
                const removeBtn = newRow.querySelector('.remove-item-btn');
                removeBtn.addEventListener('click', function () {
                    newRow.remove();
                });
            });
        });
        </script>    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create Rebate button handler
            const createRebateButton = document.getElementById('createRebateBtn');
            if (createRebateButton) {
                createRebateButton.addEventListener('click', function() {
                    console.log('Create Rebate button clicked');
                    showCreateRebateModal();
                });
            }
            
            // Reset Rebate button handler - keep this unchanged
            const resetRebateButton = document.getElementById('resetAllRebatesBtn');
            if (resetRebateButton) {
                resetRebateButton.addEventListener('click', function() {
                    console.log('Reset Rebate button clicked');
                    resetAllRebateClaims();
                });
            }
            
            // Load rebate data
            loadRebates();
        });
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Fungsi untuk menampilkan modal rebate
    function showCreateRebateModal() {
        // HTML untuk modal
        const modalHTML = `
        <div class="modal fade show" id="createRebateModal" tabindex="-1" role="dialog" style="display: block; background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Rebate</h5>
                        <button type="button" class="close" onclick="document.getElementById('createRebateModal').remove();">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="createRebateForm">
                            <div class="form-group mb-3">
                                <label>Name Rebate:</label>
                                <input type="text" class="form-control" id="rebateName" required>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label>Desc Rebate:</label>
                                <textarea class="form-control" id="rebateDesc" rows="3"></textarea>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label>Minimum Spend Balance Untuk Claim:</label>
                                <input type="number" class="form-control" id="rebateMinSpend" min="1" required>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label>Start Date:</label>
                                <input type="datetime-local" class="form-control" id="rebateStartDate" required>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label>End Date:</label>
                                <input type="datetime-local" class="form-control" id="rebateEndDate" required>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label>Foto dari Imgur:</label>
                                <input type="text" class="form-control" id="rebateImage" placeholder="https://i.imgur.com/example.jpg">
                            </div>
                            
                            <div class="form-group mb-3">
                                <label>Item Rewards:</label>
                                <div id="rebateItemsContainer">
                                    <div class="input-group mb-2">
                                        <input type="number" class="form-control" placeholder="Item ID" name="itemId[]" required>
                                        <input type="number" class="form-control" placeholder="Quantity" name="itemQty[]" min="1" value="1" required>
                                        <button type="button" class="btn btn-danger" style="display:none;">Remove</button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary btn-sm" id="addMoreItemBtn">
                                    <i class="fas fa-plus"></i> Tambah Item
                                </button>
                            </div>
                            
                            <div class="form-group">
                                <label>Status:</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="rebateStatus" id="statusInactive" value="inactive" checked>
                                    <label class="form-check-label" for="statusInactive">Inactive</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="rebateStatus" id="statusActive" value="active">
                                    <label class="form-check-label" for="statusActive">Active</label>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('createRebateModal').remove();">Close</button>
                        <button type="button" class="btn btn-primary" id="saveRebateBtn">Save</button>
                    </div>
                </div>
            </div>
        </div>`;

        // Tambahkan modal ke body
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer.firstChild);
        
        // Set tanggal sekarang untuk start date
        const now = new Date();
        document.getElementById('rebateStartDate').value = now.toISOString().slice(0, 16);
        
        // Set tanggal seminggu ke depan untuk end date
        const nextWeek = new Date();
        nextWeek.setDate(nextWeek.getDate() + 7);
        document.getElementById('rebateEndDate').value = nextWeek.toISOString().slice(0, 16);
        
        // Fungsi untuk menambah row item
        document.getElementById('addMoreItemBtn').addEventListener('click', function() {
            const container = document.getElementById('rebateItemsContainer');
            const newRow = document.createElement('div');
            newRow.className = 'input-group mb-2';
            newRow.innerHTML = `
                <input type="number" class="form-control" placeholder="Item ID" name="itemId[]" required>
                <input type="number" class="form-control" placeholder="Quantity" name="itemQty[]" min="1" value="1" required>
                <button type="button" class="btn btn-danger">Remove</button>
            `;
            
            // Tambahkan event untuk tombol remove
            const removeBtn = newRow.querySelector('button');
            removeBtn.addEventListener('click', function() {
                newRow.remove();
                updateRemoveButtons();
            });
            
            container.appendChild(newRow);
            updateRemoveButtons();
        });
        
        // Fungsi untuk update tombol remove
        function updateRemoveButtons() {
            const container = document.getElementById('rebateItemsContainer');
            const rows = container.querySelectorAll('.input-group');
            
            if (rows.length <= 1) {
                rows[0].querySelector('button').style.display = 'none';
            } else {
                rows.forEach(row => {
                    row.querySelector('button').style.display = 'block';
                });
            }
        }
        
        // Event listener untuk simpan rebate
        document.getElementById('saveRebateBtn').addEventListener('click', function() {
            saveRebate();
        });
        
        // Fungsi untuk menyimpan rebate
        function saveRebate() {
            const name = document.getElementById('rebateName').value;
            const description = document.getElementById('rebateDesc').value;
            const minSpend = document.getElementById('rebateMinSpend').value;
            const startDate = document.getElementById('rebateStartDate').value;
            const endDate = document.getElementById('rebateEndDate').value;
            const imageUrl = document.getElementById('rebateImage').value;
            const status = document.querySelector('input[name="rebateStatus"]:checked').value;
            
            // Validasi form
            if (!name || !minSpend || !startDate || !endDate) {
                alert('Silakan isi semua field yang diperlukan');
                return;
            }
            
            // Kumpulkan data item
            const items = [];
            const itemIds = document.getElementsByName('itemId[]');
            const itemQtys = document.getElementsByName('itemQty[]');
            
            for (let i = 0; i < itemIds.length; i++) {
                if (!itemIds[i].value || !itemQtys[i].value) {
                    alert('Silakan isi semua data item');
                    return;
                }
                
                items.push({
                    id: parseInt(itemIds[i].value),
                    count: parseInt(itemQtys[i].value)
                });
            }
            
            // Tampilkan loading
            const saveBtn = document.getElementById('saveRebateBtn');
            saveBtn.disabled = true;
            saveBtn.innerText = 'Menyimpan...';
            
            // Kirim data ke server
            const formData = new FormData();
            formData.append('action', 'create_rebate');
            formData.append('name', name);
            formData.append('description', description);
            formData.append('min_spend', minSpend);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('image_url', imageUrl);
            formData.append('status', status);
            formData.append('items', JSON.stringify(items));
            
            fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Rebate berhasil dibuat!');
                    document.getElementById('createRebateModal').remove();
                    // Reload data rebate
                    if (typeof loadRebates === 'function') {
                        loadRebates();
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert('Error: ' + (data.message || 'Gagal membuat rebate'));
                    saveBtn.disabled = false;
                    saveBtn.innerText = 'Save';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menyimpan data');
                saveBtn.disabled = false;
                saveBtn.innerText = 'Save';
            });
        }
    }
    
    // Tambahkan event listener ke tombol Create New Rebate
    const createButton = document.querySelector('button[id="createRebateBtn"]');
    if (createButton) {
        createButton.addEventListener('click', function() {
            showCreateRebateModal();
        });
    }
});
</script>
</body>
</html>