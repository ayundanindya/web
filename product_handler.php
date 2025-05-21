<?php
// filepath: product_handler.php
// File untuk menangani operasi produk (tambah, edit, toggle status)
session_start();
include("config/database.php");

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get user role
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT status FROM account WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

if ($userData['status'] != 99) { // Only admin can manage products
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Action handler
$action = isset($_POST['action']) ? $_POST['action'] : '';
$response = ['success' => false, 'message' => ''];

if ($action === 'add') {
    // Add new product
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $price = isset($_POST['price']) ? intval($_POST['price']) : 0;
    $items = isset($_POST['items']) ? trim($_POST['items']) : '';
    $image = isset($_POST['image']) ? trim($_POST['image']) : '';
    $limitType = isset($_POST['limit_type']) ? trim($_POST['limit_type']) : '';
    $limitAmount = isset($_POST['limit_amount']) ? intval($_POST['limit_amount']) : 0;
    
    // Validate input
    if (empty($name) || empty($description) || $price <= 0 || empty($items)) {
        $response = ['success' => false, 'message' => 'Please fill all required fields'];
    } else {
        // Validate items JSON with better error handling
        $jsonData = json_decode($items, true);
        if ($jsonData === null) {
            $jsonError = json_last_error_msg();
            $response = [
                'success' => false, 
                'message' => 'Items must be valid JSON format. Error: ' . $jsonError,
                'example' => '[{"id": 123, "count": 1}, {"id": 456, "count": 5}]'
            ];
        } else {
            // Validate JSON structure
            $isValid = true;
            $invalidItems = [];
            
            if (!is_array($jsonData)) {
                $isValid = false;
                $response = [
                    'success' => false, 
                    'message' => 'Items must be a JSON array',
                    'example' => '[{"id": 123, "count": 1}, {"id": 456, "count": 5}]'
                ];
            } else {
                foreach ($jsonData as $index => $item) {
                    if (!isset($item['id']) || !isset($item['count'])) {
                        $isValid = false;
                        $invalidItems[] = $index;
                    }
                }
                
                if (!$isValid) {
                    $response = [
                        'success' => false, 
                        'message' => 'Invalid item format at positions: ' . implode(', ', $invalidItems),
                        'example' => '[{"id": 123, "count": 1}, {"id": 456, "count": 5}]'
                    ];
                }
            }
            
            if ($isValid) {
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO product (name, description, price, items, image, limit_type, limit_amount, status)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("ssisssi", $name, $description, $price, $items, $image, $limitType, $limitAmount);
                
                if ($stmt->execute()) {
                    $response = [
                        'success' => true, 
                        'message' => 'Product added successfully',
                        'product_id' => $conn->insert_id
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to add product: ' . $stmt->error];
                }
                $stmt->close();
            }
        }
    }
} elseif ($action === 'toggle_status') {
    // Toggle product status (enable/disable)
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $newStatus = isset($_POST['status']) ? intval($_POST['status']) : 0;
    
    if ($productId <= 0) {
        $response = ['success' => false, 'message' => 'Invalid product ID'];
    } else {
        $stmt = $conn->prepare("UPDATE product SET status = ? WHERE id = ?");
        $stmt->bind_param("ii", $newStatus, $productId);
        
        if ($stmt->execute()) {
            $response = [
                'success' => true, 
                'message' => 'Product status updated successfully',
                'new_status' => $newStatus
            ];
        } else {
            $response = ['success' => false, 'message' => 'Failed to update product status: ' . $stmt->error];
        }
        $stmt->close();
    }
} elseif ($action === 'edit') {
    // Edit existing product
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $price = isset($_POST['price']) ? intval($_POST['price']) : 0;
    $items = isset($_POST['items']) ? trim($_POST['items']) : '';
    $image = isset($_POST['image']) ? trim($_POST['image']) : '';
    $limitType = isset($_POST['limit_type']) ? trim($_POST['limit_type']) : '';
    $limitAmount = isset($_POST['limit_amount']) ? intval($_POST['limit_amount']) : 0;
    
    // Validate input
    if ($productId <= 0 || empty($name) || empty($description) || $price <= 0 || empty($items)) {
        $response = ['success' => false, 'message' => 'Please fill all required fields'];
    } else {
        // Validate items JSON with better error handling
        $jsonData = json_decode($items, true);
        if ($jsonData === null) {
            $jsonError = json_last_error_msg();
            $response = [
                'success' => false, 
                'message' => 'Items must be valid JSON format. Error: ' . $jsonError,
                'example' => '[{"id": 123, "count": 1}, {"id": 456, "count": 5}]'
            ];
        } else {
            // Validate JSON structure
            $isValid = true;
            $invalidItems = [];
            
            if (!is_array($jsonData)) {
                $isValid = false;
                $response = [
                    'success' => false, 
                    'message' => 'Items must be a JSON array',
                    'example' => '[{"id": 123, "count": 1}, {"id": 456, "count": 5}]'
                ];
            } else {
                foreach ($jsonData as $index => $item) {
                    if (!isset($item['id']) || !isset($item['count'])) {
                        $isValid = false;
                        $invalidItems[] = $index;
                    }
                }
                
                if (!$isValid) {
                    $response = [
                        'success' => false, 
                        'message' => 'Invalid item format at positions: ' . implode(', ', $invalidItems),
                        'example' => '[{"id": 123, "count": 1}, {"id": 456, "count": 5}]'
                    ];
                }
            }
            
            if ($isValid) {
                // Update database
                $stmt = $conn->prepare("UPDATE product SET name = ?, description = ?, price = ?, items = ?,
                                    image = ?, limit_type = ?, limit_amount = ? WHERE id = ?");
                $stmt->bind_param("ssisssii", $name, $description, $price, $items, $image, $limitType, $limitAmount, $productId);
                
                if ($stmt->execute()) {
                    $response = [
                        'success' => true, 
                        'message' => 'Product updated successfully'
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to update product: ' . $stmt->error];
                }
                $stmt->close();
            }
        }
    }
} elseif ($action === 'get') {
    // Get product details
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    
    if ($productId <= 0) {
        $response = ['success' => false, 'message' => 'Invalid product ID'];
    } else {
        $stmt = $conn->prepare("SELECT * FROM product WHERE id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($product = $result->fetch_assoc()) {
            $response = [
                'success' => true,
                'product' => $product
            ];
        } else {
            $response = ['success' => false, 'message' => 'Product not found'];
        }
        $stmt->close();
    }
} else {
    $response = ['success' => false, 'message' => 'Invalid action'];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
