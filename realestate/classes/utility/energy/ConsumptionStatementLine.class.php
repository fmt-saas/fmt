<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\utility\energy;


class ConsumptionStatementLine extends \equal\orm\Model {

    public static function getName() {
        return 'Consumption Statement Line';
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the payment relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'consumption_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\utility\energy\ConsumptionStatement',
                'description'       => "Period of the fiscal year the consumption statement relates to.",
                'dependents'        => ['parent_consumption_meter_id']
            ],

            'parent_consumption_meter_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'realestate\utility\energy\ConsumptionMeter',
                'description'       => 'The meter ID relates to the consumption meter reading in the booking.',
                'relation'          => ['consumption_statement_id' => 'consumption_meter_id'],
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['meter_scope', '=', 'unit']],
                'required'          => true
            ],

            'consumption_meter_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\utility\energy\ConsumptionMeter',
                'description'       => 'The consumption meter ID the line relates to.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['meter_scope', '=', 'unit']],
                'required'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the line refers to (based on accounting account).",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'domain'            => [['condo_id', '=', 'object.condo_id']]
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'description'       => "Property Lot to apply the charge to.",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            // intersection between the statement period and the propertyLotOwnership
            'date_from' => [
                'type'              => 'date',
                'description'       => 'Date from which the owners owned the property lot.',
                'help'              => 'This date can only be different from parent statemnt in the cas an ownership transfer occurred within the period.',
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => 'Date from until which the owner owned the property lot.',
                'help'              => 'This date can only be different from parent statemnt in the cas an ownership transfer occurred within the period.',
            ],

            'page_from' => [
                'type'              => 'integer',
                'description'       => 'Index of first page for the property lot in parent statement document.',
                'help'              => 'This is used in order to slice the parent doc and generate a document specific to the line.'
            ],

            'page_to' => [
                'type'              => 'integer',
                'description'       => 'Index of last page for the property lot in parent statement document.',
                'help'              => 'This is used in order to slice the parent doc and generate a document specific to the line.'
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'PDF with statement specific to the ownership.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
            ],

            'price' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Final tax-included price for targeted consumptions.'
            ]

        ];
    }
}