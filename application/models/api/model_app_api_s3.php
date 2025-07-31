<?php

/**
 * Class responsible for displaying current S3 cache info (for debugging purposes).
 */
class model_app_api_s3
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

    /**
     * REST handler to debug S3 file metadata or cache contents.
     *
     * @param string $method   HTTP method.
     * @param string $action   Action parameter.
     * @param array  $params   Additional request parameters.
     * @return array|null      JSON result if no HTML is printed; null if output is HTML.
     */
    public function rest(string $method, string $action, array $params)
    {
        if (!isset($this->parent->s3) || !$this->parent->s3) {
            return ['result' => false, 'message' => 'S3 not defined'];
        }

        // Output debug UI
        echo '<link href="/serdelia/public/bootstrap/css/bootstrap.css" rel="stylesheet" media="screen">';
        echo '<body style="padding:10px">';

        if (isset($params['file'])) {
            $file = $this->parent->s3->getFileMetadata($params['file'], true);
            echo '<h3>File metadata</h3>';
            echo '<pre>' . htmlspecialchars($params['file']) . '</pre>';
            echo '<table class="table">';
            echo '<tr><td>URL</td><td>' . htmlspecialchars($file['@metadata']['effectiveUri']) . '</td></tr>';
            echo '</table>';
            echo '<p><a href="?" class="btn btn-primary">Back</a></p>';
        } else {
            $isListMode = \_uho_fx::getGet('list');
            $list = $this->parent->s3->getCache(!$isListMode);

            if (!$isListMode) {
                $this->parent->s3->saveCache();
                echo '<h3>Files (Recached)</h3>';
            } else {
                echo '<h3>' . count($list) . ' files</h3>';
            }

            echo '<ul>';
            foreach ($list as $key => $value) {
                $fileName = htmlspecialchars($key);
                $fileTime = htmlspecialchars($value['time']);
                echo "<li><code><a href=\"?file={$fileName}\">{$fileName}</a></code> ({$fileTime})</li>";
            }
            echo '</ul>';
        }

        exit('</body>');
    }
}

?>