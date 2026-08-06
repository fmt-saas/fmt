<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use documents\Document;

[$params, $providers] = eQual::announce([
    'description'   => "Returns the size of the storage used, by EDMS documents storage, for metering use.",
    'params'        => [],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$documents = Document::search(['deleted', 'in', [true, false]])
    ->read(['content_size'])
    ->get();

$documents_contents_size = 0;
foreach($documents as $document) {
    $documents_contents_size += $document['content_size'];
}

$result = [
    'value'     => $documents_contents_size,
    'unit'      => 'bytes',
    'logs'      => [],
    'errors'    => [],
    'warnings'  => []
];

$context
    ->httpResponse()
    ->body($result)
    ->send();
