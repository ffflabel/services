<?php

namespace FFFlabel\Services;

class Settings {

    protected $optionKey;

    protected $isACF;
    protected $need_stored;

    protected $settings = [
        'default' => [],
        'stored'  => [],
        'final'   => []
    ];

    /**
     * Settings constructor.
     *
     * @param $optionKey             string
     * @param $defaultSettings       array
     * @param $use_acf               bool
     * @param $without_stored        bool
     */
    public function __construct($option_key, $default_settings, bool $use_acf = false, bool $without_stored = false)
    {
        $this->optionKey = $option_key;
		$this->isACF = $use_acf && class_exists('ACF');
		$this->need_stored = !$without_stored;

        $this->settings['default'] = $default_settings;

        $this->settings['stored'] = array_merge($this->settings['default'], $this->getStoreSettings());

        $this->settings['final'] = apply_filters('fff/settings/finalize/' . $option_key, $this->settings['stored']);
    }

    public function get($key, $storage = 'final')
    {
        if (!isset($this->settings[$storage][$key])) {
            return false;
        }

        return $this->settings[$storage][$key];
    }

    public function set($key, $value): void
    {
        $this->settings['stored'][$key] = $value;
        $this->storeSettings();
    }

    public function getAll($storage = 'final')
    {
        return $this->settings[$storage];
    }

    /**
     * @param array $postedData
     *
     * @return bool
     */
    public function update($posted_data): bool
    {
        if (is_array($posted_data)) {

            $new_data = apply_filters('fff/settings/update/validate/' . $this->optionKey, $posted_data);

            if (count($new_data)) {

                $isChanged = false;
                foreach ($new_data as $key => $value) {
                    if ($this->settings['stored'][$key] != $value) {
                        $this->settings['stored'][$key] = $value;
                        $isChanged                      = true;
                    }
                }

                if ($isChanged) {
                    $allowed_keys              = array_keys($this->settings['default']);
                    $this->settings['stored'] = array_intersect_key($this->settings['stored'], array_flip($allowed_keys));

                    $this->storeSettings();

                    return true;
                }
            }
        }

        return false;
    }

	/**
	 * @return void
	 */
    protected function storeSettings(): void
    {
	    if ($this->need_stored) {

		    if ($this->isACF) {
			    foreach ($this->settings['stored'] as $key => $value) {
				    update_field($key, $value, $this->optionKey);
			    }
		    } else {
			    update_option($this->optionKey, maybe_serialize($this->settings['stored']));
		    }
	    }

	    $this->settings['final'] = apply_filters('fff/settings/finalize/' . $this->optionKey, $this->settings['stored']);
    }

	/**
	 * @return array
	 */
	protected function getStoreSettings(): array
	{
		$settings = [];

		if (!$this->need_stored) {
			return $settings;
		}

		if ($this->isACF) {
			$option_page = acf_get_options_page($this->optionKey);
			if (!empty($option_page)) {
				$settings = (array) get_fields($this->optionKey);
			}
		} else {
			$storedSettings = get_option($this->optionKey);
			if ($storedSettings !== false) {
				$settings = (array) maybe_unserialize($storedSettings);
			}
		}

		return $settings;
	}
}
