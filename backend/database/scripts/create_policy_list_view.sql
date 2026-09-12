-- Fast policy list view — pre-joins customer name and product name
-- Used by GET /api/v1/policies for much faster list loading
-- Run on production RDS: mysql -u admin -p graphite < create_policy_list_view.sql

CREATE OR REPLACE VIEW v_policy_list AS
SELECT
    p.id,
    p.policyNumber,
    p.status,
    p.premium,
    p.customer_id,
    p.product_id,
    p.agent_id,
    p.created_at,
    CONCAT(COALESCE(c.firstName, ''), ' ', COALESCE(c.lastName, '')) AS customer_name,
    c.cellphone AS customer_cellphone,
    pr.name AS product_name
FROM policies p
LEFT JOIN customers c ON c.id = p.customer_id
LEFT JOIN products pr ON pr.id = p.product_id;

-- Add indexes if they don't exist (check first on production)
-- These dramatically speed up filtering and pagination:

-- ALTER TABLE policies ADD INDEX idx_policies_status (status);
-- ALTER TABLE policies ADD INDEX idx_policies_product_id (product_id);
-- ALTER TABLE policies ADD INDEX idx_policies_policyNumber (policyNumber);
-- ALTER TABLE customers ADD INDEX idx_customers_firstName (firstName);
-- ALTER TABLE customers ADD INDEX idx_customers_lastName (lastName);
