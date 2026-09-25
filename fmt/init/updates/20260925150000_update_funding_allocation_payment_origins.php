<?php

use equal\db\DBConnector;

$db = DBConnector::getInstance()->connect();

$db->sendQuery(<<<'SQL'
UPDATE `sale_pay_payment`
SET `payment_origin` = 'funding_allocation'
WHERE `origin_object_class` IN (
    'realestate\\funding\\FundRequestExecution',
    'realestate\\purchase\\accounting\\invoice\\PurchaseInvoice',
    'finance\\accounting\\MiscOperation'
)
SQL);
