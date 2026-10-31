<?php
declare(strict_types=1);
if (!function_exists('get_company_info')) {
    function get_company_info(mysqli $conn, string $companyName): ?array {
        $stmt=$conn->prepare('SELECT id,reg_name,client_id FROM vendors WHERE reg_name=? LIMIT 1');
        $stmt->bind_param('s',$companyName);$stmt->execute();$row=$stmt->get_result()->fetch_assoc()?:null;$stmt->close();return $row;
    }
}
if (!function_exists('insert_product')) {
    function insert_product(mysqli $conn, $category, string $image, string $companyName, string $name, string $description, $salePrice, $costPrice, int $vendorId): bool {
        $categoryId=(int)$category;$sale=(float)$salePrice;$cost=(float)$costPrice;
        if ($vendorId<1 || trim($name)==='' || $sale<0 || $cost<0) return false;
        $stmt=$conn->prepare('INSERT INTO products (vendor_id,great,product_name,description,product_img,sale_price,cost_price) VALUES (?,?,?,?,?,?,?)');
        $stmt->bind_param('iisssdd',$vendorId,$categoryId,$name,$description,$image,$sale,$cost);
        $ok=$stmt->execute();$stmt->close();return $ok;
    }
}
