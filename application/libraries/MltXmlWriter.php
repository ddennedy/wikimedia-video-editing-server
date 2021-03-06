<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Wikimedia Video Editing Server
 * Copyright (C) 2014-2015 Dan R. Dennedy <dan@dennedy.org>
 * Copyright (C) 2014-2015 CDC Leuphana University Lueneburg
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
require APPPATH.'libraries/KdenliveTitleWriter.php';

/**
 * This class rewrites MLT XML namely to replace external file references
 * with proxies or the originals.
 */
class MltXmlWriter
{
    /** @var string Saves the root attribute on the root element to qualify relative paths */
    private $root;

    /**
     * Construct a MltXmlWriter.
     */
    public function __construct()
    {
        $CI =& get_instance();
        $CI->load->helper('path');
    }

    /**
     * Transform a MLT XML file.
     *
     * @param array $fileData An array of external file references prepared
     * @param string $inFilename Path to the input MLT XML file
     * @param string $outFilename Optional path the output MLT XML file to write
     * @param bool $fixLumaPaths Whether to try to adjust the path to luma
     * transition resource files to something usable on this server
     * @return string|null The output XML as a string if no $outFilename
     * @see XMLWritingIteration
     */
    public function run($fileData, $inFilename, $outFilename = null, $fixLumaPaths = false)
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
                        if ($reader->name === 'root') {
                            $this->root = $reader->value;
                            $writer->writeAttribute($reader->name, '$CURRENTPATH');
                        } else if ($reader->name === 'projectfolder') {
                            $writer->writeAttribute($reader->name, '$CURRENTPATH');
                        } else {
                            $writer->writeAttribute($reader->name, $reader->value);
                        }
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
                        // Convert relative path to absolute.
                        if ($this->root && isPathRelative($current)) {
                            $current = joinPaths($this->root, $current);
                        }
                        if (!empty($current)) {
                            if ($fixLumaPaths && $prevNonPropertyEl === 'transition') {
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
                            } else if (isset($fileData[$current])) {
                                $current = $fileData[$current]['resource'];
                            }
                            $writer->text($current);
                        }
                        $writer->endElement();
                        $reader->next();
                    } else if ($name === 'xmldata') {
                        try {
                            $value = $reader->readString();
                            $kdenlivetitle = new KdenliveTitleWriter($fileData);
                            $xml = $kdenlivetitle->run($value, $fixLumaPaths);
                            $writer->startElement($node->name);
                            $writer->writeAttribute('name', $name);
                            $writer->text($xml);
                            $writer->endElement();
                            $reader->next();
                        } catch (Exception $e) {
                        }
                    } else {
                        $iterator->write();
                    }
                } else {
                    // Remove the meta properties.
                    $reader->next();
                }
            } else if ($isElement && $node->name === 'kdenlive_producer') {
                $data = array();
                $xml = $reader->getAttribute('xmldata');
                if (isset($fileData[$reader->getAttribute('resource')])) {
                    $writer->startElement($node->name);
                    $data = $fileData[$reader->getAttribute('resource')];
                    if ($reader->moveToFirstAttribute()) {
                        do {
                            if (isset($data[$reader->name]))
                                $writer->writeAttribute($reader->name, $data[$reader->name]);
                            else
                                $writer->writeAttribute($reader->name, $reader->value);
                        } while ($reader->moveToNextAttribute());
                    }
                    $writer->endElement();
                    $reader->next();
                } else if (!empty($xml)) {
                    try {
                        $kdenlivetitle = new KdenliveTitleWriter($fileData);
                        $xml = $kdenlivetitle->run($xml, $fixLumaPaths);
                        $writer->startElement($node->name);
                        if ($reader->moveToFirstAttribute()) {
                            do {
                                if ($reader->name === 'xmldata')
                                    $writer->writeAttribute($reader->name, $xml);
                                else
                                    $writer->writeAttribute($reader->name, $reader->value);
                            } while ($reader->moveToNextAttribute());
                        }
                        $writer->endElement();
                        $reader->next();
                    } catch (Exception $e) {
                    }
                } else {
                    $iterator->write();
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
