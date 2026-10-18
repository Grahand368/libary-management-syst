-- ============================================================================
-- 003_book_requests.sql
-- ----------------------------------------------------------------------------
-- Member → admin borrow-request workflow.
--
--   mysql -u root lms < database/003_book_requests.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS book_requests (
    request_id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id              INT NOT NULL,
    book_id              INT NOT NULL,
    request_date         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status               ENUM('Pending','Approved','Declined','Cancelled')
                         NOT NULL DEFAULT 'Pending',
    admin_response_date  DATETIME NULL,
    admin_response_by    INT NULL,
    admin_response_note  VARCHAR(500) NULL,
    due_date             DATE NULL,
    KEY idx_status_date (status, request_date),
    KEY idx_user        (user_id),
    KEY idx_book        (book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;