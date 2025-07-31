<?php

/**
 * Class responsible for handling CORS-based file copy operations via API.
 */
class model_app_api_cors_copy
{
	/**
	 * Reference to parent context (usually the model or app controller).
	 * @var mixed
	 */
	private $parent;

	/**
	 * Settings or configuration array.
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param mixed $parent   Reference to the parent object.
	 * @param array $settings Configuration settings.
	 */
	public function __construct($parent, array $settings)
	{
	}

	/**
	 * Handles the REST call for copying a file via CORS.
	 *
	 * @param string $method  HTTP method (GET, POST, etc.).
	 * @param string $action  Action string (not used here).
	 * @param array  $params  Request parameters.
	 * @return bool|null      True on success, false on failure, null if invalid input.
	 */
	public function rest(string $method, string $action, array $params)
	{
		if (!isset($_POST['source'], $_POST['destination'])) {
			return null;
		}

		// Safely extract the filename from the destination
		$parts = explode('/', $_POST['destination']);
		$filename = array_pop($parts);
		$sanitizedFilename = basename($filename); // Prevent directory traversal

		$destinationPath = $this->parent->temp_folder. '/'.$sanitizedFilename;

		// Perform the file copy
		return copy($_POST['source'], $destinationPath);
	}
}
