<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace fmt\sync;

use equal\orm\Model;
use fmt\setting\Setting;

class UpdateRequestLine extends Model {

    public static function getColumns() {
        return [

            'update_request_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'fmt\sync\UpdateRequest',
                'description'       => 'Reference to the parent update request.',
                'required'          => true,
                'ondelete'          => 'cascade',
                'dependents'        => ['object_class', 'is_new']
            ],

            'object_class' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['update_request_id' => 'object_class'],
                'description'       => 'Field name of the targeted object.',
                'store'             => true
            ],

            'object_field' => [
                'type'              => 'string',
                'description'       => 'Field name of the targeted object.',
                'required'          => true
            ],

            'object_field_display_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Human readable field name of the targeted object.',
                'store'             => false,
                'function'          => 'calcObjectFieldDisplayName'
            ],

            'is_new' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['update_request_id' => 'is_new'],
                'store'             => true,
                'description'       => 'JSON encoded new proposed value for the field.'
            ],

            'new_value' => [
                'type'              => 'string',
                'description'       => 'JSON encoded new proposed value for the field.'
            ],

            'display_new_value' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "New value to display to user for it to be understandable easily.",
                'store'             => false,
                'function'          => 'calcDisplayNewValue'
            ],

            'old_value' => [
                'type'              => 'string',
                'description'       => 'Old JSON value (currently stored).',
                'visible'           => ['is_new', '=', false]
            ],

            'display_old_value' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Old value to display to user for it to be understandable easily.",
                'store'             => false,
                'function'          => 'calcDisplayOldValue'
            ],

        ];
    }

    protected static function calcObjectFieldDisplayName($self, $lang) {
        $result = [];
        $map_entity_translation = [];
        $self->read(['object_class', 'object_field']);
        foreach($self as $id => $line) {
            if(!isset($map_entity_translation[$line['object_class']])) {
                $map_entity_translation[$line['object_class']] = \eQual::run('get', 'core_config_i18n', [
                    'entity'    => $line['object_class'],
                    'lang'      => $lang
                ]);
            }

            $result[$id] = $map_entity_translation[$line['object_class']]['model'][$line['object_field']]['label'] ?? $line['object_field'];
        }

        return $result;
    }

    public static function calcDisplayNewValue($self, $orm): array {
        $date_format = null;
        $time_format = null;

        $result = [];
        $self->read(['object_class', 'object_field', 'new_value']);
        foreach($self as $id => $line) {
            $model = $orm->getModel($line['object_class']);
            $schema = $model->getSchema();

            $field_def = $schema[$line['object_field']];
            $type = $field_def['result_type'] ?? ($field_def['type'] ?? '');

            $display_value = $line['new_value'];
            if(is_null($line['new_value'])) {
                $display_value = 'NULL';
            }
            elseif($type === 'date') {
                if(is_null($date_format)) {
                    $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
                }
                $display_value = date($date_format, strtotime($line['new_value']));
            }
            elseif($type === 'datetime') {
                if(is_null($date_format)) {
                    $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
                }
                if(is_null($time_format)) {
                    $time_format = Setting::get_value('core', 'locale', 'time_format', 'H:i:s');
                }
                $display_value = date($date_format.' '.$time_format, strtotime($line['new_value']));
            }

            $result[$id] = $display_value;
        }

        return $result;
    }

    public static function calcDisplayOldValue($self, $orm): array {
        $date_format = null;
        $time_format = null;

        $result = [];
        $self->read(['object_class', 'object_field', 'is_new', 'old_value']);
        foreach($self as $id => $line) {
            $model = $orm->getModel($line['object_class']);
            $schema = $model->getSchema();

            $field_def = $schema[$line['object_field']];
            $type = $field_def['result_type'] ?? ($field_def['type'] ?? '');

            $display_value = $line['old_value'];
            if(is_null($line['old_value']) && !$line['is_new']) {
                $display_value = 'NULL';
            }
            elseif(!is_null($line['old_value']) && $type === 'date') {
                if(is_null($date_format)) {
                    $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
                }
                $display_value = date($date_format, strtotime($line['old_value']));
            }
            elseif(!is_null($line['old_value']) && $type === 'datetime') {
                if(is_null($date_format)) {
                    $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
                }
                if(is_null($time_format)) {
                    $time_format = Setting::get_value('core', 'locale', 'time_format', 'H:i:s');
                }
                $display_value = date($date_format.' '.$time_format, strtotime($line['old_value']));
            }

            $result[$id] = $display_value;
        }

        return $result;
    }
}
