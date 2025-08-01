<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
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

            'fund_request_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequest',
                'description'       => "Fund request the line relates to.",
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'request_execution_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequestExecution',
                'relation'          => ['invoice_id'],
                'description'       => 'The fund request execution (sale invoice) the line relates to.',
                'help'              => 'This field acts as an alias of `invoice_id`.'
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
                'description'       => 'There is no product for fund requests.',
            ],

            'line_entries_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\funding\FundRequestLineEntry',
                'foreign_field'     => 'execution_lines_ids',
                'rel_table'         => 'funding_lineentry_rel_funding_executionline',
                'rel_foreign_key'   => 'line_entry_id',
                'rel_local_key'     => 'execution_line_id',
                'description'       => "Request fund execution line the entry relates to, if any."
            ],

            'execution_line_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\funding\FundRequestExecutionLineEntry',
                'foreign_field'     => 'request_execution_line_id',
                'description'       => "Line entries of the Fund request execution."
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'description'       => 'The funding relating to the execution line, if any.',
                'help'              => 'Fundings are created when execution is validated. In case of cancellation, only paid or partially paid fundings remain.'
            ]

        ];
    }

}