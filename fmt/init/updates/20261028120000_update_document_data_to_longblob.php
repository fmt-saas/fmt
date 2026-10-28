<?php

use equal\db\DBConnector;

$db = DBConnector::getInstance()->connect();

$db->sendQuery(<<<'SQL'
ALTER TABLE `documents_document`
MODIFY COLUMN `data` LONGBLOB
SQL);
