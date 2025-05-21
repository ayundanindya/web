<?php
// Function to get all products from shop
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
?>