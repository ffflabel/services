<?php
/**
 * User: Timon
 * Date: 26.10.2017
 * Time: 14:06
 */

namespace FFFlabel\Services;

use FFFlabel\Services\Helpers\DBHelpers;
use FFFlabel\Services\Traits\GetSet;

class CustomType {

	use GetSet;

	public $id;
	public $title;
	public $status;
	public $label;

	public $create_date;
	public $update_date;

	public static $MACHINE_NAME = 'custom';
	public static $METAFIELDS = [];

	protected $repo;

	/**
	 * constructor of the class
	 *
	 * @param mixed $matches - Can be empty or id or array with values
	 */
	public function __construct($matches = null)
	{
		$this->repo = new CustomRepository(static::$MACHINE_NAME);

		$metafields = $this->getMetaFields();

		if (!empty($metafields)) {
			foreach ($metafields as $field) {
				if (!property_exists($this,$field)) {
					$this->{$field} = false;
				}
			}
		}

		if (!empty($matches)) {

			if (is_numeric($matches)) {
				$row = $this->repo->find($matches);
				if (!empty($row) && !is_wp_error($row)) {
					$this->updateFromArray((array) $row);
				}
			} elseif ($matches instanceof \stdClass) {
				$this->updateFromArray((array) $matches);
			} elseif (is_array($matches)) {
				$this->updateFromArray($matches);
			}
		}
	}

	public function toArray()
	{
		$result = [];

		foreach (array_keys(get_object_vars($this)) as $field) {
			if (property_exists($this,$field)) {
				$property = $this->get($field);

				if (is_array($property)) {
					if (is_object(reset($property))) {
						$result[$field] = array_keys($property);
					} else {
						$result[$field] = array_values($property);
					}
				} else {
					$result[$field] = $property;
				}

			}
		}

		return $result;
	}


	/**
	 *
	 * Insert or Update data of object in the database
	 *
	 * @return $this
	 */
	public function save()
	{
		$args = [
			'title'       => $this->title,
			'create_date' => $this->create_date ?? date('Y-m-d H:i:s'),
			'update_date' => date('Y-m-d H:i:s'),
			'status'      => $this->status
		];

		$metafields = $this->getMetaFields();

		if (!empty($metafields)) {
			foreach ($metafields as $field) {
				if (property_exists($this, $field)) {
					$args[ $field ] = $this->get($field);
				}
			}
		}

		if (empty($this->id)) {
			$this->id = $this->repo->insert($args);
		} else {
			$args['id'] = $this->id;

			$this->repo->update($args, ['id' => $this->id]);
		}

		return $this;
	}


	public function getMetaFields() {
		$metafields = static::$METAFIELDS;
		return apply_filters('fff/post/metafields', $metafields, static::$MACHINE_NAME);
	}

	public static function create($mixed) {
		return new static($mixed);
	}

	/**
	 * Function for create entity
	 *
	 */
	public static function init( $singularName, $pluralName ) {
		global $wpdb;

		$address_book_table_name = static::$MACHINE_NAME;
		$table_name              = $wpdb->base_prefix . $address_book_table_name;
		$query                   = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->base_prefix . $address_book_table_name );

		if ( $wpdb->get_var( $query ) !== $table_name ) {
			$charset_collate         = $wpdb->get_charset_collate();

			$sql                     = "CREATE TABLE `{$wpdb->base_prefix}{$address_book_table_name}` (";
			$sql                    .= "id bigint(50) NOT NULL AUTO_INCREMENT,";
			$sql                    .= "post_id bigint(50),";
			$sql                    .= "status varchar(20),";
			$sql                    .= "title text,";
			$sql                    .= "create_date DATETIME DEFAULT CURRENT_TIMESTAMP,";
			$sql                    .= "update_date DATETIME DEFAULT CURRENT_TIMESTAMP,";

			if ( !empty( static::$METAFIELDS ) ) {
				foreach ( static::$METAFIELDS as $field ) {
					$sql .= $field . " varchar(50),";
				}
			}
			$sql .= " PRIMARY KEY (id) ) $charset_collate;";

			DBHelpers::createTable( $address_book_table_name, $sql );
		}
	}


	/**
	 * Function for filter save_post. Save meta_data for post_type
	 * @param $post_id
	 */
	public static function saveMetaBox($post_id)
	{

		// Check if our nonce is set.
		// Verify that the nonce is valid.
		if (!isset($_POST[static::$MACHINE_NAME . '_meta_box_nonce']) || !wp_verify_nonce($_POST[static::$MACHINE_NAME . '_meta_box_nonce'], static::$MACHINE_NAME . '_meta_box')) {
//			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		// Check the user's permissions.
		if (isset($_POST['post_type']) && 'page' == $_POST['post_type']) {
			if (!current_user_can('edit_page', $post_id)) {
				return;
			}
		} else {
			if (!current_user_can('edit_post', $post_id)) {
				return;
			}
		}


		if (!isset($_POST['post_type']) ||  static::$MACHINE_NAME != $_POST['post_type']) {
			return;
		}

		$self = new static($post_id);

		if (!empty($self->id)) {

			if( ! empty($_POST[ static::$MACHINE_NAME . '_fields']) ) {
				$self->updateFromArray($_POST[ static::$MACHINE_NAME . '_fields']);
			}

			$self->save();
		}

	}

}
