<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace documents\validation;
use equal\orm\Model;

class ValidationRule extends Model {

    public static function getName() {
        return "Document Validation Rule";
    }

    public static function getDescription() {
        return "Validation rules allow to specify a series of conditions a document has to meet, according to its type, in order to be considered valid.";
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

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Mark the rule as active/available on the current env.",
                'default'           => true
            ],

            'document_type_id' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\DocumentType',
                'description'       => 'Document type linked to the rule.'
            ],

            'document_subtype_id' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\DocumentSubtype',
                'description'       => 'Document subtype linked to the rule.'
            ],

            'validation_rule_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\recording\ValidationRuleLine',
                'foreign_field'     => 'validation_rule_id',
                'description'       => "Lines that are related to this rule."
            ]

        ];
    }

}
