<?php defined( 'ABSPATH' ) || exit;

$stats    = CM_Memberships::stats();
$status   = sanitize_text_field( $_GET['status'] ?? '' );
$search   = sanitize_text_field( $_GET['search'] ?? '' );
$per_page = 25;
$page_num = max( 1, intval( $_GET['paged'] ?? 1 ) );
$offset   = ( $page_num - 1 ) * $per_page;

$args = [
    'status'  => $status,
    'search'  => $search,
    'limit'   => $per_page,
    'offset'  => $offset,
    'orderby' => sanitize_sql_orderby( $_GET['orderby'] ?? 'created_at' ) ?: 'created_at',
    'order'   => strtoupper( $_GET['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC',
];

$members    = CM_Memberships::get_all( $args );
$count_args = array_intersect_key( $args, array_flip( [ 'status', 'search' ] ) );
$total      = CM_Memberships::count( $count_args );
$total_pages = ceil( $total / $per_page );

$packages = CM_Packages::get_all();
?>
<div class="wrap cm-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Members', 'custom-memberships' ); ?></h1>
    <button class="page-title-action cm-btn-add-member"><?php esc_html_e( '+ Add Member', 'custom-memberships' ); ?></button>

    <!-- Stats bar -->
    <div class="cm-stats-bar">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=cm-members' ) ); ?>" class="cm-stat <?php echo ! $status ? 'cm-stat--active' : ''; ?>">
            <strong><?php echo (int) $stats['total']; ?></strong> <?php esc_html_e( 'Total', 'custom-memberships' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=cm-members&status=active' ) ); ?>" class="cm-stat <?php echo $status === 'active' ? 'cm-stat--active' : ''; ?>">
            <strong><?php echo (int) $stats['active']; ?></strong> <?php esc_html_e( 'Active', 'custom-memberships' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=cm-members&status=pending' ) ); ?>" class="cm-stat <?php echo $status === 'pending' ? 'cm-stat--active' : ''; ?>">
            <strong><?php echo (int) $stats['pending']; ?></strong> <?php esc_html_e( 'Pending', 'custom-memberships' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=cm-members&status=expired' ) ); ?>" class="cm-stat <?php echo $status === 'expired' ? 'cm-stat--active' : ''; ?>">
            <strong><?php echo (int) $stats['expired']; ?></strong> <?php esc_html_e( 'Expired', 'custom-memberships' ); ?>
        </a>
    </div>

    <!-- Search -->
    <form method="get" class="cm-search-form">
        <input type="hidden" name="page" value="cm-members">
        <?php if ( $status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
        <input type="search" name="search" value="<?php echo esc_attr( $search ); ?>"
               placeholder="<?php esc_attr_e( 'Search by name, email, phone…', 'custom-memberships' ); ?>">
        <button type="submit" class="button"><?php esc_html_e( 'Search', 'custom-memberships' ); ?></button>
        <?php if ( $search ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=cm-members' . ( $status ? '&status=' . $status : '' ) ) ); ?>" class="button">
                <?php esc_html_e( 'Clear', 'custom-memberships' ); ?>
            </a>
        <?php endif; ?>
    </form>

    <!-- Table -->
    <table class="wp-list-table widefat fixed striped cm-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Name', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Email', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Phone', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Location', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Package', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Sessions', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Status', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Joined', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'custom-memberships' ); ?></th>
            </tr>
        </thead>
        <tbody id="cm-members-tbody">
        <?php if ( $members ) : ?>
            <?php foreach ( $members as $m ) : ?>
            <tr id="cm-member-row-<?php echo (int) $m->id; ?>">
                <td><strong><?php echo esc_html( $m->name ); ?></strong></td>
                <td><a href="mailto:<?php echo esc_attr( $m->email ); ?>"><?php echo esc_html( $m->email ); ?></a></td>
                <td><?php echo esc_html( $m->phone ); ?></td>
                <td><?php echo esc_html( $m->location ); ?></td>
                <td><?php echo esc_html( $m->package_name ); ?></td>
                <td class="cm-sessions-cell">
                    <?php if ( (int) $m->sessions_total === 0 ) : ?>
                        <span class="cm-badge cm-badge--unlimited"><?php esc_html_e( 'Unlimited', 'custom-memberships' ); ?></span>
                    <?php else : ?>
                        <span class="cm-sessions-remaining <?php echo (int) $m->sessions_remaining === 0 ? 'cm-sessions-zero' : ''; ?>">
                            <?php echo (int) $m->sessions_remaining; ?> / <?php echo (int) $m->sessions_total; ?>
                        </span>
                        <button class="cm-btn-use-session button button-small"
                                data-id="<?php echo (int) $m->id; ?>"
                                title="<?php esc_attr_e( 'Use 1 session', 'custom-memberships' ); ?>">
                            &minus;1
                        </button>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="cm-status cm-status--<?php echo esc_attr( $m->status ); ?>">
                        <?php echo esc_html( ucfirst( $m->status ) ); ?>
                    </span>
                </td>
                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $m->created_at ) ) ); ?></td>
                <td class="cm-row-actions">
                    <button class="button button-small cm-btn-edit-member"
                            data-id="<?php echo (int) $m->id; ?>"
                            data-member="<?php echo esc_attr( wp_json_encode( $m ) ); ?>">
                        <?php esc_html_e( 'Edit', 'custom-memberships' ); ?>
                    </button>
                    <button class="button button-small cm-btn-delete-member"
                            data-id="<?php echo (int) $m->id; ?>">
                        <?php esc_html_e( 'Delete', 'custom-memberships' ); ?>
                    </button>
                    <?php if ( $m->order_id ) : ?>
                        <a class="button button-small"
                           href="<?php echo esc_url( admin_url( 'post.php?post=' . $m->order_id . '&action=edit' ) ); ?>">
                            <?php esc_html_e( 'Order', 'custom-memberships' ); ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr><td colspan="9" style="text-align:center;padding:24px">
                <?php esc_html_e( 'No members found.', 'custom-memberships' ); ?>
            </td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            echo paginate_links( [
                'base'    => add_query_arg( 'paged', '%#%' ),
                'format'  => '',
                'current' => $page_num,
                'total'   => $total_pages,
            ] );
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Member modal -->
<div id="cm-member-modal" class="cm-modal" style="display:none" aria-modal="true" role="dialog">
    <div class="cm-modal__overlay cm-modal-close"></div>
    <div class="cm-modal__box">
        <button class="cm-modal__close cm-modal-close" aria-label="<?php esc_attr_e( 'Close', 'custom-memberships' ); ?>">&times;</button>
        <h2 id="cm-member-modal-title"><?php esc_html_e( 'Add Member', 'custom-memberships' ); ?></h2>
        <div id="cm-member-modal-notice" class="cm-modal-notice" style="display:none"></div>
        <form id="cm-member-form">
            <input type="hidden" name="id" id="cm-member-id" value="0">

            <div class="cm-form-grid">
                <div class="cm-field">
                    <label for="cm-m-name"><?php esc_html_e( 'Full Name', 'custom-memberships' ); ?> *</label>
                    <input type="text" id="cm-m-name" name="name" required>
                </div>
                <div class="cm-field">
                    <label for="cm-m-email"><?php esc_html_e( 'Email', 'custom-memberships' ); ?> *</label>
                    <input type="email" id="cm-m-email" name="email" required>
                </div>
                <div class="cm-field">
                    <label for="cm-m-phone"><?php esc_html_e( 'Phone (WhatsApp)', 'custom-memberships' ); ?> *</label>
                    <input type="tel" id="cm-m-phone" name="phone" required>
                </div>
                <div class="cm-field">
                    <label for="cm-m-location"><?php esc_html_e( 'Location', 'custom-memberships' ); ?> *</label>
                    <input type="text" id="cm-m-location" name="location" required>
                </div>
                <div class="cm-field cm-field--new-only">
                    <label for="cm-m-package"><?php esc_html_e( 'Package', 'custom-memberships' ); ?> *</label>
                    <select id="cm-m-package" name="package_id">
                        <option value="">— <?php esc_html_e( 'Select package', 'custom-memberships' ); ?> —</option>
                        <?php foreach ( $packages as $pkg ) : ?>
                            <option value="<?php echo (int) $pkg->id; ?>">
                                <?php
                                $price_text = get_woocommerce_currency_symbol() . number_format( (float) $pkg->price, 2 );
                                echo esc_html( $pkg->name . ' (' . CM_Packages::sessions_label( $pkg->sessions ) . ' — ' . $price_text . ')' );
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cm-field cm-field--edit-only" style="display:none">
                    <label for="cm-m-sessions"><?php esc_html_e( 'Sessions Remaining', 'custom-memberships' ); ?></label>
                    <input type="number" id="cm-m-sessions" name="sessions_remaining" min="0">
                    <span class="cm-field__hint"><?php esc_html_e( 'Set to 0 for members who need to renew. Ignored for unlimited plans.', 'custom-memberships' ); ?></span>
                </div>
                <div class="cm-field">
                    <label for="cm-m-status"><?php esc_html_e( 'Status', 'custom-memberships' ); ?></label>
                    <select id="cm-m-status" name="status">
                        <option value="active"><?php esc_html_e( 'Active', 'custom-memberships' ); ?></option>
                        <option value="pending"><?php esc_html_e( 'Pending', 'custom-memberships' ); ?></option>
                        <option value="expired"><?php esc_html_e( 'Expired', 'custom-memberships' ); ?></option>
                    </select>
                </div>
                <div class="cm-field cm-field--full">
                    <label for="cm-m-notes"><?php esc_html_e( 'Notes', 'custom-memberships' ); ?></label>
                    <textarea id="cm-m-notes" name="notes" rows="3"></textarea>
                </div>
            </div>

            <div class="cm-modal__actions">
                <button type="button" class="button cm-modal-close"><?php esc_html_e( 'Cancel', 'custom-memberships' ); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Member', 'custom-memberships' ); ?></button>
            </div>
        </form>
    </div>
</div>
