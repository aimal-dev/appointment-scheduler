<?php
/**
 * Settings Page Template
 */

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

if (isset($_POST['submit'])) {
    check_admin_referer('appointment_scheduler_settings');
    
    update_option('appointment_start_time', sanitize_text_field($_POST['appointment_start_time']));
    update_option('appointment_end_time', sanitize_text_field($_POST['appointment_end_time']));
    update_option('appointment_interval', intval($_POST['appointment_interval']));
    update_option('appointment_admin_email', sanitize_email($_POST['appointment_admin_email']));
    // Handle multiple emails separated by comma
    $additional_emails = sanitize_text_field($_POST['appointment_additional_email']);
    if (!empty($additional_emails)) {
        // Split by comma and validate each email
        $email_array = array_map('trim', explode(',', $additional_emails));
        $valid_emails = array();
        foreach ($email_array as $email) {
            $sanitized = sanitize_email($email);
            if (is_email($sanitized)) {
                $valid_emails[] = $sanitized;
            }
        }
        $additional_emails = implode(', ', $valid_emails);
    }
    update_option('appointment_additional_email', $additional_emails);
    update_option('appointment_timezone', sanitize_text_field($_POST['appointment_timezone']));
    update_option('appointment_reminder_enabled', isset($_POST['appointment_reminder_enabled']) ? 'yes' : 'no');
    update_option('appointment_reminder_times', isset($_POST['appointment_reminder_times']) ? $_POST['appointment_reminder_times'] : array());
    update_option('google_calendar_client_id', sanitize_text_field($_POST['google_calendar_client_id']));
    update_option('google_calendar_client_secret', sanitize_text_field($_POST['google_calendar_client_secret']));
    update_option('google_calendar_enabled', isset($_POST['google_calendar_enabled']) ? 'yes' : 'no');
    
    echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
}

$start_time = get_option('appointment_start_time', '10:00');
$end_time = get_option('appointment_end_time', '17:30');
$interval = get_option('appointment_interval', 30);
$admin_email = get_option('appointment_admin_email', get_option('admin_email'));
$additional_email = get_option('appointment_additional_email', '');
$selected_timezone = get_option('appointment_timezone', 'Europe/London');
$reminder_enabled = get_option('appointment_reminder_enabled', 'yes');
$reminder_times = get_option('appointment_reminder_times', array('15min', '1hr', '1day'));
if (!is_array($reminder_times)) {
    $reminder_times = array();
}
?>

<div class="wrap">
    <h1>Appointment Scheduler Settings</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('appointment_scheduler_settings'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="appointment_start_time">Start Time</label>
                </th>
                <td>
                    <input type="time" id="appointment_start_time" name="appointment_start_time" 
                           value="<?php echo esc_attr($start_time); ?>" class="regular-text" required>
                    <p class="description">The earliest available appointment time (24-hour format).</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="appointment_end_time">End Time</label>
                </th>
                <td>
                    <input type="time" id="appointment_end_time" name="appointment_end_time" 
                           value="<?php echo esc_attr($end_time); ?>" class="regular-text" required>
                    <p class="description">The latest available appointment time (24-hour format).</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="appointment_interval">Time Slot Interval (minutes)</label>
                </th>
                <td>
                    <input type="number" id="appointment_interval" name="appointment_interval" 
                           value="<?php echo esc_attr($interval); ?>" min="15" max="60" step="15" class="small-text" required>
                    <p class="description">Time interval between available slots (e.g., 30 for 30-minute intervals).</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="appointment_admin_email">Admin Email</label>
                </th>
                <td>
                    <input type="email" id="appointment_admin_email" name="appointment_admin_email" 
                           value="<?php echo esc_attr($admin_email); ?>" class="regular-text" required>
                    <p class="description">Email address where appointment notifications will be sent.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="appointment_additional_email">Additional Email (Optional)</label>
                </th>
                <td>
                    <input type="text" id="appointment_additional_email" name="appointment_additional_email" 
                           value="<?php echo esc_attr($additional_email); ?>" class="regular-text" 
                           placeholder="email1@example.com, email2@example.com">
                    <p class="description">Additional email addresses to receive appointment notifications. Separate multiple emails with commas (e.g., email1@example.com, email2@example.com). Leave empty if not needed.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="appointment_timezone">Timezone</label>
                </th>
                <td>
                    <select id="appointment_timezone" name="appointment_timezone" class="regular-text">
                        <?php
                        $timezones = array(
                            'Europe/London' => '(GMT+00:00) United Kingdom Time',
                            'America/New_York' => '(GMT-05:00) Eastern Time (US & Canada)',
                            'America/Chicago' => '(GMT-06:00) Central Time (US & Canada)',
                            'America/Denver' => '(GMT-07:00) Mountain Time (US & Canada)',
                            'America/Los_Angeles' => '(GMT-08:00) Pacific Time (US & Canada)',
                            'Europe/Paris' => '(GMT+01:00) Central European Time',
                            'Europe/Berlin' => '(GMT+01:00) Central European Time',
                            'Asia/Dubai' => '(GMT+04:00) Gulf Standard Time',
                            'Asia/Karachi' => '(GMT+05:00) Pakistan Standard Time',
                            'Asia/Kolkata' => '(GMT+05:30) India Standard Time',
                            'Asia/Dhaka' => '(GMT+06:00) Bangladesh Standard Time',
                            'Asia/Bangkok' => '(GMT+07:00) Indochina Time',
                            'Asia/Singapore' => '(GMT+08:00) Singapore Time',
                            'Asia/Tokyo' => '(GMT+09:00) Japan Standard Time',
                            'Australia/Sydney' => '(GMT+10:00) Australian Eastern Time',
                            'Pacific/Auckland' => '(GMT+12:00) New Zealand Time',
                        );
                        
                        foreach ($timezones as $tz_value => $tz_label) {
                            $selected = ($selected_timezone === $tz_value) ? 'selected' : '';
                            echo '<option value="' . esc_attr($tz_value) . '" ' . $selected . '>' . esc_html($tz_label) . '</option>';
                        }
                        ?>
                    </select>
                    <p class="description">Select the timezone for displaying appointment times.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>Email Reminders</label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="appointment_reminder_enabled" value="yes" 
                               <?php checked($reminder_enabled, 'yes'); ?>>
                        Enable email reminders before appointments
                    </label>
                    <p class="description">Send automatic email reminders to customers and admin before appointments.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>Reminder Times</label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="appointment_reminder_times[]" value="1day" 
                                   <?php checked(in_array('1day', $reminder_times)); ?>>
                            1 day before
                        </label><br>
                        <label>
                            <input type="checkbox" name="appointment_reminder_times[]" value="1hr" 
                                   <?php checked(in_array('1hr', $reminder_times)); ?>>
                            1 hour before
                        </label><br>
                        <label>
                            <input type="checkbox" name="appointment_reminder_times[]" value="15min" 
                                   <?php checked(in_array('15min', $reminder_times)); ?>>
                            15 minutes before
                        </label>
                    </fieldset>
                    <p class="description">Select when to send reminder emails. Reminders include Google Meet link if available.</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
        </p>
    </form>
    
    <hr>
    
    <h2>Google Calendar API Integration</h2>
    <p>Enable automatic Google Calendar event creation with Meet links. <strong>This requires Google Cloud Project setup.</strong></p>
    
    <?php
    // Handle OAuth revoke
    if (isset($_GET['revoke_google_auth']) && $_GET['revoke_google_auth'] == '1') {
        delete_option('google_calendar_access_token');
        delete_option('google_calendar_refresh_token');
        echo '<div class="notice notice-success"><p>Google Calendar access revoked successfully!</p></div>';
    }
    ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('appointment_scheduler_settings'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="google_calendar_enabled">Enable Google Calendar API</label>
                </th>
                <td>
                    <input type="checkbox" id="google_calendar_enabled" name="google_calendar_enabled" value="yes" <?php checked($google_calendar_enabled, 'yes'); ?>>
                    <p class="description">Enable automatic calendar event creation with Meet links</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="google_calendar_client_id">Google Client ID</label>
                </th>
                <td>
                    <input type="text" id="google_calendar_client_id" name="google_calendar_client_id" value="<?php echo esc_attr($google_client_id); ?>" class="regular-text">
                    <p class="description">Get this from <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="google_calendar_client_secret">Google Client Secret</label>
                </th>
                <td>
                    <input type="password" id="google_calendar_client_secret" name="google_calendar_client_secret" value="<?php echo esc_attr($google_client_secret); ?>" class="regular-text">
                    <p class="description">Keep this secret secure</p>
                </td>
            </tr>
            <tr>
                <th scope="row">OAuth Status</th>
                <td>
                    <?php if (!empty($google_access_token)): ?>
                        <span style="color: green;">✓ Connected</span>
                        <a href="<?php echo admin_url('admin.php?page=appointment-scheduler-settings&revoke_google_auth=1'); ?>" class="button" style="margin-left: 10px;">Revoke Access</a>
                    <?php else: ?>
                        <span style="color: orange;">⚠ Not Connected</span>
                        <?php if (!empty($google_client_id) && !empty($google_client_secret)): ?>
                            <a href="<?php echo admin_url('admin.php?page=appointment-scheduler-settings&google_auth=1'); ?>" class="button button-primary" style="margin-left: 10px;">Connect Google Calendar</a>
                        <?php else: ?>
                            <p class="description">Please enter Client ID and Secret first, then click "Connect Google Calendar"</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Google Calendar Settings">
        </p>
    </form>
    
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h3>Setup Instructions:</h3>
        <ol>
            <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
            <li>Create a new project or select existing one</li>
            <li>Enable "Google Calendar API" from APIs & Services</li>
            <li>Go to "Credentials" → "Create Credentials" → "OAuth 2.0 Client ID"</li>
            <li>Application type: "Web application"</li>
            <li>Authorized redirect URIs: <code><?php echo admin_url('admin.php?page=appointment-scheduler-settings&google_oauth_callback=1'); ?></code></li>
            <li>Copy Client ID and Client Secret to fields above</li>
            <li>Click "Connect Google Calendar" to authorize</li>
        </ol>
    </div>
    
    <hr>
    
    <h2>Shortcode Usage</h2>
    <p>To display the appointment scheduler on any page or post, use the following shortcode:</p>
    <code>[appointment_scheduler]</code>
    
    <p>You can also specify a custom email address:</p>
    <code>[appointment_scheduler email="custom@example.com"]</code>
</div>

