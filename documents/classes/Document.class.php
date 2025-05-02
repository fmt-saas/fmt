<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace documents;

use equal\http\HttpRequest;
use equal\orm\Model;
use Exception;

class Document extends Model {

    public static function getLink() {
        return "/documents/#/document/object.id";
    }

    public static function constants() {
        return ['FMT_INSTANCE_TYPE', 'FMT_API_URL_EDMS'];
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium'
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'suppliership_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'purchase\supplier\Supplier',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'case_file_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'tracking\CaseFile',
                'description' => 'Optional link to the related case file (incident, quote, etc.).',
            ],

            'name' => [
                'type'              => 'string',
                'required'          => true
            ],

            'data' => [
                'type'              => 'binary',
                'dependents'        => ['content_type', 'content_size', 'readable_size', 'preview_image'],
                'onupdate'          => 'onupdateData'
            ],

            'document_type' => [
                'type'              => 'string',
                'selection'         => [
                    'invoice',
                    'credit_note',
                    'quote',
                    'purchase_order',
                    'delivery_note',
                    'incident_report',
                    'maintenance_report',
                    'contract',
                    'certificate',
                    'terms_and_conditions',
                    'reconciliation_report',
                    'bank_statement',
                    'legal_document',
                    'correspondence',
                    'supporting_document',
                    'internal_memo'
                ],
                'description'       => 'Type of document related to its purpose.'
            ],

            'content_type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcContentType',
                'store'             => true,
                'instant'           => true,
                'description'       => 'Content type of the document (from data).'
            ],

            'content_size' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcContentSize',
                'store'             => true,
                'instant'           => true,
                'description'       => 'Size of the document, in octets (from data).'
            ],

            'readable_size' => [
                'type'              => 'computed',
                'description'       => 'Readable size',
                'function'          => 'calcReadableSize',
                'result_type'       => 'string',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'uuid' => [
                'type'              => 'string',
                'usage'             => 'text/plain:36',
                // #todo - restore - only for testing
                // 'unique'            => true,
                'description'       => 'Unique document identifier provided by EDMS'
            ],

            'link' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url',
                'description'       => 'URL for visualizing the document.',
                'function'          => 'calcLink',
                'store'             => true,
                'readonly'          => true
            ],

            'lang_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\Lang',
                'description'       => 'Language used in the document.',
                'default'           => 1
            ],

            'category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentCategory',
                'description'       => 'Category of the document.',
                'default'           => 1
            ],

            'tags_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'documents\DocumentTag',
                'foreign_field'     => 'documents_ids',
                'rel_table'         => 'documents_rel_document_tag',
                'rel_foreign_key'   => 'tag_id',
                'rel_local_key'     => 'document_id',
                'description'       => 'Tags of the document.'
            ],

            'tags' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Short tags listing, max 50 characters.',
                'store'             => false,
                'function'          => 'calcTags',
                'readonly'          => true
            ],

            'public' => [
                'type'              => 'boolean',
                'description'       => 'Accessibility of the document.',
                'default'           => false
            ],

            'preview_image' => [
                'type'              => 'computed',
                'result_type'       => 'binary',
                'usage'             => 'image/jpeg',
                'function'          => 'calcPreviewImage',
                'description'       => 'Thumbnail of the document.',
                'store'             => true
            ],

            'email_id' => [
                'type'              => 'one2many',
                'foreign_object'    => 'communication\email\Email',
                'description'       => 'Email the document is an attachment of.'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending', 'processed', 'ignored'
                ],
                'default'     => 'pending',
                'description' => 'Processing status of the document.'
            ]

        ];
    }

    public static function onupdateData($self, $adapt) {
        $instance_type = constant('FMT_INSTANCE_TYPE');
        if($instance_type === 'agency') {
            /** @var \equal\data\adapt\DataAdapter */
            $adapter = $adapt->get('json');

            $self->read(['condo_id', 'name', 'data']);
            foreach($self as $id => $document) {
                if(!$document['data']) {
                    continue;
                }
                $data = $adapter->adaptOut($document['data'], 'binary');

                // we need to relay document to EDMS in order to receive a UUID
                $url = constant('FMT_API_URL_EDMS');
                try {
                    $request = new HttpRequest('POST '.$url.'?do=documents_push');
                    $response = $request
                        ->setBody([
                            'name'      => $document['name'],
                            'condo_id'  => $document['condo_id'],
                            'data'      => $data
                        ])
                        ->send();
                    $result = $response->body();
                    if(isset($result['uuid'])) {
                        self::id($document['id'])->update(['uuid' => $result['uuid'], 'data' => null]);
                    }
                    else {
                        throw new \Exception('edms_response_without_uuid', EQ_ERROR_UNKNOWN);
                    }
                }
                catch(\Exception $e) {
                    trigger_error("APP:unable to store document on EDMS: ".$e->getMessage(), EQ_REPORT_ERROR);
                }
            }
        }
    }

    public static function calcLink($self) {
        $result = [];
        foreach($self as $id => $document) {
            $result[$id] = '/document/' . $id;
        }
        return $result;
    }

    public static function calcTags($self) {
        $result = [];
        $self->read(['tags_ids' => ['name']]);
        foreach($self as $id => $document) {
            $tags = implode(', ', array_column($document['tags_ids']->get(true), 'name'));
            $result[$id] = strlen($tags) > 50 ? (substr($tags, 0, 50) . '...') : $tags;
        }

        return $result;
    }

    public static function calcContentSize($self) {
        $result = [];
        $self->read(['data']);

        foreach($self as $id => $document) {
            if(isset($document['data'])) {
                $result[$id] = strlen($document['data'] ?? '');
            }
        }

        return $result;
    }

    public static function calcReadableSize($self) {
        $result = [];
        $self->read(['content_size']);
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        foreach($self as $id => $document) {
            $size = $document['content_size'];
            if($size) {
                $power = $size > 0 ? floor(log($size, 1024)) : 0;
                $result[$id] = number_format($size / pow(1024, $power), 1, '.', '') . ' ' . $units[$power];
            }
        }
        return $result;
    }

    public static function calcContentType($self) {
        $result = [];
        $self->read(['data']);

        foreach($self as $id => $document) {
            if(!isset($document['data'])) {
                continue;
            }
            try {
                $content = $document['data'] ?? '';

                // retrieve content_type from MIME
                $finfo = new \finfo(FILEINFO_MIME);

                $mime = $finfo->buffer($content);

                if($mime === false) {
                    throw new \Exception('missing_mime');
                }

                $content_type = explode(';', $mime)[0];

                if(empty($content_type)) {
                    throw new \Exception('invalid_mime');
                }

                $result[$id] = $content_type;

            }
            catch(\Exception $e) {
                // failed retrieving content type from content: ignore
            }

        }

        return $result;
    }

    /**
     * Retrieve and validate an extension from a content type and a filename.
     *
     * @param $content_type string  The content_type found for for the file.
     * @param $name string  (optional) The name of the file, if any.
     *
     * @return string | bool    In case of success, the extension is returned. If no extension matches the content type, the method returns false.
     */
    private static function computeExtensionFromType($content_type, $name = '') {

        static $map_extensions = [
            '3g2'   => 'video/3gpp2',
            '3gp'   => 'video/3gpp',
            '7z'    => 'application/x-7z-compressed',
            'aac'   => 'audio/aac',
            'ac3'   => 'audio/ac3',
            'ai'    => 'application/vnd.adobe.illustrator',
            'aif'   => 'audio/aiff',
            'aifc'  => 'audio/x-aiff',
            'aiff'  => 'audio/aiff',
            'apng'  => 'image/apng',
            'au'    => 'audio/basic',
            'avi'   => 'video/x-msvideo',
            'avif'  => 'image/avif',
            'bin'   => 'application/octet-stream',
            'bmp'   => 'image/bmp',
            'cdr'   => 'application/vnd.corel-draw',
            'cer'   => 'application/pkix-cert',
            'class' => 'application/java-vm',
            'cpt'   => 'application/mac-compactpro',
            'crl'   => 'application/pkix-crl',
            'crt'   => 'application/x-x509-ca-cert',
            'csr'   => 'application/pkcs10',
            'css'   => 'text/css',
            'csv'   => 'text/csv',
            'dcr'   => 'application/x-director',
            'der'   => 'application/x-x509-ca-cert',
            'dir'   => 'application/x-director',
            'dll'   => 'application/x-msdownload',
            'dms'   => 'application/octet-stream',
            'doc'   => 'application/msword',
            'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'dot'   => 'application/msword',
            'dotx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
            'dvi'   => 'application/x-dvi',
            'dxr'   => 'application/x-director',
            'eml'   => 'message/rfc822',
            'eps'   => 'application/postscript',
            'exe'   => 'application/x-msdownload',
            'f4v'   => 'video/x-f4v',
            'flac'  => 'audio/flac',
            'flv'   => 'video/x-flv',
            'gif'   => 'image/gif',
            'gpg'   => 'application/pgp-encrypted',
            'gtar'  => 'application/x-gtar',
            'gz'    => 'application/gzip',
            'heic'  => 'image/heic',
            'heif'  => 'image/heif',
            'hqx'   => 'application/mac-binhex40',
            'htm'   => 'text/html',
            'html'  => 'text/html',
            'ical'  => 'text/calendar',
            'ico'   => 'image/vnd.microsoft.icon',
            'ics'   => 'text/calendar',
            'j2k'   => 'image/jp2',
            'jar'   => 'application/java-archive',
            'jp2'   => 'image/jp2',
            'jpe'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'jpf'   => 'image/jpx',
            'jpg'   => 'image/jpeg',
            'jpg2'  => 'image/jp2',
            'jpm'   => 'image/jpm',
            'jpx'   => 'image/jpx',
            'js'    => 'application/javascript',
            'json'  => 'application/json',
            'kdb'   => 'application/octet-stream',
            'kml'   => 'application/vnd.google-earth.kml+xml',
            'kmz'   => 'application/vnd.google-earth.kmz',
            'lha'   => 'application/octet-stream',
            'log'   => 'text/plain',
            'lzh'   => 'application/octet-stream',
            'm3u'   => 'audio/x-mpegurl',
            'm4a'   => 'audio/mp4',
            'm4u'   => 'video/vnd.mpegurl',
            'mid'   => 'audio/midi',
            'midi'  => 'audio/midi',
            'mif'   => 'application/vnd.mif',
            'mj2'   => 'video/mj2',
            'mjp2'  => 'video/mj2',
            'mov'   => 'video/quicktime',
            'movie' => 'video/x-sgi-movie',
            'mp2'   => 'audio/mpeg',
            'mp3'   => 'audio/mpeg',
            'mp4'   => 'video/mp4',
            'mpe'   => 'video/mpeg',
            'mpeg'  => 'video/mpeg',
            'mpg'   => 'video/mpeg',
            'mpga'  => 'audio/mpeg',
            'oda'   => 'application/oda',
            'odc'   => 'application/vnd.oasis.opendocument.chart',
            'odf'   => 'application/vnd.oasis.opendocument.formula',
            'odg'   => 'application/vnd.oasis.opendocument.graphics',
            'odi'   => 'application/vnd.oasis.opendocument.image',
            'odm'   => 'application/vnd.oasis.opendocument.text-master',
            'odp'   => 'application/vnd.oasis.opendocument.presentation',
            'ods'   => 'application/vnd.oasis.opendocument.spreadsheet',
            'odt'   => 'application/vnd.oasis.opendocument.text',
            'ogg'   => 'application/ogg',
            'otc'   => 'application/vnd.oasis.opendocument.chart-template',
            'otf'   => 'application/x-font-otf',
            'otg'   => 'application/vnd.oasis.opendocument.graphics-template',
            'oth'   => 'application/vnd.oasis.opendocument.text-web',
            'oti'   => 'application/vnd.oasis.opendocument.image-template',
            'otp'   => 'application/vnd.oasis.opendocument.presentation-template',
            'ots'   => 'application/vnd.oasis.opendocument.spreadsheet-template',
            'ott'   => 'application/vnd.oasis.opendocument.text-template',
            'p10'   => 'application/pkcs10',
            'p12'   => 'application/x-pkcs12',
            'p7a'   => 'application/x-pkcs7-signature',
            'p7c'   => 'application/pkcs7-mime',
            'p7m'   => 'application/pkcs7-mime',
            'p7r'   => 'application/x-pkcs7-certreqresp',
            'p7s'   => 'application/pkcs7-signature',
            'pdf'   => 'application/pdf',
            'pem'   => 'application/x-pem-file',
            'pgp'   => 'application/pgp-encrypted',
            'php'   => 'application/x-httpd-php',
            'php3'  => 'application/x-httpd-php',
            'php4'  => 'application/x-httpd-php',
            'phps'  => 'application/x-httpd-php-source',
            'phtml' => 'application/x-httpd-php',
            'png'   => 'image/png',
            'ppt'   => 'application/vnd.ms-powerpoint',
            'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'ps'    => 'application/postscript',
            'psd'   => 'image/vnd.adobe.photoshop',
            'qt'    => 'video/quicktime',
            'ra'    => 'audio/x-pn-realaudio',
            'ram'   => 'audio/x-pn-realaudio',
            'rar'   => 'application/x-rar-compressed',
            'rm'    => 'application/vnd.rn-realmedia',
            'rpm'   => 'audio/x-pn-realaudio-plugin',
            'rsa'   => 'application/x-pkcs7',
            'rtf'   => 'application/rtf',
            'rtx'   => 'text/richtext',
            'rv'    => 'video/vnd.rn-realvideo',
            'sea'   => 'application/octet-stream',
            'shtml' => 'text/html',
            'sit'   => 'application/x-stuffit',
            'smi'   => 'application/smil',
            'smil'  => 'application/smil',
            'so'    => 'application/octet-stream',
            'srt'   => 'application/x-subrip',
            'sst'   => 'application/octet-stream',
            'svg'   => 'image/svg+xml',
            'swf'   => 'application/x-shockwave-flash',
            'tar'   => 'application/x-tar',
            'text'  => 'text/plain',
            'tgz'   => 'application/gzip',
            'tif'   => 'image/tiff',
            'tiff'  => 'image/tiff',
            'txt'   => 'text/plain',
            'vcf'   => 'text/vcard',
            'vlc'   => 'application/videolan',
            'vtt'   => 'text/vtt',
            'wav'   => 'audio/wav',
            'wbxml' => 'application/vnd.wap.wbxml',
            'webm'  => 'video/webm',
            'wma'   => 'audio/x-ms-wma',
            'wmlc'  => 'application/vnd.wap.wmlc',
            'wmv'   => 'video/x-ms-wmv',
            'word'  => 'application/msword',
            'xht'   => 'application/xhtml+xml',
            'xhtml' => 'application/xhtml+xml',
            'xl'    => 'application/excel',
            'xls'   => 'application/vnd.ms-excel',
            'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xml'   => 'application/xml',
            'xsl'   => 'application/xml',
            'xspf'  => 'application/xspf+xml',
            'z'     => 'application/x-compress',
            'zip'   => 'application/zip',
            'zsh'   => 'application/x-zsh'
        ];

        // if we have a name holding an extension, check if the given content_type is amongst the valid MIME of the map
        if(strlen($name)) {
            $extension = strtolower( ( ($n = strrpos($name, ".")) === false) ? "" : substr($name, $n+1) );
            if(strlen($extension) && isset($map_extensions[$extension])) {
                if($map_extensions[$extension] == $content_type) {
                    return $extension;
                }
            }
        }
        // find the first extension that matches the given content_type
        foreach($map_extensions as $extension => $mime) {
            if($mime == $content_type) {
                return $extension;
            }
        }
        return false;
    }

    /**
     * Generate the preview image.
     *
     * By convention, generated thumbnail is always a JPEG image.
     *
     */
    public static function calcPreviewImage($self) {
        $result = [];
        $target_width = 150;
        $target_height = 150;
        $self->read(['name', 'content_type', 'data']);
        foreach($self as $id => $document) {
            try {
                if(substr($document['content_type'], 0, 5) != 'image') {
                    throw new Exception('not_an_image');
                }

                $parts = explode('/', $document['content_type']);

                if(count($parts) < 2) {
                    throw new Exception('invalid_content_type');
                }

                $image_type = strtolower($parts[1]);

                if(!in_array($image_type, ['avif', 'apng', 'bmp', 'png', 'gif', 'jpeg', 'svg+xml', 'webp', 'x-icon'])) {
                    throw new Exception('non_supported_format');
                }

                $src_image = imagecreatefromstring($document['data']);

                if(!$src_image) {
                    throw new Exception('malformed_image_data');
                }

                $src_width = imageSX($src_image);
                $src_height = imageSY($src_image);

                $dst_image = imagecreatetruecolor($target_width, $target_height);

                // preserve transparency
                if ($image_type == 'png' || $image_type == 'gif') {
                    imagealphablending($dst_image, false);
                    imagesavealpha($dst_image, true);
                    $transparent = imagecolorallocatealpha($dst_image, 255, 255, 255, 127);
                    imagefilledrectangle($dst_image, 0, 0, $target_width, $target_height, $transparent);
                }
                else {
                    // fill background with white for non-transparent images
                    $white = imagecolorallocate($dst_image, 255, 255, 255);
                    imagefilledrectangle($dst_image, 0, 0, $target_width, $target_height, $white);
                }

                if( ($src_width / $src_height) < ($target_width / $target_height) ) {
                    $new_height = $target_height;
                    $new_width  = $src_width * $target_height / $src_height;
                }
                else {
                    $new_height = $src_height * $target_width / $src_width;
                    $new_width  = $target_width;
                }

                $offset_x  = round( ($target_width - $new_width) / 2 );
                $offset_y  = round( ($target_height - $new_height) / 2 );
                imagecopyresampled($dst_image, $src_image, $offset_x, $offset_y, 0, 0, $new_width, $new_height, $src_width, $src_height);

                // get binary value of generated image
                ob_start();
                imagejpeg($dst_image, null, 80);
                $buffer = ob_get_clean();

                // free mem
                imagedestroy($dst_image);
                imagedestroy($src_image);

                $result[$id] = $buffer;
            }
            // non-supported image type or non-image document: fallback to hardcoded default thumbnail
            catch(Exception $e){
                $found = false;
                $extension = self::computeExtensionFromType($document['content_type'], $document['name']);
                if($extension) {
                    $filename = EQ_BASEDIR.'/packages/documents/assets/img/extensions/'.$extension.'.jpg';
                    if(is_file($filename)) {
                        $found = true;
                        $result[$id] = file_get_contents($filename);
                    }
                }
                if(!$found) {
                    $filename = EQ_BASEDIR.'/packages/documents/assets/img/extensions/unknown.jpg';
                    $result[$id] = file_get_contents($filename);
                }
            }
        }
        return $result;
    }
}
