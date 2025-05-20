<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
            ]

        ];
    }

}
