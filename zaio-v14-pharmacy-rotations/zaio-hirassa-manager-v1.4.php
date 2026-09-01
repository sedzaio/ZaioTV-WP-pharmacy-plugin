<?php
/**
 * Plugin Name: صيدليات الحراسة
 * Description: نظام متقدم لإدارة صيدليات الحراسة الدوري مع دعم التوقيت المحلي لمدينة زايو والمغرب
 * Version: 1.5.0
 * Author: Seddik Belbikkey
 * Author URI: https://seddik.be
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * 1. Database Schema Setup
 */
register_activation_hook( __FILE__, 'zaio_v14_db_init' );
function zaio_v14_db_init() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_pharmacies = $wpdb->prefix . 'zaio_v14_pharmacies';
    $table_rotations = $wpdb->prefix . 'zaio_v14_rotations';

    $wpdb->query("CREATE TABLE IF NOT EXISTS `$table_pharmacies` (
        `ph_id` INT NOT NULL AUTO_INCREMENT,
        `ph_name` VARCHAR(255) NOT NULL,
        `ph_phone` VARCHAR(100) NOT NULL DEFAULT '',
        `ph_address` TEXT NOT NULL,
        `ph_embed_code` TEXT NOT NULL,         
        `ph_latitude` VARCHAR(50) NOT NULL DEFAULT '',
        `ph_longitude` VARCHAR(50) NOT NULL DEFAULT '',
        `ph_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`ph_id`)
    ) $charset_collate;");

    $wpdb->query("CREATE TABLE IF NOT EXISTS `$table_rotations` (
        `rot_id` INT NOT NULL AUTO_INCREMENT,
        `rot_week` VARCHAR(10) NOT NULL, 
        `rot_ph_id` INT NOT NULL,
        PRIMARY KEY (`rot_id`),
        UNIQUE KEY `unique_week_rotation` (`rot_week`, `rot_ph_id`)
    ) $charset_collate;");
}

// Self-healing database mechanism
add_action( 'admin_init', 'zaio_v14_auto_heal_db' );
function zaio_v14_auto_heal_db() {
    global $wpdb;
    $table_pharmacies = $wpdb->prefix . 'zaio_v14_pharmacies';
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_pharmacies ) ) !== $table_pharmacies ) {
        zaio_v14_db_init();
    }
}

/**
 * Enqueue Admin Scripts for Drag & Drop Reordering
 */
add_action( 'admin_enqueue_scripts', 'zaio_v14_enqueue_admin_scripts' );
function zaio_v14_enqueue_admin_scripts( $hook ) {
    if ( false === strpos( $hook, 'zaio-v14-manager' ) ) {
        return;
    }
    wp_enqueue_script( 'jquery-ui-sortable' );
}

/**
 * 2. Helper: Simple Text Field Coordinate Parser
 */
function zaio_v14_process_coordinates_input( $input_string ) {
    $data = array(
        'lat'        => '',
        'lng'        => '',
        'saved_link' => sanitize_text_field( trim( wp_unslash( $input_string ) ) )
    );

    $clean_input = trim( wp_unslash( $input_string ) );

    if ( preg_match( '/(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/', $clean_input, $matches ) ) {
        $data['lat'] = sanitize_text_field( $matches[1] );
        $data['lng'] = sanitize_text_field( $matches[2] );
    }

    return $data;
}

/**
 * 3. Menu Registration
 */
add_action( 'admin_menu', 'zaio_v14_add_menu' );
function zaio_v14_add_menu() {
    add_menu_page(
        'صيدليات الحراسة',
        'صيدليات الحراسة',
        'manage_options',
        'zaio-v14-manager',
        'zaio_v14_render_dashboard',
        'dashicons-plus-alt',
        30
    );
}

/**
 * 4. Settings Config Registrations
 */
add_action( 'admin_init', 'zaio_v14_register_settings' );
function zaio_v14_register_settings() {
    register_setting( 'zaio_v14_settings_group', 'zaio_v14_opt_day' );
    register_setting( 'zaio_v14_settings_group', 'zaio_v14_opt_time' );
}

/**
 * 5. Date Range and Active Week Calculation Logic
 */

function zaio_v14_get_rotation_start_obj( $reference_datetime = null ) {
    $tz = wp_timezone(); 
    
    if ( ! $reference_datetime ) {
        $reference_datetime = new DateTime( 'now', $tz );
    }

    $target_day = get_option( 'zaio_v14_opt_day', 'Monday' );
    $target_time = get_option( 'zaio_v14_opt_time', '00:00' );

    $start = clone $reference_datetime;
    $current_dow = $start->format( 'l' );

    if ( $current_dow === $target_day ) {
        $today_target = clone $start;
        $today_target->modify( $target_time );
        
        if ( $start >= $today_target ) {
            $start = $today_target;
        } else {
            $start->modify( "last $target_day" );
            $start->modify( $target_time );
        }
    } else {
        $start->modify( "last $target_day" );
        $start->modify( $target_time );
    }

    return $start;
}

function zaio_v14_get_week_bounds( $start_datetime ) {
    $start = clone $start_datetime;
    $end = clone $start;
    $end->modify( '+6 days 23 hours 59 minutes 59 seconds' );

    return array(
        'start' => $start->format( 'd/m/Y' ),
        'end'   => $end->format( 'd/m/Y' )
    );
}

function zaio_v14_get_active_week() {
    $start_obj = zaio_v14_get_rotation_start_obj();
    return $start_obj->format( 'o-\WW' ); 
}

function zaio_v14_weeks_between( $week1, $week2 ) {
    if ( $week1 === $week2 ) return 0;
    
    $parts1 = explode( '-W', $week1 );
    $parts2 = explode( '-W', $week2 );

    if ( count( $parts1 ) !== 2 || count( $parts2 ) !== 2 ) return 0;

    $tz = wp_timezone();
    $d1 = new DateTime( 'now', $tz );
    $d1->setISODate( intval( $parts1[0] ), intval( $parts1[1] ) );

    $d2 = new DateTime( 'now', $tz );
    $d2->setISODate( intval( $parts2[0] ), intval( $parts2[1] ) );

    $diff_days = round( ( $d2->getTimestamp() - $d1->getTimestamp() ) / ( 7 * DAY_IN_SECONDS ) );
    return intval( $diff_days );
}

function zaio_v14_format_week_key_to_dates( $week_key ) {
    $parts = explode( '-W', $week_key );
    if ( count( $parts ) !== 2 ) return $week_key;

    $year = intval( $parts[0] );
    $week = intval( $parts[1] );

    $tz = wp_timezone();
    $start = new DateTime( 'now', $tz );
    $start->setISODate( $year, $week ); 

    $target_day = get_option( 'zaio_v14_opt_day', 'Monday' );
    $target_time = get_option( 'zaio_v14_opt_time', '00:00' );

    if ( $target_day !== 'Monday' ) {
        $start->modify( $target_day );
    }
    $start->modify( $target_time );

    $end = clone $start;
    $end->modify( '+6 days' );

    return $start->format( 'd/m/Y' ) . ' إلى ' . $end->format( 'd/m/Y' );
}

/**
 * Get Master Periodic Rotation Sequence Array
 */
function zaio_v14_get_rotation_sequence() {
    $seq = get_option( 'zaio_v14_rotation_sequence', false );
    if ( false === $seq ) {
        global $wpdb;
        $table_pharmacies = $wpdb->prefix . 'zaio_v14_pharmacies';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_pharmacies'" ) === $table_pharmacies ) {
            $ph_ids = $wpdb->get_col( "SELECT ph_id FROM $table_pharmacies ORDER BY ph_id ASC" );
            $seq = array_map( 'intval', $ph_ids );
            update_option( 'zaio_v14_rotation_sequence', $seq );
        } else {
            $seq = array();
        }
    }
    return is_array( $seq ) ? array_map( 'intval', array_values( $seq ) ) : array();
}

/**
 * Periodic Rotation Solver: Resolves Active On-Duty Pharmacy for Any Week
 */
function zaio_v14_get_active_duty_pharmacy( $target_week = null ) {
    global $wpdb;
    $table_pharmacies = $wpdb->prefix . 'zaio_v14_pharmacies';
    $table_rotations = $wpdb->prefix . 'zaio_v14_rotations';

    if ( ! $target_week ) {
        $target_week = zaio_v14_get_active_week();
    }

    // 1. Cyclic periodic calculation (Primary Source of Truth)
    $seq = zaio_v14_get_rotation_sequence();
    if ( ! empty( $seq ) ) {
        $anchor_week = get_option( 'zaio_v14_rotation_anchor_week', '' );
        $anchor_idx  = intval( get_option( 'zaio_v14_active_rotation_index', 0 ) );

        if ( empty( $anchor_week ) ) {
            $anchor_week = zaio_v14_get_active_week();
            update_option( 'zaio_v14_rotation_anchor_week', $anchor_week );
            update_option( 'zaio_v14_active_rotation_index', 0 );
            $anchor_idx = 0;
        }

        $elapsed_weeks = zaio_v14_weeks_between( $anchor_week, $target_week );
        $count = count( $seq );

        $calculated_idx = ( $anchor_idx + ( $elapsed_weeks % $count ) + $count ) % $count;
        $target_ph_id = $seq[$calculated_idx];

        $pharmacy = $wpdb->get_row( $wpdb->prepare( "
            SELECT * FROM $table_pharmacies WHERE ph_id = %d
        ", $target_ph_id ) );

        if ( $pharmacy ) {
            return $pharmacy;
        }
    }

    // 2. Legacy fallback check
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_rotations'" ) === $table_rotations ) {
        $override = $wpdb->get_row( $wpdb->prepare( "
            SELECT p.* FROM $table_rotations r 
            JOIN $table_pharmacies p ON r.rot_ph_id = p.ph_id 
            WHERE r.rot_week = %s
        ", $target_week ) );
        if ( $override ) {
            return $override;
        }
    }

    return null;
}

/**
 * 6. Main Dashboard Interface Render
 */
function zaio_v14_render_dashboard() {
    global $wpdb;
    $table_pharmacies = $wpdb->prefix . 'zaio_v14_pharmacies';
    $table_rotations = $wpdb->prefix . 'zaio_v14_rotations';

    zaio_v14_handle_form_posts();

    $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'duty';
    ?>
    <style>
    @keyframes zaioPulseGreen {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.4); opacity: 0.4; }
        100% { transform: scale(1); opacity: 1; }
    }
    .zaio-seq-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 10px;
        display: flex;
        flex-direction: column;
        gap: 0;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .zaio-seq-card.active-now {
        background: #f0fdf4 !important;
        border: 1.5px solid #46b450 !important;
    }
    .zaio-seq-card:hover {
        border-color: #cbd5e1;
    }
    .zaio-card-main-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        width: 100%;
    }
    .zaio-card-right {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
        min-width: 0;
    }
    .zaio-num-badge {
        background: #3b82f6;
        color: #fff;
        font-weight: 800;
        font-size: 14px;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .zaio-seq-card.active-now .zaio-num-badge {
        background: #16a34a;
    }
    .zaio-ph-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
        flex: 1;
    }
    .zaio-ph-title-row {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .zaio-ph-info h4 {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        color: #0f172a;
    }
    .zaio-active-now-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(34, 197, 94, 0.12);
        color: #15803d;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }
    .zaio-ph-meta-line {
        font-size: 13px;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 15px;
        white-space: nowrap;
        overflow: hidden;
        max-width: 100%;
    }
    .zaio-ph-address-text {
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }
    .zaio-duty-dates {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 700;
        color: #334155;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }
    .zaio-seq-card.active-now .zaio-duty-dates {
        background: #065f46 !important;
        border: 1px solid #047857 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(6, 95, 70, 0.2);
    }
    .zaio-seq-card.active-now .zaio-duty-dates i {
        color: #a7f3d0 !important;
    }
    .zaio-actions-group {
        display: none;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px dashed #e2e8f0;
    }
    .zaio-seq-card.show-actions .zaio-actions-group {
        display: flex;
    }
    .zaio-drag-handle {
        cursor: grab;
        color: #94a3b8;
        font-size: 18px;
        padding: 0 6px;
        flex-shrink: 0;
    }
    .zaio-drag-handle:active {
        cursor: grabbing;
    }
    </style>

    <div class="wrap" style="direction: rtl; font-family: tahoma, Arial, sans-serif; position: relative; min-height: 500px;">
        <h1 style="margin-bottom: 20px; font-weight: bold;">إدارة صيدليات الحراسة</h1>
        
        <h2 class="nav-tab-wrapper" style="text-align: right; margin-bottom: 25px;">
            <a href="?page=zaio-v14-manager&tab=duty" class="nav-tab <?php echo $tab === 'duty' ? 'nav-tab-active' : ''; ?>">قائمة التدوير الدوري والتسلسل</a>
            <a href="?page=zaio-v14-manager&tab=manager" class="nav-tab <?php echo $tab === 'manager' ? 'nav-tab-active' : ''; ?>">دليل الصيدليات</a>
            <a href="?page=zaio-v14-manager&tab=settings" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">إعدادات التبديل والجدولة</a>
        </h2>

        <?php if ( $tab === 'duty' ) : 
            $all_pharmacies = $wpdb->get_results( "SELECT * FROM $table_pharmacies ORDER BY ph_name ASC" );
            $sequence = zaio_v14_get_rotation_sequence();

            $not_in_seq = array();
            $in_seq     = array();

            foreach ( $all_pharmacies as $ph ) {
                if ( in_array( intval( $ph->ph_id ), $sequence, true ) ) {
                    $in_seq[] = $ph;
                } else {
                    $not_in_seq[] = $ph;
                }
            }

            $active_week = zaio_v14_get_active_week();
            $anchor_week = get_option( 'zaio_v14_rotation_anchor_week', $active_week );
            $anchor_idx  = intval( get_option( 'zaio_v14_active_rotation_index', 0 ) );

            $count = count( $sequence );
            $elapsed = zaio_v14_weeks_between( $anchor_week, $active_week );
            $current_active_idx = ( $count > 0 ) ? ( ( $anchor_idx + ( $elapsed % $count ) + $count ) % $count ) : 0;
            ?>
            <div style="background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                
                <!-- Add Pharmacy to Rotation Sequence -->
                <form method="post" style="margin-bottom: 25px; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <?php wp_nonce_field( 'zaio_v14_duty_nonce_action', 'zaio_v14_duty_nonce' ); ?>
                    <input type="hidden" name="zaio_v14_action" value="add_to_rotation">
                    
                    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <div>
                            <label style="font-weight: bold; display: block; margin-bottom: 5px;">إضافة صيدلية جديدة إلى أسفل قائمة التدوير الدوري:</label>
                            <select name="form_pharmacy_id" required style="min-width: 320px; padding: 6px;">
                                <option value="">-- اختر الصيدلية من الدليل --</option>
                                <?php if ( ! empty( $not_in_seq ) ) : ?>
                                    <optgroup label="صيدليات غير مضافة للقائمة">
                                        <?php foreach ( $not_in_seq as $ph ) : ?>
                                            <option value="<?php echo intval( $ph->ph_id ); ?>"><?php echo esc_html( $ph->ph_name ); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if ( ! empty( $in_seq ) ) : ?>
                                    <optgroup label="صيدليات مضافة للقائمة">
                                        <?php foreach ( $in_seq as $ph ) : ?>
                                            <option value="<?php echo intval( $ph->ph_id ); ?>"><?php echo esc_html( $ph->ph_name ); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div style="padding-top: 20px;">
                            <input type="submit" class="button button-primary" value="إضافة إلى أسفل القائمة">
                        </div>
                    </div>
                </form>

                <div style="margin-bottom: 15px;">
                    <h3 style="margin: 0; font-size: 18px; font-weight: 800;">تسلسل تدوير صيدليات الحراسة الأسبوعية</h3>
                </div>

                <form method="post" id="zaio-reorder-form">
                    <?php wp_nonce_field( 'zaio_v14_duty_nonce_action', 'zaio_v14_duty_nonce' ); ?>
                    <input type="hidden" name="zaio_v14_action" value="save_reorder">
                    <input type="hidden" name="ordered_sequence_str" id="ordered_sequence_str" value="">

                    <div id="zaio-sortable-sequence-list">
                        <?php
                        if ( empty( $sequence ) ) {
                            echo '<div style="padding: 20px; text-align: center; background: #f8fafc; border-radius: 6px; color: #64748b;">لا توجد صيدليات مضافة بقائمة التدوير بعد. استخدم النموذج أعلاه للإضافة.</div>';
                        } else {
                            $tz = wp_timezone();
                            $base_start_obj = zaio_v14_get_rotation_start_obj();

                            foreach ( $sequence as $idx => $ph_id ) {
                                $ph = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_pharmacies WHERE ph_id = %d", $ph_id ) );
                                if ( ! $ph ) continue;

                                $is_active = ( $idx === $current_active_idx );

                                // Calculate relative week bounds for item $idx
                                $offset_weeks = ( $idx - $current_active_idx + $count ) % $count;
                                $item_start_obj = clone $base_start_obj;
                                if ( $offset_weeks > 0 ) {
                                    $item_start_obj->modify( "+{$offset_weeks} weeks" );
                                }
                                $bounds = zaio_v14_get_week_bounds( $item_start_obj );
                                ?>
                                <div class="zaio-seq-card <?php echo $is_active ? 'active-now' : ''; ?>" data-ph-id="<?php echo intval( $ph->ph_id ); ?>">
                                    <div class="zaio-card-main-row">
                                        <div class="zaio-card-right">
                                            <span class="zaio-drag-handle" title="اسحب لإعادة الترتيب">☰</span>
                                            <span class="zaio-num-badge"><?php echo ( $idx + 1 ); ?></span>
                                            <div class="zaio-ph-info">
                                                <div class="zaio-ph-title-row">
                                                    <h4><?php echo esc_html( $ph->ph_name ); ?></h4>
                                                    <?php if ( $is_active ) : ?>
                                                        <span class="zaio-active-now-badge">
                                                            <span style="width: 7px; height: 7px; background: #22c55e; border-radius: 50%; display: inline-block; animation: zaioPulseGreen 1.5s infinite ease-in-out;"></span>
                                                            مناوبة نشطة الآن
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="zaio-ph-meta-line">
                                                    <span style="flex-shrink: 0;"><i class="fa-solid fa-phone" style="margin-left: 4px; color: #3b82f6;"></i> <?php echo esc_html( $ph->ph_phone ); ?></span>
                                                    <span class="zaio-ph-address-text"><i class="fa-solid fa-location-dot" style="margin-left: 4px; color: #ef4444;"></i> <?php echo esc_html( $ph->ph_address ); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="zaio-duty-dates">
                                            <i class="fa-regular fa-calendar-days" style="margin-left: 4px; color: #64748b;"></i>
                                            <span><?php echo esc_html( $bounds['start'] ); ?> - <?php echo esc_html( $bounds['end'] ); ?></span>
                                        </div>
                                    </div>

                                    <div class="zaio-actions-group">
                                        <?php if ( ! $is_active ) : ?>
                                            <a href="<?php echo wp_nonce_url( '?page=zaio-v14-manager&tab=duty&zaio_v14_action=set_current_duty&idx=' . $idx, 'zaio_duty_action_nonce' ); ?>" class="button button-small" style="color: #16a34a; border-color: #86efac; background: #f0fdf4;">تعيين كـ حراسة الآن</a>
                                        <?php endif; ?>

                                        <a href="?page=zaio-v14-manager&tab=manager&action=edit&id=<?php echo intval( $ph->ph_id ); ?>" class="button button-small">تعديل</a>
                                        
                                        <a href="<?php echo wp_nonce_url( '?page=zaio-v14-manager&tab=duty&zaio_v14_action=remove_from_rotation&idx=' . $idx, 'zaio_duty_action_nonce' ); ?>" class="button button-small button-link-delete" onclick="return confirm('هل أنت متأكد من حذف هذه الصيدلية من قائمة التدوير؟ (لن يتم مسحها من الدليل الرئيسي)');">حذف من القائمة</a>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </form>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // Toggle action buttons on card click
                $(document).on('click', '.zaio-seq-card', function(e) {
                    if ($(e.target).closest('a, button, input, .zaio-drag-handle').length) {
                        return;
                    }
                    $(this).toggleClass('show-actions');
                });

                if ($('#zaio-sortable-sequence-list').length) {
                    $('#zaio-sortable-sequence-list').sortable({
                        handle: '.zaio-drag-handle',
                        update: function(event, ui) {
                            var ids = [];
                            $('#zaio-sortable-sequence-list .zaio-seq-card').each(function() {
                                ids.push($(this).data('ph-id'));
                            });
                            $('#ordered_sequence_str').val(ids.join(','));
                            $('#zaio-reorder-form').submit();
                        }
                    });
                }
            });
            </script>

        <?php elseif ( $tab === 'manager' ) : 
            $ph_record = null;
            if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
                $edit_id = intval( $_GET['id'] );
                $ph_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_pharmacies WHERE ph_id = %d", $edit_id ) );
            }
            ?>
            <div style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 1; background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3><?php echo $ph_record ? 'تعديل بيانات الصيدلية' : 'إضافة صيدلية جديدة'; ?></h3>
                    <form method="post">
                        <?php wp_nonce_field( 'zaio_v14_manager_nonce_action', 'zaio_v14_manager_nonce' ); ?>
                        <input type="hidden" name="zaio_v14_action" value="<?php echo $ph_record ? 'update_ph' : 'insert_ph'; ?>">
                        <?php if ( $ph_record ) : ?>
                            <input type="hidden" name="form_ph_id" value="<?php echo intval( $ph_record->ph_id ); ?>">
                        <?php endif; ?>

                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: bold; display: block; margin-bottom: 5px;">اسم الصيدلية:</label>
                            <input type="text" name="name" required value="<?php echo $ph_record ? esc_attr( $ph_record->ph_name ) : ''; ?>" class="regular-text" style="width: 100%;">
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: bold; display: block; margin-bottom: 5px;">رقم الهاتف:</label>
                            <input type="text" name="phone" required value="<?php echo $ph_record ? esc_attr( $ph_record->ph_phone ) : ''; ?>" class="regular-text" style="width: 100%; direction: ltr; text-align: right;">
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: bold; display: block; margin-bottom: 5px;">العنوان:</label>
                            <textarea name="address" required rows="3" style="width: 100%;"><?php echo $ph_record ? esc_textarea( $ph_record->ph_address ) : ''; ?></textarea>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="font-weight: bold; display: block; margin-bottom: 5px;">إحداثيات الموقع (Normal Text Field):</label>
                            <input type="text" name="embed_code" required value="<?php echo $ph_record ? esc_attr( wp_unslash( $ph_record->ph_embed_code ) ) : ''; ?>" class="regular-text" style="width: 100%; direction: ltr; text-align: left;" placeholder="34.94166684126897, -2.737624241996165">
                        </div>
                        <div>
                            <input type="submit" class="button button-primary" value="<?php echo $ph_record ? 'حفظ التعديل' : 'تسجيل الصيدلية'; ?>">
                            <?php if ( $ph_record ) : ?>
                                <a href="?page=zaio-v14-manager&tab=manager" class="button">إلغاء</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div style="flex: 2; background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3>دليل الصيدليات بمدينة زايو</h3>
                    <table class="wp-list-table widefat fixed striped" style="text-align: right;">
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>الهاتف</th>
                                <th>العنوان</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $list = $wpdb->get_results( "SELECT * FROM $table_pharmacies ORDER BY ph_id DESC" );
                            if ( empty( $list ) ) {
                                echo '<tr><td colspan="4" style="text-align: center;">لا توجد صيدليات مضافة بدليل زايو بعد.</td></tr>';
                            } else {
                                foreach ( $list as $ph ) {
                                    ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $ph->ph_name ); ?></strong></td>
                                        <td><?php echo esc_html( $ph->ph_phone ); ?></td>
                                        <td><?php echo esc_html( $ph->ph_address ); ?></td>
                                        <td>
                                            <a href="?page=zaio-v14-manager&tab=manager&action=edit&id=<?php echo intval( $ph->ph_id ); ?>" class="button button-small">تعديل</a>
                                            <a href="<?php echo wp_nonce_url( '?page=zaio-v14-manager&tab=manager&zaio_v14_action=delete_ph&id=' . intval( $ph->ph_id ), 'delete_ph_verify_' . $ph->ph_id ); ?>" class="button button-small button-link-delete" onclick="return confirm('هل أنت متأكد من حذف هذه الصيدلية تماماً من الدليل الرئيسي؟');">حذف تماماً من الدليل</a>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ( $tab === 'settings' ) : 
            $opt_day = get_option( 'zaio_v14_opt_day', 'Monday' );
            $opt_time = get_option( 'zaio_v14_opt_time', '00:00' );
            
            $days = array( 
                'Monday'    => 'الإثنين', 
                'Tuesday'   => 'الثلاثاء', 
                'Wednesday' => 'الأربعاء', 
                'Thursday'  => 'الخميس', 
                'Friday'    => 'الجمعة', 
                'Saturday'  => 'السبت', 
                'Sunday'    => 'الأحد' 
            );
            ?>
            <div style="background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3>إعدادات جدولة صيدليات الحراسة</h3>
                <form method="post" action="options.php" style="max-width: 800px;">
                    <?php settings_fields( 'zaio_v14_settings_group' ); ?>
                    <?php do_settings_sections( 'zaio_v14_settings_group' ); ?>

                    <table class="form-table" style="text-align: right; direction: rtl;">
                        <tr>
                            <th scope="row" style="padding-right: 0; font-weight: bold;">يوم تبديل الحراسة الأسبوعية</th>
                            <td>
                                <select name="zaio_v14_opt_day">
                                    <?php foreach ( $days as $eng => $ara ) : ?>
                                        <option value="<?php echo esc_attr( $eng ); ?>" <?php selected( $opt_day, $eng ); ?>><?php echo esc_html( $ara ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">اليوم الذي تنتهي فيه حراسة الصيدلية السابقة وتتسلم فيه الصيدلية الحالية مهامها الأسبوعية.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" style="padding-right: 0; font-weight: bold;">ساعة تبديل الحراسة الأسبوعية</th>
                            <td>
                                <input type="text" name="zaio_v14_opt_time" value="<?php echo esc_attr( $opt_time ); ?>" class="regular-text" style="max-width: 150px; direction: ltr; text-align: right;" placeholder="00:00">
                                <p class="description">الساعة المحددة لتبديل الحراسة الأسبوعية بصيغة 24 ساعة (مثال: 00:00 لتبديل منتصف الليل، أو 09:00 صباحاً).</p>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top: 30px;">
                        <?php submit_button('حفظ الإعدادات'); ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div style="margin-top: 40px; padding: 15px 0; border-top: 1px solid #ddd; text-align: center; font-size: 13px; color: #666; direction: ltr;">
            <a href="https://seddik.be" target="_blank" style="text-decoration: none; font-weight: bold; color: #0073aa;">Seddik Belbikkey</a> :برمجة وتطوير
        </div>
    </div>
    <?php
}

/**
 * 7. DB Operations & Entry Handlers
 */
function zaio_v14_handle_form_posts() {
    global $wpdb;
    $table_pharmacies = $wpdb->prefix . 'zaio_v14_pharmacies';
    $table_rotations = $wpdb->prefix . 'zaio_v14_rotations';

    // Handler A: Add Pharmacy to End of Rotation Sequence
    if ( isset( $_POST['zaio_v14_action'] ) && $_POST['zaio_v14_action'] === 'add_to_rotation' ) {
        check_admin_referer( 'zaio_v14_duty_nonce_action', 'zaio_v14_duty_nonce' );
        $ph_id = intval( $_POST['form_pharmacy_id'] );
        if ( $ph_id > 0 ) {
            $seq = zaio_v14_get_rotation_sequence();
            $seq[] = $ph_id;
            update_option( 'zaio_v14_rotation_sequence', array_values( $seq ) );
            echo '<div class="updated"><p>تم إضافة الصيدلية إلى أسفل قائمة التدوير الدوري بنجاح!</p></div>';
        }
    }

    // Handler B: Save Drag & Drop Reordered Sequence
    if ( isset( $_POST['zaio_v14_action'] ) && $_POST['zaio_v14_action'] === 'save_reorder' ) {
        check_admin_referer( 'zaio_v14_duty_nonce_action', 'zaio_v14_duty_nonce' );
        if ( ! empty( $_POST['ordered_sequence_str'] ) ) {
            $raw_ids = explode( ',', $_POST['ordered_sequence_str'] );
            $new_seq = array();
            foreach ( $raw_ids as $id_str ) {
                $clean_id = intval( trim( $id_str ) );
                if ( $clean_id > 0 ) {
                    $new_seq[] = $clean_id;
                }
            }
            if ( ! empty( $new_seq ) ) {
                update_option( 'zaio_v14_rotation_sequence', array_values( $new_seq ) );
                echo '<div class="updated"><p>تم حفظ الترتيب الجديد لقائمة التدوير بنجاح!</p></div>';
            }
        }
    }

    // Handler C: Move Item Up in Sequence
    if ( isset( $_GET['zaio_v14_action'] ) && $_GET['zaio_v14_action'] === 'move_up' ) {
        $idx = intval( $_GET['idx'] );
        check_admin_referer( 'zaio_duty_action_nonce' );
        $seq = zaio_v14_get_rotation_sequence();
        if ( $idx > 0 && isset( $seq[$idx] ) ) {
            $temp = $seq[$idx];
            $seq[$idx] = $seq[$idx - 1];
            $seq[$idx - 1] = $temp;
            update_option( 'zaio_v14_rotation_sequence', array_values( $seq ) );
            echo '<div class="updated"><p>تم تحريك الصيدلية للأعلى بنجاح.</p></div>';
        }
    }

    // Handler D: Move Item Down in Sequence
    if ( isset( $_GET['zaio_v14_action'] ) && $_GET['zaio_v14_action'] === 'move_down' ) {
        $idx = intval( $_GET['idx'] );
        check_admin_referer( 'zaio_duty_action_nonce' );
        $seq = zaio_v14_get_rotation_sequence();
        if ( isset( $seq[$idx] ) && isset( $seq[$idx + 1] ) ) {
            $temp = $seq[$idx];
            $seq[$idx] = $seq[$idx + 1];
            $seq[$idx + 1] = $temp;
            update_option( 'zaio_v14_rotation_sequence', array_values( $seq ) );
            echo '<div class="updated"><p>تم تحريك الصيدلية للأسفل بنجاح.</p></div>';
        }
    }

    // Handler E: Set Pharmacy as Active Duty Now
    if ( isset( $_GET['zaio_v14_action'] ) && $_GET['zaio_v14_action'] === 'set_current_duty' ) {
        $idx = intval( $_GET['idx'] );
        check_admin_referer( 'zaio_duty_action_nonce' );
        $active_week = zaio_v14_get_active_week();
        update_option( 'zaio_v14_rotation_anchor_week', $active_week );
        update_option( 'zaio_v14_active_rotation_index', $idx );
        echo '<div class="updated"><p>تم تعيين الصيدلية كـ الحارسة الحالية لهذا الأسبوع بنجاح!</p></div>';
    }

    // Handler F: Remove Pharmacy from Rotation Sequence ONLY
    if ( isset( $_GET['zaio_v14_action'] ) && $_GET['zaio_v14_action'] === 'remove_from_rotation' ) {
        $idx = intval( $_GET['idx'] );
        check_admin_referer( 'zaio_duty_action_nonce' );
        $seq = zaio_v14_get_rotation_sequence();
        if ( isset( $seq[$idx] ) ) {
            array_splice( $seq, $idx, 1 );
            update_option( 'zaio_v14_rotation_sequence', array_values( $seq ) );
            echo '<div class="updated"><p>تم حذف الصيدلية من قائمة التدوير فقط. (بيانات الصيدلية محفوظة بالدليل)</p></div>';
        }
    }

    // Handler G: Insert Directory Pharmacy Card
    if ( isset( $_POST['zaio_v14_action'] ) && $_POST['zaio_v14_action'] === 'insert_ph' ) {
        check_admin_referer( 'zaio_v14_manager_nonce_action', 'zaio_v14_manager_nonce' );
        
        $raw_input = trim( $_POST['embed_code'] );
        $processed_map = zaio_v14_process_coordinates_input( $raw_input );

        $inserted = $wpdb->insert(
            $table_pharmacies,
            array(
                'ph_name'       => sanitize_text_field( $_POST['name'] ),
                'ph_phone'      => sanitize_text_field( $_POST['phone'] ),
                'ph_address'    => sanitize_textarea_field( $_POST['address'] ),
                'ph_embed_code' => $processed_map['saved_link'], 
                'ph_latitude'   => $processed_map['lat'],
                'ph_longitude'  => $processed_map['lng']
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $inserted !== false ) {
            echo '<div class="updated"><p>تم إضافة بيانات الصيدلية إلى الدليل بنجاح!</p></div>';
        }
    }

    // Handler H: Update Directory Pharmacy Card
    if ( isset( $_POST['zaio_v14_action'] ) && $_POST['zaio_v14_action'] === 'update_ph' ) {
        check_admin_referer( 'zaio_v14_manager_nonce_action', 'zaio_v14_manager_nonce' );
        $ph_id = intval( $_POST['form_ph_id'] );
        $raw_input = trim( $_POST['embed_code'] );
        $processed_map = zaio_v14_process_coordinates_input( $raw_input );

        $wpdb->update(
            $table_pharmacies,
            array(
                'ph_name'       => sanitize_text_field( $_POST['name'] ),
                'ph_phone'      => sanitize_text_field( $_POST['phone'] ),
                'ph_address'    => sanitize_textarea_field( $_POST['address'] ),
                'ph_embed_code' => $processed_map['saved_link'], 
                'ph_latitude'   => $processed_map['lat'],
                'ph_longitude'  => $processed_map['lng']
            ),
            array( 'ph_id' => $ph_id ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
        echo '<div class="updated"><p>تم تحديث بيانات الصيدلية بنجاح!</p></div>';
    }

    // Handler I: Delete Pharmacy Completely from Directory
    if ( isset( $_GET['zaio_v14_action'] ) && $_GET['zaio_v14_action'] === 'delete_ph' ) {
        $id = intval( $_GET['id'] );
        check_admin_referer( 'delete_ph_verify_' . $id );

        $wpdb->delete( $table_pharmacies, array( 'ph_id' => $id ), array( '%d' ) );
        $wpdb->delete( $table_rotations, array( 'rot_ph_id' => $id ), array( '%d' ) );
        
        // Also remove from rotation sequence array
        $seq = zaio_v14_get_rotation_sequence();
        $seq = array_values( array_diff( $seq, array( $id ) ) );
        update_option( 'zaio_v14_rotation_sequence', $seq );

        echo '<div class="updated"><p>تم حذف الصيدلية بنجاح من الدليل ومن قائمة التدوير.</p></div>';
    }
}

/**
 * 8. REST API: Dynamic Active Duty Endpoint Routing
 * GET: wp-json/zaiotv/v1/hirassa
 */
add_action( 'rest_api_init', 'zaio_v14_register_api' );
function zaio_v14_register_api() {
    register_rest_route( 'zaiotv/v1', '/hirassa', array(
        'methods'             => 'GET',
        'callback'            => 'zaio_v14_get_active_duty_json',
        'permission_callback' => '__return_true'
    ) );
}

function zaio_v14_get_active_duty_json() {
    $active_week = zaio_v14_get_active_week();
    $start_obj   = zaio_v14_get_rotation_start_obj();

    $pharmacy = zaio_v14_get_active_duty_pharmacy( $active_week );

    if ( $pharmacy ) {
        $ph_data = array(
            'id'        => intval( $pharmacy->ph_id ),
            'name'      => $pharmacy->ph_name,
            'phone'     => $pharmacy->ph_phone,
            'address'   => $pharmacy->ph_address,
            'lat'       => $pharmacy->ph_latitude,
            'lng'       => $pharmacy->ph_longitude,
            'gmaps_url' => "https://maps.google.com/?q=" . urlencode( $pharmacy->ph_latitude . ',' . $pharmacy->ph_longitude )
        );

        return new WP_REST_Response( array(
            'status'     => 'success',
            'week'       => $active_week,
            'configured' => true,
            'period'     => array(
                'start' => wp_date( 'd F Y', $start_obj->getTimestamp() ),
                'end'   => wp_date( 'd F Y', $start_obj->getTimestamp() + ( 6 * DAY_IN_SECONDS ) )
            ),
            'pharmacies' => array( $ph_data )
        ), 200 );
    }

    return new WP_REST_Response( array(
        'status'     => 'no_data',
        'week'       => $active_week,
        'configured' => false,
        'message'    => 'No on-duty pharmacies are configured for this week.',
        'pharmacies' => array()
    ), 200 );
}

/**
 * 9. Plugin Page Template Integration
 */
add_filter( 'theme_page_templates', 'zaio_v14_register_pharmacy_template' );
function zaio_v14_register_pharmacy_template( $post_templates ) {
    $post_templates['template-pharmacy.php'] = __( 'جدول صيدليات الحراسة (الفيشة المؤشرة)', 'zaio-hirassa-manager' );
    return $post_templates;
}

add_filter( 'template_include', 'zaio_v14_load_pharmacy_template' );
function zaio_v14_load_pharmacy_template( $template ) {
    if ( is_page() ) {
        $meta_template = get_post_meta( get_the_ID(), '_wp_page_template', true );
        if ( 'template-pharmacy.php' === $meta_template || is_page( 'pharmacy' ) || is_page( 'pharmacies' ) || is_page( 'صيدليات-الحراسة' ) || is_page( 'صيدلية-الحراسة' ) ) {
            if ( wp_is_mobile() ) {
                $mobile_template = plugin_dir_path( __FILE__ ) . 'templates/mobile/template-pharmacy.php';
                if ( file_exists( $mobile_template ) ) {
                    return $mobile_template;
                }
            }
            $desktop_template = plugin_dir_path( __FILE__ ) . 'templates/template-pharmacy.php';
            if ( file_exists( $desktop_template ) ) {
                return $desktop_template;
            }
        }
    }
    return $template;
}