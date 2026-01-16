<?php

use Huncwot\UhoFramework\_uho_fx;

/**
 * Serdelia built-in plugin to import data from CSV/JSON to a model.
 */
class serdelia_plugin_import
{
    /** @var object CMS instance (likely _uho_orm) */
    var $cms;

    /** @var array Plugin parameters */
    var $params;

    /**
     * Serdelia plugin constructor.
     *
     * @param object $cms    CMS core instance.
     * @param array  $params Parameters passed to the plugin.
     * @param object|null $parent Optional model reference (not used directly).
     */
    public function __construct($cms, $params, $parent = null)
    {
        $this->cms = $cms;
        $this->params = $params;
    }

    /**
     * Main plugin method, processes import and returns data for the View module.
     *
     * @return array Result including fields, errors, and messages.
     */
    public function getData()
    {
        $errors = [];
        $added = [];
        $updated = [];

        // Load model schema for the target page
        $schema = $this->cms->getSchema($this->params['page']);
        $fields = $schema['fields'];
        $submitted = [];

        // Filter allowed field types and detect submitted fields
        foreach ($fields as $k => $v) {
            if (in_array($v['type'], ['string', 'boolean', 'text', 'select', 'date'])) {
                $fields[$k]['field'] = $v['field'] = str_replace(':lang', '_EN', $v['field']);
                if (!empty($_POST['f_' . $v['field']])) {
                    $submitted[] = $v['field'];
                }
            } else {
                unset($fields[$k]);
            }
        }

        $submitted=$submitted || !empty($_POST['import_spreadsheet']);
        $rework_needed=true;

        // No fields selected
        if ($_POST && !$submitted) {
            $errors[] = 'nothing_checked';
        }

        /*
        * Proceed with import
        */

        if ($submitted) {

            $file = $_FILES['csv']['tmp_name'] ?? null;
            $data = [];

            if ($file) {
                // Read data from uploaded CSV file
                if (($handle = fopen($file, "r")) !== false) {
                    while (($row = fgetcsv($handle, 1000, $_POST['csv_separator'])) !== false) {
                        $entry = [];
                        foreach ($submitted as $i => $field) {
                            $entry[$field] = $row[$i] ?? null;
                        }
                        $data[] = $entry;
                    }
                    fclose($handle);
                } else {
                    exit('Error reading CSV');
                }
            } elseif (!empty($_POST['import_spreadsheet'])) {
                $lines = explode("\r\n", $_POST['import_spreadsheet']);

                foreach ($lines as $i => $line)
                    if (trim($line))
                    {
                        $line = trim($line);
                        $fieldsData = explode("\t", $line);

                        if ($i == 0)
                        {
                            $header=[];
                            foreach ($fieldsData as $j => $value)
                                if ($value) $header[]=$value; else continue   ;

                        } else
                        {
                            $entry = [];
                            foreach ($fieldsData as $j => $value)
                            if (isset($header[$j]) )
                            {
                                $entry[$header[$j]] = $value;
                            }
                            $data[] = $entry;
                        }
                    }
                    $rework_needed=false;
                }
             elseif (!empty($_POST['import'])) {
                // Try decoding JSON input
                $json = json_decode($_POST['import'] ?? '', true);

                if ($json) {
                    $data = $json;
                } else {
                    // Parse raw tab-separated values
                    $lines = explode("\r\n", $_POST['import']);
                    foreach ($lines as $i => $line) {
                        if ($i === 0 && !empty($_POST['skip'])) continue;
                        $line = trim($line);
                        if ($line) {
                            $fieldsData = explode("\t", $line);
                            $entry = [];
                            foreach ($fieldsData as $j => $value) {
                                $entry[$submitted[$j] ?? ""] = $value;
                            }
                            $data[] = $entry;
                        }
                    }
                }
            }


            // Apply schema-specific transformations and dictionary lookups

            if ($rework_needed) {
                $reworked = $this->rework($this->params['page'], $schema, $data);
                $data = $reworked['data'];
                $added = array_merge($added, $reworked['message'] ?? []);
                $errors = array_merge($errors, $reworked['errors'] ?? []);
            }

            // Insert or update data
            if ($data) {
                if (!empty($_POST['key'])) {
                    // Update by key field
                    $key = $_POST['key'];
                    $ok = $bad = 0;

                    foreach ($data as $entry) {
                        $success = $this->cms->put($this->params['page'], $entry, [$key => $entry[$key]]);
                        $success ? $ok++ : $bad++;
                    }

                    if ($ok) $added[] = "Updated items: $ok";
                    if ($bad) $errors[] = "Errors while updating: $bad";
                } else {
                    // Insert new entries
                    $success = $this->cms->post($this->params['page'], $data, true);
                    if ($success) {
                        $added[] = 'Added items: ' . count($data);
                    } else {
                        $errors[] = 'SQL: <code>' . $this->cms->getLastError() . '</code>';
                    }
                }
            } else {
                $errors[] = 'no_data';
            }
        }

        return [
            'result'  => true,
            'fields'  => $fields,
            'errors'  => $errors,
            'added'   => $added,
            'updated' => $updated,
        ];
    }

    /**
     * Preprocess input data against schema, handle select field mappings and duplicates.
     *
     * @param string $page   Model page identifier.
     * @param array  $schema Schema definition from CMS.
     * @param array  $data   Raw data to be reworked.
     *
     * @return array Processed data with messages or errors.
     */
    private function rework($page, $schema, $data)
    {
        $message = [];
        $errors = [];

        foreach ($schema['fields'] as $fieldDef) {
            $field = $fieldDef['field'];

            // Handle dictionary (select) fields
            if ($fieldDef['type'] === 'select' && !empty($fieldDef['source']['model'])) {
                $model = $this->cms->get($fieldDef['source']['model']);

                foreach ($data as $i => $row) {
                    $input = trim($row[$field] ?? '');
                    $data[$i][$field] = $input;

                    // Try to match label to existing model entry
                    $match = _uho_fx::array_filter($model, 'label', $input, ['first' => true]);
                    if ($match) {
                        $data[$i][$field] = $match['id'];
                    } else {
                        // Insert new dictionary value
                        $insertResult = $this->cms->post($fieldDef['source']['model'], ['label' => $input]);
                        if (!$insertResult) {
                            $errors[] = 'SQL: <code>' . $this->cms->getLastError() . '</code>';
                            $errors[] = "Error writing new dictionary field [$input] for [{$fieldDef['source']['model']}]";
                            return ['data' => [], 'errors' => $errors];
                        }

                        // Get inserted ID and reload model
                        $data[$i][$field] = $this->cms->getInsertId();
                        $model = $this->cms->get($fieldDef['source']['model']);
                        $message[] = "Added new dictionary field [$input] for [{$fieldDef['source']['model']}]";
                    }
                }
            }
        }

        // Remove duplicates already in model
        $duplicates = 0;
        foreach ($data as $i => $row) {
            if ($this->cms->get($page, $row)) {
                unset($data[$i]);
                $duplicates++;
            }
        }

        if ($duplicates) {
            $message[] = "$duplicates items skipped as duplicates";
        }

        return ['data' => array_values($data), 'message' => $message];
    }
}
