<?php
defined( 'ABSPATH' ) || exit;

class CM_WooCommerce {

    public static function init() {
        // Create membership when order is completed.
        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'handle_order_completed' ], 10, 1 );
        // Also handle processing status (digital/virtual products often skip "completed").
        add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'handle_order_completed' ], 10, 1 );

        // Store form data in session so we can populate checkout fields.
        add_filter( 'woocommerce_checkout_fields', [ __CLASS__, 'prefill_checkout_fields' ] );

        // Auto-fill billing fields from our session data.
        add_filter( 'woocommerce_checkout_get_value', [ __CLASS__, 'get_checkout_field_value' ], 10, 2 );

        // Add hidden order meta with member info.
        add_action( 'woocommerce_checkout_update_order_meta', [ __CLASS__, 'save_member_meta_to_order' ] );

        // Show member info in order admin.
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ __CLASS__, 'show_member_meta_in_order' ] );
    }

    // -------------------------------------------------------------------------
    // Order completed — create / activate membership
    // -------------------------------------------------------------------------

    public static function handle_order_completed( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Prevent double-processing.
        if ( $order->get_meta( '_cm_membership_created' ) ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $package_id = (int) get_post_meta( $product_id, '_cm_package_id', true );
            if ( ! $package_id ) {
                continue;
            }

            $package = CM_Packages::get( $package_id );
            if ( ! $package ) {
                continue;
            }

            // Pull member info stored on order.
            $name     = $order->get_meta( '_cm_member_name' )     ?: $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $email    = $order->get_meta( '_cm_member_email' )    ?: $order->get_billing_email();
            $phone    = $order->get_meta( '_cm_member_phone' )    ?: $order->get_billing_phone();
            $location = $order->get_meta( '_cm_member_location' ) ?: $order->get_billing_city();

            $member_id = CM_Memberships::create( [
                'name'       => $name,
                'email'      => $email,
                'phone'      => $phone,
                'location'   => $location,
                'package_id' => $package_id,
                'status'     => 'active',
                'order_id'   => $order_id,
            ] );

            if ( ! is_wp_error( $member_id ) ) {
                $order->update_meta_data( '_cm_membership_created', 1 );
                $order->update_meta_data( '_cm_member_id', $member_id );
                $order->save();

                CM_Emails::send_welcome( CM_Memberships::get( $member_id ) );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Checkout field prefill
    // -------------------------------------------------------------------------

    public static function prefill_checkout_fields( $fields ) {
        $data = WC()->session ? WC()->session->get( 'cm_member_data' ) : null;
        if ( ! $data ) {
            return $fields;
        }

        // Force these fields to readonly via custom class so the user sees them
        // but they're already filled from the membership form.
        if ( ! empty( $data['name'] ) ) {
            $parts = explode( ' ', trim( $data['name'] ), 2 );
            if ( isset( $fields['billing']['billing_first_name'] ) ) {
                $fields['billing']['billing_first_name']['default'] = $parts[0];
            }
            if ( isset( $fields['billing']['billing_last_name'] ) ) {
                $fields['billing']['billing_last_name']['default'] = $parts[1] ?? '';
            }
        }
        if ( ! empty( $data['email'] ) && isset( $fields['billing']['billing_email'] ) ) {
            $fields['billing']['billing_email']['default'] = $data['email'];
        }
        if ( ! empty( $data['phone'] ) && isset( $fields['billing']['billing_phone'] ) ) {
            $fields['billing']['billing_phone']['default'] = $data['phone'];
        }

        return $fields;
    }

    public static function get_checkout_field_value( $value, $key ) {
        if ( $value ) {
            return $value;
        }
        $data = WC()->session ? WC()->session->get( 'cm_member_data' ) : null;
        if ( ! $data ) {
            return $value;
        }
        $map = [
            'billing_email' => 'email',
            'billing_phone' => 'phone',
        ];
        if ( isset( $map[ $key ] ) && ! empty( $data[ $map[ $key ] ] ) ) {
            return $data[ $map[ $key ] ];
        }
        if ( $key === 'billing_first_name' && ! empty( $data['name'] ) ) {
            return explode( ' ', trim( $data['name'] ), 2 )[0];
        }
        if ( $key === 'billing_last_name' && ! empty( $data['name'] ) ) {
            $p = explode( ' ', trim( $data['name'] ), 2 );
            return $p[1] ?? '';
        }
        return $value;
    }

    public static function save_member_meta_to_order( $order_id ) {
        $data = WC()->session ? WC()->session->get( 'cm_member_data' ) : null;
        if ( ! $data ) {
            return;
        }
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        foreach ( [ 'name', 'email', 'phone', 'location' ] as $field ) {
            if ( ! empty( $data[ $field ] ) ) {
                $order->update_meta_data( '_cm_member_' . $field, sanitize_text_field( $data[ $field ] ) );
            }
        }
        $order->save();
        WC()->session->__unset( 'cm_member_data' );
    }

    // -------------------------------------------------------------------------
    // Admin order detail
    // -------------------------------------------------------------------------

    public static function show_member_meta_in_order( $order ) {
        $member_id = $order->get_meta( '_cm_member_id' );
        if ( ! $member_id ) {
            return;
        }
        $member = CM_Memberships::get( $member_id );
        if ( ! $member ) {
            return;
        }
        $url = admin_url( 'admin.php?page=cm-members&action=edit&id=' . $member_id );
        printf(
            '<p><strong>%s:</strong> <a href="%s">#%d – %s</a></p>',
            esc_html__( 'Membership', 'custom-memberships' ),
            esc_url( $url ),
            (int) $member_id,
            esc_html( $member->name )
        );
    }

    // -------------------------------------------------------------------------
    // Helper: add package product to cart and redirect to checkout
    // -------------------------------------------------------------------------

    public static function add_to_cart_and_redirect( $package_id, array $member_data ) {
        $package = CM_Packages::get( $package_id );
        if ( ! $package || ! $package->product_id ) {
            return new WP_Error( 'invalid_package', 'Package not found.' );
        }

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart( $package->product_id );

        // Store member info in WC session.
        WC()->session->set( 'cm_member_data', $member_data );

        return wc_get_checkout_url();
    }
}
