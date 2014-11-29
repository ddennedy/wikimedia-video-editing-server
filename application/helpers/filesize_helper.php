<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * As seen on http://php.net/manual/en/function.filesize.php#112996
 * by Arseny Mogilev, modified for mebibyte units by Dan Dennedy:
 * https://en.wikipedia.org/wiki/Mebibyte
 * Also, I removed the function call str_replace() that changes the decimal point character.
 */

/**
* Converts bytes into human readable file size.
*
* @param string $bytes
* @return string human readable file size (2,87 Мб)
* @author Mogilev Arseny
*/
function FileSizeConvert($bytes)
{
    $bytes = floatval($bytes);
        $arBytes = array(
            0 => array(
                "UNIT" => "TiB",
                "VALUE" => pow(1024, 4)
            ),
            1 => array(
                "UNIT" => "GiB",
                "VALUE" => pow(1024, 3)
            ),
            2 => array(
                "UNIT" => "MiB",
                "VALUE" => pow(1024, 2)
            ),
            3 => array(
                "UNIT" => "KiB",
                "VALUE" => 1024
            ),
            4 => array(
                "UNIT" => "B",
                "VALUE" => 1
            ),
        );

    foreach($arBytes as $arItem)
    {
        if($bytes >= $arItem["VALUE"])
        {
            $result = $bytes / $arItem["VALUE"];
            $result = strval(round($result, 2))." ".$arItem["UNIT"];
            break;
        }
    }
    return $result;
}
