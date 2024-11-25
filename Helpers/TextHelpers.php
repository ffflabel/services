<?php

namespace FFFlabel\Services\Helpers;

class TextHelpers {

	/**
	 * Show debug info
	 *
	 * @param string $var
	 * @param bool $comments
	 */
	public static function show($var = '', $comments = false) {
		print ($comments ? '<!-- <pre>' : '<pre>');
		var_dump($var);
		print ($comments ? '</pre> -->' : '</pre>');
	}

	/**
	 * Show debug info and stop
	 *
	 * @param string $var
	 * @param bool $comments
	 */
	public static function showx($var = '', $comments = false) {
		self::show($var, $comments);
		exit;
	}

	/**
	 * Function camelcase convert text to camelcase word: 'some-teXt_for funcTionName' => 'SomeTextForFunctionname'
	 *
	 * @param string $text
	 *
	 * @return      string
	 */
	public static function camelcase($text) {
		return str_replace(' ', '', ucwords(preg_replace('/[\-\_]/', ' ', strtolower($text))));
	}

	/**
	 * Truncate the text to the sentence closest to a certain number of characters
	 *
	 * @param        $text
	 * @param        $length - number of characters. If < 0 truncate will be made from the end of the text.
	 * @param bool $sentence_closest - if false truncate to the word closest
	 * @param string $tail - added to the end(beginning) after truncate
	 *
	 * @return string
	 */
	public static function truncate($text, $length, $sentence_closest = true, $tail = '...') {
		$text = strip_tags($text);

		if (empty($length)) {
			return '';
		}

		if (strlen($text)<abs($length)) {
			return $text;
		}

		$matches = [];
		$regexp  = empty($sentence_closest) ? '/\s/' : '/\.\s/';

		preg_match_all($regexp, $text, $matches, PREG_OFFSET_CAPTURE);

		if ($length>0) {
			$return_text = self::trancateLeft($matches, $length, $text, $tail);
		} else {
			$return_text = self::trancateRight($matches, $length, $text, $tail);
		}

		return $return_text;
	}

	/**
	 * @param $matches
	 * @param $length
	 * @param $text
	 * @param string $tail
	 *
	 * @return string
	 */
	private static function trancateRight($matches, $length, $text, string $tail = '...') {

		$text_length = strlen($text);
		$right       = abs($length);
		$left        = abs($length);

		if (!empty($matches)) {

			foreach ($matches[0] as $matches_item) {

				if ($text_length-$matches_item[1]>=abs($length)) {
					$left = $matches_item[1];
				} else {
					$right = $matches_item[1];
					break;
				}
			}
		}

		if (abs($text_length+$length-$left)<abs($text_length+$length-$right)) {
			$return_text = $tail.substr($text, $left+1);
		} else {
			$return_text = $tail.substr($text, $right+1);
		}

		return $return_text;
	}

	/**
	 * @param array $matches
	 * @param $length
	 * @param $text
	 * @param string $tail
	 *
	 * @return string
	 */
	private static function trancateLeft( array $matches, $length, $text, string $tail = '...') {
		$left  = 0;
		$right = abs($length);

		if (!empty($matches)) {

			foreach ($matches[0] as $matches_item) {
				if ($matches_item[1]<=$length) {
					$left = $matches_item[1];
				} else {
					$right = $matches_item[1];
					break;
				}
			}

		}

		if (abs($length-$left)<abs($length-$right)) {
			$return_text = substr($text, 0, $left).$tail;
		} else {
			$return_text = substr($text, 0, $right).$tail;
		}

		return $return_text;
	}

}
