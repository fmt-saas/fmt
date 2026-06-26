<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use equal\text\TextTransformer;

[$params, $providers] = eQual::announce([
    'description'   => "Returns the given PDF document with an overlay.",
    'help'          => "Add a watermark or extra information to a PDF document.
        Resize supports only downscaling and expects the document to be in A4 format (595x842 points).
        Positioning options are available to avoid overwriting any existing text or image.
        This output is a technical copy; the original document remains unchanged.",
    'params'        => [

        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'documents\Document',
            'description'       => "The document the overlay must be added to.",
            'required'          => true
        ],

        'disposition' => [
            'type'              => 'string',
            'selection'         => [
                'inline',
                'attachment'
            ],
            'default'           => 'inline'
        ],

        'resize' => [
            'type'              => 'float',
            'usage'             => 'amount/rate',
            'description'       => "Resize percentage value.",
            'min'               => 0.8,
            'max'               => 1,
            'default'           => 1
        ],

        'pos_x' => [
            'type'              => 'int',
            'description'       => "The horizontal position of the overlay.",
            'help'              => "Left-to-right relative (zero means left).",
            'default'           => 20,
            'min'               => 0,
            'max'               => 595
        ],

        'pos_y' => [
            'type'              => 'int',
            'description'       => "The vertical position of the overlay.",
            'help'              => "Bottom-up relative (zero means bottom).",
            'default'           => 820,
            'min'               => 0,
            'max'               => 842
        ],

        'font' => [
            'type'              => 'string',
            'selection'         => [
                'helvetica'     => 'Helvetica',
                'times_roman'   => 'Times-Roman',
                'courier'       => 'Courier'
            ],
            'description'       => "The font to use for rendering the overlay text.",
            'default'           => 'courier'
        ],

        'font_size' => [
            'type'              => 'int',
            'description'       => "The font size to use for the overlay text.",
            'help'              => "This might depend on the font (Helvetica by default). Less than 8 seems not readable.",
            'default'           => 12,
            'min'               => 1,
            'max'               => 50
        ],

        'page_size' => [
            'type'              => 'string',
            'description'       => "The expected page size of the PDF document.",
            'help'              => "Used to normalize the page CropBox and resize calculations. Values are ISO page sizes.",
            'selection'         => ['A1', 'A2', 'A3', 'A4', 'A5'],
            'default'           => 'A4'
        ],

        'page_orientation' => [
            'type'              => 'string',
            'description'       => "The expected page orientation of the PDF document.",
            'help'              => "Used to swap page width and height when landscape orientation is selected.",
            'selection'         => [
                'portrait',
                'landscape'
            ],
            'default'           => 'portrait'
        ],

        'overlay_text' => [
            'type'              => 'string',
            'description'       => "The text value to use as overlay.",
            'required'          => true
        ]

    ],
    'response'      => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$formats = [
    'A1' => ['width' => 1684, 'height' => 2384],
    'A2' => ['width' => 1191, 'height' => 1684],
    'A3' => ['width' => 842,  'height' => 1191],
    'A4' => ['width' => 595,  'height' => 842],
    'A5' => ['width' => 420,  'height' => 595]
];

$format = $formats[$params['page_size']];

$width = $format['width'];
$height = $format['height'];

if($params['page_orientation'] === 'landscape') {
    [$width, $height] = [$height, $width];
}

/**
 * Methods
 */


/**
 * Resizes a PDF document by scaling its page content down and centering it on
 * the expected page size.
 *
 * This helper writes the provided PDF content to a temporary file, computes the
 * horizontal and vertical offsets required to keep the scaled content centered,
 * and uses Ghostscript to rebuild the PDF with a BeginPage transformation.
 *
 * The page content is scaled by the given factor while the output page keeps
 * the configured page size. The helper is intended for downscaling only, so the
 * expected scale value should normally be between 0 and 1.
 *
 * The target paper size is taken from the current request parameters
 * (`page_size`) and passed to Ghostscript through `-sPAPERSIZE`. The `$width`
 * and `$height` arguments are used to compute the centering offsets in PDF
 * points.
 *
 * This function returns a newly generated PDF binary. The input PDF content is
 * not modified directly. Temporary files are removed after processing.
 *
 * @param string $pdf_content The raw PDF content to process.
 * @param float  $scale       The scale factor to apply to the page content.
 * @param int    $width       The expected page width in PDF points.
 * @param int    $height      The expected page height in PDF points.
 *
 * @return string The generated PDF content with scaled and centered pages.
 *
 * @throws Exception If Ghostscript fails to generate the resized PDF.
 */
$resize = function($pdf_content, $scale, $width, $height) use($params) {
    $output_file = tempnam(sys_get_temp_dir(), 'resized_');
    $pdf_file = tempnam(sys_get_temp_dir(), 'pdf_');

    file_put_contents($pdf_file, $pdf_content);

    $total_empty_width  = $width * (1 - $scale);
    $total_empty_height = $height * (1 - $scale);

    $offset_x = $total_empty_width / 2;
    $offset_y = $total_empty_height / 2;

    $paper_size = strtolower($params['page_size']);

    $gs_cmd = sprintf(
        'gs -o %s -dSAFER -sDEVICE=pdfwrite -sPAPERSIZE=%s -dFIXEDMEDIA -dPDFFitPage -c "<</BeginPage {%s %s translate %s %s scale}>> setpagedevice" -f %s',
        escapeshellarg($output_file),
        escapeshellarg($paper_size),
        $offset_x,
        $offset_y,
        $scale,
        $scale,
        escapeshellarg($pdf_file)
    );

    exec($gs_cmd, $gs_output, $gs_code);
    if($gs_code !== 0) {
        trigger_error("APP::PDF Ghostscript overlay creation failed: ".implode("\n", $gs_output), EQ_REPORT_ERROR);
        throw new Exception("resized_failed", EQ_ERROR_UNKNOWN);
    }

    $output = file_get_contents($output_file);

    @unlink($output_file);
    @unlink($pdf_file);

    return $output;
};

/**
 * Adds a text overlay to each page of a PDF document using Ghostscript.
 *
 * This helper writes the provided PDF content to a temporary file, generates a
 * temporary PostScript file defining a BeginPage hook, and runs Ghostscript to
 * rebuild the PDF with the overlay applied on every page.
 *
 * The overlay text is first converted to ASCII and escaped for safe inclusion
 * in a PostScript string. Backslashes, parentheses and line breaks are handled
 * to avoid breaking the generated PostScript content.
 *
 * The overlay is rendered using one of the supported built-in PostScript fonts
 * and positioned using PDF/PostScript coordinates, where the origin is located
 * at the bottom-left corner of the page.
 *
 * This function returns a newly generated PDF binary. The input PDF content is
 * not modified directly. Temporary files are removed after processing.
 *
 * @param string $pdf_content  The raw PDF content to process.
 * @param string $overlay_text The text to render on each page.
 * @param int    $font_size    The font size, in PostScript points.
 * @param int    $pos_x        The horizontal position of the text, from the left edge.
 * @param int    $pos_y        The vertical position of the text, from the bottom edge.
 *
 * @return string The generated PDF content with the overlay applied.
 *
 * @throws Exception If Ghostscript fails to generate the overlay PDF.
 */
$addOverlay = function($pdf_content, $overlay_text, $font_size, $pos_x, $pos_y) use($params) {
    $output_file = tempnam(sys_get_temp_dir(), 'overlay_');
    $pdf_file = tempnam(sys_get_temp_dir(), 'pdf_');

    file_put_contents($pdf_file, $pdf_content);

    // handle special characters for PostScript: convert utf8 -> ascii
    $overlay_text = TextTransformer::toAscii($overlay_text);

    $overlay_text = str_replace(
        ['\\',  '(',  ')',  "\r", "\n"],
        ['\\\\', '\\(', '\\)', ' ',   ' '],
        $overlay_text
    );

    /*
        #memo - in case other fonts are needed:
        ```
        % add latin encoding to handle special characters
        /Helvetica findfont
        dup length dict begin
            {1 index /FID ne {def} {pop pop} ifelse} forall
            /Encoding ISOLatin1Encoding def
        currentdict
        end
        /Helvetica-Latin1 exch definefont pop
        ```
    */

    $font = [
        'helvetica'     => 'Helvetica',
        'times_roman'   => 'Times-Roman',
        'courier'       => 'Courier'
    ][$params['font']] ?? 'Helvetica';

    $ps_content = <<<PS
    %!PS
    <<
    /BeginPage {
        gsave
        /$font findfont $font_size scalefont setfont
        0 setgray

        $pos_x $pos_y moveto
        ($overlay_text) show
        grestore
    }
    >> setpagedevice
    PS;

    $ps_file = tempnam(sys_get_temp_dir(), 'overlay_ps_');
    file_put_contents($ps_file, $ps_content);

    $gs_cmd = sprintf(
        'gs -dSAFER -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile=%s %s %s',
        escapeshellarg($output_file),
        escapeshellarg($ps_file),
        escapeshellarg($pdf_file)
    );

    exec($gs_cmd, $gs_output, $gs_code);
    if($gs_code !== 0) {
        trigger_error("APP::PDF Ghostscript overlay creation failed: ".implode("\n", $gs_output), EQ_REPORT_ERROR);
        throw new Exception("overlay_creation_failed", EQ_ERROR_UNKNOWN);
    }

    $output = file_get_contents($output_file);

    @unlink($output_file);
    @unlink($pdf_file);
    @unlink($ps_file);

    return $output;
};

/**
 * Normalizes existing PDF CropBox declarations to the expected page dimensions
 * while preserving the byte length of each numeric value.
 *
 * This helper scans the PDF content for inline CropBox definitions such as:
 *
 *     /CropBox [29.7500 42.1000 565.2500 799.9000]
 *
 * and rewrites the four numeric values to:
 *
 *     [0 0 width height]
 *
 * using the same lexical format as the original values whenever possible.
 * For example, the CropBox above may become:
 *
 *     /CropBox [00.0000 00.0000 595.0000 842.0000]
 *
 * The purpose is to avoid changing the total byte length of the PDF content,
 * which could otherwise invalidate the PDF cross-reference table and make the
 * document unreadable by strict PDF readers such as PDF.js.
 *
 * If a target value cannot be represented using the same byte length as the
 * original numeric token, the corresponding CropBox declaration is left
 * unchanged.
 *
 * This helper only handles inline CropBox arrays with four numeric values.
 * It does not resolve indirect objects, compressed object streams, inherited
 * page boxes, or non-standard CropBox representations.
 *
 * This should only be used on technical/generated PDF copies, not on the
 * original archived document.
 *
 * @param string $pdf_content The raw PDF content to process.
 * @param float  $width       The target page width in PDF points.
 * @param float  $height      The target page height in PDF points.
 *
 * @return string The PDF content with normalized inline CropBox declarations.
 *
 * @throws Exception If the CropBox normalization regex processing fails.
 */
$normalize = function(string $pdf_content, float $width, float $height): string {
    if(strpos($pdf_content, '/CropBox') === false) {
        return $pdf_content;
    }

    $formatNumberLike = function(string $template, float $value): ?string {
        $length = strlen($template);

        $has_sign = ($template[0] === '-' || $template[0] === '+');
        $unsigned_template = $has_sign ? substr($template, 1) : $template;

        $sign = '';
        if($value < 0) {
            $sign = '-';
            $value = abs($value);
        }
        elseif($has_sign && $template[0] === '+') {
            $sign = '+';
        }

        if(strpos($unsigned_template, '.') !== false) {
            $decimal_count = strlen($unsigned_template) - strpos($unsigned_template, '.') - 1;
            $formatted = $sign . number_format($value, $decimal_count, '.', '');
        }
        else {
            $formatted = $sign . (string) round($value);
        }

        if(strlen($formatted) > $length) {
            return null;
        }

        return str_pad($formatted, $length, '0', STR_PAD_LEFT);
    };

    $number_pattern = '[-+]?(?:\d+\.\d+|\d+|\.\d+)';

    $cropbox_pattern = '#'
        . '(/CropBox\s*\[\s*)'
        . '(' . $number_pattern . ')'
        . '(\s+)'
        . '(' . $number_pattern . ')'
        . '(\s+)'
        . '(' . $number_pattern . ')'
        . '(\s+)'
        . '(' . $number_pattern . ')'
        . '(\s*\])'
        . '#s';

    $output = preg_replace_callback(
        $cropbox_pattern,
        function(array $matches) use($formatNumberLike, $width, $height) {
            $values = [
                $formatNumberLike($matches[2], 0),
                $formatNumberLike($matches[4], 0),
                $formatNumberLike($matches[6], $width),
                $formatNumberLike($matches[8], $height)
            ];

            if(in_array(null, $values, true)) {
                return $matches[0];
            }

            return $matches[1]
                . $values[0]
                . $matches[3]
                . $values[1]
                . $matches[5]
                . $values[2]
                . $matches[7]
                . $values[3]
                . $matches[9];
        },
        $pdf_content
    );

    if($output === null) {
        trigger_error("APP::PDF CropBox normalization failed.", EQ_REPORT_ERROR);
        throw new Exception("cropbox_normalization_failed", EQ_ERROR_UNKNOWN);
    }

    return $output;
};

/**
 * Action
 */

$document = Document::id($params['id'])
    ->read(['content_type', 'data', 'name'])
    ->first();

if(!$document) {
    throw new Exception("unknown_document", EQ_ERROR_UNKNOWN_OBJECT);
}

if($document['content_type'] !== 'application/pdf') {
    throw new Exception("not_pdf_document", EQ_ERROR_INVALID_PARAM);
}

$content_type = $document['content_type'];
$filename = $document['name'];

$scale = round($params['resize'], 2);

$pdf_content = $document['data'];

if($scale < 1) {
    $pdf_content = $resize($pdf_content, $scale, $width, $height);
}

$pdf_content = $addOverlay($pdf_content, $params['overlay_text'], $params['font_size'], $params['pos_x'], $params['pos_y']);

$output = $normalize($pdf_content, $width, $height);

$context->httpResponse()
        ->header('Content-Disposition', $params['disposition'] . '; filename="' . $filename . '"')
        ->header('Content-Type', $content_type)
        ->body($output, true)
        ->send();
