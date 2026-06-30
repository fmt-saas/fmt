<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\db\DBConnector;

[$params, $providers] = eQual::announce([
    'description'   => "Returns the size of the db storage.",
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

if(constant('DB_DBMS') !== 'MYSQL') {
    throw new Exception('wrong_dbms', EQ_ERROR_INVALID_CONFIG);
}

$db = DBConnector::getInstance(constant('DB_HOST'), constant('DB_PORT'), constant('DB_NAME'), constant('DB_USER'), constant('DB_PASSWORD'), constant('DB_DBMS'))->connect();
if(!$db) {
    throw new Exception('missing_database', EQ_ERROR_INVALID_CONFIG);
}

$db_name = constant('DB_NAME');

$res = $db->sendQuery("SELECT table_schema AS `database`, SUM(data_length + index_length) AS size_bytes FROM information_schema.tables WHERE table_schema = '$db_name' GROUP BY table_schema");
$row = mysqli_fetch_array($res, MYSQLI_ASSOC);

$db_size = $row['size_bytes'];

$db->disconnect();

$result = [
    'value'     => $db_size,
    'unit'      => 'bytes',
    'logs'      => [],
    'errors'    => [],
    'warnings'  => []
];

$context
    ->httpResponse()
    ->body($result)
    ->send();
