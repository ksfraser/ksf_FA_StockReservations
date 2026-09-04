-- Stock Reservations table
-- Prevents overselling by reserving stock for sales orders

CREATE TABLE IF NOT EXISTS `0_ksf_stock_reservations` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `item_code` VARCHAR(20) NOT NULL,
    `order_no` VARCHAR(20) NOT NULL,
    `order_line` INT NOT NULL DEFAULT 1,
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `status` ENUM('reserved', 'picked', 'shipped', 'cancelled', 'released') NOT NULL DEFAULT 'reserved',
    `reserved_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reserved_by` INT NOT NULL,
    `picked_at` DATETIME NULL,
    `picked_by` INT NULL,
    `shipped_at` DATETIME NULL,
    `shipped_by` INT NULL,
    `notes` TEXT NULL,
    INDEX `idx_item_code` (`item_code`),
    INDEX `idx_order_no` (`order_no`),
    INDEX `idx_status` (`status`),
    INDEX `idx_item_order` (`item_code`, `order_no`),
    CONSTRAINT `fk_reservation_stock` FOREIGN KEY (`item_code`) REFERENCES `0_stock_master` (`stock_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reservation_order` FOREIGN KEY (`order_no`) REFERENCES `0_sales_orders` (`order_no`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;