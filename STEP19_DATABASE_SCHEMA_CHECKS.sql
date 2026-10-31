-- CnrByte Step 19 read-only staging verification queries.
-- Select the intended CnrByte database before running these checks.

-- 1. Confirm critical tables and columns exist after migrations.
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
       (TABLE_NAME = 'products' AND COLUMN_NAME IN ('vendor_id','sale_price','cost_price','status','great','grand','parent','child'))
    OR (TABLE_NAME = 'quotations' AND COLUMN_NAME IN ('id','client_id','quote_name','grand_total','created_at'))
    OR (TABLE_NAME = 'quotation_items' AND COLUMN_NAME IN ('quote_id','product_id','quantity','unit_price','subtotal'))
    OR (TABLE_NAME = 'trust_members' AND COLUMN_NAME IN ('id','trust_application_id','member_name'))
    OR (TABLE_NAME = 'trust_beneficiaries' AND COLUMN_NAME IN ('trust_application_id','member_id','beneficiary_name','allocation_percentage'))
    OR (TABLE_NAME = 'roster' AND COLUMN_NAME IN ('vendor_id','branch_id','shift_id','staff_id','day_date','time_in','time_out','gross_earnings'))
    OR (TABLE_NAME = 'bal_stmt' AND COLUMN_NAME IN ('vendor_id','branch_id','acc_id','cost','rate','date_obtained'))
    OR (TABLE_NAME = 'shares' AND COLUMN_NAME IN ('vendor_id','branch_id','acc_id','share_type','share_action','num_of_shares'))
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- 2. Confirm product status values fit the application workflow.
SELECT status, COUNT(*) AS product_count
FROM products
GROUP BY status
ORDER BY status;

-- 3. Find quotation rows that do not have a corresponding client or product.
SELECT q.id, q.client_id
FROM quotations q
LEFT JOIN clients c ON c.id = q.client_id
WHERE c.id IS NULL;

SELECT qi.quote_id, qi.product_id
FROM quotation_items qi
LEFT JOIN quotations q ON q.id = qi.quote_id
LEFT JOIN products p ON p.id = qi.product_id
WHERE q.id IS NULL OR p.id IS NULL;

-- 4. Check for trust beneficiaries whose member/application references are orphaned.
SELECT b.id, b.trust_application_id, b.member_id
FROM trust_beneficiaries b
LEFT JOIN trust_applications a ON a.id = b.trust_application_id
LEFT JOIN trust_members m ON m.id = b.member_id AND m.trust_application_id = b.trust_application_id
WHERE a.id IS NULL OR m.id IS NULL;

-- 5. Inspect indexes added by Step 19.
SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS indexed_columns
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND INDEX_NAME IN (
    'idx_quotations_client_created',
    'idx_quotation_items_quote',
    'idx_crj_branch_account_amount_date',
    'idx_cpj_branch_account_amount_date',
    'idx_bal_stmt_branch_account_cost_date',
    'idx_shares_branch_account_type_date',
    'idx_types_owner_type_name'
  )
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME;
