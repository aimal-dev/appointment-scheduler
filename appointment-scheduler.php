<?php
/**
 * Plugin Name: Appointment Scheduler
 * Plugin URI: https://example.com/appointment-scheduler
 * Description: A beautiful appointment scheduling system with calendar and time slot selection. Sends email notifications to admin.
 * Version: 1.3.0
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

class Appointment_Scheduler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_submit_appointment', array($this, 'handle_appointment_submission'));
        add_action('wp_ajax_nopriv_submit_appointment', array($this, 'handle_appointment_submission'));
        add_action('wp_ajax_get_time_slots', array($this, 'get_time_slots'));
        add_action('wp_ajax_nopriv_get_time_slots', array($this, 'get_time_slots'));
        add_action('wp_ajax_get_booked_dates', array($this, 'get_booked_dates'));
        add_action('wp_ajax_nopriv_get_booked_dates', array($this, 'get_booked_dates'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_delete_appointment', array($this, 'delete_appointment'));
        add_action('wp_ajax_add_to_google_calendar', array($this, 'generate_google_calendar_link'));
        add_action('wp_ajax_update_meet_link', array($this, 'update_meet_link'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('appointment_scheduler_send_reminders', array($this, 'send_appointment_reminders'));
        add_action('wp', array($this, 'schedule_reminder_cron'));
        add_action('admin_init', array($this, 'handle_google_oauth_callback'));
        add_action('wp_ajax_google_calendar_auth', array($this, 'initiate_google_oauth'));
    }
    
    public function init() {
        // Register shortcode
        add_shortcode('appointment_scheduler', array($this, 'render_scheduler'));
        
        // Handle cancellation
        if (isset($_GET['appointment_action']) && $_GET['appointment_action'] === 'cancel') {
            $this->handle_cancellation();
        }
    }
    
    public function handle_cancellation() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        
        if (!$id || !$token) {
            wp_die('Invalid cancellation request.');
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
        
        // Update status
        $wpdb->update(
            $table_name,
            array('status' => 'cancelled'),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
        
        // Send cancellation emails
        $this->send_cancellation_emails($appointment);
        
        // Show success message
        wp_die('Your appointment has been successfully cancelled. A confirmation email has been sent.', 'Appointment Cancelled');
    }
    
    private function send_cancellation_emails($appointment) {
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
    
    public function enqueue_scripts() {
        wp_enqueue_style(
            'appointment-scheduler-style',
            APPOINTMENT_SCHEDULER_PLUGIN_URL . 'assets/css/style.css',
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
            'interval' => get_option('appointment_interval', 30)
        ));
    }
    
    public function get_timezone_string() {
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
    
    public function render_scheduler($atts) {
        $atts = shortcode_atts(array(
            'email' => get_option('admin_email'),
        ), $atts);
        
        ob_start();
        include APPOINTMENT_SCHEDULER_PLUGIN_DIR . 'templates/scheduler.php';
        return ob_get_clean();
    }
    
    public function get_time_slots() {
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
    
    private function generate_time_slots($date) {
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
    
    private function check_slot_status($date, $time) {
        global $wpdb;
        
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
        
        // Check blocked times
        $blocked_times = get_option('appointment_blocked_times', array());
        foreach ($blocked_times as $blocked) {
            if ($blocked['date'] === $date && $blocked['time'] === $time) {
                return 'booked';
            }
        }
        
        return 'available';
    }
    
    private function is_slot_available($date, $time) {
        // Wrapper for legacy compatibility if needed
        return $this->check_slot_status($date, $time) === 'available';
    }
    
    private function is_date_booked($date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';
        
        // Check if this date has any appointments
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE appointment_date = %s",
            $date
        ));
        
        return $count > 0;
    }
    
    public function get_booked_dates() {
        check_ajax_referer('appointment_scheduler_nonce', 'nonce');
        
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
        $month = isset($_POST['month']) ? intval($_POST['month']) : date('m');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';
        
        // Get all booked dates for this month
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $booked_dates = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT appointment_date FROM $table_name 
             WHERE appointment_date >= %s AND appointment_date <= %s",
            $start_date,
            $end_date
        ));
        
        wp_send_json_success(array(
            'booked_dates' => $booked_dates
        ));
    }
    
    public function handle_appointment_submission() {
        check_ajax_referer('appointment_scheduler_nonce', 'nonce');
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $guest_emails = isset($_POST['guest_emails']) ? sanitize_text_field($_POST['guest_emails']) : '';
        
        // Validation
        if (empty($name) || empty($email) || empty($date) || empty($time)) {
            wp_send_json_error(array('message' => 'Please fill in all required fields.'));
        }
        
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        }
        
        // Check if this slot is available
        if ($this->check_slot_status($date, $time) !== 'available') {
            wp_send_json_error(array('message' => 'This time slot is no longer available.'));
        }
        
        // Get admin email from settings or use default
        $admin_email = get_option('appointment_admin_email', get_option('admin_email'));
        
        // Format date and time
        $date_formatted = date('F j, Y', strtotime($date));
        $time_formatted = date('g:i A', strtotime($time));
        
        // Send email to admin - meet link will be added after saving
        $admin_subject = sprintf('New Appointment Booking - %s', $date_formatted);
        $admin_email_body = "A new appointment has been booked:\n\n";
        $admin_email_body .= "Name: $name\n";
        $admin_email_body .= "Email: $email\n";
        $admin_email_body .= "Phone: " . ($phone ? $phone : 'Not provided') . "\n";
        $admin_email_body .= "Date: $date_formatted\n";
        $admin_email_body .= "Time: $time_formatted\n";
        if ($message) {
            $admin_email_body .= "Message: $message\n";
        }
        if ($guest_emails) {
            $admin_email_body .= "Guests: $guest_emails\n";
        }
        // Generate cancellation token
        $cancellation_token = bin2hex(random_bytes(16));
        
        // Save to database FIRST to get appointment ID
        $appointment_id = $this->save_appointment($name, $email, $phone, $date, $time, $message, $guest_emails, $cancellation_token);
        
        if (!$appointment_id) {
            wp_send_json_error(array(
                'message' => 'There was an error saving your appointment. Please try again.'
            ));
        }
        
        // Generate cancellation link
        $cancel_link = home_url("/?appointment_action=cancel&id=$appointment_id&token=$cancellation_token");
        
        // Update Admin Email Body
        $admin_email_body .= "\nGoogle Meet Link: \n"; // Placeholder
        $admin_email_body .= "\n---\n";
        $admin_email_body .= "This email was sent from your Appointment Scheduler plugin.";

        // Send email to user (confirmation)
        $user_subject = sprintf('Appointment Confirmation - %s', $date_formatted);
        $user_email_body = "Dear $name,\n\n";
        $user_email_body .= "Thank you for booking an appointment with us!\n\n";
        $user_email_body .= "Your appointment details:\n";
        $user_email_body .= "Date: $date_formatted\n";
        $user_email_body .= "Time: $time_formatted\n";
        if ($phone) {
            $user_email_body .= "Phone: $phone\n";
        }
        if ($message) {
            $user_email_body .= "Your Message: $message\n";
        }
        $user_email_body .= "\n";
        $user_email_body .= "Google Meet Link: \n"; // Placeholder
        $user_email_body .= "We look forward to meeting with you!\n\n";
        $user_email_body .= "To cancel this appointment, please click here:\n$cancel_link\n\n";
        $user_email_body .= "---\n";
        $user_email_body .= "This is an automated confirmation email from Appointment Scheduler.";

        // Prepare Headers - Fix "Unknown Sender"
        $blog_name = get_bloginfo('name');
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            "From: $blog_name <$admin_email>"
        );
        
        // Get additional emails from settings (can be multiple emails separated by comma)
        $additional_emails = get_option('appointment_additional_email', '');
        
        if (!$appointment_id) {
            wp_send_json_error(array(
                'message' => 'There was an error saving your appointment. Please try again.'
            ));
        }
        
        // Get the saved appointment with correct meet link
        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';
        $saved_appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $appointment_id
        ));
        
        // Use the saved meet link for emails
        $meet_link = !empty($saved_appointment->meet_link) ? $saved_appointment->meet_link : '';
        
        // Update user email with correct meet link
        if ($meet_link) {
            $user_email_body = str_replace("Google Meet Link: \n", "Google Meet Link: $meet_link\n", $user_email_body);
        }
        
        // Update admin email with correct meet link
        if ($meet_link) {
            $admin_email_body = str_replace("Google Meet Link: \n", "Google Meet Link: $meet_link\n", $admin_email_body);
        }
        
        // Send emails
        $admin_sent = wp_mail($admin_email, $admin_subject, $admin_email_body, $headers);
        $user_sent = wp_mail($email, $user_subject, $user_email_body, $headers);
        
        // Send email to additional emails if provided
        $additional_sent = false;
        if (!empty($additional_emails)) {
            // Split by comma and send to each email
            $email_array = array_map('trim', explode(',', $additional_emails));
            foreach ($email_array as $additional_email) {
                if (is_email($additional_email)) {
                    wp_mail($additional_email, $admin_subject, $admin_email_body, $headers);
                    $additional_sent = true;
                }
            }
        }
        
        // Send email to guest emails if provided
        if (!empty($guest_emails)) {
            // Split by comma and send to each email
            $guest_email_array = array_map('trim', explode(',', $guest_emails));
            foreach ($guest_email_array as $guest_email) {
                if (is_email($guest_email)) {
                    // Send user version of email to guests BUT REMOVE CANCELLATION LINK
                    $guest_body = str_replace(
                        "To cancel this appointment, please click here:\n$cancel_link\n\n", 
                        "", 
                        $user_email_body
                    );
                    wp_mail($guest_email, $user_subject, $guest_body, $headers);
                }
            }
        }
        
        // Try to create Google Calendar event using API if enabled
        $google_event_created = false;
        $google_enabled = get_option('google_calendar_enabled', 'no');
        if ($google_enabled === 'yes' && $saved_appointment) {
            $api_meet_link = $this->create_google_calendar_event_api($saved_appointment);
            if ($api_meet_link) {
                $google_event_created = true;
                // Use the Meet link from Google Calendar API if available
                if ($api_meet_link !== true) {
                    $meet_link = $api_meet_link;
                }
            }
        }
        
        // Check if email is Google account for auto calendar add
        $is_google_account = $this->is_google_account($email);
        $calendar_link = '';
        if ($saved_appointment && !$google_event_created) {
            // For user, create calendar link with admin as attendee (fallback to URL method)
            $calendar_link = $this->create_google_calendar_link($saved_appointment, false);
        }
        
        if ($admin_sent) {
            wp_send_json_success(array(
                'message' => 'Your appointment has been booked successfully! A confirmation email has been sent to your email address.' . ($google_event_created ? ' Event has been automatically added to Google Calendar with Meet link.' : ''),
                'meet_link' => $meet_link,
                'is_google_account' => $is_google_account,
                'calendar_link' => $calendar_link,
                'google_event_created' => $google_event_created
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'There was an error sending your appointment request. Please try again.'
            ));
        }
    }
    
    private function save_appointment($name, $email, $phone, $date, $time, $message, $guest_emails = '', $token = '') {
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
            $appointment = (object) array(
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
    
    private function is_google_account($email) {
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
    
    public function add_admin_menu() {
        add_menu_page(
            'Appointment Scheduler',
            'Appointments',
            'manage_options',
            'appointment-scheduler',
            array($this, 'render_admin_page'),
            'dashicons-calendar-alt',
            30
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
    
    public function register_settings() {
        register_setting('appointment_scheduler_settings', 'appointment_start_time');
        register_setting('appointment_scheduler_settings', 'appointment_end_time');
        register_setting('appointment_scheduler_settings', 'appointment_interval');
        register_setting('appointment_scheduler_settings', 'appointment_admin_email');
        register_setting('appointment_scheduler_settings', 'appointment_additional_email');
        register_setting('appointment_scheduler_settings', 'appointment_timezone');
        register_setting('appointment_scheduler_settings', 'appointment_blocked_times');
        register_setting('appointment_scheduler_settings', 'appointment_reminder_enabled');
        register_setting('appointment_scheduler_settings', 'appointment_reminder_times');
        register_setting('appointment_scheduler_settings', 'google_calendar_client_id');
        register_setting('appointment_scheduler_settings', 'google_calendar_client_secret');
        register_setting('appointment_scheduler_settings', 'google_calendar_access_token');
        register_setting('appointment_scheduler_settings', 'google_calendar_refresh_token');
        register_setting('appointment_scheduler_settings', 'google_calendar_enabled');
    }
    
    public function schedule_reminder_cron() {
        if (!wp_next_scheduled('appointment_scheduler_send_reminders')) {
            wp_schedule_event(time(), 'hourly', 'appointment_scheduler_send_reminders');
        }
    }
    
    public function send_appointment_reminders() {
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
    
    private function send_reminder_email($appointment, $reminder_type) {
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
    
    public function generate_google_calendar_link() {
        check_ajax_referer('appointment_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission.'));
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
    
    public function update_meet_link() {
        check_ajax_referer('appointment_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to update meet links.'));
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
        } else {
            wp_send_json_error(array('message' => 'Failed to update meet link.'));
        }
    }
    
    private function send_meet_link_change_notification($appointment, $old_link, $new_link) {
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
    
    private function create_google_calendar_link($appointment, $for_admin = false) {
        $start_date = strtotime($appointment->appointment_date . ' ' . $appointment->appointment_time);
        $end_date = $start_date + 3600; // 1 hour duration
        
        $start_iso = date('Ymd\THis\Z', $start_date);
        $end_iso = date('Ymd\THis\Z', $end_date);
        
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
        $details .= "Click the link above to join the meeting at the scheduled time.";
        
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
        } else {
            // Customer is creating - add admin as attendee so admin gets notification
            if (!empty($admin_email)) {
                $calendar_url .= "&add=" . urlencode($admin_email);
            }
        }
        
        return $calendar_url;
    }
    
    private function generate_meet_link($appointment) {
        // Generate a unique meet link based on appointment
        $meet_id = 'appt-' . md5($appointment->id . $appointment->appointment_date . $appointment->appointment_time);
        $meet_id = substr($meet_id, 0, 12);
        
        // Format: https://meet.google.com/xxx-xxxx-xxx
        $formatted_id = substr($meet_id, 0, 3) . '-' . substr($meet_id, 3, 4) . '-' . substr($meet_id, 7, 3);
        
        return 'https://meet.google.com/' . $formatted_id;
    }
    
    public function handle_google_oauth_callback() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle OAuth callback
        if (isset($_GET['page']) && $_GET['page'] === 'appointment-scheduler-settings' && isset($_GET['google_oauth_callback'])) {
            if (isset($_GET['code'])) {
                $code = sanitize_text_field($_GET['code']);
                $this->exchange_oauth_code_for_tokens($code);
            } elseif (isset($_GET['error'])) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>Google OAuth error: ' . esc_html($_GET['error']) . '</p></div>';
                });
            }
        }
        
        // Handle OAuth initiation
        if (isset($_GET['page']) && $_GET['page'] === 'appointment-scheduler-settings' && isset($_GET['google_auth'])) {
            $this->initiate_google_oauth();
        }
    }
    
    private function initiate_google_oauth() {
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
    
    private function exchange_oauth_code_for_tokens($code) {
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
            add_action('admin_notices', function() use ($response) {
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
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Google Calendar connected successfully!</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($body) {
                $error = isset($body['error']) ? $body['error'] : 'Unknown error';
                echo '<div class="notice notice-error"><p>Error: ' . esc_html($error) . '</p></div>';
            });
        }
    }
    
    private function get_google_access_token() {
        $access_token = get_option('google_calendar_access_token', '');
        
        if (empty($access_token)) {
            return false;
        }
        
        return $access_token;
    }
    
    private function refresh_google_token() {
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
    
    private function create_google_calendar_event_api($appointment) {
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
        
        $api_url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1';
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($event_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            // Try refreshing token
            $new_token = $this->refresh_google_token();
            if ($new_token) {
                $response = wp_remote_post($api_url, array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $new_token,
                        'Content-Type' => 'application/json'
                    ),
                    'body' => json_encode($event_data),
                    'timeout' => 30
                ));
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
    
    public function enqueue_admin_scripts($hook) {
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
    
    public function delete_appointment() {
        check_ajax_referer('appointment_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to delete appointments.'));
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
        } else {
            wp_send_json_error(array('message' => 'Failed to delete appointment.'));
        }
    }
    
    public function render_admin_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';
        $bookings = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        
        include APPOINTMENT_SCHEDULER_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    public function render_settings_page() {
        include APPOINTMENT_SCHEDULER_PLUGIN_DIR . 'templates/settings-page.php';
    }
    
    public static function activate() {
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
}

// Initialize plugin
Appointment_Scheduler::get_instance();

// Activation hook
register_activation_hook(__FILE__, array('Appointment_Scheduler', 'activate'));

