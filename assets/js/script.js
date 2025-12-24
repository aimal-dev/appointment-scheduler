(function($) {
    'use strict';
    
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();
    let selectedDate = null;
    let currentDayOffset = 0;
    const daysToShow = 4;
    
    // Initialize
    $(document).ready(function() {
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
            renderCalendar();
        });
        
        $('.next-month').on('click', function() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar();
        });
        
        $('.prev-days').on('click', function() {
            if (currentDayOffset > 0) {
                currentDayOffset--;
                loadTimeSlots();
            }
        });
        
        $('.next-days').on('click', function() {
            currentDayOffset++;
            loadTimeSlots();
        });
        
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
        
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(currentYear, currentMonth, day);
            const dateStr = formatDate(date);
            const isPast = date < today && dateStr !== todayStr;
            const isAvailable = !isPast && isDateAvailable(date);
            
            let classes = 'calendar-day';
            if (isPast) {
                classes += ' past';
            } else if (isAvailable) {
                classes += ' available';
            }
            
            if (selectedDate && dateStr === selectedDate) {
                classes += ' selected';
            }
            
            const dayLabel = ['S', 'M', 'T', 'W', 'T', 'F', 'S'][date.getDay()];
            
            calendarHTML += `
                <div class="${classes}" data-date="${dateStr}">
                    <span class="day-label">${dayLabel}</span>
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
        
        // Date selection
        $('.calendar-day.available').on('click', function() {
            const date = $(this).data('date');
            selectDate(date);
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
            // Use today as default
            selectedDate = formatDate(new Date());
        }
        
        const [year, month, day] = selectedDate.split('-').map(Number);
        const startDate = new Date(year, month - 1, day);
        startDate.setDate(startDate.getDate() + currentDayOffset);
        
        const daysHTML = [];
        
        // Create slots containers first
        let slotsHTML = '';
        for (let i = 0; i < daysToShow; i++) {
            slotsHTML += '<div class="times-day-slots"></div>';
        }
        $('.times-slots-container').html(slotsHTML);
        
        // Load slots for each day
        const promises = [];
        
        for (let i = 0; i < daysToShow; i++) {
            const date = new Date(startDate);
            date.setDate(startDate.getDate() + i);
            const dateStr = formatDate(date);
            
            const dayNames = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
            const dayName = dayNames[date.getDay()];
            const dayNum = date.getDate();
            
            daysHTML.push(`<div class="times-day-header">${dayName} ${dayNum}</div>`);
            
            // Load time slots for this date
            const promise = new Promise(function(resolve) {
                loadTimeSlotsForDate(dateStr, function(slots) {
                    const daySlotsHTML = [];
                    
                    if (slots.length === 0) {
                        daySlotsHTML.push('<div class="time-slot-placeholder"></div>');
                    } else {
                        slots.forEach(function(slot) {
                            if (slot.available) {
                                daySlotsHTML.push(
                                    `<button type="button" class="time-slot" data-date="${dateStr}" data-time="${slot.value}">${slot.time}</button>`
                                );
                            } else {
                                daySlotsHTML.push(
                                    `<div class="time-slot unavailable">${slot.time}</div>`
                                );
                            }
                        });
                    }
                    
                    const container = $('.times-day-slots').eq(i);
                    container.html(daySlotsHTML.join(''));
                    
                    // Add click handler
                    container.find('.time-slot:not(.unavailable)').on('click', function() {
                        const date = $(this).data('date');
                        const time = $(this).data('time');
                        openBookingModal(date, time);
                    });
                    
                    resolve();
                });
            });
            
            promises.push(promise);
        }
        
        $('.times-days-container').html(daysHTML.join(''));
        
        // Update navigation buttons
        $('.prev-days').prop('disabled', currentDayOffset === 0);
    }
    
    function loadTimeSlotsForDate(date, callback) {
        $.ajax({
            url: appointmentScheduler.ajax_url,
            type: 'POST',
            data: {
                action: 'get_time_slots',
                date: date,
                selected_date: selectedDate,
                nonce: appointmentScheduler.nonce
            },
            success: function(response) {
                if (response.success) {
                    callback(response.data.time_slots);
                } else {
                    callback([]);
                }
            },
            error: function() {
                callback([]);
            }
        });
    }
    
    function openBookingModal(date, time) {
        const dateFormatted = formatDateDisplay(date);
        const timeFormatted = formatTimeDisplay(time);
        
        $('#selectedDate').val(date);
        $('#selectedTime').val(time);
        $('#selectedAppointmentDisplay').text(dateFormatted + ' at ' + timeFormatted);
        
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
                    
                    setTimeout(function() {
                        closeModal();
                        // Reload time slots to update availability
                        loadTimeSlots();
                    }, 2000);
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
    
})(jQuery);

