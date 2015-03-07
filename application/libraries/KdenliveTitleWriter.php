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
 * This class rewrites Kdenlive Title XML to replace paths in content/url attribute.
 */
class KdenliveTitleWriter
{
    /** @var array Data about external file references */
    private $fileData;

    /**
     * Construct a KdenliveTitleWriter.
     *
     * @param array $fileData An array of external file references prepared
     * using functions in MltXmlHelper
     */
    public function __construct($fileData)
    {
        $this->fileData = $fileData;
        $CI =& get_instance();
        $CI->load->helper('path');
    }

    /**
     * Transform a Kdenlive Title XML file.
     *
     * @param string $xml The Kdenlive Title XML
     * @param bool $useAbsolutePath Whether to change a content/url to be a full
     * path to the file on the server (true) or remove it and include only base
     * name (false). Optional, defaults to false.
     * @return string|null The output XML as a string
     * @see XMLWritingIteration
     */
    public function run($xml, $useAbsolutePath = false)
    {
        $reader = new XMLReader();
        $reader->xml($xml);

        $writer = new XMLWriter();
        $writer->openMemory();

        $iterator = new XMLWritingIteration($writer, $reader);

        $writer->startDocument();

        foreach ($iterator as $node) {
            $isElement = $node->nodeType === XMLReader::ELEMENT;

            if ($isElement && $node->name === 'content' && $reader->getAttribute('url')) {
                $url = $reader->getAttribute('url');
                if (isset($this->fileData[$url])) {
                    if ($useAbsolutePath && isset($this->fileData[$url]['proxy_path']))
                        $url = $this->fileData[$url]['proxy_path'];
                    else if (isset($this->fileData[$url]['output_path']))
                        $url = basename($this->fileData[$url]['output_path']);
                }
                $writer->startElement($node->name);
                $writer->writeAttribute('url', $url);
                $writer->endElement();
                $reader->next();
            } else {
                // handle everything else
                $iterator->write();
            }
        }
        $writer->endDocument();
        return $writer->outputMemory(true);
    }
}
