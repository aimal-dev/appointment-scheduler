(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Handle delete appointment
        $(document).on('click', '.delete-appointment', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const appointmentId = button.data('appointment-id');
            const row = $('#appointment-row-' + appointmentId);
            
            // Confirm deletion
            if (!confirm(appointmentAdmin.confirm_delete)) {
                return;
            }
            
            // Disable button and show loading state
            button.prop('disabled', true).text('Deleting...');
            
            // AJAX request
            $.ajax({
                url: appointmentAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_appointment',
                    appointment_id: appointmentId,
                    nonce: appointmentAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Fade out and remove row
                        row.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if table is empty
                            if ($('.wp-list-table tbody tr').length === 0) {
                                $('.wp-list-table').closest('.wrap').html('<p>No appointments booked yet.</p>');
                            }
                        });
                        
                        // Show success message
                        showAdminNotice('Appointment deleted successfully.', 'success');
                    } else {
                        button.prop('disabled', false).text('Delete');
                        showAdminNotice(response.data.message || 'Failed to delete appointment.', 'error');
                    }
                },
                error: function() {
                    button.prop('disabled', false).text('Delete');
                    showAdminNotice('An error occurred. Please try again.', 'error');
                }
            });
        });
    });
    
    // Show admin notice
    function showAdminNotice(message, type) {
        type = type || 'success';
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Dismiss button
        notice.on('click', '.notice-dismiss', function() {
            notice.remove();
        });
    }
    
})(jQuery);

