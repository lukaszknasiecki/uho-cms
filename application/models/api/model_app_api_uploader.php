<?php

/**
 * Class responsible for file uploads (CKEditor, binary, and general uploads).
 */
class model_app_api_uploader
{
    /**
     * Parent object (likely the main model/controller).
     * @var mixed
     */
    private $parent;

    /**
     * Application settings/config.
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
     * Handles file upload requests.
     *
     * @param string $method  HTTP method.
     * @param string $action  Action identifier.
     * @param array  $params  Additional parameters.
     * @return array|null
     */
    public function rest(string $method, string $action, array $params)
    {

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'srt', 'mp4', 'wav', 'mp3'];


        $isCKEditor = isset($params['type']) && $params['type'] === 'ckeditor5';
        $isBinary = isset($params['type']) && $params['type'] === 'binary';

        require_once(__DIR__ . '/../../library/uploader/UploadHandler.php');

        $uploadDir = rtrim($params['cfg']['temp_folder'], '/') . '/upload/';
        $uploadUrl = $this->getFullUrl($params['cfg']['temp_path'] . '/upload/');
        $uploadUrlLocal = $params['cfg']['temp_path'] . '/upload/';

        $dirs = [
            'upload_dir' => $uploadDir,
            'upload_url' => $uploadUrl,
            'upload_url_local' => $uploadUrlLocal,
        ];

        // Handle CKEditor upload
        if ($isCKEditor) {

            $funcNum = $params['CKEditorFuncNum'] ?? '1';
            $filename = $_FILES['upload']['name'] ?? '';
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename); // Sanitize

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // Whitelist allowed extensions            
            if (!in_array($extension, $allowedExtensions)) {
                return ['result' => false, 'message' => "File type not allowed"];
            }

            $tempPath = $_FILES['upload']['tmp_name'] ?? '';

            if (!$this->checkMime($tempPath,$extension))
                exit(); //return ['result' => false, 'message' => 'mime type wrong'];


            if ($filename && move_uploaded_file($tempPath, $uploadDir . $filename)) {
                $url = $uploadUrlLocal . $filename;
                $message = ''; // Can be customized
                echo "<script>window.parent.CKEDITOR.tools.callFunction({$funcNum}, \"{$url}\", \"{$message}\");</script>";
            }
            exit;
        }

        // Handle binary upload
        if ($isBinary) {

            $filename = $_FILES['upload']['name'] ?? '';
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename); // Sanitize
            
            $source_filename = $_FILES['upload']['tmp_name'] ?? '';
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $filename=uniqid().'.'.$extension;
            
            // Whitelist allowed extensions            
            if (!in_array($extension, $allowedExtensions)) {
                return ['result' => false, 'message' => "File type not allowed"];
            }

            if (!$this->checkMime($source_filename,$extension))
                exit(); //return ['result' => false, 'message' => 'mime type wrong'];
            if ($filename && copy($source_filename, $uploadDir . $filename)) {
                $url = $uploadUrlLocal . $filename;
                echo json_encode(['url' => $url]);
            }
            exit;
        }

        // Use general UploadHandler for standard uploads
        new UploadHandler($dirs, true, null, $this->parent->lang ?? null);

        if (!empty($params['test'])) {
            return ['result' => true];
        }

        exit;
    }

    /**
     * Constructs a full URL based on the given path.
     *
     * @param string|null $uri Relative URI.
     * @return string Full URL.
     */
    private function getFullUrl(string $uri = null): string
    {
        if (!$uri) {
            $uri = substr($_SERVER['SCRIPT_NAME'], 0, strrpos($_SERVER['SCRIPT_NAME'], '/'));
        }

        $https = (!empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'on') === 0) ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
                strcasecmp($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0);

        $protocol = $https ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ??
            ($_SERVER['SERVER_NAME'] .
                (($https && $_SERVER['SERVER_PORT'] === 443 || $_SERVER['SERVER_PORT'] === 80)
                    ? ''
                    : ':' . $_SERVER['SERVER_PORT']));

        return $protocol . $host . $uri;
    }

    private function checkMime($file,$extension)
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file);
        if ($realMime && $realMime==$this->getMimeTypeFromExtension($extension)) return true;
    }

    private function getMimeTypeFromExtension(string $extension): ?string
    {
        $extension = strtolower($extension);

        $mimeTypes = [
            // Images
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',

            // Documents
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

            // Text
            'txt'  => 'text/plain',
            'csv'  => 'text/csv',
            'html' => 'text/html',
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'json' => 'application/json',
            'xml'  => 'application/xml',

            // Archives
            'zip'  => 'application/zip',
            'rar'  => 'application/vnd.rar',
            '7z'   => 'application/x-7z-compressed',

            // Audio / Video
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/wav',
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
        ];

        return $mimeTypes[$extension] ?? null;
    }
}
