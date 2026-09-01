<?php
/**
 * Template Name: صيدلية الحراسة
 * Description: Desktop Page Template for On-Duty Pharmacies (صيدلية الحراسة) using Google Maps Embed
 */

get_header();

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
$start_time_str = date( 'H:i', $start_timestamp );
?>

<div class="body-container">
    <div class="container">
        <div class="main-layout-wrapper">
            
            <!-- Main Content Column -->
            <main class="main-content">
                <?php while ( have_posts() ) : the_post(); ?>
                    
                    <article id="post-<?php the_ID(); ?>" <?php post_class( 'desktop-pharmacy-page' ); ?>>
                        
                        <h1 class="post-main-title"><?php the_title(); ?></h1>

                        <div class="pharmacy-template-container">
                            <?php if ( $active_pharmacy ) : ?>
                                <div class="pharmacy-map-layout-wrapper">
                                    
                                    <!-- Embedded Live Google Map -->
                                    <div class="pharmacy-map-container">
                                        <?php
                                        // True location coordinates for the pin
                                        $lat = $active_pharmacy->ph_latitude;
                                        $lng = $active_pharmacy->ph_longitude;
                                        
                                        // Slightly offset center coordinate (shifting view to the left)
                                        $center_lng = (float)$lng + 0.002;
                                        
                                        // Dynamic embed source with custom label inside parentheses
                                        $embed_src = "https://maps.google.com/maps?q={$lat},{$lng}+(" . urlencode($active_pharmacy->ph_name) . ")&ll={$lat},{$center_lng}&t=k&z=18&ie=UTF8&iwloc=addr&output=embed";
                                        ?>
                                        <iframe 
                                            class="pharmacy-google-iframe" 
                                            frameborder="0" 
                                            scrolling="no" 
                                            marginheight="0" 
                                            marginwidth="0" 
                                            src="<?php echo esc_url( $embed_src ); ?>">
                                        </iframe>
                                        
                                        <!-- Overlay Floating Details Card (Placed on the right side) -->
                                        <div class="pharmacy-details-card floating-card">
                                            <div class="duty-badge">
                                                <i class="fa-solid fa-star-of-life pulse-icon"></i>
                                                <span>صيدلية الحراسة حالياً</span>
                                            </div>
                                            
                                            <h2 class="pharmacy-name"><?php echo esc_html( $active_pharmacy->ph_name ); ?></h2>
                                            
                                            <div class="pharmacy-meta-list">
                                                <div class="meta-item">
                                                    <div class="meta-icon"><i class="fa-solid fa-phone"></i></div>
                                                    <div class="meta-content">
                                                        <span class="meta-label">رقم الهاتف</span>
                                                        <a href="tel:<?php echo esc_attr( $active_pharmacy->ph_phone ); ?>" class="meta-value phone-link">
                                                            <?php echo esc_html( $active_pharmacy->ph_phone ); ?>
                                                        </a>
                                                    </div>
                                                </div>
                                                
                                                <div class="meta-item">
                                                    <div class="meta-icon"><i class="fa-solid fa-location-dot"></i></div>
                                                    <div class="meta-content">
                                                        <span class="meta-label">العنوان</span>
                                                        <span class="meta-value"><?php echo esc_html( $active_pharmacy->ph_address ); ?></span>
                                                    </div>
                                                </div>
                                                
                                                <div class="meta-item">
                                                    <div class="meta-icon"><i class="fa-solid fa-calendar-days"></i></div>
                                                    <div class="meta-content">
                                                        <span class="meta-label">فترة الحراسة</span>
                                                        <span class="meta-value date-range">
                                                            من <?php echo esc_html( $start_date_str ); ?><br>
                                                            إلى <?php echo esc_html( $end_date_str ); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <a href="https://maps.google.com/?q=<?php echo urlencode($active_pharmacy->ph_latitude . ',' . $active_pharmacy->ph_longitude); ?>" target="_blank" class="gmaps-btn-link">
                                                <i class="fa-solid fa-location-arrow"></i> الاتجاهات عبر خرائط جوجل
                                            </a>
                                        </div>
                                    </div>

                                </div>
                            <?php else : ?>
                                <div class="no-pharmacy-fallback">
                                    <div class="fallback-icon">
                                        <i class="fa-solid fa-circle-info"></i>
                                    </div>
                                    <h3>لا توجد صيدلية حراسة معينة حالياً</h3>
                                    <p>لم يتم جدولة صيدلية الحراسة لهذا الأسبوع بعد. يرجى مراجعة الجدول في وقت لاحق.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Content below if page editor has text -->
                        <?php if ( get_the_content() ) : ?>
                            <div class="post-article-content pharmacy-post-text">
                                <?php the_content(); ?>
                            </div>
                        <?php endif; ?>

                    </article>

                <?php endwhile; ?>
            </main>

            <!-- Sidebar Column -->
            <aside class="main-sidebar">
                <?php if ( is_active_sidebar( 'main-sidebar' ) ) : ?>
                    <?php dynamic_sidebar( 'main-sidebar' ); ?>
                <?php endif; ?>
            </aside>

        </div>
    </div>
</div>

<style>
/* CSS Styles for Pharmacy Template */
.pharmacy-template-container {
    margin-bottom: 30px;
}
.pharmacy-map-layout-wrapper {
    position: relative;
    width: 100%;
    margin-top: 15px;
}
.pharmacy-map-container {
    position: relative;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
    height: 500px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.01);
}
body.dark-mode .pharmacy-map-container {
    border-color: rgba(255, 255, 255, 0.05);
}
.pharmacy-google-iframe {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
}
.pharmacy-details-card {
    background-color: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 30px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    transition: all 0.3s ease;
}
.pharmacy-details-card.floating-card {
    position: absolute;
    top: 25px;
    right: 25px;
    z-index: 10;
    width: 350px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}
body.dark-mode .pharmacy-details-card.floating-card {
    background-color: rgba(26, 26, 26, 0.95);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}
@media (max-width: 991px) {
    .pharmacy-map-container {
        height: 380px;
        display: flex;
        flex-direction: column;
    }
    .pharmacy-google-iframe {
        height: 380px;
    }
    .pharmacy-details-card.floating-card {
        position: relative;
        top: 0;
        right: 0;
        width: 100%;
        margin-top: 20px;
        box-shadow: none;
        background: var(--light-bg);
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        border-color: var(--border-color);
    }
    body.dark-mode .pharmacy-details-card.floating-card {
        background-color: rgba(255, 255, 255, 0.015);
        border-color: rgba(255, 255, 255, 0.04);
    }
}
.duty-badge {
    align-self: flex-start;
    background-color: rgba(206, 3, 62, 0.1);
    color: var(--primary-color);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
}
@keyframes pulse-red {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}
.pulse-icon {
    animation: pulse-red 2s infinite ease-in-out;
}
.pharmacy-name {
    font-size: 1.6rem;
    font-weight: 800;
    margin: 0 0 20px 0;
    color: var(--text-color);
    line-height: 1.3;
}
body.dark-mode .pharmacy-name {
    color: var(--dark-text);
}
.pharmacy-meta-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 25px;
}
.meta-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.meta-icon {
    font-size: 1.15rem;
    color: var(--primary-color);
    width: 20px;
    text-align: center;
    padding-top: 3px;
}
.meta-content {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.meta-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 700;
}
.meta-value {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-color);
    line-height: 1.4;
}
body.dark-mode .meta-value {
    color: var(--dark-text);
}
.phone-link {
    text-decoration: none;
    transition: color 0.2s ease;
    direction: ltr;
    display: inline-block;
}
.phone-link:hover {
    color: var(--primary-color);
}
.date-range {
    font-size: 0.9rem;
    font-weight: 600;
    line-height: 1.5;
}
.gmaps-btn-link {
    background-color: var(--primary-color);
    color: #fff;
    border-radius: 30px;
    padding: 11px 22px;
    text-align: center;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(206, 3, 62, 0.15);
    font-size: 0.9rem;
}
.gmaps-btn-link:hover {
    background-color: #b00234;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(206, 3, 62, 0.25);
    color: #fff;
}
.no-pharmacy-fallback {
    background-color: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 60px 40px;
    text-align: center;
}
body.dark-mode .no-pharmacy-fallback {
    background-color: rgba(255, 255, 255, 0.015);
    border-color: rgba(255, 255, 255, 0.04);
}
.fallback-icon {
    font-size: 3.5rem;
    color: var(--text-muted);
    margin-bottom: 20px;
}
.no-pharmacy-fallback h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 12px;
    color: var(--text-color);
}
body.dark-mode .no-pharmacy-fallback h3 {
    color: var(--dark-text);
}
.no-pharmacy-fallback p {
    color: var(--text-muted);
    margin: 0;
}
.pharmacy-post-text {
    margin-top: 30px;
}
</style>

<?php get_footer(); ?>
