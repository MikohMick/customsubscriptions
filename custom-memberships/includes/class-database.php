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

        // Seed demo packages on first install only.
        self::maybe_seed_packages();
    }

    public static function maybe_seed_packages() {
        global $wpdb;

        // Only seed if no packages exist yet.
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . CM_TABLE_PACKAGES );
        if ( $count > 0 ) {
            return;
        }

        $packages = [
            // ── Class Rates (individual sessions) ─────────────────────────────
            [
                'name'        => 'Drop-In Class',
                'description' => 'Single session — perfect for trying us out.',
                'sessions'    => 1,
                'price'       => 1950.00,
                'sort_order'  => 1,
            ],
            [
                'name'        => 'Private Session',
                'description' => 'One-on-one personalised session.',
                'sessions'    => 1,
                'price'       => 3000.00,
                'sort_order'  => 2,
            ],
            [
                'name'        => 'Private Couple Session',
                'description' => 'Private session for two people.',
                'sessions'    => 1,
                'price'       => 5500.00,
                'sort_order'  => 3,
            ],
            // ── Class Packages (bundles) ───────────────────────────────────────
            [
                'name'        => '3 Classes',
                'description' => 'A great way to build a steady weekly routine.',
                'sessions'    => 3,
                'price'       => 5500.00,
                'sort_order'  => 4,
            ],
            [
                'name'        => '5 Classes',
                'description' => 'More sessions, better value. Perfect for committed members.',
                'sessions'    => 5,
                'price'       => 9000.00,
                'sort_order'  => 5,
            ],
            [
                'name'        => '8 Classes',
                'description' => 'Best-value class bundle — most popular choice!',
                'sessions'    => 8,
                'price'       => 13000.00,
                'sort_order'  => 6,
            ],
            // ── Unlimited Options ──────────────────────────────────────────────
            [
                'name'        => '30 Days Unlimited',
                'description' => 'Unlimited classes for a full 30 days.',
                'sessions'    => 0,
                'price'       => 22000.00,
                'sort_order'  => 7,
            ],
            [
                'name'        => '3 Month Package',
                'description' => 'Three months of unlimited classes — best value for dedicated members.',
                'sessions'    => 0,
                'price'       => 60000.00,
                'sort_order'  => 8,
            ],
        ];

        foreach ( $packages as $pkg ) {
            $wpdb->insert( CM_TABLE_PACKAGES, array_merge( $pkg, [ 'active' => 1 ] ) );
            $id = (int) $wpdb->insert_id;
            if ( $id && class_exists( 'WooCommerce' ) ) {
                CM_Packages::sync_product( $id );
            }
        }
    }

    public static function deactivate() {
        // Nothing destructive on deactivate; tables stay.
    }
}
