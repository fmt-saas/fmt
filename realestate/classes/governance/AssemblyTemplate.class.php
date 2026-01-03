<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
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
                'usage'             => 'text/html',
                'description'       => "Heading text of the assembly call.",
                'required'          => false
            ],

            'heading_text_minutes' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => "Heading text of the assembly minutes.",
                'required'          => false
            ],

            'closing_text_call' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => "Closing text of the assembly call.",
                'required'          => false
            ],

            'closing_text_minutes' => [
                'type'              => 'string',
                'usage'             => 'text/html',
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
                    'constitutive',     // First general meeting of a new condominium association – formal activation (property manager, budget, funds…)
                    'statutory',        // Mandatory annual general meeting (art. 3.87 §1 C.C.)
                    'extraordinary',    // Extraordinary general meeting convened outside the cycle for specific decision(s)
                    'recovery',         // General meeting for recovery after blockage, deficiency, or change of property manager
                    'special',          // Special cases: judicial general meeting, by block, by section, undivided ownership, etc.
                    'council_meeting'   // Meeting of the Condominium Council (non-decisional except with mandate)
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

    public static function getActions() {
        return [
            'refresh_items_order' => [
                'description'   => 'Force a re-compute of the order field of the assembly items.',
                'policies'      => [/* */],
                'function'      => 'doRefreshItemsOrder'
            ]
        ];
    }

    protected static function doRefreshItemsOrder($self) {
        $self->read(['assembly_item_templates_ids' => ['has_parent_group']]);
        foreach($self as $id => $assembly) {
            $order = 1;
            foreach($assembly['assembly_item_templates_ids'] as $assembly_item_id => $assemblyItem) {
                if($assemblyItem['has_parent_group']) {
                    continue;
                }
                AssemblyItemTemplate::id($assembly_item_id)->update(['order' => $order]);
                ++$order;
            }
        }
    }

}
