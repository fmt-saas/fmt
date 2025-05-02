<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

[$params, $providers] = eQual::announce([
    'description'   => 'Return raw data (with original MIME) of a document identified by given hash.',
    'params'        => [
        'condo_id' => [
            'type'              => 'many2one',
            'description'       => "The condominium the property lot belongs to.",
            'foreign_object'    => 'realestate\property\Condominium',
            'required'          => true
        ],
        'data' => [
            'type'              => 'binary',
            'required'          => true
        ],
        'name' => [
            'type'              => 'string',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'        => 'public'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'FMT_API_URL_EDMS'],
    'providers'     => ['context', 'orm', 'auth']
]);

['orm' => $orm] = $providers;

// aller rechercher les entités créées sur l'instance Globale depuis la dernière syncrho
$url = constant('FMT_API_URL_EDMS');
/*
liste des entités protégées

$
*/

$map_entities = [
    'identity\Identity'                     => 'protected',
    'identity\User'                         => 'protected',
    'purchase\supplier\Supplier'            => 'protected',
    'realestate\management\ManagingAgent'   => 'protected',
    'realestate\property\Condominium'       => 'protected',
];





