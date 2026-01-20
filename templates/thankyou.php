<?php
/**
 * Thank You Page Template
 * Displays a customizable thank you message after successful appointment booking
 */

// Get the custom post type content
$args = array(
    'post_type'      => 'appointment_thankyou',
    'posts_per_page' => 1,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC'
);

$thankyou_posts = get_posts($args);
$custom_message = '';
$custom_title = '';

if (!empty($thankyou_posts)) {
    $post = $thankyou_posts[0];
    $custom_title = $post->post_title;
    $custom_message = apply_filters('the_content', $post->post_content);
}

// Default content if no custom message is set
if (empty($custom_title)) {
    $custom_title = 'Thank You! We\'re Excited to Meet You!';
}

if (empty($custom_message)) {
    $custom_message = '<p class="thankyou-subtitle">Your appointment has been successfully booked. A confirmation email has been sent to your email address with all the details.</p>
    <p>We look forward to meeting with you!</p>';
}

// Get appointment details from URL parameters (if available)
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

// Enqueue thank you page specific styles
wp_enqueue_style('appointment-thankyou-style', APPOINTMENT_SCHEDULER_PLUGIN_URL . 'assets/css/thankyou.css', array(), APPOINTMENT_SCHEDULER_VERSION);
?>

<div class="appointment-thankyou-wrapper">
    <div class="appointment-thankyou-container">
        <div class="thankyou-header">
            <div class="thankyou-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <h1 class="thankyou-title"><?php echo esc_html($custom_title); ?></h1>
        </div>
        
        <div class="thankyou-content">
            <?php echo wp_kses_post($custom_message); ?>
        </div>
        
        <?php if (!empty($appointment_details)): ?>
        <div class="appointment-details-card">
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
        
        <div class="thankyou-info-grid">
            <div class="thankyou-info-card">
                <div class="info-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </div>
                <h3>Check Your Email</h3>
                <p>We've sent a confirmation email with your appointment details and Google Meet link.</p>
            </div>
            
            <div class="thankyou-info-card">
                <div class="info-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                </div>
                <h3>Add to Calendar</h3>
                <p>Click the link in your email to add this appointment to your Google Calendar.</p>
            </div>
            
            <div class="thankyou-info-card">
                <div class="info-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
                <h3>We'll Send Reminders</h3>
                <p>You'll receive reminder emails before your appointment to make sure you don't miss it.</p>
            </div>
        </div>
        
        <div class="thankyou-actions">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-primary">
                Return to Home
            </a>
        </div>
    </div>
</div>
