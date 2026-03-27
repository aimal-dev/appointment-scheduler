<?php
/**
 * Admin Page Template
 */

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$is_authorized_admin = current_user_can('manage_options');

if (!$is_authorized_admin) {
    echo '<div class="notice notice-warning"><p>Note: You do not have permission to modify or delete appointments.</p></div>';
}
?>

<div class="wrap">
    <h1>Appointment Bookings</h1>
    
    <?php if (!empty($bookings)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Guests</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Meet Link</th>
                    <th>Message</th>
                    <th>Booked On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                    <tr id="appointment-row-<?php echo esc_attr($booking->id); ?>">
                        <td><?php echo esc_html($booking->id); ?></td>
                        <td><?php echo esc_html($booking->name); ?></td>
                        <td><?php echo esc_html($booking->email); ?></td>
                        <td><?php echo esc_html($booking->phone ? $booking->phone : '-'); ?></td>
                        <td>
                            <?php
        if (!empty($booking->guest_emails)) {
            $guests = explode(',', $booking->guest_emails);
            foreach ($guests as $guest) {
                echo esc_html(trim($guest)) . '<br>';
            }
        }
        else {
            echo '-';
        }
?>
                        </td>
                        <td><?php echo esc_html(date('F j, Y', strtotime($booking->appointment_date))); ?></td>
                        <td><?php echo esc_html(date('g:i A', strtotime($booking->appointment_time))); ?></td>
                        <td>
                            <div class="meet-link-container" id="meet-link-<?php echo esc_attr($booking->id); ?>">
                                <?php if (!empty($booking->meet_link)): ?>
                                    <a href="<?php echo esc_url($booking->meet_link); ?>" target="_blank" class="meet-link-display">
                                        <?php echo esc_html($booking->meet_link); ?>
                                    </a>
                                    <?php if ($is_authorized_admin): ?>
                                        <button type="button" class="button-link edit-meet-link" 
                                                data-appointment-id="<?php echo esc_attr($booking->id); ?>"
                                                data-current-link="<?php echo esc_attr($booking->meet_link); ?>"
                                                title="Edit Meet Link">
                                            Edit
                                        </button>
                                    <?php
            endif; ?>
                                <?php
        else: ?>
                                    <span class="no-meet-link">No link</span>
                                    <?php if ($is_authorized_admin): ?>
                                        <button type="button" class="button-link edit-meet-link" 
                                                data-appointment-id="<?php echo esc_attr($booking->id); ?>"
                                                data-current-link=""
                                                title="Add Meet Link">
                                            Add Link
                                        </button>
                                    <?php
            endif; ?>
                                <?php
        endif; ?>
                            </div>
                        </td>
                        <td><?php echo esc_html($booking->message ? $booking->message : '-'); ?></td>
                        <td><?php echo esc_html(date('F j, Y g:i A', strtotime($booking->created_at))); ?></td>
                        <td>
                            <button type="button" class="button button-small view-appointment" 
                                     data-appointment='<?php echo esc_attr(json_encode($booking)); ?>'
                                     title="View full details">
                                View
                            </button>
                            <?php if ($is_authorized_admin): ?>
                                <button type="button" class="button button-small edit-appointment" 
                                        data-appointment='<?php echo esc_attr(json_encode($booking)); ?>'
                                        title="Edit appointment details">
                                    Edit
                                </button>
                                <button type="button" class="button button-small add-to-calendar" 
                                        data-appointment-id="<?php echo esc_attr($booking->id); ?>"
                                        aria-label="Add to Google Calendar"
                                        title="Add to your Google Calendar">
                                    Add to My Calendar
                                </button>
                                <button type="button" class="button button-small delete-appointment" 
                                        data-appointment-id="<?php echo esc_attr($booking->id); ?>"
                                        aria-label="Delete appointment">
                                    Delete
                                </button>
                            <?php
        endif; ?>
                        </td>
                    </tr>
                <?php
    endforeach; ?>
            </tbody>
        </table>
    <?php
else: ?>
        <p>No appointments booked yet.</p>
    <?php
endif; ?>
</div>

<!-- Simple Admin Modal for Viewing Details -->
<div id="appointment-details-modal" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
    <div style="background:#fff; margin:10% auto; padding:30px; border-radius:8px; width:500px; max-width:90%; position:relative; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <span id="close-details-modal" style="position:absolute; right:15px; top:10px; font-size:24px; cursor:pointer;">&times;</span>
        <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px;">Appointment Details</h2>
        <div id="details-modal-content" style="line-height:1.6;">
            <!-- Content will be injected by JS -->
        </div>
        <div style="margin-top:20px; text-align:right;">
            <button type="button" class="button button-primary" id="close-details-button">Close</button>
        </div>
    </div>
</div>

<!-- Edit Appointment Modal -->
<div id="edit-appointment-modal" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
    <div style="background:#fff; margin:5% auto; padding:30px; border-radius:8px; width:600px; max-width:90%; position:relative; box-shadow: 0 4px 15px rgba(0,0,0,0.2); max-height: 100%; height: 500px; overflow-y: auto;">
        <span id="close-edit-modal" style="position:absolute; right:15px; top:10px; font-size:24px; cursor:pointer;">&times;</span>
        <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px;">Edit Appointment</h2>
        <form id="edit-appointment-form">
            <input type="hidden" id="edit-appointment-id" name="id">
            
            <div style="margin-bottom: 15px;">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Customer Name</label>
                <input type="text" id="edit-name" name="name" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" required>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Email</label>
                <input type="email" id="edit-email" name="email" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" required>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Phone</label>
                <input type="text" id="edit-phone" name="phone" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Guest Emails</label>
                <input type="text" id="edit-guests" name="guest_emails" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" placeholder="guest1@example.com, guest2@example.com">
            </div>
            
            <div style="display:flex; gap:15px; margin-bottom: 15px;">
                <div style="flex:1;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Date</label>
                    <input type="date" id="edit-date" name="date" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" required>
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Time</label>
                    <input type="time" id="edit-time" name="time" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" required>
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Google Meet Link</label>
                <input type="url" id="edit-meet-link" name="meet_link" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Message (Internal Note)</label>
                <textarea id="edit-message" name="message" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" rows="3"></textarea>
            </div>
            
            <div style="margin-top:20px; text-align:right;">
                <button type="button" class="button" id="cancel-edit-button" style="margin-right:10px;">Cancel</button>
                <button type="submit" class="button button-primary" id="save-edit-button">Update Appointment</button>
            </div>
        </form>
    </div>
</div>

