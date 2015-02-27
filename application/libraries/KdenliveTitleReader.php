<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Wikimedia Video Editing Server
 * Copyright (C) 2015 Dan R. Dennedy <dan@dennedy.org>
 * Copyright (C) 2015 CDC Leuphana University Lueneburg
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
 * A class derived from SimpleXMLReader specialized for kdenlivetitle XML.
 */
class KdenliveTitleReader extends SimpleXMLReader
{
    /** @var array Collects external file references */
    private $files = array();

    /** Construct a KdenliveTitleReader */
    public function __construct()
    {
        $CI =& get_instance();
        $CI->load->helper('path');
        $this->registerCallback('content', array($this, 'onContent'));
    }

    /**
     * The callback function to process a content element
     *
     * If there is an url attribute, the external
     * file reference is added to the files property.
     *
     * @param SimpleXmlReader $reader
     * @return bool Whether to continue parsing
     */
    protected function onContent($reader)
    {
        if ($reader->hasAttributes) {
            $value = $reader->readString();
            $url = $reader->getAttribute('url');
            if ($url) {
                $extension = strrchr($url, '.');
                $file_hash = null;
                if ('.titlepart' === strtolower($extension))
                    $file_hash = basename($url, $extension);
                $this->files[$url] = [
                    'mlt_service' => 'kdenlivetitle',
                    'file_hash' => $file_hash
                ];
            }
        }
        return true;
    }

    /**
     * Parse kdenlivetitle XML.
     *
     * @param string $xml The XML string
     * @return array An associative array of all external file references keyed
     * by the content url attribute value
     */
    public function getFiles($xml)
    {
        $this->files = array();
        $this->xml($xml);
        $this->parse();
        $this->close();
        return $this->files;
    }
}
