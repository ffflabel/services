<?php

namespace FFFlabel\Services;

use FFFlabel\Services\Traits\Singleton;

class CronJob {

	use Singleton;

	public static $additional_schedules = [];
	public static $jobs = [];

	/**
	 * General constructor. Theme default options
	 */
	private function __construct()
	{
		add_filter('cron_schedules', array($this,'addCronSchedules'));
		add_action('wp',  array($this, 'cronStarterActivation'));
	}


	public function cronStarterActivation()
	{

		if( !wp_next_scheduled( 'dailyEvent' ) ) {
			wp_schedule_event( strtotime('midnight - 1 day'), 'daily', 'dailyEvent' );
		}

		if( !wp_next_scheduled( 'hourlyEvent' ) ) {
			wp_schedule_event( strtotime(date('Y-m-d H:00:00')), 'hourly', 'hourlyEvent' );
		}

	}


	public function addCronSchedules( $schedules )
	{
		$schedules['hourly'] = array(
			'interval' => 60 * 60, //1 hour = 60 minutes * 60 seconds
			'display' => __( 'Once per Hour', 'tracker' )
		);

		$schedules['daily'] = array(
			'interval' => 24 * 60 * 60, //1 days = 24 hours * 60 minutes * 60 seconds
			'display' => __( 'Once Daily', 'tracker' )
		);

		return $schedules;
	}

	public static function getSchedules() {
		return wp_get_schedules();
	}

	public static function addJob($period='hourly', $callback, $priority=10) {
		if (empty($period) || !in_array($period, array('hourly','daily'))) return false;
		if (!is_callable($callback)) return false;

		add_action( $period . 'Event', $callback, $priority );

		return true;
	}


}
