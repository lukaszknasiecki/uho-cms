<?php

use Huncwot\UhoFramework\_uho_fx;
use Huncwot\UhoFramework\_uho_thumb;

/**
 * Serdelia built-in plugin for resizing/cropping media-type field items
 */

class serdelia_plugin_media_resize
{

  /** Standard Serdelia Plugin Contructor
   * object array $cms instance of _uho_orm
   * object array $params
   * object array $parent instance of _uho_model
   * @return null
   */

  var $cms, $params, $parent, $root;

  public function __construct($cms, $params, $parent = null)
  {
    $this->cms = $cms;
    $this->params = $params;
    $this->parent = $parent;
    $this->root = $_SERVER['DOCUMENT_ROOT'];
  }

  /** Main plugin-method, returns data for View module
   * @return array
   */

  public function getData()
  {

    $params = $this->params;
    $p = $params['params'];

    if (!$params['record']) return ['result' => false];
    if (!$p['field']) $p['field'] = 'media';
    if (!$p['nr']) $p['nr'] = 0;


    $pp = [1 => @$this->params['params'][0]];

    $record = [];

    $schema = $this->parent->getSchemaForEdit($params['page'], $record, $pp, $params['record']);

    $field = $schema['fields'];
    $field = _uho_fx::array_filter($field, 'field', $p['field'], ['first' => true]);
    if (!$field) exit('error::field[' . $p['field'] . '] not found');

    $record = $this->cms->getJsonModel($schema, ['id' => $params['record']], true, null, null, ['additionalParams' => $params['params']]);

    if (!$record) return ['result' => false];

    if ($field['source']['model']) {
      $m = $this->cms->getJsonModelSchema($field['source']['model']);
      $field = _uho_fx::array_filter($m['fields'], 'type', 'image', ['first' => true]);
    }

    $v = $record[$p['field']];
    $v = $v[$p['nr']];

    $value = $v;
    $media_id = $v['id'];


    /*
       SUBMIT PROCESSING
    */

    if ($_POST) {

      $field['filename'] = str_replace('%uid%', $value['uid'], $field['filename']);

      /* video processing */
      if ($_POST['cover_from_video']) {
        $position = $_POST['position'];
        if (!$position) $position = 0;
        $mp4 = $_SERVER['DOCUMENT_ROOT'] . '/' . explode('?', $value['video']['src'])[0];
        $destination = $_SERVER['DOCUMENT_ROOT'] . $field['folder'] . '/original/' . $field['filename'] . '.jpg';
        $cmd = "-i " . $mp4 . " -vframes 1 -y -ss " . str_replace(',', '.', $position) . " $destination";

        $this->parent->ffmpeg($cmd);
        $resize_all = 1;
      }

      /* image processing */
      if ($_POST['e_image'])
      {

        $source = $this->parent->upload_path . $_POST['e_image'];
        $destination = $field['folder'] . '/original/' . $field['filename'] . '.jpg'; //$_SERVER['DOCUMENT_ROOT'] . 

        if (file_exists($source)) {
          $this->unlink($destination);
          $this->copy($source, $destination);
          //@unlink($source);
          $resize_all = true;
        }
      }

      foreach ($_POST as $k => $v)
        if (substr($k, 0, 7) == 'e_fake1' && ($v || $resize_all)) {
          $nr = explode('_', $k);
          $nr = array_pop($nr);

          if ($v) {
            $crop = explode(',', $v);
            $crop = array('x1' => $crop[0], 'y1' => $crop[1], 'width' => $crop[2], 'height' => $crop[3]);
            $is_crop = null;
          } else {
            $crop = null;
            $is_crop = $field['images'][$nr]['crop'];
          }

          //if (!$source)
          {
            $source = $field['folder'] . '/original/' . $field['filename'] . '.jpg';
            if ($this->parent->s3) $source = $this->parent->s3->getFilenameWithHost($source);
          }

          $destination =  $this->root . $field['folder'] . '/' . $field['images'][$nr]['folder'] . '/' . $field['filename'] . '.jpg';
          $destination_path = $field['folder'] . '/' . $field['images'][$nr]['folder'] . '/' . $field['filename'] . '.jpg';
          if ($this->parent->s3) $destination = $this->parent->s3GetTempFilename();
          $webp = @$field['settings']['webp'];

          $result = _uho_thumb::convert(
            $field['filename'],
            $_SERVER['DOCUMENT_ROOT'] . $source,
            $destination,
            array(
              'width' => $field['images'][$nr]['width'],
              'height' => $field['images'][$nr]['height'],
              'enlarge' => true,
              'cut' => $is_crop,
              'webp' => $webp,
              'mask' => ['image' => $field['images'][$nr]['mask'], 'type' => 'overlay'],
              'position' => 'RB'
            ),
            true,
            null,
            $crop
          );


          if ($this->parent->s3) {
            $this->copy($destination, $destination_path, $webp);
          }

          if ($field['images'][$nr]['retina']) {
            $dir = $field['folder'] . '/' . $field['images'][$nr]['folder'] . '_x2';
            if (!$this->parent->s3 && !is_dir($_SERVER['DOCUMENT_ROOT'] . $dir)) mkdir($_SERVER['DOCUMENT_ROOT'] . $dir);

            $destination_path = $dir . '/' . $field['filename'] . '.jpg';
            $destination = $this->root . $destination_path;
            if ($this->parent->s3) $destination = $this->parent->s3GetTempFilename();

            $result = _uho_thumb::convert(
                $field['filename'],
                $source,
                $destination,
                array(
                  'width' => $field['images'][$nr]['width'] * 2,
                  'height' => $field['images'][$nr]['height'] * 2,
                  'enlarge' => true,
                  'cut' => $is_crop,
                  'webp' => $webp,
                  'mask' => ['image' => $field['images'][$nr]['mask'], 'type' => 'overlay'],
                  'position' => 'RB'
                ),
                true,
                null,
                $crop
              );

            if ($this->parent->s3) $this->copy($destination, $destination_path, $webp);
          }

          if (!$result['result']) print_r($result);
        }
    }
    

    $this->parent->imageUpdateResize($m['table'], $field['field'], $media_id);

    if ($_POST['submit_back']) {
      header("Location:" . $this->params['url_back_string']);
    }

    $data = ['result' => true, 'value' => $value, 'errors' => $errors, 'field' => $field];

    return $data;
  }

  private function file_exists($f)
  {
    if ($this->parent->s3) return $this->parent->s3->file_exists($f);
    else return _uho_fx::file_exists($f);
  }
  private function unlink($filename)
  {
    if ($this->parent->s3) return $this->parent->s3->unlink($filename);
    else return @unlink($_SERVER['DOCUMENT_ROOT'] . $filename);
  }
  private function copy($source, $dest, $webp = false)
  {
    if ($this->parent->s3) {
      $this->parent->s3->copy($source, $dest);
      if ($webp) {
        $source = str_replace('.jpg', '.webp', $source);
        $dest = str_replace('.jpg', '.webp', $dest);
        $this->parent->s3->copy($source, $dest);
      }
    } else return copy($$source, $this->root . $dest);
  }
  
}
