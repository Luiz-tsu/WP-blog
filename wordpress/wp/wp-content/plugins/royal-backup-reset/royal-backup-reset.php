<?php
/**
 * Plugin Name: Royal Backup, Restore & Reset
 * Plugin URI: http://wordpress.org/plugins/royal-backup-reset/
 * Description: Complete backup, restore and reset functionality for WordPress websites.
 * Author: wproyal
 * Version: 1.0.10
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Tested up to: 6.9
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: royal-backup-reset
 *
 * @package RoyalBackupReset
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent dual loading - if core already loaded by another version, bail out
if ( defined( 'ROYALBR_CORE_LOADED' ) ) {
    return;
}
define( 'ROYALBR_CORE_LOADED', true );

// Auto-deactivate free version if premium is active
require_once __DIR__ . '/includes/premium-plugin-activation.php';

// Freemius SDK Initialization.
if ( ! function_exists( 'royalbr_fs' ) ) {
    // Create a helper function for easy SDK access.
    function royalbr_fs() {
        global $royalbr_fs;

        if ( ! isset( $royalbr_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';

            // Check if premium folder exists to conditionally show license UI.
            $premium_exists = is_dir( dirname( __FILE__ ) . '/premium' );

            $royalbr_fs = fs_dynamic_init( array(
                'id'                  => '21745',
                'slug'                => 'royal-backup-reset',
                'type'                => 'plugin',
                'public_key'          => 'pk_c3ccaa8bde894c4aade87b542acf6',
                'is_premium'          => $premium_exists,
                'is_premium_only'     => $premium_exists,
                'has_premium_version' => !$premium_exists,
                'premium_suffix'      => 'Pro',
                'has_addons'          => false,
                'menu'                => array(
                    'slug'           => 'royal-backup-reset',
                    'contact'        => false,
                    'pricing'        => false,
                ),
            ) );
        }

        return $royalbr_fs;
    }

    // Init Freemius.
    royalbr_fs();
    // Signal that SDK was initiated.
    do_action( 'royalbr_fs_loaded' );

    // Add Video Tutorial submenu link via Freemius hook (after translations loaded).
    royalbr_fs()->add_action( 'before_admin_menu_init', 'royalbr_add_video_tutorial_menu' );

    // Auto-skip opt-in on first activation.
    if ( ! royalbr_fs()->is_registered() && ! royalbr_fs()->is_anonymous() ) {
        royalbr_fs()->skip_connection();
    }

    // Hook cleanup function to Freemius uninstall action.
    royalbr_fs()->add_action( 'after_uninstall', 'royalbr_uninstall_cleanup' );
}

/**
 * Adds Video Tutorial submenu link.
 *
 * @since 1.0.10
 */
function royalbr_add_video_tutorial_menu() {
    royalbr_fs()->add_submenu_link_item(
        __( 'Video Tutorial', 'royal-backup-reset' ),
        'https://www.youtube.com/watch?v=_zvnGIb97Pc',
        'video-tutorial',
        'manage_options',
        55,
        true,
        '',
        true
    );
}

/**
 * Shared uninstall cleanup function.
 *
 * Called by both uninstall.php and Freemius after_uninstall hook.
 * Removes all plugin data including options, transients, files, and task data.
 *
 * @since 1.0.0
 */
if ( ! function_exists( 'royalbr_uninstall_cleanup' ) ) {
function royalbr_uninstall_cleanup() {
	// Clean up plugin options.
	// Note: royalbr_backup_history is NOT deleted because backup files are preserved.
	delete_option( 'royalbr_backup_nonce' );
	delete_option( 'royalbr_version' );
	delete_option( 'royalbr_settings' );

	// Clean up rating notice options.
	delete_option( 'royalbr_activation_time' );
	delete_option( 'royalbr_maybe_later_time' );
	delete_option( 'royalbr_rating_dismissed' );
	delete_option( 'royalbr_already_rated' );

	// Delete site options (for multisite compatibility).
	delete_site_option( 'royalbr_restore_in_progress' );
	delete_site_option( 'royalbr_settings' );

	// Delete any transients.
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_royalbr_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_royalbr_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Delete all taskdata options (stored as royalbr_taskdata_123456789).
	$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'royalbr_taskdata_' ) . '%'
	) );

	// Delete sitemeta for multisite.
	if ( is_multisite() ) {
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'royalbr_taskdata_' ) . '%'
		) );
	}

	// Note: Backup files in wp-content/royal-backup-reset are NOT deleted during uninstall.

	// Clear any object cache.
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}
} // End if function_exists( 'royalbr_uninstall_cleanup' )

// Define plugin file path for use throughout the plugin.
if ( ! defined( 'ROYALBR_PLUGIN_FILE' ) ) {
	define( 'ROYALBR_PLUGIN_FILE', __FILE__ );
}

// Define plugin directory path before loading configuration files.
if ( ! defined( 'ROYALBR_PLUGIN_DIR' ) ) {
	define( 'ROYALBR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Set plugin version for asset cache busting and compatibility checks.
if ( ! defined( 'ROYALBR_VERSION' ) ) {
	define( 'ROYALBR_VERSION', '1.0.10' );
}

// Initialize plugin-wide constants including paths and configuration.
require_once ROYALBR_PLUGIN_DIR . 'includes/core/royalbr-constants.php';

// Load premium features if license is active AND premium folder exists.
if ( function_exists( 'royalbr_fs' ) && royalbr_fs()->can_use_premium_code() ) {
	$premium_loader = ROYALBR_PLUGIN_DIR . 'premium/class-royalbr-premium-loader.php';
	if ( file_exists( $premium_loader ) ) {
		require_once $premium_loader;
	} else {
		// License active but premium folder missing - flag for admin notice.
		define( 'ROYALBR_LICENSE_WITHOUT_PRO', true );
	}
}

// Load tour manager class.
require_once ROYALBR_INCLUDES_DIR . 'class-royalbr-tour.php';

// Load rating notice class.
require_once ROYALBR_INCLUDES_DIR . 'rating/class-royalbr-rating-notice.php';

/**
 * Main Royal Backup & Reset Plugin Class.
 *
 * Handles initialization, admin menu, AJAX handlers, and coordinates
 * backup, restore, and reset functionality.
 *
 * @since 1.0.0
 */
if ( ! class_exists( 'RoyalBackupReset' ) ) {
class RoyalBackupReset {

	/**
	 * Manages database and file backup operations.
	 *
	 * @since 1.0.0
	 * @var   ROYALBR_Backup
	 */
	private $backup_handler;

	/**
	 * Handles extraction and restoration of backup archives.
	 *
	 * @since 1.0.0
	 * @var   ROYALBR_Restore
	 */
	private $restore_handler;

	/**
	 * Manages database reset to fresh WordPress installation.
	 *
	 * @since 1.0.0
	 * @var   ROYALBR_Reset
	 */
	private $reset_handler;

	/**
	 * Unique session identifier used in backup filenames and task tracking.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $file_nonce;

	/**
	 * Unix timestamp marking when the backup operation was initiated.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public $backup_time;

	/**
	 * Runtime task information stored in memory and persisted to database.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	private $taskdata = array();

	/**
	 * High-precision start time for calculating restore operation duration.
	 *
	 * @since 1.0.0
	 * @var   float
	 */
	public $task_time_ms;

	/**
	 * Current resumption number for multi-resumption backup system.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public $current_resumption = 0;

	/**
	 * Timestamp when next resumption is scheduled.
	 *
	 * @since 1.0.0
	 * @var   int|false
	 */
	public $newresumption_scheduled = false;

	/**
	 * Whether something useful happened during this resumption.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $something_useful_happened = false;

	/**
	 * Timestamp when log file was opened (for runtime tracking).
	 *
	 * @since 1.0.0
	 * @var   float
	 */
	public $opened_log_time = 0;

	/**
	 * Last resumption number that made meaningful progress.
	 *
	 * Used to detect stalled backups and adjust batch sizes.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public $last_successful_resumption = -1;

	/**
	 * Whether the previous resumption failed to check in.
	 *
	 * Used to trigger adaptive batch size reduction.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $no_checkin_last_time = false;

	/**
	 * Semaphore lock instance for preventing concurrent backups.
	 *
	 * Public so task scheduler can refresh lock during long operations.
	 *
	 * @since 1.0.0
	 * @var   ROYALBR_Semaphore|null
	 */
	public $semaphore = null;

	/**
	 * Initializes plugin hooks and loads required class files.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Include all class files needed for plugin functionality.
		require_once ROYALBR_INCLUDES_DIR . 'class-royalbr-backup-history.php';
		require_once ROYALBR_INCLUDES_DIR . 'class-royalbr-backup.php';
		require_once ROYALBR_INCLUDES_DIR . 'class-royalbr-restore.php';
		require_once ROYALBR_INCLUDES_DIR . 'class-royalbr-reset.php';
		require_once ROYALBR_INCLUDES_DIR . 'class-royalbr-options.php';
		require_once ROYALBR_INCLUDES_DIR . 'class-royalbr-task-scheduler.php';
		require_once ROYALBR_INCLUDES_DIR . 'class-royalbr-semaphore.php';

		// Delay handler instantiation until needed to reduce memory usage on page load.
		$this->backup_handler  = null;
		$this->restore_handler = null;
		$this->reset_handler   = null;
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( 'ROYALBR_Options', 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_royalbr_get_backup_nonce', array( $this, 'get_backup_nonce_ajax' ) );
		add_action( 'wp_ajax_royalbr_create_backup', array( $this, 'create_backup_ajax' ) );
		add_action( 'wp_ajax_royalbr_restore_backup', array( $this, 'restore_backup_ajax' ) );
		add_action( 'wp_ajax_royalbr_delete_backup', array( $this, 'delete_backup_ajax' ) );
		add_action( 'wp_ajax_royalbr_download_component', array( $this, 'download_component_ajax' ) );
		add_action( 'wp_ajax_royalbr_reset_database', array( $this, 'reset_database_ajax' ) );
		add_action( 'wp_ajax_royalbr_before_reset', array( $this, 'before_reset_ajax' ) );
		add_action( 'wp_ajax_royalbr_get_settings', array( $this, 'get_settings_ajax' ) );
		add_action( 'wp_ajax_royalbr_save_settings', array( $this, 'save_settings_ajax' ) );
		add_action( 'wp_ajax_royalbr_get_backup_progress', array( $this, 'get_backup_progress_ajax' ) );
		add_action( 'wp_ajax_royalbr_get_log', array( $this, 'get_log_ajax' ) );
		add_action( 'wp_ajax_royalbr_stop_backup', array( $this, 'stop_backup_ajax' ) );
		add_action( 'wp_ajax_royalbr_ajax_restore', array( $this, 'royalbr_ajaxrestore' ) );
		add_action( 'wp_ajax_royalbr_ajaxrestore_continue', array( $this, 'royalbr_ajaxrestore' ) );
		add_action( 'wp_ajax_royalbr_get_restore_log', array( $this, 'get_restore_log_ajax' ) );
		add_action( 'wp_ajax_royalbr_get_backup_list', array( $this, 'get_backup_list_ajax' ) );
		add_action( 'wp_ajax_royalbr_get_backup_list_for_popup', array( $this, 'get_backup_list_for_popup_ajax' ) );
		add_action( 'wp_ajax_royalbr_get_backup_modal_html', array( $this, 'get_backup_modal_html_ajax' ) );
		add_action( 'wp_ajax_royalbr_get_backup_progress_modal_html', array( $this, 'get_backup_progress_modal_html_ajax' ) );
		add_action( 'wp_ajax_royalbr_get_log_viewer_modal_html', array( $this, 'get_log_viewer_modal_html_ajax' ) );
		add_action( 'wp_ajax_royalbr_get_confirmation_modal_html', array( $this, 'get_confirmation_modal_html_ajax' ) );
		add_action( 'wp_ajax_royalbr_get_progress_modal_html', array( $this, 'get_progress_modal_html_ajax' ) );
		add_action( 'wp_ajax_royalbr_get_component_selection_modal_html', array( $this, 'get_component_selection_modal_html_ajax' ) );
		add_action( 'wp_ajax_royalbr_get_reset_progress_modal_html', array( $this, 'get_reset_progress_modal_html_ajax' ) );
		add_action( 'wp_ajax_royalbr_get_pro_modal_html', array( $this, 'get_pro_modal_html_ajax' ) );
		add_action( 'wp_ajax_royalbr_download_log', array( $this, 'download_log_ajax' ) );
		add_action( 'wp_ajax_royalbr_download_restore_log', array( $this, 'download_restore_log_ajax' ) );
		add_action( 'wp_ajax_royalbr_test_scheduled_files', array( $this, 'test_scheduled_files_ajax' ) );
		add_action( 'wp_ajax_royalbr_test_scheduled_database', array( $this, 'test_scheduled_database_ajax' ) );
		add_action( 'wp_ajax_royalbr_dismiss_backup_reminder', array( $this, 'dismiss_backup_reminder_ajax' ) );

		// Register WP-Cron hook for backup resumption.
		add_action( 'royalbr_backup_resume', array( $this, 'backup_resume' ), 10, 2 );

		add_action( 'admin_notices', array( $this, 'reset_success_notice' ) );
		add_action( 'admin_notices', array( $this, 'backup_complete_notice' ) );

		// Check disk space and show warning if low (50 MB threshold).
		if ( $this->check_disk_space( 1048576 * 50 ) === false ) {
			add_action( 'admin_notices', array( $this, 'show_low_disk_space_warning' ) );
		}

		// Hide dismiss button on Freemius upgrade notice when license active but Pro not installed.
		if ( defined( 'ROYALBR_LICENSE_WITHOUT_PRO' ) && ROYALBR_LICENSE_WITHOUT_PRO ) {
			add_action( 'admin_head', array( $this, 'hide_freemius_dismiss_button' ) );
		}

		add_action( 'admin_bar_menu', array( $this, 'wp_before_admin_bar_render' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_bar_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_bar_scripts' ) );
		add_filter( ( is_multisite() ? 'network_admin_' : '' ) . 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Retrieves backup handler, creating instance on first access.
	 *
	 * @since  1.0.0
	 * @return ROYALBR_Backup Backup handler instance.
	 */
	private function get_backup_handler() {
		if ( null === $this->backup_handler ) {
			$this->backup_handler = new ROYALBR_Backup( $this );
		}
		return $this->backup_handler;
	}

	/**
	 * Retrieves restore handler, creating instance with silent feedback on first access.
	 *
	 * @since  1.0.0
	 * @return ROYALBR_Restore Restore handler instance.
	 */
	private function get_restore_handler() {
		if ( null === $this->restore_handler ) {
			// Initialize silent feedback handler to prevent WordPress upgrade notifications during restore.
			$silent_skin           = new ROYALBR_Silent_Skin();
			$this->restore_handler = new ROYALBR_Restore( $silent_skin );
		}
		return $this->restore_handler;
	}

	/**
	 * Retrieves reset handler singleton instance.
	 *
	 * @since  1.0.0
	 * @return ROYALBR_Reset Reset handler instance.
	 */
	private function get_reset_handler() {
		if ( null === $this->reset_handler ) {
			$this->reset_handler = ROYALBR_Reset::get_instance();
		}
		return $this->reset_handler;
	}

	/**
	 * Retrieves active theme and plugin information for reset preview display.
	 *
	 * @since  1.0.0
	 * @return array Theme name and active plugins list.
	 */
	public function get_reset_info() {
		$reset_handler = $this->get_reset_handler();
		return array(
			'theme_name'     => $reset_handler->get_active_theme_name(),
			'active_plugins' => $reset_handler->get_active_plugins_list(),
		);
	}

	/**
	 * Performs plugin initialization tasks on WordPress init hook.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Translation files load automatically in WordPress 6.7+, no manual loading required.
		$this->create_backup_directory();

		// Register filter to exclude specific directory types from backups.
		add_filter( 'royalbr_exclude_directory', array( $this, 'exclude_git_worktrees' ), 10, 3 );
	}

	/**
	 * Excludes git worktree directories from backup archives.
	 *
	 * Git worktrees contain a .git file pointing to the main repository,
	 * rather than a .git directory, and should be excluded from backups.
	 *
	 * @since  1.0.0
	 * @param  bool   $exclude              Whether to exclude the directory.
	 * @param  string $fullpath             Full path to the directory.
	 * @param  string $use_path_when_storing Path used for storage in zip.
	 * @return bool True to exclude, false otherwise.
	 */
	public function exclude_git_worktrees( $exclude, $fullpath, $use_path_when_storing ) {
		// Verify if directory is a git worktree by checking for .git file with gitdir pointer.
		$git_file = $fullpath . '/.git';
		if ( is_file( $git_file ) ) {
			$git_content = @file_get_contents( $git_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( $git_content && 0 === strpos( trim( $git_content ), 'gitdir:' ) ) {
				return true;
			}
		}
		return $exclude;
	}

	/**
	 * Creates backup directory during plugin activation.
	 *
	 * @since 1.0.0
	 */
	public function activate() {
		$this->create_backup_directory();

		// Track activation time for rating notice (only set once).
		if ( false === get_option( 'royalbr_activation_time' ) ) {
			update_option( 'royalbr_activation_time', strtotime( 'now' ) );
		}
	}

	/**
	 * Performs cleanup tasks when plugin is deactivated.
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
	}

	/**
	 * Creates backup storage directory with security protection files.
	 *
	 * @since 1.0.0
	 */
	public function create_backup_directory() {
		if ( ! file_exists( ROYALBR_BACKUP_DIR ) ) {
			wp_mkdir_p( ROYALBR_BACKUP_DIR );
			file_put_contents( ROYALBR_BACKUP_DIR . '.htaccess', 'deny from all' );
			file_put_contents( ROYALBR_BACKUP_DIR . 'index.php', '<?php // Silence is golden' );
		}
	}

	/**
	 * Adds Settings link to plugin action links on Plugins page.
	 *
	 * @since  1.0.0
	 * @param  array  $links Set of links for the plugin, before being filtered.
	 * @param  string $file  File name (relative to the plugin directory).
	 * @return array  Filtered results.
	 */
	public function plugin_action_links( $links, $file ) {
		if ( is_array( $links ) && plugin_basename( __FILE__ ) === $file ) {
			$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=royal-backup-reset#settings' ) ) . '" class="js-royalbr-settings">' . esc_html__( 'Settings', 'royal-backup-reset' ) . '</a>';
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	/**
	 * Registers the plugin's admin menu page in WordPress dashboard.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_menu_page(
			'Royal Backup',
			'Royal Backup',
			'manage_options',
			'royal-backup-reset',
			array( $this, 'admin_page' ),
			'dashicons-backup',
			81 // Position right after Settings (which is at 80).
		);
	}

	/**
	 * Loads CSS and JavaScript assets for admin interface.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Load stylesheets globally for admin bar elements visible on all pages.
		wp_enqueue_style( 'royalbr-admin-css', ROYALBR_ASSETS_URL . 'admin.css', array(), ROYALBR_VERSION );

		// Load JavaScript only on plugin's settings page to avoid conflicts.
		if ( 'toplevel_page_royal-backup-reset' !== $hook ) {
			return;
		}

		// Load shared utilities first (already loaded by admin bar, but ensure it's here).
		wp_enqueue_script( 'royalbr-utilities-js', ROYALBR_ASSETS_URL . 'royalbr-utilities.js', array( 'jquery' ), ROYALBR_VERSION, true );

		wp_enqueue_script( 'royalbr-admin-js', ROYALBR_ASSETS_URL . 'admin.js', array( 'jquery', 'royalbr-utilities-js' ), ROYALBR_VERSION, true );

		wp_localize_script(
			'royalbr-admin-js',
			'royalbr_ajax',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'royalbr_nonce' ),
				'restore_nonce' => wp_create_nonce( 'royalbr_initiate_restore' ),
				'is_premium'    => function_exists( 'royalbr_fs' ) && royalbr_fs()->can_use_premium_code(),
				'is_multisite'  => is_multisite(),
				'active_backup' => $this->get_active_backup_status(),
			)
		);

		wp_localize_script(
			'royalbr-admin-js',
			'royalbr_admin',
			array(
				'strings' => array(
					'preparing'                 => __( 'Preparing backup...', 'royal-backup-reset' ),
					'backup_complete'           => __( 'Backup completed successfully!', 'royal-backup-reset' ),
					'backup_created'            => __( 'Backup created successfully', 'royal-backup-reset' ),
					'backup_failed'             => __( 'Backup creation failed', 'royal-backup-reset' ),
					'restore_success'           => __( 'Backup restored successfully', 'royal-backup-reset' ),
					'restore_failed'            => __( 'Backup restoration failed', 'royal-backup-reset' ),
					'delete_success'            => __( 'Backup deleted successfully', 'royal-backup-reset' ),
					'delete_failed'             => __( 'Backup deletion failed', 'royal-backup-reset' ),
					'ajax_error'                => __( 'An error occurred. Please try again.', 'royal-backup-reset' ),
					'confirm_restore_title'     => __( 'Confirm Restore', 'royal-backup-reset' ),
					/* translators: %s: Backup filename */
					'confirm_restore_message'   => __( 'Are you sure you want to restore from backup "%s"? This will overwrite your current website data.', 'royal-backup-reset' ),
					'confirm_delete_title'      => __( 'Confirm Delete', 'royal-backup-reset' ),
					/* translators: %s: Backup filename */
					'confirm_delete_message'    => __( 'Are you sure you want to delete the backup "%s"? This action cannot be undone.', 'royal-backup-reset' ),
					'no_backups'                => __( 'No backups found. Create your first backup using the "Backup Website" tab.', 'royal-backup-reset' ),
					'reset_success'             => __( 'Database reset completed successfully', 'royal-backup-reset' ),
					'reset_failed'              => __( 'Database reset failed', 'royal-backup-reset' ),
					'confirm_reset_title'       => __( 'Confirm Database Reset', 'royal-backup-reset' ),
					'confirm_reset_message'     => __( 'Are you absolutely sure you want to reset your WordPress database? <br>It will delete all your Content and Settings.. <br><br><strong>This action cannot be undone!</strong>', 'royal-backup-reset' ),
					'resetting_database'        => __( 'Resetting database...', 'royal-backup-reset' ),
					'please_wait'               => __( 'Please wait, do not close this page...', 'royal-backup-reset' ),
					'pro_feature_default'       => __( 'This feature', 'royal-backup-reset' ),
					'pro_feature_message'       => __( 'is a PRO feature. Upgrade to unlock this and other premium features.', 'royal-backup-reset' ),
				),
			)
		);
	}

	/**
	 * Loads admin bar scripts on all admin pages for quick action buttons.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_bar_scripts( $hook ) {
		// Restrict to administrators who can access plugin features.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Load shared utilities first (used by all scripts).
		wp_enqueue_script( 'royalbr-utilities-js', ROYALBR_ASSETS_URL . 'royalbr-utilities.js', array( 'jquery' ), ROYALBR_VERSION, true );

		// Load core backup/restore functions (used by both admin.js and admin-bar.js).
		wp_enqueue_script( 'royalbr-core-js', ROYALBR_ASSETS_URL . 'royalbr-core.js', array( 'jquery', 'royalbr-utilities-js' ), ROYALBR_VERSION, true );

		// Load admin bar JavaScript for quick backup and reset actions.
		wp_enqueue_script( 'royalbr-admin-bar-js', ROYALBR_ASSETS_URL . 'admin-bar.js', array( 'jquery', 'royalbr-utilities-js', 'royalbr-core-js' ), ROYALBR_VERSION, true );

		// Retrieve default backup configuration from database.
		$default_include_files = (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_backup_include_files', true );

		// Retrieve default reset configuration options.
		$default_reactivate_theme   = (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_reactivate_theme', false );
		$default_reactivate_plugins = (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_reactivate_plugins', false );
		$default_keep_royalbr_active    = (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_keep_royalbr_active', true );
		$default_clear_uploads      = (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_clear_uploads', false );
		$default_clear_media        = (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_clear_media', false );

		// Retrieve default restore configuration options.
		$default_restore_db      = (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_restore_db', true );
		$default_restore_plugins = (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_restore_plugins', false );
		$default_restore_themes  = (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_restore_themes', false );
		$default_restore_uploads = (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_restore_uploads', false );
		$default_restore_others  = (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_restore_others', false );

		// Detect if we're on pages where backup reminder should be shown.
		global $pagenow;
		$is_importer_page   = ( 'admin.php' === $pagenow && ! empty( $_GET['import'] ) );
		$is_reminder_page   = in_array( $hook, array( 'themes.php', 'theme-install.php', 'plugins.php', 'plugin-install.php', 'update-core.php' ), true ) || $is_importer_page;
		$user_id            = get_current_user_id();
		$reminder_dismissed = (bool) get_user_meta( $user_id, 'royalbr_dismiss_backup_reminder', true );

		// Load backup reminder script on themes/plugins pages if not dismissed.
		if ( $is_reminder_page ) {
			wp_enqueue_script(
				'royalbr-backup-reminder-js',
				ROYALBR_ASSETS_URL . 'backup-reminder.js',
				array( 'jquery', 'royalbr-admin-bar-js' ),
				ROYALBR_VERSION,
				true
			);
		}

		// Pass configuration and security data to JavaScript.
		wp_localize_script(
			'royalbr-admin-bar-js',
			'royalbr_admin_bar',
			array(
				'ajax_url'                   => admin_url( 'admin-ajax.php' ),
				'nonce'                      => wp_create_nonce( 'royalbr_nonce' ),
				'admin_page_url'             => admin_url( 'admin.php?page=royal-backup-reset' ),
				'is_premium'                 => function_exists( 'royalbr_fs' ) && royalbr_fs()->can_use_premium_code(),
				'show_quick_actions'         => ! is_dir( ROYALBR_PLUGIN_DIR . 'premium' ) || ( function_exists( 'royalbr_fs' ) && royalbr_fs()->can_use_premium_code() ),
				'is_multisite'               => is_multisite(),
				'is_rbr_page'                => isset( $_GET['page'] ) && $_GET['page'] === 'royal-backup-reset',
				'default_include_files'      => $default_include_files,
				'default_reactivate_theme'   => $default_reactivate_theme,
				'default_reactivate_plugins' => $default_reactivate_plugins,
				'default_keep_royalbr_active'    => $default_keep_royalbr_active,
				'default_clear_uploads'      => $default_clear_uploads,
				'default_clear_media'        => $default_clear_media,
				'default_restore_db'         => $default_restore_db,
				'default_restore_plugins'    => $default_restore_plugins,
				'default_restore_themes'     => $default_restore_themes,
				'default_restore_uploads'    => $default_restore_uploads,
				'default_restore_others'     => $default_restore_others,
				'is_reminder_page'           => $is_reminder_page,
				'reminder_dismissed'         => $reminder_dismissed,
				'reminder_popup_mode'        => ROYALBR_Options::get_royalbr_option( 'royalbr_reminder_popup_mode', 'allow_dismiss' ),
				'reminder_strings'           => array(
					'title'                  => __( 'Create a backup first?', 'royal-backup-reset' ),
					'theme_activation'       => __( 'Consider backing up before activating themes. Takes less than a minute!', 'royal-backup-reset' ),
					'theme_update'           => __( 'Consider backing up before updating themes. Takes less than a minute!', 'royal-backup-reset' ),
					'plugin_activation'      => __( 'Consider backing up before activating plugins. Takes less than a minute!', 'royal-backup-reset' ),
					'plugin_update'          => __( 'Consider backing up before updating plugins. Takes less than a minute!', 'royal-backup-reset' ),
					'bulk_plugin_activation'   => __( 'Consider backing up before activating multiple plugins. Takes less than a minute!', 'royal-backup-reset' ),
					'bulk_plugin_deactivation' => __( 'Consider backing up before deactivating multiple plugins. Takes less than a minute!', 'royal-backup-reset' ),
					'bulk_plugin_update'       => __( 'Consider backing up before updating multiple plugins. Takes less than a minute!', 'royal-backup-reset' ),
					'bulk_theme_update'      => __( 'Consider backing up before updating multiple themes. Takes less than a minute!', 'royal-backup-reset' ),
					'core_update'            => __( 'Consider backing up before updating WordPress. Takes less than a minute!', 'royal-backup-reset' ),
					'wp_import'              => __( 'Consider backing up before importing content. Takes less than a minute!', 'royal-backup-reset' ),
					'dismiss_permanent'      => __( "Don't show again", 'royal-backup-reset' ),
					'skip_now'               => __( 'Skip Now', 'royal-backup-reset' ),
					'proceed_without_backup' => __( 'Proceed without backup', 'royal-backup-reset' ),
				),
			)
		);
	}

	/**
	 * Renders the plugin's admin page or initiates restore flow.
	 *
	 * @since 1.0.0
	 */
	public function admin_page() {
		// Validate restore action parameters before entering restore workflow.
		if ( isset( $_REQUEST['action'] ) &&
		     ( ( 'royalbr_restore' === $_REQUEST['action'] && isset( $_REQUEST['timestamp'] ) ) ||
		       ( 'royalbr_restore_continue' === $_REQUEST['action'] && ! empty( $_REQUEST['task_id'] ) ) ) ) {

			// Verify user permissions and request authenticity.
			if ( ! current_user_can( 'manage_options' ) ||
			     empty( $_REQUEST['restore_nonce'] ) ||
			     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['restore_nonce'] ) ), 'royalbr_initiate_restore' ) ) {
				wp_die( esc_html__( 'Access denied. You do not have permission to view this page.', 'royal-backup-reset' ) );
			}

			$this->prepare_restore();
			return;
		}

		include ROYALBR_INCLUDES_DIR . 'admin-page.php';
	}

	/**
	 * Handles AJAX request to get a backup nonce for placeholder.
	 *
	 * @since 1.0.0
	 */
	public function get_backup_nonce_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Generate a nonce identifier for preview
		$nonce = $this->backup_time_nonce();

		wp_send_json_success( array( 'nonce' => $nonce ) );
	}

	/**
	 * AJAX handler to permanently dismiss backup reminder notification.
	 *
	 * @since 1.0.0
	 */
	public function dismiss_backup_reminder_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		$user_id = get_current_user_id();
		update_user_meta( $user_id, 'royalbr_dismiss_backup_reminder', true );

		wp_send_json_success( array( 'dismissed' => true ) );
	}

	/**
	 * Handles AJAX request to create a new backup.
	 *
	 * @since 1.0.0
	 */
	public function create_backup_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Check if a backup is already running.
		$existing_nonce = get_option( 'royalbr_oneshotnonce', false );
		if ( false !== $existing_nonce ) {
			$existing_taskdata = $this->retrieve_task_array( $existing_nonce );

			// If taskdata exists and backup is not finished/failed, reject new backup.
			if ( ! empty( $existing_taskdata ) ) {
				$taskstatus  = isset( $existing_taskdata['taskstatus'] ) ? $existing_taskdata['taskstatus'] : '';
				$is_complete = ! empty( $existing_taskdata['backup_complete'] );
				$has_error   = ! empty( $existing_taskdata['backup_error'] );

				// Backup is still running if not finished, not complete, and no error.
				if ( 'finished' !== $taskstatus && ! $is_complete && ! $has_error ) {
					wp_send_json_error( esc_html__( 'A backup is already in progress. Please wait for it to complete.', 'royal-backup-reset' ) );
				}
			}
		}

		// Clear any previous backup error.
		delete_option( 'royalbr_backup_error' );

		// Check disk space before proceeding (50MB minimum).
		$disk_check = $this->disk_space_check( 1048576 * 50 );
		if ( false === $disk_check ) {
			wp_send_json_error( esc_html__( 'Insufficient disk space. Please free up at least 50MB of disk space before creating a backup.', 'royal-backup-reset' ) );
		}

		$include_files = isset( $_POST['include_files'] ) && '1' === $_POST['include_files'];
		$include_db    = isset( $_POST['include_db'] ) && '1' === $_POST['include_db'];

		// Clean up taskdata from previous backup if exists.
		$old_task_id = get_option( 'royalbr_oneshotnonce', false );
		if ( false !== $old_task_id ) {
			delete_option( 'royalbr_taskdata_' . $old_task_id );
		}

		// Create unique session identifier for this backup.
		$nonce = $this->backup_time_nonce();

		// Store nonce to enable database operations during backup.
		$this->file_nonce = $nonce;

		// Store custom backup name if provided.
		if ( isset( $_POST['backup_name'] ) ) {
			$backup_name = sanitize_text_field( wp_unslash( $_POST['backup_name'] ) );

			if ( '' !== $backup_name ) {
				$display_names = get_option( 'royalbr_backup_display_names', array() );
				$display_names[ $nonce ] = $backup_name;
				update_option( 'royalbr_backup_display_names', $display_names, false );
			}
		}

		// Initialize taskdata for the new backup task
		$this->save_task_data_multi(
			'backup_time', $this->backup_time,
			'file_nonce', $this->file_nonce,
			'task_time_ms', microtime( true ),
			'taskstatus', 'begun',
			'task_backup_database', $include_db,
			'task_backup_files', $include_files,
			'current_resumption', 0,
			'resume_interval', 120
		);

		// Save session identifier for progress tracking requests.
		update_option( 'royalbr_oneshotnonce', $nonce );

		// Schedule first resumption via WP-Cron (1 second from now).
		// This allows the backup to run in the background via cron.
		wp_schedule_single_event( time() + 1, 'royalbr_backup_resume', array( 0, $nonce ) );

		// Spawn loopback request to trigger WP-Cron (required for Local/shared hosts).
		$this->spawn_cron();

		// Build response for immediate return to client.
		$msg = array(
			'nonce' => $nonce,
			'm'     => __( 'Start backup: OK. Backup is running in background...', 'royal-backup-reset' ),
		);

		// Return immediately - backup will run via WP-Cron.
		wp_send_json_success( $msg );
	}

	/**
	 * Handles AJAX request to restore a backup archive.
	 *
	 * @since 1.0.0
	 */
	public function restore_backup_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		if ( ! isset( $_POST['timestamp'] ) || empty( $_POST['timestamp'] ) ) {
			wp_send_json_error( __( 'No backup timestamp specified.', 'royal-backup-reset' ) );
		}

		$timestamp = sanitize_text_field( wp_unslash( $_POST['timestamp'] ) );

		// Extract and validate requested restore components.
		$components = array();
		if ( isset( $_POST['components'] ) && is_array( $_POST['components'] ) ) {
			// Sanitize component identifiers.
			$components = array_map( 'sanitize_text_field', wp_unslash( $_POST['components'] ) );

			// Enforce allowed component list for security.
			$allowed_components = array( 'db', 'plugins', 'themes', 'uploads', 'others' );
			$components         = array_intersect( $components, $allowed_components );
		}

		// Default to full restore when no components specified.
		if ( empty( $components ) ) {
			$components = array( 'db', 'plugins', 'themes', 'uploads', 'others' );
		}

		// Store current user info before restore
		$current_user = wp_get_current_user();
		$current_user_id = $current_user->ID;
		$current_user_email = $current_user->user_email;

		$result = $this->get_restore_handler()->restore_backup_session( $timestamp, $components );

		if ( $result['success'] ) {
			// Re-authenticate the user after restore to maintain session
			if ( $current_user_id ) {
				// Try to find the user by ID first, then by email
				$user = get_user_by( 'id', $current_user_id );
				if ( ! $user ) {
					$user = get_user_by( 'email', $current_user_email );
				}

				if ( $user && $user->ID ) {
					// Clear old cookie and set new one
					wp_clear_auth_cookie();
					wp_set_current_user( $user->ID );
					wp_set_auth_cookie( $user->ID, true, is_ssl() );

					$this->log( 'Re-authenticated user after restore: ' . $user->user_login );
				}
			}

			// Return success with redirect URL to force clean session
			wp_send_json_success( array(
				'message' => $result['message'],
				'redirect' => admin_url( 'admin.php?page=royal-backup-reset&restored=1' )
			) );
		} else {
			// Provide fallback error message if specific error unavailable.
			$error_message = isset( $result['error'] ) ? $result['error'] : __( 'Unknown error occurred during restore', 'royal-backup-reset' );
			wp_send_json_error( $error_message );
		}
	}

	/**
	 * Handles AJAX request to delete a backup session and its files.
	 *
	 * @since 1.0.0
	 */
	public function delete_backup_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		if ( ! isset( $_POST['backup_nonce'] ) || empty( $_POST['backup_nonce'] ) ) {
			wp_send_json_error( __( 'No backup nonce specified.', 'royal-backup-reset' ) );
		}

		$backup_nonce = sanitize_text_field( wp_unslash( $_POST['backup_nonce'] ) );
		$result       = $this->delete_backup_by_nonce( $backup_nonce );

		if ( $result['success'] ) {
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( $result['error'] );
		}
	}

	/**
	 * Streams backup component file to browser for download.
	 *
	 * @since 1.0.0
	 */
	public function download_component_ajax() {
		// Support both GET and POST requests for flexible download initiation.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_text_field
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'royalbr_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'royal-backup-reset' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'royal-backup-reset' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_file_name
		$filename = isset( $_REQUEST['filename'] ) ? sanitize_file_name( wp_unslash( $_REQUEST['filename'] ) ) : '';
		if ( empty( $filename ) ) {
			wp_die( esc_html__( 'Filename parameter is missing.', 'royal-backup-reset' ) );
		}

		$backup_dir = rtrim( ROYALBR_BACKUP_DIR, '/\\' ) . DIRECTORY_SEPARATOR;
		$file_path  = $backup_dir . $filename;

		if ( ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'Requested file does not exist.', 'royal-backup-reset' ) );
		}

		// Clean ALL output buffers before sending file headers.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Output buffering cleanup.
		while ( ob_get_level() > 0 ) {
			@ob_end_clean();
		}

		// Disable PHP time limit for large files.
		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- May be disabled on some hosts.
			@set_time_limit( 0 );
		}

		// Configure HTTP headers for file download.
		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Content-Transfer-Encoding: binary' );

		// Flush headers before streaming file.
		flush();

		// Stream file directly to browser.
		readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Required for streaming large backup files
		exit;
	}

	/**
	 * Retrieves organized list of all backup sessions from database history.
	 *
	 * @since  1.0.0
	 * @return array Array of backup sessions with their components.
	 */
	public function get_backup_files() {
		// Load backup list from database for improved performance.
		$backup_history = ROYALBR_Backup_History::get_history();

		// Rebuild history from filesystem if database is empty.
		if ( empty( $backup_history ) ) {
			$this->log( 'Backup history is empty, rebuilding from files' );
			$backup_history = ROYALBR_Backup_History::rebuild();
		}

		// Verify history matches filesystem and rebuild if mismatch detected.
		$rebuild_triggered = $this->verify_backup_history( $backup_history );

		// Reload history if rebuild was triggered.
		if ( $rebuild_triggered ) {
			$backup_history = ROYALBR_Backup_History::get_history();
		}

		$backup_sessions = array();
		$backup_dir      = rtrim( ROYALBR_BACKUP_DIR, '/\\' ) . DIRECTORY_SEPARATOR;

		// Build session array from history records, keyed by nonce to avoid collisions.
		foreach ( $backup_history as $nonce => $backup ) {
			$timestamp = isset( $backup['timestamp'] ) ? $backup['timestamp'] : 0;

			// Construct session metadata from database record.
			$backup_sessions[ $nonce ] = array(
				'timestamp'  => $timestamp,
				'nonce'      => $nonce,
				'date'       => $timestamp,
				'components' => array(),
				'total_size' => 0, // Will be calculated from actual file sizes below
			);

			// Track cumulative size across all components
			$session_total_size = 0;

			// Extract component file information from backup record.
			if ( isset( $backup['components'] ) && is_array( $backup['components'] ) ) {
				foreach ( $backup['components'] as $component => $component_data ) {
					$file_data = $component_data['file'];

					// Handle both array (chunked backups) and string (single file) formats
					$filenames = is_array( $file_data ) ? $file_data : array( $file_data );

					// If we only have one file, try to discover additional chunks from filesystem
					// This handles backups created before multi-chunk support was added
					if ( count( $filenames ) === 1 && ! empty( $nonce ) ) {
						$discovered = ROYALBR_Backup_History::discover_chunks( $nonce, $component );
						if ( count( $discovered ) > 1 ) {
							$filenames = $discovered;
						}
					}

					// Calculate total size and verify all chunks exist
					$component_total_size = 0;
					$all_exist            = true;
					$valid_files          = array();

					foreach ( $filenames as $filename ) {
						$file_path = $backup_dir . $filename;
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists, WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- Required for backup file validation and size calculation
						if ( file_exists( $file_path ) ) {
							$component_total_size += filesize( $file_path );
							$valid_files[]         = $filename;
						} else {
							$all_exist = false;
						}
					}

					// Only add if at least one file exists
					if ( ! empty( $valid_files ) ) {
						$backup_sessions[ $nonce ]['components'][ $component ] = array(
							'filename' => $valid_files,
							'size'     => $component_total_size,
						);
						$session_total_size += $component_total_size;
					}
				}
			}

			// Update total size with calculated value from all components
			$backup_sessions[ $nonce ]['total_size'] = $session_total_size;

			// Remove backup records with missing or deleted files.
			if ( empty( $backup_sessions[ $nonce ]['components'] ) ) {
				unset( $backup_sessions[ $nonce ] );
			}
		}

		// Sort by timestamp (newest first) before returning.
		uasort(
			$backup_sessions,
			function ( $a, $b ) {
				return $b['timestamp'] - $a['timestamp'];
			}
		);

		return array_values( $backup_sessions );
	}

	/**
	 * Extracts backup session details from filename structure.
	 *
	 * @since  1.0.0
	 * @param  string $filename Backup filename.
	 * @return array|false Array with timestamp and component, or false on failure.
	 */
	private function parse_backup_filename( $filename ) {
		// Current filename format with collision-prevention nonce.
		if ( preg_match( '/^backup_(\d{4}-\d{2}-\d{2}-\d{4})_(.+)_([a-f0-9]{12})-([^0-9.]+)(\d+)?\.(gz|zip)$/', $filename, $matches ) ) {
			$timestamp = $matches[1];
			$nonce     = $matches[3];
			$component = $matches[4];

			return array(
				'timestamp' => $timestamp,
				'nonce'     => $nonce,
				'component' => $component,
			);
		}

		// Support older filename format for existing backups.
		if ( preg_match( '/^backup_(\d{4}-\d{2}-\d{2}-\d{4})_(.+)-([^0-9.]+)(\d+)?\.(gz|zip)$/', $filename, $matches ) ) {
			$timestamp = $matches[1];
			$component = $matches[3];

			return array(
				'timestamp' => $timestamp,
				'nonce'     => null,
				'component' => $component,
			);
		}

		return false;
	}

	/**
	 * Verifies backup history matches filesystem and rebuilds if mismatch detected.
	 *
	 * @since  1.0.0
	 * @param  array $backup_history Current backup history from database.
	 * @return bool True if rebuild was triggered, false otherwise.
	 */
	private function verify_backup_history( $backup_history ) {
		$backup_dir = trailingslashit( ROYALBR_BACKUP_DIR );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_dir -- Required for backup directory validation
		if ( ! is_dir( $backup_dir ) ) {
			return false;
		}

		// Count backup files in directory.
		$files             = scandir( $backup_dir );
		$backup_file_count = 0;
		$nonces_in_files   = array();

		foreach ( $files as $file ) {
			$ext = pathinfo( $file, PATHINFO_EXTENSION );
			if ( 'gz' !== $ext && 'zip' !== $ext ) {
				continue;
			}

			// Extract nonce from filename.
			if ( preg_match( '/^backup_\d{4}-\d{2}-\d{2}-\d{4}_.*_([a-f0-9]{12})-(db|plugins|themes|uploads|others)/', $file, $matches ) ) {
				$nonce                    = $matches[1];
				$nonces_in_files[ $nonce ] = true;
			}
		}

		$backup_sets_in_files = count( $nonces_in_files );
		$backup_sets_in_db    = count( $backup_history );

		// Rebuild if mismatch detected.
		if ( $backup_sets_in_files !== $backup_sets_in_db ) {
			$this->log( "Backup history mismatch detected. Files: {$backup_sets_in_files}, DB: {$backup_sets_in_db}. Rebuilding..." );
			ROYALBR_Backup_History::rebuild();
			return true;
		}

		return false;
	}

	/**
	 * Outputs HTML table displaying available backup sessions.
	 *
	 * @since 1.0.0
	 */
	public function display_backup_table() {
		$backup_sessions = $this->get_backup_files();
		$backup_dir      = rtrim( ROYALBR_BACKUP_DIR, '/\\' ) . DIRECTORY_SEPARATOR;

		if ( empty( $backup_sessions ) ) {
			echo '<div class="royalbr-no-backups">';
			echo '<p>' . esc_html__( 'No backups available yet. Use the "Create Backup" tab to generate your first backup.', 'royal-backup-reset' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped royalbr-backup-table">';
		echo '<thead>';
		echo '<tr>';
		echo '<th scope="col">' . esc_html__( 'Backup ID', 'royal-backup-reset' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Created On', 'royal-backup-reset' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Included Content', 'royal-backup-reset' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Available Actions', 'royal-backup-reset' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $backup_sessions as $session ) {
			echo '<tr>';

			// Format Unix timestamp for display.
			$timestamp   = $session['timestamp'];
			$nonce       = isset( $session['nonce'] ) ? $session['nonce'] : '';
			$pretty_date = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $timestamp ), 'M d, Y G:i' );

			// Get custom display name if exists.
			$display_names = get_option( 'royalbr_backup_display_names', array() );

			// Display backup identifier with size.
			echo '<td>';
			echo '<div class="royalbr-backup-name-wrapper">';
			if ( isset( $display_names[ $nonce ] ) && ! empty( $display_names[ $nonce ] ) ) {
				echo '<strong class="royalbr-backup-name">' . esc_html( ucfirst( $display_names[ $nonce ] ) ) . '</strong>';
			} else {
				echo '<strong class="royalbr-backup-name">' . esc_html( $nonce ) . '</strong>';
			}

			// Hook for premium features to add actions (e.g., rename button).
			$current_name = isset( $display_names[ $nonce ] ) && ! empty( $display_names[ $nonce ] ) ? $display_names[ $nonce ] : '';
			do_action( 'royalbr_after_backup_name', $nonce, $current_name );

			echo '</div>';
			if ( isset( $display_names[ $nonce ] ) && ! empty( $display_names[ $nonce ] ) ) {
				echo '<br><span class="royalbr-backup-id">' . esc_html( $nonce ) . '</span>';
			}
			echo '<br><small>' . esc_html( size_format( $session['total_size'], 1 ) ) . '</small></td>';

			// Display creation date using site's configured format.
			echo '<td class="royalbr-backup-date">' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ) . '</td>';

			// Render downloadable component links.
			echo '<td class="royalbr-backup-components">';
			echo '<div class="royalbr-download-label">'. esc_html__( 'Download components', 'royal-backup-reset' ) . '<span class="dashicons dashicons-download"></span></div>';
			echo '<div class="royalbr-download-links">';

			foreach ( $session['components'] as $component => $file_info ) {
				$component_label = $this->get_component_label( $component );
				// Handle both array (chunked) and string (single file) filenames.
				$filenames       = $file_info['filename'];
				$filenames       = is_array( $filenames ) ? $filenames : array( $filenames );
				$chunk_count     = count( $filenames );

				foreach ( $filenames as $chunk_index => $filename ) {
					// Calculate individual chunk file size.
					$chunk_file_path = $backup_dir . $filename;
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists, WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- Required for backup file size display
					$chunk_size = file_exists( $chunk_file_path ) ? filesize( $chunk_file_path ) : 0;

					// Add part number for chunked backups (Part 2, Part 3, etc.).
					$part_label = ( $chunk_count > 1 && $chunk_index > 0 )
						? sprintf(
							/* translators: %d: Part number for chunked backup file */
							esc_html__( ' Part %d', 'royal-backup-reset' ),
							$chunk_index + 1
						)
						: '';

					echo '<a href="#" class="royalbr-download-component" ';
					echo 'data-filename="' . esc_attr( $filename ) . '">';
					echo esc_html( $component_label . $part_label . ' (' . size_format( $chunk_size ) . ')' );
					echo '</a>';
				}
			}
			echo '</div>';
			echo '</td>';

			// Render action buttons with component data.
			echo '<td class="royalbr-backup-actions">';
			// Provide component list to JavaScript for UI validation.
			$available_components = array_keys( $session['components'] );
			echo '<button type="button" class="button button-primary royalbr-restore-backup" ';
			echo 'data-timestamp="' . esc_attr( $timestamp ) . '" ';
			echo 'data-available-components="' . esc_attr( wp_json_encode( $available_components ) ) . '">';
			echo esc_html__( 'Restore', 'royal-backup-reset' );
			echo '</button> ';
			echo '<button type="button" class="button royalbr-delete-backup" data-timestamp="' . esc_attr( $timestamp ) . '" data-nonce="' . esc_attr( $nonce ) . '">';
			echo esc_html__( 'Remove', 'royal-backup-reset' );
			echo '</button>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Transforms timestamp string into human-readable date format.
	 *
	 * @since  1.0.0
	 * @param  string $timestamp Timestamp string (YYYY-MM-DD-HHMM).
	 * @return string Formatted backup name.
	 */
	private function format_backup_name( $timestamp ) {
		// Parse and format structured timestamp string.
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})-(\d{2})(\d{2})$/', $timestamp, $matches ) ) {
			$year   = $matches[1];
			$month  = $matches[2];
			$day    = $matches[3];
			$hour   = $matches[4];
			$minute = $matches[5];

			return sprintf( '%s/%s/%s %s:%s', $month, $day, $year, $hour, $minute );
		}

		return $timestamp;
	}

	/**
	 * Converts component identifier to display-friendly label.
	 *
	 * @since  1.0.0
	 * @param  string $component Component key (db, plugins, themes, etc.).
	 * @return string Human-readable component label.
	 */
	private function get_component_label( $component ) {
		$labels = array(
			'db'      => __( 'Database', 'royal-backup-reset' ),
			'plugins' => __( 'Plugins', 'royal-backup-reset' ),
			'themes'  => __( 'Themes', 'royal-backup-reset' ),
			'uploads' => __( 'Uploads', 'royal-backup-reset' ),
			'others'  => __( 'Others', 'royal-backup-reset' ),
		);

		return isset( $labels[ $component ] ) ? $labels[ $component ] : ucfirst( $component );
	}

	/**
	 * Check if available disk space is at least the specified number of bytes.
	 *
	 * @since  1.0.0
	 * @param  int $space Number of bytes required.
	 * @return int|bool True if enough space, false if not, -1 if unknown.
	 */
	private function disk_space_check( $space ) {
		$backup_dir = defined( 'ROYALBR_BACKUP_DIR' ) ? ROYALBR_BACKUP_DIR : ( WP_CONTENT_DIR . '/royal-backup-reset/' );
		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- Silenced to suppress errors on hosts that disable this function.
		$disk_free_space = function_exists( 'disk_free_space' ) ? @disk_free_space( $backup_dir ) : false;
		// == rather than === is deliberate; 0 can be returned when the real result should be false.
		if ( false == $disk_free_space ) {
			return -1;
		}
		return ( $disk_free_space > $space ) ? true : false;
	}

	/**
	 * Delete backup files using backup nonce for precise identification.
	 *
	 * Deletes only the specific backup identified by nonce, not all backups from the same minute.
	 *
	 * @since  1.0.0
	 * @param  string $nonce Backup nonce (unique identifier).
	 * @return array Result array with success/error status.
	 */
	public function delete_backup_by_nonce( $nonce ) {
		$backup_dir    = rtrim( ROYALBR_BACKUP_DIR, '/\\' ) . DIRECTORY_SEPARATOR;
		$deleted_files = 0;
		$errors        = array();

		// Get backup record from history to find timestamp for history deletion
		$backup_record = ROYALBR_Backup_History::get_backup_set_by_nonce( $nonce );
		if ( ! $backup_record ) {
			return array(
				'success' => false,
				'error'   => __( 'Backup not found in history.', 'royal-backup-reset' ),
			);
		}

		$timestamp = isset( $backup_record['timestamp'] ) ? $backup_record['timestamp'] : 0;

		if ( is_dir( $backup_dir ) ) {
			$files = scandir( $backup_dir );
			foreach ( $files as $file ) {
				$file_extension = pathinfo( $file, PATHINFO_EXTENSION );

				// Delete backup files (gz, zip) that match the specific nonce
				if ( 'gz' === $file_extension || 'zip' === $file_extension ) {
					$parsed = $this->parse_backup_filename( $file );
					if ( $parsed && $parsed['nonce'] === $nonce ) {
						$file_path = $backup_dir . $file;
						if ( wp_delete_file( $file_path ) ) {
							++$deleted_files;
						} else {
							/* translators: %s: Filename that failed to delete */
						$errors[] = sprintf( __( 'Failed to delete %s', 'royal-backup-reset' ), $file );
						}
					}
				}

				// Delete log files (txt) that match the specific nonce
				// Log filename format: backup_{timestamp}_{sitename}_{nonce}-log.txt
				if ( 'txt' === $file_extension && strpos( $file, '_' . $nonce . '-log.txt' ) !== false ) {
					$file_path = $backup_dir . $file;
					if ( wp_delete_file( $file_path ) ) {
						++$deleted_files;
					} else {
						/* translators: %s: Filename that failed to delete */
					$errors[] = sprintf( __( 'Failed to delete %s', 'royal-backup-reset' ), $file );
					}
				}

				// Delete temp files (.zip.tmp, .tmp) that match the specific nonce.
				// These are incomplete backup files from interrupted processes.
				if ( 'tmp' === $file_extension && strpos( $file, '_' . $nonce . '-' ) !== false ) {
					$file_path = $backup_dir . $file;
					if ( wp_delete_file( $file_path ) ) {
						++$deleted_files;
					} else {
						/* translators: %s: Filename that failed to delete */
						$errors[] = sprintf( __( 'Failed to delete %s', 'royal-backup-reset' ), $file );
					}
				}
			}
		}

		if ( ! empty( $errors ) ) {
			return array(
				'success' => false,
				'error'   => implode( '; ', $errors ),
			);
		}

		if ( 0 === $deleted_files ) {
			return array(
				'success' => false,
				'error'   => __( 'No backup files found to delete.', 'royal-backup-reset' ),
			);
		}

		// Update history database after file removal.
		if ( $timestamp ) {
			ROYALBR_Backup_History::delete_backup_set( $timestamp );
		}

		// Remove custom display name if exists.
		$display_names = get_option( 'royalbr_backup_display_names', array() );
		if ( isset( $display_names[ $nonce ] ) ) {
			unset( $display_names[ $nonce ] );
			update_option( 'royalbr_backup_display_names', $display_names, false );
		}

		return array(
			'success' => true,
			/* translators: %d: Number of files deleted */
		'message' => sprintf( __( 'Backup session deleted successfully (%d files).', 'royal-backup-reset' ), $deleted_files ),
		);
	}

	/**
	 * Removes all backup files belonging to a specific session.
	 *
	 * @since  1.0.0
	 * @param  string $timestamp Backup timestamp.
	 * @return array Result array with success/error status.
	 */
	public function delete_backup_session( $timestamp ) {
		$backup_dir    = rtrim( ROYALBR_BACKUP_DIR, '/\\' ) . DIRECTORY_SEPARATOR;
		$deleted_files = 0;
		$errors        = array();

		// Convert Unix timestamp to filename format for pattern matching.
		$formatted_timestamp = gmdate( 'Y-m-d-Hi', (int) $timestamp );

		if ( is_dir( $backup_dir ) ) {
			$files = scandir( $backup_dir );
			foreach ( $files as $file ) {
				$file_extension = pathinfo( $file, PATHINFO_EXTENSION );

				// Delete backup files (gz, zip) that match the timestamp
				if ( 'gz' === $file_extension || 'zip' === $file_extension ) {
					$parsed = $this->parse_backup_filename( $file );
					if ( $parsed && $parsed['timestamp'] === $formatted_timestamp ) {
						$file_path = $backup_dir . $file;
						if ( wp_delete_file( $file_path ) ) {
							++$deleted_files;
						} else {
							/* translators: %s: Filename that failed to delete */
						$errors[] = sprintf( __( 'Failed to delete %s', 'royal-backup-reset' ), $file );
						}
					}
				}

				// Delete log files (txt) that match the timestamp pattern
				// Log filename format: backup_{timestamp}_{sitename}_{nonce}-log.txt
				if ( 'txt' === $file_extension && strpos( $file, 'backup_' . $formatted_timestamp ) === 0 && strpos( $file, '-log.txt' ) !== false ) {
					$file_path = $backup_dir . $file;
					if ( wp_delete_file( $file_path ) ) {
						++$deleted_files;
					} else {
						/* translators: %s: Filename that failed to delete */
					$errors[] = sprintf( __( 'Failed to delete %s', 'royal-backup-reset' ), $file );
					}
				}
			}
		}

		if ( ! empty( $errors ) ) {
			return array(
				'success' => false,
				'error'   => implode( '; ', $errors ),
			);
		}

		if ( 0 === $deleted_files ) {
			return array(
				'success' => false,
				'error'   => __( 'No backup files found to delete.', 'royal-backup-reset' ),
			);
		}

		// Update history database after file removal.
		ROYALBR_Backup_History::delete_backup_set( $timestamp );

		return array(
			'success' => true,
			/* translators: %d: Number of files deleted */
		'message' => sprintf( __( 'Backup session deleted successfully (%d files).', 'royal-backup-reset' ), $deleted_files ),
		);
	}

	/**
	 * Handles AJAX request to reset database to fresh WordPress state.
	 *
	 * @since 1.0.0
	 */
	public function reset_database_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Extract reset configuration from request.
		$options = array(
			'reactivate_theme'   => isset( $_POST['reactivate_theme'] ) && '1' === $_POST['reactivate_theme'],
			'reactivate_plugins' => isset( $_POST['reactivate_plugins'] ) && '1' === $_POST['reactivate_plugins'],
			'keep_royalbr_active'    => isset( $_POST['keep_royalbr_active'] ) && '1' === $_POST['keep_royalbr_active'],
			'clear_uploads'      => isset( $_POST['clear_uploads'] ) && '1' === $_POST['clear_uploads'],
			'clear_media'        => isset( $_POST['clear_media'] ) && '1' === $_POST['clear_media'],
		);

		// Execute database reset operation.
		$result = $this->get_reset_handler()->reset_database( $options );

		if ( $result['success'] ) {
			// Queue success message for display after redirect.
			set_transient( 'royalbr_reset_success_' . get_current_user_id(), true, 60 );

			wp_send_json_success(
				array(
					'message'      => $result['message'],
					'redirect_url' => admin_url( 'admin.php?page=royal-backup-reset&tab=reset-database' ),
				)
			);
		} else {
			wp_send_json_error( $result['error'] );
		}
	}

	/**
	 * Prepares environment for safe database reset by isolating this plugin.
	 *
	 * @since 1.0.0
	 */
	public function before_reset_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Store current plugin list for potential reactivation.
		$active_plugins = get_option( 'active_plugins' );
		set_transient( 'royalbr_active_plugins', $active_plugins, 100 );

		// Disable other plugins during reset process.
		remove_all_actions( 'update_option_active_plugins' );
		update_option( 'active_plugins', array( plugin_basename( __FILE__ ) ) );

		wp_send_json_success();
	}

	/**
	 * Adds quick action buttons to WordPress admin bar.
	 *
	 * @since 1.0.0
	 */
	public function wp_before_admin_bar_render() {
		global $wp_admin_bar;

		if ( !is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Multisite not yet supported - hide admin bar buttons.
		if ( is_multisite() ) {
			return;
		}

		$base_url = admin_url( 'admin.php?page=royal-backup-reset' );

		// Create backup button in admin bar, JavaScript handles click event.
		$args = array(
			'id'     => 'royalbr_backup_node',
			'parent' => 'top-secondary',
			'title'  => '<span class="ab-icon dashicons dashicons-backup royalbr-admin-bar-backup"></span>',
			'href'   => '#',
			'meta'   => array(
				'class' => 'royalbr-backup-trigger',
			),
		);
		$wp_admin_bar->add_node( $args );

		// Create reset button in admin bar, JavaScript handles click event.
		$args = array(
			'id'     => 'royalbr_reset_node',
			'parent' => 'top-secondary',
			'title'  => '<span class="ab-icon dashicons dashicons-trash"></span>',
			'href'   => '#',
			'meta'   => array(
				'class' => 'royalbr-reset-trigger',
			),
		);
		$wp_admin_bar->add_node( $args );
	}

	/**
	 * Displays one-time success message after database reset completion.
	 *
	 * @since 1.0.0
	 */
	public function reset_success_notice() {
		// Look for completion flag in temporary storage.
		$reset_success = get_transient( 'royalbr_reset_success_' . get_current_user_id() );

		if ( ! $reset_success ) {
			return;
		}

		// Clear flag after displaying message.
		delete_transient( 'royalbr_reset_success_' . get_current_user_id() );

		// Display one-time success notification.
		echo '<div class="notice notice-success"><p>';
		echo '<strong>' . esc_html__( 'Database Reset Complete!', 'royal-backup-reset' ) . '</strong><br>';
		echo esc_html__( 'Your WordPress database has been restored to factory settings. Your admin account remains active.', 'royal-backup-reset' );
		echo '</p></div>';
	}

	/**
	 * Displays one-time success message after backup completion.
	 *
	 * @since 1.0.0
	 */
	public function backup_complete_notice() {
		// Look for completion data in temporary storage.
		$backup_info = get_transient( 'royalbr_backup_complete_' . get_current_user_id() );

		if ( ! $backup_info ) {
			return;
		}

		// Remove completion flag to prevent repeated display.
		delete_transient( 'royalbr_backup_complete_' . get_current_user_id() );

		// Notification removed - backup completion is indicated in UI without admin notice
	}

	/**
	 * Checks if available disk space meets the required threshold.
	 *
	 * @since 1.0.0
	 * @param int $required_space Required space in bytes.
	 * @return bool|int True if sufficient space, false if low, -1 if unknown.
	 */
	private function check_disk_space( $required_space ) {
		$backup_dir = ROYALBR_BACKUP_DIR;
		$free_space = function_exists( 'disk_free_space' ) ? @disk_free_space( $backup_dir ) : false;
		if ( false === $free_space ) {
			return -1;
		}
		return ( $free_space > $required_space ) ? true : false;
	}

	/**
	 * Displays admin warning when disk space is low.
	 *
	 * @since 1.0.0
	 */
	public function show_low_disk_space_warning() {
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Low Disk Space', 'royal-backup-reset' ); ?></strong>
				<?php esc_html_e( 'Your server has less than 50 MB of free disk space. Backups and restores may fail. Please free up space or contact your hosting provider.', 'royal-backup-reset' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Hide dismiss button on Freemius upgrade notice.
	 *
	 * When license is active but Pro version is not installed, prevent users
	 * from dismissing the Freemius upgrade notice until they install Pro.
	 *
	 * @since 1.0.5
	 */
	public function hide_freemius_dismiss_button() {
		?>
		<style>
			#fs_promo_tab .fs-notice.fs-slug-royal-backup-reset .fs-close { display: none !important; }
			.fs-notice.fs-slug-royal-backup-reset[data-id="premium_installed"] .fs-close { display: none !important; }
		</style>
		<?php
	}

	/**
	 * Handles AJAX request to retrieve plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function get_settings_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Retrieve settings with type casting for JavaScript compatibility.
		$settings = array(
			'backup_include_db'    => (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_backup_include_db', true ),
			'backup_include_files' => (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_backup_include_files', true ),
			'restore_db'           => (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_restore_db', true ),
			'restore_plugins'      => (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_restore_plugins', false ),
			'restore_themes'       => (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_restore_themes', false ),
			'restore_uploads'      => (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_restore_uploads', false ),
			'restore_others'       => (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_restore_others', false ),
			'reactivate_theme'     => (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_reactivate_theme', false ),
			'reactivate_plugins'   => (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_reactivate_plugins', false ),
			'keep_royalbr_active'      => (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_keep_royalbr_active', true ),
			'clear_uploads'        => (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_clear_uploads', false ),
			'clear_media'          => (bool) ROYALBR_Options::get_royalbr_option( 'royalbr_clear_media', false ),
		);

		wp_send_json_success( $settings );
	}

	/**
	 * Handles AJAX request to update plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function save_settings_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Extract settings array from request.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per-field below in foreach loop
		$settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

		if ( empty( $settings ) || ! is_array( $settings ) ) {
			wp_send_json_error( __( 'No settings provided.', 'royal-backup-reset' ) );
		}

		// Define allowed checkbox options.
		$checkbox_options = array(
			'royalbr_backup_include_db',
			'royalbr_backup_include_files',
			'royalbr_restore_db',
			'royalbr_restore_plugins',
			'royalbr_restore_themes',
			'royalbr_restore_uploads',
			'royalbr_restore_others',
			'royalbr_reactivate_theme',
			'royalbr_reactivate_plugins',
			'royalbr_keep_royalbr_active',
			'royalbr_clear_uploads',
			'royalbr_clear_media',
		);

		// Process and save checkbox settings.
		foreach ( $checkbox_options as $option_name ) {
			// Convert checkbox input to boolean value.
			$raw_value = isset( $settings[ $option_name ] ) ? sanitize_text_field( $settings[ $option_name ] ) : '';
			$value     = '1' === $raw_value;

			// Use add_option for non-existent options (fixes false values not saving on fresh installs).
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $value, '', 'yes' );
			} else {
				ROYALBR_Options::update_royalbr_option( $option_name, $value );
			}
		}

		// Process scheduled backup settings (if premium is active).
		if ( function_exists( 'royalbr_fs' ) && royalbr_fs()->can_use_premium_code() ) {
			// Get scheduler instance.
			if ( class_exists( 'RoyalBR_Scheduled_Backups' ) ) {
				$scheduler = RoyalBR_Scheduled_Backups::get_instance();

				// Files backup interval - call scheduling callback and save.
				if ( isset( $settings['royalbr_interval_files'] ) ) {
					$interval = sanitize_text_field( $settings['royalbr_interval_files'] );
					$validated_interval = $scheduler->schedule_backup_files( $interval );
					update_option( 'royalbr_interval_files', $validated_interval );
				}

				// Database backup interval - call scheduling callback and save.
				if ( isset( $settings['royalbr_interval_database'] ) ) {
					$interval_db = sanitize_text_field( $settings['royalbr_interval_database'] );
					$validated_interval_db = $scheduler->schedule_backup_database( $interval_db );
					update_option( 'royalbr_interval_database', $validated_interval_db );
				}
			}

			// Retention settings.
			if ( isset( $settings['royalbr_retain_files'] ) ) {
				$retain = max( 1, absint( $settings['royalbr_retain_files'] ) );
				update_option( 'royalbr_retain_files', $retain );
			}
			if ( isset( $settings['royalbr_retain_db'] ) ) {
				$retain_db = max( 1, absint( $settings['royalbr_retain_db'] ) );
				update_option( 'royalbr_retain_db', $retain_db );
			}
		}

		// Process backup reminder popup mode setting.
		if ( isset( $settings['royalbr_reminder_popup_mode'] ) ) {
			$mode = sanitize_text_field( $settings['royalbr_reminder_popup_mode'] );
			if ( in_array( $mode, array( 'allow_dismiss', 'show_always' ), true ) ) {
				ROYALBR_Options::update_royalbr_option( 'royalbr_reminder_popup_mode', $mode );
			}
		}

		wp_send_json_success( __( 'Settings saved successfully.', 'royal-backup-reset' ) );
	}

	/**
	 * Gets active backup status for page load initialization.
	 *
	 * Returns the current state of any running backup so JavaScript can
	 * resume progress display after page refresh.
	 *
	 * @since 1.0.0
	 * @return array Active backup status data.
	 */
	public function get_active_backup_status() {
		$task_id = get_option( 'royalbr_oneshotnonce', false );

		if ( false === $task_id ) {
			return array( 'running' => false );
		}

		$taskdata = $this->retrieve_task_array( $task_id );

		if ( empty( $taskdata ) ) {
			return array( 'running' => false );
		}

		// Check for errors or completion.
		if ( ! empty( $taskdata['backup_error'] ) ) {
			return array(
				'running'      => false,
				'backup_error' => $taskdata['backup_error'],
			);
		}

		if ( ! empty( $taskdata['backup_complete'] ) ) {
			return array( 'running' => false );
		}

		// Backup is running - return current state.
		return array(
			'running'                => true,
			'taskstatus'             => isset( $taskdata['taskstatus'] ) ? $taskdata['taskstatus'] : 'begun',
			'filecreating_substatus' => isset( $taskdata['filecreating_substatus'] ) ? $taskdata['filecreating_substatus'] : null,
			'dbcreating_substatus'   => isset( $taskdata['dbcreating_substatus'] ) ? $taskdata['dbcreating_substatus'] : null,
			'include_db'             => isset( $taskdata['task_backup_database'] ) ? $taskdata['task_backup_database'] : true,
			'include_files'          => isset( $taskdata['task_backup_files'] ) ? $taskdata['task_backup_files'] : true,
		);
	}

	/**
	 * Handles AJAX request to check backup operation progress.
	 *
	 * @since 1.0.0
	 */
	public function get_backup_progress_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Check for direct error (set immediately when disk space issue detected).
		$direct_error = get_option( 'royalbr_backup_error', '' );
		if ( ! empty( $direct_error ) ) {
			delete_option( 'royalbr_backup_error' );
			wp_send_json_success( array(
				'running'      => false,
				'taskstatus'   => 'failed',
				'backup_error' => $direct_error,
			) );
		}

		// Retrieve active task identifier and load its progress data.
		$task_id = get_option( 'royalbr_oneshotnonce', false );
		$taskdata =( false === $task_id ) ? array() : $this->retrieve_task_array( $task_id );

		// Return idle status when no active backup exists.
		if ( empty( $taskdata ) ) {
			wp_send_json_success( array(
				'running'    => false,
				'taskstatus' => '',
				'taskdata'   => array(),
			) );
		}

		// Return failed status when backup encountered an error (check FIRST, regardless of backup_complete).
		if ( ! empty( $taskdata['backup_error'] ) ) {
			wp_send_json_success( array(
				'running'         => false,
				'taskstatus'      => 'failed',
				'backup_complete' => false,
				'backup_error'    => $taskdata['backup_error'],
			) );
		}

		// Return finished status when backup is complete (only if no error - checked above).
		if ( isset( $taskdata['backup_complete'] ) && $taskdata['backup_complete'] ) {
			wp_send_json_success( array(
				'running'         => false,
				'taskstatus'      => 'finished',
				'backup_complete' => true,
			) );
		}

		// Return current progress information for UI updates.
		wp_send_json_success( array(
			'running'                => true,
			'taskstatus'             => isset( $taskdata['taskstatus'] ) ? $taskdata['taskstatus'] : 'begun',
			'filecreating_substatus' => isset( $taskdata['filecreating_substatus'] ) ? $taskdata['filecreating_substatus'] : null,
			'dbcreating_substatus'   => isset( $taskdata['dbcreating_substatus'] ) ? $taskdata['dbcreating_substatus'] : null,
			'backup_complete'        => isset( $taskdata['backup_complete'] ) ? $taskdata['backup_complete'] : false,
			'backup_error'           => isset( $taskdata['backup_error'] ) ? $taskdata['backup_error'] : '',
		) );
	}

	/**
	 * Handles AJAX request to retrieve backup operation log.
	 *
	 * @since 1.0.0
	 */
	public function get_log_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Determine which backup log to retrieve.
		$backup_nonce = '';
		if ( isset( $_POST['backup_nonce'] ) && ! empty( $_POST['backup_nonce'] ) ) {
			$backup_nonce = sanitize_text_field( wp_unslash( $_POST['backup_nonce'] ) );
		} else {
			// Retrieve current active backup identifier.
			$backup_nonce = get_option( 'royalbr_oneshotnonce', '' );
		}

		// Ensure nonce matches expected format for security.
		if ( ! preg_match( '/^[0-9a-f]{12}$/', $backup_nonce ) ) {
			wp_send_json_error( array(
				'log'     => __( 'Invalid backup nonce.', 'royal-backup-reset' ),
				'nonce'   => '',
				'pointer' => 0
			) );
		}

		// Locate log file for this backup.
		$backup_handler = $this->get_backup_handler();
		$log_file       = $backup_handler->get_logfile_name( $backup_nonce );

		// Read and return log file contents.
		$log_content = '';
		if ( file_exists( $log_file ) && is_readable( $log_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading log file for display
			$log_content = file_get_contents( $log_file );
			if ( false === $log_content ) {
				$log_content = __( 'Error reading log file.', 'royal-backup-reset' );
			} elseif ( empty( $log_content ) ) {
				$log_content = __( 'Log file is empty (backup may still be in progress or just started).', 'royal-backup-reset' );
			}
		} else {
			$log_content = sprintf(
				/* translators: %1$s: log file path, %2$s: backup nonce */
				__( 'Log file not found. Looking for: %1$s (nonce: %2$s)', 'royal-backup-reset' ),
				basename( $log_file ),
				$backup_nonce
			);
		}

		// Send log data to client.
		// No need for htmlspecialchars() as JavaScript uses .text() which escapes HTML
		wp_send_json_success( array(
			'log'      => $log_content,
			'nonce'    => $backup_nonce,
			'filename' => basename( $log_file ),
			'pointer'  => strlen( $log_content )
		) );
	}

	/**
	 * Handles AJAX request to download backup log file.
	 *
	 * @since 1.0.0
	 */
	public function download_log_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Determine which backup log to download.
		$backup_nonce = '';
		if ( isset( $_POST['backup_nonce'] ) && ! empty( $_POST['backup_nonce'] ) ) {
			$backup_nonce = sanitize_text_field( wp_unslash( $_POST['backup_nonce'] ) );
		} else {
			// Retrieve current active backup identifier.
			$backup_nonce = get_option( 'royalbr_oneshotnonce', '' );
		}

		// Ensure nonce matches expected format for security.
		if ( ! preg_match( '/^[0-9a-f]{12}$/', $backup_nonce ) ) {
			wp_die( esc_html__( 'Invalid backup nonce.', 'royal-backup-reset' ) );
		}

		// Locate log file for this backup.
		$backup_handler = $this->get_backup_handler();
		$log_file       = $backup_handler->get_logfile_name( $backup_nonce );

		// Check if file exists and is readable.
		if ( ! file_exists( $log_file ) || ! is_readable( $log_file ) ) {
			wp_die( esc_html__( 'Log file not found.', 'royal-backup-reset' ) );
		}

		// Set headers for file download.
		// Use actual log filename with download timestamp appended
		$base_filename = basename( $log_file, '.txt' );
		$download_filename = $base_filename . '-downloaded-' . gmdate( 'Y-m-d-His' ) . '.txt';

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $download_filename . '"' );
		header( 'Content-Length: ' . filesize( $log_file ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Output file content.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Direct file output for download
		readfile( $log_file );
		exit;
	}

	/**
	 * Handles AJAX request to download restore log file.
	 *
	 * @since 1.0.0
	 */
	public function download_restore_log_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Extract log file path from request.
		$log_file_path = '';
		if ( isset( $_POST['log_file'] ) && ! empty( $_POST['log_file'] ) ) {
			$log_file_path = sanitize_text_field( wp_unslash( $_POST['log_file'] ) );
		}

		if ( empty( $log_file_path ) ) {
			wp_die( esc_html__( 'Log file path not provided.', 'royal-backup-reset' ) );
		}

		// Security: Ensure the log file is within our backup directory
		$backup_dir = rtrim( ROYALBR_BACKUP_DIR, '/\\' ) . DIRECTORY_SEPARATOR;
		$real_log_path = realpath( $log_file_path );

		if ( false === $real_log_path || strpos( $real_log_path, realpath( $backup_dir ) ) !== 0 ) {
			wp_die( esc_html__( 'Invalid log file path.', 'royal-backup-reset' ) );
		}

		// Check if file exists and is readable.
		if ( ! file_exists( $real_log_path ) || ! is_readable( $real_log_path ) ) {
			wp_die( esc_html__( 'Log file not found.', 'royal-backup-reset' ) );
		}

		// Set headers for file download.
		// Use actual log filename with download timestamp appended
		$base_filename = basename( $real_log_path, '.txt' );
		$download_filename = $base_filename . '-downloaded-' . gmdate( 'Y-m-d-His' ) . '.txt';

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $download_filename . '"' );
		header( 'Content-Length: ' . filesize( $real_log_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Output file content.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Direct file output for download
		readfile( $real_log_path );
		exit;
	}

	/**
	 * Handles AJAX request to test scheduled files backup immediately.
	 *
	 * @since 2.0.0
	 */
	public function test_scheduled_files_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Tag this as a scheduled backup for retention purposes.
		$this->backup_source = 'scheduled';

		// Trigger immediate files backup (no database)
		$result = $this->perform_backup( false, true );

		// Save display name for scheduled backup.
		if ( $result ) {
			$display_names = get_option( 'royalbr_backup_display_names', array() );
			$display_names[ $this->file_nonce ] = __( 'Scheduled Files Backup', 'royal-backup-reset' );
			update_option( 'royalbr_backup_display_names', $display_names, false );
		}

		// Clean up old file backups based on retention settings.
		if ( class_exists( 'RoyalBR_Scheduled_Backups' ) ) {
			$scheduler = RoyalBR_Scheduled_Backups::get_instance();
			if ( $scheduler ) {
				$scheduler->cleanup_old_files();
			}
		}

		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Files backup test started successfully.', 'royal-backup-reset' )
			) );
		} else {
			wp_send_json_error( __( 'Failed to start files backup test.', 'royal-backup-reset' ) );
		}
	}

	/**
	 * Handles AJAX request to test scheduled database backup immediately.
	 *
	 * @since 2.0.0
	 */
	public function test_scheduled_database_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Tag this as a scheduled backup for retention purposes.
		$this->backup_source = 'scheduled';

		// Trigger immediate database backup (no files)
		$result = $this->perform_backup( true, false );

		// Save display name for scheduled backup.
		if ( $result ) {
			$display_names = get_option( 'royalbr_backup_display_names', array() );
			$display_names[ $this->file_nonce ] = __( 'Scheduled Database Backup', 'royal-backup-reset' );
			update_option( 'royalbr_backup_display_names', $display_names, false );
		}

		// Clean up old database backups based on retention settings.
		if ( class_exists( 'RoyalBR_Scheduled_Backups' ) ) {
			$scheduler = RoyalBR_Scheduled_Backups::get_instance();
			if ( $scheduler ) {
				$scheduler->cleanup_old_databases();
			}
		}

		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Database backup test started successfully.', 'royal-backup-reset' )
			) );
		} else {
			wp_send_json_error( __( 'Failed to start database backup test.', 'royal-backup-reset' ) );
		}
	}

	/**
	 * Handles AJAX request to retrieve restore operation log file.
	 *
	 * @since 2.0.0
	 */
	public function get_restore_log_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Extract log file path from request.
		$log_file_path = '';
		if ( isset( $_POST['log_file'] ) && ! empty( $_POST['log_file'] ) ) {
			$log_file_path = sanitize_text_field( wp_unslash( $_POST['log_file'] ) );
		} else {
			wp_send_json_error( __( 'No log file specified.', 'royal-backup-reset' ) );
		}

		// Resolve absolute paths for security validation.
		$backup_dir = defined( 'ROYALBR_BACKUP_DIR' ) ? ROYALBR_BACKUP_DIR : ( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'royal-backup-reset' . DIRECTORY_SEPARATOR );
		$real_log_path = realpath( $log_file_path );
		$real_backup_dir = realpath( $backup_dir );

		// Prevent directory traversal attacks.
		if ( false === $real_log_path ) {
			wp_send_json_error( __( 'Invalid log file path - file does not exist.', 'royal-backup-reset' ) . ' Path: ' . $log_file_path );
		}
		if ( false === $real_backup_dir ) {
			wp_send_json_error( __( 'Invalid backup directory path.', 'royal-backup-reset' ) . ' Dir: ' . $backup_dir );
		}
		if ( 0 !== strpos( $real_log_path, $real_backup_dir ) ) {
			wp_send_json_error( __( 'Log file is not in backup directory.', 'royal-backup-reset' ) . ' Log: ' . $real_log_path . ', Dir: ' . $real_backup_dir );
		}

		// Verify file accessibility.
		if ( ! file_exists( $real_log_path ) || ! is_readable( $real_log_path ) ) {
			wp_send_json_error( __( 'Log file not found or not readable.', 'royal-backup-reset' ) );
		}

		// Load log file contents.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading log file for display
		$log_content = file_get_contents( $real_log_path );

		if ( false === $log_content ) {
			wp_send_json_error( __( 'Error reading log file.', 'royal-backup-reset' ) );
		}

		// Send log content to client with filename for consistency with backup logs
		wp_send_json_success( array(
			'log'      => $log_content,
			'filename' => basename( $real_log_path ),
			'log_file' => $real_log_path, // Full path for download functionality
		) );
	}

	/**
	 * Handles AJAX request to abort a running backup operation.
	 *
	 * @since 1.0.0
	 */
	public function stop_backup_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Retrieve current active backup task identifier.
		$task_id = get_option( 'royalbr_oneshotnonce', false );

		// Validate task identifier format for security.
		if ( ! $task_id || ! preg_match( '/^[0-9a-f]{12}$/', $task_id ) ) {
			wp_send_json_error( __( 'Could not find that task - perhaps it has already finished?', 'royal-backup-reset' ) );
		}

		// Signal backup termination by creating abort flag file.
		$backup_handler = $this->get_backup_handler();
		$backup_dir     = ROYALBR_BACKUP_DIR;
		$deleteflag     = $backup_dir . 'deleteflag-' . $task_id . '.txt';

		// Create flag file to signal abort request.
		if ( file_exists( $backup_dir . 'log.' . $task_id . '.txt' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Required to update deleteflag file timestamp for backup abort signal
			touch( $deleteflag );
			wp_send_json_success( __( 'Backup stop requested.', 'royal-backup-reset' ) );
		} else {
			wp_send_json_error( __( 'Could not find backup log - backup may have already finished.', 'royal-backup-reset' ) );
		}
	}

	/**
	 * Handles AJAX request to refresh backup table HTML.
	 *
	 * @since 1.0.0
	 */
	public function get_backup_list_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Generate fresh backup table markup.
		ob_start();
		$this->display_backup_table();
		$backup_list_html = ob_get_clean();

		wp_send_json_success( array(
			'html' => $backup_list_html,
		) );
	}

	/**
	 * AJAX handler to get simplified backup list for hover popup.
	 *
	 * Returns array of backups with only essential data (nonce, timestamp, display name).
	 *
	 * @since 1.0.0
	 */
	public function get_backup_list_for_popup_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		$backup_sessions = $this->get_backup_files();
		$display_names   = get_option( 'royalbr_backup_display_names', array() );

		if ( empty( $backup_sessions ) ) {
			wp_send_json_success( array() );
			return;
		}

		$simplified_list = array();

		// Limit to last 3 backups for popup display.
		$backup_sessions = array_slice( $backup_sessions, 0, 3 );

		foreach ( $backup_sessions as $session ) {
			$nonce     = isset( $session['nonce'] ) ? $session['nonce'] : '';
			$timestamp = $session['timestamp'];

			// Format date using site's configured format.
			$formatted_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );

			// Get available components from session data.
			$available_components = isset( $session['components'] ) ? array_keys( $session['components'] ) : array();

			$simplified_list[] = array(
				'nonce'                => $nonce,
				'timestamp'            => $timestamp,
				'display_name'         => isset( $display_names[ $nonce ] ) ? $display_names[ $nonce ] : '',
				'formatted_date'       => $formatted_date,
				'available_components' => $available_components,
			);
		}

		wp_send_json_success( $simplified_list );
	}

	/**
	 * AJAX handler to get backup confirmation modal HTML.
	 *
	 * Returns the modal HTML for backup name input, allowing it to be loaded
	 * dynamically on any page without being in the initial DOM.
	 *
	 * @since 1.0.0
	 */
	public function get_backup_modal_html_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Get context parameter (admin-page or quick-actions).
		$context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : 'admin-page';

		// Validate context.
		$valid_contexts = array( 'admin-page', 'quick-actions' );
		if ( ! in_array( $context, $valid_contexts, true ) ) {
			$context = 'admin-page';
		}

		// Build context class.
		$context_class = 'royalbr-' . $context . '-modal';

		// Get default backup settings.
		$default_include_db    = ROYALBR_Options::get_royalbr_option( 'royalbr_backup_include_db', true );
		$default_include_files = ROYALBR_Options::get_royalbr_option( 'royalbr_backup_include_files', true );

		// Generate modal HTML.
		ob_start();
		?>
		<div id="royalbr-backup-confirmation-modal" class="royalbr-modal <?php echo esc_attr( $context_class ); ?>" style="display: none;">
			<div class="royalbr-modal-content">
				<div class="royalbr-modal-header">
					<h3><?php esc_html_e( 'Start Backup Process', 'royal-backup-reset' ); ?></h3>
					<span class="royalbr-modal-close">&times;</span>
				</div>
				<div class="royalbr-modal-body">
					<div style="margin: 20px 0;">
						<label for="royalbr-backup-name"><strong><?php esc_html_e( 'Custom Backup Name (Optional)', 'royal-backup-reset' ); ?></strong></label>
						<p class="description"><?php esc_html_e( 'If left empty, the backup ID will be used for display.', 'royal-backup-reset' ); ?></p>
						<input type="text" id="royalbr-backup-name" class="regular-text" placeholder="" style="width: 100%; margin-top: 8px;">
					</div>

					<?php if ( 'admin-page' !== $context ) : ?>
					<?php
					$is_premium_modal = function_exists( 'royalbr_fs' ) && royalbr_fs()->can_use_premium_code();
					$db_disabled_class_modal = $is_premium_modal ? '' : 'royalbr-pro-option-disabled';
					?>
					<div class="royalbr-backup-options" style="margin: 20px 0;">
						<div class="royalbr-checkbox-card <?php echo esc_attr( $db_disabled_class_modal ); ?>" <?php if ( ! $is_premium_modal ) : ?>data-pro-option-name="<?php esc_attr_e( 'Database Content', 'royal-backup-reset' ); ?>"<?php endif; ?>>
							<label>
								<input type="checkbox" class="royalbr-custom-checkbox" id="royalbr-backup-database" <?php checked( $default_include_db ); ?> <?php echo $is_premium_modal ? '' : 'disabled'; ?>>
								<span class="royalbr-checkbox-content">
									<span class="royalbr-checkbox-title">
										<?php esc_html_e( 'Database Content', 'royal-backup-reset' ); ?>
									</span>
									<span class="royalbr-checkbox-label"><?php esc_html_e( '(your posts, pages, users and settings)', 'royal-backup-reset' ); ?></span>
								</span>
							</label>
						</div>
						<div class="royalbr-checkbox-card">
							<label>
								<input type="checkbox" class="royalbr-custom-checkbox" id="royalbr-backup-files" <?php checked( $default_include_files ); ?>>
								<span class="royalbr-checkbox-content">
									<span class="royalbr-checkbox-title">
										<?php esc_html_e( 'Include Site Files', 'royal-backup-reset' ); ?>
									</span>
									<span class="royalbr-checkbox-label"><?php esc_html_e( '(themes, plugins, images and uploads )', 'royal-backup-reset' ); ?></span>
								</span>
							</label>
						</div>
					</div>
					<?php endif; ?>
				</div>
				<div class="royalbr-modal-footer">
					<button type="button" class="button" id="royalbr-backup-cancel"><?php esc_html_e( 'Cancel', 'royal-backup-reset' ); ?></button>
					<button type="button" class="button button-primary" id="royalbr-backup-proceed"><?php esc_html_e( 'Proceed', 'royal-backup-reset' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		$modal_html = ob_get_clean();

		wp_send_json_success( array(
			'html' => $modal_html,
		) );
	}

	/**
	 * AJAX handler to get backup progress modal HTML.
	 *
	 * Returns the modal HTML for showing backup progress with real-time updates,
	 * allowing it to be loaded dynamically on any page.
	 *
	 * @since 1.0.0
	 */
	public function get_backup_progress_modal_html_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Generate backup progress modal HTML.
		ob_start();
		?>
		<div id="royalbr-backup-progress-modal" class="royalbr-modal" style="display: none;">
			<div class="royalbr-modal-content">
				<div class="royalbr-modal-header">
					<h3><?php esc_html_e( 'Backup in Progress', 'royal-backup-reset' ); ?></h3>
					<span class="royalbr-modal-close">&times;</span>
				</div>
				<div class="royalbr-modal-body">
					<p style="text-align: center; color: #6e6e73; margin-bottom: 20px;">
						<?php esc_html_e( 'Your backup is being created. You can close this and continue working.', 'royal-backup-reset' ); ?>
					</p>

					<div class="royalbr-progress-wrapper" style="margin: 0; padding: 18px; background: #f5f5f7; border: 1px solid #d2d2d7; border-radius: 7px;">
						<div class="royalbr-progress-bar">
							<div class="royalbr-progress-fill" style="width: 0%;"></div>
						</div>
						<div class="royalbr-progress-text" style="text-align: center; margin-top: 10px;">
							<?php esc_html_e( 'Initializing...', 'royal-backup-reset' ); ?>
						</div>
					</div>

					<div class="royalbr-backup-complete-message" style="display: none; text-align: center;">
						<p style="font-size: 16px; font-weight: 600; color: #00a32a;">
							<span class="dashicons dashicons-yes" style="font-size: 24px; width: 24px; height: 24px;"></span>
							<?php esc_html_e( 'Backup completed successfully!', 'royal-backup-reset' ); ?>
						</p>
					</div>

					<div class="royalbr-backup-error-message" style="display: none; margin-top: 20px; padding: 12px 15px; background: #fef7f7; border: 1px solid #d63638; border-radius: 4px; color: #8a2424; font-size: 13px;">
					</div>
				</div>
				<div class="royalbr-modal-footer">
					<button type="button" class="button" id="royalbr-backup-progress-view-log" style="display: none;"><?php esc_html_e( 'View Log', 'royal-backup-reset' ); ?></button>
					<button type="button" class="button button-primary" id="royalbr-backup-progress-done" style="display: none;"><?php esc_html_e( 'Done', 'royal-backup-reset' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		$modal_html = ob_get_clean();

		wp_send_json_success( array(
			'html' => $modal_html,
		) );
	}

	/**
	 * AJAX handler to get log viewer modal HTML.
	 *
	 * Returns the modal HTML for viewing activity logs, allowing it to be loaded
	 * dynamically on any page without being in the initial DOM.
	 *
	 * @since 1.0.0
	 */
	public function get_log_viewer_modal_html_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Get context parameter (admin-page or quick-actions).
		$context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : 'admin-page';

		// Validate context.
		$valid_contexts = array( 'admin-page', 'quick-actions' );
		if ( ! in_array( $context, $valid_contexts, true ) ) {
			$context = 'admin-page';
		}

		// Build context class.
		$context_class = 'royalbr-' . $context . '-modal';

		// Generate log viewer modal HTML.
		ob_start();
		?>
		<div id="royalbr-log-popup" class="royalbr-modal <?php echo esc_attr( $context_class ); ?>" style="display: none;">
			<div class="royalbr-modal-content royalbr-log-modal-content">
				<div class="royalbr-modal-header">
					<div style="display: flex; flex-direction: column; gap: 4px; flex: 1;">
						<h3 id="royalbr-log-modal-title" style="margin: 0; font-size: 18px;"><?php esc_html_e( 'Activity Log', 'royal-backup-reset' ); ?></h3>
						<p id="royalbr-log-modal-filename" style="font-size: 12px; color: #999; margin: 0; font-weight: normal;"></p>
					</div>
					<span class="royalbr-modal-close">&times;</span>
				</div>
				<div class="royalbr-modal-body">
					<pre id="royalbr-log-content" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto; background: #f5f5f5; padding: 10px; border: 1px solid #ddd; font-family: monospace; font-size: 12px;"></pre>
				</div>
				<div class="royalbr-modal-footer">
					<button type="button" class="button button-primary" id="royalbr-download-log">
						<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Download Log', 'royal-backup-reset' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
		$modal_html = ob_get_clean();

		wp_send_json_success( array(
			'html' => $modal_html,
		) );
	}

	/**
	 * AJAX handler to get generic confirmation modal HTML.
	 *
	 * Returns a generic confirmation modal used for various confirmations
	 * (restore, delete, reset). The modal content is set dynamically via JavaScript.
	 * Accepts optional context parameter to add context-specific classes.
	 *
	 * @since 1.0.0
	 */
	public function get_confirmation_modal_html_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Get context parameter (admin-page or quick-actions).
		$context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : 'admin-page';

		// Validate context.
		$valid_contexts = array( 'admin-page', 'quick-actions' );
		if ( ! in_array( $context, $valid_contexts, true ) ) {
			$context = 'admin-page';
		}

		// Build context class.
		$context_class = 'royalbr-' . $context . '-modal';

		// Generate generic confirmation modal HTML.
		ob_start();
		?>
		<div id="royalbr-confirmation-modal" class="royalbr-modal <?php echo esc_attr( $context_class ); ?>" style="display: none;">
			<div class="royalbr-modal-content">
				<div class="royalbr-modal-header">
					<h3 id="royalbr-modal-title"><?php esc_html_e( 'Please Confirm', 'royal-backup-reset' ); ?></h3>
					<span class="royalbr-modal-close">&times;</span>
				</div>
				<div class="royalbr-modal-body">
					<p id="royalbr-modal-message"></p>
				</div>
				<div class="royalbr-modal-footer">
					<button type="button" class="button" id="royalbr-modal-cancel"><?php esc_html_e( 'Cancel', 'royal-backup-reset' ); ?></button>
					<button type="button" class="button button-primary" id="royalbr-modal-confirm"><?php esc_html_e( 'Proceed', 'royal-backup-reset' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		$modal_html = ob_get_clean();

		wp_send_json_success( array(
			'html' => $modal_html,
		) );
	}

	/**
	 * AJAX handler to get generic progress modal HTML.
	 *
	 * Returns a progress modal for restore operations. This modal shows
	 * real-time progress updates during restore process.
	 * Accepts optional context parameter to add context-specific classes.
	 *
	 * @since 1.0.0
	 */
	public function get_progress_modal_html_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Get context parameter (admin-page or quick-actions).
		$context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : 'admin-page';

		// Validate context.
		$valid_contexts = array( 'admin-page', 'quick-actions' );
		if ( ! in_array( $context, $valid_contexts, true ) ) {
			$context = 'admin-page';
		}

		// Build context class.
		$context_class = 'royalbr-' . $context . '-modal';

		// Generate progress modal HTML (for restore operations).
		ob_start();
		?>
		<div id="royalbr-progress-modal" class="royalbr-modal <?php echo esc_attr( $context_class ); ?>" style="display: none;">
			<div class="royalbr-modal-content royalbr-modal-large">
				<div class="royalbr-modal-header">
					<h3><?php esc_html_e( 'Restoration in Progress', 'royal-backup-reset' ); ?></h3>
				</div>
				<div class="royalbr-modal-body">
					<p class="royalbr-restore-subtitle" style="color: #6e6e73; margin-bottom: 20px;">
						<?php esc_html_e( 'Please wait while your site is being restored...', 'royal-backup-reset' ); ?>
					</p>

					<!-- Progress Components List -->
					<ul class="royalbr-restore-components-list">
						<!-- Dynamic components will be inserted here by JavaScript -->
					</ul>

					<!-- Restore Result (hidden by default, shown on completion) -->
					<div class="royalbr-restore-result" style="display: none;">
						<span class="dashicons"></span>
						<span class="royalbr-restore-result--text"></span>
					</div>

					<!-- Hidden inputs for restore tracking -->
					<input type="hidden" id="royalbr_ajax_restore_task_id" value="">
					<input type="hidden" id="royalbr_ajax_restore_action" value="">
					<input type="hidden" id="royalbr_restore_log_file" value="">
				</div>
				<div class="royalbr-modal-footer">
					<button type="button" class="button" id="royalbr-progress-view-log" style="display: none;"><?php esc_html_e( 'View Activity Log', 'royal-backup-reset' ); ?></button>
					<button type="button" class="button button-primary" id="royalbr-progress-done" style="display: none;"><?php esc_html_e( 'Done', 'royal-backup-reset' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		$modal_html = ob_get_clean();

		wp_send_json_success( array(
			'html' => $modal_html,
		) );
	}


	/**
	 * AJAX handler to get component selection modal HTML.
	 *
	 * Returns the component selection modal HTML for quick actions restore,
	 * showing only the component checkboxes without the progress section.
	 *
	 * @since 1.0.0
	 */
	public function get_component_selection_modal_html_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Get context parameter (admin-page or quick-actions).
		$context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : 'admin-page';

		// Validate context.
		$valid_contexts = array( 'admin-page', 'quick-actions' );
		if ( ! in_array( $context, $valid_contexts, true ) ) {
			$context = 'admin-page';
		}

		// Build context class.
		$context_class = 'royalbr-' . $context . '-modal';

		// Generate component selection modal HTML.
		$is_premium_restore = function_exists( 'royalbr_fs' ) && royalbr_fs()->can_use_premium_code();
		ob_start();
		?>
		<div id="royalbr-component-selection-modal" class="royalbr-modal <?php echo esc_attr( $context_class ); ?>" style="display: none;">
			<div class="royalbr-modal-content royalbr-modal-large">
				<div class="royalbr-modal-header">
					<h3>
						<?php esc_html_e( 'Choose Items to Restore', 'royal-backup-reset' ); ?>
						<?php if ( ! $is_premium_restore ) : ?>
							<a href="#" class="royalbr-pro-badge"><?php esc_html_e( 'PRO', 'royal-backup-reset' ); ?></a>
						<?php endif; ?>
					</h3>
					<span class="royalbr-modal-close">&times;</span>
				</div>
				<div class="royalbr-modal-body">
					<form id="royalbr-component-selection-form">
						<input type="hidden" id="royalbr-component-selection-timestamp" value="">
						<input type="hidden" id="royalbr-component-selection-nonce" value="">
						<fieldset class="royalbr-restore-components <?php echo $is_premium_restore ? '' : 'royalbr-pro-option-disabled'; ?>" <?php if ( ! $is_premium_restore ) : ?>data-pro-option-name="<?php esc_attr_e( 'Choose Items to Restore', 'royal-backup-reset' ); ?>"<?php endif; ?>>
							<?php if ( $is_premium_restore ) : ?>
							<div class="royalbr-restore-item royalbr-restore-select-all">
								<label>
									<input type="checkbox" class="royalbr-custom-checkbox" id="royalbr_component_select_all">
									<strong><?php esc_html_e( 'Select Everything', 'royal-backup-reset' ); ?></strong>
								</label>
							</div>
							<?php endif; ?>
							<div class="royalbr-restore-item">
								<label>
									<input type="checkbox" class="royalbr-custom-checkbox" id="royalbr_component_db" name="royalbr_component[]" value="db" checked <?php echo $is_premium_restore ? '' : 'disabled'; ?>>
									<div class="royalbr-restore-item-content">
										<strong><?php esc_html_e( 'Database Content', 'royal-backup-reset' ); ?></strong>
										<p class="description"><?php esc_html_e( 'Restore complete database - your posts, pages, users and settings.', 'royal-backup-reset' ); ?></p>
									</div>
								</label>
							</div>
							<div class="royalbr-restore-item">
								<label>
									<input type="checkbox" class="royalbr-custom-checkbox" id="royalbr_component_plugins" name="royalbr_component[]" value="plugins" <?php echo $is_premium_restore ? '' : 'checked disabled'; ?>>
									<div class="royalbr-restore-item-content">
										<strong><?php esc_html_e( 'Plugin Files', 'royal-backup-reset' ); ?></strong>
										<p class="description"><?php esc_html_e( 'Restore entire wp-content/plugins folder.', 'royal-backup-reset' ); ?></p>
									</div>
								</label>
							</div>
							<div class="royalbr-restore-item">
								<label>
									<input type="checkbox" class="royalbr-custom-checkbox" id="royalbr_component_themes" name="royalbr_component[]" value="themes" <?php echo $is_premium_restore ? '' : 'checked disabled'; ?>>
									<div class="royalbr-restore-item-content">
										<strong><?php esc_html_e( 'Theme Files', 'royal-backup-reset' ); ?></strong>
										<p class="description"><?php esc_html_e( 'Restore entire wp-content/themes folder.', 'royal-backup-reset' ); ?></p>
									</div>
								</label>
							</div>
							<div class="royalbr-restore-item">
								<label>
									<input type="checkbox" class="royalbr-custom-checkbox" id="royalbr_component_uploads" name="royalbr_component[]" value="uploads" <?php echo $is_premium_restore ? '' : 'checked disabled'; ?>>
									<div class="royalbr-restore-item-content">
										<strong><?php esc_html_e( 'Media Uploads', 'royal-backup-reset' ); ?></strong>
										<p class="description"><?php esc_html_e( 'Restore entire wp-content/uploads folder including images, videos, etc...', 'royal-backup-reset' ); ?></p>
									</div>
								</label>
							</div>
							<div class="royalbr-restore-item">
								<label>
									<input type="checkbox" class="royalbr-custom-checkbox" id="royalbr_component_others" name="royalbr_component[]" value="others" <?php echo $is_premium_restore ? '' : 'checked disabled'; ?>>
									<div class="royalbr-restore-item-content">
										<strong><?php esc_html_e( 'Additional Files', 'royal-backup-reset' ); ?></strong>
										<p class="description"><?php esc_html_e( 'Restore remaining wp-content items.', 'royal-backup-reset' ); ?></p>
									</div>
								</label>
							</div>
						</fieldset>
						<?php if ( ! $is_premium_restore ) : ?>
						<p class="description" style="margin-top: 15px;"><?php esc_html_e( 'Free version restores all available components. Upgrade to PRO to select specific items.', 'royal-backup-reset' ); ?></p>
						<?php endif; ?>
					</form>
				</div>
				<div class="royalbr-modal-footer">
					<button type="button" class="button" id="royalbr-component-selection-cancel"><?php esc_html_e( 'Cancel', 'royal-backup-reset' ); ?></button>
					<button type="button" class="button button-primary" id="royalbr-component-selection-proceed"><?php esc_html_e( 'Continue', 'royal-backup-reset' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		$modal_html = ob_get_clean();

		wp_send_json_success( array(
			'html' => $modal_html,
		) );
	}

	/**
	 * AJAX handler to get reset progress modal HTML.
	 *
	 * Returns the reset progress modal HTML for tracking database reset operations.
	 *
	 * @since 1.0.0
	 */
	public function get_reset_progress_modal_html_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		// Get context parameter (admin-page or quick-actions).
		$context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : 'admin-page';

		// Validate context.
		$valid_contexts = array( 'admin-page', 'quick-actions' );
		if ( ! in_array( $context, $valid_contexts, true ) ) {
			$context = 'admin-page';
		}

		// Build context class.
		$context_class = 'royalbr-' . $context . '-modal';

		// Generate reset progress modal HTML.
		ob_start();
		?>
		<div id="royalbr-reset-progress-modal" class="royalbr-modal <?php echo esc_attr( $context_class ); ?>" style="display: none;">
			<div class="royalbr-modal-content royalbr-modal-large">
				<div class="royalbr-modal-header">
					<h3><?php esc_html_e( 'Reset in Progress', 'royal-backup-reset' ); ?></h3>
				</div>
				<div class="royalbr-modal-body">
					<p class="royalbr-reset-subtitle" style="color: #6e6e73; margin-bottom: 20px;">
						<?php esc_html_e( 'Please wait while your database is being reset...', 'royal-backup-reset' ); ?>
					</p>

					<!-- Progress Components List -->
					<ul class="royalbr-restore-components-list">
						<!-- Dynamic components will be inserted here by JavaScript -->
					</ul>

					<!-- Reset Result (hidden by default, shown on completion) -->
					<div class="royalbr-restore-result" style="display: none;">
						<span class="dashicons"></span>
						<span class="royalbr-restore-result--text"></span>
					</div>
				</div>
				<div class="royalbr-modal-footer">
					<button type="button" class="button button-primary" id="royalbr-reset-progress-done" style="display: none;"><?php esc_html_e( 'Done', 'royal-backup-reset' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		$modal_html = ob_get_clean();

		wp_send_json_success( array(
			'html' => $modal_html,
		) );
	}

	/**
	 * Returns the modal HTML for PRO feature upgrade prompt.
	 *
	 * Displays a modal informing free users that the feature they clicked
	 * is a PRO-only feature with an upgrade button.
	 *
	 * @since 1.0.0
	 */
	public function get_pro_modal_html_ajax() {
		check_ajax_referer( 'royalbr_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'royal-backup-reset' ) );
		}

		$upgrade_url = function_exists( 'royalbr_fs' ) ? royalbr_fs()->get_upgrade_url() : '#';

		ob_start();
		?>
		<div id="royalbr-pro-modal" class="royalbr-modal" style="display: none;">
			<div class="royalbr-modal-content">
				<div class="royalbr-modal-header royalbr-pro-modal-header">
					<span class="royalbr-modal-close">&times;</span>
					<div class="royalbr-pro-modal-icon-title">
						<span class="dashicons dashicons-lock"></span>
						<h3 id="royalbr-pro-modal-title"><?php esc_html_e( 'Premium Feature', 'royal-backup-reset' ); ?></h3>
					</div>
				</div>
				<div class="royalbr-modal-body">
					<p id="royalbr-pro-modal-message"></p>
				</div>
				<div class="royalbr-modal-footer royalbr-modal-footer-centered">
					<button type="button" class="button button-primary" id="royalbr-pro-modal-upgrade-btn" data-upgrade-url="<?php echo esc_url( $upgrade_url ); ?>"><?php esc_html_e( 'Upgrade to PRO', 'royal-backup-reset' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		$modal_html = ob_get_clean();

		wp_send_json_success( array( 'html' => $modal_html ) );
	}

	/**
	 * Initializes restore operation based on request type.
	 *
	 * @since 1.0.0
	 */
	public function prepare_restore() {
		// Security verification completed at entry point.
		// Check whether restore is starting or resuming.
		$is_continuation = ( isset( $_REQUEST['action'] ) && 'royalbr_restore_continue' === $_REQUEST['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $is_continuation ) {
			// First restore request, initialize task data.
			if ( ! isset( $_REQUEST['timestamp'] ) || empty( $_REQUEST['timestamp'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_die( esc_html__( 'Backup timestamp parameter is missing.', 'royal-backup-reset' ) );
			}

			$timestamp = sanitize_text_field( wp_unslash( $_REQUEST['timestamp'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			// Extract and validate requested components.
			$components = array();
			if ( isset( $_REQUEST['components'] ) && is_array( $_REQUEST['components'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$components = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['components'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$allowed    = array( 'db', 'plugins', 'themes', 'uploads', 'others' );
				$components = array_intersect( $components, $allowed );
			}

			if ( empty( $components ) ) {
				$components = array( 'db', 'plugins', 'themes', 'uploads', 'others' );
			}

			// Create unique restore session identifier.
			$this->file_nonce = $this->backup_time_nonce();

			// Initialize restore task metadata.
			$this->save_task_data( 'task_type', 'restore' );
			$this->task_time_ms = microtime( true );
			$this->save_task_data( 'task_time_ms', $this->task_time_ms );
			$this->save_task_data( 'backup_timestamp', $timestamp );
			$this->save_task_data( 'restore_components', $components );

			// Flag restore as active.
			update_site_option( 'royalbr_restore_in_progress', $this->file_nonce );
		}

		// Determine if AJAX restore interface should display.
		if ( isset( $_REQUEST['royalbr_ajax_restore'] ) && 'start_ajax_restore' == $_REQUEST['royalbr_ajax_restore'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Render restore progress interface.
			return $this->prepare_ajax_restore();
		}

		// Invalid restore request path.
		wp_die( esc_html__( 'Restoration request is invalid.', 'royal-backup-reset' ) );
	}

	/**
	 * Executes restore operation with streaming progress output.
	 *
	 * @since 1.0.0
	 */
	public function royalbr_ajaxrestore() {
		// Verify nonce and user capabilities.
		if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'royalbr_nonce' ) ) {
			die( 'Security Check' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			die( 'Insufficient permissions' );
		}

		// Check if this is a request to start a new restore
		if ( isset( $_REQUEST['royalbr_ajax_restore'] ) && 'start_ajax_restore' === $_REQUEST['royalbr_ajax_restore'] ) {
			// Create a new restore task
			if ( ! isset( $_REQUEST['timestamp'] ) || empty( $_REQUEST['timestamp'] ) ) {
				wp_send_json_error( 'Backup timestamp parameter is missing.' );
			}

			$timestamp = sanitize_text_field( wp_unslash( $_REQUEST['timestamp'] ) );

			// Extract and validate requested components
			$components = array();
			if ( isset( $_REQUEST['components'] ) && is_array( $_REQUEST['components'] ) ) {
				$components = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['components'] ) );
				$allowed    = array( 'db', 'plugins', 'themes', 'uploads', 'others' );
				$components = array_intersect( $components, $allowed );
			}

			if ( empty( $components ) ) {
				$components = array( 'db', 'plugins', 'themes', 'uploads', 'others' );
			}

			// Create unique restore session identifier
			$this->file_nonce = $this->backup_time_nonce();

			// Initialize restore task metadata
			$this->save_task_data( 'task_type', 'restore' );
			$this->task_time_ms = microtime( true );
			$this->save_task_data( 'task_time_ms', $this->task_time_ms );
			$this->save_task_data( 'backup_timestamp', $timestamp );
			$this->save_task_data( 'restore_components', $components );

			// Flag restore as active
			update_site_option( 'royalbr_restore_in_progress', $this->file_nonce );

			// If this is an AJAX request (from modal), return JSON with task_id
			if ( wp_doing_ajax() ) {
				wp_send_json_success( array( 'task_id' => $this->file_nonce ) );
			}

			// Otherwise, prepare the restore page (for non-AJAX requests)
			return $this->prepare_ajax_restore();
		}

		// Enable real-time progress streaming for continuing restore
		if ( ! empty( $_REQUEST['royalbr_ajax_restore'] ) ) {
			add_filter( 'royalbr_logline', array( $this, 'royalbr_logline' ), 10, 5 );

			// Configure server for immediate output delivery.
			$this->stream_output_to_browser( '' );
			// Ensure log lines stream to browser immediately.
			@ini_set( 'zlib.output_compression', '0' ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for streaming restore output
			// Disable web server buffering.
			header( 'X-Accel-Buffering: no' );
			header( 'Content-Encoding: none' );
			// Clear any pending output buffers.
			while ( ob_get_level() ) {
				ob_end_flush();
			}
		}

		// Retrieve restore session identifier for continuing restore
		$task_id = isset( $_REQUEST['task_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['task_id'] ) ) : get_site_option( 'royalbr_restore_in_progress' );

		if ( ! $task_id ) {
			die( 'No restore task found' );
		}

		$this->file_nonce = $task_id;

		// Load operation start time for duration tracking.
		$this->task_time_ms = $this->retrieve_task_data( 'task_time_ms' );
		if ( ! $this->task_time_ms ) {
			$this->task_time_ms = microtime( true );
		}

		// Load restore operation parameters.
		$backup_timestamp = $this->retrieve_task_data( 'backup_timestamp' );
		$components       = $this->retrieve_task_data( 'restore_components' );

		if ( ! $backup_timestamp ) {
			die( 'No backup timestamp found in taskdata' );
		}

		// Initialize restore handler and log file.
		$restore_handler = $this->get_restore_handler();

		// Create log file for restore operation.
		if ( method_exists( $restore_handler, 'init_restore_log' ) ) {
			$reflection = new ReflectionClass( $restore_handler );
			$method = $reflection->getMethod( 'init_restore_log' );
			$method->setAccessible( true );
			$method->invoke( $restore_handler, $task_id );
		}

		// Share restore instance with logging system.
		global $royalbr_restore_instance;
		$royalbr_restore_instance = $restore_handler;

		// Execute restore with progress streaming.
		$result = $restore_handler->restore_backup_session( $backup_timestamp, $components );

		// Evaluate restore operation result.
		$success = false;
		if ( isset( $result['success'] ) && $result['success'] ) {
			$success = true;
		}

		// Finalize log file with completion status.
		if ( method_exists( $restore_handler, 'close_restore_log' ) ) {
			$reflection = new ReflectionClass( $restore_handler );
			$method = $reflection->getMethod( 'close_restore_log' );
			$method->setAccessible( true );
			$method->invoke( $restore_handler, $success );
		}

		// Retrieve log file location for display.
		$log_file = '';
		if ( method_exists( $restore_handler, 'get_restore_log_file' ) ) {
			$log_file = $restore_handler->get_restore_log_file();
		}

		// Send completion status and log location to JavaScript.
		echo '<input type="hidden" id="royalbr_restore_log_file" value="' . esc_attr( $log_file ) . '">';

		if ( is_wp_error( $result ) ) {
			echo '<p class="royalbr_restore_error">';
			echo esc_html__( 'Restoration process failed', 'royal-backup-reset' );
			echo '</p>';
			echo '<div class="royalbr_restore_errors">';
			echo esc_html( $result->get_error_message() );
			echo '</div>';
		} elseif ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
			echo '<p class="royalbr_restore_error">';
			echo esc_html__( 'Restoration process failed', 'royal-backup-reset' );
			echo '</p>';
			echo '<div class="royalbr_restore_errors">';
			echo esc_html( $result['error'] );
			echo '</div>';
		} elseif ( $success ) {
			echo '<p class="royalbr_restore_successful"><strong>';
			echo esc_html__( 'Site Restored Successfuly', 'royal-backup-reset' );
			echo '</strong></p>';
		} else {
			echo '<p class="royalbr_restore_error">';
			echo esc_html__( 'Restoration process failed', 'royal-backup-reset' );
			echo '</p>';
		}

		die();
	}

	/**
	 * Renders standalone HTML page for restore progress tracking.
	 *
	 * @since 1.0.0
	 */
	private function prepare_ajax_restore() {
		// Locate restore session identifier.
		$task_id = isset( $_REQUEST['task_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['task_id'] ) ) : get_site_option( 'royalbr_restore_in_progress' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $task_id ) {
			wp_die( esc_html__( 'Restoration task could not be located.', 'royal-backup-reset' ) );
		}

		// Load task configuration.
		$this->file_nonce = $task_id;
		$taskdata         = $this->retrieve_task_array();

		if ( empty( $taskdata ) || ! isset( $taskdata['restore_components'] ) ) {
			wp_die( esc_html__( 'Restoration task data is corrupted.', 'royal-backup-reset' ) );
		}

		$restore_components = $taskdata['restore_components'];
		$backup_timestamp   = isset( $taskdata['backup_timestamp'] ) ? $taskdata['backup_timestamp'] : 0;

		// Select appropriate AJAX endpoint for restore.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ajax_action = ( isset( $_REQUEST['royalbr_ajax_restore'] ) && 'continue_ajax_restore' === $_REQUEST['royalbr_ajax_restore'] && isset( $_REQUEST['action'] ) && 'royalbr_restore' !== $_REQUEST['action'] )
			? 'royalbr_ajaxrestore_continue'
			: 'royalbr_ajax_restore';

		// Define display labels for components.
		$component_labels = array(
			'db'      => __( 'Database', 'royal-backup-reset' ),
			'plugins' => __( 'Plugins', 'royal-backup-reset' ),
			'themes'  => __( 'Themes', 'royal-backup-reset' ),
			'uploads' => __( 'Uploads', 'royal-backup-reset' ),
			'others'  => __( 'Others', 'royal-backup-reset' ),
		);

		// Define helper text for progress steps.
		$component_helpers = array(
			'verifying' => __( 'Checking backup integrity and file availability', 'royal-backup-reset' ),
			'db'        => __( 'Restoring database tables and content', 'royal-backup-reset' ),
			'plugins'   => __( 'Extracting and installing plugin files', 'royal-backup-reset' ),
			'themes'    => __( 'Restoring theme files and configurations', 'royal-backup-reset' ),
			'uploads'   => __( 'Restoring media library and uploaded files', 'royal-backup-reset' ),
			'others'    => __( 'Restoring additional content and configurations', 'royal-backup-reset' ),
			'finished'  => __( 'Finalizing restoration and cleaning up', 'royal-backup-reset' ),
		);

		// Render standalone HTML page for restore progress.
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php esc_html_e( 'Royal Backup & Reset - Restore Process', 'royal-backup-reset' ); ?></title>
			<?php
			// Load WordPress core dependencies.
			wp_enqueue_script( 'jquery' );
			wp_enqueue_style( 'dashicons' );

			// Load restore progress JavaScript.
			wp_enqueue_script( 'royalbr-admin-restore', plugins_url( 'assets/admin-restore.js', __FILE__ ), array( 'jquery' ), ROYALBR_VERSION, true );

			// Load interface stylesheets.
			wp_enqueue_style( 'royalbr-admin', plugins_url( 'assets/admin.css', __FILE__ ), array( 'dashicons' ), ROYALBR_VERSION );

			// Pass configuration to JavaScript.
			wp_localize_script(
				'royalbr-admin-restore',
				'royalbr_restore',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'royalbr_nonce' ),
				)
			);

			// Output asset tags.
			wp_print_styles();
			wp_print_scripts();
			?>
		</head>
		<body class="wp-admin wp-core-ui">
			<div class="royalbr-restore-container">
				<div class="error" id="royalbr-restore-hidethis">
					<p><strong><?php esc_html_e( 'Alert: If this message remains visible after page load, there may be a JavaScript issue.', 'royal-backup-reset' ); ?> <?php esc_html_e( 'This could interfere with the restoration process.', 'royal-backup-reset' ); ?></strong></p>
				</div>
				<div class="royalbr-restore-main--header"><?php esc_html_e( 'Site Restoration In Progress', 'royal-backup-reset' ); ?> - <?php esc_html_e( 'Archive', 'royal-backup-reset' ); ?> <?php echo esc_html( gmdate( 'M d, Y H:i', $backup_timestamp ) ); ?></div>
				<div class="royalbr-restore-main">
					<!-- Task parameters for JavaScript progress tracker -->
					<input type="hidden" id="royalbr_ajax_restore_task_id" value="<?php echo esc_attr( $task_id ); ?>">
					<input type="hidden" id="royalbr_ajax_restore_action" value="<?php echo esc_attr( $ajax_action ); ?>">
					<div id="royalbr_ajax_restore_progress" style="display: none;"></div>

					<div class="royalbr-restore-main--components">
						<?php /* translators: %s: Task ID for the restore operation */ ?>
						<p><?php echo sprintf( esc_html__( 'Restoration has started (Task: %s).', 'royal-backup-reset' ), '<strong>'. esc_html( $task_id ) .'</strong>' ); ?> <br>
						<?php esc_html_e( 'Please do NOT reload the page open until the process is complete.', 'royal-backup-reset' ); ?></p>

					<ul class="royalbr-restore-components-list">
						<li data-component="verifying" class="active">
							<div class="royalbr-component--wrapper">
								<span class="royalbr-component--description"><?php esc_html_e( 'Verification', 'royal-backup-reset' ); ?></span>
								<span class="royalbr-component--helper"><?php echo esc_html( $component_helpers['verifying'] ); ?></span>
							</div>
							<span class="royalbr-component--progress"></span>
						</li>
						<?php foreach ( $restore_components as $component ) : ?>
							<?php
							$label = isset( $component_labels[ $component ] ) ? $component_labels[ $component ] : $component;
							$helper = isset( $component_helpers[ $component ] ) ? $component_helpers[ $component ] : '';
							?>
							<li data-component="<?php echo esc_attr( $component ); ?>">
								<div class="royalbr-component--wrapper">
									<span class="royalbr-component--description"><?php echo esc_html( $label ); ?></span>
									<?php if ( $helper ) : ?>
										<span class="royalbr-component--helper"><?php echo esc_html( $helper ); ?></span>
									<?php endif; ?>
								</div>
								<span class="royalbr-component--progress"></span>
							</li>
						<?php endforeach; ?>
						<!-- <li data-component="cleaning">
							<div class="royalbr-component--wrapper">
								<span class="royalbr-component--description"><?php esc_html_e( 'Cleanup', 'royal-backup-reset' ); ?></span>
								<span class="royalbr-component--helper"><?php esc_html_e( 'Removing temporary files', 'royal-backup-reset' ); ?></span>
							</div>
							<span class="royalbr-component--progress"></span>
						</li> -->
						<li data-component="finished">
							<div class="royalbr-component--wrapper">
								<span class="royalbr-component--description"><?php esc_html_e( 'Complete', 'royal-backup-reset' ); ?></span>
								<span class="royalbr-component--helper"><?php echo esc_html( $component_helpers['finished'] ); ?></span>
							</div>
							<span class="royalbr-component--progress"></span>
						</li>
					</ul>
						</ul>

						<!-- Completion Section (hidden until restore finishes) -->
						<div class="royalbr-restore-result"><span class="royalbr-restore-result--text"></span><span class="dashicons"></span></div>
						<div class="royalbr-restore-completion" style="display: none;">
							<div class="royalbr-restore-error-message" style="display: none;"></div>
							<div class="royalbr-restore-buttons">
								<button type="button" id="royalbr-view-restore-log" class="button"><?php esc_html_e( 'View Log', 'royal-backup-reset' ); ?></button>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=royal-backup-reset' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Return to Admin Page', 'royal-backup-reset' ); ?></a>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Log Viewer Modal -->
			<div id="royalbr-log-popup" class="royalbr-modal" style="display: none;">
				<div class="royalbr-modal-content royalbr-log-modal-content">
					<div class="royalbr-modal-header">
						<div style="display: flex; flex-direction: column; gap: 4px; flex: 1;">
							<p id="royalbr-log-modal-filename" style="font-size: 12px; color: #999; margin: 0; font-weight: normal;"></p>
							<h3 id="royalbr-log-modal-title" style="margin: 0; font-size: 18px;"><?php esc_html_e( 'Activity Log', 'royal-backup-reset' ); ?></h3>
						</div>
						<span class="royalbr-modal-close">&times;</span>
					</div>
					<div class="royalbr-modal-body">
						<pre id="royalbr-log-content" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto; background: #f5f5f5; padding: 10px; border: 1px solid #ddd; font-family: monospace; font-size: 12px;"></pre>
					</div>
					<div class="royalbr-modal-footer">
						<button type="button" class="button button-primary" id="royalbr-download-log">
							<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 4px;"></span>
							<?php esc_html_e( 'Download Log', 'royal-backup-reset' ); ?>
						</button>
					</div>
				</div>
			</div>

			<?php wp_print_footer_scripts(); ?>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Sends AJAX response and continues server-side processing.
	 *
	 * @since  1.0.0
	 * @param  string $txt JSON-encoded response text
	 * @return void
	 */
	public function close_browser_connection( $txt = '' ) {
		// Close connection to allow concurrent status requests.
		header( 'Content-Length: ' . ( empty( $txt ) ? '0' : 4 + strlen( $txt ) ) );
		header( 'Content-Type: application/json' );
		header( 'Connection: close' );
		if ( function_exists( 'session_id' ) && session_id() ) {
			session_write_close();
		}
		echo "\r\n\r\n";
		echo wp_kses_post( $txt );
		// Force immediate output delivery.
		$ob_level = ob_get_level();
		while ( $ob_level > 0 ) {
			ob_end_flush();
			$ob_level--;
		}
		flush();
		// Complete response transmission for supported servers.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		if ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}
	}

	/**
	 * Detects user-requested backup abortion via flag file.
	 *
	 * @since 1.0.0
	 * @return bool True if abort requested, false otherwise
	 */
	private function check_abort_requested() {
		$backup_dir = ROYALBR_BACKUP_DIR;
		$deleteflag = $backup_dir . 'deleteflag-' . $this->file_nonce . '.txt';

		if ( file_exists( $deleteflag ) ) {
			$backup_handler = $this->get_backup_handler();
			$backup_handler->log( 'User request for abort: backup task will be immediately halted' );

			// Remove abort signal file.
			wp_delete_file( $deleteflag );

			// Trigger cleanup and finalization.
			$this->backup_finish( true );

			return true;
		}

		return false;
	}

	/**
	 * Performs cleanup tasks when backup completes or is aborted.
	 *
	 * Finish the backup and clean up.
	 *
	 * Releases semaphore, clears scheduled resumptions, and marks backup complete.
	 * This method is called both on successful completion and on abort.
	 *
	 * @since 1.0.0
	 * @param bool $do_cleanup  Whether to perform cleanup tasks.
	 * @param bool $force_abort Whether this is an abort rather than completion.
	 * @return void
	 */
	public function backup_finish( $do_cleanup = true, $force_abort = false ) {
		$backup_handler = $this->get_backup_handler();

		// Release semaphore lock if held.
		if ( ! empty( $this->semaphore ) ) {
			$this->semaphore->unlock();
		}

		if ( $do_cleanup ) {
			// Clear scheduled resumptions (next 5).
			$next_resumption = $this->current_resumption + 1;
			for ( $i = 0; $i < 5; $i++ ) {
				wp_clear_scheduled_hook( 'royalbr_backup_resume', array( $next_resumption + $i, $this->file_nonce ) );
			}

			// Check current taskstatus - don't overwrite if already 'failed'.
			$current_status = $this->retrieve_task_data( 'taskstatus' );

			if ( $force_abort ) {
				// Mark task as aborted.
				$this->save_task_data( 'taskstatus', 'aborted' );
				$this->save_task_data( 'backup_complete', false );
				$backup_handler->log( 'Backup aborted' );
				// Clean up partial backup files on abort.
				$this->cleanup_failed_backup_files( $this->file_nonce, $backup_handler );
			} elseif ( 'failed' !== $current_status ) {
				// Only set to 'finished' if not already failed.
				$this->save_task_data( 'taskstatus', 'finished' );
				$backup_handler->log( 'Backup finished - cleared scheduled resumptions' );
			} else {
				$backup_handler->log( 'Backup failed - cleared scheduled resumptions' );
				// Clean up partial backup files on failure.
				$this->cleanup_failed_backup_files( $this->file_nonce, $backup_handler );
			}
		}

		// Close log file.
		$backup_handler->logfile_close();

		// Note: Do NOT delete taskdata here. It's needed for progress polling to detect
		// backup_complete/finished status. Cleanup happens when a new backup starts.

		// Terminate if aborted.
		if ( $force_abort && $do_cleanup ) {
			die;
		}
	}

	/**
	 * Clean up partial backup files when backup fails or is aborted.
	 *
	 * Removes all backup files (zip, gz, tmp) associated with the given nonce
	 * to prevent orphaned files from cluttering the backup directory.
	 *
	 * @since 1.0.0
	 * @param string               $nonce          The backup nonce identifying the files.
	 * @param ROYALBR_Backup|null  $backup_handler Optional backup handler for logging.
	 * @return int Number of files deleted.
	 */
	private function cleanup_failed_backup_files( $nonce, $backup_handler = null ) {
		if ( empty( $nonce ) ) {
			return 0;
		}

		$backup_dir    = rtrim( ROYALBR_BACKUP_DIR, '/\\' ) . DIRECTORY_SEPARATOR;
		$deleted_count = 0;

		// Pattern to match all backup files with this nonce.
		$pattern = $backup_dir . '*' . $nonce . '*';
		$files   = glob( $pattern );

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				// Skip log files - keep them for debugging.
				if ( preg_match( '/log\.[a-f0-9]+\.txt$/', $file ) || preg_match( '/-log\.txt$/', $file ) ) {
					continue;
				}

				// Delete backup files (zip, gz, tmp).
				if ( is_file( $file ) && wp_delete_file( $file ) ) {
					$deleted_count++;
					if ( $backup_handler ) {
						$backup_handler->log( 'Cleanup: deleted partial file ' . basename( $file ) );
					}
				}
			}
		}

		if ( $backup_handler && $deleted_count > 0 ) {
			$backup_handler->log( 'Cleanup complete: removed ' . $deleted_count . ' partial backup file(s)' );
		}

		return $deleted_count;
	}

	/**
	 * Orchestrates complete backup process including database and file backups.
	 *
	 * @since  1.0.0
	 * @param  bool $backup_database Whether to backup database
	 * @param  bool $backup_files    Whether to backup files
	 * @return void
	 */
	public function perform_backup( $backup_database = true, $backup_files = true ) {
		// Initialize backup time and nonce if not already set.
		if ( empty( $this->file_nonce ) || empty( $this->backup_time ) ) {
			$this->backup_time_nonce();
		}

		// Initialize backup log file.
		$backup_handler = $this->get_backup_handler();
		$backup_handler->logfile_open( $this->file_nonce );

		$backup_handler->log( 'Backup requested: Database=' . ( $backup_database ? 'Yes' : 'No' ) . ', Files=' . ( $backup_files ? 'Yes' : 'No' ) );

		// Set initial operation status.
		$this->save_task_data( 'taskstatus', 'begun' );
		$backup_handler->log( 'Task status set to: begun' );

		// Execute database backup phase.
		if ( $backup_database ) {

			// Update progress to database backup phase.
			$this->save_task_data( 'taskstatus', 'dbcreating' );
			$backup_handler->log( 'Task status set to: dbcreating' );

			$db_file = $backup_handler->create_database_backup( 'begun', 'wp', array() );

			if ( $db_file ) {
				// Record successful database backup.
				$this->save_task_data( 'backup_database', array( 'wp' => array( 'status' => 'finished' ) ) );
				$this->save_task_data( 'backup_db_file', $db_file );
				$this->save_task_data( 'taskstatus', 'dbcreated' );
				$backup_handler->log( 'Database backup completed: ' . $db_file );
			} else {
				$backup_handler->log( 'Database backup failed', 'error' );
				$this->save_task_data( 'backup_database', array( 'wp' => array( 'status' => 'failed' ) ) );
			}
		}

		// Exit if user cancelled backup.
		if ( $this->check_abort_requested() ) {
			return;
		}

		// Execute file backup phase.
		if ( $backup_files ) {
			$backup_handler->log( 'Starting file backup...' );

			// Update progress to file backup phase.
			$this->save_task_data( 'taskstatus', 'filescreating' );
			$backup_handler->log( 'Task status set to: filescreating' );

			$backup_files_array = $backup_handler->process_file_backup();

			// Handle WP_Error from backup process (e.g., disk space issues).
			if ( is_wp_error( $backup_files_array ) ) {
				$error_message = $backup_files_array->get_error_message();
				$backup_handler->log( 'File backup error: ' . $error_message );
				$this->save_task_data( 'backup_files', 'failed' );
				$this->save_task_data( 'backup_complete', false );
				$this->save_task_data( 'taskstatus', 'failed' );
				$this->save_task_data( 'backup_error', $error_message );
				$this->backup_finish( true, false );
				return;
			} elseif ( ! empty( $backup_files_array ) ) {
				// Record successful file backup.
				$this->save_task_data( 'backup_files', 'finished' );
				$this->save_task_data( 'backup_files_array', $backup_files_array );
				$this->save_task_data( 'taskstatus', 'filescreated' );
				$backup_handler->log( 'File backup completed - ' . count( $backup_files_array ) . ' entities backed up' );
			} else {
				$backup_handler->log( 'File backup failed or no files to backup', 'warning' );
				$this->save_task_data( 'backup_files', 'failed' );
			}
		}

		// Check for errors before marking backup complete.
		$backup_error = $backup_handler->get_backup_error();
		if ( ! empty( $backup_error ) ) {
			$this->save_task_data( 'backup_complete', false );
			$this->save_task_data( 'taskstatus', 'failed' );
			$backup_handler->log( 'Backup failed with error: ' . $backup_error );
			$backup_handler->logfile_close();
			return; // Don't save to history - backup is incomplete.
		}

		// Set completion flag and final status.
		$this->save_task_data( 'backup_complete', true );
		$this->save_task_data( 'taskstatus', 'finished' );
		$backup_handler->log( 'Backup complete - nonce: ' . $this->file_nonce );

		// Record backup in database history.
		$backup_handler->log( 'Saving backup to history' );
		$this->save_backup_to_history( $backup_database, $backup_files );

		// Finalize and close log file.
		$backup_handler->log( 'Closing log file' );
		$backup_handler->logfile_close();

		// Note: Do NOT delete taskdata here. It's needed for progress polling to detect
		// backup_complete=true status. Cleanup happens when a new backup starts or via
		// scheduled cleanup of old task data.
	}

	/**
	 * WP-Cron handler for backup resumption.
	 *
	 * This method is called via WP-Cron to resume a backup after timeout.
	 * Each resumption processes a chunk of work, saves progress, and schedules
	 * the next resumption. This allows large backups to complete across
	 * multiple HTTP requests.
	 *
	 * @since 1.0.0
	 * @param int    $resumption_no Which resumption attempt (0 = first).
	 * @param string $bnonce        Backup task identifier.
	 * @return void
	 */
	public function backup_resume( $resumption_no, $bnonce ) {
		// Reset internal state if called multiple times in same context.
		static $last_bnonce = null;
		if ( $last_bnonce ) {
			$this->taskdata = array();
		}
		$last_bnonce = $bnonce;

		$this->current_resumption = $resumption_no;

		// Extend PHP limits for backup processing.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 900 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( function_exists( 'ignore_user_abort' ) ) {
			@ignore_user_abort( true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$runs_started = array();
		$time_now     = microtime( true );

		// Restore state for non-initial resumptions.
		$resumption_extralog       = '';
		$prev_resumption           = $resumption_no - 1;
		$last_successful_resumption = -1;

		if ( 0 === $resumption_no ) {
			// First resumption - load state from taskdata initialized by create_backup_ajax.
			$this->file_nonce   = $bnonce;
			$this->backup_time  = $this->retrieve_task_data( 'backup_time' );
			$this->task_time_ms = $this->retrieve_task_data( 'task_time_ms' );

			// Open log file for this resumption.
			$backup_handler = $this->get_backup_handler();
			$backup_handler->logfile_open( $this->file_nonce );
			$this->opened_log_time = microtime( true );

			// Try to acquire semaphore lock to prevent concurrent runs.
			if ( ! $this->get_backup_semaphore_lock( $bnonce, $resumption_no ) ) {
				$backup_handler->log( 'Failed to get backup semaphore lock; possible overlapping resumptions - will abort this instance' );
				die;
			}
		} else {
			// Subsequent resumption - restore state from taskdata.
			$this->file_nonce  = $bnonce;
			$file_nonce        = $this->retrieve_task_data( 'file_nonce' );
			$this->file_nonce  = $file_nonce ? $file_nonce : $bnonce;
			$this->backup_time = $this->retrieve_task_data( 'backup_time' );
			$this->task_time_ms = $this->retrieve_task_data( 'task_time_ms' );

			// Open log file for this resumption.
			$backup_handler = $this->get_backup_handler();
			$backup_handler->logfile_open( $this->file_nonce );
			$this->opened_log_time = microtime( true );

			// Try to acquire semaphore lock to prevent concurrent runs.
			if ( ! $this->get_backup_semaphore_lock( $bnonce, $resumption_no ) ) {
				$backup_handler->log( 'Failed to get backup semaphore lock; possible overlapping resumptions - will abort this instance' );
				die;
			}

			// Check for overlap with previous resumption (30-second buffer).
			$runs_started = $this->retrieve_task_data( 'runs_started' );
			if ( ! is_array( $runs_started ) ) {
				$runs_started = array();
			}
			$time_passed = $this->retrieve_task_data( 'run_times' );
			if ( ! is_array( $time_passed ) ) {
				$time_passed = array();
			}

			foreach ( $time_passed as $run => $passed ) {
				if ( isset( $runs_started[ $run ] ) && $runs_started[ $run ] + $time_passed[ $run ] + 30 > $time_now ) {
					// Detected recent activity - another process may be running.
					$increase_resumption = ( $run && $run == $resumption_no ) ? false : true;
					ROYALBR_Task_Scheduler::terminate_due_to_activity( 'check-in', round( $time_now, 1 ), round( $runs_started[ $run ] + $time_passed[ $run ], 1 ), $increase_resumption );
				}
			}

			// Track which resumptions made progress.
			$useful_checkins = $this->retrieve_task_data( 'useful_checkins', array() );
			if ( ! empty( $useful_checkins ) ) {
				$last_successful_resumption = min( max( $useful_checkins ), $prev_resumption );
			}

			if ( isset( $time_passed[ $prev_resumption ] ) ) {
				$resumption_extralog = ', previous check-in=' . round( $time_passed[ $prev_resumption ], 2 ) . 's';
			}

			// Check for stale backup (> 2 days old).
			if ( $time_now - $this->backup_time > 172800 ) {
				$backup_handler->log( 'This backup task (' . $bnonce . ') is over 2 days old - ending' );
				die;
			}
		}

		$this->last_successful_resumption = $last_successful_resumption;

		// Detect if previous resumption made no progress (no check-in)
		$this->no_checkin_last_time = false;
		if ( $resumption_no >= 1 ) {
			$useful_checkins = $this->retrieve_task_data( 'useful_checkins', array() );
			// Check if previous resumption had a check-in
			if ( ! in_array( $prev_resumption, $useful_checkins, true ) ) {
				$this->no_checkin_last_time = true;

				// Log that previous run was interrupted (likely timeout).
				$backup_handler = $this->get_backup_handler();
				$backup_handler->log(
					sprintf(
						'Run %d did not complete - likely server timeout; continuing from last checkpoint',
						$prev_resumption
					),
					'warning'
				);

				// Reduce batch size early (before resumption 10) if no progress
				if ( $resumption_no <= 10 ) {
					$maxzipbatch     = $this->retrieve_task_data( 'maxzipbatch', 23068672 ); // Default 22MB.
					$new_maxzipbatch = max( floor( $maxzipbatch * 0.75 ), 5242880 ); // Minimum 5MB.
					if ( $new_maxzipbatch < $maxzipbatch ) {
						$this->save_task_data( 'maxzipbatch', $new_maxzipbatch );
						$backup_handler = $this->get_backup_handler();
						$backup_handler->log( sprintf( 'No check-in last run; reducing maxzipbatch from %s to %s', size_format( $maxzipbatch ), size_format( $new_maxzipbatch ) ) );
					}
				}
			}
		}

		// Record this run's start time.
		$runs_started[ $resumption_no ] = $time_now;
		if ( ! empty( $this->backup_time ) ) {
			$this->save_task_data( 'runs_started', $runs_started );
		}

		// Calculate resume interval.
		$resume_interval = max( (int) $this->retrieve_task_data( 'resume_interval' ), 100 );
		$btime           = $this->backup_time;

		$backup_handler = $this->get_backup_handler();
		$this->opened_log_time = microtime( true );

		$time_ago = time() - $btime;
		$backup_handler->log( "Backup run: resumption=$resumption_no, nonce=$bnonce, file_nonce={$this->file_nonce} begun at=$btime ({$time_ago}s ago)" . $resumption_extralog );

		// Check if backup is already complete.
		if ( $resumption_no >= 1 && 'finished' === $this->retrieve_task_data( 'taskstatus' ) ) {
			$backup_handler->log( 'Terminate: This backup task is already finished.' );
			die;
		}

		$this->save_task_data( 'current_resumption', $resumption_no );

		// Schedule next resumption as safety net.
		$next_resumption     = $resumption_no + 1;
		$schedule_resumption = true;

		// For resumptions 10+, only reschedule if useful work happened last time.
		if ( $next_resumption >= 10 ) {
			$useful_checkins = $this->retrieve_task_data( 'useful_checkins', array() );
			$last_useful     = ! empty( $useful_checkins ) ? max( $useful_checkins ) : 0;

			if ( $last_useful < $resumption_no - 1 ) {
				$fail_on_resume = $this->retrieve_task_data( 'fail_on_resume' );
				if ( empty( $fail_on_resume ) ) {
					$backup_handler->log( sprintf( 'Resumption %d: no useful work on last run (last useful: %d) - will abort if no progress this time', $resumption_no, $last_useful ) );
					$this->save_task_data( 'fail_on_resume', $next_resumption );
					$schedule_resumption = 1; // Schedule but mark for potential abort.

					// This helps on servers with slow I/O that timeout before completing a batch.
					$maxzipbatch     = $this->retrieve_task_data( 'maxzipbatch', 23068672 ); // Default 22MB.
					$new_maxzipbatch = max( floor( $maxzipbatch * 0.75 ), 5242880 ); // Minimum 5MB.
					if ( $new_maxzipbatch < $maxzipbatch ) {
						$this->save_task_data( 'maxzipbatch', $new_maxzipbatch );
						$backup_handler->log( sprintf( 'Reducing maxzipbatch from %s to %s due to lack of progress', size_format( $maxzipbatch ), size_format( $new_maxzipbatch ) ) );
					}
				}
			}

			// Check if we should abort due to repeated failures.
			$fail_on_resume = $this->retrieve_task_data( 'fail_on_resume' );
			if ( ! empty( $fail_on_resume ) && $fail_on_resume == $this->current_resumption ) {
				$backup_handler->log( 'The backup is being aborted for a repeated failure to progress.' );
				$this->backup_finish( true, true );
				die;
			}
		}

		// Sanity check.
		if ( empty( $this->backup_time ) ) {
			$backup_handler->log( 'The backup_time parameter is empty (usually caused by resuming an already-complete backup).' );
			return false;
		}

		// Schedule next resumption.
		if ( ! empty( $schedule_resumption ) ) {
			$schedule_for = time() + $resume_interval;
			if ( 1 === $schedule_resumption ) {
				$backup_handler->log( "Scheduling resumption ($next_resumption) after $resume_interval seconds; but task will abort unless progress happens" );
			} else {
				$backup_handler->log( "Scheduling resumption ($next_resumption) after $resume_interval seconds in case this run gets aborted" );
			}
			wp_schedule_single_event( $schedule_for, 'royalbr_backup_resume', array( $next_resumption, $bnonce ) );
			$this->newresumption_scheduled = $schedule_for;

			// Spawn loopback request to trigger WP-Cron (required for Local/shared hosts).
			$this->spawn_cron();
		}

		// Get backup configuration.
		$backup_database = $this->retrieve_task_data( 'task_backup_database' );
		$backup_files    = $this->retrieve_task_data( 'task_backup_files' );
		$taskstatus      = $this->retrieve_task_data( 'taskstatus' );

		// Execute database backup phase if not complete.
		if ( $backup_database && ! in_array( $taskstatus, array( 'dbcreated', 'filescreating', 'filescreated', 'finished' ), true ) ) {
			$this->save_task_data( 'taskstatus', 'dbcreating' );
			if ( $resumption_no > 0 ) {
				$backup_handler->log( 'Database export was interrupted; picking up where we left off' );
			} else {
				$backup_handler->log( 'Starting database export' );
			}

			$db_file = $backup_handler->create_database_backup( 'begun', 'wp', array() );

			if ( $db_file ) {
				$this->save_task_data( 'backup_database', array( 'wp' => array( 'status' => 'finished' ) ) );
				$this->save_task_data( 'backup_db_file', $db_file );
				$this->save_task_data( 'taskstatus', 'dbcreated' );
				$backup_handler->log( 'Database backup completed: ' . $db_file );
				ROYALBR_Task_Scheduler::something_useful_happened();
			} else {
				$backup_handler->log( 'Database backup failed or incomplete', 'error' );
			}
		}

		// Check abort request.
		if ( $this->check_abort_requested() ) {
			$this->backup_finish( true, true );
			return;
		}

		// Refresh taskstatus after database phase.
		$taskstatus = $this->retrieve_task_data( 'taskstatus' );

		// Execute file backup phase if not complete.
		if ( $backup_files && ! in_array( $taskstatus, array( 'filescreated', 'finished' ), true ) ) {
			$this->save_task_data( 'taskstatus', 'filescreating' );
			$existing_entities = $this->retrieve_task_data( 'task_file_entities' );
			if ( ! empty( $existing_entities ) || $resumption_no > 0 ) {
				$backup_handler->log( 'File archiving was interrupted; picking up where we left off' );
			} else {
				$backup_handler->log( 'Starting file archiving' );
			}

			$backup_files_array = $backup_handler->process_file_backup();

			// Handle WP_Error from backup process (e.g., disk space issues).
			if ( is_wp_error( $backup_files_array ) ) {
				$error_message = $backup_files_array->get_error_message();
				$backup_handler->log( 'File backup error: ' . $error_message );
				$this->save_task_data( 'backup_files', 'failed' );
				$this->save_task_data( 'backup_complete', false );
				$this->save_task_data( 'taskstatus', 'failed' );
				$this->save_task_data( 'backup_error', $error_message );
				$this->backup_finish( true, false );
				return;
			} elseif ( ! empty( $backup_files_array ) ) {
				$this->save_task_data( 'backup_files', 'finished' );
				$this->save_task_data( 'backup_files_array', $backup_files_array );
				$this->save_task_data( 'taskstatus', 'filescreated' );
				$backup_handler->log( 'File backup completed - ' . count( $backup_files_array ) . ' entities' );
				ROYALBR_Task_Scheduler::something_useful_happened();
			} else {
				$backup_handler->log( 'File backup incomplete or failed', 'warning' );
			}
		}

		// Check if backup is complete.
		$taskstatus = $this->retrieve_task_data( 'taskstatus' );
		$db_done    = ! $backup_database || 'dbcreated' === $this->retrieve_task_data( 'taskstatus' ) || in_array( $taskstatus, array( 'filescreating', 'filescreated', 'finished' ), true );
		$files_done = ! $backup_files || in_array( $taskstatus, array( 'filescreated', 'finished' ), true );

		if ( $db_done && $files_done ) {
			// Check disk space before marking complete - catch silent truncation.
			$backup_dir = defined( 'ROYALBR_BACKUP_DIR' ) ? ROYALBR_BACKUP_DIR : ( WP_CONTENT_DIR . '/royal-backup-reset/' );
			// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- Silenced to suppress errors that may arise because of the function.
			$disk_free = function_exists( 'disk_free_space' ) ? @disk_free_space( $backup_dir ) : false;

			if ( false !== $disk_free && $disk_free < 52428800 ) { // 50MB threshold.
				$error_msg = esc_html__( 'Insufficient disk space', 'royal-backup-reset' ) . ' (' . size_format( $disk_free ) . ' ' . esc_html__( 'remaining', 'royal-backup-reset' ) . ')';
				$backup_handler->set_backup_error( $error_msg );
				$backup_handler->log( $error_msg, 'error' );
			}

			// Check for errors before marking backup complete.
			$backup_error = $backup_handler->get_backup_error();
			if ( ! empty( $backup_error ) ) {
				$this->save_task_data( 'backup_complete', false );
				$this->save_task_data( 'taskstatus', 'failed' );
				$backup_handler->log( 'Backup failed with error: ' . $backup_error );
				$this->backup_finish( false, false );
				return;
			}

			$this->save_task_data( 'backup_complete', true );
			$this->save_task_data( 'taskstatus', 'finished' );
			$backup_handler->log( 'Backup complete - nonce: ' . $this->file_nonce );

			// Save to history.
			$this->save_backup_to_history( $backup_database, $backup_files );
			$backup_handler->log( 'Saved backup to history' );

			// Finish and clean up.
			$this->backup_finish( true, false );
		}
	}

	/**
	 * Try to acquire semaphore lock for backup.
	 *
	 * @since 1.0.0
	 * @param string $nonce         Backup nonce.
	 * @param int    $resumption_no Resumption number.
	 * @return bool True if lock acquired, false otherwise.
	 */
	private function get_backup_semaphore_lock( $nonce, $resumption_no ) {
		// Only use semaphore for resumptions after the first.
		if ( $resumption_no < 1 ) {
			return true;
		}

		$semaphore_name = 'backup_' . $nonce;
		ROYALBR_Semaphore::ensure_semaphore_exists( $semaphore_name );

		// Use extended timeout (600s/10min) for backup operations to handle large tables/files.
		// Large backups can take 5-10+ minutes per resumption during ZipArchive::close();
		// short timeouts cause stuck_check() to break locks mid-operation, corrupting state.
		// Lock is also refreshed via something_useful_happened() during long operations.
		$this->semaphore = ROYALBR_Semaphore::factory( 600 );
		$this->semaphore->lock_name = $semaphore_name;

		return $this->semaphore->lock();
	}

	/**
	 * Records completed backup in database history system.
	 *
	 * @param bool $backup_database Whether database was backed up.
	 * @param bool $backup_files    Whether files were backed up.
	 * @return void
	 */
	private function save_backup_to_history( $backup_database, $backup_files ) {
		// Store with Unix timestamp for restore operations.
		$timestamp = $this->backup_time;

		// Initialize backup record.
		$backup_set = array(
			'nonce'  => $this->file_nonce,
			'source' => isset( $this->backup_source ) ? $this->backup_source : 'manual',
		);

		// Include database component if present.
		if ( $backup_database ) {
			$db_file = $this->retrieve_task_data( 'backup_db_file' );
			if ( $db_file ) {
				$backup_set['db'] = basename( $db_file );
			}
		}

		// Include file components from backup.
		if ( $backup_files ) {
			$backup_files_array = $this->retrieve_task_data( 'backup_files_array' );
			if ( is_array( $backup_files_array ) ) {
				foreach ( $backup_files_array as $entity => $files ) {
					if ( ! empty( $files ) ) {
						// Support both single and multiple file formats.
						if ( is_array( $files ) ) {
							$backup_set[ $entity ] = basename( $files[0] );
						} else {
							$backup_set[ $entity ] = basename( $files );
						}
					}
				}
			}
		}

		// Store backup record in database.
		ROYALBR_Backup_History::save_backup_set( $timestamp, $backup_set );

		$this->log( 'Saved backup to history - timestamp: ' . $timestamp . ', components: ' . implode( ', ', array_keys( $backup_set ) ) );

		// Queue success message for display after redirect.
		$backup_info = array(
			'timestamp'  => $timestamp,
			'nonce'      => $this->file_nonce,
			'components' => array_keys( $backup_set ),
		);
		set_transient( 'royalbr_backup_complete_' . get_current_user_id(), $backup_info, 60 );
		$this->log( 'Set completion transient for user ' . get_current_user_id() );
	}

	/**
	 * Spawn a non-blocking HTTP request to trigger WP-Cron.
	 *
	 * Required because many hosts (including Local by Flywheel) don't have
	 * real server cron and rely on HTTP requests to trigger scheduled events.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function spawn_cron() {
		$cron_url = site_url( 'wp-cron.php' );

		$args = array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		);

		wp_remote_post( $cron_url, $args );
	}

	/**
	 * Creates unique session identifier for backup tracking.
	 *
	 * @since  1.0.0
	 * @param  string|false $use_nonce Optional nonce to use
	 * @param  int|false    $use_time  Optional timestamp to use
	 * @return string Backup nonce
	 */
	public function backup_time_nonce( $use_nonce = false, $use_time = false ) {
		if ( ! $use_time ) {
			$this->backup_time = ( false === $use_time ) ? time() : $use_time;
		} else {
			$this->backup_time = $use_time;
		}

		if ( ! $use_nonce ) {
			// Create unique session identifier from timestamp and random value.
			$this->file_nonce = substr( md5( $this->backup_time . wp_rand() ), 20 );
		} else {
			$this->file_nonce = $use_nonce;
		}

		return $this->file_nonce;
	}

	/**
	 * Get backupable file entities.
	 *
	 * @since  1.0.0
	 * @param  bool $include_others Whether to include 'others' directory
	 * @param  bool $full_info      Whether to return full info or just paths
	 * @return array Array of entity => path mappings
	 */
	public function get_backupable_file_entities( $include_others = true, $full_info = false ) {
		$wp_upload_dir = wp_upload_dir();

		if ( $full_info ) {
			$arr = array(
				'plugins' => array(
					'path'                => untrailingslashit( WP_PLUGIN_DIR ),
					'description'         => __( 'Plugins', 'royal-backup-reset' ),
					'singular_description' => __( 'Plugin', 'royal-backup-reset' ),
				),
				'themes'  => array(
					'path'                => WP_CONTENT_DIR . '/themes',
					'description'         => __( 'Themes', 'royal-backup-reset' ),
					'singular_description' => __( 'Theme', 'royal-backup-reset' ),
				),
				'uploads' => array(
					'path'        => untrailingslashit( $wp_upload_dir['basedir'] ),
					'description' => __( 'Uploads', 'royal-backup-reset' ),
				),
			);
		} else {
			$arr = array(
				'plugins' => untrailingslashit( WP_PLUGIN_DIR ),
				'themes'  => WP_CONTENT_DIR . '/themes',
				'uploads' => untrailingslashit( $wp_upload_dir['basedir'] ),
			);
		}

		$arr = apply_filters( 'royalbr_backupable_file_entities', $arr, $full_info );

		// Include content directory as catch-all category.
		if ( $include_others ) {
			if ( $full_info ) {
				$arr['others'] = array(
					'path'        => WP_CONTENT_DIR,
					'description' => __( 'Others', 'royal-backup-reset' ),
				);
			} else {
				$arr['others'] = WP_CONTENT_DIR;
			}
		}

		// Entries that should be added after 'others'
		$arr = apply_filters( 'royalbr_backupable_file_entities_final', $arr, $full_info );

		return $arr;
	}

	/**
	 * Retrieves list of uploads directory items to include in backup.
	 *
	 * Scans WordPress uploads directory and returns top-level files and folders.
	 *
	 * @since  1.0.0
	 * @param  bool $log_it Whether to log exclusion settings
	 * @return array Array of full paths to backup
	 */
	public function backup_uploads_dirlist( $log_it = false ) {
		// Initialize empty exclusion list, filtered before use.
		$exclude = '';
		$exclude = apply_filters( 'royalbr_include_uploads_exclude', $exclude );

		if ( $log_it ) {
			$this->log( 'Exclusion option setting (uploads): ' . $exclude );
		}

		$skip = array_flip( preg_split( '/,/', $exclude ) );

		$wp_upload_dir = wp_upload_dir();
		$uploads_dir   = $wp_upload_dir['basedir'];

		// Scan directory and return items not in exclusion list.
		return $this->compile_folder_list_for_backup( $uploads_dir, array(), $skip );
	}

	/**
	 * Retrieves content directory items not covered by other backup entities.
	 *
	 * @since  1.0.0
	 * @param  bool $log_it Whether to log the operation
	 * @return array Array of directory paths to backup
	 */
	public function backup_others_dirlist( $log_it = false ) {
		// Exclude cache, upgrade, other backup plugins' directories and debug.log by default.
		$exclude = 'upgrade,cache,royal-backup-reset,backup*,*backups,wpvivid*,backuply,updraft,mysql.sql,debug.log';
		$exclude = apply_filters( 'royalbr_include_others_exclude', $exclude );

		if ( $log_it ) {
			$this->log( 'Exclusion option setting (others): ' . $exclude );
		}

		$skip = array_flip( preg_split( '/,/', $exclude ) );

		$file_entities = $this->get_backupable_file_entities( false );

		// Build map of directories already handled by other backup entities.
		$avoid_these_dirs = array();
		foreach ( $file_entities as $type => $dirs ) {
			if ( is_string( $dirs ) ) {
				$avoid_these_dirs[ $dirs ] = $type;
			} elseif ( is_array( $dirs ) ) {
				foreach ( $dirs as $dir ) {
					$avoid_these_dirs[ $dir ] = $type;
				}
			}
		}

		return $this->compile_folder_list_for_backup( WP_CONTENT_DIR, $avoid_these_dirs, $skip );
	}

	/**
	 * Scans directory and builds list of items to include in backup.
	 *
	 * avoid_these_dirs = full paths (system directories to never backup)
	 * skip_these_dirs = basenames (user-excluded items)
	 *
	 * @since  1.0.0
	 * @param  string $backup_from_inside_dir Directory to scan
	 * @param  array  $avoid_these_dirs       Full paths to avoid
	 * @param  array  $skip_these_dirs        Basenames to skip
	 * @return array Array of directory paths
	 */
	public function compile_folder_list_for_backup( $backup_from_inside_dir, $avoid_these_dirs, $skip_these_dirs ) {
		$dirlist     = array();
		$added       = 0;
		$log_skipped = 0;

		$this->log( 'Looking for candidates to backup in: ' . $backup_from_inside_dir );
		$royalbr_backup_dir = defined( 'ROYALBR_BACKUP_DIR' ) ? ROYALBR_BACKUP_DIR : ( WP_CONTENT_DIR . '/royal-backup-reset/' );

		if ( is_file( $backup_from_inside_dir ) ) {
			array_push( $dirlist, $backup_from_inside_dir );
			$added++;
			$this->log( "finding files: $backup_from_inside_dir: adding to list ($added)" );
		} elseif ( $handle = opendir( $backup_from_inside_dir ) ) {

			while ( false !== ( $entry = readdir( $handle ) ) ) {

				if ( '.' == $entry || '..' == $entry ) {
					continue;
				}

				// Build full path from directory and entry name.
				$candidate = $backup_from_inside_dir . '/' . $entry;

				if ( isset( $avoid_these_dirs[ $candidate ] ) ) {
					$this->log( "finding files: $entry: skipping: this is the " . $avoid_these_dirs[ $candidate ] . ' directory' );
				} elseif ( $candidate == $royalbr_backup_dir || $candidate == rtrim( $royalbr_backup_dir, '/' ) ) {
					$this->log( "finding files: $entry: skipping: this is the backup directory" );
				} elseif ( isset( $skip_these_dirs[ $entry ] ) ) {
					$this->log( "finding files: $entry: skipping: excluded by options" );
				} else {
					$add_to_list = true;

					// Check wildcard patterns in exclusion list.
					foreach ( $skip_these_dirs as $skip => $sind ) {
						// Pattern: *text* matches if entry contains text.
						if ( '*' == substr( $skip, -1, 1 ) && '*' == substr( $skip, 0, 1 ) && strlen( $skip ) > 2 ) {
							if ( strpos( $entry, substr( $skip, 1, strlen( $skip ) - 2 ) ) !== false ) {
								$this->log( "finding files: $entry: skipping: excluded by options (glob)" );
								$add_to_list = false;
							}
						} elseif ( '*' == substr( $skip, -1, 1 ) && strlen( $skip ) > 1 ) {
							// Pattern: text* matches if entry starts with text.
							if ( substr( $entry, 0, strlen( $skip ) - 1 ) == substr( $skip, 0, strlen( $skip ) - 1 ) ) {
								$this->log( "finding files: $entry: skipping: excluded by options (glob)" );
								$add_to_list = false;
							}
						} elseif ( '*' == substr( $skip, 0, 1 ) && strlen( $skip ) > 1 ) {
							// Pattern: *text matches if entry ends with text.
							if ( strlen( $entry ) >= strlen( $skip ) - 1 && substr( $entry, ( strlen( $skip ) - 1 ) * -1 ) == substr( $skip, 1 ) ) {
								$this->log( "finding files: $entry: skipping: excluded by options (glob)" );
								$add_to_list = false;
							}
						}
					}

					if ( $add_to_list ) {
						array_push( $dirlist, $candidate );
						$added++;
					} else {
						$log_skipped++;
					}
				}
			}
			closedir( $handle );
		} else {
			$this->log( "ERROR: finding files: failed to open directory: $backup_from_inside_dir" );
		}

		$this->log( "finding files: $added files/directories found for backup, $log_skipped excluded" );

		return $dirlist;
	}

	/**
	 * Retrieves single task data value by key.
	 *
	 * Lazy-loads task data from database on first access.
	 *
	 * @since  1.0.0
	 * @param  string $key     Task data key
	 * @param  mixed  $default Default value if not found
	 * @return mixed Task data value
	 */
	public function retrieve_task_data( $key, $default = null ) {
		if ( empty( $this->taskdata ) ) {
			$this->taskdata = empty( $this->file_nonce ) ? array() : get_option( 'royalbr_taskdata_' . $this->file_nonce, array() );
			if ( ! is_array( $this->taskdata ) ) return $default;
		}
		return isset( $this->taskdata[ $key ] ) ? $this->taskdata[ $key ] : $default;
	}

	/**
	 * Stores single task data value by key.
	 *
	 * Persists to database immediately to survive request boundaries.
	 *
	 * @since 1.0.0
	 * @param string $key   Task data key
	 * @param mixed  $value Task data value
	 */
	public function save_task_data( $key, $value ) {
		if ( empty( $this->taskdata ) ) {
			$this->taskdata = empty( $this->file_nonce ) ? array() : get_option( 'royalbr_taskdata_' . $this->file_nonce );
			if ( ! is_array( $this->taskdata ) ) $this->taskdata = array();
		}
		$this->taskdata[ $key ] = $value;
		if ( $this->file_nonce ) update_option( 'royalbr_taskdata_' . $this->file_nonce, $this->taskdata );
	}

	/**
	 * Stores multiple task data values efficiently in a single database write.
	 *
	 * Accepts pairs of key-value arguments: key1, value1, key2, value2, ...
	 * This is more efficient than calling save_task_data() multiple times
	 * as it only writes to database once.
	 *
	 * @since 1.0.0
	 * @param mixed ...$args Key-value pairs to save.
	 * @return void
	 */
	public function save_task_data_multi( ...$args ) {
		if ( empty( $this->taskdata ) ) {
			$this->taskdata = empty( $this->file_nonce ) ? array() : get_option( 'royalbr_taskdata_' . $this->file_nonce );
			if ( ! is_array( $this->taskdata ) ) {
				$this->taskdata = array();
			}
		}

		// Process pairs of key => value from arguments.
		$count = count( $args );
		for ( $i = 0; $i < $count - 1; $i += 2 ) {
			$key   = $args[ $i ];
			$value = $args[ $i + 1 ];
			$this->taskdata[ $key ] = $value;
		}

		// Single database write for all values.
		if ( $this->file_nonce ) {
			update_option( 'royalbr_taskdata_' . $this->file_nonce, $this->taskdata );
		}
	}

	/**
	 * Writes log message with optional filtering and routing.
	 *
	 * Level parameter supports 'level-destination' format for specialized routing.
	 *
	 * @since 1.0.0
	 * @param string         $line    The message to log
	 * @param string         $level   Log level (notice, warning, error) or 'level-destination' format
	 * @param string|boolean $uniq_id Unique identifier for this log message, or false
	 * @return void
	 */
	public function log( $line, $level = 'notice', $uniq_id = false ) {
		// Extract destination from level if specified.
		$destination = 'default';
		if ( preg_match( '/^([a-z]+)-([a-z]+)$/', $level, $matches ) ) {
			$level = $matches[1];
			$destination = $matches[2];
		}

		// Allow filters to intercept or modify log output.
		$line = apply_filters( 'royalbr_logline', $line, $this->file_nonce, $level, $uniq_id, $destination );

		// Filter returned false to indicate it handled output completely.
		if ( false === $line ) {
			return;
		}

		// Output to error log only when debugging is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Conditional debug logging allowed when WP_DEBUG is enabled
			error_log( 'ROYALBR: ' . $line );
		}
	}

	/**
	 * Alias for log() method - used by task scheduler and semaphore classes.
	 *
	 * @since 1.0.0
	 * @param string $line  The message to log.
	 * @param string $level Log level (notice, warning, error).
	 * @return void
	 */
	public function write_to_log( $line, $level = 'notice' ) {
		$this->log( $line, $level );
	}

	/**
	 * Logs translatable strings for i18n tool detection.
	 *
	 * Separate method ensures translation tools recognize strings passed here
	 * without flagging all log() calls as translatable.
	 *
	 * First argument: message to log (required)
	 * Additional arguments: sprintf format parameters
	 *
	 * Logs twice: once normally, once with progress destination.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function log_e() {
		$args = func_get_args();
		// Extract message from arguments.
		$pre_line = array_shift( $args );
		// Write untranslated message to log.
		if ( is_wp_error( $pre_line ) ) {
			// Extract and log error message from WP_Error object.
			$this->log( $pre_line->get_error_message() );
			$this->log( $pre_line->get_error_message(), 'notice-progress' );
		} else {
			// Format message with remaining arguments as sprintf parameters.
			$this->log( vsprintf( $pre_line, $args ) );
			// Log again with progress routing for browser output.
			$this->log( vsprintf( $pre_line, $args ), 'notice-progress' );
		}
	}

	/**
	 * Sends progress update to JavaScript via log stream.
	 *
	 * Encodes progress data as JSON with RINFO: prefix for client parsing.
	 *
	 * @since 1.0.0
	 * @param array $restore_information Progress data array with keys: type, stage, data
	 */
	public function log_restore_update( $restore_information ) {
		// Route through progress logging to trigger stream output filter.
		$this->log( 'RINFO:' . wp_json_encode( $restore_information ), 'notice-progress' );
	}

	/**
	 * Sends output to browser immediately, bypassing server buffering.
	 *
	 * Includes nginx-specific workaround to fill buffer and force output.
	 *
	 * @since 1.0.0
	 * @param string $line The text to output. This may validly include HTML.
	 */
	public function stream_output_to_browser( $line ) {
		echo wp_kses_post( $line );

		// Force immediate output from PHP and web server buffers.
		// Check if output buffer exists before flushing to avoid PHP notices.
		if ( ob_get_level() ) {
			@ob_flush();
		}
		@flush();

		if ( ! isset( $_SERVER['SERVER_SOFTWARE'] ) || false === stripos( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ), 'nginx' ) ) {
			return;
		}
		// Recalculate sanitized output length for buffer tracking.
		$line = wp_kses_post( $line );
		static $strcount = 0;
		static $time = 0;
		$buffer_size = 65536; // Default nginx buffer size is 32KB or 64KB depending on platform.
		if ( 0 == $time ) {
			$time = time();
		}
		$strcount += strlen( $line );
		if ( ( time() - $time ) >= 8 ) {
			// Buffer likely flushed already if output exceeds buffer size.
			if ( $strcount > $buffer_size ) {
				$time = time();
				$strcount = $strcount - $buffer_size;
				return;
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- False positive: str_repeat outputs only space characters with controlled integer values
			echo str_repeat( ' ', (int) ( $buffer_size - $strcount ) );
			// Reset counters after forcing buffer flush.
			$time = time();
			$strcount = 0;
		}
	}

	/**
	 * Processes log messages during backup/restore operations.
	 *
	 * Writes to log file and streams RINFO progress updates to browser.
	 *
	 * @since 1.0.0
	 * @param string         $line        The line to be logged
	 * @param string         $nonce       The task ID of the restore task
	 * @param string         $level       The level of the log notice
	 * @param string|boolean $uniq_id     A unique ID for the log if it should only be logged once; or false otherwise
	 * @param string         $destination The type of task ongoing. If it is not 'progress', then we will skip the logging.
	 * @return string|boolean The filtered value. If set to false, then log() will stop processing the log line.
	 */
	public function royalbr_logline( $line, $nonce, $level, $uniq_id, $destination ) {
		if ( 'progress' != $destination || false === $line ) {
			return $line;
		}

		// Send progress JSON to browser for real-time updates.
		if ( false !== strpos( $line, 'RINFO:' ) ) {
			// Stream progress updates for JavaScript step tracking.
			$this->stream_output_to_browser( $line );
		}

		// Persist all log messages to file via restore handler.
		if ( ! empty( $this->restore_handler ) && method_exists( $this->restore_handler, 'write_to_restore_log' ) ) {
			// Access restore handler through global since method is not directly accessible.
			global $royalbr_restore_instance;
			if ( ! empty( $royalbr_restore_instance ) && method_exists( $royalbr_restore_instance, 'write_to_restore_log' ) ) {
				$royalbr_restore_instance->write_to_restore_log( $line, $level );
			}
		}

		// Signal to log() that output was fully handled by this filter.
		return false;
	}

	/**
	 * Stores multiple task data values in single operation.
	 *
	 * @since 1.0.0
	 * @param array $data Associative array of key => value pairs
	 */
	public function save_task_batch( $data ) {
		foreach ( $data as $key => $value ) {
			$this->taskdata[ $key ] = $value;
		}
	}

	/**
	 * Retrieves complete task data set for specified task.
	 *
	 * Falls back to oneshot task if no task ID provided or available.
	 *
	 * @since  1.0.0
	 * @param  string $task_id Task identifier (nonce). If null, uses current file_nonce or looks up oneshot
	 * @return array All task data
	 */
	public function retrieve_task_array( $task_id = null ) {
		// Use current task ID if none specified.
		if ( $task_id === null ) {
			$task_id = $this->file_nonce;
		}

		// Fall back to oneshot task for immediate single operations.
		if ( empty( $task_id ) ) {
			$task_id = get_option( 'royalbr_oneshotnonce', false );
			if ( false === $task_id ) return array();
		}

		// Load complete task data from database.
		$taskdata = get_option( 'royalbr_taskdata_' . $task_id, array() );
		return is_array( $taskdata ) ? $taskdata : array();
	}

	/**
	 * Returns backup storage directory path.
	 *
	 * @since  1.0.0
	 * @return string Storage directory path
	 */
	public function get_storage_directory() {
		return defined( 'ROYALBR_BACKUP_DIR' ) ? ROYALBR_BACKUP_DIR : ( WP_CONTENT_DIR . '/royal-backup-reset/' );
	}

}
} // End if class_exists.

$GLOBALS['royalbr_instance'] = new RoyalBackupReset();