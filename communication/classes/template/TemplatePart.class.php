<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace communication\template;

use equal\orm\Model;

class TemplatePart extends Model {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Code of the template part.",
                'required'          => true
            ],

            'value' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => "Template content (html).",
                'multilang'         => true,
                'onupdate'          => 'onupdateValue',
                'dependents'        => ['excerpt']
            ],

            'excerpt' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcExcerpt',
                'description'       => "Template content (html).",
                'multilang'         => true,
                'store'             => true
            ],

            'variables' => [
                'type'              => 'string',
                'usage'             => 'text/json',
                'description'       => "JSON array of possibly referenced variables.",
                'multilang'         => true,
                'readonly'          => true
            ],

            'template_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'communication\template\Template',
                'description'       => "The template the part belongs to.",
                'required'          => true,
                'ondelete'          => 'cascade'
            ]

        ];
    }

    protected static function onupdateValue($self, $lang) {
        $self->read(['value'], $lang);
        foreach($self as $id => $templatePart) {
            /*
            preg_match_all('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', $templatePart['value'], $matches);
            $variables = json_encode(array_unique($matches[1]), JSON_PRETTY_PRINT);
            self::id($id)->update(['variables' => $variables], $lang);
            */
        }
    }

    protected static function calcExcerpt($self, $lang) {
        $results = [];
        $self->read(['value'], $lang);

        foreach($self as $id => $templatePart) {
            $html = $templatePart['value'];

            // strip HTML tags & normalize whitespace
            $text = strip_tags($html ?? '');
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/\s+/', ' ', trim($text));

            $max_len = 200;
            if(mb_strlen($text, 'UTF-8') > $max_len) {
                $text = mb_substr($text, 0, $max_len, 'UTF-8') . '…';
            }

            $results[$id] = $text;
        }

        return $results;
    }
}
