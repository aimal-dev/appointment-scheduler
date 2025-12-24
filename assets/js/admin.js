(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Handle edit meet link
        $(document).on('click', '.edit-meet-link', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const appointmentId = button.data('appointment-id');
            const currentLink = button.data('current-link') || '';
            
            // Create edit form
            const editForm = $('<div class="meet-link-edit-form" style="margin-top: 5px;">' +
                '<input type="text" class="meet-link-input regular-text" value="' + currentLink + '" placeholder="https://meet.google.com/xxx-xxxx-xxx" style="width: 300px; margin-right: 5px;">' +
                '<button type="button" class="button button-small save-meet-link" data-appointment-id="' + appointmentId + '">Save</button>' +
                '<button type="button" class="button button-small cancel-edit-meet-link" style="margin-left: 5px;">Cancel</button>' +
                '</div>');
            
            // Hide current display and show edit form
            button.closest('.meet-link-container').find('.meet-link-display, .no-meet-link, .edit-meet-link').hide();
            button.closest('.meet-link-container').append(editForm);
            editForm.find('.meet-link-input').focus();
        });
        
        // Handle cancel edit
        $(document).on('click', '.cancel-edit-meet-link', function(e) {
            e.preventDefault();
            const form = $(this).closest('.meet-link-edit-form');
            form.remove();
            form.siblings('.meet-link-display, .no-meet-link, .edit-meet-link').show();
        });
        
        // Handle save meet link
        $(document).on('click', '.save-meet-link', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const appointmentId = button.data('appointment-id');
            const newLink = button.siblings('.meet-link-input').val().trim();
            const container = $('#meet-link-' + appointmentId);
            
            if (!newLink) {
                alert('Please enter a Meet link.');
                return;
            }
            
            button.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: appointmentAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_meet_link',
                    appointment_id: appointmentId,
                    meet_link: newLink,
                    nonce: appointmentAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update display
                        container.find('.meet-link-edit-form').remove();
                        container.html(
                            '<a href="' + response.data.new_link + '" target="_blank" class="meet-link-display">' + 
                            response.data.new_link + '</a> ' +
                            '<button type="button" class="button-link edit-meet-link" ' +
                            'data-appointment-id="' + appointmentId + '" ' +
                            'data-current-link="' + response.data.new_link + '" ' +
                            'title="Edit Meet Link">Edit</button>'
                        );
                        showAdminNotice(response.data.message, 'success');
                    } else {
                        button.prop('disabled', false).text('Save');
                        showAdminNotice(response.data.message || 'Failed to update meet link.', 'error');
                    }
                },
                error: function() {
                    button.prop('disabled', false).text('Save');
                    showAdminNotice('An error occurred. Please try again.', 'error');
                }
            });
        });
        
        // Handle add to Google Calendar
        $(document).on('click', '.add-to-calendar', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const appointmentId = button.data('appointment-id');
            
            button.prop('disabled', true).text('Generating...');
            
            $.ajax({
                url: appointmentAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'add_to_google_calendar',
                    appointment_id: appointmentId,
                    nonce: appointmentAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Open Google Calendar in new window
                        window.open(response.data.calendar_link, '_blank');
                        const adminEmail = response.data.admin_email || 'your admin email';
                        showAdminNotice('⚠️ IMPORTANT: Google Calendar opened! Make sure you are logged in with your ADMIN Google account (' + adminEmail + '). If you see a different account, please switch to your admin account first, then click "Save" to add the event. Meet Link: ' + response.data.meet_link, 'warning');
                    } else {
                        showAdminNotice(response.data.message || 'Failed to generate calendar link.', 'error');
                    }
                    button.prop('disabled', false).text('Add to My Calendar');
                },
                error: function() {
                    button.prop('disabled', false).text('Add to Calendar');
                    showAdminNotice('An error occurred. Please try again.', 'error');
                }
            });
        });
        
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
        let noticeClass = 'notice-success';
        if (type === 'error') {
            noticeClass = 'notice-error';
        } else if (type === 'warning') {
            noticeClass = 'notice-warning';
        }
        const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        // Auto dismiss after 8 seconds for warnings (longer for important messages)
        const dismissTime = type === 'warning' ? 8000 : 5000;
        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, dismissTime);
        
        // Dismiss button
        notice.on('click', '.notice-dismiss', function() {
            notice.remove();
        });
    }
    
})(jQuery);

