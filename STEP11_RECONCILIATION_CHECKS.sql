-- Run on staging after applying includes/sql_tables/07_step11_refunds_settlement.sql.
-- Replace :vendor_id with an integer vendor ID using your database client.

-- Pending versus recorded settlements and refunded orders
SELECT vendor_id,
       SUM(CASE WHEN entry_status = 'pending_settlement' THEN vendor_payable ELSE 0 END) AS pending_payable,
       SUM(CASE WHEN entry_status = 'settled' THEN vendor_payable ELSE 0 END) AS settled_payable,
       SUM(CASE WHEN entry_status = 'refunded' THEN gross_sales ELSE 0 END) AS refunded_gross_sales
FROM marketplace_accounting_entries
WHERE vendor_id = :vendor_id
GROUP BY vendor_id;

-- Settlement records versus accounting records
SELECT a.order_ref, a.vendor_payable, a.entry_status,
       s.amount AS settlement_amount, s.external_payment_reference, s.settlement_status
FROM marketplace_accounting_entries a
LEFT JOIN marketplace_vendor_settlements s ON s.order_id = a.order_id
WHERE a.vendor_id = :vendor_id
ORDER BY a.created_at DESC;

-- Orders missing accounting entries
SELECT o.id, o.order_ref, o.total_amount, o.order_status, o.created_at
FROM marketplace_orders o
LEFT JOIN marketplace_accounting_entries a ON a.order_id = o.id
WHERE o.vendor_id = :vendor_id AND o.order_status <> 'cancelled' AND a.order_id IS NULL;

-- Duplicate refunds/settlements should be prevented by unique keys; this query should return no rows.
SELECT order_id, COUNT(*) AS records FROM marketplace_refunds GROUP BY order_id HAVING COUNT(*) > 1;
SELECT order_id, COUNT(*) AS records FROM marketplace_vendor_settlements GROUP BY order_id HAVING COUNT(*) > 1;
