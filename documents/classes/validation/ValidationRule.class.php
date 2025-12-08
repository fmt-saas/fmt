<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
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
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentType',
                'description'       => 'Document type linked to the rule.',
                'required'          => true
            ],

            'document_subtype_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentSubtype',
                'description'       => 'Document subtype linked to the rule.'
            ],

            'validation_rule_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\validation\ValidationRuleLine',
                'foreign_field'     => 'validation_rule_id',
                'description'       => 'Lines relating to the rule.',
                'help'              => "Most validations rules use a single line, but defining several conditions (distinct controllers) is possible."
            ]

        ];
    }

}
