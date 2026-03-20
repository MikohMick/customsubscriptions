<?php
defined( 'ABSPATH' ) || exit;

class CM_Frontend {

    public static function init() {
        add_shortcode( 'membership_signup', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_cm_submit_step1',        [ __CLASS__, 'ajax_submit_step1' ] );
        add_action( 'wp_ajax_nopriv_cm_submit_step1', [ __CLASS__, 'ajax_submit_step1' ] );
        add_action( 'wp_ajax_cm_submit_step2',        [ __CLASS__, 'ajax_submit_step2' ] );
        add_action( 'wp_ajax_nopriv_cm_submit_step2', [ __CLASS__, 'ajax_submit_step2' ] );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public static function enqueue_assets() {
        if ( ! self::page_has_shortcode() ) {
            return;
        }
        wp_enqueue_style(
            'cm-frontend',
            CM_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            CM_VERSION
        );
        wp_enqueue_script(
            'cm-frontend',
            CM_PLUGIN_URL . 'assets/js/frontend.js',
            [ 'jquery' ],
            CM_VERSION,
            true
        );
        wp_localize_script( 'cm-frontend', 'cmData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'cm_frontend' ),
            'i18n'    => [
                'required'      => __( 'This field is required.', 'custom-memberships' ),
                'invalid_email' => __( 'Please enter a valid email address.', 'custom-memberships' ),
                'select_plan'   => __( 'Please select a membership plan.', 'custom-memberships' ),
                'processing'    => __( 'Processing…', 'custom-memberships' ),
                'error'         => __( 'Something went wrong. Please try again.', 'custom-memberships' ),
            ],
        ] );
    }

    private static function page_has_shortcode() {
        global $post;
        return is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'membership_signup' );
    }

    // -------------------------------------------------------------------------
    // Shortcode
    // -------------------------------------------------------------------------

    public static function render_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'title'    => '',
            'subtitle' => '',
        ], $atts, 'membership_signup' );

        $packages = CM_Packages::get_all( true );

        ob_start();
        ?>
        <div class="cm-form-wrap" id="cm-membership-form">

            <?php if ( $atts['title'] ) : ?>
                <h2 class="cm-form-title"><?php echo esc_html( $atts['title'] ); ?></h2>
            <?php endif; ?>
            <?php if ( $atts['subtitle'] ) : ?>
                <p class="cm-form-subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
            <?php endif; ?>

            <!-- Step indicator -->
            <div class="cm-steps" aria-label="<?php esc_attr_e( 'Form steps', 'custom-memberships' ); ?>">
                <div class="cm-step cm-step--active" data-step="1">
                    <span class="cm-step__number">1</span>
                    <span class="cm-step__label"><?php esc_html_e( 'Your Details', 'custom-memberships' ); ?></span>
                </div>
                <div class="cm-step-connector"></div>
                <div class="cm-step" data-step="2">
                    <span class="cm-step__number">2</span>
                    <span class="cm-step__label"><?php esc_html_e( 'Choose Plan', 'custom-memberships' ); ?></span>
                </div>
            </div>

            <div class="cm-notices" role="alert" aria-live="polite"></div>

            <!-- Step 1: Personal details -->
            <div class="cm-panel cm-panel--active" id="cm-panel-1">
                <form id="cm-form-step1" novalidate>
                    <?php wp_nonce_field( 'cm_frontend', 'cm_nonce' ); ?>

                    <div class="cm-field">
                        <label for="cm_name"><?php esc_html_e( 'Full Name', 'custom-memberships' ); ?> <span class="cm-required">*</span></label>
                        <input type="text" id="cm_name" name="cm_name" autocomplete="name" required
                               placeholder="<?php esc_attr_e( 'e.g. Jane Doe', 'custom-memberships' ); ?>">
                    </div>

                    <div class="cm-field">
                        <label for="cm_email"><?php esc_html_e( 'Email Address', 'custom-memberships' ); ?> <span class="cm-required">*</span></label>
                        <input type="email" id="cm_email" name="cm_email" autocomplete="email" required
                               placeholder="<?php esc_attr_e( 'you@example.com', 'custom-memberships' ); ?>">
                    </div>

                    <div class="cm-field">
                        <label for="cm_phone">
                            <?php esc_html_e( 'WhatsApp Number', 'custom-memberships' ); ?> <span class="cm-required">*</span>
                        </label>
                        <input type="tel" id="cm_phone" name="cm_phone" autocomplete="tel" required
                               placeholder="<?php esc_attr_e( 'e.g. 0712 345 678', 'custom-memberships' ); ?>">
                        <span class="cm-field__hint"><?php esc_html_e( 'Please enter a valid WhatsApp number.', 'custom-memberships' ); ?></span>
                    </div>

                    <div class="cm-field">
                        <label for="cm_location"><?php esc_html_e( 'Location', 'custom-memberships' ); ?> <span class="cm-required">*</span></label>
                        <input type="text" id="cm_location" name="cm_location" autocomplete="address-level2" required
                               placeholder="<?php esc_attr_e( 'e.g. Nairobi, Westlands', 'custom-memberships' ); ?>">
                    </div>

                    <div class="cm-actions">
                        <button type="submit" class="cm-btn cm-btn--primary">
                            <?php esc_html_e( 'Next: Choose Your Plan', 'custom-memberships' ); ?> &rarr;
                        </button>
                    </div>
                </form>
            </div>

            <!-- Step 2: Package selection -->
            <div class="cm-panel" id="cm-panel-2">
                <form id="cm-form-step2" novalidate>
                    <?php wp_nonce_field( 'cm_frontend', 'cm_nonce_step2' ); ?>
                    <input type="hidden" id="cm_step1_data" name="cm_step1_data" value="">

                    <?php if ( empty( $packages ) ) : ?>
                        <p class="cm-notice cm-notice--info">
                            <?php esc_html_e( 'No membership plans are available at this time. Please check back later.', 'custom-memberships' ); ?>
                        </p>
                    <?php else : ?>
                        <p class="cm-plans-intro"><?php esc_html_e( 'Select the plan that works best for you:', 'custom-memberships' ); ?></p>
                        <div class="cm-plans" role="radiogroup" aria-label="<?php esc_attr_e( 'Membership plans', 'custom-memberships' ); ?>">
                            <?php foreach ( $packages as $pkg ) : ?>
                                <label class="cm-plan" for="cm_package_<?php echo esc_attr( $pkg->id ); ?>">
                                    <input type="radio" name="cm_package_id"
                                           id="cm_package_<?php echo esc_attr( $pkg->id ); ?>"
                                           value="<?php echo esc_attr( $pkg->id ); ?>">
                                    <span class="cm-plan__text">
                                        <span class="cm-plan__name"><?php echo esc_html( $pkg->name ); ?></span>
                                        <span class="cm-plan__meta">
                                            <?php echo esc_html( CM_Packages::sessions_label( $pkg->sessions ) ); ?>
                                            &mdash;
                                            <?php echo wp_kses_post( wc_price( $pkg->price ) ); ?>
                                        </span>
                                        <?php if ( $pkg->description ) : ?>
                                            <span class="cm-plan__desc"><?php echo esc_html( $pkg->description ); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="cm-actions cm-actions--split">
                        <button type="button" class="cm-btn cm-btn--ghost" id="cm-back-btn">
                            &larr; <?php esc_html_e( 'Back', 'custom-memberships' ); ?>
                        </button>
                        <button type="submit" class="cm-btn cm-btn--primary" <?php echo empty( $packages ) ? 'disabled' : ''; ?>>
                            <?php esc_html_e( 'Proceed to Checkout', 'custom-memberships' ); ?> &rarr;
                        </button>
                    </div>
                </form>
            </div>

        </div><!-- .cm-form-wrap -->
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // AJAX: Step 1 validation & store
    // -------------------------------------------------------------------------

    public static function ajax_submit_step1() {
        check_ajax_referer( 'cm_frontend', 'cm_nonce' );

        $name     = sanitize_text_field( $_POST['cm_name'] ?? '' );
        $email    = sanitize_email( $_POST['cm_email'] ?? '' );
        $phone    = sanitize_text_field( $_POST['cm_phone'] ?? '' );
        $location = sanitize_text_field( $_POST['cm_location'] ?? '' );

        $errors = [];
        if ( ! $name )              $errors['cm_name']     = __( 'Name is required.', 'custom-memberships' );
        if ( ! is_email( $email ) ) $errors['cm_email']    = __( 'A valid email is required.', 'custom-memberships' );
        if ( ! $phone )             $errors['cm_phone']    = __( 'WhatsApp number is required.', 'custom-memberships' );
        if ( ! $location )          $errors['cm_location'] = __( 'Location is required.', 'custom-memberships' );

        if ( $errors ) {
            wp_send_json_error( [ 'errors' => $errors ] );
        }

        wp_send_json_success( [
            'data' => [
                'name'     => $name,
                'email'    => $email,
                'phone'    => $phone,
                'location' => $location,
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: Step 2 — add to cart and get checkout URL
    // -------------------------------------------------------------------------

    public static function ajax_submit_step2() {
        check_ajax_referer( 'cm_frontend', 'cm_nonce_step2' );

        $package_id  = intval( $_POST['cm_package_id'] ?? 0 );
        $step1_raw   = sanitize_text_field( $_POST['cm_step1_data'] ?? '' );
        $member_data = json_decode( stripslashes( $step1_raw ), true );

        if ( ! $package_id ) {
            wp_send_json_error( [ 'message' => __( 'Please select a membership plan.', 'custom-memberships' ) ] );
        }

        if ( ! is_array( $member_data ) || empty( $member_data['email'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Your details are missing. Please go back and re-enter them.', 'custom-memberships' ) ] );
        }

        if ( ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }

        $checkout_url = CM_WooCommerce::add_to_cart_and_redirect( $package_id, $member_data );

        if ( is_wp_error( $checkout_url ) ) {
            wp_send_json_error( [ 'message' => $checkout_url->get_error_message() ] );
        }

        wp_send_json_success( [ 'redirect' => $checkout_url ] );
    }
}
