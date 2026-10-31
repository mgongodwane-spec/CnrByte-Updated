<?php
//vendors/func.php
declare(strict_types=1);

function get_vendor_info_by_client_id(mysqli $conn, int $client_id) {

        $query = "SELECT * FROM vendors WHERE client_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }

function get_vendor_info_by_vendor_id(mysqli $conn, int $vendor_id) {

        $query = "SELECT * FROM vendors WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }

function get_vendor_profile_body_info_by_vendor_id(mysqli $conn, int $vendor_id) {

        $query = "SELECT * FROM profiles WHERE vendor_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }
function get_business_type_by_client_id(mysqli $conn, int $client_id) {

        $query = "SELECT * FROM business_types WHERE client_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }