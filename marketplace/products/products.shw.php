<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/product_hierarchy.php';
//**************************************/
//cost items
//**************************************/
function create_product_form(mysqli $conn): void {
    if (!isset($_SESSION["vendor_info"])) {
        return;
    }

    product_hierarchy_bootstrap($conn);
    display_success_error_message();
    $vendor_id = (int)$_SESSION["vendor_info"]["id"];

    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total_children FROM product_child_categories");
    $count_stmt->execute();
    $count_res = $count_stmt->get_result()->fetch_assoc();
    $count_stmt->close();
    $has_child_categories = (int)($count_res["total_children"] ?? 0) > 0;
    if (!$has_child_categories) {
        ?>
        <div class="signup-form">
            <h3 class="form-title"><?php echo $_SESSION["vendor_info"]["reg_name"];?></h3>
            <p class="error-title">No product categories found yet. Create category hierarchy first, then create products.</p>
        </div>
        <?php
        create_product_hierarchy_form($conn);
        return;
    }

    // Check if a child category has been selected from the hierarchy
    $has_child_selected = isset($_SESSION["selected_child"]) && !empty($_SESSION["selected_child"]);

    // Get category names for breadcrumb display
    $great_name = '';
    $grand_name = '';
    $parent_name = '';
    $child_name = '';

    if ($has_child_selected) {
        $child_id = (int)$_SESSION["selected_child"];
        $path = product_hierarchy_get_path($conn, $child_id);

        if ($path) {
            $great_name = (string)($path["great"] ?? '');
            $grand_name = (string)($path["grand"] ?? '');
            $parent_name = (string)($path["parent"] ?? '');
            $child_name = (string)($path["child"] ?? '');
        }
    }
    ?>
    <form action = "create.run.php" method = "post" enctype = "multipart/form-data" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <h3 class="form-title"><?php echo $_SESSION["vendor_info"]["reg_name"];?></h3>
        <p class="form-title">Create Product</p>

        <?php if ($has_child_selected): ?>
            <!-- Display breadcrumb of selected category hierarchy -->
            <div style="background-color: #e7f6ff; padding: 12px; border-radius: 4px; margin-bottom: 16px; border-left: 4px solid #007bff;">
                <p style="margin: 0; color: #333; font-size: 0.95em;">
                    <strong>Category Path:</strong><br>
                    <?php echo htmlspecialchars($great_name, ENT_QUOTES, 'UTF-8'); ?>
                    <span style="color: #666;"> > </span>
                    <?php echo htmlspecialchars($grand_name, ENT_QUOTES, 'UTF-8'); ?>
                    <span style="color: #666;"> > </span>
                    <?php echo htmlspecialchars($parent_name, ENT_QUOTES, 'UTF-8'); ?>
                    <span style="color: #666;"> > </span>
                    <strong><?php echo htmlspecialchars($child_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                </p>
            </div>

            <!-- Hidden fields to pass selected categories -->
            <input type="hidden" name="great" value="<?php echo (int)($_SESSION['selected_great'] ?? 0); ?>">
            <input type="hidden" name="grand" value="<?php echo (int)($_SESSION['selected_grand'] ?? 0); ?>">
            <input type="hidden" name="parent" value="<?php echo (int)($_SESSION['selected_parent'] ?? 0); ?>">
            <input type="hidden" name="child" value="<?php echo (int)($_SESSION['selected_child'] ?? 0); ?>">

        <?php else: ?>
            <!-- Cascading selectors: each level is restricted to the chosen ancestor. -->
            <p style="color: #666; font-style: italic; margin-bottom: 12px;">Select a complete category path. Each list only shows categories belonging to the selection above it.</p>
            <?php
                $greatOptions = product_hierarchy_get_rows($conn, "SELECT id, category FROM product_great_categories ORDER BY category ASC");
                $grandOptions = product_hierarchy_get_rows($conn, "SELECT id, great_id, category FROM product_grand_categories ORDER BY category ASC");
                $parentOptions = product_hierarchy_get_rows($conn, "SELECT id, great_id, grand_id, category FROM product_parent_categories ORDER BY category ASC");
                $childOptions = product_hierarchy_get_rows($conn, "SELECT id, great_id, grand_id, parent_id, category FROM product_child_categories ORDER BY category ASC");
                $categoryOptionData = [
                    'great' => $greatOptions,
                    'grand' => $grandOptions,
                    'parent' => $parentOptions,
                    'child' => $childOptions,
                ];
            ?>
            <label for="great">Great Category</label>
            <select id="productGreat" name="great" class="form-input" required>
                <option value="">--- Select Great Category ---</option>
            </select><br>
            <label for="grand">Grand Category</label>
            <select id="productGrand" name="grand" class="form-input" required disabled>
                <option value="">--- Select Grand Category ---</option>
            </select><br>
            <label for="parent">Parent Category</label>
            <select id="productParent" name="parent" class="form-input" required disabled>
                <option value="">--- Select Parent Category ---</option>
            </select><br>
            <label for="child">Child Category</label>
            <select id="productChild" name="child" class="form-input" required disabled>
                <option value="">--- Select Child Category ---</option>
            </select><br>
            <script>
            (function () {
                const data = <?= json_encode($categoryOptionData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
                const selects = {
                    great: document.getElementById('productGreat'),
                    grand: document.getElementById('productGrand'),
                    parent: document.getElementById('productParent'),
                    child: document.getElementById('productChild')
                };
                function resetSelect(select, label) {
                    select.replaceChildren(new Option(label, ''));
                    select.value = '';
                    select.disabled = true;
                }
                function fill(select, rows, label) {
                    resetSelect(select, label);
                    rows.forEach(row => select.add(new Option(row.category, String(row.id))));
                    select.disabled = rows.length === 0;
                }
                fill(selects.great, data.great, '--- Select Great Category ---');
                selects.great.addEventListener('change', function () {
                    resetSelect(selects.grand, '--- Select Grand Category ---');
                    resetSelect(selects.parent, '--- Select Parent Category ---');
                    resetSelect(selects.child, '--- Select Child Category ---');
                    if (!this.value) return;
                    fill(selects.grand, data.grand.filter(row => Number(row.great_id) === Number(this.value)), '--- Select Grand Category ---');
                });
                selects.grand.addEventListener('change', function () {
                    resetSelect(selects.parent, '--- Select Parent Category ---');
                    resetSelect(selects.child, '--- Select Child Category ---');
                    if (!this.value) return;
                    fill(selects.parent, data.parent.filter(row => Number(row.grand_id) === Number(this.value) && Number(row.great_id) === Number(selects.great.value)), '--- Select Parent Category ---');
                });
                selects.parent.addEventListener('change', function () {
                    resetSelect(selects.child, '--- Select Child Category ---');
                    if (!this.value) return;
                    fill(selects.child, data.child.filter(row => Number(row.parent_id) === Number(this.value) && Number(row.grand_id) === Number(selects.grand.value) && Number(row.great_id) === Number(selects.great.value)), '--- Select Child Category ---');
                });
            })();
            </script>
        <?php endif; ?>

        <label for = "product_name">Product Name</label>
        <input type="text" name = "product_name" placeholder = "Product Name" required><br>

        <label for = "description">Description</label>
        <input type="text" name = "description" placeholder = "Description" required><br>

        <label for = "measure">Unit Measure</label>
        <select name = "measure" class = "form-input" required>
            <option value = "">--- Select Unit Measure ---</option>
            <option value = "kilograms">Weight (Kilograms)</option>
            <option value = "litres">Capacity (Litres)</option>
            <option value = "metres">Distance (Metres)</option>
            <option value = "minutes">Time (Minutes)</option>
            <option value = "pieces">Piece (Units)</option>
            <option value = "slices">Slices (Units)</option>
        </select><br>

        <label for="sale_price">Sale Price</label>
        <input type="number" step="0.01" min="0" name="sale_price" placeholder="0.00" required><br>

        <label for="cost_price">Cost Price</label>
        <input type="number" step="0.01" min="0" name="cost_price" placeholder="0.00" required><br>

        <label for = "product_img">Upload Product Image</label>
        <input type = "file" name = "product_img" id = "file" accept=".jpg,.jpeg,.png,.gif,.webp"><br>

        <button name="create_product" type = "submit" class = "form-btn">Submit Product</button>
    </form>
    <form action="/cnrbyte/includes/select.run.php" method="post" class="signup-form">
        <?php echo cnrbyte_csrf_field(); ?>
        <button type="submit" name="selected_form" value="Create Product Hierarchy" class="form-btn">Create Product Categories</button>
    </form>
    <?php
}
function edit_product_form(): void {
    if(isset($_SESSION["vendor_info"], $_SESSION["product_info"], $_SESSION["selected_form"])){
    global $conn;
    display_success_error_message();
        $path = product_hierarchy_get_path($conn, (int)($_SESSION["product_info"]["child"] ?? 0));
        ?>
        <form action="edit.run.php" method="post" enctype = "multipart/form-data" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <?php echo '<h3 class="form-title">'.$_SESSION["vendor_info"]["reg_name"].'</h3>'; ?>
            <?php echo '<h3 class="form-title">'.$_SESSION["branch_info"]["branch_name"].'</h3>'; ?>
            <?php echo '<h3 class="form-title">'.$_SESSION["product_info"]["product_name"].'</h3>'; ?>
            <p class="form-title">Edit Product</p>
            <?php
            //!-- Product Financials -->
            show_product_profit_margin_info();
            show_product_sale_and_cost_price_edit();
            ?>
            <?php if ($path): ?>
                <p class="form-title">
                    <?php echo htmlspecialchars((string)$path['great'], ENT_QUOTES, 'UTF-8'); ?>
                    >
                    <?php echo htmlspecialchars((string)$path['grand'], ENT_QUOTES, 'UTF-8'); ?>
                    >
                    <?php echo htmlspecialchars((string)$path['parent'], ENT_QUOTES, 'UTF-8'); ?>
                    >
                    <?php echo htmlspecialchars((string)$path['child'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endif; ?>
            <input type="hidden" name="great" value="<?php echo (int)($_SESSION["product_info"]["great"] ?? 0); ?>">
            <input type="hidden" name="grand" value="<?php echo (int)($_SESSION["product_info"]["grand"] ?? 0); ?>">
            <input type="hidden" name="parent" value="<?php echo (int)($_SESSION["product_info"]["parent"] ?? 0); ?>">
            <input type="hidden" name="child" value="<?php echo (int)($_SESSION["product_info"]["child"] ?? 0); ?>">
            <input type="hidden" name="product_id" value="<?php echo (int)($_SESSION["product_info"]["id"] ?? 0); ?>">
            <input type="hidden" name="existing_img" value="<?php echo htmlspecialchars((string)($_SESSION["product_info"]["product_img"] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

            <label for="product_name">Product Name</label>
            <input name="product_name" value="<?php echo $_SESSION["product_info"]["product_name"];?>"><br>

            <!-- Product Info -->
            <label for="description">Description</label>
            <input name="description" value="<?php echo $_SESSION["product_info"]["description"];?>"><br>

            <label for="measure">Unit Measure: <?php echo $_SESSION["product_info"]["measure"];?></label>
            <select name = "measure" class = "form-input">
                <option value = "">--- Select Unit Measure ---</option>
                <option value = "kilograms">Weight (Kilograms)</option>
                <option value = "litres">Capacity (Litres)</option>
                <option value = "metres">Distance (Metres)</option>
                <option value = "minutes">Time (Minutes)</option>
                <option value = "pieces">Piece (Units)</option>
                <option value = "slices">Slices (Units)</option>
            </select><br>

            <label for = "product_img">Change Product Image</label>
            <input type = "file" name = "product_img" id = "file" accept=".jpg,.jpeg,.png,.gif,.webp"><br>

            <button name="edit_product" type="submit" class="form-btn">Edit Product</button>
        </form>
        <?php
    }
}
function sell_product_form(): void {
    if(isset($_SESSION["vendor_info"], $_SESSION["branch_info"], $_SESSION["product_info"], $_SESSION["selected_form"])){
    display_success_error_message();
        ?>
        <form action = "edit.run.php" method = "post" class = "signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <?php echo '<h3 class="form-title">'.$_SESSION["vendor_info"]["reg_name"].'</h3>'; ?>
            <?php echo '<h3 class="form-title">'.$_SESSION["branch_info"]["branch_name"].'</h3>'; ?>
            <?php echo '<h3 class="form-title">'.$_SESSION["product_info"]["product_name"].'</h3>'; ?>
            <p class="form-title">Sell Product</p>
            <?php
            $row = $_SESSION["product_info"];
                show_product_profit_margin_info();
                show_product_sale_price_edit();
            ?>
            <input type="hidden" name="product_id" value = "<?php echo $_SESSION["product_info"]["id"]; ?>">

            <label for = "select_journal">Select Payment Method</label>
            <select name = "select_journal" class = "form-input" id = "select_journal">
                <option value = "">---Select Payment Method---</option>
                <option value = "crj">Cash</option>
                <option value = "dj">Credit</option>
            </select>

            <label for = "sale_price">Sale Price: <p id="total_sales" class="form-title"></p></label>
            <input type="number" name="sale_price" step="0.01" id="sale_price" placeholder = "Purchase Price">

            <label for = "units_sold">Units :<?php echo $_SESSION["product_info"]["measure"];?></label>
            <input type = "number" name = "units_sold" id = "units_sold" onchange="updateTotalSale()" placeholder = "Units Sold"><br>

            <button name="sell_product" type = "submit" class="form-btn">Manual Sale</button>
        </form>
        <script>
            function updateTotalSale() {
                const sales = document.getElementById('sale_price');
                const units = document.getElementById('units_sold');
                const input = document.getElementById('sale_price');
                const p = document.getElementById('total_sales');

                let sale_price = sales.innerHTML;
                let units_sold = units.value;
                let total_sale = sale_price * units_sold;
                    p.innerHTML = 'Total Sales R '+total_sale;
                    input.value = total_sale;
            }
        </script>
        <?php
    }
}
function show_products_table(mysqli $conn): void {
    if (isset($_SESSION['vendor_info'], $_SESSION["selected_form"])) {
    display_success_error_message();
        $vendor_id = $_SESSION["vendor_info"]["id"];
        $has_child_filter = isset($_SESSION["selected_child"]) && (int)$_SESSION["selected_child"] > 0;
        if ($has_child_filter) {
            $child = (int)$_SESSION["selected_child"];
            $products_stmt = $conn->prepare("SELECT * FROM products WHERE vendor_id = ? AND child = ?");
            $products_stmt->bind_param("ii", $vendor_id, $child);
        } else {
            $products_stmt = $conn->prepare("SELECT * FROM products WHERE vendor_id = ?");
            $products_stmt->bind_param("i", $vendor_id);
        }
        $products_stmt->execute();
        $products_result = $products_stmt->get_result();

        // Check if there are any products
        if ($products_result->num_rows > 0) {
            echo "<div class='products-container'>";
            $products_result->data_seek(0); // Reset pointer to beginning
            while ($products_row = $products_result->fetch_assoc()) {
                show_product_info_description($products_row);
            }
            echo "</div>";
        } else {
            echo "<div style='text-align: center; padding: 2rem; color: #6b7280;'>";
            echo "<p>No products found. Create a product to get started.</p>";
            echo "</div>";
        }

        $products_stmt->close();
    }
}
//show name, description and picture after selecting product
function show_product_info_description(array $products_row){
    if($products_row != null || !empty($products_row)):
        $img_path = htmlspecialchars("../uploads/products/{$products_row["product_img"]}", ENT_QUOTES, 'UTF-8');
        $product_name = htmlspecialchars($products_row["product_name"], ENT_QUOTES, 'UTF-8');
        echo "<div class='product-card'>
            <div class='product-card-image'>
                <img src='{$img_path}' alt='{$product_name}' class='product-card-img' onerror=\"this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 300 300%22><rect fill=%22%23f3f4f6%22 width=%22300%22 height=%22300%22/><text x=%22150%22 y=%22150%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%236b7280%22 font-family=%22sans-serif%22 font-size=%2218%22>Product Image</text></svg>'\">
            </div>
            <div class='product-card-content'>
                <h2 class='product-card-title'>{$product_name}</h2>
                <p class='product-card-description'>{$products_row["description"]}</p>
                <form action='/cnrbyte/includes/select.run.php' method='post' class='product-card-form'><?php echo cnrbyte_csrf_field(); ?>
                    <button name='selected_product' value='".$products_row["id"]."' class='product-card-btn'>Select Product</button>
                </form>
            </div>
        </div>
        ";
    endif;
}
//show list and totals after selecting product
function show_list_of_cost_items_and_totals_for_product(mysqli $conn, array $product_row): void {
    if($product_row != null || !empty($product_row)){
        $cost_of_sales_stmt = $conn->prepare("SELECT * FROM cost_of_sales WHERE product_id = ? AND vendor_id = ?");
        $cost_of_sales_stmt->bind_param("ii", $product_row["id"], $product_row["vendor_id"]);
        $cost_of_sales_stmt->execute();
        $cost_of_sales_result = $cost_of_sales_stmt->get_result();
        if(!empty($cost_of_sales_result)){
            echo "
                <div>
                    <table class='products_tbl'>
                        <tr>
                            <th>Item Name</th>
                            <th>Sale Per Unit</th>
                            <th>Cost Per Unit</th>
                            <th>Units Taken</th>
                            <th>Sales</th>
                            <th>Cost of Sales</th>
                        </tr>

            ";
            while ($cost_of_sales_row = $cost_of_sales_result->fetch_assoc()) {
                echo "
                    <tr>
                        <th>{$cost_of_sales_row["cost_item_name"]}</th>
                        <td>{$cost_of_sales_row["sale_price"]}</td>
                        <td>{$cost_of_sales_row["cost_price"]}</td>
                        <td>{$cost_of_sales_row["units_taken"]}</td>
                        <td>{$cost_of_sales_row["sales"]}</td>
                        <td>{$cost_of_sales_row["cost_of_sale"]}</td>
                    </tr>
                ";
            }
            echo "
                    </table>
                </div>
            ";
            $sales = get_cost_of_sale_item_total_by_colum($conn, $product_row, 'sale_price');
            $cost_of_sales = get_cost_of_sale_item_total_by_colum($conn, $product_row, 'cost_price');
        }else{
            $sales = '0,00';
            $cost_of_sales = '0,00';
        }
        $cost_of_sales_stmt->close();
        echo "<div>
                <table class='products_tbl'>
                    <tr>
                        <td>
                            <h2>**Sales*</h2>
                            <p class='form-title'>R {$sales}</p>
                        </td>

                        <td>
                            <h2>*Cost of Sales**</h2>
                            <p class='form-title'>R {$cost_of_sales}</p>
                        </td>
                    </tr>
                </table><br>
            </div>
        ";
    }
}

function get_cost_of_sale_item_total_by_colum(mysqli $conn, array $row, string $colum){
    $stmt = $conn->prepare("SELECT SUM($colum) AS total
        FROM cost_of_sales WHERE vendor_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $row["vendor_id"], $row["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = 0;
    while ($res_row = $result->fetch_assoc()) {
        $total = $res_row['total'];
    }
    $stmt->close();
    return $total;
}
function show_product_profit_margin_info(){
    if(isset($_SESSION["product_info"])):
        $row = $_SESSION["product_info"];
        echo"
        <table>
            <tr>
                <td>
                    <label>Sale Price</label>
                    <p id='sale_price'>{$row['sale_price']}</p>
                </td>
                <td>
                    <label>Cost Price</label>
                    <p id='cost_price'>{$row['cost_price']}</p>
                </td>
                <td>
                    <label>Profit Margin</label>
                    <p id='profit_margin'>{$row['profit_margin']}</p>
                </td>
                <td>
                    <label>Profit Rate</label>
                    <p id='profit_rate>{$row['profit_rate']}</p>
                </td>
            </tr>
        </table>
        ";
    endif;
}
function show_product_sale_price_edit(){
    if(isset($_SESSION["product_info"])):
        $row = $_SESSION["product_info"];
        echo "
            <table>
                <tr>
                    <td>
                        <form action='edit.run.php' method='post' class='signup-form'>
".cnrbyte_csrf_field()."
                            <lable for='sale_price'>Edit Sale Price</lable><br>
                            <input type='hidden' name='product_id' value='{$row["id"]}'>
                            <input type='number' step='0.01' min='0' required name='sale_price' value='{$row["sale_price"]}'>
                            <button name='edit_product_sale_price' class='form-btn'>Edit Product Sale Price</button>
                        </form>
                    </td>
            </table>
        ";
    endif;
}
function show_product_cost_price_edit(){
    if(isset($_SESSION["product_info"])):
        $row = $_SESSION["product_info"];
        echo "
            <table>
                <tr>
                    <td>
                        <form action='edit.run.php' method='post' class='signup-form'>
".cnrbyte_csrf_field()."
                            <label for='cost_price'>Edit Product Cost Price</label><br>
                            <input type='hidden' name='product_id' value='{$row["id"]}'>
                            <input type='number' step='0.01' min='0' required name='cost_price' value='{$row["cost_price"]}'>
                            <button name='edit_product_cost_price' class='form-btn'>Edit Product Cost Price</button>
                        </form>
                    </td>
                </tr>
            </table>
        ";
    endif;
}
function show_product_sale_and_cost_price_edit(){
    if(isset($_SESSION["product_info"])):
        $row = $_SESSION["product_info"];
        echo "
            <div>
            <table class='products_tbl'>
                <td><br><br><p>Change Product Sale Price</p></td>
                <tr>
                    <td>
                        <form action='edit.run.php' method='post' class='signup-form'>
".cnrbyte_csrf_field()."
                            <input type='hidden' name='product_id' value='".$row["id"]."'>
                            <input type='number' step='0.01' min='0' required name='sale_price' value='".$row["sale_price"]."'>
                            <button name='edit_product_sale_price' class='form-btn'>Edit Product Sale Price</button>
                        </form>
                    </td>
                </tr>
                <tr>
                    <td><br><br><p>Change Product Cost Price</p></td>
                </tr>
                <tr>
                    <td>
                        <form action='edit.run.php' method='post' class='signup-form'>
".cnrbyte_csrf_field()."
                            <input type='hidden' name='product_id' value='".$row["id"]."'>
                            <input type='number' step='0.01' min='0' required name='cost_price' value='".$row["cost_price"]."'>
                            <button name='edit_product_cost_price' class='form-btn'>Edit Product Cost Price</button>
                        </form>
                    </td>
                </tr>
            </table>
        ";
    endif;
}
//**************************************/
//cost items
//**************************************/
function create_cost_item_form($conn): void {
    if(!isset($_SESSION["vendor_info"])){
        return;
    }
    display_success_error_message();
    $vendor_id = (int)$_SESSION["vendor_info"]["id"];
    ?>
    <form action = "create.run.php" method = "post" enctype = "multipart/form-data" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <h3 class="form-title"><?php echo $_SESSION["vendor_info"]["reg_name"];?></h3>
        <p class="form-title">Create Product Cost Item</p>

        <label for = "type">Type of Cost</label>
        <select name = "type" class = "form-input">
            <option value = "">--- Select Type of Cost ---</option>
            <option value = "ingredients">Ingredient</option>
            <option value = "materials">Material</option>
            <option value = "overheads">Overhead</option>
        </select><br>

        <label for="source_product_id">Use Existing Product As Ingredient (Optional)</label>
        <select name="source_product_id" class="form-input">
            <option value="">--- None ---</option>
            <?php show_vendor_product_option_list($conn, $vendor_id); ?>
        </select><br>

        <label for = "cost_item_name">Cost Item Name</label>
        <input type="text" name = "cost_item_name" placeholder = "Cost Item Name"><br>

        <label for = "description">Description</label>
        <input type="text" name = "description" placeholder = "Description"><br>

        <label for = "measure">Unit Measure</label>
        <select name = "measure" class = "form-input">
            <option value = "">--- Select Unit Measure ---</option>
            <option value = "kilograms">Weight (Kilograms)</option>
            <option value = "litres">Capacity (Litres)</option>
            <option value = "metres">Distance (Metres)</option>
            <option value = "minutes">Time (Minutes)</option>
            <option value = "pieces">Piece (Units)</option>
            <option value = "slices">Slices (Units)</option>
        </select><br>

        <button name="create_cost_item" type = "submit" class = "form-btn">Create Cost Item</button>
    </form>
    <?php
}
function edit_cost_item_form($conn): void {
    if(isset($_SESSION["vendor_info"], $_SESSION["cost_item_info"], $_SESSION["selected_form"])){
    display_success_error_message();
        ?>
        <form action="edit.run.php" method="post" enctype = "multipart/form-data" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <?php echo '<h3 class="form-title">'.$_SESSION["vendor_info"]["reg_name"].'</h3>'; ?>
            <?php echo '<h3 class="form-title">'.$_SESSION["branch_info"]["branch_name"].'</h3>'; ?>
            <?php echo '<h3 class="form-title">'.$_SESSION["cost_item_info"]["cost_item_name"].'</h3>'; ?>
            <p class="form-title">Edit Cost Item</p>

            <label for="cost_item_name">Cost Item Name</label>
            <input name="cost_item_name" value="<?php echo $_SESSION["cost_item_info"]["cost_item_name"];?>"><br>

            <!-- Cost Info -->
            <label for="description">Description</label>
            <input name="description" value="<?php echo $_SESSION["cost_item_info"]["description"];?>"><br>

            <label for="measure">Unit Measure: <?php echo $_SESSION["cost_item_info"]["measure"];?></label>
            <select name = "measure" class = "form-input">
                <option value = "">--- Select Unit Measure ---</option>
                <option value = "kilograms">Weight (Kilograms)</option>
                <option value = "litres">Capacity (Litres)</option>
                <option value = "metres">Distance (Metres)</option>
                <option value = "minutes">Time (Minutes)</option>
                <option value = "pieces">Piece (Units)</option>
                <option value = "slices">Slices (Units)</option>
            </select><br>

            <label for = "cost_img">Change Product Image</label>
            <input type = "file" name = "cost_img" id = "file"><br>

            <button name="edit_cost_item" type="submit" class="form-btn">Edit Cost Item</button>
        </form>
        <?php
    }
}
function stock_cost_item_form($conn): void {
    if(isset($_SESSION["vendor_info"], $_SESSION["branch_info"], $_SESSION["cost_item_info"], $_SESSION["selected_form"])){
    display_success_error_message();
        ?>
        <form action = "edit.run.php" method = "post" class = "signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <?php echo '<h3 class="form-title">'.$_SESSION["vendor_info"]["reg_name"].'</h3>'; ?>
            <?php echo '<h3 class="form-title">'.$_SESSION["branch_info"]["branch_name"].'</h3>'; ?>
            <?php echo '<p class="form-title">'.$_SESSION["cost_item_info"]["cost_item_name"].': '.$_SESSION["cost_item_info"]["description"].'</p>'; ?>
            <p class="form-title">Stock Materials & Ingredients</p>
            <?php
                show_cost_profit_margin_info($conn);
                show_cost_item_cost_price_edit($conn);
            ?>
            <input type="hidden" name="cost_item_id" value = "<?php echo $_SESSION["cost_item_info"]["id"]; ?>">

            <label for = "select_journal">Select Payment Method</label>
            <select name = "select_journal" class="form-input" id = "payment_method">
                <option value = "">---Select Payment Method---</option>
                <option value = "cpj">Cash</option>
                <option value = "cj">Credit</option>
            </select>

            <label for = "cost_price">Cost Price: R <p id="total_costs" class="form-title"></p></label>
            <input type="number" name="cost_price" step="0.01" id="cost_price" placeholder = "Cost Price">

            <label for = "units_bought">Units :<?php echo $_SESSION["cost_item_info"]["measure"];?></label>
            <input type="number" name="units_bought" step="0.001" id="units_bought" onchange="updateTotalCost()" placeholder = "Units bought"><br>

            <button name="stock_cost_item" type = "submit" class="form-btn">Manual Purchase</button>
        </form>
        <script>
            function updateTotalCost() {
                const costs = document.getElementById('cost_price');
                const units = document.getElementById('units_bought');
                const input = document.getElementById('cost_price');
                const p = document.getElementById('total_costs');

                let cost_price = costs.innerHTML;
                let units_bought = units.value;
                let total_cost = cost_price * units_bought;
                    p.innerHTML = 'Total Costs R '+total_cost;
                    input.value = total_cost;

            }
        </script>
        <?php
    }
}
function show_ingredients_table(mysqli $conn): void {
    if (isset($_SESSION['vendor_info'], $_SESSION["selected_form"])) {
    display_success_error_message();

        $vendor_id = (int)$_SESSION["vendor_info"]["id"];
        $type = "ingredients";
        $ingredients_stmt = $conn->prepare("SELECT * FROM cost_items WHERE vendor_id = ? AND type = ?");
        $ingredients_stmt->bind_param("is", $vendor_id, $type);
        $ingredients_stmt->execute();
        $ingredients_result = $ingredients_stmt->get_result();
        while ($ingredients_row = $ingredients_result->fetch_assoc()) {
            show_cost_item_info_description($ingredients_row);
        }
    }
}
function show_materials_table(mysqli $conn): void {
    if (isset($_SESSION['vendor_info'], $_SESSION["selected_form"])) {
    display_success_error_message();

        $vendor_id = (int)$_SESSION["vendor_info"]["id"];
        $type = "materials";
        $materials_stmt = $conn->prepare("SELECT * FROM cost_items WHERE vendor_id = ? AND type = ?");
        $materials_stmt->bind_param("is", $vendor_id, $type);
        $materials_stmt->execute();
        $materials_result = $materials_stmt->get_result();
        while ($materials_row = $materials_result->fetch_assoc()) {
            show_cost_item_info_description($materials_row);
        }
    }
}
function show_overheads_table(mysqli $conn): void {
    if (isset($_SESSION['vendor_info'], $_SESSION["selected_form"])) {
    display_success_error_message();

        $vendor_id = (int)$_SESSION["vendor_info"]["id"];
        $type = "overheads";
        $overheads_stmt = $conn->prepare("SELECT * FROM cost_items WHERE vendor_id = ? AND type = ?");
        $overheads_stmt->bind_param("is", $vendor_id, $type);
        $overheads_stmt->execute();
        $overheads_result = $overheads_stmt->get_result();
        while ($overheads_row = $overheads_result->fetch_assoc()) {
            show_cost_item_info_description($overheads_row);
        }
    }
}
//show name, description and picture after selecting cost item
function show_cost_item_info_description(array $cost_item_row){
    if($cost_item_row != null || !empty($cost_item_row)):
    $cost_item_img = !empty($cost_item_row["cost_item_img"]) ? $cost_item_row["cost_item_img"] : "default_product.png";
    echo "<div>
            <table class='products_tbl'>
                <tr>
                    <td>
                        <h2>{$cost_item_row["cost_item_name"]}</h2>
                        <p class='form-title'>{$cost_item_row["description"]}</p>
                        <form action='/cnrbyte/includes/select.run.php' method='post' class='signup-form'><?php echo cnrbyte_csrf_field(); ?>
                            <button name='selected_cost_item' value='".$cost_item_row["id"]."' class='form-btn'>Select Cost Item</button>
                        </form>
                    </td>
                    <td>
                        <div class='banner-img'>
                            <img class='banner-img-el' src='../uploads/cost_items/{$cost_item_img}' alt='Banner'>
                        </div>
                    </td>
                </tr>
            </table>
            </div><br>
        ";
        echo ".............................................................";
    endif;
}
function show_cost_item_option_list(mysqli $conn, string $cost_item_type) {
    $vendor_id = (int)$_SESSION["vendor_info"]["id"];
    $cost_item_stmt = $conn->prepare("SELECT * FROM cost_items WHERE vendor_id = ? AND type = ?");
    $cost_item_stmt->bind_param("is", $vendor_id, $cost_item_type);
    $cost_item_stmt->execute();
    $cost_item_stmt_result = $cost_item_stmt->get_result();

    while ($cost_item_row = $cost_item_stmt_result->fetch_assoc()) {
        echo "<option value='{$cost_item_row["id"]}'>{$cost_item_row["cost_item_name"]} :{$cost_item_row["description"]}</option>";
    }

    $cost_item_stmt->close();
}
function show_cost_profit_margin_info(){
    if(isset($_SESSION["cost_item_info"])):
        $row = $_SESSION["cost_item_info"];
        echo"
            <table>
                <tr>
                    <td>
                        <label>Sale per Unit</label>
                        <p id='sale_price'>{$row['sale_price']}</p>
                    </td>
                    <td>
                        <label>Cost per Unit</label>
                        <p id='cost_price'>{$row['cost_price']}</p>
                    </td>
                    <td>
                        <label>Profit per Unit</label>
                        <p id='profit_margin'>{$row['profit_margin']}</p>
                    </td>
                    <td>
                        <label>Profit Rate per Unit</label>
                        <p id='profit_rate>{$row['profit_rate']}</p>
                    </td>
                </tr>
            </table>
        ";
    endif;
}
function show_cost_item_sale_price_edit(){
    if(isset($_SESSION["cost_item_info"])):
        $row = $_SESSION["cost_item_info"];
    echo "
        <table>
            <tr>
                <td>
                    <form action='edit.run.php' method='post' class='signup-form'>
".cnrbyte_csrf_field()."
                        <lable for='sale_price'>Edit Cost Item Sale Price</lable><br>
                        <input type='hidden' name='cost_item_id' value='{$row["id"]}'>
                        <input type='number' step='0.01' min='0' required name='sale_price' value='{$row["sale_price"]}'>
                        <button name='edit_cost_item_sale_price' class='form-btn'>Edit Cost Item Sale Price</button>
                    </form>
                </td>
        </table>
    ";
    endif;
}
function show_cost_item_cost_price_edit(){
    if(isset($_SESSION["cost_item_info"])):
        $row = $_SESSION["cost_item_info"];
    echo "
        <table>
            <tr>
                <td>
                    <form action='edit.run.php' method='post' class='signup-form'>
".cnrbyte_csrf_field()."
                        <lable for='cost_price'>Edit Cost Item Cost Price</lable><br>
                        <input type='hidden' name='cost_item_id' value='".$row["id"]."'>
                        <input type='number' step='0.01' min='0' required name='cost_price' value='".$row["cost_price"]."'>
                        <button name='edit_costs_cost_price' class='form-btn'>Edit Cost Item Cost Price</button>
                    </form>
                </td>
        </table>
    ";
    endif;
}
function show_cost_item_sale_and_cost_price_edit(){
    if(isset($_SESSION["cost_item_info"])):
        $row = $_SESSION["cost_item_info"];
    echo "
            <div>
            <table class='products_tbl'>
                <td><br><br><p>Change Cost Item Sale Price</p></td>
                <tr>
                    <td>
                        <form action='edit.run.php' method='post' class='signup-form'>
".cnrbyte_csrf_field()."
                            <input type='hidden' name='cost_item_id' value='".$row["id"]."'>
                            <input type='number' step='0.01' min='0' required name='sale_price' value='".$row["sale_price"]."'>
                            <button name='edit_cost_item_sale_price' class='form-btn'>Edit Cost Item Sale Price</button>
                        </form>
                    </td>
                </tr>
                <tr>
                    <td><br><br><p>Change Cost Item Cost Price</p></td>
                </tr>
                <tr>
                    <td>
                        <form action='edit.run.php' method='post' class='signup-form'>
".cnrbyte_csrf_field()."
                            <input type='hidden' name='cost_item_id' value='".$row["id"]."'>
                            <input type='number' step='0.01' min='0' required name='cost_price' value='".$row["cost_price"]."'>
                            <button name='edit_cost_item_cost_price' class='form-btn'>Edit Cost Item Cost Price</button>
                        </form>
                    </td>
                </tr>
            </table>
        ";
    endif;
}
//**************************************/
//other items
//**************************************/
function create_cost_of_sale_form($conn): void {
    if(!isset($_SESSION["vendor_info"], $_SESSION["selected_form"])){
        return;
    }
    display_success_error_message();
    $vendor_id = (int)$_SESSION["vendor_info"]["id"];
    ?>
    <form action = "create.run.php" method = "post" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <h3 class="form-title"><?php echo $_SESSION["vendor_info"]["reg_name"];?></h3>
        <p class="form-title">Create Product Costs</p>

        <label for="product_id">Select Product</label>
        <select name="product_id" class="form-input">
            <option value="">--- Select Product ---</option>
            <?php show_vendor_product_option_list($conn, $vendor_id); ?>
        </select><br>

        <label for = "type_id">Select Cost Item</label>
        <select name = "type_id" class = "form-input">
            <option value = "">--- Select Cost Item ---</option>
            <?php show_cost_item_option_list($conn, 'ingredients');?>
            <?php show_cost_item_option_list($conn, 'materials');?>
            <?php show_cost_item_option_list($conn, 'overheads');?>
        </select><br>

        <button name="create_cost_of_sale" type = "submit" class = "form-btn">Submit Product</button>
    </form>
    <?php
}
function create_product_hierarchy_form(mysqli $conn): void {
    if(!isset($_SESSION["vendor_info"], $_SESSION["selected_form"])){
        return;
    }
    product_hierarchy_bootstrap($conn);
    display_success_error_message();
    $vendor_id = (int)$_SESSION["vendor_info"]["id"];
    ?>
        <form action="create.run.php" method="post" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <h3 class='form-title'>Insert Great</h3>
            <h3 class='form-title'><?php echo $_SESSION["vendor_info"]["reg_name"];?></h3>

            <label for="category">Category</label>
            <input type="text" name="category">

            <input type="hidden" name="parent_id" value="0">
            <input type="hidden" name="rank" value="great">

            <button type='submit' name ='new_category' class='form-btn'>Submit</button>
        </form><br>

        <form action="create.run.php" method="post" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <h3 class='form-title'>Insert Grand</h3>
            <h3 class='form-title'><?php echo $_SESSION["vendor_info"]["reg_name"];?></h3>

            <label for="parent_id">Great</label>
            <select name="parent_id">
                <option value="">Select Great</option>
                <?php show_category_option_list_by_rank($conn, $vendor_id, 'great'); ?>
            </select>

            <label for="category">Grand Category Name</label>
            <input type="text" name="category">

            <input type="hidden" name="rank" value="grand">

            <button type='submit' name ='new_category' class='form-btn'>Submit</button>
        </form><br>

        <form action="create.run.php" method="post" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <h3 class='form-title'>Insert Parent</h3>
            <h3 class='form-title'><?php echo $_SESSION["vendor_info"]["reg_name"];?></h3>

            <label for="parent_id">Grand</label>
            <select name="parent_id">
                <option value="">Select Grand</option>
                <?php show_category_option_list_by_rank($conn, $vendor_id, 'grand'); ?>
            </select>

            <label for="category">Parent Category Name</label>
            <input type="text" name="category">

            <input type="hidden" name="rank" value="parent">

            <button type='submit' name ='new_category' class='form-btn'>Submit</button>
        </form><br>

        <form action="create.run.php" method="post" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <h3 class='form-title'>Insert Child</h3>
            <h3 class='form-title'><?php echo $_SESSION["vendor_info"]["reg_name"];?></h3>

            <label for="parent_id">Parent</label>
            <select name="parent_id">
                <option value="">Select Parent</option>
                <?php show_category_option_list_by_rank($conn, $vendor_id, 'parent'); ?>
            </select>

            <label for="category">Child Category Name</label>
            <input type="text" name="category">

            <input type="hidden" name="rank" value="child">

            <button type='submit' name ='new_category' class='form-btn'>Submit</button>
        </form><br>
    <?php
}

function show_category_option_list_by_rank(mysqli $conn, int $vendor_id, string $rank): void {
    product_hierarchy_bootstrap($conn);
    $rows = product_hierarchy_get_categories($conn, $rank);
    foreach ($rows as $row) {
        echo '<option value="' . (int)$row['id'] . '">' . htmlspecialchars((string)$row['category'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
}

function show_vendor_product_option_list(mysqli $conn, int $vendor_id): void {
    $stmt = $conn->prepare("SELECT id, product_name, description FROM products WHERE vendor_id = ? ORDER BY product_name ASC");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $description = trim((string)($row["description"] ?? ""));
        $label = $description !== '' ? "{$row["product_name"]} : {$description}" : $row["product_name"];
        echo "<option value='{$row["id"]}'>{$label}</option>";
    }
    $stmt->close();
}
