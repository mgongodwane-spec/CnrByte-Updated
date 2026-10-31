# CnrByte — Step 4 Cost and Selling Price Audit

## Changes made

- Product creation now requires numeric, finite, non-negative sale and cost prices within the `DECIMAL(12,2)` range and stores them rounded to two decimal places.
- Product sale-price and cost-price edits now validate the amount and verify that the product belongs to the logged-in vendor before updating it. SQL updates include both `id` and `vendor_id`.
- The standalone cost-price edit form now displays the current `cost_price` and submits `edit_product_cost_price` instead of accidentally submitting the sale-price action.
- Product detail edits are restricted to products owned by the current vendor.
- Sales recording checks product ownership, validates sales/cost amounts and units, rejects invalid journal selections, and guards against division by zero when calculating margins.
- Cost-item stocking checks vendor ownership and rejects zero/invalid purchase quantities to avoid division by zero.
- Linking a cost item to a product now verifies that both records belong to the current vendor.
- The current marketplace cart derives sale prices from the database product record rather than accepting a browser-supplied price; checkout totals and order item prices are calculated from those server-side cart records.

## Validation performed

- PHP syntax checks passed for the three modified PHP files: `marketplace/products/edit.run.php`, `marketplace/products/create.run.php`, and `marketplace/products/products.shw.php`.
- A whole-project PHP lint attempt did not complete: it stopped on a pre-existing strict-types placement error in `vendors/forms.run.php` and then timed out. This patch does not modify that file.
- No live database or end-to-end checkout test was run in this environment.

## Follow-up findings

- `marketplace/products/cart/cart.func.php` debits a buyer balance before all vendor order records are confirmed successfully inserted. This is a payment/transaction atomicity issue and should be handled in a separate financial-core step using a database transaction and verified order writes.
- `marketplace/products/cart/cart.dbs.php` retains legacy helper functions that accept sale/cost prices as arguments. No active call sites to its `insert_to_cart()` helper were found in the PHP source scan; do not reactivate that legacy path without making database prices authoritative.
- Price changes are limited to two decimal places to match `DECIMAL(12,2)`. Business rules allowing free products are preserved; this patch does not prohibit a sale price below cost price.
