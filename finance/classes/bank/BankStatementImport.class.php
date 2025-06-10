<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace finance\bank;

use documents\Document;
use documents\DocumentType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;
use documents\processing\DocumentProcess;
use equal\orm\Model;


class BankStatementImport extends Model {

    public static function getName() {
        return 'Bank statement import';
    }

    public static function getDescription() {
        return 'Bank Statement Import is a virtual Entity for allowing import of grouped Bank Statements. These imports are meant to be removed upon successful processing.';
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Display name of bank statement.',
                'function'          => 'calcName',
                'store'             => true
            ],

            'data' => [
                'type'              => 'binary',
                'description'       => 'Raw binary data of the uploaded document',
                'help'              => 'This field is meant to be used for the subsequent document creation, and is emptied once the document creation is confirmed.',
                'onupdate'          => 'onupdateData'
            ]

        ];
    }

    /**
     * Handle data update (i.e. file upload).
     * This method is used to create the document based on received data, and start the processing.
     */
    public static function onupdateData($self) {
        $self->read(['name', 'data']);
        $documentType = DocumentType::search(['code', '=', 'bank_statement'])->first();

        foreach($self as $id => $bankStatementImport) {
            // create a temporary import Document holding all statements
            $document = Document::create(['name' => $bankStatementImport['name'], 'data' => $bankStatementImport['data']])->first();
            $data = \eQual::run('get', 'documents_processing_bankStatement_extract', ['document_id' => $document['id']]);

            if(!is_array($data)) {
                throw new \Exception('invalid_data', EQ_ERROR_INVALID_PARAM);
            }
            $file_name = pathinfo($bankStatementImport['name'], PATHINFO_FILENAME);
            $extension = pathinfo($bankStatementImport['name'], PATHINFO_EXTENSION);

            foreach($data as $i => $statement) {
                $binary = self::computeXlsxBinaryFromStatement($statement);
                // this will trigger the creation of the document and the auto processing, which might fail without interrupting the import
                try {
                    DocumentProcess::create(['name' => $file_name . '(' . ($i+1) . ').' . $extension, 'document_type_id' => $documentType['id']])
                        ->update(['data' => $binary])
                        ->first();
                }
                catch(\Exception $e) {
                    // ignore (outputs are in logs)
                }
            }
            Document::id($document['id'])->delete();
            self::id($id)->delete(true);
        }
    }

    /**
     * DocumentProcess is used to upload and create a new Document.
     * We rely on the same strategy than regular Document upload, by receiving document meta from UI with onchange event.
     */
    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['data']['name'])) {
            $result['name'] = $event['data']['name'];
        }

        return $result;
    }

    /**
     * Generate a XLSX binary from an array representation of a Bank Statement.
     *
     */
    private static function computeXlsxBinaryFromStatement(array $statement): string {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Colonnes fixes, en cohérence avec le mapping d’entrée
        $headers = [
                'Account',
                'Account holder',
                'Bank',
                'Account type',
                'Bic',
                'Statement number',
                'Statement currency',
                'Opening balance date',
                'Opening balance',
                'Closing balance date',
                'Closing balance',
                'Closing available balance',
                'Entry date',
                'Value date',
                'Transaction amount',
                'Transaction currency',
                'Transaction type',
                'Client reference',
                'Structured Reference',
                'Unstructured Reference',
                'Bank reference',
                'Counterparty name',
                'Counterparty account',
                'Counterparty bank BIC',
                'Counterparty data',
                'Transaction message',
                'Sequence number',
                'Reception Date/Time',
                'stFreeMessage'
            ];

        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($statement['transactions'] as $transaction) {
            $data = [
                $statement['account_iban'] ?? '',
                $statement['account_holder'] ?? '',
                '', // Bank name is not present in JSON
                $statement['account_type'] ?? '',
                $statement['bank_bic'] ?? '',
                $statement['statement_number'] ?? '',
                $statement['statement_currency'] ?? '',
                self::convertToExcelDate($statement['opening_date'] ?? ''),
                $statement['opening_balance'] ?? '',
                self::convertToExcelDate($statement['closing_date'] ?? ''),
                $statement['closing_balance'] ?? '',
                '', // Closing available balance not present
                self::convertToExcelDate($transaction['entry_date'] ?? ''),
                self::convertToExcelDate($transaction['value_date'] ?? ''),
                $transaction['amount'] ?? '',
                $transaction['currency'] ?? '',
                $transaction['transaction_type'] ?? '',
                $transaction['client_reference'] ?? '',
                $transaction['structured_reference'] ?? '',
                $transaction['unstructured_reference'] ?? '',
                $transaction['bank_reference'] ?? '',
                $transaction['counterparty_name'] ?? '',
                $transaction['counterparty_iban'] ?? '',
                $transaction['counterparty_bic'] ?? '',
                $transaction['counterparty_details'] ?? '',
                $transaction['transaction_message'] ?? '',
                $transaction['sequence_number'] ?? '',
                self::convertToExcelDate($transaction['received_at'] ?? ''),
                '', // stFreeMessage
            ];

            foreach($data as $col => $value) {
                $cell = $sheet->getCellByColumnAndRow($col + 1, $row);
                $cell->setValue($value);

                // Appliquer un format de date si la colonne correspond
                if (in_array($col, [7, 9, 12, 13, 27])) {
                    $sheet->getStyleByColumnAndRow($col + 1, $row)
                        ->getNumberFormat()
                        ->setFormatCode('yyyy-mm-dd');
                }
            }
            $row++;
        }

        // Écriture en mémoire
        $writer = new Xlsx($spreadsheet);
        $stream = fopen('php://memory', 'w+');
        $writer->save($stream);
        rewind($stream);
        $binary = stream_get_contents($stream);
        fclose($stream);

        return $binary;
    }

    private static function convertToExcelDate($date_str) {
        $timestamp = strtotime($date_str);
        $dt = (new \DateTime())->setTimestamp($timestamp);
        return XlsDate::PHPToExcel($dt);
    }

}