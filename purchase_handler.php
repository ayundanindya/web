<?php
session_start();
include("config/database.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Validasi keamanan tambahan
$userId = $_SESSION['user_id'];
$userIP = $_SERVER['REMOTE_ADDR'];
$sessionIP = $_SESSION['user_ip'] ?? '';

if (!empty($sessionIP) && $sessionIP !== $userIP) {
    error_log("Potential session hijacking detected. User ID: {$userId}, Session IP: {$sessionIP}, Current IP: {$userIP}");
    session_unset();
    session_destroy();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Security check failed. Please login again.']);
    exit;
}

// Kunci API untuk otorisasi
define('GM_API_PASSWORD', 'PASSWORDNYASUSAHGUAJAMINKONTOL12345');

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => ''];

if ($action === 'purchase') {
    $productId = intval($_POST['product_id'] ?? 0);
    $characterId = intval($_POST['character_id'] ?? 0);

    if ($productId <= 0 || $characterId <= 0) {
        $response = ['success' => false, 'message' => 'Invalid product or character'];
    } else {
        $conn->begin_transaction();

        try {
            // Ambil data akun
            $stmt = $conn->prepare("SELECT money FROM account WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $userData = $result->fetch_assoc();
            $stmt->close();

            if (!$userData) throw new Exception("Account data not found");

            // Ambil data produk
            $stmt = $conn->prepare("SELECT * FROM product WHERE id = ? AND status = 1");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            $stmt->close();

            if (!$product) throw new Exception("Product not available");

            // Validasi karakter
            $stmt = $conn->prepare("SELECT charid FROM ro_xd_r2.charbase WHERE charid = ? AND accid = ?");
            $stmt->bind_param("ii", $characterId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $character = $result->fetch_assoc();
            $stmt->close();

            if (!$character) throw new Exception("Character not found or does not belong to your account");

            // Validasi saldo
            if ($userData['money'] < $product['price']) throw new Exception("Insufficient balance");

            // Proses transaksi
            $transactionId = 'SHOP' . time() . rand(1000, 9999) . $userId;
            $newBalance = $userData['money'] - $product['price'];

            $stmt = $conn->prepare("UPDATE account SET money = ? WHERE id = ?");
            $stmt->bind_param("ii", $newBalance, $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO purchase_history (accid, charid, product_id, amount, transaction_id, purchase_time)
                                    VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiids", $userId, $characterId, $productId, $product['price'], $transactionId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            // Kirim item ke karakter
            $items = json_decode($product['items'], true);
            $sendItemErrors = [];
            foreach ($items as $item) {
                $postData = [
                    'password' => GM_API_PASSWORD, // Tambahkan password untuk autentikasi
                    'charid' => $characterId,
                    'itemcode' => $item['id'],
                    'amount' => $item['count'],
                    'title' => "Shop Purchase: {$product['name']}",
                    'desc' => "Thank you for your purchase! Transaction ID: {$transactionId}"
                ];

                $ch = curl_init('https://gm.v-ragnarokmobile.xyz/gmsenditem.php');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                $result = curl_exec($ch);
                if (!$result || json_decode($result, true)['status'] !== 'success') {
                    $sendItemErrors[] = "Failed to send item {$item['id']}";
                }
                curl_close($ch);
            }

            $response = [
                'success' => true,
                'message' => 'Purchase successful!',
                'transaction_id' => $transactionId,
                'new_balance' => $newBalance,
                'warnings' => $sendItemErrors
            ];
        } catch (Exception $e) {
            $conn->rollback();
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
    }
} else {
    $response = ['success' => false, 'message' => 'Invalid action'];
}

header('Content-Type: application/json');
echo json_encode($response);
?>