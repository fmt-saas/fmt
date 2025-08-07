<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

class AssemblyTemplate extends \equal\orm\Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'        => 'string',
                'description' => "Name of the assembly template.",
                'required'    => true
            ],

            'description' => [
                'type'        => 'string',
                'usage'       => 'text/plain.small',
                'description' => "Name of the assembly template."
            ],

            'heading_text_call' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Heading text of the assembly call.",
                'required'          => false
            ],

            'heading_text_minutes' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Heading text of the assembly minutes.",
                'required'          => false
            ],

            'closing_text_call' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Closing text of the assembly call.",
                'required'          => false
            ],

            'closing_text_minutes' => [
                'type'              => 'string',
                'usage'             => 'text/html.small',
                'description'       => "Closing text of the assembly minutes.",
                'required'          => false
            ],

            'organization_id' => [
                'type'            => 'many2one',
                'description'     => "Organization managing the assembly.",
                'foreign_object'  => 'identity\Organisation',
                'default'         => 1
            ],

            'assembly_type' => [
                'type'              => 'string',
                'description'       => "Type of assembly.",
                'selection'         => [
                    'statutory',
                    'takeover',
                    'ordinary',
                    'extraordinary',
                    'partial'
                ],
                'required'          => true
            ],

            'assembly_item_templates_ids' => [
                'type'            => 'one2many',
                'description'     => "Templates of resolutions used for this assembly.",
                'foreign_object'  => 'realestate\governance\AssemblyItemTemplate',
                'foreign_field'   => 'assembly_template_id'
            ]
        ];
    }
}
