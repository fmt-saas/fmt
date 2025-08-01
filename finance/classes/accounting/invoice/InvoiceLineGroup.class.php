<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting\invoice;

use equal\orm\Model;

class InvoiceLineGroup extends Model {

    public static function getName() {
        return 'Invoice line group';
    }

    public static function getDescription() {
        return 'Invoice line groups are related to an invoice and are meant to join several invoice lines.';
    }

    public static function getColumns() {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the account refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'name' => [
                'type'              => 'string',
                'description'       => 'Label of the group (displayed on invoice).',
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short description of the group (displayed on invoice).'
            ],

            'order' => [
                'type'              => 'integer',
                'description'       => 'Order by which the group has to be sorted when presented.',
                'default'           => 0
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\invoice\Invoice',
                'description'       => 'Invoice the line is related to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\invoice\InvoiceLine',
                'foreign_field'     => 'invoice_line_group_id',
                'description'       => 'Detailed lines of the group.',
                'ondetach'          => 'delete'
            ],

            'is_visible' => [
                'type'              => 'boolean',
                'description'       => 'Show group on the invoice.',
                'help'              => 'The group can be shown or hidden on the invoice.',
                'default'           => true
            ]

        ];
    }


}
