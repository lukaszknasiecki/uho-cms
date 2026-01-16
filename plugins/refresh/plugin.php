<?php

/**
 * Serdelia built-in plugin to automate teasks on whole models
 */

class serdelia_plugin_refresh
{

    var $cms, $params;

    /** Standard Serdelia Plugin Contructor
     * object array $cms instance of _uho_orm
     * object array $params
     * object array $parent instance of _uho_model
     * @return null
     */


    public function __construct($cms, $params, $parent = null)
    {
        $this->cms = $cms;
        $this->params = $params;
    }

    /** Main plugin-method, returns data for View module
     * @return array
     */

    public function getData()
    {

        ini_set('memory_limit', '2048M');

        $model = $this->params['page'];
        $schema = $this->cms->getSchema($model);
        $type = 'list';

        // get fields
        $fields = [];
        $id = 0;


        if (!empty($_SESSION['page_filters'][$model])) $filters = $_SESSION['page_filters'][$model];
        else $filters = [];

        $count_all = $this->cms->get($this->params['page'], $filters, null, null, null, ['count' => true, 'additionalParams' => $this->params['params']]);


        // add edit plugins
        if ($schema['buttons_edit'])
            foreach ($schema['buttons_edit'] as $k => $v)
                if ($v['type'] == 'plugin' && !in_array($v['plugin'], ['refresh', 'preview']))
                    $fields[] = ['type' => 'plugin', 'plugin' => $v['plugin'], 'label' => $v['label'] . ' (plug-in)', 'id' => 'i' . ++$id, 'params' => $v['params']];

        // add auto-fields
        foreach ($schema['fields'] as $k => $v) {
            if (is_array($v['cms']['label'])) $v['cms']['label'] = $v['cms']['label']['edit'];
            if ($v['auto'] || in_array($v['type'], ['image']))
                $fields[] = ['type' => 'field', 'field' => $v['field'], 'label' => $v['cms']['label'], 'id' => 'i' . ++$id];
        }

        // POST
        if ($_POST) {
            $f = [];
            $s_from = intval($_POST['s_from']) - 1;
            if ($s_from < 0) $s_from = 0;
            $s_count = intval($_POST['s_count']);

            if ($s_from > 0 || $s_count != 0) {
                if ($s_count == 0) $s_count = 999999;
                $limit = $s_from . ',' . $s_count;
            } else unset($limit);

            foreach ($_POST as $k => $v)
                if ($k[0] == 'i') $f[] = $fields[substr($k, 1) - 1];

            if ($f) {

                $post = $_POST;
                $count = $this->cms->get($this->params['page'], $filters, null, null, null, ['count' => true, 'additionalParams' => $this->params['params']]);
                if ($s_from) $count = $count - $s_from;
                if ($limit && $count > $s_count) $count = $s_count;

                $progress = 1;
                $type = 'submit';
            } {
                $fields_mod = [];
                $s_mod = [];

                foreach ($f as $k => $v)
                    if ($v['type'] == 'field') $fields_mod[] = $v['field'];
                    else if ($v['type'] == 'plugin')
                        $plugins_mod[] = $this->params['url_serdelia'] . '/plugin-edit/' . $this->params['page_with_params'] . '/%id%/' . $v['plugin'] . '?params=' . urlencode(json_encode($v['params']));

                if ($fields_mod) {
                    $ajax = $this->params['url_serdelia'] . '/write/' . $this->params['page_with_params'] . '/';
                }

                $ids = $this->cms->get($this->params['page'], $filters, null, null, $limit, ['additionalParams' => $this->params['params']]);
                foreach ($ids as $k => $v)
                    $ids[$k] = $v['id'];
            }

            $hashable = [
                'decrypt' => @$_POST['s_decrypt'],
                'encrypt' => @$_POST['s_encrypt']
            ];
        }

        $data = ['result' => true, 'count_all' => $count_all, 'hashable' => $hashable, 'filters' => $filters, 'fields' => $fields, 'ajax' => $ajax, 'ajax_ids' => $ids, 'ajax_plugins' => $plugins_mod, 'ajax_fields' => $fields_mod, 'type' => $type, 'count' => $count, 'progress' => $progress, 'post' => $post];

        return $data;
    }
}
