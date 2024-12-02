<?php
/**
 * User: Timon
 * Date: 26.10.2017
 * Time: 14:06
 */

namespace FFFlabel\Services;


use FFFlabel\Services\Helpers\TextHelpers;
use FFFlabel\Services\Traits\GetSet;

class PostType {

	use GetSet;

	public $id;
	public $title;
	public $content;
	public $excerpt;
	public $status;
	public $slug;

	public $create_date;

	public static $MACHINE_NAME = 'post';
	public static $TAXONOMIES = [];
	public static $METAFIELDS = [];

	/**
	 * constructor of the class
	 *
	 * @param mixed $matches - Can be empty or id of wp_post or WP_POST object or array with values
	 */
	public function __construct($matches = null)
	{
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
				$wp_post = get_post($matches);
				if (!empty($wp_post) && !is_wp_error($wp_post)) {
					$this->createFromPost($wp_post);
				}
			} elseif ($matches instanceof \WP_Post) {
				$this->createFromPost($matches);
			} elseif (is_array($matches)) {
				$this->updateFromArray($matches);
			}
		}
	}


	/**
	 * Loaded and set the value for $field from DB
	 *
	 * @param $field
	 * @return $this
	 */
	public function loadField($field)
	{

		if (method_exists($this,'load' . TextHelpers::camelcase($field))) {
			$this->{'load' . TextHelpers::camelcase($field)}();
		} else {
			$this->set($field, get_post_meta($this->id, $field, true));
		}
		return $this;

	}

	/**
	 * Save the value for $field to the DB
	 *
	 * @param $field
	 * @param $value
	 * @return $this
	 */
	public function saveField($field, $value = '')
	{
		if (!empty($value)) {
			$this->set($field,$value);
		}

		if (method_exists($this,'save' . TextHelpers::camelcase($field))) {
			$this->{'save' . TextHelpers::camelcase($field)}();
		} else {
			update_post_meta($this->id, $field, $this->{$field});
		}
		return $this;
	}



	/**
	 * loads values of fields from WP_Post
	 *
	 * @param \WP_Post $post  - The wp_post from which the data will be taken
	 * @param bool     $force - if false than data will taken if post_type == $MACHINE_NAME of class only, if true data will taken from any post
	 * @return $this - return object of class
	 */
	public function createFromPost(\WP_Post $post, $force = false)
	{
		if (!$force && $post->post_type != $this::$MACHINE_NAME) {
			return $this;
		}

		$this->id = $post->ID;
		$this->title = $post->post_title;
		$this->content = $post->post_content;
		$this->excerpt = $post->post_excerpt;
		$this->create_date = $post->post_date;
		$this->status = $post->post_status;
		$this->slug = $post->post_name;

		$metafields = $this->getMetaFields();

		if (!empty($metafields)) {
			foreach ($metafields as $field) {
				if (property_exists($this,$field)) {
					$this->loadField($field);
				}

			}
		}

		return $this;
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


	public function loadTaxonomy($taxonomy_field, $taxonomy_name)
	{
		$method = 'load' . TextHelpers::camelcase($taxonomy_field);
		if (method_exists($this,$method)) {
			return $this->{$method}();
		}

		if (!property_exists($this,$taxonomy_field) || !in_array($taxonomy_name, $this->getTaxonomies())) {
			return $this;
		}

		$this->{$taxonomy_field} = [];

		$taxonomy_items = wp_get_post_terms($this->id, $taxonomy_name, array("fields" => "all"));
		if (!empty($taxonomy_items)) {
			foreach ($taxonomy_items as $taxonomy_item) {
				$this->{$taxonomy_field}[$taxonomy_item->term_id] = $taxonomy_item;
			}
		}

		return $this;
	}

	public function saveTaxonomy($taxonomy_field, $taxonomy_name)
	{
		$method = 'save' . TextHelpers::camelcase($taxonomy_field);
		if (method_exists($this,$method)) {
			return $this->{$method}();
		}

		if (!property_exists($this,$taxonomy_field) || !in_array($taxonomy_name, $this->getTaxonomies())) {
			return $this;
		}

		if (!empty($this->{$taxonomy_field})) {
			$_ids = array_map('intval', array_keys($this->{$taxonomy_field})) ;
			wp_set_post_terms($this->id,$_ids,$taxonomy_name, false);
		}

		return $this;
	}


	public function addToTaxonomy($taxonomy_field, $taxonomy_name, $termitem)
	{
		$method = 'addTo' . TextHelpers::camelcase($taxonomy_field);
		if (method_exists($this,$method)) {
			return $this->{$method}($termitem);
		}

		if (!property_exists($this,$taxonomy_field)) {
			return $this;
		}

		if (is_object($termitem) && $termitem instanceof \WP_Term ) {
			$term = $termitem;

		} elseif (is_numeric($termitem)) {
			$term = get_term_by('id',intval($termitem),$taxonomy_name);
		} else {
			$term = get_term_by('name',$termitem,$taxonomy_name);
			if (empty($term) || is_wp_error($term)) {
				$term = get_term_by('slug',$termitem,$taxonomy_name);
			}
		}

		if (empty($term) || is_wp_error($term)) {
			return $this;
		}

		$this->{$taxonomy_field}[$term->term_id] = $term;

		return $this;
	}


	/**
	 *
	 * Insert or Update data of object in the database
	 *
	 * @param array $extra_fields
	 * @return $this
	 */
	public function save($extra_fields = [])
	{
		$args = [
			'post_title'   => $this->title,
			'post_content' => $this->content,
			'post_date'    => $this->create_date,
			'post_status'  => $this->status,
			'post_name'    => $this->slug,
		];

		remove_action('save_post', array(get_class($this), 'saveMetaBox'));

		if (empty($this->id)) {
			$args['post_type'] = $this::$MACHINE_NAME;
			$this->id = wp_insert_post($args);
		} else {
			$args['ID'] = $this->id;
			$this->id = wp_update_post($args);
		}

		add_action('save_post', array(get_class($this), 'saveMetaBox'));

		$metafields = $this->getMetaFields();

		if (!empty($metafields)) {
			foreach ($metafields as $field) {
				if (property_exists($this,$field)) {
					$this->saveField($field);
				}
			}
		}

		if (!empty($extra_fields) && is_array($extra_fields)) {
			foreach ($extra_fields as $field=>$value) {
				update_post_meta($this->id, $field, $value);
			}
		}

		return $this;
	}

	public function getTaxonomies() {
		$taxonomies = [];
		return apply_filters('ffflabel/post/taxonomies',$taxonomies, static::$MACHINE_NAME);
	}

	public function getMetaFields() {
		$metafields = static::$METAFIELDS;
		return apply_filters('ffflabel/post/metafields', $metafields, static::$MACHINE_NAME);
	}

	public function getPermalink() {
		return get_permalink($this->id);
	}

	public function exist() {
		$post_id = $this->id;
		return !empty($post_id);
	}

	public static function create($mixed) {
		return new static($mixed);
	}

	/**
	 * Function for create and settings post_type in the WP
	 *
	 * @param $singularName
	 * @param $pluralName
	 */
	public static function init($singularName, $pluralName)
	{
		$self = new static;

		PostTypeCreator::addPostType(static::$MACHINE_NAME , $singularName, $pluralName);

		PostTypeCreator::setArgs(array(
			'supports'      => ['title','editor'],
			'rewrite'        => array(
				'slug'       => apply_filters(static::$MACHINE_NAME . '/post_type_slug', static::$MACHINE_NAME)
			),
		),static::$MACHINE_NAME);

		PostTypeCreator::setLabels([],static::$MACHINE_NAME);

		$taxonomies = $self->getTaxonomies();
		if (!empty($taxonomies)) {
			foreach ($taxonomies as $taxonomy=>$taxonomy_config) {
				TaxonomyCreator::addTaxonomy($taxonomy, $taxonomy_config['singular'], $taxonomy_config['plural'], static::$MACHINE_NAME);
			}
		}

		add_action('add_meta_boxes', array(get_class($self), 'registerMetaBoxes'));
		add_action('save_post', array(get_class($self), 'saveMetaBox'));
	}

	/**
	 * Function for register the meta boxes
	 */
	public static function registerMetaBoxes() {}


	/**
	 * Function for filter save_post. Save meta_data for post_type
	 * @param $post_id
	 */
	public static function saveMetaBox($post_id)
	{

		// Check if our nonce is set.
		// Verify that the nonce is valid.
//		if (!isset($_POST[static::$MACHINE_NAME . '_meta_box_nonce']) || !wp_verify_nonce($_POST[static::$MACHINE_NAME . '_meta_box_nonce'], static::$MACHINE_NAME . '_meta_box')) {
//			return;
//		}

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

    /**
     * Get featured image
     *
     * @param $post_id
     * @param string $size
     *
     * @return string
     */
    public static function getFeaturedImage( $post_id, $size = 'thumbnail' ) {

        if ( ! has_post_thumbnail( $post_id ) ) {
            return false;
        }

        $thumbId = get_post_thumbnail_id( $post_id );
        $thumb   = wp_get_attachment_image_src( $thumbId, $size );

        return $thumb[0];
    }
}
