<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace documents\typing;
use realestate\purchase\accounting\invoice\InvoiceLine;

class DocumentInvoiceLine extends InvoiceLine {

    public function getTable() {
        return 'documents_typing_documentinvoiceline';
    }

    public static function getName() {
        return "Document Purchase Invoice Line";
    }

    public static function getColumns() {

        return [

        ];
    }

}
