<?php

namespace FFFlabel\Services;

use FFFlabel\Services\Traits\GetSet;

class PostRepository
{

	use GetSet;

	static $QUERY_DETAILS;

	public $posttype;

	public $count;
	public $page;
	public $status;

	public function __construct($posttype = 'post', $args = [])
	{
		$this->posttype = $posttype ?: 'post';
		$this->count = -1;
		$this->page = 1;
		$this->status = 'publish';
		$this->order = '';
		$this->orderby = '';
		$this->meta_key = '';

		if (!empty($args)) {
			$this->updateFromArray($args);
		}

	}

	protected function setOrderVars($args) {
		if (!empty($this->order)) {
			$args['order'] = $this->order;
		}
		if (!empty($this->orderby)) {
			$args['orderby'] = $this->orderby;
		}
		if (!empty($this->meta_key)) {
			$args['meta_key'] = $this->meta_key;
		}
		return $args;
	}

	/**
	 * @param $id
	 *
	 * @return \WP_Post|bool
	 */
	public function find( $id )
	{
		$self = get_post($id);

		if (!empty($self) && !empty($self->ID) && $self->post_type == $this->posttype ) {
			return $self;
		}

		return false;
	}

	/**
	 * @return \WP_Query|bool
	 */
	public function getAll()
	{
		$args = $this->setOrderVars([
			'post_type' => $this->posttype,
			'post_status' => $this->status,
			'posts_per_page' => $this->count,
			'paged' => $this->page,
		]);

		$postsQuery = new \WP_Query($args);

		self::$QUERY_DETAILS = $postsQuery;

		if (!empty($postsQuery) && !is_wp_error($postsQuery)) {
			return $postsQuery;
		}

		return false;
	}

	/**
	 * Find All post by the field
	 *
	 * @param        $field
	 * @param        $value
	 * @param string $compare
	 * @return \WP_Query|bool
	 */
	public function findAllBy($field, $value, $compare = '=')
	{
		$args = $this->generateArgs($field, $value, $compare);

		$postsQuery = new \WP_Query($args);
		self::$QUERY_DETAILS = $postsQuery;

		if (!empty($postsQuery) && !is_wp_error($postsQuery)) {
			return $postsQuery;
		}
		return false;
	}

	/**
	 * @param        $field
	 * @param        $value
	 * @param string $compare
	 * @return \WP_Post|int|bool
	 */
	public function findOneBy($field, $value, $compare = '=')
	{
		$post = false;

		$args = $this->generateArgs($field, $value, $compare);
		$args['posts_per_page'] = 1;

		$postsQuery = new \WP_Query($args);
		self::$QUERY_DETAILS = $postsQuery;

		if (!empty($postsQuery) && !is_wp_error($postsQuery)) {
			foreach ($postsQuery->posts as $postitem) {
				$post = $postitem;
				break;
			}
		}
		return $post;
	}

	/**
	 * @param array $param
	 * @return \WP_Post|int|bool
	 */
	public function findSmartOne(array $param)
	{
		$post = false;

		$args = $this->setOrderVars([
			'post_status' => $this->status,
			'posts_per_page' => 1,
			'post_type'   => $this->posttype
		]);

		foreach ($param as $key => $value) {
			$args[$key] = $value;
		}

		$postsQuery = new \WP_Query( $args );
		self::$QUERY_DETAILS = $postsQuery;

		if ($findposts = $postsQuery->get_posts()) {
			foreach  ( $findposts as $postitem ) {
				$post = $postitem;
				break;
			}
		}
		return $post;
	}

	/**
	 * @param array $param
	 * @return bool|\WP_Query
	 */
	public function findSmartAll(array $param)
	{

		$args = $this->setOrderVars([
			'post_status' => 'publish',
			'posts_per_page' => $this->count,
			'paged' => $this->page,
			'post_type'   => $this->posttype
		]);

		foreach ($param as $key => $value) {
			$args[$key] = $value;
		}

		$postsQuery = new \WP_Query( $args );
		self::$QUERY_DETAILS = $postsQuery;

		if (!empty($postsQuery) && !is_wp_error($postsQuery)) {
			return $postsQuery;
		}

		return false;
	}

	/**
	 * @param        $field
	 * @param        $value
	 * @param string $compare
	 * @return array
	 */
	public function generateArgs($field, $value, $compare = '=') {

		$args = $this->setOrderVars([
			'post_type' => $this->posttype,
			'post_status' => $this->status,
			'posts_per_page' => $this->count,
			'paged' => $this->page,
		]);

		if (strtolower($field) === 'id') {
			$args['p'] = intval($value);
		} elseif (strtolower($field) == 'title') {
			global $wpdb;
			$postids = $wpdb->get_col($wpdb->prepare("select ID from $wpdb->posts where post_title LIKE '%s' ", "%$value%") );
			$args['post__in'] = empty($postids) ? [-1] : $postids;
		} elseif (strtolower($field) == 'status') {
			$args['post_status'] = $value;
		} else {
			$args['meta_query'] = [
				[
					'key' => $field,
					'value' => $value,
					'compare' => $compare
				]
			];
		}

		return $args;
	}
}

