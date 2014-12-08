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

class MltXmlWriter
{
    private $fileData;

    public function __construct($fileData)
    {
        $this->fileData = $fileData;
    }

    public function run($inFilename, $outFilename)
    {
        $reader = new XMLReader();
        $reader->open($inFilename);

        $writer = new XMLWriter();
        $writer->openUri($outFilename);

        $iterator = new XMLWritingIteration($writer, $reader);

        $writer->startDocument();

        foreach ($iterator as $node) {
            $isElement = $node->nodeType === XMLReader::ELEMENT;

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
                $writer->startElement($node->name);
                $isResource = false;
                if ($reader->moveToFirstAttribute()) {
                    do {
                        if ($reader->name === 'name' && $reader->value === 'resource')
                            $isResource = true;
                        $writer->writeAttribute($reader->name, $reader->value);
                    } while ($reader->moveToNextAttribute());
                    $reader->moveToElement();
                    if ($isResource) {
                        $current = $reader->readString();
                        if (isset($this->fileData[$current])) {
                            $writer->text('$CURRENTPATH/' . $this->fileData[$current]['output_path']);
                            while ($reader->read() && $reader->nodeType !== XMLReader::END_ELEMENT);
                            $writer->endElement();
                        }
                    }
                }
                if ($node->isEmptyElement) {
                    $writer->endElement();
                }
            } else if ($isElement && $node->name === 'kdenlive_producer') {
                $hash = null;
                $writer->startElement($node->name);
                if ($reader->moveToFirstAttribute()) {
                    do {
                        if ($reader->name === 'resource' && isset($this->fileData[$reader->value])) {
                            $writer->writeAttribute($reader->name, '$CURRENTPATH/'
                                . $this->fileData[$reader->value]['output_path']);
                            $hash = $this->fileData[$reader->value]['output_hash'];
                        } else if ($hash && $reader->name === 'file_hash') {
                            //TODO this only works if file_hash comes after resource,
                            // which it does when kdenlive writes it, but potentially
                            // not if another tool or kdenlive changes.
                            $writer->writeAttribute($reader->name, $hash);
                        } else {
                            $writer->writeAttribute($reader->name, $reader->value);
                        }
                    } while ($reader->moveToNextAttribute());
                    $reader->moveToElement();
                }
                if ($node->isEmptyElement) {
                    $writer->endElement();
                }
            } else {
                // handle everything else
                $iterator->write();
            }
        }

        $writer->endDocument();
    }
}
