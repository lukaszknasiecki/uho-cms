<?php

use Huncwot\UhoFramework\_uho_fx;

/**
 * Model class for page [list view] widget
 */

class serdelia_widget_page
{
    var $params;
    var $orm;
    var $parent;

    /**
     * Constructor
     * @param object $orm instance of _uho_orm class
     * @param array $params
     */


    public function __construct($orm, $params, $parent)
    {
        $this->orm = $orm;
        $this->params = $params;
        $this->parent = $parent;
    }

    /**
     * Sets custom params for the widget
     * @param array $params
     * @return void
     */

    public function setParams($params)
    {
        $this->params = $params;
    }

    /**
     * Loads model data to be rendered by View
     * @return array
     */

    public function getData()
    {

        // depreceated
        if (!empty($this->params['page']) && empty($this->params['model']))
        {
            $this->params['model']=$this->params['page'];
        }

        $p = explode(',', $this->params['model']);
        $model = $p[0];
        unset($p[0]);
        $model_params = $p;

        $model = explode('?', $model)[0];

        $this->orm->setHaltOnError(false);
        $schema = $this->orm->get($model, true);

        if (!$schema)
        {
            $this->orm->setHaltOnError(true);
            return ['result' => false];
        }

        if (!$schema['label']) $schema['label'] = $this->parent->getSchemaLabelFromMenu($this->params['model']);
        if ($this->params['label'])  $schema['label'] = $this->params['label'];

        $f = [];
        if (isset($schema['filters'])) {
            $f = $schema['filters'];
            $params = ['cms_user' => $this->parent->getUser()];
            foreach ($f as $k => $v) {
                $f[$k] = $this->parent->getTwigFromHtml($f[$k], $params);
                foreach ($model_params as $k2 => $v2)
                    $f[$k] = str_replace('%' . $k2 . '%', $v2, $f[$k]);
            }
        }

        
        
        $all = $this->orm->getJsonModel($model, $f, false, null, null, ['count' => true, 'additionalParams' => $model_params]);
        if (_uho_fx::array_filter($schema['fields'], 'field', 'active')) {
            $f['active'] = 1;
            $active = $this->orm->getJsonModel($model, $f, false, null, null, ['count' => true, 'additionalParams' => $model_params]);
        } else $active = -1;

        $first = $this->orm->getJsonModel($model, $f, true, null, null, ['additionalParams' => $model_params]);
        if ($this->params['image']) $image = $first[$this->params['image']];
        if (is_array($image)) {
            if ($this->params['image_folder']) $image = $image[$this->params['image_folder']];
            else {
                $i = null;
                foreach ($image as $k => $v)
                    if ($v && $k != 'original' && !$i) $i = $v;
                $image = $i;
            }
        }

        $this->orm->setHaltOnError(true);

        return ['result' => true, 'all' => $all, 'active' => $active, 'icon' => @$this->params['icon'], 'label' => $schema['label'], 'image' => $image, 'url' => '/page/' . $this->params['model']];
    }
}
