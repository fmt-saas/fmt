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
    'help'          => "Can be used to add a watermark or extra information to a PDF document.
        Resize supports only downscaling and expects the document to be in A4 format (595x842 points).
        Positioning options are available to avoid overwriting any existing text or image.",
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

// Normalize existing CropBox entries to the expected full page size to avoid
// cropped rendering in downstream PDF viewers/converters. This output is a
// technical copy; the original document remains unchanged.
$output = preg_replace(
    '#/CropBox\s*\[\s*[-+]?\d*\.?\d+\s+[-+]?\d*\.?\d+\s+[-+]?\d*\.?\d+\s+[-+]?\d*\.?\d+\s*\]#s',
    "/CropBox [0 0 {$width} {$height}]",
    $pdf_content
);

$context->httpResponse()
        ->header('Content-Disposition', $params['disposition'] . '; filename="' . $filename . '"')
        ->header('Content-Type', $content_type)
        ->body($output, true)
        ->send();
