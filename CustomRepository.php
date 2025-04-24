<?php

namespace FFFlabel\Services;

use FFFlabel\Services\Traits\GetSet;

class CustomRepository
{
	use GetSet;

	public $table;

	public $count;
	public $page;
	public $status;
	public $order;
	public $orderby;

	public $query_sql;

	protected $clauses = [
		'fields' => [],
		'join' => [],
		'where' => [],
		'groupby' => [],
		'orderby' => [],
		'limit' => [],
	];

	public function __construct($table = 'custom', $args = [])
	{
		global $wpdb;

		$table_name = $table ?: 'custom';

		$this->table = str_starts_with($table_name, $wpdb->prefix) ? $table_name : $wpdb->prefix . $table_name;
		$this->count = -1;
		$this->page = 1;
		$this->status = 'publish';
		$this->order = '';
		$this->orderby = '';


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

		return $args;
	}

	/**
	 * @param $id
	 *
	 * @return array|false|object|\stdClass
	 */
	public function find( $id )
	{
		global $wpdb;

		$query = $wpdb->prepare("SELECT * FROM $this->table WHERE id = %d", $id);
		$row = $wpdb->get_row($query);

		if (!empty($row) && !empty($row->id)) {
			return $row;
		}

		return false;
	}

	/**
	 * @return array|false|object|\stdClass[]
	 */
	public function getAll()
	{
		$args = $this->setOrderVars([
			'post_status' => $this->status,
			'posts_per_page' => -1,
			'paged' => 1,
		]);

		$rows = $this->query($args);

		if (!empty($rows) && !is_wp_error($rows)) {
			return $rows;
		}

		return false;
	}

	/**
	 * Find All post by the field
	 *
	 * @param        $field
	 * @param        $value
	 * @param string $compare
	 * @return array|false|object|\stdClass[]
	 */
	public function findAllBy($field, $value, $compare = '=')
	{
		$args = $this->generateArgs($field, $value, $compare);

		$rows = $this->query($args);

		if (!empty($rows) && !is_wp_error($rows)) {
			return $rows;
		}

		return false;
	}

	/**
	 * @param        $field
	 * @param        $value
	 * @param string $compare
	 * @return \array|false|object|\stdClass[]
	 */
	public function findOneBy($field, $value, $compare = '=')
	{
		$args = $this->generateArgs($field, $value, $compare);
		$args['posts_per_page'] = 1;

		$rows = $this->query($args);

		if (!empty($rows) && !is_wp_error($rows)) {
			foreach ($rows as $row) {
				return $row;
			}
		}

		return false;
	}

	/**
	 * @param array $param
	 * @return \array|false|object|\stdClass[]
	 */
	public function findSmartOne(array $param)
	{
		$args = $this->setOrderVars([
			'post_status' => $this->status,
			'posts_per_page' => 1
		]);

		foreach ($param as $key => $value) {
			$args[$key] = $value;
		}

		$rows = $this->query($args);

		if (!empty($rows) && !is_wp_error($rows)) {
			foreach ($rows as $row) {
				return $row;
			}
		}

		return false;
	}

	/**
	 * @param array $param
	 * @return \array|false|object|\stdClass[]
	 */
	public function findSmartAll(array $param)
	{
		$args = $this->setOrderVars([
			'status' => $this->status,
			'posts_per_page' => $this->count,
			'paged' => $this->page
		]);

		foreach ($param as $key => $value) {
			$args[$key] = $value;
		}

		$rows = $this->query($args);

		if (!empty($rows) && !is_wp_error($rows)) {
			return $rows;
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
			'status' => $this->status,
			'posts_per_page' => $this->count,
			'paged' => $this->page,
		]);

		$args['meta_query'] = [
			[
				'key' => $field,
				'value' => $value,
				'compare' => $compare
			]
		];

		return $args;
	}

	protected function query($args=[])
	{

		global $wpdb;

		$fields = '*';
		$join = '';
		$where = '(1=1)';
		$groupby = '';
		$orderby = '';
		$limits = '';

		if (!empty($args)) {
			$this->updateClauses($args);
		}

		if (!empty($this->clauses['fields'])) {
			$fields = implode(', ', $this->clauses['fields']);
		}

		if (!empty($this->clauses['join'])) {
			$join = implode(' ', $this->clauses['join']);
		}

		if (!empty($this->clauses['where'])) {
			$where = 'WHERE ' . implode(' AND ', $this->clauses['where']);
		}

		if (!empty($this->clauses['groupby'])) {
			$groupby = 'GROUP BY ' . implode(', ', $this->clauses['groupby']);
		}

		if (!empty($this->clauses['orderby'])) {
			$orderby = 'ORDER BY ' . implode(', ', $this->clauses['orderby']);
		}

		if (!empty($this->clauses['limit'])) {
			$limits = 'LIMIT ' . implode(' OFFSET ', $this->clauses['limit']);
		}

//		SELECT $found_rows $distinct $fields FROM {$wpdb->posts} $join WHERE 1=1 $where $groupby $orderby $limits
		$query = "SELECT $fields FROM {$this->table} $join $where $groupby $orderby $limits";

		$this->query_sql = apply_filters('ffflabel/customrepository/query', $query, $args, $this);

		return $wpdb->get_results($this->query_sql);
	}

	public function total($args)
	{
		$args['fields'] = 'COUNT(*) as total';
		$args['posts_per_page'] = -1;
		$args['paged'] = 1;

		if (!array_key_exists('status', $args)) {
			$args['status'] = $this->status;
		}

		$rows = $this->query($args);

		if (!empty($rows) && !is_wp_error($rows)) {
			return $rows[0]->total;
		}

		return 0;
	}

	public function insert($data)
	{
		global $wpdb;

		$wpdb->insert($this->table, $data);

		return $wpdb->insert_id;
	}

	public function update($data, $where)
	{
		global $wpdb;

		$wpdb->update($this->table, $data, $where);

		return $wpdb->rows_affected;
	}


	public function delete($where)
	{
		global $wpdb;

		if (empty($where)) return false;

		$wpdb->delete($this->table, $where);

		return $wpdb->rows_affected;
	}

	protected function updateClauses($args = [])
	{

		$this->clauses['fields'] = $this->clausesFields($args);
		$this->clauses['join'] = $this->clausesJoin($args);
		$this->clauses['where'] = $this->clausesWhere($args);
		$this->clauses['groupby'] = $this->clausesGroupBy($args);
		$this->clauses['orderby'] = $this->clausesOrderBy($args);
		$this->clauses['limit'] = $this->clausesLimit($args);

		return apply_filters('ffflabel/customrepository/clauses', $this->clauses, $args, $this);
	}

	protected function clausesFields($args = [])
	{
		$fields = ['*'];
		if (empty($args['fields']) || $args['fields'] === '*' || $args['fields'] === 'all') {
			$fields = ['*'];
		} elseif (is_string($args['fields'])) {
			$fields = array_map('trim', explode(',', $args['fields']));
		} elseif (is_array($args['fields'])) {
			if (self::arrayIsList($args['fields'])) {
				$fields = $args['fields'];
			} else {
				$fields = array_map(function($field, $alias) {
					return $field . ' AS ' . $alias;
				}, array_keys($args['fields']), array_values($args['fields']));
			}
		}
		return apply_filters('ffflabel/customrepository/clauses/fields', $fields, $args, $this);
	}
	protected function clausesJoin($args)
	{
		return apply_filters('ffflabel/customrepository/clauses/join', [], $args, $this);
	}

	protected function clausesGroupBy($args)
	{
		return apply_filters('ffflabel/customrepository/clauses/groupby', [], $args, $this);
	}


	protected function clausesWhere($args = [])
	{
		global $wpdb;

		$where = ['1=1'];

		if (!empty($args['status']) && $args['status'] !== 'any') {
			if (is_array($args['status'])) {
				$status_or=[];
				foreach ($args['status'] as $status) {
					$status_or[] = $wpdb->prepare("status = '%s'", $status);
				}
				$where[] = '(' . implode(' OR ', $status_or) . ')';
			} else {
				$where[] = $wpdb->prepare("status = '%s'", $args['status']);
			}
		}

		if ( !empty( $args['s'] ) ) {
			$search_fields = apply_filters('ffflabel/customrepository/clauses/where/search_fields', ['title'], $args, $this);
			if (!empty($search_fields)) {
				$search_or = [];
				foreach ($search_fields as $field) {
					$search_or[] = $wpdb->prepare( esc_attr($field) . " LIKE '%s'", '%' . $wpdb->esc_like( sanitize_text_field( $args['s'] ) ) . '%' );
				}
			}

			$where[] = '(' . implode( ' OR ', $search_or ) . ')';
		}

		if (!empty($args['post__in'])) {
			$meta_compare_string = '(' . substr( str_repeat( ',%d', count( $args['post__in'] ) ), 1 ) . ')';
			$meta = $wpdb->prepare( $meta_compare_string, $args['post__in'] );
			$where[] = "id IN {$meta}";
		}

		if (array_key_exists('meta_key', $args) && array_key_exists('meta_value', $args)) {
			if (!array_key_exists('meta_query', $args)) {
				$args['meta_query'] = [
					[
						'key' => $args['meta_key'],
						'value' => $args['meta_value'],
						'compare' => $args['meta_compare'] ?? '='
					]
				];
			} elseif (array_key_exists('relation', $args['meta_query']) && strtoupper($args['meta_query']['relation']) === 'OR') {
				$args['meta_query'] = [
					[
						'key' => $args['meta_key'],
						'value' => $args['meta_value'],
						'compare' => $args['meta_compare'] ?? '='
					],
					$args['meta_query']
				];
			} else {
				$args['meta_query'][] = [
					'key' => $args['meta_key'],
					'value' => $args['meta_value'],
					'compare' => $args['meta_compare'] ?? '='
				];
			}
		}

		if (!empty($args['meta_query'])) {
			$meta_query = new \WP_Meta_Query($args['meta_query']);
			$where[] = $this->metaQuery($meta_query->queries);
		}

		return apply_filters('ffflabel/customrepository/clauses/where', $where, $args, $this);
	}

	protected function clausesOrderBy($args = [])
	{
		$orderby = [];
		$order_by = [];
		if (!empty($args['orderby'])) {
			if (is_string($args['orderby'])) {
				$order_by = array_map('trim', explode(',', $args['orderby']));
			} elseif (is_array($args['orderby'])) {
				$order_by = $args['orderby'];
			}

			$order = [];
			if (!empty($args['order'])) {
				if (is_string($args['order'])) {
					$order = array_map('trim', explode(',', $args['order']));
				} elseif (is_array($args['order'])) {
					$order = $args['order'];
				}
			}

			$orderby = array_map(function($order_by, $order) {
				return $order_by . ' ' . $order;
			}, $order_by, $order);

		}



		return apply_filters('ffflabel/customrepository/clauses/orderby', $orderby, $args, $this);
	}

	protected function clausesLimit($args) {
		$limit = [];
//		posts_per_page

		if (empty($args['posts_per_page'])) {
			$args['posts_per_page'] = 10;
		}

		if ($args['posts_per_page'] != -1) {
			$limit['limit'] = $args['posts_per_page'];

			if (!empty($args['paged'])) {

				if (intval($args['paged']) < 1) {
					$args['paged'] = 1;
				}

				$limit['offset'] = (intval($args['paged'])-1) * intval($args['posts_per_page']) ;
			}
		}

		return apply_filters('ffflabel/customrepository/clauses/limit', $limit, $args, $this);
	}


	protected function metaQuery(array $meta_query)
	{

		$relation = 'AND';
		if (array_key_exists('relation', $meta_query) && in_array(strtoupper($meta_query['relation']), ['AND', 'OR'])) {
			$relation = strtoupper($meta_query['relation']);
			unset($meta_query['relation']);
		}


		if ($relation === 'OR' && count($meta_query) > 1) {
			$where =  '(' . implode(' OR ', array_map([$this, 'metaQueryItem'], $meta_query)) . ')';
		} else {
			$where =  implode(' ' . $relation . ' ', array_map([$this, 'metaQueryItem'], $meta_query));
		}

		return $where;
	}

	protected function metaQueryItem($meta): array|string
	{
		global $wpdb;

		if (!is_array($meta)) {
			return '';
		}

		if (array_key_exists('relation', $meta)) {
			return $this->metaQuery($meta);
		}

		if (empty($meta['key'])) {
			return '';
		}

		$meta = wp_parse_args($meta, [
			'key' => '',
			'value' => '',
			'compare' => '='
		]);

		if ( isset( $meta['compare'] ) ) {
			$meta['compare'] = strtoupper( $meta['compare'] );
		} else {
			$meta['compare'] = isset( $meta['value'] ) && is_array( $meta['value'] ) ? 'IN' : '=';
		}

		$non_numeric_operators = array(
			'=',
			'!=',
			'LIKE',
			'NOT LIKE',
			'IN',
			'NOT IN',
			'EXISTS',
			'NOT EXISTS',
			'RLIKE',
			'REGEXP',
			'NOT REGEXP',
		);

		$numeric_operators = array(
			'>',
			'>=',
			'<',
			'<=',
			'BETWEEN',
			'NOT BETWEEN',
		);

		if ( ! in_array( $meta['compare'], $non_numeric_operators, true ) && ! in_array( $meta['compare'], $numeric_operators, true ) ) {
			$meta['compare'] = '=';
		}

		if ( !array_key_exists( 'value', $meta ) ) {
			$meta['value'] = null;
		}

		$compare     = $meta['compare'];
		$key		 = $meta['key'];
		$value		 = $meta['value'];

		if ( in_array( $compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ), true ) ) {
			if ( ! is_array( $value ) ) {
				$value = preg_split( '/[,\s]+/', $value );
			}
		} elseif ( is_string( $value ) ) {
			$value = trim( $value );
		}

		switch ( $compare ) {
			case 'IN':
			case 'NOT IN':
				$meta_compare_string = '(' . substr( str_repeat( ',%s', count( $value ) ), 1 ) . ')';
				$where               = $wpdb->prepare( $meta_compare_string, $value );
				break;

			case 'BETWEEN':
			case 'NOT BETWEEN':
				$where = $wpdb->prepare( '%s AND %s', $value[0], $value[1] );
				break;

			case 'LIKE':
			case 'NOT LIKE':
				$meta_value = '%' . $wpdb->esc_like( $value ) . '%';
				$where      = $wpdb->prepare( '%s', $meta_value );
				break;

			// EXISTS with a value is interpreted as '='.
			case 'EXISTS':
				$compare      = '=';
				$where        = $wpdb->prepare( '%s', $value );
				break;

			case 'NOT EXISTS':
				$compare = 'IS';
				$where = 'NULL';
				break;

			default:
				$where = $wpdb->prepare( '%s', $value );
				break;
		}

		return "({$key} {$compare} {$where})";
	}

	public static function arrayIsList(array $array)
	{
		if (empty($array)) {
			return true;
		}
		return array_keys($array) === range(0, count($array) - 1);
	}
}

