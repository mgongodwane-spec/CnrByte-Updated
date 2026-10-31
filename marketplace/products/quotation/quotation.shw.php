<?php
declare(strict_types=1);

function show_quotation_builder_form(mysqli $conn): void
{
    ?>
    <div class="user_form">
        <form action="/cnrbyte/marketplace/products/quotation/quotation.run.php" method="post" class="input_form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <h3 class="form-title">Quotation Builder</h3>
            <label for="quote_name">Project / Quote Name</label>
            <input id="quote_name" type="text" name="quote_name" maxlength="255" placeholder="e.g. Warehouse Roofing" required>
            <div class="cnr-table-wrap">
                <table class="roster_tbl" style="width:100%;margin-bottom:20px;">
                    <thead><tr><th>Product</th><th>Qty</th><th>Unit price</th><th>Subtotal (estimate)</th></tr></thead>
                    <tbody id="quote-rows">
                        <tr class="item-row">
                            <td><select name="product_ids[]" class="form-select" required onchange="updateRowPrice(this)"><option value="">-- Select Product --</option><?php get_products_for_dropdown($conn); ?></select></td>
                            <td><input type="number" name="qtys[]" min="0.01" max="100000" step="0.01" value="1" required oninput="calculateRow(this)" style="width:80px;"></td>
                            <td><input type="text" class="row-price" readonly aria-label="Unit price estimate" style="width:100px;border:none;background:transparent;"></td>
                            <td><input type="text" class="row-total" readonly aria-label="Subtotal estimate" style="width:120px;border:none;background:transparent;" value="0.00"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="form-help">Prices shown here are estimates. The server recalculates totals from the current product prices when you save.</p>
            <button type="button" class="form-btn secondary" onclick="addNewRow()">+ Add Another Item</button>
            <button type="submit" name="save_quote" class="form-btn">Save and Generate Quote</button>
        </form>
    </div>
    <script>
    function updateRowPrice(selectElement) {
        const option = selectElement.options[selectElement.selectedIndex];
        const row = selectElement.closest('tr');
        row.querySelector('.row-price').value = option.getAttribute('data-price') || '0.00';
        calculateRow(row.querySelector('input[name="qtys[]"]'));
    }
    function calculateRow(qtyInput) {
        const row = qtyInput.closest('tr');
        const qty = Number(qtyInput.value) || 0;
        const price = Number(row.querySelector('.row-price').value) || 0;
        row.querySelector('.row-total').value = (qty * price).toFixed(2);
    }
    function addNewRow() {
        const tbody = document.getElementById('quote-rows');
        if (tbody.querySelectorAll('.item-row').length >= 50) { alert('A quotation can contain at most 50 items.'); return; }
        const row = tbody.querySelector('.item-row').cloneNode(true);
        row.querySelector('select').selectedIndex = 0;
        row.querySelectorAll('input').forEach(input => { input.value = input.name === 'qtys[]' ? '1' : (input.classList.contains('row-total') ? '0.00' : ''); });
        tbody.appendChild(row);
    }
    </script>
    <?php
}

function get_products_for_dropdown(mysqli $conn): void
{
    $sql = "SELECT p.id, p.product_name, p.sale_price, COALESCE(pc.category, c.category, 'Other') AS category_name
            FROM products p
            INNER JOIN vendors v ON v.id = p.vendor_id AND v.status = 'approved'
            LEFT JOIN product_child_categories pc ON pc.id = p.child
            LEFT JOIN categories c ON c.id = p.child AND c.rank = 'child'
            ORDER BY category_name, p.product_name";
    $result = $conn->query($sql);
    if (!$result) {
        error_log('CnrByte quotation product lookup failed: ' . $conn->error);
        return;
    }
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id'];
        $name = htmlspecialchars((string)$row['product_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $category = htmlspecialchars((string)$row['category_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $price = number_format((float)$row['sale_price'], 2, '.', '');
        echo '<option value="' . $id . '" data-price="' . $price . '">[' . $category . '] ' . $name . ' (R' . $price . ')</option>';
    }
    $result->free();
}
