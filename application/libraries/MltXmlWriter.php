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

require APPPATH.'libraries/third_party/xmlreader-iterators.php';

/**
 * This class rewrites MLT XML namely to replace external file references
 * with proxies or the originals.
 */
class MltXmlWriter
{
    /** @var array Data about external file references */
    private $fileData;

    /**
     * Construct a MltXmlWriter.
     *
     * @param array $fileData An array of external file references prepared
     * using functions in MltXmlHelper
     */
    public function __construct($fileData)
    {
        $this->fileData = $fileData;
    }

    /**
     * Transform a MLT XML file.
     *
     * @param string $inFilename Path to the input MLT XML file
     * @param string $outFilename Optional path the output MLT XML file to write
     * @return string|null The output XML as a string if no $outFilename
     * @see XMLWritingIteration
     */
    public function run($inFilename, $outFilename = null)
    {
        $prevNonPropertyEl = null;
        $reader = new XMLReader();
        $reader->open($inFilename);

        $writer = new XMLWriter();
        if ($outFilename)
            $writer->openUri($outFilename);
        else
            $writer->openMemory();

        $iterator = new XMLWritingIteration($writer, $reader);

        $writer->startDocument();

        foreach ($iterator as $node) {
            $isElement = $node->nodeType === XMLReader::ELEMENT;

            // Save the parent element name for property elements.
            if ($isElement && $node->name !== 'property')
                $prevNonPropertyEl = $node->name;

            if ($isElement && ($node->name === 'mlt' || $node->name === 'kdenlivedoc')) {
                $writer->startElement($node->name);
                if ($reader->moveToFirstAttribute()) {
                    do {
                        if ($reader->name === 'root' || $reader->name === 'projectfolder')
                            $writer->writeAttribute($reader->name, '$CURRENTPATH');
                        else
                            $writer->writeAttribute($reader->name, $reader->value);
                    } while ($reader->moveToNextAttribute());
                    $reader->moveToElement();
                }
                if ($node->isEmptyElement) {
                    $writer->endElement();
                }
            } else if ($isElement && $node->name === 'property') {
                $name = $reader->getAttribute('name');
                if ($name && strpos($name, 'meta.') !== 0) {
                    if ($name === 'resource') {
                        $writer->startElement($node->name);
                        $writer->writeAttribute('name', $name);
                        $current = $reader->readString();
                        if (!empty($current)) {
                            if ($prevNonPropertyEl === 'transition') {
                                // Replace a gradient image file for luma transition.
                                $baseName = basename($current);
                                $parentDir = basename(dirname($current));
                                $fullpath = implode('/',
                                    [config_item('mlt_lumas_path'), $parentDir, $baseName]);
                                if (file_exists($fullpath)) {
                                    $current = $fullpath;
                                } else {
                                    $fullpath = implode('/',
                                        [config_item('kdenlive_lumas_path'), $parentDir, $baseName]);
                                    if (file_exists($fullpath)) $current = $fullpath;
                                }
                            } else if (isset($this->fileData[$current])) {
                                $current = $this->fileData[$current]['resource'];
                            }
                            $writer->text($current);
                        }
                        $writer->endElement();
                        $reader->next();
                    } else {
                        $iterator->write();
                    }
                } else {
                    // Remove the meta properties.
                    $reader->next();
                }
            } else if ($isElement && $node->name === 'kdenlive_producer') {
                $data = array();
                $writer->startElement($node->name);
                if (isset($this->fileData[$reader->getAttribute('resource')])) {
                    $data = $this->fileData[$reader->getAttribute('resource')];
                    if ($reader->moveToFirstAttribute()) {
                        do {
                            if (isset($data[$reader->name]))
                                $writer->writeAttribute($reader->name, $data[$reader->name]);
                            else
                                $writer->writeAttribute($reader->name, $reader->value);
                        } while ($reader->moveToNextAttribute());
                        $reader->moveToElement();
                    }
                }
            } else if ($isElement && $node->name === 'metaproperty' && is_array($data)
                       && element('mlt_service', $data) == 'avformat') {
                $reader->next();
            } else {
                // handle everything else
                $iterator->write();
            }
        }
        $writer->endDocument();
        if (!$outFilename)
            return $writer->outputMemory(true);
    }
}
