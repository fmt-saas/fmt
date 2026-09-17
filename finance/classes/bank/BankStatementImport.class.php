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
use fmt\setting\Setting;
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
            ],

            'summary' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Summary of the bank statement import processing.'
            ],

            'logs' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Logs of the bank statement import processing.'
            ]

        ];
    }

    private static function extractFilesFromBinary(string $binary, string $name): array {
        $files = [];

        // ZIP archive detection
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if($ext !== 'zip') {
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
     * Format the main import counters for display in the result form.
     */
    private static function computeSummary(array $summary): string {
        return implode("\n", [
            "Files in import: {$summary['files']}",
            "Statements imported: {$summary['imported']}",
            "Statements skipped (already imported): {$summary['already_imported']}",
            "Statements skipped (unknown accounts): {$summary['unknown_accounts']}"
        ]);
    }

    /**
     * Handle data update (i.e. file upload).
     * This method is used to create the document based on received data, and start the processing.
     */
    protected static function onupdateData($self, $auth) {
        $self->read(['name', 'data', 'logs']);

        $documentType = DocumentType::search(['code', '=', 'bank_statement'])->first();
        $user = User::id($auth->userId())->read(['employee_id'])->first();

        // make sure there is no filter for current user on condo_id
        Setting::assert_value('fmt', 'organization', 'user.condo_id', null, ['user_id' => $user['id']]);
        Setting::set_value('fmt', 'organization', 'user.condo_id', null, ['user_id' => $user['id']]);

        // in case binary is an archive, all files contained inside are returned
        foreach($self as $id => $bankStatementImport) {
            $summary = [
                'files'             => 0,
                'imported'          => 0,
                'already_imported'  => 0,
                'unknown_accounts'  => 0
            ];

            $logs = [];
            if(isset($bankStatementImport['logs']) && strlen($bankStatementImport['logs']) > 0) {
                $logs = explode("\n", $bankStatementImport['logs']);
            }

            $logs[] = "INFO - Start bank statement import {$id} for {$bankStatementImport['name']}";

            try {
                $files = self::extractFilesFromBinary(
                    $bankStatementImport['data'],
                    $bankStatementImport['name']
                );
            }
            catch(\Exception $e) {
                $logs[] = "ERR  - Unable to read {$bankStatementImport['name']}: {$e->getMessage()}";
                self::id($id)->write([
                    'summary' => self::computeSummary($summary),
                    'logs'    => implode("\n", $logs)
                ]);
                throw $e;
            }

            $summary['files'] = count($files);
            $logs[] = 'INFO - Found ' . count($files) . ' file(s) to process';

            foreach($files as $file) {
                $logs[] = "INFO - Start processing file {$file['name']}";

                try {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if(!in_array($ext, static::ALLOWED_EXTENSIONS)) {
                        $logs[] = "WARN - Skipped file {$file['name']}: unsupported extension '{$ext}'";
                        continue;
                    }

                    // create a temporary import Document holding all statements
                    $document = Document::create([
                            'name' => $file['name'],
                            'data' => $file['data']
                        ])
                        ->first();
                    $logs[] = "INFO - Created temporary document {$document['id']} for {$file['name']}";

                    // extract data independently from the document content-type
                    try {
                        $data = \eQual::run('get', 'documents_processing_BankStatement_extract', ['document_id' => $document['id']]);
                    }
                    finally {
                        Document::id($document['id'])->delete(true);
                        $logs[] = "INFO - Deleted temporary document {$document['id']}";
                    }

                    if(!is_array($data)) {
                        $logs[] = "WARN - Skipped file {$file['name']}: extraction returned no statement list";
                        continue;
                    }

                    $logs[] = 'INFO - Extracted ' . count($data) . " statement(s) from {$file['name']}";
                    $file_name = pathinfo($file['name'], PATHINFO_FILENAME);

                    foreach($data as $i => $statement) {
                        $statement_number = $statement['statement_number'] ?? 'unknown';
                        $logs[] = "INFO - Processing statement {$statement_number} from {$file['name']}";

                        // ignore statement if relating to an irrelevant IBAN
                        $iban = strtoupper(preg_replace('/\s+/', '', $statement['account_iban'] ?? ''));
                        $bankAccount = CondominiumBankAccount::search([
                                ['bank_account_iban', '=', $iban],
                                ['is_active', '=', true]
                            ])
                            ->read(['condo_id' => ['is_active']])
                            ->first();

                        if(!$bankAccount || !$bankAccount['condo_id'] || !$bankAccount['condo_id']['is_active']) {
                            ++$summary['unknown_accounts'];
                            $logs[] = "WARN - Skipped statement {$statement_number}: no active condominium bank account for IBAN {$iban}";
                            continue;
                        }

                        // ignore statement if already imported
                        $existingBankStatement = BankStatement::search([
                                ['bank_account_iban', '=', $iban],
                                ['statement_number', '=', $statement['statement_number']],
                                ['opening_date', '=', strtotime($statement['opening_date'])],
                                ['opening_balance', '=', round($statement['opening_balance'], 2)],
                                ['closing_date', '=', strtotime($statement['closing_date'])],
                                ['closing_balance', '=', round($statement['closing_balance'], 2)]
                            ])
                            ->first();

                        if($existingBankStatement) {
                            ++$summary['already_imported'];
                            $logs[] = "WARN - Skipped statement {$statement_number}: already imported as bank statement {$existingBankStatement['id']}";
                            continue;
                        }

                        $binary = self::computeXlsxBinaryFromStatement($statement);
                        // this will trigger the creation of the Document and the Document Processing, which should not interrupt the import even if it fails
                        try {
                            $documentProcess = DocumentProcess::create([
                                    'name'                  => $file_name . '(' . ($i+1) . ').' . 'xlsx',
                                    'document_type_id'      => $documentType['id'],
                                    'assigned_employee_id'  => $user['employee_id']
                                ])
                                ->first();
                            $logs[] = "INFO - Created document process {$documentProcess['id']} for statement {$statement_number}";

                            DocumentProcess::id($documentProcess['id'])->update(['data' => $binary]);
                            ++$summary['imported'];
                            $logs[] = "INFO - Submitted statement {$statement_number} to document process {$documentProcess['id']}";
                        }
                        catch(\Exception $e) {
                            $logs[] = "ERR  - Unable to process statement {$statement_number}: {$e->getMessage()}";
                        }
                    }

                    $logs[] = "INFO - Finished processing file {$file['name']}";
                }
                catch(\Exception $e) {
                    $logs[] = "ERR  - Error while processing file {$file['name']}: {$e->getMessage()}";
                    // keep on processing other files
                }
            }

            $logs[] = "INFO - Finished bank statement import {$id}";
            self::id($id)->write([
                'data'    => null,
                'summary' => self::computeSummary($summary),
                'logs'    => implode("\n", $logs)
            ]);
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
