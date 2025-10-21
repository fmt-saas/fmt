<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for Accounts: returns a collection according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'finance\bank\BankAccount'
        ],

        'domain' => [
            'description'   => 'Criterias that results have to match (series of conjunctions)',
            'type'          => 'array',
            'default'       => []
        ],

        'bank_account_iban' => [
            'type'          => 'string',
            'description'   => "Number of the bank account of the Identity, if any.",
            'help'          => "Free text is allowed and number can be partial."
        ],

        'identity_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Identity',
            'description'       => 'Customer identity.'
        ],

        'bank_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\bank\Bank',
            'description'       => "The Bank the account is part of.",
            'domain'            => ['object_class', '=', 'finance\bank\Bank']
        ],

        'bank_account_bic' => [
            'type'              => 'string',
            'description'       => 'The BIC code of the bank related to the organization\'s bank account.',
            'help'          => "Free text is allowed and number can be partial."
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);
/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

//   Add conditions to the domain to consider advanced parameters
$domain = new Domain($params['domain']);


if(isset($params['identity_id'])) {
    $domain->addCondition(new DomainCondition('owner_identity_id', '=', $params['identity_id']));
}


if(isset($params['bank_id'])) {
    $domain->addCondition(new DomainCondition('bank_id', '=', $params['bank_id']));
}

if(isset($params['bank_account_bic']) && strlen($params['bank_account_bic']) > 0) {
    $domain->addCondition(new DomainCondition('bank_account_bic', 'like', '%' . $params['bank_account_bic'] . '%'));
}


$params['domain'] = $domain->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
