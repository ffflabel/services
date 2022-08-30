<?php

namespace FFFlabel\Services\Helpers;

class DBHelpers {

	/**
	 * Set up the database tables which the plugin needs.
	 *
	 * @param       string $schema
	 */
	public static function createTables($schema)
	{
		global $wpdb;

		$wpdb->hide_errors();

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		dbDelta($schema);
	}

	/**
	 * Set up the database table.
	 * @param       string $name
	 * @param       string $schema
	 */
	public static function createTable($name, $schema)
	{
		global $wpdb;

		$wpdb->hide_errors();

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		maybe_create_table($name, $schema);
	}

}