<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Wikimedia Video Editing Server
 * Copyright (C) 2014 Dan R. Dennedy <dan@dennedy.org>
 * Copyright (C) 2014 CDC Leuphana University Lueneburg
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * Get the img src path of the icon to use for a MIME type.
 *
 * @param string $mimeType The MIME type
 * @return string
 */
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
