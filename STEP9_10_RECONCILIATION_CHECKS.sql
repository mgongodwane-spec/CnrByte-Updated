-- Step 9/10 manual reconciliation checks. Run read-only queries in staging/phpMyAdmin.
-- Replace :vendor_id, :from_date and :to_date with actual values before running.

-- 1. Orders vs marketplace operational subledger by vendor and date.
SELECT o.vendor_id,
       ROUND(SUM(o.total_amount), 2) AS order_sales,
       ROUND(SUM(COALESCE(a.gross_sales, 0)), 2) AS subledger_sales,
       ROUND(SUM(o.total_amount) - SUM(COALESCE(a.gross_sales, 0)), 2) AS difference
FROM marketplace_orders o
LEFT JOIN marketplace_accounting_entries a ON a.order_id = o.id
WHERE o.vendor_id = :vendor_id
  AND o.created_at >= :from_date
  AND o.created_at < DATE_ADD(:to_date, INTERVAL 1 DAY)
  AND o.order_status <> 'cancelled'
GROUP BY o.vendor_id;

-- 2. Check every posted journal is balanced.
SELECT je.id, je.reference,
       ROUND(SUM(jl.debit), 2) AS total_debits,
       ROUND(SUM(jl.credit), 2) AS total_credits,
       ROUND(SUM(jl.debit) - SUM(jl.credit), 2) AS difference
FROM journal_entries je
JOIN journal_entry_lines jl ON jl.journal_entry_id = je.id
WHERE je.vendor_id = :vendor_id
  AND je.entry_date BETWEEN :from_date AND :to_date
  AND je.status = 'posted'
GROUP BY je.id, je.reference
HAVING ABS(SUM(jl.debit) - SUM(jl.credit)) > 0.01;

-- 3. Product stock ledger totals (the signed quantity/value deltas should agree with stock activity).
SELECT product_id,
       SUM(quantity_delta) AS net_units_moved,
       SUM(value_delta) AS net_inventory_value_movement
FROM inventory_movements
WHERE vendor_id = :vendor_id
  AND created_at >= :from_date
  AND created_at < DATE_ADD(:to_date, INTERVAL 1 DAY)
GROUP BY product_id;

-- 4. Orders missing either the marketplace subledger or a GL journal.
SELECT o.id, o.order_ref, o.total_amount
FROM marketplace_orders o
LEFT JOIN marketplace_accounting_entries a ON a.order_id = o.id
LEFT JOIN journal_entries je ON je.vendor_id = o.vendor_id
    AND je.source_type = 'marketplace_order' AND je.source_id = o.id
WHERE o.vendor_id = :vendor_id
  AND o.created_at >= :from_date
  AND o.created_at < DATE_ADD(:to_date, INTERVAL 1 DAY)
  AND o.order_status <> 'cancelled'
  AND (a.id IS NULL OR je.id IS NULL);
