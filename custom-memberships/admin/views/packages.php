<?php defined( 'ABSPATH' ) || exit;
$categories = CM_Categories::get_all();
$groups     = CM_Categories::get_with_packages( false, false );
?>
<div class="wrap cm-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Membership Packages', 'custom-memberships' ); ?></h1>
    <button class="page-title-action cm-btn-add-package"><?php esc_html_e( '+ New Package', 'custom-memberships' ); ?></button>

    <p class="description">
        <?php esc_html_e( 'Each package automatically creates a hidden WooCommerce product used at checkout. Set sessions to 0 for unlimited access. Packages are grouped by category on the signup form.', 'custom-memberships' ); ?>
        <?php if ( empty( $categories ) ) : ?>
            <br><strong>
                <?php
                printf(
                    /* translators: %s: link to the categories screen */
                    esc_html__( 'You have no categories yet — %s so packages can be grouped on the signup form.', 'custom-memberships' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=cm-categories' ) ) . '">' . esc_html__( 'create one first', 'custom-memberships' ) . '</a>'
                );
                ?>
            </strong>
        <?php endif; ?>
    </p>

    <?php if ( empty( $groups ) ) : ?>
        <table class="wp-list-table widefat fixed striped cm-table">
            <tbody>
                <tr><td style="text-align:center;padding:24px">
                    <?php esc_html_e( 'No packages yet. Add your first one!', 'custom-memberships' ); ?>
                </td></tr>
            </tbody>
        </table>
    <?php endif; ?>

    <?php foreach ( $groups as $group ) : ?>
        <h2 class="cm-group-heading">
            <?php echo esc_html( $group->name ); ?>
            <?php if ( ! empty( $group->subtitle ) ) : ?>
                <span class="cm-group-heading__sub"><?php echo esc_html( $group->subtitle ); ?></span>
            <?php endif; ?>
        </h2>

        <table class="wp-list-table widefat fixed striped cm-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'custom-memberships' ); ?></th>
                    <th><?php esc_html_e( 'Sessions', 'custom-memberships' ); ?></th>
                    <th><?php esc_html_e( 'Price', 'custom-memberships' ); ?></th>
                    <th><?php esc_html_e( 'Perks', 'custom-memberships' ); ?></th>
                    <th><?php esc_html_e( 'Order', 'custom-memberships' ); ?></th>
                    <th><?php esc_html_e( 'Active', 'custom-memberships' ); ?></th>
                    <th><?php esc_html_e( 'Product', 'custom-memberships' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'custom-memberships' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $group->packages ) ) : ?>
                <tr><td colspan="8" style="padding:16px;color:#666">
                    <?php esc_html_e( 'No packages in this category yet.', 'custom-memberships' ); ?>
                </td></tr>
            <?php else : ?>
                <?php foreach ( $group->packages as $pkg ) : ?>
                    <?php $perks = CM_Packages::perks_list( $pkg->perks ?? '' ); ?>
                    <tr id="cm-package-row-<?php echo (int) $pkg->id; ?>">
                        <td>
                            <strong><?php echo esc_html( $pkg->name ); ?></strong>
                            <?php if ( ! empty( $pkg->highlight ) ) : ?>
                                <span class="cm-badge cm-badge--popular"><?php esc_html_e( 'Popular', 'custom-memberships' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( (int) $pkg->sessions === 0 ) : ?>
                                <span class="cm-badge cm-badge--unlimited"><?php esc_html_e( 'Unlimited', 'custom-memberships' ); ?></span>
                            <?php else : ?>
                                <?php echo esc_html( CM_Packages::sessions_label( $pkg->sessions, $pkg->session_unit ?? 'Session' ) ); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo wp_kses_post( wc_price( $pkg->price ) ); ?></td>
                        <td><?php echo $perks ? esc_html( count( $perks ) . ' listed' ) : '—'; ?></td>
                        <td><?php echo (int) $pkg->sort_order; ?></td>
                        <td>
                            <span class="cm-badge cm-badge--<?php echo $pkg->active ? 'active' : 'inactive'; ?>">
                                <?php echo $pkg->active ? esc_html__( 'Yes', 'custom-memberships' ) : esc_html__( 'No', 'custom-memberships' ); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ( $pkg->product_id ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $pkg->product_id . '&action=edit' ) ); ?>" target="_blank">
                                    #<?php echo (int) $pkg->product_id; ?>
                                </a>
                            <?php else : ?>
                                <em><?php esc_html_e( 'Not created', 'custom-memberships' ); ?></em>
                            <?php endif; ?>
                        </td>
                        <td class="cm-row-actions">
                            <button class="button button-small cm-btn-edit-package"
                                    data-id="<?php echo (int) $pkg->id; ?>"
                                    data-package="<?php echo esc_attr( wp_json_encode( $pkg ) ); ?>">
                                <?php esc_html_e( 'Edit', 'custom-memberships' ); ?>
                            </button>
                            <button class="button button-small cm-btn-delete-package"
                                    data-id="<?php echo (int) $pkg->id; ?>">
                                <?php esc_html_e( 'Delete', 'custom-memberships' ); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
</div>

<!-- Package modal -->
<div id="cm-package-modal" class="cm-modal" style="display:none" aria-modal="true" role="dialog">
    <div class="cm-modal__overlay cm-modal-close"></div>
    <div class="cm-modal__box">
        <button class="cm-modal__close cm-modal-close" aria-label="<?php esc_attr_e( 'Close', 'custom-memberships' ); ?>">&times;</button>
        <h2 id="cm-package-modal-title"><?php esc_html_e( 'Add Package', 'custom-memberships' ); ?></h2>
        <div id="cm-package-modal-notice" class="cm-modal-notice" style="display:none"></div>

        <form id="cm-package-form">
            <input type="hidden" name="id" id="cm-pkg-id" value="0">

            <div class="cm-form-grid">
                <div class="cm-field">
                    <label for="cm-pkg-name"><?php esc_html_e( 'Package Name', 'custom-memberships' ); ?> *</label>
                    <input type="text" id="cm-pkg-name" name="name" required placeholder="e.g. Signature Plus">
                </div>
                <div class="cm-field">
                    <label for="cm-pkg-category"><?php esc_html_e( 'Category', 'custom-memberships' ); ?></label>
                    <select id="cm-pkg-category" name="category_id">
                        <option value="0">— <?php esc_html_e( 'Uncategorized', 'custom-memberships' ); ?> —</option>
                        <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo (int) $cat->id; ?>"><?php echo esc_html( $cat->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cm-field">
                    <label for="cm-pkg-price"><?php esc_html_e( 'Price', 'custom-memberships' ); ?> *</label>
                    <input type="number" id="cm-pkg-price" name="price" min="0" step="0.01" required placeholder="20000">
                </div>
                <div class="cm-field">
                    <label for="cm-pkg-sessions">
                        <?php esc_html_e( 'Number of Sessions', 'custom-memberships' ); ?>
                        <span class="cm-field__hint"><?php esc_html_e( '(0 = unlimited)', 'custom-memberships' ); ?></span>
                    </label>
                    <input type="number" id="cm-pkg-sessions" name="sessions" min="0" value="0">
                </div>
                <div class="cm-field">
                    <label for="cm-pkg-unit"><?php esc_html_e( 'Session Unit', 'custom-memberships' ); ?></label>
                    <input type="text" id="cm-pkg-unit" name="session_unit" value="Session"
                           placeholder="e.g. Class, Reformer Class">
                    <span class="cm-field__hint"><?php esc_html_e( 'Shown as "8 Reformer Classes". Pluralised automatically.', 'custom-memberships' ); ?></span>
                </div>
                <div class="cm-field">
                    <label for="cm-pkg-order"><?php esc_html_e( 'Sort Order', 'custom-memberships' ); ?></label>
                    <input type="number" id="cm-pkg-order" name="sort_order" value="0">
                </div>
                <div class="cm-field cm-field--full">
                    <label for="cm-pkg-perks"><?php esc_html_e( 'Perks', 'custom-memberships' ); ?></label>
                    <textarea id="cm-pkg-perks" name="perks" rows="4"
                              placeholder="8 Reformer Classes&#10;Unlimited Yoga &amp; Mat Pilates"></textarea>
                    <span class="cm-field__hint"><?php esc_html_e( 'One perk per line. Shown as a ticked list under the plan name.', 'custom-memberships' ); ?></span>
                </div>
                <div class="cm-field cm-field--full">
                    <label for="cm-pkg-desc"><?php esc_html_e( 'Description', 'custom-memberships' ); ?></label>
                    <textarea id="cm-pkg-desc" name="description" rows="2"
                              placeholder="<?php esc_attr_e( 'Optional short line shown under the plan', 'custom-memberships' ); ?>"></textarea>
                </div>
                <div class="cm-field">
                    <label>
                        <input type="checkbox" id="cm-pkg-active" name="active" value="1" checked>
                        <?php esc_html_e( 'Active (visible on signup form)', 'custom-memberships' ); ?>
                    </label>
                </div>
                <div class="cm-field">
                    <label>
                        <input type="checkbox" id="cm-pkg-highlight" name="highlight" value="1">
                        <?php esc_html_e( 'Show "Most Popular" badge', 'custom-memberships' ); ?>
                    </label>
                </div>
            </div>

            <div class="cm-modal__actions">
                <button type="button" class="button cm-modal-close"><?php esc_html_e( 'Cancel', 'custom-memberships' ); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Package', 'custom-memberships' ); ?></button>
            </div>
        </form>
    </div>
</div>
