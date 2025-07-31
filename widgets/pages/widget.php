<?php

/**
 * Model class for pages widget
 */

class serdelia_widget_pages
{

    /**
     * Constructor
     * @param object $orm instance of _uho_orm class
     * @param array $params
     */

    var $params;
    var $orm;
    var $parent;

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
        $pages = [];

        foreach ($this->params['pages'] as $k => $v) {
            if (is_array($v)) {
                $icon = $v['icon'];
                $v = $v['page'];
            } else $icon = null;

            $p = explode(',', $v);
            $model = $p[0];
            unset($p[0]);
            $model_params = $p;
            $schema = $this->orm->getJsonModelSchema($model, true);

            $label_now = $this->parent->getSchemaLabelFromMenu($v);

            if ($this->params['label'])  $schema['label'] = $this->params['label'];

            $f = [];

            $all = $this->orm->getJsonModel($model, $f, false, null, null, ['count' => true, 'additionalParams' => $model_params]);
            if (_uho_fx::array_filter($schema['fields'], 'field', 'active')) {
                $f = ['active' => 1];
                $active = $this->orm->getJsonModel($model, $f, false, null, null, ['count' => true, 'additionalParams' => $model_params]);
            } else $active = -1;

            $first = $this->orm->getJsonModel($model, $f, true, null, null, ['additionalParams' => $model_params]);
            if ($this->params['image']) $image = $first[$this->params['image']];
            if (is_array($image)) {
                array_shift($image);
                $image = array_shift($image);
            }

            $pages[] = ['all' => $all, 'active' => $active, 'icon' => $icon, 'label' => $label_now, 'url' => '/serdelia/page/' . $v];
        }

        return ['result' => true, 'pages' => $pages, 'icon' => @$this->params['icon'], 'label' => $schema['label'], 'image' => $image];
    }
}
