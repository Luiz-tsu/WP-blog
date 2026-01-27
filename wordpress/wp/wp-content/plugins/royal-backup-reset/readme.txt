=== Royal Backup, Restore & Reset ===
Contributors: wproyal
Tags: backup, restore, reset, database
Stable tag: 1.0.10
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete backup, restore and reset functionality for WordPress websites. Simple, powerful, and reliable.

== Description ==

Royal Backup, Restore & Reset is a comprehensive WordPress plugin that provides complete backup, restore, and reset functionality for your WordPress website. Whether you need to create regular backups, migrate your site, or reset your database, this plugin has you covered.

= Key Features =

* **Full Website Backups** - Backup your entire WordPress website including database, plugins, themes, uploads, and other files
* **Selective Restore** - Choose which components to restore (database, plugins, themes, uploads, others)
* **Database Reset** - Reset your WordPress database to a fresh installation while preserving user accounts
* **Progress Tracking** - Real-time progress updates during backup and restore operations
* **Backup Management** - View, download, restore, and delete backups from a simple interface
* **Component Downloads** - Download individual backup components (database, plugins, themes, etc.)
* **Customizable Defaults** - Save your preferred backup and restore settings

= Use Cases =

* **Regular Backups** - Schedule or manually create backups of your WordPress site
* **Development & Testing** - Reset your database for testing or development purposes
* **Disaster Recovery** - Quickly restore your site from a backup if something goes wrong
* **Pre-Update Safety** - Create a backup before updating plugins, themes, or WordPress core

= Technical Features =

* Built following WordPress coding standards and security best practices
* Nonce verification and capability checks on all operations
* Proper input sanitization and output escaping
* Resumable backups for large websites
* AJAX-powered interface for seamless user experience

== Installation ==

1. Upload the `royal-backup-reset` folder to the `/wp-content/plugins/` directory
2. Activate the Royal Backup, Restore & Reset plugin through the 'Plugins' menu in WordPress
3. In the WP appearance menu go to Royal Backup  to start using the plugin
4. Create your first backup using the "Create Backup" tab > Press "Start Backup Process" Button

== Frequently Asked Questions ==

= Where are backups stored? =

Backups are stored in the `wp-content/royal-backup-reset/` directory by default. This directory is protected with .htaccess rules to prevent direct web access.

= Can I schedule automatic backups? =

This is only supported in Premium Version.

= What gets included in a backup? =

A backup includes:
* Database (all WordPress tables)
* Plugins folder
* Themes folder
* Uploads folder (Where images, videos and similar files are stored)

= Is it safe to reset my database? =

The database reset feature will delete all your Content and Settings.. Your current user account will be preserved. **Always create a backup before resetting!**


== Screenshots ==

1. Main backup interface showing existing backups
2. Backup creation with file selection options
3. Restore component selection modal
4. Database reset options
5. Settings page for default preferences

== Changelog ==

= 1.0.10 =
* Performance Improvements.
* Added Video Tutorial.

= 1.0.9 =
* Performance Improvements.

= 1.0.2 =
* Initial release.