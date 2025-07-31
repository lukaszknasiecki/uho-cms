<?php

require_once('model_app.php');

/**
 * Model class for the S3 debug page module.
 */
class model_app_s3 extends model_app
{
	/**
	 * Gathers debug information from the S3 instance.
	 *
	 * @param array|null $params Optional parameters (not used)
	 * @return array Result data including error/success messages
	 */
	public function getContentData($params = null): array
	{
		$errors = [];
		$success = [];

		if (isset($this->s3)) {
			$success[] = 'Cached items: ' . $this->s3->getCachedItemsCount();
			$success[] = 'Compress method: ' . $this->s3->getCompress();
			$success[] = 'Path to skip: ' . $this->s3->getPathSkip();

			$data = $this->s3->getCache(false, 0, 10);
			$success[] = 'First 10 assets: ' . json_encode($data);
		} else {
			$errors[] = 'S3 is not defined';
		}

		return [
			'result'  => true,
			'errors'  => $errors,
			'success' => $success
		];
	}
}