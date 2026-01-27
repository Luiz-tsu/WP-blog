<?php
/**
 * Manages backup archive records and metadata storage.
 *
 * @package Royal_Backup_Reset
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access allowed' );
}

/**
 * Handles tracking and retrieval of backup sets.
 *
 * Storage Architecture v2:
 * - Primary keys use backup nonces instead of timestamps
 * - Comprehensive metadata for each backup operation
 * - Dual indexing system enables rapid queries
 *
 */
class ROYALBR_Backup_History {

	/**
	 * Retrieves the complete backup history structure from storage.
	 *
	 * @since  1.0.0
	 * @return array History array containing backup sets, lookup indexes, and system metadata.
	 */
	private static function get_history_data() {
		$history = get_option( 'royalbr_backup_history', array() );

		// Initialize default structure when data is missing or malformed
		if ( empty( $history ) || ! isset( $history['backups'] ) ) {
			$history = array(
				'backups' => array(),
				'index'   => array(
					'by_timestamp' => array(),
					'by_status'    => array(
						'complete'    => array(),
						'partial'     => array(),
						'in_progress' => array(),
					),
				),
				'meta'    => array(
					'version'      => 2,
					'last_updated' => time(),
				),
			);
		}

		return $history;
	}

	/**
	 * Fetches either a single backup by timestamp or all backups sorted by recency.
	 *
	 * @since  1.0.0
	 * @param  int|bool $timestamp Specific timestamp to retrieve one backup, or false to return all backups sorted newest first.
	 * @return array Single backup array when timestamp provided, otherwise all backups ordered by date.
	 */
	public static function get_history( $timestamp = false ) {
		$history = self::get_history_data();

		if ( $timestamp ) {
			// Resolve timestamp to nonce via index lookup
			if ( isset( $history['index']['by_timestamp'][ $timestamp ] ) ) {
				$nonce = $history['index']['by_timestamp'][ $timestamp ];
				if ( isset( $history['backups'][ $nonce ] ) ) {
					$backup         = $history['backups'][ $nonce ];
					$backup['nonce'] = $nonce; // Include nonce for API compatibility
					return $backup;
				}
			}
			return array();
		}

		// Retrieve all backups ordered by creation time (newest entries first)
		$backups = $history['backups'];
		uasort(
			$backups,
			function ( $a, $b ) {
				return $b['timestamp'] - $a['timestamp'];
			}
		);

		return $backups;
	}

	/**
	 * Retrieves a specific backup using its unique nonce identifier.
	 *
	 * @since  1.0.0
	 * @param  string $nonce Unique backup identifier to locate the backup set.
	 * @return array|bool Backup data array if found, false otherwise.
	 */
	public static function get_backup_set_by_nonce( $nonce ) {
		if ( empty( $nonce ) ) {
			return false;
		}

		$history = self::get_history_data();

		if ( isset( $history['backups'][ $nonce ] ) ) {
			$backup         = $history['backups'][ $nonce ];
			$backup['nonce'] = $nonce; // Append nonce to returned data
			return $backup;
		}

		return false;
	}

	/**
	 * Persists a backup set record to the history database.
	 *
	 * @since  1.0.0
	 * @param  int   $timestamp    Unix timestamp when backup was created.
	 * @param  array $backup_array Backup details containing nonce and component filenames (db, plugins, themes, uploads, others).
	 * @return bool True if saved successfully, false on error.
	 */
	public static function save_backup_set( $timestamp, $backup_array ) {
		$history = self::get_history_data();

		// Pull nonce identifier from provided backup data
		$nonce = isset( $backup_array['nonce'] ) ? $backup_array['nonce'] : '';

		if ( empty( $nonce ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'ROYALBR: Cannot save backup set without nonce' );
			}
			return false;
		}

		// Construct component metadata from backup files
		$components = array();
		$total_size = 0;
		foreach ( array( 'db', 'plugins', 'themes', 'uploads', 'others' ) as $type ) {
			if ( ! empty( $backup_array[ $type ] ) ) {
				// Handle both array of files (chunked backups) and single file string
				$files = is_array( $backup_array[ $type ] )
					? $backup_array[ $type ]
					: array( $backup_array[ $type ] );

				// Calculate total size across all chunks
				$component_size = 0;
				foreach ( $files as $file ) {
					$file_path = trailingslashit( ROYALBR_BACKUP_DIR ) . $file;
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize, WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- Required for backup file validation and size calculation
					if ( file_exists( $file_path ) ) {
						$component_size += filesize( $file_path );
					}
				}

				$components[ $type ] = array(
					'file'   => $files,
					'size'   => $component_size,
					'status' => 'complete',
				);
				$total_size += $component_size;
			}
		}

		// Assemble complete backup record
		$history['backups'][ $nonce ] = array(
			'timestamp'      => (int) $timestamp,
			'created'        => gmdate( 'Y-m-d H:i:s', $timestamp ),
			'site_url'       => get_site_url(),
			'wp_version'     => get_bloginfo( 'version' ),
			'plugin_version' => defined( 'ROYALBR_VERSION' ) ? ROYALBR_VERSION : '1.0.0',
			'components'     => $components,
			'status'         => empty( $components ) ? 'partial' : 'complete',
			'total_size'     => $total_size,
			'notes'          => '',
			'source'         => isset( $backup_array['source'] ) ? $backup_array['source'] : 'manual',
		);

		// Register timestamp-to-nonce mapping
		$history['index']['by_timestamp'][ $timestamp ] = $nonce;

		// Add to appropriate status category
		$status = empty( $components ) ? 'partial' : 'complete';
		if ( ! in_array( $nonce, $history['index']['by_status'][ $status ], true ) ) {
			$history['index']['by_status'][ $status ][] = $nonce;
		}

		// Refresh metadata timestamp
		$history['meta']['last_updated'] = time();

		$result = update_option( 'royalbr_backup_history', $history, false );

		// Write diagnostic logs in debug mode only
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( $result ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'ROYALBR: Saved backup set: timestamp=' . $timestamp . ', nonce=' . $nonce . ', components=' . implode( ',', array_keys( $components ) ) );
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'ROYALBR: Failed to save backup set: timestamp=' . $timestamp );
			}
		}

		return $result;
	}

	/**
	 * Removes a backup record from the history storage.
	 *
	 * @since  1.0.0
	 * @param  int $timestamp Timestamp identifying which backup to remove.
	 * @return bool True if deletion succeeded, false if not found or failed.
	 */
	public static function delete_backup_set( $timestamp ) {
		$history = self::get_history_data();

		// Translate timestamp to its nonce key
		if ( ! isset( $history['index']['by_timestamp'][ $timestamp ] ) ) {
			return false;
		}

		$nonce = $history['index']['by_timestamp'][ $timestamp ];

		// Clear backup record from main storage
		unset( $history['backups'][ $nonce ] );

		// Clear timestamp index entry
		unset( $history['index']['by_timestamp'][ $timestamp ] );

		// Purge from all status index lists
		foreach ( $history['index']['by_status'] as $status => &$nonces ) {
			$key = array_search( $nonce, $nonces, true );
			if ( false !== $key ) {
				unset( $nonces[ $key ] );
				$nonces = array_values( $nonces ); // Resequence array indices
			}
		}

		// Record modification time
		$history['meta']['last_updated'] = time();

		return update_option( 'royalbr_backup_history', $history, false );
	}

	/**
	 * Reconstructs history by discovering backup files in storage directory.
	 *
	 * Scans filesystem for backup archives and regenerates complete history structure.
	 *
	 * @since  1.0.0
	 * @return array Reconstructed backup history indexed by nonce.
	 */
	public static function rebuild() {
		// Output debug information when debugging is active
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'ROYALBR: Rebuilding backup history from directory scan' );
		}

		$backup_dir = trailingslashit( ROYALBR_BACKUP_DIR );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_dir -- Required for backup directory validation
		if ( ! is_dir( $backup_dir ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'ROYALBR: Backup directory does not exist: ' . $backup_dir );
			}
			return array();
		}

		$files       = scandir( $backup_dir );
		$backup_sets = array();

		// Build mapping of nonces to timestamps from existing history (preserves second precision).
		$existing_history      = get_option( 'royalbr_backup_history', array() );
		$backup_times_by_nonce = array();
		if ( ! empty( $existing_history['backups'] ) ) {
			foreach ( $existing_history['backups'] as $nonce => $backup_data ) {
				if ( isset( $backup_data['timestamp'] ) ) {
					$backup_times_by_nonce[ $nonce ] = $backup_data['timestamp'];
				}
			}
		}

		// Organize discovered files into sets keyed by nonce
		foreach ( $files as $file ) {
			$ext = pathinfo( $file, PATHINFO_EXTENSION );
			if ( 'gz' !== $ext && 'zip' !== $ext ) {
				continue;
			}

			// Extract backup metadata from filename pattern: backup_YYYY-MM-DD-HHMM_sitename_nonce-component[N].ext
			// The optional N captures chunk number (e.g., plugins2.zip, plugins3.zip)
			if ( preg_match( '/^backup_(\d{4})-(\d{2})-(\d{2})-(\d{2})(\d{2})_.*_([a-f0-9]{12})-(db|plugins|themes|uploads|others)(\d+)?\./', $file, $matches ) ) {
				$year        = $matches[1];
				$month       = $matches[2];
				$day         = $matches[3];
				$hour        = $matches[4];
				$minute      = $matches[5];
				$nonce       = $matches[6];
				$component   = $matches[7];
				$chunk_index = isset( $matches[8] ) && '' !== $matches[8] ? (int) $matches[8] : 1;

				// Use existing timestamp if nonce is known (preserves seconds), otherwise parse from filename.
				if ( isset( $backup_times_by_nonce[ $nonce ] ) ) {
					$timestamp = $backup_times_by_nonce[ $nonce ];
				} else {
					$base_timestamp = gmmktime( (int) $hour, (int) $minute, 0, (int) $month, (int) $day, (int) $year );
					$timestamp      = $base_timestamp;
					// Ensure unique timestamp when no existing record (collision-safe fallback).
					while ( isset( $backup_times_by_nonce[ '_used_' . $timestamp ] ) ) {
						$timestamp++;
					}
					$backup_times_by_nonce[ '_used_' . $timestamp ] = true;
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- Required for backup file size calculation
				$file_size = @filesize( $backup_dir . $file );

				if ( ! isset( $backup_sets[ $nonce ] ) ) {
					$backup_sets[ $nonce ] = array(
						'timestamp'      => $timestamp,
						'created'        => gmdate( 'Y-m-d H:i:s', $timestamp ),
						'site_url'       => get_site_url(),
						'wp_version'     => get_bloginfo( 'version' ),
						'plugin_version' => defined( 'ROYALBR_VERSION' ) ? ROYALBR_VERSION : '1.0.0',
						'components'     => array(),
						'status'         => 'complete',
						'total_size'     => 0,
						'notes'          => '',
					);
				}

				// Initialize component if not exists - store files as array to support chunks
				if ( ! isset( $backup_sets[ $nonce ]['components'][ $component ] ) ) {
					$backup_sets[ $nonce ]['components'][ $component ] = array(
						'file'   => array(),
						'size'   => 0,
						'status' => 'complete',
					);
				}

				// Add this chunk file to the component's file array (indexed by chunk number for sorting)
				$backup_sets[ $nonce ]['components'][ $component ]['file'][ $chunk_index ] = $file;
				$backup_sets[ $nonce ]['components'][ $component ]['size']                += $file_size ? $file_size : 0;
				$backup_sets[ $nonce ]['total_size']                                      += $file_size ? $file_size : 0;
			}
		}

		// Sort chunk files by index and convert to sequential array
		foreach ( $backup_sets as $nonce => &$backup ) {
			foreach ( $backup['components'] as $component => &$component_data ) {
				if ( is_array( $component_data['file'] ) ) {
					ksort( $component_data['file'] );
					$component_data['file'] = array_values( $component_data['file'] );
				}
			}
		}
		unset( $backup, $component_data );

		// Create fresh history structure with index mappings
		$history = array(
			'backups' => $backup_sets,
			'index'   => array(
				'by_timestamp' => array(),
				'by_status'    => array(
					'complete'    => array(),
					'partial'     => array(),
					'in_progress' => array(),
				),
			),
			'meta'    => array(
				'version'      => 2,
				'last_updated' => time(),
			),
		);

		// Populate lookup indexes for fast queries
		foreach ( $backup_sets as $nonce => $backup ) {
			$history['index']['by_timestamp'][ $backup['timestamp'] ] = $nonce;
			$history['index']['by_status']['complete'][]              = $nonce;
		}

		if ( ! empty( $backup_sets ) ) {
			update_option( 'royalbr_backup_history', $history, false );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'ROYALBR: Rebuilt history with ' . count( $backup_sets ) . ' backup sets' );
			}
		}

		return $backup_sets;
	}

	/**
	 * Retrieves the timestamp of the newest backup in history.
	 *
	 * @since  1.0.0
	 * @return int|bool Unix timestamp of latest backup, or false when history is empty.
	 */
	public static function get_latest_backup_timestamp() {
		$history = self::get_history_data();

		if ( empty( $history['index']['by_timestamp'] ) ) {
			return false;
		}

		// Find highest timestamp value in index
		$timestamps = array_keys( $history['index']['by_timestamp'] );
		rsort( $timestamps );

		return $timestamps[0];
	}

	/**
	 * Discovers all chunk files for a backup component from the filesystem.
	 *
	 * This is a fallback method when the history only contains a single file
	 * but the backup was actually split into multiple chunks.
	 *
	 * @since  1.0.0
	 * @param  string $nonce     Backup nonce identifier.
	 * @param  string $component Component type (db, plugins, themes, uploads, others).
	 * @return array Array of chunk filenames in correct order.
	 */
	public static function discover_chunks( $nonce, $component ) {
		$backup_dir = trailingslashit( ROYALBR_BACKUP_DIR );
		$chunks     = array();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_dir -- Required for backup directory validation
		if ( ! is_dir( $backup_dir ) ) {
			return $chunks;
		}

		$files = scandir( $backup_dir );
		foreach ( $files as $file ) {
			$ext = pathinfo( $file, PATHINFO_EXTENSION );
			if ( 'gz' !== $ext && 'zip' !== $ext ) {
				continue;
			}

			// Match pattern: backup_*_nonce-component[N].ext
			// First chunk has no number, subsequent chunks have 2, 3, etc.
			$pattern = '/^backup_.*_' . preg_quote( $nonce, '/' ) . '-' . preg_quote( $component, '/' ) . '(\d+)?\.(gz|zip)$/';
			if ( preg_match( $pattern, $file, $matches ) ) {
				// First chunk (no number) = index 1, chunk2 = index 2, etc.
				$index            = isset( $matches[1] ) && '' !== $matches[1] ? (int) $matches[1] : 1;
				$chunks[ $index ] = $file;
			}
		}

		// Sort by chunk index and return as sequential array
		ksort( $chunks );
		return array_values( $chunks );
	}
}
