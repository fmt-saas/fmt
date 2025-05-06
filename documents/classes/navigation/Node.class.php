<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace documents\navigation;

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

            'parent_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\navigation\Node',
                'description'       => 'Parent node of the node.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'targeted document of the node.',
                'visible'           => ['node_type', '=', 'document'],
                'onupdate'          => 'onupdateDocumentId',
                'domain'            => ['condo_id', '=', 'object.condo_id']
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
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ]

        ];
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
        $node = self::id($id)->read(['document_id' => ['name']])->first();
        if($node) {
            $result = $node['document_id']['name'];
        }
        return $result;
    }

    public static function onupdateDocumentId($self) {
        static $map_document_types_labels = [
            'invoice'               => 'Facture',
            'credit_note'           => 'Note de crédit',
            'quote'                 => 'Devis',
            'purchase_order'        => 'Bon de commande',
            'delivery_note'         => 'Bon de livraison',
            'incident_report'       => 'Rapport de sinistre',
            'maintenance_report'    => 'Rapport d\'entretien',
            'contract'              => 'Contrats fournisseurs',
            'certificate'           => 'Attestations',
            'terms_and_conditions'  => 'Conditions générales',
            'reconciliation_report' => 'Relevés de consommations',
            'fund_request'          => 'Appel de fonds',
            'expense_statement'     => 'État des dépenses',
            'bank_statement'        => 'Relevés bancaires',
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

                if($node['ownership_id']) {
                    $description .= ' - ' . $node['ownership_id']['name'];
                }

                if($node['suppliership_id']) {
                    $description .= ' - ' . $node['suppliership_id']['name'];
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
