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

    private $_version = '1.0.1';

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

	    // Load each block's styles on render (front end) / into the editor iframe,
	    // which is what `wp_enqueue_block_style()` needs to keep the loading
	    // conditional instead of enqueuing every block's CSS on every page.
	    add_filter('should_load_separate_core_block_assets', '__return_true');

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
			    // ACF block v3 → ACF's iframe-compatible edit UI, and ACF derives the
			    // WordPress block `api_version` (3 on WP 6.3+) from this. Setting the
			    // WP `api_version` directly instead leaves ACF's edit form on its
			    // legacy (v1) path, which cannot render inside the v3 editor iframe —
			    // so the block's edit mode toggle stops working.
			    'acf_block_version' => 3,
			    'mode' => 'preview',
			    'keywords' => [$theme_block],
			    'align' => 'full',
			    'supports' => [
				    'align' => [
					    'full', 'center'
				    ]
			    ],
			    // NB: no `enqueue_style` here — ACF enqueues that option on
			    // `enqueue_block_editor_assets`, which never reaches the block-API-v3
			    // canvas iframe. All of a block's CSS (base + per-breakpoint + shared)
			    // is routed through {@see self::registerBlockStyles()} via
			    // wp_enqueue_block_style(), which loads into the iframe and, on the
			    // front end, on demand when the block renders.
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

	/**
	 * Enqueues a block's scripts (shared JS deps + its own script.js).
	 *
	 * Block styles are handled separately by {@see self::registerBlockStyles()} via
	 * `wp_enqueue_block_style()` — the block API v3 editor renders in an iframe, and
	 * styles added with a plain `wp_enqueue_style()` from a block render land in the
	 * wrong document ("… was added to the iframe incorrectly").
	 */
	public function enqueueBlockAssets($model) : void
	{
		$js_deps = [];

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

	/**
	 * Registers a block's stylesheets (its per-breakpoint `style_<media>.css` plus
	 * any shared `require_assets['css']`) with `wp_enqueue_block_style()`.
	 *
	 * This is the block-API-v3-safe way to attach styles: WordPress loads them into
	 * the editor iframe correctly and, on the front end (with
	 * `should_load_separate_core_block_assets` enabled), only when the block is
	 * actually rendered. Called once per block from {@see self::registerBlocksAction()}.
	 *
	 * Includes the block's base `style.css`: it is deliberately NOT registered via ACF's
	 * `enqueue_style` option, because ACF enqueues that on `enqueue_block_editor_assets`,
	 * which only reaches the outer editor document and never the v3 canvas iframe.
	 */
	public function registerBlockStyles($model): void
	{
		if (!function_exists('wp_enqueue_block_style')) {
			return;
		}

		$block_name = (false !== strpos($model['name'], '/')) ? $model['name'] : 'acf/' . $model['name'];

		// Shared CSS deps, also routed through wp_enqueue_block_style so they reach
		// the iframe; used as dependencies of the block's own stylesheets.
		$css_deps = [];
		if (!empty($model['require_assets']['css'])) {
			foreach ($model['require_assets']['css'] as $name) {
				foreach ($this->_media as $media_name => $size) {
					if (Gutenberg::locateFile('shared_assets/css/' . $name . '_' . $media_name . '.css')) {
						$handle     = 'sa_css_' . $name . '_' . $media_name;
						$css_deps[] = $handle;
						wp_enqueue_block_style($block_name, [
							'handle' => $handle,
							'src'    => Gutenberg::locateFile('shared_assets/css/' . $name . '_' . $media_name . '.css', [], true),
							'ver'    => $this->_version,
							'media'  => $size ? '(min-width:' . $size . 'px)' : 'all',
						]);
					}
				}
			}
		}

		// Base stylesheet (`style.css`). Routed through wp_enqueue_block_style — NOT
		// ACF's `enqueue_style` option — because ACF enqueues that option on
		// `enqueue_block_editor_assets`, which only reaches the outer editor document,
		// never the block-API-v3 canvas iframe. It carries the bulk of the block's
		// styling, so the per-breakpoint files below depend on it to keep cascade order.
		$base_handle = $model['name'] . '_style';
		if (Gutenberg::locateFile($model['name'] . '/style.css')) {
			wp_enqueue_block_style($block_name, [
				'handle' => $base_handle,
				'src'    => Gutenberg::locateFile($model['name'] . '/style.css', [], true),
				'deps'   => $css_deps,
				'ver'    => $this->_version,
				'media'  => 'all',
			]);
		} else {
			$base_handle = '';
		}

		$breakpoint_deps = $base_handle ? array_merge([$base_handle], $css_deps) : $css_deps;

		foreach ($this->_media as $media_name => $size) {
			if (Gutenberg::locateFile($model['name'] . '/style_' . $media_name . '.css')) {
				wp_enqueue_block_style($block_name, [
					'handle' => $model['name'] . '_style_' . $media_name,
					'src'    => Gutenberg::locateFile($model['name'] . '/style_' . $media_name . '.css', [], true),
					'deps'   => $breakpoint_deps,
					'ver'    => $this->_version,
					'media'  => $size ? '(min-width:' . $size . 'px)' : 'all',
				]);
			}
		}
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
				$this->registerBlockStyles($block);
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

