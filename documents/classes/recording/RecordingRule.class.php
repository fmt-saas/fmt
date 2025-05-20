<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace documents\recording;
use equal\orm\Model;

class RecordingRule extends Model {

    public static function getName() {
        return "Recording Rule";
    }

    public static function getDescription() {
        return "Recording rules allow to specify the way a document is meant to be imputed.";
    }

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Name of the accounting rule.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the rule to serve as memo."
            ],

            'document_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentType',
                'description'       => 'Document type linked to the rule.'
            ],

            'document_subtype_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentSubtype',
                'description'       => 'Document subtype linked to the rule.'
            ],

            'recording_rule_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\recording\RecordingRuleLine',
                'foreign_field'     => 'recording_rule_id',
                'description'       => "Lines that are related to this rule."
            ]

        ];
    }

}
