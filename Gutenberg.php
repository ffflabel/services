<?php

namespace FFFlabel\Service;


use FFFlabel\Services\Traits\Singleton;

class Gutenberg {

    const BLOCKS_DIR_NAME = '/template-parts/blocks/';

	use Singleton;

	private $_blocks = [];

    private $blocks_paths = '';

	public static function init() {
		return self::instance();
	}

	private function __construct()
    {

		$this->blocks_paths = apply_filters('fff/gutenberg/blocks_path', get_template_directory() . '/template-parts/blocks/');

		if (file_exists($this->blocks_paths)) {

			$theme_blocks = apply_filters(
                    'fff/gutenberg/blocks',
                    array_diff(
                            scandir($this->blocks_paths),
                            apply_filters('fff/gutenberg/blocks_path/excluding_folders', ['.', '..', '.DS_Store'])
                    )
            );

            foreach ($theme_blocks as $theme_block) {
                $this->initBlock($theme_block);
            }
        }

		add_filter('acf/settings/load_json', [$this, 'blocksLoadFromJson']);

		add_action('acf/init', [$this, 'registerBlocksAction']);
	}

    private function initBlock($theme_block): void
    {
	    $plugin_blocks_path = $this->blocks_paths . $theme_block;

	    $init_path = Gutenberg::locateFile($theme_block . '/init.php');
	    $index_path = Gutenberg::locateFile($theme_block . '/index.php');

	    if (!empty($init_path)) {
		    $opt = include_once $init_path;

		    if (empty($opt) || !is_array($opt)) return;

		    if (isset($opt['example_data'])) {
			    $opt['example'] = [
				    'attributes' => [
					    'mode' => 'preview',
					    'data' => $opt['example_data']
				    ]
			    ];
			    unset($opt['example_data']);
		    }

		    $default_options = [
			    'path' => $plugin_blocks_path,
			    'name' => $theme_block,
			    'mode' => 'preview',
			    'keywords' => [$theme_block],
			    'align' => 'full',
			    'supports' => [
				    'align' => [
					    'full', 'center'
				    ]
			    ],
			    'enqueue_style' => Gutenberg::locateFile($theme_block . '/style.css', [], true) ,
			    'enqueue_script' => Gutenberg::locateFile($theme_block . '/script.js', [], true),
			    'enqueue_assets' => function() use ($theme_block) {
				    do_action('fff/gutenberg/block/enqueue_assets', $theme_block);
			    }
		    ];

		    if (!empty($index_path)) {
			    $default_options['render_template'] = $index_path;
		    } else {
			    $default_options['render_callback'] = [$this, 'renderEmptyBlockWithError'];
		    }

		    $block_options = apply_filters('fff/gutenberg/block_options', array_merge($default_options, $opt), $theme_block);
		    $this->addBlock($block_options);
	    }
    }

	public function addBlock($model)
	{
		$this->_blocks[$this->clearBlockName($model['name'])] = $model;
	}

	public function renderEmptyBlockWithError($block, $content = '', $is_preview = false, $post_id = 0)
    {
		ob_start();
		?>

		<h2><?php print $block['name']; ?></h2>
		<p>Block template does not exist</p>

		<?php

		$html = ob_get_clean();

		return apply_filters('fff/gutenberg/block_empty_html', $html, $block, $is_preview = false, $post_id = 0);
	}

    public function maybeGetBlockName($field_group)
    {
	    $block_name = '';

	    $locations_or = $field_group['location'];
	    if (is_array($locations_or)) {
		    foreach ($locations_or as $locations_and) {
			    if (is_array($locations_and)) {
				    foreach ($locations_and as $location) {
					    if ($location['param'] === 'block' && $location['operator'] === '==') {
						    $block_name = $this->clearBlockName($location['value']);
					    }
				    }
			    }
		    }
	    }

        return $block_name;
    }

    public function blocksLoadFromJson($paths)
    {
        if (!empty($this->_blocks)) {
	        foreach ($this->_blocks as $block) {
		        if (!empty($block['path'])) {
			        $paths[] = $block['path'] . '/acf-json';
                }
	        }
        }
	    return $paths;
    }

    public function clearBlockName($block_name)
    {
	    $name_arr = explode('/', $block_name);
        $name = end($name_arr);
        return str_replace('-', '_', sanitize_title($name));
    }


	public function registerBlocksAction()
    {

		if (function_exists('acf_register_block_type')) {

			foreach ($this->_blocks as $block_name => $block) {
				acf_register_block_type($block);
			}
		}
	}

    public static function locateFile($file_name, $template_paths = [], $return_url=false)
    {
        if (empty($file_name)) {
            return '';
        }

        if (empty($template_paths)) {
	        $template_paths = apply_filters('fff/gutenberg/locate/blocks_paths', [
		        'child'  => get_stylesheet_directory() . Gutenberg::BLOCKS_DIR_NAME,
		        'parent' => get_template_directory() . Gutenberg::BLOCKS_DIR_NAME
	        ]);
        }

        foreach ($template_paths as $path) {
            if (file_exists($path . $file_name)) {
	            $url_dir = str_replace(str_replace(DIRECTORY_SEPARATOR, '/', ABSPATH), home_url('/'), str_replace(DIRECTORY_SEPARATOR,'/', $path));
	            return $return_url ? $url_dir . $file_name : $path . $file_name;
            }
        }

        return '';
    }

}

