<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

class AssemblyMinutesEntry extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'assembly_id' => [
                'type'              => 'many2one',
                'description'       => "The assembly the minute entry refers to.",
                'foreign_object'    => 'realestate\governance\Assembly',
                'required'          => true
            ],

            'assembly_item_id' => [
                'type'              => 'many2one',
                'description'       => "Link to the AssemblyItem record.",
                'foreign_object'    => 'realestate\governance\AssemblyItem',
                'required'          => true
            ],

            'description_text' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => "Optional comments for the entry."
            ],

            'has_vote_required' => [
                'type'              => 'boolean',
                'description'       => "Indicates if a vote is required for this item.",
                'default'           => false
            ],

            'vote_validated' => [
                'type'              => 'boolean',
                'description'       => "Indicates if the vote has been validated."
            ],

            'vote_details' => [
                'type'              => 'string',
                'usage'             => 'text/json',
                'description'       => "Synthetic details of the vote, if applicable.",
                'visible'           => ['has_vote_required', '=', true]
            ]
        ];
    }
}
