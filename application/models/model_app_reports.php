<?php

use Huncwot\UhoFramework\_uho_fx;

require_once('model_app.php');

/**
 * Model class for the reports module.
 */
class model_app_reports extends model_app
{
	/**
	 * Retrieves data for the reports page module.
	 *
	 * @param array|null $params Optional parameters
	 * @return array Report data, including file list and selected report content
	 */
	public function getContentData($params = null): array
	{
		$errors = [];
		$success = [];
		$report = '';

		// Read log files from the logs directory
		$files = scandir($this->logs_folder, SCANDIR_SORT_DESCENDING);

		// Filter out hidden files (starting with ".")
		$files = array_filter($files, fn($file) => $file[0] !== '.');
		$files = array_values($files); // Reindex array

		// Load selected file content by index
		$fileIndex = _uho_fx::getGet('file');
		if ($fileIndex && isset($files[$fileIndex - 1])) {
			$filename = $files[$fileIndex - 1];
			$filePath = $this->logs_folder . '/' . $filename;

			// Basic security: avoid directory traversal
			if (strpos(realpath($filePath), realpath($this->logs_folder)) === 0) {
				$report = file_get_contents($filePath);
			} else {
				$errors[] = 'Invalid file path.';
			}
		}

		return [
			'result'  => true,
			'files'   => $files,
			'report'  => $report,
			'errors'  => $errors,
			'success' => $success
		];
	}
}