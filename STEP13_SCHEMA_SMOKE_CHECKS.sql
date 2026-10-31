-- CnrByte Step 13 schema smoke checks (read-only).
-- Run after applying 01, 06, 07 and 08 on staging.

SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'vendors','branches','shifts','staff','roster','staff_payslip',
    'qualifications','staff_leave','staff_earnings_deductions',
    'marketplace_orders','marketplace_order_items',
    'marketplace_accounting_entries','marketplace_refunds',
    'marketplace_vendor_settlements','cnrbyte_messages',
    'cnrbyte_notifications','messages','cart','companies'
  )
ORDER BY TABLE_NAME;

SELECT TABLE_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    (TABLE_NAME='vendors' AND COLUMN_NAME IN ('client_id','user_id','status','reg_name'))
    OR (TABLE_NAME='branches' AND COLUMN_NAME IN ('vendor_id','branch_name','branch_code'))
    OR (TABLE_NAME='shifts' AND COLUMN_NAME IN ('vendor_id','branch_id','shift_name'))
    OR (TABLE_NAME='staff' AND COLUMN_NAME IN ('vendor_id','branch_id','shift_id','first_names','last_name','rate'))
    OR (TABLE_NAME='roster' AND COLUMN_NAME IN ('vendor_id','branch_id','shift_id','staff_id','rate_description','gross_earnings'))
    OR (TABLE_NAME='marketplace_orders' AND COLUMN_NAME IN ('payment_method','delivery_street','delivery_postal_code'))
    OR (TABLE_NAME='marketplace_order_items' AND COLUMN_NAME IN ('unit_cost','cost_total'))
  )
ORDER BY TABLE_NAME, COLUMN_NAME;

-- These should return no rows: orphaned roster records by vendor ownership.
SELECT r.id, r.vendor_id, r.branch_id, r.shift_id
FROM roster r
LEFT JOIN branches b ON b.id=r.branch_id AND b.vendor_id=r.vendor_id
LEFT JOIN shifts s ON s.id=r.shift_id AND s.vendor_id=r.vendor_id AND s.branch_id=r.branch_id
WHERE (r.branch_id IS NOT NULL AND b.id IS NULL)
   OR (r.shift_id IS NOT NULL AND s.id IS NULL);

-- Verify every posted general-ledger journal is balanced for a selected vendor.
-- Replace 123 with a real vendor id.
SELECT je.id, je.reference, SUM(jl.debit) AS debits, SUM(jl.credit) AS credits
FROM journal_entries je
JOIN journal_entry_lines jl ON jl.journal_entry_id=je.id
WHERE je.vendor_id=123 AND je.status='posted'
GROUP BY je.id, je.reference
HAVING ABS(SUM(jl.debit)-SUM(jl.credit)) > 0.01;
