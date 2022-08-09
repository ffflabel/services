<?php

namespace FFFlabel\Services\Traits;


trait Singleton {

	/**
	 * @var static singleton instance
	 */
	protected static $instance;

	/**
	 * Get instance
	 *
	 * @return static
	 */
	public static function instance()
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
	private function __clone()
	{
		throw new \InvalidArgumentException( 'Clone instances of this class is forbidden.' );
	}

	/**
	 * Prevent unserializing.
	 */
	final public function __wakeup() {
		throw new \InvalidArgumentException( 'Unserializing instances of this class is forbidden.' );
	}

}