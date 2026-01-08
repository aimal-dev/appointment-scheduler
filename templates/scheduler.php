<?php
/**
 * Appointment Scheduler Template
 */

// Get timezone from plugin settings or WordPress settings
$appointment_timezone = get_option('appointment_timezone', 'Europe/London');
if (!empty($appointment_timezone)) {
    // Use plugin setting
    $timezone_obj = new DateTimeZone($appointment_timezone);
    $datetime = new DateTime('now', $timezone_obj);
    $offset = $timezone_obj->getOffset($datetime) / 3600;
    
    // Format timezone label
    $offset_formatted = ($offset >= 0 ? '+' : '') . sprintf('%02d:00', abs($offset));
    $timezone_parts = explode('/', $appointment_timezone);
    $region = str_replace('_', ' ', end($timezone_parts));
    
    $timezone_label = '(GMT' . $offset_formatted . ') ' . $region;
} else {
    // Fallback to WordPress timezone
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

<div class="appointment-scheduler-wrapper">
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
                <span>M</span>
                <span>T</span>
                <span>W</span>
                <span>T</span>
                <span>F</span>
                <span>S</span>
                <span>S</span>
            </div>
            <div class="calendar-grid"></div>
        </div>
        
        <!-- Time Slots Section -->
        <div class="appointment-times-section">
            <div class="times-header">
                <button class="times-nav-btn prev-days" aria-label="Previous days">‹</button>
                <div class="times-days-container"></div>
                <button class="times-nav-btn next-days" aria-label="Next days">›</button>
            </div>
            <div class="times-slots-container"></div>
        </div>
    </div>
    
    <!-- Booking Form Modal -->
    <div class="appointment-modal" id="appointmentModal">
        <div class="appointment-modal-content">
            <span class="appointment-modal-close">&times;</span>
            <h3>Complete Your Booking</h3>
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
                    <input type="text" id="appointmentGuests" name="guest_emails" placeholder="Separate multiple emails with commas">
                    <small class="form-text">Add colleagues to this meeting invitation.</small>
                </div>
                
                <div class="form-group">
                    <label for="appointmentMessage">Message (Optional)</label>
                    <textarea id="appointmentMessage" name="message" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <div class="selected-appointment-info">
                        <strong>Selected:</strong> <span id="selectedAppointmentDisplay"></span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-submit">Book Appointment</button>
                </div>
                
                <div class="form-message" id="formMessage"></div>
            </form>
        </div>
    </div>
</div>

