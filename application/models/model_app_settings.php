<?php

use Huncwot\UhoFramework\_uho_fx;
require_once('model_app.php');

/**
 * Model class for settings and admin tools module.
 */
class model_app_settings extends model_app
{
	/**
	 * Returns settings options and performs optional admin operations.
	 *
	 * @param array|null $params Route and request context
	 * @return array Structured output for the settings page
	 */
	public function getContentData($params = null): array
	{
		$action = '';
		$text = '';
		$errors = [];
		$success=[];
		$admin = $this->isAdmin();
		$url = explode('/', $params['url'] ?? '');

		// Define available settings items
		$items = [
			['label' => 'password_change', 'url' => ['type' => 'password_change']],
			['label' => 'cache_clear', 'url' => ['type' => 'settings', 'subtype' => 'cache-clear']],
			['label' => 's3_cache_build', 'url' => ['type' => 'settings', 'subtype' => 's3-cache-build']],
			['label' => 'app_reports', 'url' => ['type' => 'settings', 'subtype' => 'app-reports'], 'admin' => true],
			['label' => 'cms_reports', 'url' => ['type' => 'settings', 'subtype' => 'cms-reports'], 'admin' => true],
			['label' => 'php_ini', 'url' => ['type' => 'settings', 'subtype' => 'php-ini'], 'admin' => true],
		];

		$action = $url[1] ?? '';

		// Check access restrictions
		if ($action) {
			foreach ($items as $item) {
				if (
					isset($item['url']['subtype'], $item['admin']) &&
					$item['url']['subtype'] === $action &&
					$item['admin'] === true &&
					!$admin
				) {
					$action = '';
					$errors[] = 'no_access';
					break;
				}
			}
		}

		// Perform actions
		switch ($action) {
			case 'php-ini':
				echo str_repeat('<br>', 5);
				phpinfo();
				exit;

			case 's3-cache-build':
				if (isset($this->s3))
				{
					$this->s3recache();
					$success[]='s3_built';
				}
					else $errors[] = 's3_not_defined';
				break;

			case 'app-reports':
				$text = $this->getReports('/reports/', _uho_fx::getGet('file'), _uho_fx::getGet('remove'));
				if (!$text) $errors[] = 'no_reports';
				break;

			case 'cms-reports':
				$text = $this->getReports($this->logs_path . '/', _uho_fx::getGet('file'), _uho_fx::getGet('remove'));
				if (!$text) $errors[] = 'no_reports';
				break;

			case 'cache-clear':
				$this->cacheKill();
				$this->cache_kill('serdelia/temp/upload/', ['.htaccess']);
				$this->cache_kill('serdelia/temp/upload/thumbnail/', ['.htaccess']);
				break;
		}

		// Load translations from external JSON
		$translatePath = __DIR__ . '/model_app_settings.json';
		$translations = file_exists($translatePath)
			? json_decode(file_get_contents($translatePath), true)
			: [];


		return [
			'action' => $action,
			'success'=>$success,
			'errors' => $errors,
			'translate' => $translations[$this->lang] ?? [],
			'items' => $items,
			'admin' => $admin,
			'text' => $text,
			'result' => true
		];
	}

	/**
	 * Clears files in a specified directory (excluding skipped files).
	 *
	 * @param string $dir Relative path from document root
	 * @param array $skip List of file names to skip
	 * @return void
	 */
	private function cache_kill(string $dir, array $skip = []): void
	{
		$path = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . trim($dir, '/');
		$scan = @scandir($path);

		if ($scan) {
			foreach ($scan as $item) {
				if ($item === '.' || $item === '..' || in_array($item, $skip)) continue;
				@unlink($path . DIRECTORY_SEPARATOR . $item);
			}
		}
	}

	/**
	 * Loads and optionally removes reports from a specified directory.
	 *
	 * @param string $dir Directory path
	 * @param string|null $file File name without extension
	 * @param string|null $remove File name to remove
	 * @return string HTML-rendered report
	 */
	private function getReports(string $dir, ?string $file = '', ?string $remove = ''): string
	{
		if ($file) $file .= '.txt';
		if ($remove) $remove = str_replace('/', '', $remove) . '.txt';

		$fullPath = $_SERVER['DOCUMENT_ROOT'] . $dir;
		$files = scandir($fullPath, SCANDIR_SORT_DESCENDING);
		$text = [];

		foreach ($files as $k => $v) {
			if ($remove && $v === $remove) {
				unset($files[$k]);
				@unlink($fullPath . '/' . $remove);
				continue;
			}

			if ($v === 'sql_errors.txt') {
				$text[] = ['label' => 'SQL Errors', 'file' => $v];
			} elseif (strpos($v, 'php-errors-') === 0) {
				$label = 'PHP Errors ' . substr($v, 11, 4) . '-' . substr($v, 15, 2) . '-' . substr($v, 17, 2);
				$text[] = ['label' => $label, 'file' => $v];
			}
		}

		$data = [];

		foreach ($text as $k => $entry) {
			if ($file && $file === $entry['file']) {
				$data = file($fullPath . '/' . $file);
			}

			$text[$k] = '<code><a href="?file=' . $entry['file'] . '">' . $entry['label'] . '</a></code>';
			$s = file_get_contents($fullPath . '/' . $entry['file']);
			if (strpos($s, 'PHP Fatal')) {
				$text[$k] = str_replace('<a ', '<a style="color:red;font-weight:600"', $text[$k]);
			}
			$text[$k] .= '<small style="padding-left:10px"><a href="?remove=' . $entry['file'] . '">[REMOVE]</a></small>';
		}

		if ($file === 'sql_errors.txt' && $data) {
			$data = array_filter(array_map('trim', $data));
			return '<pre>' . implode('<br>', $data) . '</pre>';
		} elseif ($data) {
			$parsed = [];
			foreach ($data as $line) {
				$line = trim($line);
				if (strpos($line, '[') === 0) {
					$i = strpos($line, '] ');
					$timestamp = substr($line, 1, $i - 1);
					$typeText = substr($line, $i + 2);
					$type = explode(':', $typeText, 2);
					$parsed[] = [
						'time' => explode(' ', $timestamp)[1] ?? '',
						'type' => trim($type[0]),
						'text' => trim($type[1] ?? ''),
						'details' => []
					];
				} elseif (!empty($parsed)) {
					$parsed[count($parsed) - 1]['details'][] = $line;
				}
			}

			// Format parsed logs
			foreach ($parsed as $entry) {
				$color = ($entry['type'] === 'PHP Fatal error') ? 'red' : 'black';
				$output = $entry['time'] . ' <b style="color:' . $color . '">' . $entry['type'] . '</b><br>' . $entry['text'];
				if (!empty($entry['details'])) {
					$output .= '<br><small>' . implode('<br>', $entry['details']) . '</small>';
				}
				$entryText[] = $output;
			}

			return '<pre>' . implode('<br><br>', $entryText ?? []) . '</pre>';
		}

		if ($file && empty($data)) {
			array_unshift($text, '<b>No data found in file ' . $file . '</b><br>');
		}

		return '<h5>Report</h5><hr>' . implode('<br>', $text);
	}
}