<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace documents\navigation;

use documents\Document;
use equal\orm\Model;

class Node extends Model {

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium'
            ],

            'name' => [
                'type'              => 'string',
                'required'          => true,
                'description'       => 'Arbitrary name of the node.'
            ],

            'code' => [
                'type'              => 'string',
                'usage'             => 'text/plain:25',
                'description'       => 'Code for identifying the folder (for auto assignments).',
                'visible'           => ['node_type', '=', 'folder']
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short description folder intended use.',
                'visible'           => ['node_type', '=', 'folder']
            ],

            'node_type' => [
                'type'              => 'string',
                'selection'         => ['folder', 'document'],
                'description'       => 'Content type of the document (from data).',
                'default'           => 'folder'
            ],

            'nodes_count' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Number of items contained by the node.',
                'store'             => true,
                'function'          => 'calcNodesCount'
            ],

            'parent_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\navigation\Node',
                'description'       => 'Parent node of the node.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'ondelete'          => 'null',
                'onupdate'          => 'onupdateParentId'
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'targeted document of the node.',
                'visible'           => ['node_type', '=', 'document'],
                'ondelete'          => 'cascade',
                'onupdate'          => 'onupdateDocumentId',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'document_link' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url.relative',
                'description'       => 'URL for visualizing the document.',
                'function'          => 'calcLink',
                'store'             => true,
                'readonly'          => true,
                'visible'           => ['node_type', '=', 'document']
            ],

            'nodes_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\navigation\Node',
                'foreign_field'     => 'parent_id',
                'description'       => 'Nodes having the node as parent.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'node_visibility' => [
                'type'              => 'string',
                'selection'         => [
                    'public',       // visible to all condo owners + syndic
                    'protected',    // visible only to syndic
                    'private'       // visible only a single owner (to which the document is linked) + syndic
                ],
                'default'           => 'public',
                'description'       => 'Defines who can see the node.',
                'help'              => 'This field is synchronized with the node and is automatically updated when the parent node visibility changes. 
                    If this is a child node, the `document_visibility` of the corresponding document is updated.
                    If this is a parent node, all descendant nodes are updated (cascade).',
                'onupdate'          => 'onupdateNodeVisibility'
            ],

            'is_system' => [
                'type'              => 'boolean',
                'description'       => 'System folders cannot be changed (by anyone).',
                'visible'           => ['node_type', '=', 'folder'],
                'default'           => false
            ]

        ];
    }

    protected static function onupdateNodeVisibility($self) {
        $self->read(['node_visibility', 'node_type', 'document_id', 'nodes_ids']);
        foreach($self as $id => $node) {
            if($node['node_type'] === 'folder') {
                // #memo - recursion is prevented by the ORM
                $children_ids = self::computeChildrenNodesIds($id);
                $children = self::ids($children_ids)
                    ->update(['node_visibility' => $node['node_visibility']])
                    ->read(['id', 'node_type', 'document_id']);

                foreach($children as $child) {
                    if($child['node_type'] === 'document' && $child['document_id']) {
                        // update document visibility
                        Document::id($child['document_id'])->update(['document_visibility' => $node['node_visibility']]);
                    }
                }
            }
            elseif($node['node_type'] === 'document' && $node['document_id']) {
                // update document visibility
                Document::id($node['document_id'])->update(['document_visibility' => $node['node_visibility']]);
            }
        }
    }

    private static function computeChildrenNodesIds($id) {
        $visited = [$id => true];
        $stack = [$id];

        while(!empty($stack)) {
            $current_id = array_pop($stack);

            $node = self::id($current_id)->read(['id', 'node_type', 'nodes_ids'])->first();
            if (!$node || $node['node_type'] === 'document') {
                continue;
            }

            foreach ($node['nodes_ids'] ?? [] as $child_id) {
                if($child_id && !isset($visited[$child_id])) {
                    $visited[$child_id] = true;
                    $stack[] = $child_id;
                }
            }
        }
        // remove the root node itself
        unset($visited[$id]);
        return array_keys($visited);
    }

    protected static function calcNodesCount($self) {
        $result = [];

        // load parent_id to identify root nodes
        $self->read(['id', 'parent_id']);

        $root_nodes_ids = [];
        foreach($self as $id => $node) {
            if(!$node['parent_id']) {
                $root_nodes_ids[] = $id;
            }
        }

        foreach($root_nodes_ids as $root_node_id) {
            // Stack for DFS: each entry is [id, expanded_flag]
            $stack = [[$root_node_id, false]];
            // stores already computed nodes_count
            $map_cache = [];

            while(!empty($stack)) {
                [$current_id, $expanded] = array_pop($stack);

                if(isset($map_cache[$current_id])) {
                    continue;
                }

                $node = self::id($current_id)->read(['id', 'node_type', 'nodes_ids'])->first();

                if($node['node_type'] === 'document') {
                    $map_cache[$current_id] = 1;
                    continue;
                }

                $child_ids = $node['nodes_ids'] ?? [];

                if(!$expanded) {
                    // Post-order traversal: push children first
                    $stack[] = [$current_id, true];
                    foreach($child_ids as $child_id) {
                        if(!isset($map_cache[$child_id])) {
                            $stack[] = [$child_id, false];
                        }
                    }
                }
                else {
                    // All children have been processed
                    $count = 0;
                    foreach($child_ids as $child_id) {
                        $count += $map_cache[$child_id] ?? 0;
                    }
                    $map_cache[$current_id] = $count;
                    self::id($current_id)->update(['nodes_count' => $count]);
                }
            }
            // return a result for root nodes
            $result[$root_node_id] = $map_cache[$root_node_id];
        }

        return $result;
    }

    public static function onupdateParentId($self) {
        $result = [];

        $self->read(['parent_id']);

        foreach($self as $id => $node) {
            $current = $node;

            // climb up to the root
            while($current['parent_id']) {
                $current = self::id($current['parent_id'])->read(['parent_id'])->first();
            }

            // current is a root node
            self::id($current['id'])->update(['nodes_count' => null]);
        }

        return $result;
    }

    public static function calcLink($self) {
        $result = [];
        $self->read(['document_id']);
        foreach($self as $id => $node) {
            if($node['document_id']) {
                $result[$id] = '/document/' . $node['document_id'];
            }
        }
        return $result;
    }

    protected static function computeName($id) {
        $result = '';
        $node = self::id($id)->read(['document_id' => ['name', 'created']])->first();
        if($node && $node['document_id']) {
            $result = date('Ymd', $node['document_id']['created']) . ' - ' . $node['document_id']['name'];
        }
        return $result;
    }

    public static function onupdateDocumentId($self) {
        // #todo -  use translations for this mapping
        static $map_document_types_labels = [
            'invoice'               => 'Facture',
            'credit_note'           => 'Note de crédit',
            'quote'                 => 'Devis',
            'purchase_order'        => 'Bon de commande',
            'delivery_note'         => 'Bon de livraison',
            'incident_report'       => 'Rapport de sinistre',
            'maintenance_report'    => 'Rapport d\'entretien',
            'contract'              => 'Contrat fournisseur',
            'certificate'           => 'Attestation',
            'terms_and_conditions'  => 'Conditions générales',
            'reconciliation_report' => 'Relevé de consommations',
            'fund_request'          => 'Appel de fonds',
            'expense_statement'     => 'État des dépenses',
            'bank_statement'        => 'Relevé bancaire',
            'legal_document'        => 'Document juridique',
            'correspondence'        => 'Courrier',
            'supporting_document'   => 'Pièce justificative',
            'internal_memo'         => 'PV',
        ];

        $self->read(['document_id' => ['name', 'document_type', 'suppliership_id' => ['name'], 'ownership_id' => ['name']]]);
        foreach($self as $id => $node) {
            $description = '';

            if($node['document_id']) {

                if(isset($map_document_types_labels[$node['document_id']['document_type']])) {
                    $description .= $map_document_types_labels[$node['document_id']['document_type']];
                }

                if($node['document_id']['ownership_id']) {
                    $description .= ' - ' . $node['document_id']['ownership_id']['name'];
                }

                if($node['document_id']['suppliership_id']) {
                    $description .= ' - ' . $node['document_id']['suppliership_id']['name'];
                }

                self::id($id)->update(['name' => self::computeName($id), 'description' => $description]);
            }
        }
    }

    public static function onchange($event, $values) {
        $result = [];
        if(array_key_exists('document_id', $event)) {
            if(!is_null($event['document_id'])) {
                $result['name'] = self::computeName($values['id']);
            }
            else {
                $result['name'] = '';
            }
        }
        if(isset($event['node_type']) && $event['node_type'] == 'folder') {
            $result['document_id'] = null;
        }
        return $result;
    }

    public function getUnique(): array {
        return [
            ['condo_id', 'code']
        ];
    }

}
