<?php

require_once('model_app.php');

/**
 * Model class for handling API requests.
 */
class model_app_api extends model_app
{
    /**
     * Configuration settings inherited from _uho_application
     * @var array
     */
    public $cfg;

    /**
     * Handles API calls by delegating to the appropriate API handler class.
     *
     * @param string $action The API action name.
     * @param array $params Parameters passed to the API handler.
     * @return array Result from the API handler, or an error if action is invalid.
     */
    public function getApi(string $action, array $params): array
    {
        $this->cfg = $params['cfg']; // Assign config from params
        $result = [
            'result' => false,
            'message' => 'Invalid action: [' . $action . ']'
        ];

        $method = ''; // Placeholder for potential method usage (e.g., GET, POST)

        switch ($action) {
            // File-related API handlers
            case "s3":
            case "uploader":
            case "cors_copy":
                require_once("api/model_app_api_" . $action . ".php");
                $className = 'model_app_api_' . $action;
                $handler = new $className($this, []);
                $result = $handler->rest($method, $action, $params);
                break;

            // Application-specific API handlers
            case "app_init":
            case "app_page":
                require_once("api/model_app_api_" . $action . ".php");
                $className = 'model_app_api_' . $action;
                $handler = new $className($this, null);
                $result = $handler->rest($method, $action, $params);
                break;
        }

        return $result;
    }
}