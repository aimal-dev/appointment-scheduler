<?php
/**
 * Settings Page Template with Tabs
 */

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// 1. Handle Form Submission
if (isset($_POST['submit'])) {
    check_admin_referer('appointment_scheduler_settings');
    
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

    if ($active_tab == 'general') {
        update_option('appointment_start_time', sanitize_text_field($_POST['appointment_start_time']));
        update_option('appointment_end_time', sanitize_text_field($_POST['appointment_end_time']));
        update_option('appointment_interval', intval($_POST['appointment_interval']));
        update_option('appointment_admin_email', sanitize_email($_POST['appointment_admin_email']));
        
        // Additional Emails
        $additional_emails = sanitize_text_field($_POST['appointment_additional_email']);
        if (!empty($additional_emails)) {
            $email_array = array_map('trim', explode(',', $additional_emails));
            $valid_emails = array();
            foreach ($email_array as $email) {
                $sanitized = sanitize_email($email);
                if (is_email($sanitized)) $valid_emails[] = $sanitized;
            }
            $additional_emails = implode(', ', $valid_emails);
        }
        update_option('appointment_additional_email', $additional_emails);
        
        update_option('appointment_timezone', sanitize_text_field($_POST['appointment_timezone']));
        update_option('appointment_reminder_enabled', isset($_POST['appointment_reminder_enabled']) ? 'yes' : 'no');
        update_option('appointment_reminder_times', isset($_POST['appointment_reminder_times']) ? $_POST['appointment_reminder_times'] : array());
        
        // Google Calendar
        update_option('google_calendar_client_id', sanitize_text_field($_POST['google_calendar_client_id']));
        update_option('google_calendar_client_secret', sanitize_text_field($_POST['google_calendar_client_secret']));
        update_option('google_calendar_enabled', isset($_POST['google_calendar_enabled']) ? 'yes' : 'no');
    
    } elseif ($active_tab == 'design') {
        // Sidebar / Top Content
        update_option('appointment_sidebar_title', sanitize_text_field($_POST['appointment_sidebar_title']));
        update_option('appointment_sidebar_subtitle', sanitize_text_field($_POST['appointment_sidebar_subtitle']));
        update_option('appointment_sidebar_duration', sanitize_text_field($_POST['appointment_sidebar_duration']));
        update_option('appointment_sidebar_location', sanitize_text_field($_POST['appointment_sidebar_location']));
        update_option('appointment_sidebar_description', sanitize_textarea_field($_POST['appointment_sidebar_description']));
        
        // Redirect Settings
        update_option('appointment_thankyou_url', sanitize_text_field($_POST['appointment_thankyou_url']));
    }
    
    echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
}

// 2. Fetch Active Tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

// 3. Render Tabs
?>
<div class="wrap">
    <h1>Appointment Scheduler Settings</h1>

    <div class="notice notice-info" style="margin-top: 20px; border-left: 4px solid #722ed1; padding: 15px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0; color: #722ed1;"><span class="dashicons dashicons-calendar-alt"></span> How to Show Appointment Scheduler</h3>
        <p>Copy and paste the shortcode below onto any page or post where you want the booking form to appear:</p>
        <div style="display: flex; align-items: center; gap: 10px; margin: 15px 0;">
            <code id="scheduler-shortcode" style="padding: 12px 20px; background: #f0f0f1; border: 1px solid #dcdcde; border-radius: 4px; font-size: 1.25em; font-weight: 600;">[appointment_scheduler]</code>
            <button type="button" class="button button-secondary" onclick="copyShortcode()" style="height: 40px;">
                <span class="dashicons dashicons-copy" style="vertical-align: middle; margin-top: -3px;"></span> Copy
            </button>
        </div>
        <p class="description"><strong>Pro Tip:</strong> Create a new page titled "Book an Appointment" and paste this code in the block editor.</p>
    </div>

    <script>
    function copyShortcode() {
        const code = document.getElementById('scheduler-shortcode').innerText;
        navigator.clipboard.writeText(code).then(() => {
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="dashicons dashicons-yes" style="vertical-align: middle; margin-top: -3px;"></span> Copied!';
            setTimeout(() => {
                btn.innerHTML = originalText;
            }, 2000);
        });
    }
    </script>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=appointment-scheduler-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">General Settings</a>
        <a href="?page=appointment-scheduler-settings&tab=design" class="nav-tab <?php echo $active_tab == 'design' ? 'nav-tab-active' : ''; ?>">Form Content & Branding</a>
    </h2>

    <form method="post" action="">
        <?php wp_nonce_field('appointment_scheduler_settings'); ?>
        
        <?php if ($active_tab == 'general'): ?>
            <?php 
            // Load General Settings values
            $start_time = get_option('appointment_start_time', '10:00');
            $end_time = get_option('appointment_end_time', '17:30');
            $interval = get_option('appointment_interval', 30);
            $admin_email = get_option('appointment_admin_email', get_option('admin_email'));
            $additional_email = get_option('appointment_additional_email', '');
            $selected_timezone = get_option('appointment_timezone', 'Europe/London');
            $reminder_enabled = get_option('appointment_reminder_enabled', 'yes');
            $reminder_times = get_option('appointment_reminder_times', array('15min', '1hr', '1day'));
            if (!is_array($reminder_times)) $reminder_times = array();
            // Google
            $google_client_id = get_option('google_calendar_client_id', '');
            $google_client_secret = get_option('google_calendar_client_secret', '');
            $google_calendar_enabled = get_option('google_calendar_enabled', 'no');
            $google_access_token = get_option('google_calendar_access_token', '');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="appointment_start_time">Start Time</label></th>
                    <td><input type="time" name="appointment_start_time" value="<?php echo esc_attr($start_time); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="appointment_end_time">End Time</label></th>
                    <td><input type="time" name="appointment_end_time" value="<?php echo esc_attr($end_time); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="appointment_interval">Slot Interval (min)</label></th>
                    <td><input type="number" name="appointment_interval" value="<?php echo esc_attr($interval); ?>" min="15" max="60" step="15" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="appointment_admin_email">Admin Email</label></th>
                    <td><input type="email" name="appointment_admin_email" value="<?php echo esc_attr($admin_email); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="appointment_additional_email">Additional Emails</label></th>
                    <td>
                        <input type="text" name="appointment_additional_email" value="<?php echo esc_attr($additional_email); ?>" class="regular-text">
                        <p class="description">Comma separated.</p>
                    </td>
                </tr>
                 <tr>
                    <th scope="row"><label for="appointment_timezone">Timezone</label></th>
                    <td>
                        <select id="appointment_timezone" name="appointment_timezone">
                            <?php 
                            $timezone_identifiers = DateTimeZone::listIdentifiers();
                            foreach ($timezone_identifiers as $timezone) {
                                $selected = ($timezone == $selected_timezone) ? 'selected' : '';
                                echo "<option value='$timezone' $selected>$timezone</option>";
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <!-- Reminder Section -->
                 <tr>
                    <th scope="row">Email Reminders</th>
                    <td>
                        <label><input type="checkbox" name="appointment_reminder_enabled" value="yes" <?php checked($reminder_enabled, 'yes'); ?>> Enable Reminders</label><br><br>
                        <fieldset>
                            <label><input type="checkbox" name="appointment_reminder_times[]" value="15min" <?php echo in_array('15min', $reminder_times) ? 'checked' : ''; ?>> 15 Minutes Before</label><br>
                            <label><input type="checkbox" name="appointment_reminder_times[]" value="1hr" <?php echo in_array('1hr', $reminder_times) ? 'checked' : ''; ?>> 1 Hour Before</label><br>
                            <label><input type="checkbox" name="appointment_reminder_times[]" value="1day" <?php echo in_array('1day', $reminder_times) ? 'checked' : ''; ?>> 1 Day Before</label>
                        </fieldset>
                    </td>
                </tr>
                
                <!-- Google Calendar -->
                 <tr>
                    <th scope="row">Google Calendar</th>
                    <td>
                        <label><input type="checkbox" name="google_calendar_enabled" value="yes" <?php checked($google_calendar_enabled, 'yes'); ?>> Enable Google Calendar Sync</label>
                        <div style="margin-top: 15px; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
                            <h4 style="margin-top: 0;">How to Setup Google Calendar Sync:</h4>
                            <ol>
                                <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>.</li>
                                <li>Create a new project and enable the <strong>Google Calendar API</strong>.</li>
                                <li>Go to <strong>APIs & Services > OAuth consent screen</strong> and configure it.</li>
                                <li>Go to <strong>APIs & Services > Credentials</strong>, click <strong>Create Credentials > OAuth client ID</strong>.</li>
                                <li>Select <strong>Web application</strong> as the application type.</li>
                                <li>Add the following <strong>Authorized Redirect URI</strong>:<br>
                                    <code style="background: #f0f0f1; padding: 2px 5px;"><?php echo admin_url('admin.php?page=appointment-scheduler-settings&google_oauth_callback=1'); ?></code>
                                </li>
                                <li>Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> and paste them below.</li>
                                <li>Save settings, then click the <strong>Connect with Google</strong> button that appears.</li>
                            </ol>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label>Client ID</label></th>
                    <td><input type="text" name="google_calendar_client_id" value="<?php echo esc_attr($google_client_id); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label>Client Secret</label></th>
                    <td><input type="password" name="google_calendar_client_secret" value="<?php echo esc_attr($google_client_secret); ?>" class="large-text"></td>
                </tr>
                 <tr>
                    <th scope="row">Connection Status</th>
                    <td>
                        <?php if ($google_access_token): ?>
                            <span style="color: green; font-weight: bold;">Connected</span> 
                            <a href="<?php echo admin_url('admin-ajax.php?action=google_calendar_auth'); ?>" class="button button-secondary">Reconnect</a>
                        <?php else: ?>
                            <span style="color: red;">Not Connected</span>
                            <?php if ($google_calendar_enabled === 'yes' && $google_client_id && $google_client_secret): ?>
                                <a href="<?php echo admin_url('admin-ajax.php?action=google_calendar_auth'); ?>" class="button button-primary">Connect with Google</a>
                            <?php else: ?>
                                <p class="description">Enable and save settings to connect.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

        <?php elseif ($active_tab == 'design'): ?>
            <?php
            // Load Design Settings
            $s_title = get_option('appointment_sidebar_title', 'BoltOS Demo');
            $s_subtitle = get_option('appointment_sidebar_subtitle', 'Turn viewers into revenue');
            $s_duration = get_option('appointment_sidebar_duration', '30 min appointments');
            $s_location = get_option('appointment_sidebar_location', 'Google Meet video conference info added after booking');
            $s_desc = get_option('appointment_sidebar_description', 'Our sales experts have helped customers grow revenue...');
            ?>

            <h3>Form Header Content</h3>
            <table class="form-table">
                <tr><th>Title</th><td><input type="text" name="appointment_sidebar_title" value="<?php echo esc_attr($s_title); ?>" class="large-text"></td></tr>
                <tr><th>Subtitle</th><td><input type="text" name="appointment_sidebar_subtitle" value="<?php echo esc_attr($s_subtitle); ?>" class="large-text"></td></tr>
                <tr><th>Duration Text</th><td><input type="text" name="appointment_sidebar_duration" value="<?php echo esc_attr($s_duration); ?>" class="regular-text"></td></tr>
                <tr><th>Location Text</th><td><input type="text" name="appointment_sidebar_location" value="<?php echo esc_attr($s_location); ?>" class="large-text"></td></tr>
                <tr><th>Description</th><td><textarea name="appointment_sidebar_description" rows="5" class="large-text"><?php echo esc_textarea($s_desc); ?></textarea></td></tr>
            </table>

            <hr>

            <h3>Post-Booking Redirect</h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="appointment_thankyou_url">Redirect URL</label></th>
                    <td>
                        <input type="text" id="appointment_thankyou_url" name="appointment_thankyou_url" value="<?php echo esc_attr(get_option('appointment_thankyou_url', '')); ?>" class="large-text" placeholder="https://example.com/thank-you/">
                        <p class="description">Where should the user be sent after a successful booking? Leave blank to just show a success message.</p>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        
        <?php submit_button(); ?>
    </form>
</div>
