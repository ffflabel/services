<?php

namespace FFFlabel\Services\Persistent;

use FFFlabel\Services\Persistent\Storage\Session;
use FFFlabel\Services\Persistent\Storage\StorageAbstract;
use FFFlabel\Services\Persistent\Storage\Transient;
use FFFlabel\Services\Traits\Singleton;
use WP_User;

class Persistent {

    use Singleton;

    /** @var StorageAbstract */
    private $storage;

	private function __construct()
	{
        add_action('init', [$this, 'init'], 0);

        add_action('fff_before_wp_login', function () {
            add_action('wp_login', [$this, 'transferSessionToUser' ], 10, 2);
        });
    }

    public function init()
    {
        if ($this->storage === NULL) {
            if (is_user_logged_in()) {
                $this->storage = new Transient();
            } else {
                $this->storage = new Session();
            }
        }
    }

    public static function set($key, $value)
    {
        if (self::$instance->storage) {
            self::$instance->storage->set($key, $value);
        }
    }

    public static function get($key, $default = false)
    {
        if (self::$instance->storage) {
            return self::$instance->storage->get($key);
        }

        return $default;
    }

    public static function delete($key)
    {
        if (self::$instance->storage) {
            self::$instance->storage->delete($key);
        }
    }

    /**
     * @param          $user_login
     * @param WP_User  $user
     */
    public function transferSessionToUser($user_login, $user = null)
    {

        if (!$user) { // For do_action( 'wp_login' ) calls that lacked passing the 2nd arg.
            $user = get_user_by('login', $user_login);
        }

        $newStorage = new Transient($user->ID);
        /**
         * $this->storage might be NULL if init action not called yet
         */
        if ($this->storage !== null) {
            $newStorage->transferData($this->storage);
        }

        $this->storage = $newStorage;
    }

    public static function clear()
    {
        if (self::$instance->storage) {
            self::$instance->storage->clear();
        }
    }
}
