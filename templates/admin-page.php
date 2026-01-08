<?php
/**
 * Admin Page Template
 */

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
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
                                foreach($guests as $guest) {
                                    echo esc_html(trim($guest)) . '<br>';
                                }
                            } else {
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
                                    <button type="button" class="button-link edit-meet-link" 
                                            data-appointment-id="<?php echo esc_attr($booking->id); ?>"
                                            data-current-link="<?php echo esc_attr($booking->meet_link); ?>"
                                            title="Edit Meet Link">
                                        Edit
                                    </button>
                                <?php else: ?>
                                    <span class="no-meet-link">No link</span>
                                    <button type="button" class="button-link edit-meet-link" 
                                            data-appointment-id="<?php echo esc_attr($booking->id); ?>"
                                            data-current-link=""
                                            title="Add Meet Link">
                                        Add Link
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo esc_html($booking->message ? $booking->message : '-'); ?></td>
                        <td><?php echo esc_html(date('F j, Y g:i A', strtotime($booking->created_at))); ?></td>
                        <td>
                            <button type="button" class="button button-small add-to-calendar" 
                                    data-appointment-id="<?php echo esc_attr($booking->id); ?>"
                                    aria-label="Add to Google Calendar"
                                    title="Add to your Google Calendar (make sure you're logged in with admin account)">
                                Add to My Calendar
                            </button>
                            <button type="button" class="button button-small delete-appointment" 
                                    data-appointment-id="<?php echo esc_attr($booking->id); ?>"
                                    aria-label="Delete appointment">
                                Delete
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No appointments booked yet.</p>
    <?php endif; ?>
</div>

