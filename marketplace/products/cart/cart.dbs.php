<?php
declare(strict_types=1);

function get_company_info($conn, string $reg_name){
    $query = "SELECT * FROM companies WHERE reg_name = ?;";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $reg_name);
    $stmt->execute();
    $res = $stmt->get_result();
    $company_info = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $company_info;
}
function get_vendors_info_by_id($conn, string $vendor_id){
    $query = "SELECT * FROM vendors WHERE id = ?;";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $vendor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $company_info = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $company_info;
}
function get_client_info($conn, string $user_id){
    $query = "SELECT * FROM clients WHERE id = ?;";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $client_info = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $client_info;
}
function get_product_info($conn, string $product_id){
    $query = "SELECT * FROM products WHERE id = ?;";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $product_info = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $product_info;
}
function get_product_info_cart(object $conn, string $client_id){
    $sql =
        "SELECT *
        FROM cart
        WHERE client_id = '$client_id';";
    $result = mysqli_query($conn, $sql);
    $result_check = mysqli_num_rows($result);
    if($result_check > 0){
        while($row = mysqli_fetch_assoc($result)){
            $_SESSION["item_id"] = $row["id"];
            $_SESSION["client_id"] = $row['client_id'];
            $_SESSION["vendor_id"] = $row['vendor_id'];
            $_SESSION["pro_id"] = $row['pro_id'];
            $_SESSION["bank"] = $row['sale_price'];
            $_SESSION["sales"] = $row['sale_price'];
            $_SESSION["costs"] = $row['cost_price'];
            $_SESSION["units"] = $row['units'];
            $_SESSION["description"] = $row['description'];
        }
    }
}
function insert_to_cart($conn, string $client_id, string $vendor_id, string $pro_id, string $product_img, string $brand, string $name, string $description, string $sale_price, string $cost_price, string $units){
    $query = "INSERT INTO cart(client_id, vendor_id, pro_id, img, brand, name, description, sale_price, cost_price, units) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssssssssss', $client_id, $vendor_id, $pro_id, $product_img, $brand, $name, $description, $sale_price, $cost_price, $units);
    $stmt->execute();
    $stmt->close();
}

function insert_vendor_sale_crj($conn, string $vendor_id, string $pro_id, string $pro_description, string $bank, string $sales, string $costs,string $units){
    $query = "INSERT INTO crj(vendor_id, pro_id, details, bank, sales, costs, units) VALUES(?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssssss', $vendor_id, $pro_id, $pro_description, $bank, $sales, $costs, $units);
    $stmt->execute();
    $stmt->close();
}
function insert_vendor_commission_fee_cpj($conn, string $vendor_id, string $commission_details, string $commission_amount){
    $query = "INSERT INTO cpj(vendor_id, details, bank, expenses) VALUES(?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssss', $vendor_id, $commission_details, $commission_amount, $commission_amount);
    $stmt->execute();
    $stmt->close();
}
function insert_comp_commission_fee_crj($conn, string $comp_id, string $commission_details, string $commission_amount){
    $query = "INSERT INTO crj(comp_id, details, bank, income) VALUES(?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssss', $comp_id, $commission_details, $commission_amount, $commission_amount);
    $stmt->execute();
    $stmt->close();
}

function insert_client_purchase_cpj($conn, string $client_id, string $drawings_details, string $drawings){
    $query = "INSERT INTO cpj(client_id, details, bank, drawings) VALUES(?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssss', $client_id, $drawings_details, $drawings, $drawings);
    $stmt->execute();
    $stmt->close();
}
function insert_client_logistics_fee_cpj($conn, string $client_id, string $logistics_fee_details, string $logistics_fee_amount){
    $query = "INSERT INTO cpj(client_id, details, bank, drawings) VALUES(?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssss', $client_id, $logistics_fee_details, $logistics_fee_amount, $logistics_fee_amount);
    $stmt->execute();
    $stmt->close();
}
function insert_comp_logistics_fee_crj($conn, string $comp_id, string $logistics_fee_details, string $logistics_fee_amount){
    $query = "INSERT INTO crj(comp_id, details, bank, income) VALUES(?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssss', $comp_id, $logistics_fee_details, $logistics_fee_amount, $logistics_fee_amount);
    $stmt->execute();
    $stmt->close();
}

function edit_cart($conn, string $remove_code, string $item_id){
    $query = "UPDATE cart SET client_id = ? WHERE id = ?;";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $remove_code, $item_id);
    $stmt->execute();
    $stmt->close();
}
function deduct_product_inventory($conn, string $pro_id, string $units){
    $query = "UPDATE products SET units = units - ? WHERE id = ?;";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $units, $pro_id);
    $stmt->execute();
    $stmt->close();
}
function poke_admin($conn,int $sender_id, string $sender_name, int $receiver_id, string $receiver_name){
    $query = "INSERT INTO messages(sender_id, receiver_id, subject, message, is_read) VALUES(?, ?, 'Deposit Request', 'Please check my deposit', 0)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $sender_id, $receiver_id);
    $stmt->execute();
    $stmt->close();
}

function get_company_reg_no($conn, string $reg_no){
    $query = "SELECT id FROM companies WHERE reg_no = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) return null;
    $stmt->bind_param('s', $reg_no);
    $stmt->execute();
    $res = $stmt->get_result();
    $result = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $result;
}

function get_company_reg_name($conn, string $reg_name){
    $query = "SELECT id FROM companies WHERE reg_name = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) return null;
    $stmt->bind_param('s', $reg_name);
    $stmt->execute();
    $res = $stmt->get_result();
    $result = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $result;
}

function get_company_email($conn, string $email){
    $query = "SELECT id FROM companies WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) return null;
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $result = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $result;
}
