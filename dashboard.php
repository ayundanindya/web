<?php
session_start();

// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login");
    exit;
}

// Include necessary files
include("config/database.php");
include("functions/character_functions.php");
include("functions/transaction_functions.php");
include("functions/product_functions.php");
include("functions/admin_functions.php");
include("data/job_classes.php");

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

// Load data based on user role
$characters = getCharacterList($userId, $conn);
$purchases = getPurchaseHistory($userId, $conn);
$donations = getDonationHistory($userId, $conn);
$shopProducts = getShopProducts($conn);
$accountStats = ($userRole == 'administrator') ? getAccountStats($conn) : [];
$topupHistory = [];
$bannedUsers = [];

// Hanya ambil topup history jika user adalah agent atau admin
if ($userRole == 'agenttopup' || $userRole == 'administrator') {
    $topupHistory = getTopupHistory($userId, $conn);
}

// Hanya ambil banned users jika user adalah game master atau administrator
if ($userRole == 'gamemaster' || $userRole == 'administrator') {
    $bannedUsers = getBannedUsers($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - V-Ragnarok Mobile</title>
    <link rel="shortcut icon" href="/images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include('templates/sidebar.php'); ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <?php
            // Include section template based on current section
            if ($section == 'profile') {
                include('templates/profile_section.php');
            } elseif ($section == 'donation-history') {
                include('templates/donation_history_section.php');
            } elseif ($section == 'giftcode') {
                include('templates/giftcode_section.php');
            } elseif ($section == 'donate') {
                include('templates/donate_section.php');
            } elseif ($section == 'shop') {
                include('templates/shop_section.php');
            } elseif ($section == 'gm' && ($userRole == 'gamemaster' || $userRole == 'administrator')) {
                include('templates/gm_section.php');
            } elseif ($section == 'agent' && ($userRole == 'agenttopup' || $userRole == 'administrator')) {
                include('templates/agent_section.php');
            } elseif ($section == 'admin' && $userRole == 'administrator') {
                include('templates/admin_section.php');
            } elseif ($section == 'rebates' && $userRole == 'administrator') {
                include('templates/rebates_section.php');
            }
            ?>
        </div>
    </div>

    <!-- Include JavaScript files -->
    <script src="js/dashboard.js"></script>
    <script src="js/shop.js"></script>
    <script src="js/admin.js"></script>
    <script src="js/rebate.js"></script>
</body>
</html>