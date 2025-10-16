<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace documents\labeling;
use equal\orm\Model;

class LabelingRule extends Model {

    public static function getName() {
        return "Document Labeling Rule";
    }

    public static function getDescription() {
        return "Labeling rules allow to specify how the internal operations originating from a document, and its subsequent allocations, should be labeled.";
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

            'labeling_rule_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\labeling\LabelingRuleLine',
                'foreign_field'     => 'labeling_rule_id',
                'description'       => "Lines that are related to this rule."
            ],

            'supplier_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\SupplierType',
                'description'       => "Type of supplier this rule applies to."
            ],

            'suppliers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'foreign_field'     => 'labeling_rule_id',
                'description'       => "Lines that are related to this rule."
            ],

            'document_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentType',
                'description'       => 'Document type linked to the rule.'
            ],

            'document_subtype_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentSubtype',
                'description'       => 'Document subtype associated with the rule, if any.',
                'domain'            => ['document_type_id', '=', 'object.document_type_id']
            ]
        ];
    }

}
