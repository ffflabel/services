<?php

namespace FFFlabel\Services;

class RestRoute {

	private static array $routes = [];

	/**
	 * Add new Route
	 *
	 * @param $namespace
	 * @param $path
	 * @param $callback
	 * @param $methods
	 *
	 * @return bool|array
	 */
	public static function add($namespace, $path, $callback, $methods = 'GET'): bool|array
	{
		$errors = new \WP_Error();
		if(array_key_exists($path, self::$routes)) {
			$errors->add('409', 'Route already exist');
			return $errors->errors;
		}
		else {
			self::$routes[ $path ] = [
				'namespace' => $namespace,
				'path'      => $path,
				'callback'  => $callback,
				'methods'   => $methods
			];
			return true;
		}
	}

	/**
	 * Get Route by path
	 * @param $path
	 *
	 * @return array
	 */
	public static function get($path): array
	{
		return self::$routes[$path];
	}

	/**
	 * Get all Routes
	 *
	 * @return array
	 */
	public static function getAll(): array
	{
		return self::$routes;
	}

	/**
	 * Register all Routes
	 *
	 * @return void
	 */
	public static function register(): void
	{
		$routes = self::getAll();

		foreach ($routes as $route){
			$args = [
				'methods' => $route['methods'],
				'callback' => $route['callback']
			];
			register_rest_route($route['namespace'], $route['path'], $args);
		}
	}

	/**
	 * Add action to register routes
	 *
	 * @return void
	 */
	public static function addToInit(): void
	{
		add_action('rest_api_init', [self::class, 'registerRoutes']);
	}

}
