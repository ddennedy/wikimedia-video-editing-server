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

require APPPATH.'libraries/third_party/SimpleXMLReader.php';

class MltXmlReader extends SimpleXMLReader
{
    private $files = array();
    private $mlt_service;
    private $resource;
    private $valid_services = [
        // producers
        'avformat',
        'libdv',
        'webvfx',
        'pgm',
        'pango',
        'pixbuf',
        'sdl_image',
        'xml',
        'xml-nogl',
        'melt_file',
        'swfdec',
        'qimage',
        'qtext',
        'kdenlivetitle',
        'avformat-novalidate',
        'hold',
        'framebuffer',
        'luma',
        // transitions
        'region',
        'matte',
        'movit.luma_mix',
        //TODO identify filters and additional property names
    ];

    public function __construct()
    {
        $this->registerCallback('producer', array($this, 'onProducer'));
        $this->registerCallback('property', array($this, 'onProperty'));
        $this->registerCallback('kdenlive_producer', array($this, 'onKdenliveProducer'));
    }

    protected function onProducer($reader)
    {
        // Reset state.
        $this->mlt_service = null;
        $this->resource = null;
        return true;
    }

    protected function onProperty($reader)
    {
        if ($reader->hasAttributes) {
            $value = $reader->readString();
            while ($reader->moveToNextAttribute()) {
                if ($reader->value === 'mlt_service') {
                    $this->mlt_service = $value;
                } else if ($reader->value === 'resource') {
                    $this->resource = $value;
                }
                // If state is ready.
                if ($this->mlt_service && $this->resource) {
                    // Check if the MLT service takes a file resource.
                    if (in_array($this->mlt_service, $this->valid_services)) {
                        $this->files[$this->resource] = [
                            'mlt_service' => $this->mlt_service
                        ];
                    }
                    // Reset state.
                    $this->mlt_service = null;
                    $this->resource = null;
                }
            }
        }
        return true;
    }

    protected function onKdenliveProducer($reader)
    {
        if ($reader->hasAttributes) {
            $this->resource = null;
            $file_hash = null;
            while ($reader->moveToNextAttribute()) {
                if ($reader->name === 'resource') {
                    $this->resource = $reader->value;
                } else if ($reader->name === 'file_hash') {
                    $file_hash = $reader->value;
                }
            }
            if ($this->resource) {
                if (array_key_exists($this->resource, $this->files))
                    $this->files[$this->resource]['file_hash'] = $file_hash;
                else
                    $this->files[$this->resource] = ['file_hash' => $file_hash];
                $this->resource = null;
            }
        }
        return true;
    }

    public function getFiles()
    {
        // Remove
        return $this->files;
    }

    public static function isMimeTypeMltXml($mimeType)
    {
        return $mimeType === 'application/xml' ||
               $mimeType === 'text/xml' ||
               $mimeType === 'application/x-kdenlive' ||
               $mimeType === 'application/mlt+xml';
    }
}
