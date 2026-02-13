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

        'resize' => [
            'type'              => 'float',
            'usage'             => 'amount/rate',
            'description'       => "Resize percentage value.",
            'min'               => 0.80,
            'max'               => 1,
            'default'           => 1
        ],

        'pos_x' => [
            'type'              => 'int',
            'description'       => "The horizontal position of the overlay.",
            'help'              => "Left-to-right relative (zero means left).",
            'default'           => 10,
            'min'               => 0,
            'max'               => 595
        ],

        'pos_y' => [
            'type'              => 'int',
            'description'       => "The vertical position of the overlay.",
            'help'              => "Bottom-up relative (zero means bottom).",
            'default'           => 830,
            'min'               => 0,
            'max'               => 842
        ],

        'font_size' => [
            'type'              => 'int',
            'description'       => "The font size to use for the overlay text.",
            'help'              => "This might depend on the font (Helvetica by default). Less than 8 seems not readable.",
            'default'           => 12,
            'min'               => 1,
            'max'               => 50
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

/**
 * Methods
 */

$resize = function($pdf_file, $scale) {
    $output_file = tempnam(sys_get_temp_dir(), 'resized_');

    $total_empty_width  = 595 * (1 - $scale);
    $total_empty_height = 842 * (1 - $scale);

    $offset_x = $total_empty_width / 2;
    $offset_y = $total_empty_height / 2;

    $gs_cmd = sprintf(
        'gs -o %s -dSAFER -sDEVICE=pdfwrite -sPAPERSIZE=a4 -dFIXEDMEDIA -dPDFFitPage -c "<</BeginPage {%s %s translate %s %s scale}>> setpagedevice" -f %s',
        escapeshellarg($output_file),
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

    return $output_file;
};

$addOverlay = function($pdf_file, $overlay_text, $font_size, $pos_x, $pos_y) {
    $output_file = tempnam(sys_get_temp_dir(), 'overlay_');

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
    $ps_content = <<<PS
%!PS
<<
  /BeginPage {
    gsave
      /Helvetica findfont $font_size scalefont setfont
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

    @unlink($ps_file);

    return $output_file;
};

/**
 * Action
 */

$document = Document::id($params['id'])
    ->read(['content_type', 'data'])
    ->first();

if(!$document) {
    throw new Exception("unknown_document", EQ_ERROR_UNKNOWN_OBJECT);
}

if($document['content_type'] !== 'application/pdf') {
    throw new Exception("not_pdf_document", EQ_ERROR_INVALID_PARAM);
}

$scale = round($params['resize'], 2);

$temp_file = tempnam(sys_get_temp_dir(), 'pdf_');
file_put_contents($temp_file, $document['data']);

if($scale < 1) {
    $temp_resized_file = $resize($temp_file, $scale);
    @unlink($temp_file);

    $temp_file = $temp_resized_file;
}

$tmp_file_with_overlay = $addOverlay($temp_file, $params['overlay_text'], $params['font_size'], $params['pos_x'], $params['pos_y']);
@unlink($temp_file);

$output = file_get_contents($tmp_file_with_overlay);
@unlink($tmp_file_with_overlay);

$context->httpResponse()
        ->body($output)
        ->send();
