<?php

namespace FFFlabel\Services\Persistent\Storage;

abstract class StorageAbstract {

    protected $sessionId = null;

    protected $data = [];

    public function set($key, $value)
    {
        $this->load(true);

        $this->data[$key] = $value;

        $this->store();
    }

    public function get($key, $default = null)
    {
        $this->load();

        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        return $default;
    }

    public function delete($key)
    {
        $this->load();

        if (isset($this->data[$key])) {
            unset($this->data[$key]);
            $this->store();
        }
    }

    public function clear()
    {
        $this->data = [];
        $this->store();
    }

    protected function load($createSession = false)
    {
        static $isLoaded = false;

        if (!$isLoaded) {
            $data = maybe_unserialize(get_site_transient($this->sessionId));
            if (is_array($data)) {
                $this->data = $data;
            }
            $isLoaded = true;
        }
    }

    private function store()
    {
        if (empty($this->data)) {
            delete_site_transient($this->sessionId);
        } else {
            set_site_transient($this->sessionId, $this->data, apply_filters('fff_persistent_expiration', HOUR_IN_SECONDS));
        }
    }

    /**
     * @param StorageAbstract $storage
     */
    public function transferData($storage)
    {
        $this->data = $storage->data;
        $this->store();

        $storage->clear();
    }
}