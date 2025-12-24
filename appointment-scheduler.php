<?php
/**
 * Plugin Name: Appointment Scheduler
 * Plugin URI: https://example.com/appointment-scheduler
 * Description: A beautiful appointment scheduling system with calendar and time slot selection. Sends email notifications to admin.
 * Version: 1.0.0
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
define('APPOINTMENT_SCHEDULER_VERSION', '1.0.0');
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function init() {
        // Register shortcode
        add_shortcode('appointment_scheduler', array($this, 'render_scheduler'));
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
            'timezone' => $this->get_timezone_string()
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
        
        wp_send_json_success(array(
            'date' => $date,
            'time_slots' => $time_slots,
            'selected_date' => $selected_date
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
            
            // Check if slot is available (you can add custom logic here)
            $is_available = $this->is_slot_available($date, $time_value);
            
            $slots[] = array(
                'time' => $time_str,
                'value' => $time_value,
                'available' => $is_available
            );
            
            $current = strtotime('+' . $interval . ' minutes', $current);
        }
        
        return $slots;
    }
    
    private function is_slot_available($date, $time) {
        // Check if date is in the past
        if (strtotime($date . ' ' . $time) < current_time('timestamp')) {
            return false;
        }
        
        // Get blocked times from settings
        $blocked_times = get_option('appointment_blocked_times', array());
        $date_time = $date . ' ' . $time;
        
        foreach ($blocked_times as $blocked) {
            if ($blocked['date'] === $date && $blocked['time'] === $time) {
                return false;
            }
        }
        
        return true;
    }
    
    public function handle_appointment_submission() {
        check_ajax_referer('appointment_scheduler_nonce', 'nonce');
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        
        // Validation
        if (empty($name) || empty($email) || empty($date) || empty($time)) {
            wp_send_json_error(array('message' => 'Please fill in all required fields.'));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        }
        
        // Get admin email from settings or use default
        $admin_email = get_option('appointment_admin_email', get_option('admin_email'));
        
        // Format date and time
        $date_formatted = date('F j, Y', strtotime($date));
        $time_formatted = date('g:i A', strtotime($time));
        
        // Send email to admin
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
        $user_email_body .= "We look forward to seeing you!\n\n";
        $user_email_body .= "If you need to reschedule or cancel, please contact us.\n\n";
        $user_email_body .= "---\n";
        $user_email_body .= "This is an automated confirmation email from Appointment Scheduler.";
        
        // Get additional emails from settings (can be multiple emails separated by comma)
        $additional_emails = get_option('appointment_additional_email', '');
        
        // Send emails
        $headers = array('Content-Type: text/plain; charset=UTF-8');
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
        
        // Save to database if at least admin email was sent
        if ($admin_sent) {
            $this->save_appointment($name, $email, $phone, $date, $time, $message);
            
            wp_send_json_success(array(
                'message' => 'Your appointment has been booked successfully! A confirmation email has been sent to your email address.'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'There was an error sending your appointment request. Please try again.'
            ));
        }
    }
    
    private function save_appointment($name, $email, $phone, $date, $time, $message) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'appointment_bookings';
        
        $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'appointment_date' => $date,
                'appointment_time' => $time,
                'message' => $message,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
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
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
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

