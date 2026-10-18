-- ============================================================================
-- 004_purchase_requests.sql
-- ----------------------------------------------------------------------------
-- Member purchase-suggestion workflow (library purchase, NOT a payment).
--
--   mysql -u root lms < database/004_purchase_requests.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS purchase_requests (
    purchase_request_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id              INT NOT NULL,
    book_title           VARCHAR(255) NOT NULL,
    author_name          VARCHAR(255) NULL,
    isbn                 VARCHAR(32) NULL,
    reason               TEXT NULL,
    estimated_price      DECIMAL(10,2) NULL,
    status               ENUM('Pending','Approved','Purchased','Declined','Rejected')
                         NOT NULL DEFAULT 'Pending',
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
    admin_response_by    INT NULL,
    admin_response_note  VARCHAR(500) NULL,
    KEY idx_user         (user_id),
    KEY idx_status_date  (status, created_at),
    KEY idx_title        (book_title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;