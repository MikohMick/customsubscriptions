<?php
defined( 'ABSPATH' ) || exit;

class CM_Database {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Packages table.
        dbDelta( "CREATE TABLE " . CM_TABLE_PACKAGES . " (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(191)    NOT NULL,
            description TEXT            DEFAULT NULL,
            sessions    INT             NOT NULL DEFAULT 0 COMMENT '0 = unlimited',
            price       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
            product_id  BIGINT UNSIGNED DEFAULT NULL COMMENT 'WooCommerce product ID',
            sort_order  INT             NOT NULL DEFAULT 0,
            active      TINYINT(1)      NOT NULL DEFAULT 1,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        // Members table.
        dbDelta( "CREATE TABLE " . CM_TABLE_MEMBERS . " (
            id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name               VARCHAR(191)    NOT NULL,
            email              VARCHAR(191)    NOT NULL,
            phone              VARCHAR(50)     NOT NULL,
            location           VARCHAR(191)    NOT NULL,
            package_id         BIGINT UNSIGNED NOT NULL,
            sessions_total     INT             NOT NULL DEFAULT 0,
            sessions_remaining INT             NOT NULL DEFAULT 0,
            status             ENUM('pending','active','expired') NOT NULL DEFAULT 'pending',
            order_id           BIGINT UNSIGNED DEFAULT NULL,
            renewal_email_sent TINYINT(1)      NOT NULL DEFAULT 0,
            notes              TEXT            DEFAULT NULL,
            created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email   (email),
            KEY idx_status  (status),
            KEY idx_order   (order_id)
        ) $charset;" );

        // API keys table.
        dbDelta( "CREATE TABLE " . CM_TABLE_API_KEYS . " (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            label       VARCHAR(191)    NOT NULL,
            api_key     VARCHAR(64)     NOT NULL,
            permissions VARCHAR(20)     NOT NULL DEFAULT 'read' COMMENT 'read | write | read_write',
            last_used   DATETIME        DEFAULT NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY api_key (api_key)
        ) $charset;" );

        update_option( 'cm_db_version', CM_VERSION );
    }

    public static function deactivate() {
        // Nothing destructive on deactivate; tables stay.
    }
}
