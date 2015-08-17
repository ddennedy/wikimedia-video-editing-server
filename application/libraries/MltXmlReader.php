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
require APPPATH.'libraries/KdenliveTitleReader.php';

/**
 * A class derived from SimpleXMLReader specialized for MLT XML.
 */
class MltXmlReader extends SimpleXMLReader
{
    /** @var array Collects external file references */
    private $files = array();

    /** @var string Saves the root attribute on the root element to qualify relative paths */
    private $root;

    /** @var string Saves the current mlt_service while processing other parts of the XML */
    private $mlt_service;

    /** @var string Saves the current external file reference while processing other parts of the XML */
    private $resource;

    /** @var array MLT services that can have an external file reference */
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

    /** Construct a MltXmlReader */
    public function __construct()
    {
        $CI =& get_instance();
        $CI->load->helper('path');
        $this->registerCallback('mlt', array($this, 'onMlt'));
        $this->registerCallback('producer', array($this, 'onProducer'));
        $this->registerCallback('property', array($this, 'onProperty'));
        $this->registerCallback('kdenlive_producer', array($this, 'onKdenliveProducer'));
    }

    /**
     * The callback function to process the root mlt element
     *
     * This resets the mlt_service and resource state since this is a new producer.
     *
     * @param SimpleXmlReader $reader
     * @return bool Whether to continue parsing
     */
    protected function onMlt($reader)
    {
        $this->root = $reader->getAttribute('root');
        return true;
    }

    /**
     * The callback function to process a producer element
     *
     * This resets the mlt_service and resource state since this is a new producer.
     *
     * @param SimpleXmlReader $reader
     * @return bool Whether to continue parsing
     */
    protected function onProducer($reader)
    {
        // Reset state.
        $this->mlt_service = null;
        $this->resource = null;
        return true;
    }

    /**
     * The callback function to process a property element
     *
     * When both a mlt_service and resource have been discovered, the external
     * file reference is added to the files property.
     *
     * @param SimpleXmlReader $reader
     * @return bool Whether to continue parsing
     */
    protected function onProperty($reader)
    {
        if ($reader->hasAttributes) {
            $value = $reader->readString();
            while ($reader->moveToNextAttribute()) {
                if ($reader->value === 'mlt_service') {
                    $this->mlt_service = $value;
                } else if ($reader->value === 'resource') {
                    // Convert relative path to absolute.
                    if ($this->root && isPathRelative($value)) {
                        $this->resource = joinPaths($this->root, $value);
                    } else {
                        $this->resource = $value;
                    }
                } else if ($reader->value === 'xmldata') {
                    try {
                        $reader = new KdenlivetitleReader();
                        $this->files = array_merge($this->files, $reader->getFiles($value));
                    } catch (Exception $e) {
                    }
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

    /**
     * The callback function to process a property element
     *
     * This adds the external file's MD5 hash to the files array property.
     *
     * @param SimpleXmlReader $reader
     * @return bool Whether to continue parsing
     */
    protected function onKdenliveProducer($reader)
    {
        if ($reader->hasAttributes) {
            $this->resource = null;
            $file_hash = null;
            while ($reader->moveToNextAttribute()) {
                if ($reader->name === 'resource') {
                    // Convert relative path to absolute.
                    if ($this->root && isPathRelative($reader->value)) {
                        $this->resource = joinPaths($this->root, $reader->value);
                    } else {
                        $this->resource = $reader->value;
                    }
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

    /**
     * Parse an MLT XML file.
     *
     * @param string $filename The path to the XML file
     * @return array An associative array of all external file references keyed
     * by the resource property value.
     */
    public function getFiles($filename)
    {
        $this->files = array();
        $this->open($filename);
        $this->parse();
        $this->close();
        return $this->files;
    }

    /** Determine if a MIME type is MLT XML.
     *
     * @param string $mimeType The MIME type to check
     * @return bool True if MIME type is for MLT XML
     */
    public static function isMimeTypeMltXml($mimeType)
    {
        return $mimeType === 'application/xml' ||
               $mimeType === 'text/xml' ||
               $mimeType === 'application/x-kdenlive' ||
               $mimeType === 'application/mlt+xml';
    }
}
