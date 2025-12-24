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
                    <th>Date</th>
                    <th>Time</th>
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
                        <td><?php echo esc_html(date('F j, Y', strtotime($booking->appointment_date))); ?></td>
                        <td><?php echo esc_html(date('g:i A', strtotime($booking->appointment_time))); ?></td>
                        <td><?php echo esc_html($booking->message ? $booking->message : '-'); ?></td>
                        <td><?php echo esc_html(date('F j, Y g:i A', strtotime($booking->created_at))); ?></td>
                        <td>
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

