<?php
defined( 'ABSPATH' ) || exit;

class CM_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // AJAX actions for admin operations.
        add_action( 'wp_ajax_cm_admin_save_package',   [ __CLASS__, 'ajax_save_package' ] );
        add_action( 'wp_ajax_cm_admin_delete_package', [ __CLASS__, 'ajax_delete_package' ] );
        add_action( 'wp_ajax_cm_admin_save_category',   [ __CLASS__, 'ajax_save_category' ] );
        add_action( 'wp_ajax_cm_admin_delete_category', [ __CLASS__, 'ajax_delete_category' ] );
        add_action( 'wp_ajax_cm_admin_save_member',    [ __CLASS__, 'ajax_save_member' ] );
        add_action( 'wp_ajax_cm_admin_delete_member',  [ __CLASS__, 'ajax_delete_member' ] );
        add_action( 'wp_ajax_cm_admin_use_session',    [ __CLASS__, 'ajax_use_session' ] );
    }

    // -------------------------------------------------------------------------
    // Menus
    // -------------------------------------------------------------------------

    public static function register_menus() {
        add_menu_page(
            __( 'Memberships', 'custom-memberships' ),
            __( 'Memberships', 'custom-memberships' ),
            'manage_woocommerce',
            'cm-members',
            [ __CLASS__, 'page_members' ],
            'dashicons-groups',
            56
        );
        add_submenu_page(
            'cm-members',
            __( 'Members', 'custom-memberships' ),
            __( 'Members', 'custom-memberships' ),
            'manage_woocommerce',
            'cm-members',
            [ __CLASS__, 'page_members' ]
        );
        add_submenu_page(
            'cm-members',
            __( 'Packages', 'custom-memberships' ),
            __( 'Packages', 'custom-memberships' ),
            'manage_woocommerce',
            'cm-packages',
            [ __CLASS__, 'page_packages' ]
        );
        add_submenu_page(
            'cm-members',
            __( 'Categories', 'custom-memberships' ),
            __( 'Categories', 'custom-memberships' ),
            'manage_woocommerce',
            'cm-categories',
            [ __CLASS__, 'page_categories' ]
        );
        add_submenu_page(
            'cm-members',
            __( 'API Keys', 'custom-memberships' ),
            __( 'API Keys', 'custom-memberships' ),
            'manage_options',
            'cm-api-keys',
            [ __CLASS__, 'page_api_keys' ]
        );
        add_submenu_page(
            'cm-members',
            __( 'Settings', 'custom-memberships' ),
            __( 'Settings', 'custom-memberships' ),
            'manage_options',
            'cm-settings',
            [ __CLASS__, 'page_settings' ]
        );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public static function enqueue_assets( $hook ) {
        $pages = [
            'toplevel_page_cm-members',
            'memberships_page_cm-packages',
            'memberships_page_cm-categories',
            'memberships_page_cm-api-keys',
            'memberships_page_cm-settings',
        ];
        if ( ! in_array( $hook, $pages, true ) ) {
            return;
        }
        wp_enqueue_style(
            'cm-admin',
            CM_PLUGIN_URL . 'admin/assets/admin.css',
            [],
            CM_VERSION
        );
        wp_enqueue_script(
            'cm-admin',
            CM_PLUGIN_URL . 'admin/assets/admin.js',
            [ 'jquery' ],
            CM_VERSION,
            true
        );
        wp_localize_script( 'cm-admin', 'cmAdmin', [
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'cm_admin' ),
            'currencySymbol' => get_woocommerce_currency_symbol(),
            'dateFormat'     => get_option( 'date_format' ),
            'i18n'           => [
                'confirm_delete' => __( 'Are you sure? This cannot be undone.', 'custom-memberships' ),
                'confirm_delete_category' => __( 'Delete this category? Its packages will be kept but moved to Uncategorized.', 'custom-memberships' ),
                'saved'          => __( 'Saved!', 'custom-memberships' ),
                'deleted'        => __( 'Deleted.', 'custom-memberships' ),
                'error'          => __( 'An error occurred. Please try again.', 'custom-memberships' ),
                'copy_success'   => __( 'Copied!', 'custom-memberships' ),
                'unlimited'      => __( 'Unlimited', 'custom-memberships' ),
                'new_member'     => __( 'New member added. Reloading…', 'custom-memberships' ),
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------------

    public static function page_members() {
        require CM_PLUGIN_DIR . 'admin/views/members.php';
    }

    public static function page_packages() {
        require CM_PLUGIN_DIR . 'admin/views/packages.php';
    }

    public static function page_categories() {
        require CM_PLUGIN_DIR . 'admin/views/categories.php';
    }

    public static function page_api_keys() {
        require CM_PLUGIN_DIR . 'admin/views/api-keys.php';
    }

    public static function page_settings() {
        if ( isset( $_POST['cm_settings_nonce'] ) && wp_verify_nonce( $_POST['cm_settings_nonce'], 'cm_save_settings' ) ) {
            update_option( 'cm_email_from_name', sanitize_text_field( $_POST['cm_email_from_name'] ?? '' ) );
            update_option( 'cm_email_from',      sanitize_email( $_POST['cm_email_from'] ?? '' ) );
            update_option( 'cm_renewal_page_id', intval( $_POST['cm_renewal_page_id'] ?? 0 ) );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'custom-memberships' ) . '</p></div>';
        }
        require CM_PLUGIN_DIR . 'admin/views/settings.php';
    }

    // -------------------------------------------------------------------------
    // AJAX: Packages
    // -------------------------------------------------------------------------

    public static function ajax_save_package() {
        check_ajax_referer( 'cm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }

        $id   = intval( $_POST['id'] ?? 0 );
        $data = [
            'name'         => sanitize_text_field( $_POST['name'] ?? '' ),
            'description'  => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'perks'        => sanitize_textarea_field( $_POST['perks'] ?? '' ),
            'sessions'     => intval( $_POST['sessions'] ?? 0 ),
            'session_unit' => sanitize_text_field( $_POST['session_unit'] ?? 'Session' ),
            'price'        => floatval( $_POST['price'] ?? 0 ),
            'sort_order'   => intval( $_POST['sort_order'] ?? 0 ),
            'active'       => intval( $_POST['active'] ?? 1 ),
            'highlight'    => intval( $_POST['highlight'] ?? 0 ),
            'category_id'  => intval( $_POST['category_id'] ?? 0 ),
        ];

        if ( ! $data['name'] ) {
            wp_send_json_error( [ 'message' => 'Name is required.' ] );
        }

        if ( $id ) {
            CM_Packages::update( $id, $data );
        } else {
            $id = CM_Packages::create( $data );
        }

        wp_send_json_success( [ 'id' => $id, 'package' => CM_Packages::get( $id ) ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: Categories
    // -------------------------------------------------------------------------

    public static function ajax_save_category() {
        check_ajax_referer( 'cm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }

        $id   = intval( $_POST['id'] ?? 0 );
        $data = [
            'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
            'subtitle'    => sanitize_text_field( $_POST['subtitle'] ?? '' ),
            'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'sort_order'  => intval( $_POST['sort_order'] ?? 0 ),
            'active'      => intval( $_POST['active'] ?? 1 ),
        ];

        if ( ! $data['name'] ) {
            wp_send_json_error( [ 'message' => 'Name is required.' ] );
        }

        if ( $id ) {
            CM_Categories::update( $id, $data );
        } else {
            $id = CM_Categories::create( $data );
        }

        wp_send_json_success( [ 'id' => $id, 'category' => CM_Categories::get( $id ) ] );
    }

    public static function ajax_delete_category() {
        check_ajax_referer( 'cm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => 'Invalid ID.' ] );
        }
        CM_Categories::delete( $id );
        wp_send_json_success();
    }

    public static function ajax_delete_package() {
        check_ajax_referer( 'cm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => 'Invalid ID.' ] );
        }
        CM_Packages::delete( $id );
        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // AJAX: Members
    // -------------------------------------------------------------------------

    public static function ajax_save_member() {
        check_ajax_referer( 'cm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }

        $id   = intval( $_POST['id'] ?? 0 );
        $data = [
            'name'               => sanitize_text_field( $_POST['name'] ?? '' ),
            'email'              => sanitize_email( $_POST['email'] ?? '' ),
            'phone'              => sanitize_text_field( $_POST['phone'] ?? '' ),
            'location'           => sanitize_text_field( $_POST['location'] ?? '' ),
            'status'             => sanitize_text_field( $_POST['status'] ?? 'active' ),
            'sessions_remaining' => intval( $_POST['sessions_remaining'] ?? 0 ),
            'notes'              => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        ];

        if ( $id ) {
            // Capture session count before saving so we can detect changes.
            $before           = CM_Memberships::get( $id );
            $sessions_before  = $before ? (int) $before->sessions_remaining : 0;

            CM_Memberships::update( $id, $data );

            // Reset renewal flag if sessions were added back.
            if ( (int) $data['sessions_remaining'] > 0 ) {
                CM_Memberships::update( $id, [ 'renewal_email_sent' => 0 ] );
            }

            // Notify member if sessions changed.
            $fresh = CM_Memberships::get( $id );
            if ( $sessions_before !== (int) $fresh->sessions_remaining ) {
                CM_Emails::send_session_update( $fresh, $sessions_before );
            }
        } else {
            $package_id = intval( $_POST['package_id'] ?? 0 );
            if ( ! $package_id ) {
                wp_send_json_error( [ 'message' => 'Package is required.' ] );
            }
            $data['package_id'] = $package_id;
            $id = CM_Memberships::create( $data );
            if ( is_wp_error( $id ) ) {
                wp_send_json_error( [ 'message' => $id->get_error_message() ] );
            }

            // Welcome email for manually added members.
            CM_Emails::send_manual_welcome( CM_Memberships::get( $id ) );
        }

        wp_send_json_success( [ 'id' => $id, 'member' => CM_Memberships::get( $id ) ] );
    }

    public static function ajax_delete_member() {
        check_ajax_referer( 'cm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => 'Invalid ID.' ] );
        }
        CM_Memberships::delete( $id );
        wp_send_json_success();
    }

    public static function ajax_use_session() {
        check_ajax_referer( 'cm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }
        $id     = intval( $_POST['id'] ?? 0 );
        $amount = max( 1, intval( $_POST['amount'] ?? 1 ) );

        // Capture before so we can show the diff in the email.
        $before          = CM_Memberships::get( $id );
        $sessions_before = $before ? (int) $before->sessions_remaining : 0;

        $result = CM_Memberships::use_sessions( $id, $amount );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $member = CM_Memberships::get( $id );

        // Send session notification (send_session_update skips if remaining hits 0 —
        // that case is already handled by the renewal reminder inside use_sessions()).
        CM_Emails::send_session_update( $member, $sessions_before );

        wp_send_json_success( [ 'member' => $member ] );
    }
}
