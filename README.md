# Appointment Scheduler - Complete Documentation

## Overview

A beautiful, professional appointment scheduling system for WordPress. This plugin allows users to book appointments through a modern interface, automatically creates Google Calendar events with Meet links, and redirects users to a customizable premium "Thank You" page.

---

## ✨ Key Features

### 1. Modern Booking Scheduler

- **Visual Calendar:** Easy date selection with real-time availability.
- **Dynamic Time Slots:** Configurable intervals (30, 60 mins etc.).
- **Guest Support:** Users can add multiple guest emails to the booking.
- **Admin Dashboard:** Manage all appointments under **Appointments → All Appointments**.

### 2. Google Calendar Integration

- **Automatic Events:** Creates events in the admin's Google Calendar.
- **Google Meet:** Automatically generates and includes video meeting links.
- **Smart Invites:** Sends calendar invites to:
  - Admin (Organizer)
  - User (Attendee)
  - All guests provided by user
  - Additional team emails configured in settings

### 3. Premium Thank You Page (Bolt+ Style)

- **Automatic Redirect:** Seamlessly transitions users after a successful booking.
- **Enhanced Design:** Beautiful animations, gradients, and modern layout based on the Bolt+ React component.
- **Dynamic Content:** Displays the user's specific appointment details (Name, Email, Date, Time).
- **Dashboard Editing:** Fully customizable via **Appointments → Thank You Messages**. You can edit:
  - Logo and Brand Colors
  - Headings and multi-paragraph descriptions
  - Animated Statistics (Numbers + Labels)
  - Scrolling Image Carousel
  - Primary and Secondary Action Buttons

---

## 🚀 Setup Guide

### Step 1: Create the Thank You Page

1. Go to **Pages → Add New**.
2. Title it "Thank You" and add the shortcode: `[appointment_thankyou]`.
3. Publish and copy the URL.

### Step 2: Configure Plugin Settings

1. Go to **Appointments → Settings**.
2. **Thank You Page URL**: Paste the URL from Step 1.
3. **Google Calendar API**: Paste your Client ID and Secret (from Google Cloud Console).
4. **Authorized Redirect URI**: Ensure the URI shown at the bottom of the settings page is added to your Google App.
5. Click **Connect Google Calendar** to authorize.

### Step 3: Customize the Message

1. Go to **Appointments → Thank You Messages**.
2. Click **Add New**.
3. Use the fields below the editor to set your logo, headings, stats, and carousel images.
4. **Publish**. The latest published message will be used.

---

## 🛠️ Developer Details

### Shortcodes

- `[appointment_scheduler]` - The main booking form.
- `[appointment_thankyou]` - The premium thank you page.

### Files

- `appointment-scheduler.php` - Core logic and integration.
- `templates/thankyou-bolt.php` - The premium template.
- `assets/css/thankyou-bolt.css` - Bolt+ styling and animations.

---

**Version:** 1.3.0  
**Status:** Production Ready
