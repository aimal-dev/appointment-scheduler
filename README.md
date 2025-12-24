# Appointment Scheduler WordPress Plugin

A beautiful, custom appointment scheduling plugin for WordPress that matches the modern design shown in your reference image. Features a calendar-based date picker and time slot selection system.

## Screenshots

### Main Appointment Scheduler Interface

![Appointment Scheduler Interface](./screenshots/appointment-scheduler-main.png)

*The main appointment scheduling interface showing the calendar on the left and available time slots on the right.*

### Admin Settings Page

![Admin Settings](./screenshots/appointment-scheduler-settings.png)

*Admin settings page where you can configure working hours, time intervals, timezone, and email notifications.*

### Admin Bookings Dashboard

![Admin Bookings](./screenshots/appointment-scheduler-bookings.png)

*Admin dashboard showing all booked appointments with customer details.*

**Note:** Screenshot images should be placed in the `screenshots` folder. See `screenshots/README.md` for instructions on taking and adding screenshots.

## Features

- ✅ Beautiful calendar interface with date selection
- ✅ Time slot display with availability indicators
- ✅ Responsive design matching the reference UI
- ✅ Email notifications to admin when appointments are booked
- ✅ Database storage of all appointments
- ✅ Admin dashboard to view all bookings
- ✅ Customizable working hours and time intervals
- ✅ Easy shortcode integration

## Installation

1. **Upload the plugin:**
   - Upload the `appointment-scheduler` folder to `/wp-content/plugins/` directory
   - Or zip the folder and upload via WordPress admin → Plugins → Add New → Upload Plugin

2. **Activate the plugin:**
   - Go to WordPress admin → Plugins
   - Find "Appointment Scheduler" and click "Activate"

3. **Configure settings:**
   - Go to WordPress admin → Appointments → Settings
   - Set your working hours (start time, end time)
   - Set time slot interval (e.g., 30 minutes)
   - Set admin email for notifications

## Usage

### Adding to a Page

Simply add the shortcode `[appointment_scheduler]` to any page or post where you want the booking form to appear.

**Example:**
```
[appointment_scheduler]
```

**With custom email:**
```
[appointment_scheduler email="custom@example.com"]
```

### How It Works

1. **User selects a date** from the calendar (left side)
2. **Available time slots appear** for the selected date and next few days (right side)
3. **User clicks a time slot** to open the booking form
4. **User fills in details** (name, email, phone, optional message)
5. **Admin receives email** with all appointment details
6. **Appointment is saved** to the database

## Admin Features

### View Bookings
- Go to **Appointments** in WordPress admin menu
- View all appointments with details:
  - Name, Email, Phone
  - Appointment Date & Time
  - Message (if provided)
  - Booking timestamp

### Settings
- **Start Time:** Earliest available appointment time (e.g., 10:00)
- **End Time:** Latest available appointment time (e.g., 17:30)
- **Interval:** Minutes between time slots (e.g., 30 for 30-minute intervals)
- **Admin Email:** Email address to receive booking notifications
- **Additional Email:** Multiple email addresses (comma-separated) to receive notifications
- **Timezone:** Select timezone for displaying appointment times (e.g., United Kingdom Time, Pakistan Standard Time, etc.)

## Email Notifications

When a user books an appointment, emails are sent to:
- **Admin Email:** Receives notification with booking details
- **User Email:** Receives confirmation email with appointment details
- **Additional Emails:** All additional emails (if configured) receive the same notification as admin

**Email Content Includes:**
- Customer name
- Customer email
- Customer phone (if provided)
- Appointment date
- Appointment time
- Message (if provided)

## Database

The plugin creates a table `wp_appointment_bookings` to store all appointments. This allows you to:
- View bookings in admin dashboard
- Export data if needed
- Track appointment history

## Customization

### Styling
Edit `assets/css/style.css` to customize colors, fonts, and layout.

### Time Slots
Modify the `generate_time_slots()` function in `appointment-scheduler.php` to customize:
- Available time ranges
- Blocked times
- Special date handling

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- jQuery (included with WordPress)

## Support

For issues or customization requests, please check the plugin code comments or contact M.Aimal.

## License

GPL v2 or later

