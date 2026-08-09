<?php
defined( 'ABSPATH' ) || exit;

class CM_Database {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Categories table.
        dbDelta( "CREATE TABLE " . CM_TABLE_CATEGORIES . " (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(191)    NOT NULL,
            subtitle    VARCHAR(191)    DEFAULT NULL COMMENT 'e.g. Yoga + Mat Pilates',
            description TEXT            DEFAULT NULL,
            sort_order  INT             NOT NULL DEFAULT 0,
            active      TINYINT(1)      NOT NULL DEFAULT 1,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        // Packages table.
        dbDelta( "CREATE TABLE " . CM_TABLE_PACKAGES . " (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id  BIGINT UNSIGNED DEFAULT NULL,
            name         VARCHAR(191)    NOT NULL,
            description  TEXT            DEFAULT NULL,
            sessions     INT             NOT NULL DEFAULT 0 COMMENT '0 = unlimited',
            session_unit VARCHAR(50)     NOT NULL DEFAULT 'Session' COMMENT 'e.g. Class, Reformer Class',
            perks        TEXT            DEFAULT NULL COMMENT 'One perk per line, shown as bullets',
            highlight    TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Show a Most Popular badge',
            price        DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
            product_id   BIGINT UNSIGNED DEFAULT NULL COMMENT 'WooCommerce product ID',
            sort_order   INT             NOT NULL DEFAULT 0,
            active       TINYINT(1)      NOT NULL DEFAULT 1,
            created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_category (category_id)
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

        // Seed the FlowForm rate card on first install only.
        self::maybe_seed_packages();
    }

    /**
     * Seed categories + packages from the FlowForm rate card.
     * Only runs when the categories table is empty, so it never
     * duplicates or overwrites anything the admin has set up.
     */
    public static function maybe_seed_packages() {
        global $wpdb;

        if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . CM_TABLE_CATEGORIES ) > 0 ) {
            return;
        }

        $catalog = [
            [
                'category' => [
                    'name'        => 'FlowForm Essentials',
                    'subtitle'    => 'Yoga + Mat Pilates',
                    'description' => 'Perfect for building a steady practice with yoga and mat pilates.',
                    'sort_order'  => 1,
                ],
                'packages' => [
                    [ 'name' => 'Drop-In Class',      'sessions' => 1, 'session_unit' => 'Class', 'price' => 975.00,   'sort_order' => 1, 'description' => 'Single class — perfect for trying us out.' ],
                    [ 'name' => '4 Classes',          'sessions' => 4, 'session_unit' => 'Class', 'price' => 3500.00,  'sort_order' => 2 ],
                    [ 'name' => '8 Classes',          'sessions' => 8, 'session_unit' => 'Class', 'price' => 6500.00,  'sort_order' => 3 ],
                    [ 'name' => 'Unlimited Monthly',  'sessions' => 0, 'session_unit' => 'Class', 'price' => 11000.00, 'sort_order' => 4, 'description' => 'Unlimited yoga and mat pilates for one month.' ],
                ],
            ],
            [
                'category' => [
                    'name'        => 'FlowForm Signature',
                    'subtitle'    => 'Reformer + Yoga + Mat Pilates',
                    'description' => 'Reformer classes bundled with unlimited yoga and mat pilates.',
                    'sort_order'  => 2,
                ],
                'packages' => [
                    [
                        'name'         => 'Signature Lite',
                        'sessions'     => 4,
                        'session_unit' => 'Reformer Class',
                        'price'        => 15500.00,
                        'sort_order'   => 1,
                        'perks'        => "4 Reformer Classes\nUnlimited Yoga & Mat Pilates",
                    ],
                    [
                        'name'         => 'Signature Plus',
                        'sessions'     => 8,
                        'session_unit' => 'Reformer Class',
                        'price'        => 20000.00,
                        'sort_order'   => 2,
                        'highlight'    => 1,
                        'perks'        => "8 Reformer Classes\nUnlimited Yoga & Mat Pilates",
                    ],
                ],
            ],
            [
                'category' => [
                    'name'        => 'Reformer Only',
                    'subtitle'    => 'Reformer classes',
                    'description' => 'Focused reformer training, priced per session or in packages.',
                    'sort_order'  => 3,
                ],
                'packages' => [
                    [ 'name' => 'Single Reformer Session', 'sessions' => 1,  'session_unit' => 'Reformer Class', 'price' => 1500.00,  'sort_order' => 1 ],
                    [ 'name' => '4 Reformer Classes',      'sessions' => 4,  'session_unit' => 'Reformer Class', 'price' => 5500.00,  'sort_order' => 2 ],
                    [ 'name' => '8 Reformer Classes',      'sessions' => 8,  'session_unit' => 'Reformer Class', 'price' => 11000.00, 'sort_order' => 3 ],
                    [ 'name' => '12 Reformer Classes',     'sessions' => 12, 'session_unit' => 'Reformer Class', 'price' => 17000.00, 'sort_order' => 4 ],
                ],
            ],
            [
                'category' => [
                    'name'        => 'FlowForm Elite',
                    'subtitle'    => 'Everything, unlimited',
                    'description' => 'Full access to every class we offer, plus priority booking.',
                    'sort_order'  => 4,
                ],
                'packages' => [
                    [
                        'name'         => 'FlowForm Elite Monthly',
                        'sessions'     => 0,
                        'session_unit' => 'Class',
                        'price'        => 35000.00,
                        'sort_order'   => 1,
                        'perks'        => "Unlimited Reformer\nUnlimited Yoga\nUnlimited Mat Pilates\nPriority Booking",
                    ],
                ],
            ],
        ];

        foreach ( $catalog as $group ) {
            $wpdb->insert( CM_TABLE_CATEGORIES, array_merge( $group['category'], [ 'active' => 1 ] ) );
            $category_id = (int) $wpdb->insert_id;
            if ( ! $category_id ) {
                continue;
            }

            foreach ( $group['packages'] as $pkg ) {
                $wpdb->insert( CM_TABLE_PACKAGES, array_merge( [
                    'description'  => '',
                    'perks'        => '',
                    'highlight'    => 0,
                    'session_unit' => 'Session',
                ], $pkg, [
                    'category_id' => $category_id,
                    'active'      => 1,
                ] ) );

                $pkg_id = (int) $wpdb->insert_id;
                if ( $pkg_id && class_exists( 'WooCommerce' ) ) {
                    CM_Packages::sync_product( $pkg_id );
                }
            }
        }
    }

    public static function deactivate() {
        // Nothing destructive on deactivate; tables stay.
    }
}
