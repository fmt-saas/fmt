<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace finance\bank;

use documents\Document;
use documents\DocumentType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;
use documents\processing\DocumentProcess;
use equal\orm\Model;
use identity\User;

class BankStatementImport extends Model {

    protected const ALLOWED_EXTENSIONS = ['cod', 'coda', 'txt', 'csv', 'xls', 'xlsx'];

    public static function getName() {
        return 'Bank statement import';
    }

    public static function getDescription() {
        return 'Bank Statement Import is a virtual entity used to upload and process bank statement files, either as single files or grouped within ZIP archives.
            Supported formats include CODA (.cod, .coda), text (.txt), CSV (.csv), and Excel (.xls, .xlsx).
            Each file is parsed to extract one or multiple statements, which are then converted into structured documents for further processing.
            Import records are temporary and automatically removed after successful processing.';
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Display name of bank statement.',
            ],

            'data' => [
                'type'              => 'binary',
                'description'       => 'Raw binary data of the uploaded document',
                'help'              => 'This field is meant to be used for the subsequent document creation, and is emptied once the document creation is confirmed.',
                'onupdate'          => 'onupdateData'
            ]

        ];
    }

    private static function extractFilesFromBinary(string $binary, string $name): array {
        $files = [];

        // ZIP archive detection
        $is_zip = substr($binary, 0, 2) === "PK";

        if(!$is_zip) {
            return [[
                'name' => $name,
                'data' => $binary
            ]];

        }

        if(!class_exists(\ZipArchive::class)) {
            throw new \Exception('zip_extension_missing', EQ_ERROR_INVALID_CONFIG);
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'zip_');
        file_put_contents($tmpZip, $binary);

        $zip = new \ZipArchive();
        if($zip->open($tmpZip) === true) {
            for($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);

                // ignore folders
                if(substr($stat['name'], -1) === '/') {
                    continue;
                }

                $content = $zip->getFromIndex($i);

                if(!$content) {
                    continue;
                }

                $files[] = [
                    'name' => basename($stat['name']),
                    'data' => $content
                ];
            }
            $zip->close();
        }

        unlink($tmpZip);

        return $files;
    }

    /**
     * Handle data update (i.e. file upload).
     * This method is used to create the document based on received data, and start the processing.
     */
    protected static function onupdateData($self, $auth) {
        $self->read(['name', 'data']);
        $documentType = DocumentType::search(['code', '=', 'bank_statement'])->first();
        $user = User::id($auth->userId())->read(['employee_id'])->first();

        foreach($self as $id => $bankStatementImport) {
            $files = self::extractFilesFromBinary(
                $bankStatementImport['data'],
                $bankStatementImport['name']
            );

            foreach($files as $file) {
                try {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if(!in_array($ext, static::ALLOWED_EXTENSIONS)) {
                        // #todo - dispatch error
                        continue;
                    }

                    // create a temporary import Document holding all statements
                    $document = Document::create([
                            'name'      => $file['name'],
                            'data'      => $file['data'],
                            'is_origin' => true
                        ])
                        ->first();
                    // extract data independently from the document content-type
                    $data = \eQual::run('get', 'documents_processing_BankStatement_extract', ['document_id' => $document['id']]);

                    if(!is_array($data)) {
                        // remove original document
                        Document::id($document['id'])->delete(true);
                        // #todo - dispatch error
                        continue;
                    }
                    $file_name = pathinfo($file['name'], PATHINFO_FILENAME);

                    foreach($data as $i => $statement) {
                        $binary = self::computeXlsxBinaryFromStatement($statement);
                        // this will trigger the creation of the Document and the Document Processing, which should not interrupt the import even if it fails
                        try {
                            $documentProcess = DocumentProcess::create([
                                    'name'                  => $file_name . '(' . ($i+1) . ').' . 'xlsx',
                                    'document_type_id'      => $documentType['id'],
                                    'assigned_employee_id'  => $user['employee_id']
                                ])
                                ->update(['data' => $binary])
                                ->read(['document_id'])
                                ->first();

                            if($documentProcess && $documentProcess['document_id']) {
                                // attach original document to the one being processed
                                Document::id($documentProcess['document_id'])->update(['origin_document_id' => $document['id']]);
                            }
                        }
                        catch(\Exception $e) {
                            // ignore (outputs are in logs)
                        }
                    }
                }
                catch(\Exception $e) {
                    // #todo - dispatch error to let user know that a file was not imported
                    // keep on processing other files
                }
            }
            // remove current object (pointless after successful import)
            self::id($id)->delete(true);
        }
    }

    /**
     * BankStatementImport is used to upload and create a new Document.
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

        // columns from standard ISABEL XLS exports
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

        $writer = new Xlsx($spreadsheet);
        $stream = fopen('php://memory', 'w+');
        $writer->save($stream);
        rewind($stream);
        $binary = stream_get_contents($stream);
        fclose($stream);

        return $binary;
    }

    private static function convertToExcelDate($date_str) {
        if(!$date_str) {
            return null;
        }

        $timestamp = strtotime($date_str);
        if($timestamp === false) {
            return null;
        }

        $dt = (new \DateTime())->setTimestamp($timestamp);
        return XlsDate::PHPToExcel($dt);
    }
}
