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
    private const BANK_STATEMENT_SCHEMA = 'urn:fmt:json-schema:finance:bank-statement';

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
                'usage'             => 'text/plain.medium',
                'description'       => 'Logs of the bank statement import processing.'
            ],

            'has_error' => [
                'type'              => 'boolean',
                'description'       => 'Import status feedback.',
                'default'           => false
            ],

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
            "Warnings: {$summary['warnings']}",
            "Errors: {$summary['errors']}",
            "Statements imported: {$summary['imported']}",
            "Statements rejected (invalid): {$summary['invalid_statements']}",
            "Statements skipped (already imported): {$summary['already_imported']}",
            "Statements skipped (unknown accounts): {$summary['unknown_accounts']}"
        ]);
    }

    /**
     * Remove document processes created during a failed atomic import.
     */
    private static function rollbackDocumentProcesses(array $document_processes_ids, $orm): bool {
        $result = true;

        foreach(array_reverse($document_processes_ids) as $document_process_id) {
            try {
                $documentProcess = DocumentProcess::id($document_process_id)
                    ->read(['document_id', 'document_bank_statement_id'])
                    ->first();

                if(!$documentProcess) {
                    continue;
                }

                if($documentProcess['document_bank_statement_id']) {
                    $orm->delete(BankStatement::getType(), $documentProcess['document_bank_statement_id'], true);
                }
                if($documentProcess['document_id']) {
                    $orm->delete(Document::getType(), $documentProcess['document_id'], true);
                }
                $orm->delete(DocumentProcess::getType(), $document_process_id, true);
            }
            catch(\Throwable $e) {
                $result = false;
                trigger_error(
                    "APP::unable to rollback document process {$document_process_id} after failed bank statement import: {$e->getMessage()}",
                    EQ_REPORT_ERROR
                );
            }
        }

        return $result;
    }

    /**
     * Handle data update (i.e. file upload).
     * This method is used to create the document based on received data, and start the processing.
     */
    protected static function onupdateData($self, $auth, $orm) {
        $self->read(['name', 'data', 'logs', 'has_error']);

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
                'unknown_accounts'  => 0,
                'invalid_statements' => 0,
                'warnings'          => 0,
                'errors'            => 0
            ];

            $has_error = false;
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
                $has_error = true;
                ++$summary['errors'];
                trigger_error(
                    "APP::unable to read bank statement import {$id}: {$e->getMessage()}",
                    EQ_REPORT_WARNING
                );
                $logs[] = "ERR  - Unable to read import {$bankStatementImport['name']}";
                self::id($id)->write([
                    'summary'   => self::computeSummary($summary),
                    'logs'      => implode("\n", $logs),
                    'has_error' => $has_error
                ]);
                continue;
            }

            $summary['files'] = count($files);
            $logs[] = 'INFO - Found ' . count($files) . ' file(s) to process';
            $prepared_statements = [];
            $prepared_statement_keys = [];

            if(count($files) === 0) {
                $has_error = true;
                ++$summary['errors'];
                $logs[] = "ERR  - Invalid import {$bankStatementImport['name']}: no file was found";
            }

            foreach($files as $file) {
                $logs[] = "INFO - Start validating file {$file['name']}";

                try {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if(!in_array($ext, static::ALLOWED_EXTENSIONS)) {
                        $has_error = true;
                        ++$summary['errors'];
                        $logs[] = "ERR  - Invalid file {$file['name']}: unsupported extension '{$ext}'";
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
                        $has_error = true;
                        ++$summary['errors'];
                        $logs[] = "ERR  - Invalid file {$file['name']}: extraction returned an invalid response";
                        continue;
                    }

                    $logs[] = 'INFO - Extracted ' . count($data) . " statement(s) from {$file['name']}";
                    if(count($data) === 0) {
                        $has_error = true;
                        ++$summary['errors'];
                        $logs[] = "ERR  - Invalid file {$file['name']}: no bank statement was found";
                        continue;
                    }

                    $file_name = pathinfo($file['name'], PATHINFO_FILENAME);

                    foreach($data as $i => $statement) {
                        if(!is_array($statement)) {
                            $has_error = true;
                            ++$summary['errors'];
                            ++$summary['invalid_statements'];
                            $logs[] = "ERR  - Invalid statement " . ($i + 1) . " in {$file['name']}: unexpected data structure";
                            continue;
                        }

                        $statement_number = $statement['statement_number'] ?? 'unknown';
                        $validation = \eQual::run('get', 'json-validate', [
                            'json'      => json_encode($statement),
                            'schema_id' => static::BANK_STATEMENT_SCHEMA
                        ]);

                        if(!is_array($validation) || !($validation['result'] ?? false)) {
                            $has_error = true;
                            ++$summary['errors'];
                            ++$summary['invalid_statements'];
                            $logs[] = "ERR  - Invalid statement {$statement_number} (item " . ($i + 1) . ") in {$file['name']}";

                            $validation_errors = is_array($validation) ? ($validation['errors'] ?? []) : [];
                            if(count($validation_errors) === 0) {
                                $logs[] = "ERR  - Validation details are unavailable";
                            }
                            else {
                                foreach($validation_errors as $path => $messages) {
                                    $field = ltrim((string) $path, '/');
                                    $field = $field !== '' ? str_replace('/', '.', $field) : 'statement';
                                    foreach((array) $messages as $message) {
                                        $logs[] = "ERR  - {$field}: {$message}";
                                    }
                                }
                            }
                            continue;
                        }

                        // Validate the related bank account before creating any document process.
                        $iban = strtoupper(preg_replace('/\s+/', '', $statement['account_iban'] ?? ''));
                        $bankAccount = CondominiumBankAccount::search([
                                ['bank_account_iban', '=', $iban],
                                ['is_active', '=', true]
                            ])
                            ->read(['condo_id' => ['is_active']])
                            ->first();

                        if(!$bankAccount || !$bankAccount['condo_id'] || !$bankAccount['condo_id']['is_active']) {
                            ++$summary['unknown_accounts'];
                            ++$summary['warnings'];
                            $has_error = true;
                            $logs[] = "WARN - Statement {$statement_number} cannot be imported: no active condominium bank account for IBAN {$iban}";
                            continue;
                        }

                        // Validate duplicates before creating any document process.
                        $statement_key = implode('|', [
                            $iban,
                            $statement['statement_number'],
                            $statement['opening_date'],
                            round($statement['opening_balance'], 2),
                            $statement['closing_date'],
                            round($statement['closing_balance'], 2)
                        ]);
                        if(isset($prepared_statement_keys[$statement_key])) {
                            ++$summary['already_imported'];
                            $has_error = true;
                            ++$summary['warnings'];
                            $logs[] = "WARN - Statement {$statement_number} cannot be imported: duplicate statement in the same import";
                            continue;
                        }

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
                            $has_error = true;
                            ++$summary['warnings'];
                            $logs[] = "WARN - Statement {$statement_number} cannot be imported: already imported as bank statement {$existingBankStatement['id']}";
                            continue;
                        }

                        $prepared_statement_keys[$statement_key] = true;
                        $prepared_statements[] = [
                            'name'             => $file_name . '(' . ($i + 1) . ').xlsx',
                            'file_name'        => $file['name'],
                            'statement_number' => $statement_number,
                            'binary'           => self::computeXlsxBinaryFromStatement($statement)
                        ];
                    }

                    $logs[] = "INFO - Finished validating file {$file['name']}";
                }
                catch(\Exception $e) {
                    $has_error = true;
                    ++$summary['errors'];
                    trigger_error(
                        "APP::error while validating bank statement file {$file['name']}: {$e->getMessage()}",
                        EQ_REPORT_WARNING
                    );
                    $logs[] = "ERR  - Invalid bank statement file {$file['name']}";
                }
            }

            if($has_error) {
                $logs[] = "ERR  - Import aborted during validation: no document process was created";
                $logs[] = "INFO - Finished bank statement import {$id} with errors";
                self::id($id)->write([
                    'summary'   => self::computeSummary($summary),
                    'logs'      => implode("\n", $logs),
                    'has_error' => true
                ]);
                continue;
            }

            $created_document_processes_ids = [];
            foreach($prepared_statements as $prepared_statement) {
                try {
                    $documentProcess = DocumentProcess::create([
                            'name'                  => $prepared_statement['name'],
                            'document_type_id'      => $documentType['id'],
                            'assigned_employee_id'  => $user['employee_id']
                        ])
                        ->first();

                    if(!$documentProcess) {
                        throw new \Exception('document_process_creation_failed', EQ_ERROR_UNKNOWN);
                    }

                    $created_document_processes_ids[] = $documentProcess['id'];
                    DocumentProcess::id($documentProcess['id'])->update(['data' => $prepared_statement['binary']]);

                    ++$summary['imported'];
                    $logs[] = "INFO - Processed statement {$prepared_statement['statement_number']} from {$prepared_statement['file_name']} as document process {$documentProcess['id']}";
                }
                catch(\Exception $e) {
                    $has_error = true;
                    ++$summary['errors'];
                    trigger_error(
                        "APP::unable to create document process for bank statement import {$id}: {$e->getMessage()}",
                        EQ_REPORT_ERROR
                    );
                    $logs[] = "ERR  - Unable to create all bank statement document processes";
                    break;
                }
            }

            if($has_error) {
                $rollback_success = self::rollbackDocumentProcesses($created_document_processes_ids, $orm);
                if($rollback_success) {
                    $summary['imported'] = 0;
                    $logs[] = "INFO - Rolled back all document processes created by this import";
                }
                else {
                    ++$summary['errors'];
                    $logs[] = "ERR  - Automatic rollback failed; manual cleanup is required";
                }

                $logs[] = "INFO - Finished bank statement import {$id} with errors";
                self::id($id)->write([
                    'summary'   => self::computeSummary($summary),
                    'logs'      => implode("\n", $logs),
                    'has_error' => true
                ]);
                continue;
            }

            $logs[] = "INFO - Finished bank statement import {$id}";
            // Remove current import object after successful import.
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
