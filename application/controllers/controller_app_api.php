<?php

require_once("controller_app.php");

/**
 * Controller class for handling API method requests.
 * Inherits from controller_app.
 */
class controller_app_api extends controller_app
{
    /**
     * Processes incoming API request and prepares JSON output.
     * Sets response data based on API action and parameters.
     *
     * @return void
     */
    public function getData(): void
    {
        // Optional language configuration for ORM based on CMS config
        if (!empty($this->cfg['cms']['serdelia_languages_url'])) {

            // Retrieve available languages and normalize their shortcuts
            $langs = $this->cfg['cms']['app_languages'];
            foreach ($langs as $key => $lang) {
                $langs[$key] = strtolower($lang['shortcut']);
            }

            // Configure ORM with available languages
            $this->model->apporm->setLanguages($langs);
            $this->model->apporm->setLanguage($langs[0]); // Default to the first language
        }

        // Determine API action from route or POST parameters
        $action = $this->route->e(1);
        if (!$this->post) {
            $this->post = $this->get; // Fallback if no POST data
        }
        if (!$action && isset($this->post['action'])) {
            $action = $this->post['action'];
        }

        // Add configuration to GET parameters
        $this->get['cfg'] = $this->cfg;

        // Fetch API data using combined GET and POST parameters
        $apiParams = array_merge($this->get, $this->post);
        $this->data['content'] = $this->model->getApi($action, $apiParams);

        // Optionally update URLs in the response content
        $this->data['content'] = $this->urlUpdate($this->data['content']);

        // Set output type for the response to JSON
        $this->outputType = 'json';
    }
}

?>