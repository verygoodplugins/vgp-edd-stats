-- Anonymization SQL Transforms for EDD Data
-- Protects customer privacy while preserving data patterns for analytics
-- Run after importing live data into development database

-- Anonymize customer email addresses
-- Converts: user@example.com -> anon_md5hash@localhost.dev
UPDATE wp_edd_customers
SET email = CONCAT('anon_', MD5(email), '@localhost.dev')
WHERE email IS NOT NULL;

-- Anonymize customer names
-- Converts: John Doe -> Customer_12345
UPDATE wp_edd_customers
SET name = CONCAT('Customer_', id)
WHERE name IS NOT NULL;

-- Anonymize order email addresses (if different from customer)
UPDATE wp_edd_orders
SET email = CONCAT('anon_', MD5(email), '@localhost.dev')
WHERE email IS NOT NULL;

-- Anonymize IP addresses
-- Replace with random IP in private range (192.168.x.x)
UPDATE wp_edd_orders
SET ip = CONCAT('192.168.', FLOOR(1 + RAND() * 254), '.', FLOOR(1 + RAND() * 254))
WHERE ip IS NOT NULL;

-- Anonymize customer IP addresses in customer meta (if exists)
UPDATE wp_edd_customermeta
SET meta_value = CONCAT('192.168.', FLOOR(1 + RAND() * 254), '.', FLOOR(1 + RAND() * 254))
WHERE meta_key = '_edd_customer_ip'
AND meta_value IS NOT NULL;

-- Note: The following data is PRESERVED for realistic analytics:
-- - Customer IDs (relationships between tables)
-- - Order amounts and totals
-- - Dates and timestamps
-- - Subscription statuses and periods
-- - Transaction counts and patterns
-- - Product/download IDs
-- - License information (keys, activation counts)
