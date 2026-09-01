<?php
/**
 * Template Name: صيدلية الحراسة
 * Description: Mobile Page Template for On-Duty Pharmacies (صيدلية الحراسة)
 */

locate_template( 'mobile/header.php', true, true );

global $wpdb;
$table_pharmacies = $wpdb->prefix . 'zaio_v14_pharmacies';
$table_rotations = $wpdb->prefix . 'zaio_v14_rotations';

// Retrieve active week from the new Version 1.4.0 plugin
if ( function_exists( 'zaio_v14_get_active_week' ) ) {
    $current_week = zaio_v14_get_active_week();
} else {
    $current_week = date( 'Y-\WW' );
}

$active_pharmacy = null;
if ( function_exists( 'zaio_v14_get_active_duty_pharmacy' ) ) {
    $active_pharmacy = zaio_v14_get_active_duty_pharmacy( $current_week );
} elseif ( $wpdb->get_var( "SHOW TABLES LIKE '$table_pharmacies'" ) && $wpdb->get_var( "SHOW TABLES LIKE '$table_rotations'" ) ) {
    $results = $wpdb->get_results( $wpdb->prepare( "
        SELECT p.ph_name, p.ph_phone, p.ph_address, p.ph_latitude, p.ph_longitude 
        FROM $table_rotations r 
        JOIN $table_pharmacies p ON r.rot_ph_id = p.ph_id 
        WHERE r.rot_week = %s
    ", $current_week ) );
    
    if ( ! empty( $results ) ) {
        $active_pharmacy = $results[0];
    }
}

// Calculate week range dates using local translation
$handover_day = get_option( 'zaio_v14_opt_day', 'Monday' );
$handover_time = get_option( 'zaio_v14_opt_time', '00:00' );

// Find the date range matching API (+6 days offset for 7-day period)
if ( function_exists( 'zaio_v14_get_rotation_start_obj' ) ) {
    $start_obj = zaio_v14_get_rotation_start_obj();
    $start_timestamp = $start_obj->getTimestamp();
    $end_timestamp   = $start_timestamp + ( 6 * DAY_IN_SECONDS );
} else {
    $year_week = explode( '-W', $current_week );
    if ( count( $year_week ) === 2 ) {
        $dto = new DateTime();
        $dto->setISODate( intval( $year_week[0] ), intval( $year_week[1] ) );
        $start_timestamp = strtotime( "$handover_day this week $handover_time", $dto->getTimestamp() );
        
        if ( $start_timestamp > $dto->getTimestamp() ) {
            $start_timestamp = strtotime( "last $handover_day $handover_time", $dto->getTimestamp() );
        }
        
        $end_timestamp = $start_timestamp + ( 6 * DAY_IN_SECONDS );
    } else {
        $start_timestamp = time();
        $end_timestamp = strtotime( '+6 days' );
    }
}

$start_date_str = date_i18n( 'j F Y', $start_timestamp );
$end_date_str   = date_i18n( 'j F Y', $end_timestamp );
?>

<main class="mobile-main-content">
    <?php while ( have_posts() ) : the_post(); ?>
        
        <article class="mobile-single-post mobile-pharmacy-page">
            
            <div class="mobile-single-header">
                <h1 class="mobile-single-title"><?php the_title(); ?></h1>
            </div>

            <div class="mobile-single-content">
                <?php if ( $active_pharmacy ) : ?>
                    
                    <div class="mobile-pharmacy-card">
                        
                        <!-- Duty Badge Header (Cleaned, no week references) -->
                        <div class="mobile-duty-header">
                            <span class="mobile-duty-badge">
                                <span class="badge-dot"></span>
                                صيدلية الحراسة حالياً
                            </span>
                        </div>
                        
                        <!-- Pharmacy Name -->
                        <h2 class="mobile-pharmacy-name"><?php echo esc_html( $active_pharmacy->ph_name ); ?></h2>
                        
                        <!-- Details List -->
                        <div class="mobile-pharmacy-details">
                            <div class="mobile-detail-item">
                                <div class="detail-icon"><i class="fa-solid fa-phone"></i></div>
                                <div class="detail-text">
                                    <div class="detail-label">رقم الهاتف</div>
                                    <a href="tel:<?php echo esc_attr( $active_pharmacy->ph_phone ); ?>" class="detail-val phone-call-link" style="direction: ltr;"><?php echo esc_html( $active_pharmacy->ph_phone ); ?></a>
                                </div>
                            </div>
                            
                            <div class="mobile-detail-item">
                                <div class="detail-icon"><i class="fa-solid fa-location-dot"></i></div>
                                <div class="detail-text">
                                    <div class="detail-label">العنوان بالكامل</div>
                                    <div class="detail-val"><?php echo esc_html( $active_pharmacy->ph_address ); ?></div>
                                </div>
                            </div>
                            
                            <div class="mobile-detail-item">
                                <div class="detail-icon"><i class="fa-solid fa-calendar-days"></i></div>
                                <div class="detail-text">
                                    <div class="detail-label">فترة المداومة</div>
                                    <div class="detail-val date-range">
                                        من <?php echo esc_html( $start_date_str ); ?><br>
                                        إلى <?php echo esc_html( $end_date_str ); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Big Action Touch Targets -->
                        <div class="mobile-action-buttons">
                            <!-- Call Now Button -->
                            <a href="tel:<?php echo esc_attr( $active_pharmacy->ph_phone ); ?>" class="mobile-btn mobile-call-btn">
                                <i class="fa-solid fa-phone-volume"></i>
                                <span>اتصل الآن بالصيدلية</span>
                            </a>
                            
                            <!-- Google Maps Navigation Button (Uses un-offset coordinates for accuracy) -->
                            <a href="https://maps.google.com/?q=<?php echo urlencode($active_pharmacy->ph_latitude . ',' . $active_pharmacy->ph_longitude); ?>" target="_blank" class="mobile-btn mobile-maps-btn">
                                <i class="fa-solid fa-location-arrow"></i>
                                <span>تحديد الموقع على الخريطة</span>
                            </a>
                        </div>

                    </div>
                    
                <?php else : ?>
                    
                    <div class="mobile-pharmacy-fallback">
                        <div class="fallback-icon">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <h3>لم يتم تعيين صيدلية حراسة</h3>
                        <p>لا توجد صيدلية حراسة مجدولة لهذا الأسبوع. يرجى مراجعة الصفحة لاحقاً.</p>
                    </div>
                    
                <?php endif; ?>

                <?php if ( get_the_content() ) : ?>
                    <div class="mobile-pharmacy-desc-content" style="margin-top: 25px;">
                        <?php the_content(); ?>
                    </div>
                <?php endif; ?>
            </div>

        </article>
    <?php endwhile; ?>
</main>

<style>
/* CSS Styles for Mobile Pharmacy Page */
.mobile-pharmacy-page {
    padding: 0 15px;
}
.mobile-pharmacy-card {
    background-color: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.01);
}
body.dark-mode .mobile-pharmacy-card {
    background-color: rgba(255, 255, 255, 0.015);
    border-color: rgba(255, 255, 255, 0.04);
}
.mobile-duty-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px dashed var(--border-color);
    padding-bottom: 12px;
    margin-bottom: 15px;
}
body.dark-mode .mobile-duty-header {
    border-bottom-color: rgba(255, 255, 255, 0.06);
}
.mobile-duty-badge {
    background-color: rgba(206, 3, 62, 0.08);
    color: var(--primary-color);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.badge-dot {
    width: 6px;
    height: 6px;
    background-color: var(--primary-color);
    border-radius: 50%;
    display: inline-block;
    animation: mobile-pulse-red 2s infinite ease-in-out;
}
@keyframes mobile-pulse-red {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.4); opacity: 0.5; }
    100% { transform: scale(1); opacity: 1; }
}
.mobile-pharmacy-name {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0 0 15px 0;
    color: var(--text-color);
}
body.dark-mode .mobile-pharmacy-name {
    color: var(--dark-text);
}
.mobile-pharmacy-details {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 22px;
}
.mobile-detail-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.detail-icon {
    font-size: 1.1rem;
    color: var(--primary-color);
    width: 20px;
    text-align: center;
    padding-top: 2px;
}
.detail-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.detail-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-weight: 600;
}
.detail-val {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-color);
    line-height: 1.4;
}
.phone-call-link {
    text-decoration: none;
    display: inline-block;
}
body.dark-mode .detail-val {
    color: var(--dark-text);
}
.mobile-action-buttons {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.mobile-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    height: 52px; /* standard mobile touch target */
    border-radius: var(--border-radius);
    text-decoration: none;
    font-weight: 700;
    font-size: 0.95rem;
    transition: all 0.2s ease;
}
.mobile-call-btn {
    background-color: var(--primary-color);
    color: #fff;
    box-shadow: 0 4px 10px rgba(206, 3, 62, 0.15);
}
.mobile-call-btn:active {
    background-color: #b00234;
    transform: scale(0.98);
}
.mobile-maps-btn {
    background-color: var(--white);
    border: 1px solid var(--border-color);
    color: var(--text-color);
}
body.dark-mode .mobile-maps-btn {
    background-color: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
    color: var(--dark-text);
}
.mobile-maps-btn:active {
    background-color: rgba(0,0,0,0.05);
    transform: scale(0.98);
}
body.dark-mode .mobile-maps-btn:active {
    background-color: rgba(255, 255, 255, 0.08);
}
.mobile-pharmacy-fallback {
    background-color: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 35px 20px;
    text-align: center;
}
body.dark-mode .mobile-pharmacy-fallback {
    background-color: rgba(255,255,255,0.02);
    border-color: rgba(255,255,255,0.05);
}
.mobile-pharmacy-fallback .fallback-icon {
    font-size: 2.5rem;
    color: var(--text-muted);
    margin-bottom: 12px;
}
.mobile-pharmacy-fallback h3 {
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--text-color);
}
body.dark-mode .mobile-pharmacy-fallback h3 {
    color: var(--dark-text);
}
.mobile-pharmacy-fallback p {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin: 0;
    line-height: 1.5;
}
</style>

<?php
locate_template( 'mobile/footer.php', true, true );
?>
