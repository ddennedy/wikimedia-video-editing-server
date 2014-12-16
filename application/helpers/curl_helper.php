<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * As seen on http://php.net/manual/en/curlfile.construct.php#114539
 */

if (!function_exists('curl_file_create')) {
    function curl_file_create($filename, $mimetype = '', $postname = '') {
        return "@$filename;filename="
            . ($postname ?: basename($filename))
            . ($mimetype ? ";type=$mimetype" : '');
    }
}

