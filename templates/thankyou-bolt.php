<?php
/**
 * Enhanced Thank You Page Template - Bolt+ Style
 * Fully editable from WordPress dashboard
 */

// Get the custom thank you content
$args = array(
    'post_type'      => 'appointment_thankyou',
    'posts_per_page' => 1,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC'
);

$thankyou_posts = get_posts($args);
$thank_you_data = array();

if (!empty($thankyou_posts)) {
    $post = $thankyou_posts[0];
    
    // Get custom fields
    $thank_you_data = array(
        'logo_url' => get_post_meta($post->ID, '_thankyou_logo_url', true),
        'main_heading' => get_post_meta($post->ID, '_thankyou_main_heading', true),
        'description_intro' => get_post_meta($post->ID, '_thankyou_desc_intro', true),
        'description_para1' => get_post_meta($post->ID, '_thankyou_desc_para1', true),
        'description_para2' => get_post_meta($post->ID, '_thankyou_desc_para2', true),
        'description_para3' => get_post_meta($post->ID, '_thankyou_desc_para3', true),
        'stat1_number' => get_post_meta($post->ID, '_thankyou_stat1_number', true),
        'stat1_label' => get_post_meta($post->ID, '_thankyou_stat1_label', true),
        'stat2_number' => get_post_meta($post->ID, '_thankyou_stat2_number', true),
        'stat2_label' => get_post_meta($post->ID, '_thankyou_stat2_label', true),
        'stat3_number' => get_post_meta($post->ID, '_thankyou_stat3_number', true),
        'stat3_label' => get_post_meta($post->ID, '_thankyou_stat3_label', true),
        'button1_text' => get_post_meta($post->ID, '_thankyou_button1_text', true),
        'button1_url' => get_post_meta($post->ID, '_thankyou_button1_url', true),
        'button2_text' => get_post_meta($post->ID, '_thankyou_button2_text', true),
        'button2_url' => get_post_meta($post->ID, '_thankyou_button2_url', true),
        'carousel_images' => get_post_meta($post->ID, '_thankyou_carousel_images', true),
    );
}

// Default values if no custom content exists
$defaults = array(
    'logo_url' => APPOINTMENT_SCHEDULER_PLUGIN_URL . 'assets/images/logo.png',
    'main_heading' => 'Thank You! We\'re Excited to Show You Around!',
    'description_intro' => 'In the meantime, why not take a self-tour?',
    'description_para1' => 'While you wait for your demo, we\'d love to have you explore the platform on your own.',
    'description_para2' => 'Watch the Immersive Panel in action: chat, shop, AI, read, rewards, and more.',
    'description_para3' => 'Our proprietary sandbox combines a wealth of content with an abundance of engagement.',
    'stat1_number' => '110+',
    'stat1_label' => 'Streams to watch now',
    'stat2_number' => '380,000 mins',
    'stat2_label' => 'Streams added (monthly)',
    'stat3_number' => '14 Million',
    'stat3_label' => 'TV installs',
    'button1_text' => 'Launch Web App',
    'button1_url' => home_url('/'),
    'button2_text' => 'Learn More',
    'button2_url' => home_url('/about'),
    'carousel_images' => array(),
);

// Merge with defaults
foreach ($defaults as $key => $value) {
    if (empty($thank_you_data[$key])) {
        $thank_you_data[$key] = $value;
    }
}

// Get appointment details from URL parameters
$appointment_details = array();
if (isset($_GET['name'])) {
    $appointment_details['name'] = sanitize_text_field($_GET['name']);
}
if (isset($_GET['email'])) {
    $appointment_details['email'] = sanitize_email($_GET['email']);
}
if (isset($_GET['date'])) {
    $appointment_details['date'] = sanitize_text_field($_GET['date']);
    $appointment_details['date_formatted'] = date('F j, Y', strtotime($_GET['date']));
}
if (isset($_GET['time'])) {
    $appointment_details['time'] = sanitize_text_field($_GET['time']);
    $appointment_details['time_formatted'] = date('g:i A', strtotime($_GET['time']));
}

// Enqueue styles
wp_enqueue_style('appointment-thankyou-bolt-style', APPOINTMENT_SCHEDULER_PLUGIN_URL . 'assets/css/thankyou-bolt.css', array(), APPOINTMENT_SCHEDULER_VERSION);
?>

<div class="thankyou-bolt-container animate-fade-in-up">
    
    <!-- Logo -->
    <?php if (!empty($thank_you_data['logo_url'])): ?>
    <div class="thankyou-logo">
        <img src="<?php echo esc_url($thank_you_data['logo_url']); ?>" alt="Logo" />
    </div>
    <?php endif; ?>
    
    <!-- Main Heading -->
    <h1 class="thankyou-main-heading">
        <?php echo esc_html($thank_you_data['main_heading']); ?>
    </h1>
    
    <!-- Appointment Details (if available) -->
    <?php if (!empty($appointment_details)): ?>
    <div class="thankyou-appointment-details">
        <h3>Your Appointment Details</h3>
        <div class="details-grid">
            <?php if (isset($appointment_details['name'])): ?>
            <div class="detail-item">
                <span class="detail-label">Name:</span>
                <span class="detail-value"><?php echo esc_html($appointment_details['name']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (isset($appointment_details['email'])): ?>
            <div class="detail-item">
                <span class="detail-label">Email:</span>
                <span class="detail-value"><?php echo esc_html($appointment_details['email']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (isset($appointment_details['date_formatted'])): ?>
            <div class="detail-item">
                <span class="detail-label">Date:</span>
                <span class="detail-value"><?php echo esc_html($appointment_details['date_formatted']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (isset($appointment_details['time_formatted'])): ?>
            <div class="detail-item">
                <span class="detail-label">Time:</span>
                <span class="detail-value"><?php echo esc_html($appointment_details['time_formatted']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Two Column Layout -->
    <div class="thankyou-two-column">
        
        <!-- Left Column - Description -->
        <div class="thankyou-left-column">
            <div class="thankyou-description">
                <?php if (!empty($thank_you_data['description_intro'])): ?>
                <p class="font-semibold">
                    <?php echo esc_html($thank_you_data['description_intro']); ?>
                </p>
                <?php endif; ?>
                
                <?php if (!empty($thank_you_data['description_para1'])): ?>
                <p><?php echo esc_html($thank_you_data['description_para1']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($thank_you_data['description_para2'])): ?>
                <p><?php echo esc_html($thank_you_data['description_para2']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($thank_you_data['description_para3'])): ?>
                <p><?php echo esc_html($thank_you_data['description_para3']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Right Column - Stats -->
        <div class="thankyou-right-column">
            <div class="thankyou-stats">
                
                <!-- Stat 1 -->
                <div class="thankyou-stat-item">
                    <div class="thankyou-stat-number">
                        <?php echo esc_html($thank_you_data['stat1_number']); ?>
                    </div>
                    <div class="thankyou-stat-label">
                        <?php echo esc_html($thank_you_data['stat1_label']); ?>
                    </div>
                </div>
                
                <!-- Stat 2 -->
                <div class="thankyou-stat-item">
                    <div class="thankyou-stat-number">
                        <?php echo esc_html($thank_you_data['stat2_number']); ?>
                    </div>
                    <div class="thankyou-stat-label">
                        <?php echo esc_html($thank_you_data['stat2_label']); ?>
                    </div>
                </div>
                
                <!-- Stat 3 -->
                <div class="thankyou-stat-item">
                    <div class="thankyou-stat-number">
                        <?php echo esc_html($thank_you_data['stat3_number']); ?>
                    </div>
                    <div class="thankyou-stat-label">
                        <?php echo esc_html($thank_you_data['stat3_label']); ?>
                    </div>
                </div>
                
            </div>
        </div>
        
    </div>
    
    <!-- Carousel -->
    <?php if (!empty($thank_you_data['carousel_images']) && is_array($thank_you_data['carousel_images'])): ?>
    <div class="thankyou-carousel">
        <div class="thankyou-carousel-wrapper">
            <div class="thankyou-carousel-track">
                <?php 
                // Duplicate images for infinite scroll effect
                $images = array_merge($thank_you_data['carousel_images'], $thank_you_data['carousel_images']);
                foreach ($images as $image_url): 
                ?>
                <div class="thankyou-carousel-item">
                    <img src="<?php echo esc_url($image_url); ?>" alt="Carousel Image" />
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Buttons -->
    <div class="thankyou-buttons">
        <?php if (!empty($thank_you_data['button1_text']) && !empty($thank_you_data['button1_url'])): ?>
        <a href="<?php echo esc_url($thank_you_data['button1_url']); ?>" class="thankyou-button contained" target="_blank">
            <?php echo esc_html($thank_you_data['button1_text']); ?>
            <svg class="launch-arrow-svg" width="18" height="18" viewBox="0 0 24 24">
                <path d="M7 17L17 7M17 7H7M17 7V17"/>
            </svg>
        </a>
        <?php endif; ?>
        
        <?php if (!empty($thank_you_data['button2_text']) && !empty($thank_you_data['button2_url'])): ?>
        <a href="<?php echo esc_url($thank_you_data['button2_url']); ?>" class="thankyou-button outlined" target="_blank">
            <?php echo esc_html($thank_you_data['button2_text']); ?>
            <svg class="launch-arrow-svg" width="18" height="18" viewBox="0 0 24 24">
                <path d="M7 17L17 7M17 7H7M17 7V17"/>
            </svg>
        </a>
        <?php endif; ?>
    </div>
    
</div>
