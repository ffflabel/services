<?php

namespace FFFlabel\Services\Persistent\Storage;

class Transient extends StorageAbstract {

    public function __construct($user_id = false)
    {
        if ($user_id === false) {
            $user_id = get_current_user_id();
        }
        $this->sessionId = 'fff_persistent_' . $user_id;
    }
}
