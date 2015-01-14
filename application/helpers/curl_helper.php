<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * As seen on http://php.net/manual/en/curlfile.construct.php#114539
 */

if (!function_exists('curl_file_create')) {
    /**
     * Create a CURLFile object.
     *
     * Implements curl_file_create() for PHP < 5.5.
     *
     * @param string $filename Path to the file which will be uploaded
     * @param string $mimetype MIME type of the file
     * @param string $postname Name of the file
     */
    function curl_file_create($filename, $mimetype = '', $postname = '') {
        return "@$filename;filename="
            . ($postname ?: basename($filename))
            . ($mimetype ? ";type=$mimetype" : '');
    }
}

