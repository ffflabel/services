<?php

namespace FFFlabel\Services\Persistent\Storage;

class Session extends StorageAbstract {

    /**
     * @var string name of the cookie. Can be changed with nsl_session_name filter and NSL_SESSION_NAME constant.
     *
     * @see https://pantheon.io/docs/caching-advanced-topics/
     */
    private $sessionName = 'SESSfff';

    public function __construct() {

        /**
         * WP Engine hosting needs custom cookie name to prevent caching.
         *
         * @see https://wpengine.com/support/wpengine-ecommerce/
         */
        if (class_exists('WpePlugin_common', false)) {
            $this->sessionName = 'wordpress_nsl';
        }
        if (defined('FFF_SESSION_NAME')) {
            $this->sessionName = FFF_SESSION_NAME;
        }
        $this->sessionName = apply_filters('nsl_session_name', $this->sessionName);
    }

    public function clear()
    {
        parent::clear();

        $this->destroy();
    }

    private function destroy()
    {
        $sessionID = $this->sessionId;
        if ($sessionID) {
            $this->setCookie($sessionID, time() - YEAR_IN_SECONDS, apply_filters('fff_session_use_secure_cookie', false));

            add_action('shutdown', array(
                $this,
                'destroySiteTransient'
            ));
        }
    }

    public function destroySiteTransient()
    {
        $sessionID = $this->sessionId;
        if ($sessionID) {
            delete_site_transient('fff_' . $sessionID);
        }
    }

    protected function load($createSession = false)
    {
        static $isLoaded = false;
        if ($this->sessionId === null) {
            if (isset($_COOKIE[$this->sessionName])) {
                $this->sessionId = 'fff_persistent_' . md5(SECURE_AUTH_KEY . $_COOKIE[$this->sessionName]);
            } elseif ($createSession) {

                $this->setCookie($this->sessionName, apply_filters('fff_session_cookie_expiration', 0), apply_filters('fff_session_use_secure_cookie', false));

                $this->sessionId = 'fff_persistent_' . md5(SECURE_AUTH_KEY . $this->sessionName);

                $isLoaded = true;
            }
        }

        if (!$isLoaded && $this->sessionId !== null) {
            $data = maybe_unserialize(get_site_transient($this->sessionId));
            if (is_array($data)) {
                $this->data = $data;
            }
            $isLoaded = true;
        }
    }

    private function setCookie($value, $expire, $secure = false)
    {
        setcookie($this->sessionName, $value, $expire, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, $secure);
    }
}
