<?php

/**
 * Model class for edit widget
 */

class serdelia_widget_edit
{
    /**
     * Constructor
     * @param object $orm instance of _uho_orm class
     * @param array $params
     */

    private $orm, $params, $parent;

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

        $p = explode(',', $this->params['model']);
        $model = $p[0];
        unset($p[0]);
        $model_params = $p;
        $this->orm->getSchema($model);

        if (is_numeric($this->params['record'])) $f = ['id' => $this->params['record']];
        else $f = ['slug' => $this->params['record']];
        $first = $this->orm->get($model, $f, true, null, null, ['additionalParams' => $model_params]);

        if (!$first) return ['result' => false];

        if (isset($this->params['label']))
            $first['title'] = $this->params['label'];
        elseif ($first['title' . $this->parent->lang_add]) $first['title'] = $first['title' . $this->parent->lang_add];

        $modules = $this->orm->get('pages_modules', ['parent' => $first['id']], false, null, null, ['count' => true]);

        if ($this->params['image']) {
            $image = $first[$this->params['image']];
            if (is_array($image)) {
                array_shift($image);
                $image = array_shift($image);
            }
        }

        return ['result' => true, 'modules' => $modules, 'label' => $first['title'], 'image' => $image, 'url' => '/edit/' . $this->params['model'] . '/' . $first['id'], 'icon' => $this->params['icon']];
    }
}
