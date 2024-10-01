
# Abandoned Cart Recovery Plugin for WooCommerce

**Version:** 1.0  
**Author:Dimitris Tsintzelis 
**Plugin Name:** Abandoned Cart Recovery Plugin  
**Requires:** WordPress 5.5+ and WooCommerce 4.0+  
Here's the updated `README.md` file, with the additional configuration step regarding the **Threshold Page**:

## Table of Contents
1. [Project Overview](#project-overview)
2. [Features](#features)
3. [Installation](#installation)
4. [Configuration and Usage](#configuration-and-usage)
5. [Technologies Used](#technologies-used)
6. [Screenshots](#screenshots) (optional)
7. [Support](#support)

## Project Overview
The **Abandoned Cart Recovery Plugin for WooCommerce** is a custom plugin designed to help online stores track and recover abandoned carts. By sending automated recovery emails to customers, the plugin aims to enhance user engagement, increase conversion rates, and ultimately reduce cart abandonment rates. This plugin also features a robust admin dashboard for managing email templates, tracking abandoned orders, and viewing user subscription statuses.

## Features
- **Real-Time Cart Tracking:** Captures user information and cart details when items are added to the cart but the checkout process is not completed.
- **Custom Email Notifications:** Sends personalized recovery emails to users, with cart contents and available discount codes.
- **Subscription Management:** Allows users to subscribe or unsubscribe from receiving recovery emails.
- **Admin Dashboard:** Provides an intuitive interface for viewing and managing abandoned carts, customizing email templates, and sending manual email notifications.
- **Dynamic Cart Updates:** Avoids duplicate entries by updating the existing user cart record when additional items are added.

## Installation
1. Download the plugin files and place them in the `/wp-content/plugins/` directory.
2. Activate the plugin through the WordPress Admin dashboard by navigating to **Plugins > Installed Plugins** and clicking on **Activate** under the "Abandoned Cart Recovery Plugin" entry.
3. Ensure WooCommerce is installed and activated.

## Configuration and Usage
### 1. Setting Up the Plugin:
   - After activation, navigate to the **Abandoned Cart Recovery** menu in the WordPress admin dashboard.
   - Set up your email template under the **Email Template** page by filling out the Subject, Salutation, Body, Closing, and Signature fields.

### 2. Setting Up Email Delivery:
   - To send recovery emails, you must set up the **WP Mail SMTP** plugin:
     1. Install and activate the **WP Mail SMTP** plugin.
     2. Configure your SMTP settings by navigating to **WP Mail SMTP > Settings** in the WordPress admin menu.
     3. Choose your desired mailer service (e.g., Gmail, SendGrid, Mailgun, etc.) and complete the necessary configurations.
     4. Save the changes and run a test email to ensure proper setup.
   - The plugin uses `PHPMailer` for sending emails, and configuring the **WP Mail SMTP** plugin is essential for ensuring emails are delivered successfully.

### 3. Configuring the Abandonment Threshold:
   - Before tracking abandoned carts, set the abandonment threshold to define when a cart is considered abandoned.
     - Go to the **Threshold Settings** page under the **Abandoned Cart Recovery** menu.
     - Set the number of minutes/hours/days after which a cart is considered abandoned if the user has not proceeded to checkout.
   - Once the threshold is configured, abandoned carts will be displayed in the **Abandoned Orders** page.

### 4. Viewing Abandoned Carts:
   - Go to the **Abandoned Orders** page to view a list of all abandoned carts, including user information, cart contents, and subscription status.
   - Use the checkboxes to select users and manually send email reminders.

### 5. Managing Email Templates:
   - The **Email Template** page allows you to customize the content of recovery emails.
   - Choose from available WooCommerce coupons and add them to the template.
   - Save the template to be used in manual or automated email notifications.

### 6. Sending Email Notifications:
   - From the **Abandoned Orders** page, select one or more users and click **Send Email** to send a recovery email using the saved template.
   - The email will include the cart contents and a link for users to return and complete their purchase.

### 7. Tracking Recovery Statistics:
   - Navigate to the **Order Analytics** page to view detailed statistics about recovery emails and their effectiveness.
   - This page shows metrics such as:
     - Number of recovery emails sent.
     - Orders placed through email links.
     - Revenue generated from recovered orders.

## Technologies Used
- **WordPress & WooCommerce**: Core platforms for creating the plugin and managing e-commerce functionalities.
- **PHP**: Server-side language for plugin development, database interactions, and email handling.
- **HTML/CSS**: Used to create and style the admin dashboard interface and email templates.
- **JavaScript**: Enhances interactivity and functionality on the admin dashboard.
- **MySQL**: Database system for storing abandoned cart data and user information.
- **PHPMailer**: A PHP library used for sending email notifications from the plugin.
- **WP Mail SMTP**: Plugin used to configure and manage SMTP settings for reliable email delivery.
- **AJAX**: Asynchronous JavaScript for real-time communication between the front-end and back-end.



## Support
For any issues or support related to this plugin, please contact the author through the WordPress support forum or open an issue on the plugin's repository.



