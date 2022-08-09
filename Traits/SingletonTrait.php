<?php

namespace DompakDelivery\Service;


trait SingletonTrait {

	/**
	 * @var static singleton instance
	 */
	protected static $instance;

	/**
	 * Get instance
	 *
	 * @return static
	 */
	public static function getInstance()
	{
		return static::$instance ?? (static::$instance = static::initInstance());
	}

	/**
	 * Initialization of class instance
	 *
	 * @return static
	 */
	private static function initInstance()
	{
		return new static();
	}

	/**
	 * Reset instance
	 *
	 * @return void
	 */
	public static function resetInstance(): void
	{
		static::$instance = null;
	}

	/**
	 * Disabled by access level
	 */
	private function __construct()
	{
	}

	/**
	 * Disabled by access level
	 */
	private function __clone()
	{
	}

	/**
	 * Prevent unserializing.
	 */
	final public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'woocommerce' ), '4.6' );
		die();
	}


}