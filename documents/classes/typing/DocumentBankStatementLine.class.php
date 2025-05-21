<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace documents\typing;
use finance\bank\BankStatementLine;

class DocumentBankStatementLine extends BankStatementLine {

    public function getTable() {
        return 'documents_typing_documentbankstatementline';
    }

    public static function getName() {
        return "Document BankStatement Line";
    }

    public static function getColumns() {

        return [

        ];
    }

}
