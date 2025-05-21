<?php
// Function to get character list from ro_xd_r2.charbase
function getCharacterList($accountId, $conn) {
    try {
        // Debug: Log the account ID
        error_log("Getting characters for account ID: " . $accountId);
        
        // Gunakan kolom accid, bukan accountid
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

// Function to get profession name
function getProfessionName($professionId, $jobClasses) {
    return isset($jobClasses[$professionId]) ? $jobClasses[$professionId] : "Unknown";
}
?>