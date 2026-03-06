<?php

/**
 * Class responsible for logout time management and session activity tracking.
 */
class model_app_api_timer
{
    /**
     * Reference to the parent object (likely model/controller).
     * @var mixed
     */
    private $parent;

    /**
     * Configuration or environment settings.
     * @var array
     */
    private $settings;

    /**
     * Constructor.
     *
     * @param mixed $parent
     * @param array $settings
     */
    public function __construct($parent, array $settings)
    {
        $this->parent = $parent;
        $this->settings = $settings;
    }

    public function rest(string $method, string $action, array $params)
    {
        $action=$params['action']??'';
        if ($action=='activity_renew')
            $this->parent->setActivityTime();

                return [
                    'result'=>true,
                    'activity_left' => $this->parent->getTimeActivityToLogout(),
                    'session_left' => $this->parent->getTimeSessionToLogout()
                ];
    }
}

?>