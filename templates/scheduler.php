<?php
/**
 * Appointment Scheduler Template - Two Column Layout (Google Style)
 */

// Construct data arrays for template compatibility - ONLY if not already passed from controller
if (!isset($sidebar_data)) {
    $sidebar_data = array(
        'title' => get_option('appointment_sidebar_title', 'BoltOS Demo'),
        'subtitle' => get_option('appointment_sidebar_subtitle', 'Turn viewers into revenue'),
        'duration' => get_option('appointment_sidebar_duration', '30 min appointments'),
        'location' => get_option('appointment_sidebar_location', 'Google Meet video conference info added after booking'),
        'description' => get_option('appointment_sidebar_description', "Our sales experts have helped thousands of companies growing revenue within weeks.")
    );
}

if (!isset($thankyou_data)) {
    $thankyou_data = array(
        'logo_url' => get_option('appointment_thankyou_logo_url', ''),
        'main_heading' => get_option('appointment_thankyou_main_heading', 'Complete Your Booking'),
        'description_intro' => get_option('appointment_thankyou_intro', 'A few more details and we are good to go!'),
        'stat1_number' => get_option('appointment_thankyou_stat1_number', '15k+'),
        'stat1_label' => get_option('appointment_thankyou_stat1_label', 'Customers'),
        'stat2_number' => get_option('appointment_thankyou_stat2_number', '120%'),
        'stat2_label' => get_option('appointment_thankyou_stat2_label', 'Avg. Growth'),
        'stat3_number' => get_option('appointment_thankyou_stat3_number', '4.9'),
        'stat3_label' => get_option('appointment_thankyou_stat3_label', 'User Rating'),
        'button1_text' => get_option('appointment_thankyou_button1_text', 'Join Community'),
        'button1_url' => get_option('appointment_thankyou_button1_url', '#'),
        'button2_text' => get_option('appointment_thankyou_button2_text', 'View Dashboard'),
        'button2_url' => get_option('appointment_thankyou_button2_url', '#'),
        'carousel_images' => get_option('appointment_thankyou_carousel_images', array())
    );
}

// Get timezone info (existing logic)
$appointment_timezone = get_option('appointment_timezone', 'Europe/London');
if (!empty($appointment_timezone)) {
    $timezone_obj = new DateTimeZone($appointment_timezone);
    $datetime = new DateTime('now', $timezone_obj);
    $offset = $timezone_obj->getOffset($datetime) / 3600;
    $offset_formatted = ($offset >= 0 ? '+' : '') . sprintf('%02d:00', abs($offset));
    $timezone_parts = explode('/', $appointment_timezone);
    $region = str_replace('_', ' ', end($timezone_parts));
    $timezone_label = '(GMT' . $offset_formatted . ') ' . $region;
} else {
    // Fallback logic...
    $timezone = get_option('timezone_string');
    if (empty($timezone)) {
        $offset = get_option('gmt_offset');
        $offset_formatted = ($offset >= 0 ? '+' : '') . sprintf('%02d:00', abs($offset));
        $timezone_label = '(GMT' . $offset_formatted . ')';
    } else {
        $timezone_obj = new DateTimeZone($timezone);
        $datetime = new DateTime('now', $timezone_obj);
        $offset = $timezone_obj->getOffset($datetime) / 3600;
        $offset_formatted = ($offset >= 0 ? '+' : '') . sprintf('%02d:00', abs($offset));
        $timezone_parts = explode('/', $timezone);
        $region = str_replace('_', ' ', end($timezone_parts));
        $timezone_label = '(GMT' . $offset_formatted . ') ' . $region;
    }
}
?>

<!-- Main Scheduling Wrapper -->
<div class="appointment-page-wrapper">

    <!-- 1. Top Content Section (Matches Screenshot 2) -->
    <div class="appointment-content-header">
        <!-- Logo & Title Row -->
        <div class="header-top-row">
            <?php if (!empty($thankyou_data['logo_url'])): ?>
                <div class="header-logo">
                     <img src="<?php echo esc_url($thankyou_data['logo_url']); ?>" alt="Logo">
                </div>
            <?php else: ?>
                <div class="header-avatar">B</div>
            <?php endif; ?>
            
            <h4 class="company-name"><?php echo esc_html($sidebar_data['title']); ?></h4>
        </div>

        <h1 class="page-title"><?php echo esc_html($sidebar_data['subtitle']); ?></h1>

        <!-- Info & Description Row -->
        <div class="header-info-row">
            <!-- Left: Meta Info -->
            <div class="header-meta">
                <div class="meta-item">
                    <span class="dashicons dashicons-clock"></span>
                    <span><?php echo esc_html($sidebar_data['duration']); ?></span>
                </div>
                <div class="meta-item" style="align-items: center;">
                    <img src="https://fonts.gstatic.com/s/i/productlogos/meet_2020q4/v6/web-512dp/logo_meet_2020q4_color_2x_web_512dp.png" alt="Google Meet" style="width:20px;height:20px; margin-right:8px; vertical-align:middle;">
                    <span><?php echo esc_html($sidebar_data['location']); ?></span>
                </div>
            </div>
            
            <!-- Right: Description -->
            <div class="header-description">
                <p><?php echo nl2br(esc_html($sidebar_data['description'])); ?></p>
            </div>
        </div>
    </div>
    
    <div class="appointment-divider"></div>
    
    <!-- 2. Scheduler Content (Calendar + Time Slots) -->
    <div class="appointment-main-content">
        
        <!-- A. Scheduler View -->
        <div id="scheduler-view" class="appointment-scheduler-wrapper">
            <div class="appointment-scheduler-header">
                <h2 class="appointment-title">Select an appointment time</h2>
                <div class="appointment-timezone">(<?php echo esc_html($timezone_label); ?>)</div>
            </div>
            
            <div class="appointment-scheduler-container">
                <!-- Calendar Section -->
                <div class="appointment-calendar-section">
                    <div class="calendar-header">
                        <button class="calendar-nav-btn prev-month" aria-label="Previous month">‹</button>
                        <h3 class="calendar-month-year"></h3>
                        <button class="calendar-nav-btn next-month" aria-label="Next month">›</button>
                    </div>
                    <div class="calendar-days-header">
                        <span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span><span>S</span>
                    </div>
                    <div class="calendar-grid"></div>
                </div>
                
                <!-- Time Slots Section (Multi-Day View) -->
                <!-- Time Slots Section (Multi-Day View) -->
                <div class="appointment-times-section">
                    <div class="slots-nav-container" style="display:flex; justify-content:flex-end; margin-bottom:10px;">
                        <button id="slots-prev" type="button" class="nav-arrow" style="border:none; background:transparent; font-size:24px; cursor:pointer; color:#5f6368;" title="Previous Days">&lt;</button>
                        <button id="slots-next" type="button" class="nav-arrow" style="border:none; background:transparent; font-size:24px; cursor:pointer; color:#5f6368;" title="Next Days">&gt;</button>
                    </div>
                    <div id="time-slots-grid" class="time-slots-grid-3-cols">
                        <!-- JS will populate 3 columns here: e.g. Wed 21, Thu 22, Fri 23 -->
                        <div class="slots-loading">Select a date to see availability</div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- Booking Form Modal (Updated ID to not conflict?) No, keep it same but ensure z-index is correct -->
    <div class="appointment-modal" id="appointmentModal">
        <div class="appointment-modal-content">
            <span class="appointment-modal-close">&times;</span>
            <div id="formMessage" style="display:none; padding: 10px; margin-bottom: 20px; border-radius: 4px;"></div>
            <h3 id="modalTitle">Complete Your Booking</h3>
            <form id="appointmentForm" class="appointment-form">
                <input type="hidden" id="selectedDate" name="date" value="">
                <input type="hidden" id="selectedTime" name="time" value="">
                
                <div class="form-group">
                    <label for="appointmentName">Name <span class="required">*</span></label>
                    <input type="text" id="appointmentName" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="appointmentEmail">Email <span class="required">*</span></label>
                    <input type="email" id="appointmentEmail" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="appointmentPhone">Phone</label>
                    <input type="tel" id="appointmentPhone" name="phone">
                </div>
                
                <div class="form-group">
                    <label for="appointmentGuests">Guest Emails (Optional)</label>
                    <input type="text" id="appointmentGuests" name="guest_emails" placeholder="e.g. guest1@example.com, guest2@example.com">
                    <small>Separate multiple emails with commas</small>
                </div>
                
                <div class="form-group">
                    <label for="appointmentMessage">Message</label>
                    <textarea id="appointmentMessage" name="message" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-submit">Confirm Booking</button>
                    <button type="button" class="btn-cancel" id="cancelBooking">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
</div>
