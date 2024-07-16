<?php
/**
 * Create and register post types for the plugin.
 * @author     Timon
 */

namespace FFFlabel\Services;

class PostTypeCreator {
	private static $labels;
	private static $args;
	private static $machineNames;

	public static $entityDirName = 'Entity';

	/**
	 * Added post type args to array for registering
	 *
	 * @param string $machineName - name of PostType
	 * @param string $singularName - singular name for labels
	 * @param string $pluralName - plural name for labels
	 * @param string $custom_base_link - custom slug for rewrite
	 */
	public static function addPostType($machineName, $singularName, $pluralName, $custom_base_link = '') {

		$context = 'Post Type Creator';

		self::$labels[ $machineName ] = [
			'name'                  => $pluralName,
			'menu_name'             => $pluralName,
			'singular_name'         => $singularName,
			'name_admin_bar'        => $singularName,
			'archives'              => sprintf(_x('%s Archives', $context), $singularName),
			'parent_item_colon'     => sprintf(_x('Parent %s:', $context), $singularName),
			'all_items'             => sprintf(_x('All %s', $context), $pluralName),
			'add_new_item'          => sprintf(_x('Add New %s', $context), $singularName),
			'add_new'               => sprintf(_x('Add New %s', $context), $singularName),
			'new_item'              => sprintf(_x('New %s', $context), $singularName),
			'edit_item'             => sprintf(_x('Edit %s', $context), $singularName),
			'update_item'           => sprintf(_x('Update %s', $context), $singularName),
			'view_item'             => sprintf(_x('View %s', $context), $singularName),
			'search_items'          => sprintf(_x('Search %s', $context), $singularName),
			'not_found'             => _x('Not found', $context),
			'not_found_in_trash'    => _x('Not found in Trash', $context),
			'featured_image'        => _x('Featured Image', $context),
			'set_featured_image'    => _x('Set featured image', $context),
			'remove_featured_image' => _x('Remove featured image', $context),
			'use_featured_image'    => _x('Use as featured image', $context),
			'insert_into_item'      => _x('Insert into item', $context),
			'uploaded_to_this_item' => sprintf(_x('Uploaded to this %s', $context), $singularName),
			'items_list'            => sprintf(_x('%s list', $context), $singularName),
			'items_list_navigation' => sprintf(_x('%s list navigation', $context), $singularName),
			'filter_items_list'     => sprintf(_x('Filter %s list', $context), $singularName),
		];

		$args = array(
			'label'               => $singularName,
			'description'         => $singularName,
			'labels'              => self::$labels[ $machineName ],
			'supports'            => ['title'],
			'taxonomies'          => [],
			'hierarchical'        => true,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 5,
			'menu_icon'           => null,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post'
		);


		if ($custom_base_link!=='') {
			$args['rewrite'] = [
				'slug' => $custom_base_link
			];
		}

		self::$args[ $machineName ] = $args;

		self::$machineNames[] = $machineName;
	}


	public static function loadCustomFieldsFromFile($machine_name, $file_name = '') {
		if (empty($file_name)) {
			$file_name = $machine_name;
		}

		$name = implode(DIRECTORY_SEPARATOR, [
			self::$entityDirName,
			ucfirst(strtolower($machine_name)),
			$file_name . '.json'
		]);

		$file = locate_template([$name]);


		if (!empty($file)) {

			$directory = str_replace(DIRECTORY_SEPARATOR . $file_name . '.json', '', $file);

			add_filter('timon/post-type-creator/custom-fields-directory', function($directories) use ($directory) {
				$directories[] = $directory;

				return $directories;
			}, 10, 1);

			$as = file_get_contents($file);

			$json = json_decode(file_get_contents($file), true);

			if (!is_array($json)) {
				$json = [$json];
			}
			foreach ($json as $fieldgroup) {
				if (!empty($fieldgroup)) {
					register_field_group($fieldgroup);
				}
			}
		}
	}

	/**
	 * Change args for some PostType
	 *
	 * @param array $args - args that should be changed
	 * @param string $machineName - name of PostType
	 */
	public static function setArgs(array $args, $machineName) {
		self::$args[ $machineName ] = array_merge(self::$args[ $machineName ], $args);
	}

	/**
	 * Change capability_type of some PostType
	 *
	 * @param string $type - new capability_type
	 * @param string $machineName - name of PostType
	 */
	public static function setType($type, $machineName) {
		$args = [
			'capability_type' => $type
		];
		self::setArgs($args, $machineName);
	}

	/**
	 * Added Taxonomy for PostType
	 *
	 * @param string $taxonomy - name of Taxonomy
	 * @param string $machineName - name of PostType
	 */
	public static function addTaxonomy($taxonomy, $machineName) {
		$curtax     = self::$args[ $machineName ]['taxonomies'];
		$taxonomies = array();
		if (!empty($curtax)) {
			if (is_array($curtax)) {
				$taxonomies = $curtax;
			} else {
				$taxonomies = array($curtax);
			}
		}

		if (!in_array($taxonomy, $taxonomies)) {
			$taxonomies[] = $taxonomy;
		}

		$args = [
			'taxonomies' => $taxonomies
		];
		self::setArgs($args, $machineName);
	}

	/**
	 * Changes labels for PostType
	 *
	 * @param array $labels - new labels
	 * @param string $machineName - name of PostType
	 */
	public static function setLabels(array $labels, $machineName) {
		self::$labels[ $machineName ] = array_merge(self::$labels[ $machineName ], $labels);
		$args                         = [
			'labels' => self::$labels[ $machineName ]
		];
		self::setArgs($args, $machineName);
	}

	/**
	 * Registered all PostTypes
	 */
	public static function registerPostTypes() {
		if (!empty(self::$machineNames)) {
			foreach (self::$machineNames as $machineName) {
				register_post_type($machineName, self::$args[ $machineName ]);
			}
		}
	}

	/**
	 * Added function for registered post types to init action
	 */
	public static function addToInit() {
		add_action('init', [self::class, 'registerPostTypes']);
	}
}
