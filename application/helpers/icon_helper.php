<?php defined('BASEPATH') OR exit('No direct script access allowed');

function iconForMimeType($mimeType)
{
    $path = 'img/oxygen/32x32/mimetypes/';
    if ($mimeType === 'application/x-kdenlive')
        return $path.'application-x-kdenlive.png';
    elseif ($mimeType === 'application/x-kdenlivetitle')
        return $path.'application-x-kdenlivetitle.png';
    elseif ($mimeType === 'image/svg+xml')
        return $path.'image-svg+xml.png';
    elseif ($mimeType === 'text/html')
        return $path.'text-html.png';
    elseif ($mimeType === 'text/plain')
        return $path.'text-plain.png';
    elseif ($mimeType === 'text/xml' || $mimeType === 'application/mlt+xml' || $mimeType === 'application/xml')
        return $path.'text-xml.png';
    elseif (strpos($mimeType, 'audio/') === 0)
        return $path.'audio.png';
    elseif (strpos($mimeType, 'image/') === 0)
        return $path.'image.png';
    elseif (strpos($mimeType, 'video/') === 0)
        return $path.'video.png';
    return $path.'application-octet-stream.png';
}
