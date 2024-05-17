<?php

namespace FFFlabel\Services;


use FFFlabel\Services\Traits\Singleton;

class Gutenberg {

    const BLOCKS_DIR_NAME = '/template-parts/blocks/';

	use Singleton;

	private $_blocks = [];

    private $blocks_paths = '';

	private $_media = [
		'sm' => 500,
		'mds' => 720,
		'md' => 1020,
		'lgs' => 1200,
		'lg' => 1400,
		'lgl' => 1650,
	];

    private $_version = '0.0.1';

	public static function init($version = '0.0.1') {
		return self::instance()->setVersion($version);
	}


	private function __construct()
    {

        $this->_media = apply_filters('fff/gutenberg/media_sizes', $this->_media);

	    $this->blocks_paths = apply_filters('fff/gutenberg/locate/blocks_paths', [
		    'child'  => get_stylesheet_directory() . Gutenberg::BLOCKS_DIR_NAME,
		    'parent' => get_template_directory() . Gutenberg::BLOCKS_DIR_NAME
	    ]);

	    $paths = [];
	    foreach ($this->blocks_paths as $block_path) {
		    if (file_exists($block_path)) {
			    $paths +=  scandir($block_path);
		    }
	    }

	    $theme_blocks = apply_filters(
		    'fff/gutenberg/blocks',
		    array_diff(
			    $paths,
			    apply_filters('fff/gutenberg/blocks_path/excluding_folders', ['.', '..', '.DS_Store'])
		    )
	    );

	    if (!empty($theme_blocks)) {
		    foreach ($theme_blocks as $theme_block) {
			    $this->initBlock($theme_block);
		    }
	    }

	    add_filter('acf/settings/load_json', [$this, 'blocksLoadFromJson']);

	    add_action('acf/init', [$this, 'registerBlocksAction']);
	}

    public function setVersion($version)
    {
        $this->_version = $version;
        return $this;
    }

    private function initBlock($theme_block): void
    {
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
			    'name' => $theme_block,
			    'mode' => 'preview',
			    'keywords' => [$theme_block],
			    'align' => 'full',
			    'supports' => [
				    'align' => [
					    'full', 'center'
				    ]
			    ],
			    'enqueue_style' => Gutenberg::locateFile($theme_block . '/style.css', [], true),
		    ];

		    if (!empty($index_path)) {
			    $default_options['render_template'] = $index_path;
		    } else {
			    $default_options['render_callback'] = [$this, 'renderEmptyBlockWithError'];
		    }

		    $block_options = apply_filters('fff/gutenberg/block_options', array_merge($default_options, $opt), $theme_block);

		    $block_options['enqueue_assets'] = function() use ($block_options) {
                $this->enqueueBlockAssets($block_options);
            };

		    $this->addBlock($block_options);
	    }
    }

	public function addBlock($model)
	{
		$this->_blocks[$this->clearBlockName($model['name'])] = $model;
	}

	public function enqueueBlockAssets($model) : void
	{
		$css_deps = [];
		$js_deps = [];

		if (!empty($model['require_assets']['css'])) {
			foreach ($model['require_assets']['css'] as $name) {
				foreach ($this->_media as $media_name => $size) {

					if (Gutenberg::locateFile('shared_assets/css/' . $name . '_' . $media_name . '.css')) {
                        $css_dep = 'sa_css_' . $name . '_' . $media_name;
						$css_deps[] = $css_dep;
						wp_register_style(
							$css_dep,
							Gutenberg::locateFile('shared_assets/css/' . $name . '_' . $media_name . '.css', [], true),
							[],
							$this->_version,
							$size?'(min-width:'.$size.'px)':'all'
						);
					}
				}
			}
		}

		foreach ($this->_media as $media_name => $size) {
			if (Gutenberg::locateFile($model['name'] . '/style_' . $media_name . '.css')) {
				wp_enqueue_style(
					$model['name'] . '_style_' . $media_name,
					Gutenberg::locateFile($model['name'] . '/style_' . $media_name . '.css', [], true),
					$css_deps,
					$this->_version,
					$size?'(min-width:'.$size.'px)':'all'
				);
			}
		}

		if (!empty($model['require_assets']['js'])) {
			foreach ($model['require_assets']['js'] as $name) {
				if (Gutenberg::locateFile('shared_assets/js/' . $name . '.js')) {
					$js_deps[] = 'sa_js_' . $name;
					wp_register_script(
						'sa_js_' . $name,
						Gutenberg::locateFile('shared_assets/js/' . $name . '.js', [], true),
						[],
						$this->_version,
						true
					);
				}
			}
		}

		if (Gutenberg::locateFile($model['name'] . '/script.js')) {
			wp_enqueue_script(
				$model['name'] . '_script',
				Gutenberg::locateFile($model['name'] . '/script.js', [], true),
				$js_deps,
				$this->_version,
				true
			);
		}

		do_action('fff/gutenberg/block/enqueue_assets', $model['name'], $model);
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
                foreach ($this->blocks_paths as $path) {
                    $folder = $path . $block['name'] . DIRECTORY_SEPARATOR . 'acf-json';
	                if (file_exists($folder)) {
		                $paths[] = $folder;
	                }
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
            return apply_filters('fff/gutenberg/locateFile', '', $file_name, $template_paths, $return_url) ;
        }

        if (empty($template_paths)) {
	        $template_paths = apply_filters('fff/gutenberg/locate/blocks_paths', [
		        'child'  => get_stylesheet_directory() . Gutenberg::BLOCKS_DIR_NAME,
		        'parent' => get_template_directory() . Gutenberg::BLOCKS_DIR_NAME
	        ]);
        }

        foreach ($template_paths as $path) {
            if (file_exists($path . $file_name)) {
	            $url_dir = str_replace(str_replace(DIRECTORY_SEPARATOR, '/', WP_CONTENT_DIR), WP_CONTENT_URL, str_replace(DIRECTORY_SEPARATOR,'/', $path));
	            return apply_filters('fff/gutenberg/locateFile', $return_url ? $url_dir . $file_name : $path . $file_name, $file_name, $template_paths, $return_url) ;
            }
        }

	    return apply_filters('fff/gutenberg/locateFile', '', $file_name, $template_paths, $return_url);
    }

}

