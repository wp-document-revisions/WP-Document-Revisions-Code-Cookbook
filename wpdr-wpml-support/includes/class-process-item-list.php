<?php
/**
 * Process item list helper functionality.
 *
 * @author  Neil W. James <neil@familyjames.com>
 * @package WPML Support for WP Document Revisions
 */

// No direct access allowed to plugin php file.
if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
	die( esc_html__( 'You are not allowed to call this file directly.', 'process-item-list' ) );
}

/**
 * Process item list helper class.
 */
class Process_Item_List {

	/* Initialisation */

	/**
	 * File version.
	 *
	 * @since 1.0.0
	 *
	 * @var string $version
	 */
	public static $version = '1.0.0';

	/**
	 * List name used by this instance of the class.
	 *
	 * @since 1.0.0
	 *
	 * @var int $list_name;
	 */
	private static $list_name;

	/**
	 * File handle of the locked file.
	 *
	 * @since 1.0.0
	 *
	 * @var int $fp;
	 */
	private static $fp;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @param string $list_name the list name .
	 * @return void
	 */
	public function __construct( $list_name = null ) {
		// check list name is defined.
		if ( empty( $list_name ) ) {
			// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
			wp_die( esc_html__( 'Class Process_Item_List needs to be called with a parameter', 'process-item-list' ) );
		}

		$uploads         = ( defined( 'UPLOADS' ) ? trailingslashit( UPLOADS ) : trailingslashit( WP_CONTENT_DIR ) . 'uploads/' );
		self::$list_name = $uploads . $list_name . '/';

		// make sure the directory exists.
		if ( ! file_exists( self::$list_name ) ) {
			// phpcs:ignore
			mkdir( self::$list_name, 0755 );
		}
	}

	/**
	 * Returns an array of items to be processed.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function extract_items() {
		$items = array();
		$files = scandir( self::$list_name );
		foreach ( $files as $leaf ) {
			if ( '.' === $leaf || '..' === $leaf || ! preg_match( '/^lock(\d+).txt$/', $leaf, $match ) ) {
				continue;
			}
			$file = self::$list_name . $leaf;
			// ignore empty files.
			$fs = filesize( $file );
			if ( 0 === $fs ) {
				continue;
			}

			// phpcs:disable WordPress.WP.AlternativeFunctions
			$fp = fopen( $file, 'r' );
			if ( ! flock( $fp, LOCK_EX | LOCK_NB ) ) {
				continue;
			}
			$rec = json_decode( fread( $fp, $fs ), true );
			fclose( $fp );
			// phpcs:enable WordPress.WP.AlternativeFunctions
			$items[ $rec['time'] ] = array(
				'number' => $match[1],
				'data'   => $rec['data'],
			);
		}
		ksort( $items, SORT_NUMERIC );
		return $items;
	}

	/**
	 * Put an item to be processed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $data Data to be processed.
	 * @return void
	 */
	public function set_item( $data ) {
		// find a free item.
		$i = 0;
		do {
			$file = self::$list_name . 'lock' . $i . '.txt';
			if ( ! file_exists( $file ) ) {
				break;
			} elseif ( 0 === filesize( $file ) ) {
				break;
			}
			++$i;
		} while ( 0 );
		$out = array(
			'time' => time(),
			'data' => $data,
		);
		// phpcs:ignore WordPress.WP
		$out = json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK );
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $file, $out );
	}

	/**
	 * Lock a specific item (assume extract_items run).
	 *
	 * @since 1.0.0
	 *
	 * @param int $number  Lock number to be locked. .
	 * @return bool whether lock was successful return data.
	 */
	public function lock_item( $number ) {
		$file = self::$list_name . 'lock' . $number . '.txt';
		$rec  = filesize( $file );
		if ( $rec ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions
			$fp = fopen( $file, 'c' );
			if ( flock( $fp, LOCK_EX | LOCK_NB ) ) {
				self::$fp = $fp;
				return true;
			}
		}
		return false;
	}

	/**
	 * Unlock an item to be processed.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $number    Lock number to be locked. .
	 * @param bool $processed Data was processed (whether to clear).
	 * @return void
	 */
	public function unlock_item( $number, $processed ) {
		$file = self::$list_name . 'lock' . $number . '.txt';
		if ( $processed ) {
			// remove data as processed.
			ftruncate( self::$fp, 0 );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( self::$fp );
		self::$fp = null;
	}
}
