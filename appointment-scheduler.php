<?php
/**
 * Plugin Name: Appointment Scheduler
 * Plugin URI: https://example.com/appointment-scheduler
 * Description: A beautiful appointment scheduling system with calendar and time slot selection. Sends email notifications to admin.
 * Version: 1.5.0
 * Author: M.Aimal
 * Author URI: https://example.com
 * License: GPL v2 or later
 * Text Domain: appointment-scheduler
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('APPOINTMENT_SCHEDULER_VERSION', '1.3.0');
define('APPOINTMENT_SCHEDULER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('APPOINTMENT_SCHEDULER_PLUGIN_URL', plugin_dir_url(__FILE__));

class Appointment_Scheduler
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'redirect_non_allowed_admins'));
        add_action('wp_ajax_submit_appointment', array($this, 'handle_appointment_submission'));
        add_action('wp_ajax_nopriv_submit_appointment', array($this, 'handle_appointment_submission'));
        add_action('wp_ajax_modify_appointment', array($this, 'handle_appointment_modification'));
        add_action('wp_ajax_nopriv_modify_appointment', array($this, 'handle_appointment_modification'));
        add_action('wp_ajax_get_time_slots', array($this, 'get_time_slots'));
        add_action('wp_ajax_nopriv_get_time_slots', array($this, 'get_time_slots'));
        add_action('wp_ajax_get_booked_dates', array($this, 'get_booked_dates'));
        add_action('wp_ajax_nopriv_get_booked_dates', array($this, 'get_booked_dates'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_delete_appointment', array($this, 'delete_appointment'));
        add_action('wp_ajax_update_appointment', array($this, 'update_appointment'));
        add_action('wp_ajax_add_to_google_calendar', array($this, 'generate_google_calendar_link'));
        add_action('wp_ajax_update_meet_link', array($this, 'update_meet_link'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('appointment_scheduler_send_reminders', array($this, 'send_appointment_reminders'));
        add_action('wp', array($this, 'schedule_reminder_cron'));
        add_action('admin_init', array($this, 'handle_google_oauth_callback'));
        add_action('wp_ajax_google_calendar_auth', array($this, 'initiate_google_oauth'));
        add_action('wp_ajax_get_multi_day_slots', array($this, 'get_multi_day_slots'));
        add_action('wp_ajax_nopriv_get_multi_day_slots', array($this, 'get_multi_day_slots'));
        add_action('wp_ajax_confirm_cancellation', array($this, 'ajax_confirm_cancellation'));
    }

    public function init()
    {
        // Register shortcodes
        add_shortcode('appointment_scheduler', array($this, 'render_scheduler'));

        // Handle cancellation
        if (isset($_GET['appointment_action']) && $_GET['appointment_action'] === 'cancel') {
            $this->handle_cancellation();
        }
    }

    /**
     * Helper to check if current user is an administrator
     */
    private function is_authorized_admin()
    {
        return current_user_can('manage_options');
    }

    /**
     * Redirect unauthorized admins trying to access plugin pages
     */
    public function redirect_non_allowed_admins()
    {
        // We will no longer die() here, so other admins can still view the pages.
        // Modification rights are already restricted in the AJAX handlers and UI buttons.
        return;
    }

    public function handle_cancellation()
    {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        if (!$this->is_authorized_admin() && empty($token)) {
            wp_die('Unauthorized action.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';

        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND cancellation_token = %s",
            $id,
            $token
        ));

        if (!$appointment) {
            wp_die('Invalid cancellation token or appointment not found.');
        }

        if ($appointment->status === 'cancelled') {
            wp_die('This appointment has already been cancelled.');
        }

        if ($appointment->status === 'cancellation_requested') {
            wp_die('A cancellation request for this appointment has already been sent to the administrator.', 'Request Already Sent');
        }

        // Update status to 'cancellation_requested'
        $wpdb->update(
            $table_name,
            array('status' => 'cancellation_requested'),
            array('id' => $id),
            array('%s'),
            array('%d')
        );

        // Send cancellation request email to admin
        $this->send_cancellation_request_email($appointment);

        // Show success message
        wp_die('Your cancellation request has been sent to the administrator. They will process your request shortly.', 'Cancellation Request Sent');
    }

    private function send_cancellation_request_email($appointment)
    {
        $date_formatted = date('F j, Y', strtotime($appointment->appointment_date));
        $time_formatted = date('g:i A', strtotime($appointment->appointment_time));
        
        $subject = sprintf('Cancellation Request: %s - %s', $appointment->name, $date_formatted);
        $blog_name = get_bloginfo('name');
        $admin_email = get_option('appointment_admin_email', get_option('admin_email'));
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            "From: $blog_name <$admin_email>"
        );
        
        $admin_body = "Cancellation Request Received\n\n";
        $admin_body .= "The following user has requested to cancel their appointment:\n\n";
        $admin_body .= "Name: {$appointment->name}\n";
        $admin_body .= "Email: {$appointment->email}\n";
        $admin_body .= "Date: $date_formatted at $time_formatted\n\n";
        $admin_body .= "Action Required: Please log in to your dashboard to confirm or deny this cancellation.\n";
        $admin_body .= admin_url('admin.php?page=appointment-scheduler') . "\n\n";
        $admin_body .= "---\nAppointment Scheduler";
        
        wp_mail($admin_email, $subject, $admin_body, $headers);
    }

    private function send_cancellation_emails($appointment)
    {
        $date_formatted = date('F j, Y', strtotime($appointment->appointment_date));
        $time_formatted = date('g:i A', strtotime($appointment->appointment_time));

        $subject = sprintf('Appointment Cancelled - %s', $date_formatted);
        $blog_name = get_bloginfo('name');
        $admin_email = get_option('appointment_admin_email', get_option('admin_email'));

        // Headers with specific "From" to avoid "Unknown"
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            "From: $blog_name <$admin_email>"
        );

        // 1. Email to User
        $user_body = "Dear {$appointment->name},\n\n";
        $user_body .= "Your appointment scheduled for $date_formatted at $time_formatted has been cancelled successfully.\n\n";
        $user_body .= "If you did not initiate this cancellation, please contact us immediately.\n\n";
        $user_body .= "---\n$blog_name";

        wp_mail($appointment->email, $subject, $user_body, $headers);

        // 2. Email to Admin
        $admin_body = "Appointment Cancelled\n\n";
        $admin_body .= "The following appointment has been cancelled by the user:\n\n";
        $admin_body .= "Name: {$appointment->name}\n";
        $admin_body .= "Email: {$appointment->email}\n";
        $admin_body .= "Date: $date_formatted at $time_formatted\n\n";
        $admin_body .= "---\nAppointment Scheduler";

        wp_mail($admin_email, $subject, $admin_body, $headers);

        // 3. Email to Additional Emails
        $additional_emails = get_option('appointment_additional_email', '');
        if (!empty($additional_emails)) {
            $email_array = array_map('trim', explode(',', $additional_emails));
            foreach ($email_array as $additional_email) {
                if (is_email($additional_email)) {
                    wp_mail($additional_email, $subject, $admin_body, $headers);
                }
            }
        }

        // 4. Email to Guests
        if (!empty($appointment->guest_emails)) {
            $guest_email_array = array_map('trim', explode(',', $appointment->guest_emails));
            foreach ($guest_email_array as $guest_email) {
                if (is_email($guest_email)) {
                    wp_mail($guest_email, $subject, $user_body, $headers);
                }
            }
        }
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style(
            'appointment-scheduler-style',
            APPOINTMENT_SCHEDULER_PLUGIN_URL . 'assets/css/style.css',
            array(),
            APPOINTMENT_SCHEDULER_VERSION
        );

        // Enqueue Bolt+ Thank You styles (since it's now part of the main scheduler view)
        wp_enqueue_style(
            'appointment-thankyou-bolt-style',
            APPOINTMENT_SCHEDULER_PLUGIN_URL . 'assets/css/thankyou-bolt.css',
            array(),
            APPOINTMENT_SCHEDULER_VERSION
        );

        wp_enqueue_script(
            'appointment-scheduler-script',
            APPOINTMENT_SCHEDULER_PLUGIN_URL . 'assets/js/script.js',
            array('jquery'),
            APPOINTMENT_SCHEDULER_VERSION,
            true
        );

        wp_localize_script('appointment-scheduler-script', 'appointmentScheduler', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('appointment_scheduler_nonce'),
            'timezone' => $this->get_timezone_string(),
            'interval' => get_option('appointment_interval', 30),
            'thankyou_url' => get_option('appointment_thankyou_url', ''),
            'enable_weekends' => get_option('appointment_enable_weekends', 'no')
        ));
    }

    public function get_timezone_string()
    {
        // Get timezone from plugin settings first
        $appointment_timezone = get_option('appointment_timezone', '');
        if (!empty($appointment_timezone)) {
            return $appointment_timezone;
        }

        // Fallback to WordPress timezone
        $timezone = get_option('timezone_string');
        if (empty($timezone)) {
            $offset = get_option('gmt_offset');
            $timezone = 'UTC' . ($offset >= 0 ? '+' : '') . $offset;
        }
        return $timezone;
    }

    public function render_scheduler($atts)
    {
        $atts = shortcode_atts(array(
            'email' => get_option('admin_email'),
        ), $atts);

        // Check for modification mode
        $modify_data = null;
        if (isset($_GET['appointment_action']) && $_GET['appointment_action'] === 'modify') {
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

            if ($id && $token) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'appointment_bookings';
                $appointment = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE id = %d AND cancellation_token = %s AND status != 'cancelled'",
                    $id,
                    $token
                ));

                if ($appointment) {
                    $modify_data = array(
                        'id' => $appointment->id,
                        'token' => $appointment->cancellation_token,
                        'name' => $appointment->name,
                        'email' => $appointment->email,
                        'phone' => $appointment->phone,
                        'message' => $appointment->message,
                        'guest_emails' => $appointment->guest_emails,
                        'date' => $appointment->appointment_date,
                        'time' => $appointment->appointment_time
                    );
                    
                    // Pass to JS
                    wp_add_inline_script('appointment-scheduler-script', 'var appointmentModifyData = ' . json_encode($modify_data) . ';', 'before');
                }
            }
        }

        // Fetch values from settings instead of Custom Post Type meta
        $sidebar_data = array(
            'title' => get_option('appointment_sidebar_title', 'BoltOS Demo'),
            'subtitle' => get_option('appointment_sidebar_subtitle', 'Turn viewers into revenue'),
            'duration' => get_option('appointment_sidebar_duration', '30 min appointments'),
            'location' => get_option('appointment_sidebar_location', 'Google Meet video conference info added after booking'),
            'description' => get_option('appointment_sidebar_description', 'Our sales experts have helped thousands of companies growing revenue within weeks of implementation.')
        );

        // Prepare Timezone Label
        $timezone_label = $this->get_timezone_string();

        ob_start();
        include APPOINTMENT_SCHEDULER_PLUGIN_DIR . 'templates/scheduler.php';
        return ob_get_clean();
    }

    public function register_settings()
    {
        register_setting('appointment_scheduler_settings', 'appointment_start_time');
        register_setting('appointment_scheduler_settings', 'appointment_end_time');
        register_setting('appointment_scheduler_settings', 'appointment_interval');
        register_setting('appointment_scheduler_settings', 'appointment_admin_email');
        register_setting('appointment_scheduler_settings', 'appointment_additional_email');
        register_setting('appointment_scheduler_settings', 'appointment_timezone');
        register_setting('appointment_scheduler_settings', 'appointment_blocked_times');
        register_setting('appointment_scheduler_settings', 'appointment_reminder_enabled');
        register_setting('appointment_scheduler_settings', 'appointment_reminder_times');
        register_setting('appointment_scheduler_settings', 'appointment_enable_weekends');

        // Google Calendar Settings
        register_setting('appointment_scheduler_settings', 'google_calendar_client_id');
        register_setting('appointment_scheduler_settings', 'google_calendar_client_secret');
        register_setting('appointment_scheduler_settings', 'google_calendar_access_token');
        register_setting('appointment_scheduler_settings', 'google_calendar_refresh_token');
        register_setting('appointment_scheduler_settings', 'google_calendar_enabled');

        // Sidebar Content Settings
        register_setting('appointment_scheduler_settings', 'appointment_sidebar_title');
        register_setting('appointment_scheduler_settings', 'appointment_sidebar_subtitle');
        register_setting('appointment_scheduler_settings', 'appointment_sidebar_duration');
        register_setting('appointment_scheduler_settings', 'appointment_sidebar_location');
        register_setting('appointment_scheduler_settings', 'appointment_sidebar_description');

        // Redirect Settings
        register_setting('appointment_scheduler_settings', 'appointment_thankyou_url');
    }


    public function get_time_slots()
    {
        check_ajax_referer('appointment_scheduler_nonce', 'nonce');

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $selected_date = isset($_POST['selected_date']) ? sanitize_text_field($_POST['selected_date']) : '';

        if (empty($date)) {
            wp_send_json_error(array('message' => 'Date is required'));
        }

        $time_slots = $this->generate_time_slots($date);
        $is_date_booked = $this->is_date_booked($date);

        wp_send_json_success(array(
            'date' => $date,
            'time_slots' => $time_slots,
            'selected_date' => $selected_date,
            'is_date_booked' => $is_date_booked
        ));
    }

    private function generate_time_slots($date)
    {
        // Get working hours from settings
        $start_time = get_option('appointment_start_time', '10:00');
        $end_time = get_option('appointment_end_time', '17:30');
        $interval = get_option('appointment_interval', 30); // minutes

        $slots = array();
        $start = strtotime($date . ' ' . $start_time);
        $end = strtotime($date . ' ' . $end_time);

        $current = $start;
        while ($current <= $end) {
            $time_str = date('g:ia', $current);
            $time_value = date('H:i', $current);

            // detailed status check
            $status = $this->check_slot_status($date, $time_value);

            $slots[] = array(
                'time' => $time_str,
                'value' => $time_value,
                'available' => ($status === 'available'),
                'status' => $status
            );

            $current = strtotime('+' . $interval . ' minutes', $current);
        }

        return $slots;
    }

    private function check_slot_status($date, $time)
    {
        global $wpdb;

        // Check if weekend (Weekdays only requirement)
        $day_of_week = date('N', strtotime($date)); // 1 (Mon) to 7 (Sun)
        $enable_weekends = get_option('appointment_enable_weekends', 'no');
        
        if ($day_of_week > 5 && $enable_weekends !== 'yes') {
            return 'closed'; // Mark as office closed
        }

        // Check if date/time is in the past
        if (strtotime($date . ' ' . $time) < current_time('timestamp')) {
            return 'past';
        }

        // Check database
        $table_name = $wpdb->prefix . 'appointment_bookings';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE appointment_date = %s AND appointment_time = %s AND status != 'cancelled'",
            $date,
            $time
        ));

        if ($existing > 0) {
            return 'booked';
        }

        // Buffer Logic REMOVED for efficiency.

        // Check blocked times
        $blocked_times = get_option('appointment_blocked_times', array());
        foreach ($blocked_times as $blocked) {
            if ($blocked['date'] === $date && $blocked['time'] === $time) {
                return 'booked';
            }
        }

        return 'available';
    }

    private function is_slot_available($date, $time)
    {
        // Wrapper for legacy compatibility if needed
        return $this->check_slot_status($date, $time) === 'available';
    }

    private function is_date_booked($date)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';

        // Check if this date has any appointments
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE appointment_date = %s",
            $date
        ));

        return $count > 0;
    }

    public function get_booked_dates()
    {
        check_ajax_referer('appointment_scheduler_nonce', 'nonce');

        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
        $month = isset($_POST['month']) ? intval($_POST['month']) : date('m');

        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';

        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));

        // Capacity Logic
        $start_time_setting = get_option('appointment_start_time', '10:00');
        $end_time_setting = get_option('appointment_end_time', '17:30');
        $interval = intval(get_option('appointment_interval', 30));

        $start_ts = strtotime("2000-01-01 $start_time_setting");
        $end_ts = strtotime("2000-01-01 $end_time_setting");
        $total_minutes = ($end_ts - $start_ts) / 60;
        $total_slots = floor($total_minutes / $interval);

        // Get booking counts per day
        $daily_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT appointment_date, COUNT(*) as count FROM $table_name 
             WHERE appointment_date >= %s AND appointment_date <= %s AND status != 'cancelled'
             GROUP BY appointment_date",
            $start_date,
            $end_date
        ));

        $fully_booked_dates = array();
        foreach ($daily_counts as $row) {
            // Only mark as booked if ALL slots are taken
            if (intval($row->count) >= $total_slots) {
                $fully_booked_dates[] = $row->appointment_date;
            }
        }

        // Add weekends to booked dates to grey them out in calendar
        $current_ts = strtotime($start_date);
        $end_ts = strtotime($end_date);
        while ($current_ts <= $end_ts) {
            $day_n = date('N', $current_ts);
            if ($day_n > 5) { // 6=Sat, 7=Sun
                $date_str = date('Y-m-d', $current_ts);
                if (!in_array($date_str, $fully_booked_dates)) {
                    $fully_booked_dates[] = $date_str;
                }
            }
            $current_ts = strtotime('+1 day', $current_ts);
        }

        wp_send_json_success(array(
            'booked_dates' => $fully_booked_dates
        ));
    }

    public function handle_appointment_submission()
    {
        check_ajax_referer('appointment_scheduler_nonce', 'nonce');

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $guest_emails = isset($_POST['guest_emails']) ? sanitize_text_field($_POST['guest_emails']) : '';

        if (empty($name) || empty($email) || empty($date) || empty($time)) {
            wp_send_json_error(array('message' => 'Please fill in all required fields.'));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        }

        if ($this->check_slot_status($date, $time) !== 'available') {
            wp_send_json_error(array('message' => 'This time slot is no longer available.'));
        }

        $cancellation_token = bin2hex(random_bytes(16));
        $appointment_id = $this->save_appointment($name, $email, $phone, $date, $time, $message, $guest_emails, $cancellation_token);

        if (!$appointment_id) {
            wp_send_json_error(array('message' => 'Error saving appointment.'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';
        $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $appointment_id));

        // Create Google Calendar event if enabled
        if (get_option('google_calendar_enabled', 'no') === 'yes' && $appointment) {
            $this->create_google_calendar_event_api($appointment);
            // Refresh object after API update (it might have updated meet_link)
            $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $appointment_id));
        }

        // Send Notifications
        if ($appointment) {
            $this->send_appointment_notifications($appointment);
        }

        wp_send_json_success(array(
            'message' => 'Appointment scheduled successfully!',
            'appointment_id' => $appointment_id
        ));
    }

    public function handle_appointment_modification()
    {
        check_ajax_referer('appointment_scheduler_nonce', 'nonce');

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $guest_emails = isset($_POST['guest_emails']) ? sanitize_text_field($_POST['guest_emails']) : '';

        if (empty($id) || empty($token) || empty($date) || empty($time)) {
            wp_send_json_error(array('message' => 'Required fields are missing.'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';

        // Verify token
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND cancellation_token = %s AND status != 'cancelled'",
            $id,
            $token
        ));

        if (!$appointment) {
            wp_send_json_error(array('message' => 'Invalid or expired modification link.'));
        }

        // Check availability if date/time changed
        if ($date !== $appointment->appointment_date || $time !== $appointment->appointment_time) {
            if ($this->check_slot_status($date, $time) !== 'available') {
                wp_send_json_error(array('message' => 'The selected time slot is not available.'));
            }
        }

        // Update data
        $updated = $wpdb->update(
            $table_name,
            array(
                'appointment_date' => $date,
                'appointment_time' => $time,
                'phone' => $phone,
                'message' => $message,
                'guest_emails' => $guest_emails,
                'created_at' => current_time('mysql') // Update timestamp to show it was modified
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($updated !== false) {
            // Re-fetch to get updated object
            $fresh_appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
            
            // Sync with Google Calendar if enabled
            if (get_option('google_calendar_enabled', 'no') === 'yes') {
                $this->create_google_calendar_event_api($fresh_appointment);
                // Refresh again for meet_link
                $fresh_appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
            }

            // Send Notifications
            $this->send_appointment_notifications($fresh_appointment, true);

            wp_send_json_success(array(
                'message' => 'Appointment modified successfully! A confirmation email has been sent.',
                'appointment_id' => $id
            ));
        } else {
            wp_send_json_error(array('message' => 'No changes were made or update failed.'));
        }
    }
    private function save_appointment($name, $email, $phone, $date, $time, $message, $guest_emails = '', $token = '')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';

        // First insert without meet link
        $insert_id = $wpdb->insert(
            $table_name,
            array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'appointment_date' => $date,
            'appointment_time' => $time,
            'message' => $message,
            'guest_emails' => $guest_emails,
            'status' => 'booked',
            'cancellation_token' => $token,
            'created_at' => current_time('mysql')
        ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        // Generate meet link with actual ID for uniqueness
        if ($insert_id) {
            $appointment_id = $wpdb->insert_id;
            $appointment = (object)array(
                'id' => $appointment_id,
                'appointment_date' => $date,
                'appointment_time' => $time
            );
            $final_meet_link = $this->generate_meet_link($appointment);
            $wpdb->update(
                $table_name,
                array('meet_link' => $final_meet_link),
                array('id' => $appointment_id),
                array('%s'),
                array('%d')
            );
            return $appointment_id;
        }

        return false;
    }

    private function is_google_account($email)
    {
        // Check if email is a Google account (gmail.com, googlemail.com, or Google Workspace)
        $google_domains = array('gmail.com', 'googlemail.com');
        $email_domain = strtolower(substr(strrchr($email, "@"), 1));

        // Check for Gmail
        if (in_array($email_domain, $google_domains)) {
            return true;
        }

        // Check if it's a Google Workspace domain (ends with .gmail.com or has Google Workspace)
        // This is a basic check - for full detection, would need Google API
        return false;
    }

    public function add_admin_menu()
    {
        $capability = 'manage_options';

        // Restricted to administrators with 'manage_options' capability.

        add_menu_page(
            'Appointment Scheduler',
            'Appointments',
            'manage_options',
            'appointment-scheduler',
            array($this, 'render_admin_page'),
            'dashicons-calendar-alt',
            30
        );

        // Add "All Appointments" as first submenu item
        add_submenu_page(
            'appointment-scheduler',
            'All Appointments',
            'All Appointments',
            'manage_options',
            'appointment-scheduler',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'appointment-scheduler',
            'Settings',
            'Settings',
            'manage_options',
            'appointment-scheduler-settings',
            array($this, 'render_settings_page')
        );
    }

    public function schedule_reminder_cron()
    {
        if (!wp_next_scheduled('appointment_scheduler_send_reminders')) {
            wp_schedule_event(time(), 'hourly', 'appointment_scheduler_send_reminders');
        }
    }

    public function send_appointment_reminders()
    {
        $reminder_enabled = get_option('appointment_reminder_enabled', 'yes');
        if ($reminder_enabled !== 'yes') {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';
        $current_time = current_time('mysql');

        // Get appointments in next 24 hours
        $upcoming_appointments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE CONCAT(appointment_date, ' ', appointment_time) >= %s 
            AND CONCAT(appointment_date, ' ', appointment_time) <= DATE_ADD(%s, INTERVAL 24 HOUR)
            AND status = 'booked'
            ORDER BY appointment_date, appointment_time",
            $current_time,
            $current_time
        ));

        foreach ($upcoming_appointments as $appointment) {
            $appointment_datetime = strtotime($appointment->appointment_date . ' ' . $appointment->appointment_time);
            $current_timestamp = current_time('timestamp');
            $time_diff = $appointment_datetime - $current_timestamp;

            // Send reminder 15 minutes before
            if ($time_diff <= 900 && $time_diff > 0 && empty($appointment->reminder_sent_15min)) {
                $this->send_reminder_email($appointment, '15min');
                $wpdb->update(
                    $table_name,
                    array('reminder_sent_15min' => 1),
                    array('id' => $appointment->id),
                    array('%d'),
                    array('%d')
                );
            }

            // Send reminder 1 hour before
            if ($time_diff <= 3600 && $time_diff > 900 && empty($appointment->reminder_sent_1hr)) {
                $this->send_reminder_email($appointment, '1hr');
                $wpdb->update(
                    $table_name,
                    array('reminder_sent_1hr' => 1),
                    array('id' => $appointment->id),
                    array('%d'),
                    array('%d')
                );
            }

            // Send reminder 1 day before
            if ($time_diff <= 86400 && $time_diff > 3600 && empty($appointment->reminder_sent_1day)) {
                $this->send_reminder_email($appointment, '1day');
                $wpdb->update(
                    $table_name,
                    array('reminder_sent_1day' => 1),
                    array('id' => $appointment->id),
                    array('%d'),
                    array('%d')
                );
            }
        }
    }

    private function send_reminder_email($appointment, $reminder_type)
    {
        $reminder_times = get_option('appointment_reminder_times', array('15min', '1hr', '1day'));
        if (!in_array($reminder_type, $reminder_times)) {
            return;
        }

        $reminder_text = array(
            '15min' => '15 minutes',
            '1hr' => '1 hour',
            '1day' => '1 day'
        );

        $date_formatted = date('F j, Y', strtotime($appointment->appointment_date));
        $time_formatted = date('g:i A', strtotime($appointment->appointment_time));
        $meet_link = !empty($appointment->meet_link) ? $appointment->meet_link : $this->generate_meet_link($appointment);

        $subject = sprintf('Reminder: Your appointment is in %s - %s', $reminder_text[$reminder_type], $date_formatted);

        $email_body = "Dear {$appointment->name},\n\n";
        $email_body .= "This is a reminder that you have an appointment scheduled:\n\n";
        $email_body .= "Date: $date_formatted\n";
        $email_body .= "Time: $time_formatted\n";

        if ($meet_link) {
            $email_body .= "Google Meet Link: $meet_link\n\n";
        }

        $email_body .= "We look forward to meeting with you!\n\n";
        $email_body .= "---\n";
        $email_body .= "This is an automated reminder from Appointment Scheduler.";

        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($appointment->email, $subject, $email_body, $headers);

        // Also send to admin
        $admin_email = get_option('appointment_admin_email', get_option('admin_email'));
        wp_mail($admin_email, $subject, $email_body, $headers);
    }

    public function generate_google_calendar_link()
    {
        check_ajax_referer('appointment_admin_nonce', 'nonce');

        if (!$this->is_authorized_admin()) {
            wp_send_json_error(array('message' => 'You do not have permission. Only administrators can manage events.'));
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;

        if (empty($appointment_id)) {
            wp_send_json_error(array('message' => 'Invalid appointment ID.'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $appointment_id
        ));

        if (!$appointment) {
            wp_send_json_error(array('message' => 'Appointment not found.'));
        }

        // Get admin email for display
        $admin_email = get_option('appointment_admin_email', get_option('admin_email'));

        // Create calendar link for admin (admin will be the organizer)
        $calendar_link = $this->create_google_calendar_link($appointment, true);

        wp_send_json_success(array(
            'calendar_link' => $calendar_link,
            'meet_link' => !empty($appointment->meet_link) ? $appointment->meet_link : $this->generate_meet_link($appointment),
            'admin_email' => $admin_email,
            'message' => 'Google Calendar will open. Please make sure you are logged in with your admin Google account. The event will be added to your calendar.'
        ));
    }

    public function update_meet_link()
    {
        check_ajax_referer('appointment_admin_nonce', 'nonce');

        if (!$this->is_authorized_admin()) {
            wp_send_json_error(array('message' => 'You do not have permission to update meet links. Only administrators can modify events.'));
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        $new_meet_link = isset($_POST['meet_link']) ? esc_url_raw($_POST['meet_link']) : '';

        if (empty($appointment_id)) {
            wp_send_json_error(array('message' => 'Invalid appointment ID.'));
        }

        if (empty($new_meet_link)) {
            wp_send_json_error(array('message' => 'Meet link cannot be empty.'));
        }

        // Validate it's a Google Meet link
        if (strpos($new_meet_link, 'meet.google.com') === false) {
            wp_send_json_error(array('message' => 'Please enter a valid Google Meet link.'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';

        // Get current appointment
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $appointment_id
        ));

        if (!$appointment) {
            wp_send_json_error(array('message' => 'Appointment not found.'));
        }

        $old_meet_link = $appointment->meet_link;

        // Update meet link
        $updated = $wpdb->update(
            $table_name,
            array('meet_link' => $new_meet_link),
            array('id' => $appointment_id),
            array('%s'),
            array('%d')
        );

        if ($updated !== false) {
            // Send notification emails about link change
            $this->send_meet_link_change_notification($appointment, $old_meet_link, $new_meet_link);

            wp_send_json_success(array(
                'message' => 'Meet link updated successfully. Notification emails have been sent.',
                'new_link' => $new_meet_link
            ));
        }
        else {
            wp_send_json_error(array('message' => 'Failed to update meet link.'));
        }
    }

    private function send_meet_link_change_notification($appointment, $old_link, $new_link)
    {
        $date_formatted = date('F j, Y', strtotime($appointment->appointment_date));
        $time_formatted = date('g:i A', strtotime($appointment->appointment_time));

        $subject = sprintf('Google Meet Link Updated - Appointment on %s', $date_formatted);

        $email_body = "Dear {$appointment->name},\n\n";
        $email_body .= "The Google Meet link for your appointment has been updated.\n\n";
        $email_body .= "Appointment Details:\n";
        $email_body .= "Date: $date_formatted\n";
        $email_body .= "Time: $time_formatted\n\n";
        $email_body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $email_body .= "NEW GOOGLE MEET LINK:\n";
        $email_body .= "$new_link\n";
        $email_body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        if ($old_link) {
            $email_body .= "Previous link: $old_link\n\n";
        }

        $email_body .= "Please use the new link above to join the meeting.\n\n";
        $email_body .= "If you have any questions, please contact us.\n\n";
        $email_body .= "---\n";
        $email_body .= "This is an automated notification from Appointment Scheduler.";

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        // Send to user
        wp_mail($appointment->email, $subject, $email_body, $headers);

        // Send to admin
        $admin_email = get_option('appointment_admin_email', get_option('admin_email'));
        wp_mail($admin_email, $subject, $email_body, $headers);

        // Send to additional emails
        $additional_emails = get_option('appointment_additional_email', '');
        if (!empty($additional_emails)) {
            $email_array = array_map('trim', explode(',', $additional_emails));
            foreach ($email_array as $additional_email) {
                if (is_email($additional_email)) {
                    wp_mail($additional_email, $subject, $email_body, $headers);
                }
            }
        }

        // Send to guest emails
        if (!empty($appointment->guest_emails)) {
            $guest_email_array = array_map('trim', explode(',', $appointment->guest_emails));
            foreach ($guest_email_array as $guest_email) {
                if (is_email($guest_email)) {
                    wp_mail($guest_email, $subject, $email_body, $headers);
                }
            }
        }
    }

    private function create_google_calendar_link($appointment, $for_admin = false)
    {
        $start_date = strtotime($appointment->appointment_date . ' ' . $appointment->appointment_time);
        $end_date = $start_date + 3600; // 1 hour duration

        $start_iso = gmdate('Ymd\THis\Z', $start_date);
        $end_iso = gmdate('Ymd\THis\Z', $end_date);

        $title = urlencode('Appointment with ' . $appointment->name);
        $meet_link = !empty($appointment->meet_link) ? $appointment->meet_link : $this->generate_meet_link($appointment);

        // Get admin email
        $admin_email = get_option('appointment_admin_email', get_option('admin_email'));

        // Create detailed description with Meet link prominently displayed
        $details = "Appointment Details:\n\n";
        $details .= "Name: {$appointment->name}\n";
        $details .= "Email: {$appointment->email}\n";
        $details .= "Phone: " . ($appointment->phone ? $appointment->phone : 'N/A') . "\n\n";
        $details .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $details .= "🔗 JOIN GOOGLE MEET:\n";
        $details .= "$meet_link\n";
        $details .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $details .= "Click the link above to join the meeting at the scheduled time.\n\n";

        // Add cancellation link only for the administrator/organizer
        if ($for_admin) {
            $cancellation_token = $appointment->cancellation_token;
            if (empty($cancellation_token)) {
                // Fetch from database if missing (though it should be there)
                global $wpdb;
                $table_name = $wpdb->prefix . 'appointment_bookings';
                $cancellation_token = $wpdb->get_var($wpdb->prepare("SELECT cancellation_token FROM $table_name WHERE id = %d", $appointment->id));
            }
            $cancel_link = home_url("/?appointment_action=cancel&id={$appointment->id}&token=$cancellation_token");
            $details .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $details .= "❌ CANCEL APPOINTMENT:\n";
            $details .= "$cancel_link\n";
            $details .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        }

        $details_encoded = urlencode($details);
        // Put Meet link in location field so Google Calendar can recognize it
        $location = urlencode('Google Meet: ' . $meet_link);

        // Create Google Calendar URL
        $calendar_url = "https://calendar.google.com/calendar/render?action=TEMPLATE";
        $calendar_url .= "&text=$title";
        $calendar_url .= "&dates=$start_iso/$end_iso";
        $calendar_url .= "&details=$details_encoded";
        $calendar_url .= "&location=$location";

        // Add conference parameter to enable video conferencing
        // Note: Google Calendar URL doesn't directly support adding existing Meet links,
        // but putting it in location helps users see it immediately
        // For auto-adding Meet, we need to use Google Calendar API with OAuth

        // Add attendees - this ensures event appears in both calendars
        // When admin clicks, add customer as attendee (admin is organizer by default)
        // When customer clicks, add admin as attendee (customer is organizer)
        if ($for_admin) {
            // Admin is creating - add customer as attendee
            if (!empty($appointment->email)) {
                $calendar_url .= "&add=" . urlencode($appointment->email);
            }
        }
        else {
            // Customer is creating - add admin as attendee so admin gets notification
            if (!empty($admin_email)) {
                $calendar_url .= "&add=" . urlencode($admin_email);
            }
        }

        return $calendar_url;
    }

    private function generate_meet_link($appointment)
    {
        // Generate a unique meet link based on appointment
        $meet_id = 'appt-' . md5($appointment->id . $appointment->appointment_date . $appointment->appointment_time);
        $meet_id = substr($meet_id, 0, 12);

        // Format: https://meet.google.com/xxx-xxxx-xxx
        $formatted_id = substr($meet_id, 0, 3) . '-' . substr($meet_id, 3, 4) . '-' . substr($meet_id, 7, 3);

        return 'https://meet.google.com/' . $formatted_id;
    }

    public function handle_google_oauth_callback()
    {
        if (!$this->is_authorized_admin()) {
            return;
        }

        // Handle OAuth callback
        if (isset($_GET['page']) && $_GET['page'] === 'appointment-scheduler-settings' && isset($_GET['google_oauth_callback'])) {
            if (isset($_GET['code'])) {
                $code = sanitize_text_field($_GET['code']);
                $success = $this->exchange_oauth_code_for_tokens($code);

                // Redirect to clean URL to avoid "invalid_grant" on refresh
                $redirect_url = admin_url('admin.php?page=appointment-scheduler-settings');
                if ($success) {
                    $redirect_url = add_query_arg('google_connected', '1', $redirect_url);
                }
                wp_safe_redirect($redirect_url);
                exit;
            }
            elseif (isset($_GET['error'])) {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error"><p>Google OAuth error: ' . esc_html($_GET['error']) . '</p></div>';
                });
            }
        }

        // Show success notice if redirected
        if (isset($_GET['google_connected']) && $_GET['google_connected'] === '1') {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>Google Calendar connected successfully!</p></div>';
            });
        }

        // Handle OAuth initiation
        if (isset($_GET['page']) && $_GET['page'] === 'appointment-scheduler-settings' && isset($_GET['google_auth'])) {
            $this->initiate_google_oauth();
        }
    }

    public function initiate_google_oauth()
    {
        if (!$this->is_authorized_admin()) {
            wp_die('You do not have permission to initiate Google OAuth.');
        }

        $client_id = get_option('google_calendar_client_id', '');
        $redirect_uri = admin_url('admin.php?page=appointment-scheduler-settings&google_oauth_callback=1');

        if (empty($client_id)) {
            wp_die('Google Client ID is not configured. Please add it in settings first.');
        }

        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth';
        $params = array(
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent'
        );

        $auth_url .= '?' . http_build_query($params);
        wp_redirect($auth_url);
        exit;
    }

    private function exchange_oauth_code_for_tokens($code)
    {
        $client_id = get_option('google_calendar_client_id', '');
        $client_secret = get_option('google_calendar_client_secret', '');
        $redirect_uri = admin_url('admin.php?page=appointment-scheduler-settings&google_oauth_callback=1');

        $token_url = 'https://oauth2.googleapis.com/token';
        $response = wp_remote_post($token_url, array(
            'body' => array(
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code'
            )
        ));

        if (is_wp_error($response)) {
            add_action('admin_notices', function () use ($response) {
                echo '<div class="notice notice-error"><p>Error exchanging code: ' . esc_html($response->get_error_message()) . '</p></div>';
            });
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            update_option('google_calendar_access_token', $body['access_token']);
            if (isset($body['refresh_token'])) {
                update_option('google_calendar_refresh_token', $body['refresh_token']);
            }
            return true;
        }
        else {
            add_action('admin_notices', function () use ($body) {
                $error = isset($body['error']) ? $body['error'] : 'Unknown error';
                echo '<div class="notice notice-error"><p>Error: ' . esc_html($error) . '</p></div>';
            });
            return false;
        }
    }

    private function get_google_access_token()
    {
        $access_token = get_option('google_calendar_access_token', '');

        if (empty($access_token)) {
            return false;
        }

        return $access_token;
    }

    private function refresh_google_token()
    {
        $client_id = get_option('google_calendar_client_id', '');
        $client_secret = get_option('google_calendar_client_secret', '');
        $refresh_token = get_option('google_calendar_refresh_token', '');

        if (empty($refresh_token)) {
            return false;
        }

        $token_url = 'https://oauth2.googleapis.com/token';
        $response = wp_remote_post($token_url, array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            update_option('google_calendar_access_token', $body['access_token']);
            return $body['access_token'];
        }

        return false;
    }

    private function create_google_calendar_event_api($appointment)
    {
        $google_enabled = get_option('google_calendar_enabled', 'no');
        if ($google_enabled !== 'yes') {
            return false;
        }

        $access_token = $this->get_google_access_token();
        if (!$access_token) {
            return false;
        }

        $start_date = strtotime($appointment->appointment_date . ' ' . $appointment->appointment_time);
        $end_date = $start_date + 3600; // 1 hour duration

        $start_iso = date('c', $start_date);
        $end_iso = date('c', $end_date);

        $meet_link = !empty($appointment->meet_link) ? $appointment->meet_link : $this->generate_meet_link($appointment);
        $admin_email = get_option('appointment_admin_email', get_option('admin_email'));

        // Create event with Google Meet conference
        $event_data = array(
            'summary' => 'Appointment with ' . $appointment->name,
            'description' => "Appointment Details:\n\n" .
            "Name: {$appointment->name}\n" .
            "Email: {$appointment->email}\n" .
            "Phone: " . ($appointment->phone ? $appointment->phone : 'N/A') . "\n\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "🔗 JOIN GOOGLE MEET:\n" .
            "$meet_link\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            'start' => array(
                'dateTime' => $start_iso,
                'timeZone' => get_option('appointment_timezone', 'Europe/London')
            ),
            'end' => array(
                'dateTime' => $end_iso,
                'timeZone' => get_option('appointment_timezone', 'Europe/London')
            ),
            'location' => 'Google Meet: ' . $meet_link,
            'attendees' => array(
                    array('email' => $appointment->email),
                    array('email' => $admin_email)
            ),
            'conferenceData' => array(
                'createRequest' => array(
                    'requestId' => 'appointment-' . $appointment->id . '-' . time(),
                    'conferenceSolutionKey' => array(
                        'type' => 'hangoutsMeet'
                    )
                )
            ),
            'reminders' => array(
                'useDefault' => false,
                'overrides' => array(
                        array('method' => 'email', 'minutes' => 1440), // 1 day
                        array('method' => 'popup', 'minutes' => 15)
                )
            )
        );

        // Add guest emails to attendees
        if (!empty($appointment->guest_emails)) {
            $guest_emails = array_map('trim', explode(',', $appointment->guest_emails));
            foreach ($guest_emails as $guest_email) {
                if (is_email($guest_email)) {
                    $event_data['attendees'][] = array('email' => $guest_email);
                }
            }
        }

        // Add additional emails from settings to attendees
        $additional_emails = get_option('appointment_additional_email', '');
        if (!empty($additional_emails)) {
            $additional_email_array = array_map('trim', explode(',', $additional_emails));
            foreach ($additional_email_array as $additional_email) {
                if (is_email($additional_email)) {
                    $event_data['attendees'][] = array('email' => $additional_email);
                }
            }
        }

        $api_url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1&sendUpdates=all';
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($event_data),
            'timeout' => 30
        ));

        $status_code = wp_remote_retrieve_response_code($response);

        // If 401 Unauthorized, try refreshing token
        if ($status_code === 401 || is_wp_error($response)) {
            error_log("Appointment Scheduler: Access token expired or error. Attempting refresh...");
            $new_token = $this->refresh_google_token();
            if ($new_token) {
                error_log("Appointment Scheduler: Token refreshed. Retrying API call...");
                $api_url_retry = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1&sendUpdates=all';
                $response = wp_remote_post($api_url_retry, array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $new_token,
                        'Content-Type' => 'application/json'
                    ),
                    'body' => json_encode($event_data),
                    'timeout' => 30
                ));
                $status_code = wp_remote_retrieve_response_code($response);
            }
        }

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 200 || $status_code === 201) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            // Update meet_link with the one from Google Calendar if different
            if (isset($body['hangoutLink'])) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'appointment_bookings';
                $wpdb->update(
                    $table_name,
                    array('meet_link' => $body['hangoutLink']),
                    array('id' => $appointment->id),
                    array('%s'),
                    array('%d')
                );
                return $body['hangoutLink'];
            }
            return true;
        }

        return false;
    }

    public function enqueue_admin_scripts($hook)
    {
        if ($hook !== 'toplevel_page_appointment-scheduler') {
            return;
        }

        // Use file modification time for cache busting
        $admin_js_version = file_exists(APPOINTMENT_SCHEDULER_PLUGIN_DIR . 'assets/js/admin.js')
            ? filemtime(APPOINTMENT_SCHEDULER_PLUGIN_DIR . 'assets/js/admin.js')
            : APPOINTMENT_SCHEDULER_VERSION;

        wp_enqueue_script(
            'appointment-scheduler-admin',
            APPOINTMENT_SCHEDULER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            $admin_js_version,
            true
        );

        wp_localize_script('appointment-scheduler-admin', 'appointmentAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('appointment_admin_nonce'),
            'confirm_delete' => __('Are you sure you want to delete this appointment? This action cannot be undone.', 'appointment-scheduler')
        ));
    }

    public function update_appointment()
    {
        check_ajax_referer('appointment_admin_nonce', 'nonce');

        if (!$this->is_authorized_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized access.'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (empty($id)) {
            wp_send_json_error(array('message' => 'Invalid appointment ID.'));
        }

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $guest_emails = isset($_POST['guest_emails']) ? sanitize_textarea_field($_POST['guest_emails']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $meet_link = isset($_POST['meet_link']) ? esc_url_raw($_POST['meet_link']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';

        // Fetch old data to check for changes
        $old_appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        if (!$old_appointment) {
            wp_send_json_error(array('message' => 'Appointment not found.'));
        }

        $is_changed = (
            $old_appointment->appointment_date !== $date ||
            $old_appointment->appointment_time !== $time ||
            $old_appointment->meet_link !== $meet_link
            );

        $updated = $wpdb->update(
            $table_name,
            array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'guest_emails' => $guest_emails,
            'appointment_date' => $date,
            'appointment_time' => $time,
            'meet_link' => $meet_link,
            'message' => $message
        ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($updated !== false) {
            if ($is_changed) {
                // Fetch fresh data for notifications
                $fresh_appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
                $this->send_appointment_notifications($fresh_appointment, true);
            }
            wp_send_json_success(array('message' => 'Appointment updated successfully. ' . ($is_changed ? 'Notifications sent.' : '')));
        }
        else {
            wp_send_json_error(array('message' => 'Failed to update appointment or no changes made.'));
        }
    }

    public function ajax_confirm_cancellation()
    {
        check_ajax_referer('appointment_admin_nonce', 'nonce');

        if (!$this->is_authorized_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized action.'));
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;

        if (empty($appointment_id)) {
            wp_send_json_error(array('message' => 'Invalid appointment ID.'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';

        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $appointment_id
        ));

        if (!$appointment) {
            wp_send_json_error(array('message' => 'Appointment not found.'));
        }

        // Update status to 'cancelled'
        $wpdb->update(
            $table_name,
            array('status' => 'cancelled'),
            array('id' => $appointment_id),
            array('%s'),
            array('%d')
        );

        // Send final cancellation emails (already has the logic to notify user/guests/admin)
        $this->send_cancellation_emails($appointment);

        wp_send_json_success(array('message' => 'Appointment cancellation confirmed. Notifications sent.'));
    }

    public function delete_appointment()
    {
        check_ajax_referer('appointment_admin_nonce', 'nonce');

        if (!$this->is_authorized_admin()) {
            wp_send_json_error(array('message' => 'You do not have permission to delete appointments. Only administrators can perform this action.'));
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;

        if (empty($appointment_id)) {
            wp_send_json_error(array('message' => 'Invalid appointment ID.'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';

        $deleted = $wpdb->delete(
            $table_name,
            array('id' => $appointment_id),
            array('%d')
        );

        if ($deleted) {
            wp_send_json_success(array('message' => 'Appointment deleted successfully.'));
        }
        else {
            wp_send_json_error(array('message' => 'Failed to delete appointment.'));
        }
    }

    public function render_admin_page()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';
        $bookings = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

        include APPOINTMENT_SCHEDULER_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function render_settings_page()
    {
        include APPOINTMENT_SCHEDULER_PLUGIN_DIR . 'templates/settings-page.php';
    }

    public static function activate()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20),
            appointment_date date NOT NULL,
            appointment_time time NOT NULL,
            message text,
            guest_emails text,
            meet_link varchar(255),
            status varchar(20) DEFAULT 'booked',
            cancellation_token varchar(64),
            reminder_sent_15min tinyint(1) DEFAULT 0,
            reminder_sent_1hr tinyint(1) DEFAULT 0,
            reminder_sent_1day tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Add new columns if they don't exist (for existing installations)
        $columns = $wpdb->get_col("DESC $table_name");
        if (!in_array('meet_link', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN meet_link varchar(255) AFTER message");
        }
        if (!in_array('guest_emails', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN guest_emails text AFTER message");
        }
        if (!in_array('status', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN status varchar(20) DEFAULT 'booked' AFTER meet_link");
        }
        if (!in_array('cancellation_token', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN cancellation_token varchar(64) AFTER status");
        }
        if (!in_array('reminder_sent_15min', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN reminder_sent_15min tinyint(1) DEFAULT 0 AFTER cancellation_token");
        }
        if (!in_array('reminder_sent_1hr', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN reminder_sent_1hr tinyint(1) DEFAULT 0 AFTER reminder_sent_15min");
        }
        if (!in_array('reminder_sent_1day', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN reminder_sent_1day tinyint(1) DEFAULT 0 AFTER reminder_sent_1hr");
        }

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Set default options
        add_option('appointment_start_time', '10:00');
        add_option('appointment_end_time', '17:30');
        add_option('appointment_interval', '30');
        add_option('appointment_admin_email', get_option('admin_email'));
    }

    public function get_multi_day_slots()
    {
        check_ajax_referer('appointment_scheduler_nonce', 'nonce');

        $start_date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d');
        $days_count = isset($_POST['days']) ? intval($_POST['days']) : 3;

        $start_time_opt = get_option('appointment_start_time', '09:00');
        $end_time_opt = get_option('appointment_end_time', '17:00');
        $interval = intval(get_option('appointment_interval', 30));

        // Convert to minutes
        $start_minutes = strtotime("1970-01-01 $start_time_opt UTC");
        $end_minutes = strtotime("1970-01-01 $end_time_opt UTC");

        $result = array();

        for ($i = 0; $i < $days_count; $i++) {
            $current_date = date('Y-m-d', strtotime("$start_date +$i days"));
            $day_label = date('D j', strtotime($current_date)); // e.g., Wed 21

            $day_slots = array();
            $current_slot_time = $start_minutes;

            while ($current_slot_time < $end_minutes) {
                $time_str = date('H:i', $current_slot_time);
                $display_time = date('g:ia', $current_slot_time);

                $status = $this->check_slot_status($current_date, $time_str);

                $day_slots[] = array(
                    'time' => $time_str,
                    'display' => $display_time,
                    'status' => $status,
                    'booked' => ($status === 'booked'),
                    'past' => ($status === 'past')
                );

                $current_slot_time += ($interval * 60);
            }

            $result[] = array(
                'date' => $current_date,
                'label' => $day_label,
                'slots' => $day_slots
            );
        }

        wp_send_json_success($result);
    }

    /**
     * Send email notifications to all parties (Admin, User, Guests, Additional)
     */
    private function send_appointment_notifications($appointment, $is_update = false)
    {
        $id = $appointment->id;
        $name = $appointment->name;
        $email = $appointment->email;
        $phone = $appointment->phone;
        $date = $appointment->appointment_date;
        $time = $appointment->appointment_time;
        $message = $appointment->message;
        $guest_emails = $appointment->guest_emails;
        $meet_link = $appointment->meet_link;
        $cancellation_token = $appointment->cancellation_token;

        $date_formatted = date('F j, Y', strtotime($date));
        $time_formatted = date('g:i A', strtotime($time));

        $blog_name = get_bloginfo('name');
        $admin_email_setting = get_option('appointment_admin_email');
        $admin_email_final = !empty($admin_email_setting) ? trim($admin_email_setting) : get_option('admin_email');
        $additional_emails = get_option('appointment_additional_email', '');

        $url_parts = parse_url(home_url());
        $server_name = isset($url_parts['host']) ? $url_parts['host'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'your-site.com');
        if (substr($server_name, 0, 4) === 'www.') {
            $server_name = substr($server_name, 4);
        }
        $from_email = $admin_email_final;

        // Subject
        $subject_prefix = $is_update ? '[UPDATED] ' : '';
        $admin_subject = $subject_prefix . sprintf('Appointment: %s - %s', $name, $date_formatted);
        $user_subject = $subject_prefix . sprintf('Invitation: Appointment @ %s %s', $date_formatted, $time_formatted);

        // Headers
        $user_headers = array('Content-Type: text/html; charset=UTF-8', "From: $blog_name <$from_email>");
        if (is_email($admin_email_final)) {
            $user_headers[] = "Reply-To: $admin_email_final";
        }

        $admin_headers = array('Content-Type: text/html; charset=UTF-8', "From: $blog_name <$from_email>", "Reply-To: $name <$email>");

        // MODAL/BODY Generation (Reusing the beautiful style)
        $meet_btn = '';
        if ($meet_link) {
            $meet_btn = '<a href="' . $meet_link . '" style="display: inline-block; background-color: #1a73e8; color: white; padding: 10px 24px; text-decoration: none; border-radius: 4px; font-weight: 500; font-size: 14px;">Join with Google Meet</a>';
            $meet_btn .= '<div style="margin-top: 8px; font-size: 12px; color: #5f6368;">' . $meet_link . '</div>';
        }
        else {
            $meet_btn = '<span style="color: #d93025; font-style: italic;">Link not available</span>';
        }

        $common_style = 'font-family: Roboto, Arial, sans-serif; max-width: 600px; color: #3c4043; line-height: 1.5;';

        $admin_email_body = '<div style="' . $common_style . '"><h2>' . ($is_update ? 'Appointment Updated' : 'New Appointment') . '</h2>';
        $admin_email_body .= '<p>The appointment has been ' . ($is_update ? 'updated' : 'scheduled') . ' with the following details:</p>';
        $admin_email_body .= '<p><strong>Name:</strong> ' . esc_html($name) . '<br><strong>Date:</strong> ' . $date_formatted . '<br><strong>Time:</strong> ' . $time_formatted . '</p>';
        $admin_email_body .= '<div style="margin: 20px 0;">' . $meet_btn . '</div>';
        $admin_email_body .= '<p>A confirmation has been sent to the customer.</p></div>';

        $user_email_body = '<div style="' . $common_style . '"><h2>' . ($is_update ? 'Update: Appointment Invitation' : 'Invitation: Appointment') . '</h2>';
        $user_email_body .= '<p>Your appointment on ' . $date_formatted . ' at ' . $time_formatted . ' has been ' . ($is_update ? 'updated' : 'confirmed') . '.</p>';
        $user_email_body .= '<div style="margin: 20px 0;">' . $meet_btn . '</div>';
        
        // Add Modify/Cancel Buttons for User
        $scheduler_url = home_url('/'); // Fallback to home
        // Try to find the page with shortcode
        global $wpdb;
        $page_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content LIKE '%[appointment_scheduler]%' AND post_status = 'publish' LIMIT 1");
        if ($page_id) {
            $scheduler_url = get_permalink($page_id);
        }
        
        $modify_url = add_query_arg(array(
            'appointment_action' => 'modify',
            'id' => $id,
            'token' => $cancellation_token
        ), $scheduler_url);
        
        $cancel_url = add_query_arg(array(
            'appointment_action' => 'cancel',
            'id' => $id,
            'token' => $cancellation_token
        ), $scheduler_url);

        $user_email_body .= '<div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">';
        $user_email_body .= '<p style="font-size: 14px; color: #5f6368; margin-bottom: 15px;">Need to make changes to your appointment?</p>';
        $user_email_body .= '<a href="' . esc_url($modify_url) . '" style="display: inline-block; background-color: #fff; color: #1a73e8; border: 1px solid #dadce0; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: 500; font-size: 14px; margin-right: 10px;">Modify Appointment</a>';
        $user_email_body .= '<a href="' . esc_url($cancel_url) . '" style="display: inline-block; background-color: #fff; color: #d93025; border: 1px solid #dadce0; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: 500; font-size: 14px;">Request Cancellation</a>';
        $user_email_body .= '</div>';

        $user_email_body .= '<p style="margin-top: 25px;">Thank you for using our service.</p></div>';

        // ICS Generator
        $sequence = $is_update ? 1 : 0;
        $ics_content = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Appointment Scheduler//WordPress//EN\r\nMETHOD:REQUEST\r\nBEGIN:VEVENT\r\n";
        $ics_content .= "UID:appt-{$id}@{$server_name}\r\n";
        $ics_content .= "SEQUENCE:{$sequence}\r\n";
        $ics_content .= "STATUS:CONFIRMED\r\n";
        $ics_content .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $ics_content .= "DTSTART:" . gmdate('Ymd\THis\Z', strtotime($date . ' ' . $time)) . "\r\n";
        $ics_content .= "DTEND:" . gmdate('Ymd\THis\Z', strtotime($date . ' ' . $time) + 3600) . "\r\n";
        $ics_content .= "SUMMARY:" . ($is_update ? '[UPDATED] ' : '') . "Appointment with $name\r\n";
        $description = "Appointment with $name. ";
        if ($meet_link)
            $description .= "Join with Google Meet: $meet_link";
        $ics_content .= "DESCRIPTION:" . str_replace("\n", "\\n", $description) . "\r\n";
        $ics_content .= "LOCATION:Google Meet: $meet_link\r\n";
        $ics_content .= "ORGANIZER;CN=\"" . addslashes($blog_name) . "\":mailto:$from_email\r\n";

        // Add User as Attendee
        $ics_content .= "ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=\"" . addslashes($name) . "\":mailto:$email\r\n";

        // Add Guests as Attendees
        if (!empty($guest_emails)) {
            $g_arr = array_map('trim', explode(',', str_replace(array(' ', ';'), ',', $guest_emails)));
            foreach ($g_arr as $g) {
                if (is_email($g)) {
                    $ics_content .= "ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:$g\r\n";
                }
            }
        }

        // Add Additional Emails as Attendees so it shows in their calendar too
        if (!empty($additional_emails)) {
            $a_arr = array_map('trim', explode(',', str_replace(array(' ', ';'), ',', $additional_emails)));
            foreach ($a_arr as $a) {
                if (is_email($a)) {
                    $ics_content .= "ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:$a\r\n";
                }
            }
        }

        $ics_content .= "END:VEVENT\r\nEND:VCALENDAR\r\n";

        $ics_file = wp_upload_dir()['basedir'] . '/invite.ics';
        file_put_contents($ics_file, $ics_content);
        $attachments = array($ics_file);

        // Send to all
        wp_mail($admin_email_final, $admin_subject, $admin_email_body, $admin_headers, $attachments);
        wp_mail($email, $user_subject, $user_email_body, $user_headers, $attachments);

        // Additional
        if (!empty($additional_emails)) {
            $a_arr = array_map('trim', explode(',', str_replace(array(' ', ';'), ',', $additional_emails)));
            foreach ($a_arr as $a)
                if (is_email($a))
                    wp_mail($a, $admin_subject, $admin_email_body, $admin_headers, $attachments);
        }
        // Guests
        if (!empty($guest_emails)) {
            $g_arr = array_map('trim', explode(',', str_replace(array(' ', ';'), ',', $guest_emails)));
            foreach ($g_arr as $g)
                if (is_email($g))
                    wp_mail($g, $user_subject, $user_email_body, $user_headers, $attachments);
        }

        @unlink($ics_file);
    }
}

// Initialize plugin
Appointment_Scheduler::get_instance();

// Activation hook
register_activation_hook(__FILE__, array('Appointment_Scheduler', 'activate'));
