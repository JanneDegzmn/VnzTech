ALTER TABLE submissions
    ADD COLUMN bulk_format TINYINT(1) NOT NULL DEFAULT 0 AFTER selling_method;

ALTER TABLE submission_items
    ADD COLUMN quantity_is_estimated TINYINT(1) NOT NULL DEFAULT 0 AFTER quantity;
