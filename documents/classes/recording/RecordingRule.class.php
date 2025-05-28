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
        return "Recording rules specify how a document of a given type and subtype should be recorded in the accounting system.";
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Condominium',
                'description'       => "The condominium the rule applies to.",
                'help'              => "If left unset, the rule is considered as a template."
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the accounting rule.",
                'required'          => true
            ],

            'is_template' => [
                'type'              => 'boolean',
                'description'       => "Flag marking rhe rule as a template.",
                'help'              => "The rule is a template meant to be used for condominium-specific rules.",
                'default'           => false
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
