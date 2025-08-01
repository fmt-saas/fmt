<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace tracking;

class CaseFile extends \equal\orm\Model {

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium'
            ],

            'folder_node_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\navigation\Node',
                'description'       => 'Specific folder holding all documents relating to the case, if any.',
                'domain'            => [['node_type', '=', 'folder'], ['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

        ];
    }
}