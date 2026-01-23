(function($) {
    'use strict';
    
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();
    let selectedDate = null;
    let currentDayOffset = 0;
    const daysToShow = 4;
    
    // Initialize
    $(document).ready(function() {
        loadBookedDates();
        renderCalendar();
        loadTimeSlots();
        initResponsiveFeatures();
        
        // Event listeners
        $('.prev-month').on('click', function() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            loadBookedDates();
            renderCalendar();
        });
        
        $('.next-month').on('click', function() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            loadBookedDates();
            renderCalendar();
        });
        
        $('#slots-prev').on('click', function() {
            changeSelectedDateBy(-3);
        });
        
        $('#slots-next').on('click', function() {
            changeSelectedDateBy(3);
        });

        function changeSelectedDateBy(days) {
            if (!selectedDate) {
               // If no date selected, start from Today
               const now = new Date();
               selectedDate = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            }
            
            let d = new Date(selectedDate);
            d.setDate(d.getDate() + days); // Shift days
            
            // Format YYYY-MM-DD
            let year = d.getFullYear();
            let month = String(d.getMonth() + 1).padStart(2, '0');
            let day = String(d.getDate()).padStart(2, '0');
            let newDateStr = `${year}-${month}-${day}`;
            
            // Update Calendar Month view if changed
            if (d.getMonth() !== currentMonth || d.getFullYear() !== currentYear) {
                currentMonth = d.getMonth();
                currentYear = d.getFullYear();
                renderCalendar(); // Re-render grid for new month
            }
            
            selectDate(newDateStr); // Highlight new date and load slots
        }
        
        // Modal close
        $('.appointment-modal-close, .btn-cancel').on('click', function() {
            closeModal();
        });
        
        // Close modal on outside click
        $(window).on('click', function(e) {
            if ($(e.target).hasClass('appointment-modal')) {
                closeModal();
            }
        });
        
        // Form submission
        $('#appointmentForm').on('submit', function(e) {
            e.preventDefault();
            submitAppointment();
        });
    });
    
    // Initialize responsive features
    function initResponsiveFeatures() {
        // Smooth scrolling for horizontal containers
        const scrollContainers = $('.times-days-container, .times-slots-container');
        
        // Enable smooth scrolling
        scrollContainers.css({
            'scroll-behavior': 'smooth',
            '-webkit-overflow-scrolling': 'touch'
        });
        
        // Improve touch scrolling for horizontal containers
        scrollContainers.each(function() {
            const container = $(this);
            let startX = 0;
            let startY = 0;
            
            container.on('touchstart', function(e) {
                const touch = e.originalEvent.touches[0];
                startX = touch.pageX;
                startY = touch.pageY;
            });
            
            container.on('touchmove', function(e) {
                const touch = e.originalEvent.touches[0];
                const deltaX = Math.abs(touch.pageX - startX);
                const deltaY = Math.abs(touch.pageY - startY);
                
                // If horizontal movement is greater than vertical, allow horizontal scroll
                if (deltaX > deltaY && deltaX > 10) {
                    // Allow horizontal scrolling
                    return true;
                }
            });
        });
        
        // Handle window resize
        let resizeTimer;
        $(window).on('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                // Recalculate layout if needed
                if (selectedDate) {
                    loadTimeSlots();
                }
            }, 250);
        });
    }
    
    function renderCalendar() {
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];
        
        $('.calendar-month-year').text(monthNames[currentMonth] + ' ' + currentYear);
        
        const firstDay = new Date(currentYear, currentMonth, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const prevMonthDays = new Date(currentYear, currentMonth, 0).getDate();
        
        // Adjust first day (Monday = 0)
        const adjustedFirstDay = (firstDay === 0) ? 6 : firstDay - 1;
        
        let calendarHTML = '';
        
        // Previous month days
        for (let i = adjustedFirstDay - 1; i >= 0; i--) {
            const day = prevMonthDays - i;
            calendarHTML += `<div class="calendar-day other-month">${day}</div>`;
        }
        
        // Current month days
        const today = new Date();
        const todayStr = formatDate(today);
        
        // Get booked dates for this month
        const bookedDates = getBookedDatesForMonth(currentYear, currentMonth + 1);
        
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(currentYear, currentMonth, day);
            const dateStr = formatDate(date);
            const isPast = date < today && dateStr !== todayStr;
            const isBooked = bookedDates.includes(dateStr);
            const isAvailable = !isPast && !isBooked && isDateAvailable(date);
            
            let classes = 'calendar-day';
            if (isPast) {
                classes += ' past';
            } else if (isBooked) {
                classes += ' booked';
            } else if (isAvailable) {
                classes += ' available';
            }
            
            if (selectedDate && dateStr === selectedDate) {
                classes += ' selected';
            }
            
            calendarHTML += `
                <div class="${classes}" data-date="${dateStr}" title="${isBooked ? 'Appointment Booked' : ''}">
                    <span class="day-number">${day}</span>
                </div>
            `;
        }
        
        // Next month days to fill grid
        const totalCells = calendarHTML.match(/calendar-day/g).length;
        const remainingCells = 42 - totalCells;
        
        for (let day = 1; day <= remainingCells; day++) {
            calendarHTML += `<div class="calendar-day other-month">${day}</div>`;
        }
        
        $('.calendar-grid').html(calendarHTML);
        
        // Date selection - only allow available dates
        $('.calendar-day.available').on('click', function() {
            const date = $(this).data('date');
            selectDate(date);
        });
        
        // Prevent clicking on booked dates
        $('.calendar-day.booked').on('click', function(e) {
            e.preventDefault();
            return false;
        });
    }
    
    function selectDate(date) {
        selectedDate = date;
        currentDayOffset = 0;
        renderCalendar();
        loadTimeSlots();
    }
    
    function loadTimeSlots() {
        if (!selectedDate) {
            selectedDate = formatDate(new Date());
        }
        
        const grid = $('#time-slots-grid');
        grid.html('<div class="slots-loading">Loading slots...</div>');
        
        $.ajax({
            url: appointmentScheduler.ajax_url,
            type: 'POST',
            data: {
                action: 'get_multi_day_slots',
                date: selectedDate,
                days: 3,
                nonce: appointmentScheduler.nonce
            },
            success: function(response) {
                if (response.success) {
                    let html = '';
                    const days = response.data;
                    
                    if (days && days.length > 0) {
                        days.forEach(day => {
                            let slotsHtml = '';
                            
                            if (day.slots.length > 0) {
                                day.slots.forEach(slot => {
                                    if (slot.status === 'past') return;
                                    
                                    if (slot.status === 'booked') {
                                        // Calculate End Time for display
                                        // slot.time is 'HH:mm'
                                        const [h, m] = slot.time.split(':').map(Number);
                                        const dateObj = new Date();
                                        dateObj.setHours(h, m, 0, 0);
                                        dateObj.setMinutes(dateObj.getMinutes() + (parseInt(appointmentScheduler.interval) || 30));
                                        
                                        // Format end time
                                        const endH = dateObj.getHours();
                                        const endM = dateObj.getMinutes();
                                        const endAmpm = endH >= 12 ? 'pm' : 'am';
                                        const endH12 = endH % 12 || 12;
                                        const endMStr = endM < 10 ? '0' + endM : endM;
                                        const endTimeDisplay = `${endH12}:${endMStr}${endAmpm}`;
                                        
                                        slotsHtml += `
                                            <div class="time-slot booked" title="Already Booked">
                                                <span class="slot-time">${slot.display} - ${endTimeDisplay}</span>
                                                <span class="slot-status">Booked</span>
                                            </div>
                                        `;
                                    } else {
                                        slotsHtml += `
                                            <div class="time-slot available" 
                                                 data-time="${slot.time}" 
                                                 data-date="${day.date}"
                                                 data-display="${slot.display}">
                                                ${slot.display}
                                            </div>
                                        `;
                                    }
                                });
                            }
                            
                            if (slotsHtml === '') {
                                slotsHtml = '<div class="no-slots">-</div>';
                            }
                            
                            html += `
                                <div class="day-column">
                                    <div class="column-header">${day.label}</div>
                                    <div class="column-slots">
                                        ${slotsHtml}
                                    </div>
                                </div>
                            `;
                        });
                        grid.html(html);
                        
                        // Bind click events
                        $('.time-slot.available').off('click').on('click', function() {
                            $('.time-slot').removeClass('selected');
                            $(this).addClass('selected');
                            
                            const date = $(this).data('date');
                            const time = $(this).data('time');
                            
                            openBookingModal(date, time);
                        });
                        
                    } else {
                        grid.html('<div class="slots-message">No availability found.</div>');
                    }
                } else {
                    grid.html('<div class="slots-error">Error loading slots.</div>');
                }
            },
            error: function() {
                grid.html('<div class="slots-error">Connection error.</div>');
            }
        });
    }
    
    function openBookingModal(date, time) {
        const dateFormatted = formatDateDisplay(date);
        const timeFormattedStart = formatTimeDisplay(time);
        
        // Calculate End Time
        const [hours, minutes] = time.split(':').map(Number);
        const dateObj = new Date();
        dateObj.setHours(hours, minutes, 0, 0);
        // Add interval
        const interval = parseInt(appointmentScheduler.interval) || 30;
        dateObj.setMinutes(dateObj.getMinutes() + interval);
        
        const endHours = dateObj.getHours();
        const endMinutes = dateObj.getMinutes();
        // Format end time manually to match formatTimeDisplay logic
        const endAmpm = endHours >= 12 ? 'pm' : 'am';
        const endDisplayHour = endHours % 12 || 12;
        const endDisplayMinutes = endMinutes < 10 ? '0' + endMinutes : endMinutes;
        const timeFormattedEnd = endDisplayHour + ':' + endDisplayMinutes + endAmpm;
        
        $('#selectedDate').val(date);
        $('#selectedTime').val(time);
        $('#selectedAppointmentDisplay').text(dateFormatted + ' at ' + timeFormattedStart + ' - ' + timeFormattedEnd);
        
        $('#appointmentModal').addClass('show');
        $('body').css('overflow', 'hidden');
    }
    
    function closeModal() {
        $('#appointmentModal').removeClass('show');
        $('body').css('overflow', '');
        $('#formMessage').removeClass('success error').hide();
        $('#appointmentForm')[0].reset();
    }
    
    function submitAppointment() {
        const formData = {
            action: 'submit_appointment',
            nonce: appointmentScheduler.nonce,
            name: $('#appointmentName').val(),
            email: $('#appointmentEmail').val(),
            phone: $('#appointmentPhone').val(),
            date: $('#selectedDate').val(),
            time: $('#selectedTime').val(),
            guest_emails: $('#appointmentGuests').val(),
            message: $('#appointmentMessage').val()
        };
        
        $('.btn-submit').prop('disabled', true).text('Booking...');
        
        $.ajax({
            url: appointmentScheduler.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                const messageEl = $('#formMessage');
                
                if (response.success) {
                    messageEl.removeClass('error').addClass('success')
                        .text(response.data.message).show();
                    
                    // Priority: Redirect to configured Thank You URL
                    if (appointmentScheduler.thankyou_url && appointmentScheduler.thankyou_url !== '') {
                        // Build URL with appointment details
                        // Using prefixed keys to avoid conflict with WordPress reserved query vars (like 'name')
                        const params = new URLSearchParams({
                            booking_name: $('#appointmentName').val(),
                            booking_email: $('#appointmentEmail').val(),
                            booking_date: $('#selectedDate').val(),
                            booking_time: $('#selectedTime').val()
                        });
                        
                        const separator = appointmentScheduler.thankyou_url.includes('?') ? '&' : '?';
                        const redirectUrl = appointmentScheduler.thankyou_url + separator + params.toString();
                        
                        // Redirect after short delay
                        setTimeout(function() {
                            window.location.href = redirectUrl;
                        }, 1500);
                    } else {
                        // Fallback: Success message and close modal
                        setTimeout(function() {
                            closeModal();
                            loadBookedDates();
                            loadTimeSlots();
                        }, 2000);
                    }
                } else {
                    messageEl.removeClass('success').addClass('error')
                        .text(response.data.message || 'An error occurred. Please try again.').show();
                    $('.btn-submit').prop('disabled', false).text('Book Appointment');
                }
            },
            error: function() {
                $('#formMessage').removeClass('success').addClass('error')
                    .text('An error occurred. Please try again.').show();
                $('.btn-submit').prop('disabled', false).text('Book Appointment');
            }
        });
    }
    
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    function formatDateDisplay(dateStr) {
        const date = new Date(dateStr + 'T00:00:00');
        const months = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];
        return months[date.getMonth()] + ' ' + date.getDate() + ', ' + date.getFullYear();
    }
    
    function formatTimeDisplay(timeStr) {
        const [hours, minutes] = timeStr.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'pm' : 'am';
        const displayHour = hour % 12 || 12;
        return displayHour + ':' + minutes + ampm;
    }
    
    function isDateAvailable(date) {
        // Check if date is at least today
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return date >= today;
    }
    
    // Cache for booked dates
    let bookedDatesCache = [];
    let bookedDatesCacheMonth = null;
    let bookedDatesCacheYear = null;
    
    function loadBookedDates() {
        // Check cache first
        if (bookedDatesCacheMonth === currentMonth && bookedDatesCacheYear === currentYear) {
            return; // Already loaded
        }
        
        $.ajax({
            url: appointmentScheduler.ajax_url,
            type: 'POST',
            data: {
                action: 'get_booked_dates',
                year: currentYear,
                month: currentMonth + 1, // JavaScript months are 0-indexed
                nonce: appointmentScheduler.nonce
            },
            success: function(response) {
                if (response.success) {
                    bookedDatesCache = response.data.booked_dates || [];
                    bookedDatesCacheMonth = currentMonth;
                    bookedDatesCacheYear = currentYear;
                    renderCalendar(); // Re-render calendar with booked dates
                }
            },
            error: function() {
                bookedDatesCache = [];
            }
        });
    }
    
    function getBookedDatesForMonth(year, month) {
        if (bookedDatesCacheMonth === month - 1 && bookedDatesCacheYear === year) {
            return bookedDatesCache;
        }
        return [];
    }
    
})(jQuery);

