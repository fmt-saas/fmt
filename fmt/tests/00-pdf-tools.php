<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

$fixtures_path = EQ_BASEDIR.'/packages/fmt/tests/fixtures';
$first_pdf = $fixtures_path.'/pdf-tools-first.pdf';
$second_pdf = $fixtures_path.'/pdf-tools-second.pdf';

$normalize_extracted_lines = static function(string $text): array {
    $text = str_replace("\f", "\n", $text);
    $lines = preg_split('/\R/u', $text);

    if($lines === false) {
        return [];
    }

    return array_values(array_filter(
        array_map('trim', $lines),
        static fn(string $line): bool => $line !== ''
    ));
};

$tests = [
    '0001' => [
        'description' => 'Extract known text from a minimal PDF with pdftotext.',
        'help'        => 'The pdftotext binary must extract the exact text embedded in the fixture.',
        'act'         => static function() use($first_pdf, $normalize_extracted_lines): array {
            $output_lines = [];
            $result_code = 0;
            $command = 'pdftotext -enc UTF-8 -layout -nopgbrk '
                .escapeshellarg($first_pdf)
                .' - 2>&1';

            exec($command, $output_lines, $result_code);

            return [
                'result_code' => $result_code,
                'text_lines'  => $normalize_extracted_lines(implode("\n", $output_lines)),
                'output'      => implode("\n", $output_lines)
            ];
        },
        'assert'      => static function(array $result): bool {
            return $result['result_code'] === 0
                && $result['text_lines'] === ['FMT PDF TOOLS - FIRST PAGE'];
        }
    ],
    '0002' => [
        'description' => 'Merge two minimal PDFs with qpdf.',
        'help'        => 'The qpdf binary must create a valid two-page PDF while preserving page order and text.',
        'act'         => static function() use($first_pdf, $second_pdf, $normalize_extracted_lines): array {
            $output_file = sys_get_temp_dir()
                .DIRECTORY_SEPARATOR
                .'fmt-qpdf-'.bin2hex(random_bytes(8)).'.pdf';
            $escaped_files = array_map('escapeshellarg', [$first_pdf, $second_pdf]);
            $escaped_output = escapeshellarg($output_file);

            $merge_output = [];
            $merge_result_code = 0;
            $command = 'qpdf --empty --pages '
                .implode(' ', $escaped_files)
                .' -- '
                .$escaped_output
                .' 2>&1';
            exec($command, $merge_output, $merge_result_code);

            $page_count_output = [];
            $page_count_result_code = 0;
            exec(
                'qpdf --show-npages '.$escaped_output.' 2>&1',
                $page_count_output,
                $page_count_result_code
            );

            $text_output = [];
            $text_result_code = 0;
            exec(
                'pdftotext -enc UTF-8 -layout -nopgbrk '.$escaped_output.' - 2>&1',
                $text_output,
                $text_result_code
            );

            return [
                'output_file'           => $output_file,
                'merge_result_code'     => $merge_result_code,
                'merge_output'          => implode("\n", $merge_output),
                'page_count_result_code' => $page_count_result_code,
                'page_count'            => trim(implode("\n", $page_count_output)),
                'text_result_code'      => $text_result_code,
                'text_lines'            => $normalize_extracted_lines(implode("\n", $text_output)),
                'text_output'           => implode("\n", $text_output)
            ];
        },
        'assert'      => static function(array $result): bool {
            return $result['merge_result_code'] === 0
                && $result['page_count_result_code'] === 0
                && $result['page_count'] === '2'
                && $result['text_result_code'] === 0
                && $result['text_lines'] === [
                    'FMT PDF TOOLS - FIRST PAGE',
                    'FMT PDF TOOLS - SECOND PAGE'
                ];
        },
        'rollback'    => static function($result): void {
            $output_file = is_array($result) ? ($result['output_file'] ?? null) : null;
            if(is_string($output_file) && is_file($output_file)) {
                unlink($output_file);
            }
        }
    ]
];
