<?php

namespace FFFlabel\Services\Helpers;

class DateHelpers {

	/**
	 * Format date
	 *
	 * @param        $date
	 * @param string $format
	 *
	 * @return string
	 */

	public static function formatDate($date, $format = 'F j, Y')
	{
		$date = new \DateTime($date);

		return $date->format($format);
	}

	/**
	 * Parse string like 1w 2d 10h 10m 30s and return the number of seconds
	 * @param string    $time
	 * @param array     $time_settings
	 * @return bool|int
	 */
	public static function parseTimeText($time='', $time_settings = [])
	{
		$default_settings = [
			'w' => 7*24*60*60, // week (7d*24h*60m*60s)
			'd' => 24*60*60, // day (24h*60m*60s)
			'h' => 60*60, // hour (24h*60m*60s)
			'm' => 60, // minute (60s)
			's' => 1, // second
		];

		$settings = array_merge($default_settings, $time_settings);

		$stringtime = trim(strtolower($time));

		if (preg_match('/^(?:\d+[wdhms](?: +|$))+$/', $stringtime )) {
			$arr_time = explode(' ', $stringtime);
			$time = 0;
			foreach ($arr_time as $time_fragment) {
				$time_code = substr($time_fragment,-1);
				$time_value = intval($time_fragment);

				if (array_key_exists($time_code,$settings)) {
					$time += $time_value * $settings[$time_code];
				}
			}

			return $time;
		}
		return false;
	}

}