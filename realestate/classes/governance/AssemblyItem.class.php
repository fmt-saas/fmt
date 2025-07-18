<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\governance;

class AssemblyItem extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'name' => [
                'type'        => 'string',
                'description' => "Short description of the assembly item.",
                'required'    => true
            ],

            'code' => [
                'type'        => 'string',
                'description' => "Unique code for the assembly item.",
                'required'    => false
            ],

            'description_call' => [
                'type'        => 'string',
                'usage'       => 'text/plain.small',
                'description' => "Description for the assembly call.",
                'required'    => false
            ],

            'description_minutes' => [
                'type'        => 'string',
                'usage'       => 'text/plain.small',
                'description' => "Description for the assembly minutes.",
                'required'    => false
            ],

            'has_vote_required' => [
                'type'        => 'boolean',
                'description' => "Flag indicating if a vote is required for this item.",
                'required'    => true,
                'default'     => false
            ],

            'majority' => [
                'type'        => 'string',
                'description' => "Type of majority required for the vote.",
                'selection'   => [
                    'unanimity',
                    'absolute',
                    '2_3',
                    '3_4',
                    '4_5',
                    '1_5'
                ],
                'required'    => true
            ],

            'apportionment_id' => [
                'type'           => 'many2one',
                'description'    => "The apportionment key used for the item (statutory or not).",
                'foreign_object' => 'realestate\property\Apportionment',
                'required'       => false
            ],

            'assembly_id' => [
                'type'              => 'many2one',
                'description'       => "The assembly the invitation refers to.",
                'foreign_object'    => 'realestate\governance\Assembly',
                'required'          => true
            ]
        ];
    }
}
