<?php

use equal\db\DBConnector;

$db = DBConnector::getInstance()->connect();

$db->sendQuery(<<<'SQL'
ALTER TABLE `finance_bank_bankstatementimport`
MODIFY COLUMN `logs` MEDIUMTEXT
SQL);
