<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\funding;

class FundRequestExecutionLine extends \sale\accounting\invoice\InvoiceLine {

    public static function getName() {
        return 'Fund Request Execution';
    }

    public static function getDescription() {
        return "A Fund Request Execution Line represents an individual allocation within a Fund Request Execution, detailing the called amount for a specific owner and period.";
    }

    public static function getColumns() {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'request_execution_id' => [
                'type'              => 'alias',
                'alias'             => 'invoice_id'
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true,
                'readonly'          => true
            ],

            'price' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-excluded price of the line.',
            ],

            'called_amount' => [
                'type'              => 'alias',
                'alias'             => 'price',
                'usage'             => 'amount/money:2'
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => 'There is no customer for fund requests.',
            ],

            'line_entries_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\funding\FundRequestLineEntry',
                'foreign_field'     => 'execution_lines_ids',
                'rel_table'         => 'funding_lineentry_rel_funding_executionline',
                'rel_foreign_key'   => 'line_entry_id',
                'rel_local_key'     => 'execution_line_id',
                'description'       => "Request fund execution line the entry relates to, if any."
            ]

        ];
    }

}