# Appointment Scheduler WordPress Plugin

A beautiful and feature-rich appointment scheduling system for WordPress with calendar integration, Google Meet links, email notifications, and automatic reminders.

**Version:** 1.3.0  
**Author:** M.Aimal  
**License:** GPL v2 or later

---

## 📋 Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Settings Configuration](#settings-configuration)
- [Google Calendar API Integration](#google-calendar-api-integration)
- [Usage](#usage)
- [Shortcode](#shortcode)
- [Screenshots](#screenshots)
- [Changelog](#changelog)
- [Support](#support)

---

## ✨ Features

### Core Features

- **📅 Interactive Calendar**: Beautiful, responsive calendar interface for date selection
- **⏰ Time Slot Management**: Configurable working hours and time intervals
- **📧 Email Notifications**: Automatic email confirmations to admin, customers, and guests
- **🌍 Timezone Support**: Select timezone for accurate scheduling
- **📱 Fully Responsive**: Works perfectly on desktop, tablet, and mobile devices
- **🎨 Modern UI**: Clean, blue-themed design with smooth animations

### Advanced Features

- **� Guest Support**: Add colleagues/guests to appointments via email
- **❌ Cancellation System**: Unique cancellation links for users to easily cancel appointments
- **�🔗 Google Meet Integration**: Automatic Google Meet link generation for appointments
- **📅 Google Calendar Sync**: Automatic event creation in Google Calendar with Meet links
- **� Smart Notifications**: Alerts for admin and guests on new bookings and cancellations
- **🔔 Email Reminders**: Automated reminders (1 day, 1 hour, 15 minutes before)
- **🚫 Duplicate Prevention**: Prevents double-booking of same time slots
- **📊 Admin Dashboard**: View and manage all appointments
- **✏️ Meet Link Management**: Edit Meet links from admin dashboard
- **🗑️ Appointment Deletion**: Delete appointments from admin panel
- **📱 Mobile-Friendly**: Optimized for all screen sizes with overflow handling

---

## 📦 Installation

### Method 1: Manual Installation

1. Download the plugin files
2. Upload the `appointment-scheduler` folder to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Appointments → Settings** to configure the plugin

### Method 2: WordPress Admin

1. Go to **Plugins → Add New**
2. Click **Upload Plugin**
3. Choose the plugin zip file
4. Click **Install Now** and then **Activate**

---

## 🚀 Quick Start

1. **Activate the Plugin**: Go to Plugins and activate "Appointment Scheduler"

2. **Configure Settings**:

   - Navigate to **Appointments → Settings**
   - Set your working hours (Start Time, End Time)
   - Set time interval (e.g., 30 minutes)
   - Add admin email address
   - Select your timezone
   - Save settings

3. **Add to Page/Post**:

   - Use shortcode: `[appointment_scheduler]`
   - Or use the block editor to add the shortcode block

4. **Test Booking**:
   - Visit the page with the shortcode
   - Select a date and time
   - Fill in the booking form
   - Submit and check email confirmations

---

## ⚙️ Settings Configuration

Navigate to **Appointments → Settings** to configure:

### Working Hours

- **Start Time**: When appointments can start (e.g., 10:00)
- **End Time**: When appointments end (e.g., 17:30)
- **Time Interval**: Minutes between slots (e.g., 30 minutes)

### Email Settings

- **Admin Email**: Primary email to receive appointment notifications
- **Additional Emails**: Multiple emails separated by commas (e.g., `email1@example.com, email2@example.com`)

### Timezone

- Select your timezone from the dropdown (e.g., Europe/London, America/New_York)

### Email Reminders

- **Enable Reminders**: Toggle to enable/disable automatic reminders
- **Reminder Times**: Select when to send reminders:
  - 1 day before
  - 1 hour before
  - 15 minutes before

### Google Calendar API (Optional)

- **Enable Google Calendar API**: Toggle to enable automatic calendar event creation
- **Client ID**: Google OAuth Client ID
- **Client Secret**: Google OAuth Client Secret
- **OAuth Status**: Connection status and authorization button

---

## 🔗 Google Calendar API Integration

### Why Use Google Calendar API?

- **Automatic Event Creation**: Events are automatically added to Google Calendar
- **Google Meet Links**: Meet links are automatically generated and added to events
- **Two-Way Sync**: Both admin and customer receive calendar invites
- **No Manual Work**: Everything happens automatically when appointment is booked

### Setup Instructions

#### Step 1: Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click **Create Project** or select an existing project
3. Give your project a name (e.g., "Appointment Scheduler")
4. Click **Create**

#### Step 2: Enable Google Calendar API

1. In the Google Cloud Console, go to **APIs & Services → Library**
2. Search for "Google Calendar API"
3. Click on **Google Calendar API**
4. Click **Enable**

#### Step 3: Create OAuth Credentials

1. Go to **APIs & Services → Credentials**
2. Click **Create Credentials → OAuth 2.0 Client ID**
3. If prompted, configure OAuth consent screen:

   - User Type: **External** (or Internal for Workspace)
   - App name: Your app name
   - User support email: Your email
   - Developer contact: Your email
   - Click **Save and Continue**
   - Scopes: Add `https://www.googleapis.com/auth/calendar.events`
   - Click **Save and Continue**
   - Test users: Add your email (for testing)
   - Click **Save and Continue**

4. Create OAuth Client ID:

   - Application type: **Web application**
   - Name: Appointment Scheduler
   - **Authorized redirect URIs**: Add this URL:
     ```
     https://yourdomain.com/wp-admin/admin.php?page=appointment-scheduler-settings&google_oauth_callback=1
     ```
     Replace `yourdomain.com` with your actual domain
   - Click **Create**

5. **Copy the Client ID and Client Secret**

#### Step 4: Configure Plugin

1. Go to **Appointments → Settings** in WordPress admin
2. Scroll to **Google Calendar API Integration** section
3. Check **Enable Google Calendar API**
4. Paste **Client ID** in the field
5. Paste **Client Secret** in the field
6. Click **Save Google Calendar Settings**
7. Click **Connect Google Calendar** button
8. Authorize the application in Google
9. You'll be redirected back and see "✓ Connected"

#### Step 5: Test

1. Book a test appointment
2. Check your Google Calendar - event should appear automatically
3. Event should include Google Meet link

### Troubleshooting

**Issue: "Error exchanging code"**

- Check that redirect URI matches exactly in Google Console
- Ensure Client ID and Secret are correct
- Check that Calendar API is enabled

**Issue: "Access denied"**

- Make sure you're logged in with the correct Google account
- Check OAuth consent screen is configured
- Verify scopes include calendar.events

**Issue: Events not creating**

- Check OAuth status shows "Connected"
- Verify Google Calendar API is enabled in Cloud Console
- Check WordPress error logs for API errors

---

## 📖 Usage

### For Site Administrators

1. **View Appointments**: Go to **Appointments** in WordPress admin
2. **Manage Appointments**:
   - View all bookings with customer details
   - Edit Google Meet links
   - Add events to Google Calendar
   - Delete appointments
3. **Configure Settings**: Adjust working hours, emails, timezone, etc.

### For Customers

1. Visit the page with `[appointment_scheduler]` shortcode
2. Select an available date from the calendar
3. Choose a time slot from available times (duration is automatically calculated)
4. Fill in the booking form:
   - Name (required)
   - Email (required)
   - Phone (optional)
   - Guests (optional)
   - Message (optional)
5. Click **Book Appointment**
6. Receive confirmation email with:
   - Appointment details
   - Google Meet link
   - Cancellation link

---

## 📝 Shortcode

### Basic Usage

```
[appointment_scheduler]
```

### With Custom Email

```
[appointment_scheduler email="custom@example.com"]
```

**Note:** The custom email parameter is optional. By default, the admin email from settings is used.

---

## 📸 Screenshots

### Main Appointment Scheduler Interface

![Appointment Scheduler Interface](./screenshots/appointment-scheduler-main.png)

_The main appointment scheduling interface showing the calendar on the left and available time slots on the right._

### Admin Settings Page

![Admin Settings](./screenshots/appointment-scheduler-settings.png)

_Admin settings page where you can configure working hours, time intervals, timezone, email notifications, and Google Calendar API._

### Admin Bookings Dashboard

![Admin Bookings](./screenshots/appointment-scheduler-bookings.png)

_Admin dashboard showing all booked appointments with customer details, Meet links, and management options._

**Note:** Screenshot images should be placed in the `screenshots` folder. See `screenshots/README.md` for instructions on taking and adding screenshots.

---

## 🔄 Changelog

### Version 1.3.0 (Current)

**New Features:**

- ✅ **Guest Support**: Users can now add guest emails to invites
- ✅ **Cancellation System**: Users receive a cancellation link to cancel appointments
- ✅ **Smart Slot Status**: Differentiates between 'Booked' slots and 'Time Passed' slots
- ✅ **Dynamic Time Display**: Modal now shows "Start Time - End Time" based on duration interval
- ✅ **Modal Scroll Fix**: Fixed scrolling issue on booking modal for better UX
- ✅ **Email Improvements**: Fixed "Unknown Sender" issue and improved email templates

### Version 1.2.0

**New Features:**

- ✅ **Multiple Admin Emails**: Support for multiple notification recipients
- ✅ **Google Calendar Sync**: Attendees (guests) are now added to Google Calendar events
- ✅ **Improved Validation**: Better email and form validation logic

### Version 1.1.0

**New Features:**

- ✅ Google Calendar API integration with automatic event creation
- ✅ Automatic Google Meet link generation and addition to calendar events
- ✅ OAuth 2.0 authentication for Google Calendar
- ✅ Duplicate booking prevention - same time slot cannot be booked twice
- ✅ Booked dates display in calendar with "Booked" badge
- ✅ Admin can edit Google Meet links from dashboard
- ✅ Admin can delete appointments from bookings list
- ✅ Multiple admin email support (comma-separated)
- ✅ Email reminder system (1 day, 1 hour, 15 minutes before)
- ✅ Improved responsive design with overflow handling
- ✅ Cache busting for CSS/JS files using file modification time
- ✅ Blue color theme throughout the interface
- ✅ Enhanced spacing and border styling with !important flags

**Improvements:**

- Better error handling for Google Calendar API
- Improved email templates with Meet links
- Enhanced admin dashboard UI
- Better mobile responsiveness

**Bug Fixes:**

- Fixed CSS caching issue
- Fixed Meet link inconsistency between admin and user emails
- Fixed timezone display format
- Fixed settings page PHP errors

### Version 1.0.0

- Initial release
- Basic appointment scheduling
- Calendar and time slot selection
- Email notifications
- Admin dashboard

---

## 🛠️ Technical Details

### Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- MySQL 5.6 or higher

### Database

The plugin creates a custom table: `wp_appointment_bookings` with the following structure:

- `id` - Primary key
- `name` - Customer name
- `email` - Customer email
- `phone` - Customer phone
- `appointment_date` - Appointment date
- `appointment_time` - Appointment time
- `message` - Customer message
- `meet_link` - Google Meet link
- `reminder_sent_15min` - 15min reminder flag
- `reminder_sent_1hr` - 1hr reminder flag
- `reminder_sent_1day` - 1day reminder flag
- `created_at` - Creation timestamp

### Hooks & Filters

**Actions:**

- `appointment_scheduler_send_reminders` - Cron hook for sending reminders

**AJAX Actions:**

- `submit_appointment` - Submit new appointment
- `get_time_slots` - Get available time slots for a date
- `delete_appointment` - Delete an appointment (admin only)
- `add_to_google_calendar` - Generate Google Calendar link (admin only)
- `update_meet_link` - Update Meet link (admin only)

### File Structure

```
appointment-scheduler/
├── appointment-scheduler.php (Main plugin file)
├── README.md (This file)
├── assets/
│   ├── css/
│   │   └── style.css (Frontend styles)
│   └── js/
│       ├── script.js (Frontend JavaScript)
│       └── admin.js (Admin JavaScript)
├── templates/
│   ├── scheduler.php (Frontend template)
│   ├── admin-page.php (Admin bookings page)
│   └── settings-page.php (Settings page)
└── screenshots/
    └── README.md (Screenshot instructions)
```

---

## 🔒 Security

- All user inputs are sanitized and validated
- Nonce verification for all AJAX requests
- Capability checks for admin functions
- SQL prepared statements to prevent SQL injection
- Email validation before sending

---

## 📧 Support

For support, feature requests, or bug reports, please contact:

**Author:** M.Aimal  
**Email:** [Your Email]  
**Website:** [Your Website]

---

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2024 M.Aimal

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

---

## 🙏 Credits

- Built with WordPress
- Uses Google Calendar API
- jQuery for frontend interactions
- Modern CSS for responsive design

---

**Thank you for using Appointment Scheduler!** 🎉
