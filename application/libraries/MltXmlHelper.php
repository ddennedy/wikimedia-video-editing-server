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

require APPPATH.'libraries/MltXmlReader.php';

/**
 * This class contains a set of MLT XML processing functions.
 */
class MltXmlHelper {

    /**
     * Determine if XML is syntactically well-formed.
     *
     * @param string $xml The XML data
     * @return bool
     */
    public static function isXmlWellFormed($xml)
    {
        libxml_use_internal_errors(true);
        return simplexml_load_string($xml) !== false;
    }

    /**
     * Get all the external files referenced by a MLT XML file.
     *
     * @param string $filename Path to the XML file
     * @param string $log A reference to a string to which errors may be logged
     * @return array
     * @see MltXmlReader::getFiles()
     */
    public static function getFilesData($filename, &$log)
    {
        try {
            $reader = new MltXmlReader();
            return $reader->getFiles($filename);
        } catch (Exception $e) {
            $log .= "$e\n";
            return array();
        }
    }

    /**
     * Determine if all external file references are in the database.
     *
     * The database is updated with missing files or to add file records as children.
     *
     * @param object $file_model A reference to a File model
     * @param array $file A file record
     * @param array $childFiles A reference to the array of file external files
     * @param string $log A reference to a string for logging
     * @return bool Whether there are missing files
     * @see MltXmlHelper::getFilesData()
     */
    public static function checkFileReferences(&$file_model, $file, &$childFiles, &$log)
    {
        $isValid = true;
        foreach($childFiles as $fileName => &$fileData) {
            $name = basename($fileName);
            if (isset($fileData['mlt_service'])) {
                $child = null;
                $log .= "Found file in XML with name: $name.\n";
                if (!empty($fileData['file_hash'])) {
                    // Search for file by hash.
                    $child = $file_model->getByHash($fileData['file_hash']);
                    if ($child)
                        $log .= "Found file record by its hash: $fileData[file_hash].\n";
                }
                if (!$child) {
                    // Search for file by basename.
                    $child = $file_model->getByPath($name);
                    if ($child)
                        $log .= "Found file record by name: $name.\n";
                }
                //TODO Search for the file on Commons based on its basename;
                if ($child) {
                    // Add child and parent relations to database.
                    if ($file_model->addChild($file['id'], $child['id']))
                        $log .= "Added file relationship: $file[id] -> $child[id].\n";
                    else
                        $log .= "Error adding record to file_children table: $file[id] -> $child[id].\n";
                } else {
                    $isValid = false;
                    // Add child to missing_files table.
                    if ($file_model->addMissing($file['id'], $name, $fileData['file_hash']))
                        $log .= "Added to missing_files table: $file[id] -> $name.\n";
                    else
                        $log .= "Error adding record to missing_files table: $file[id] -> $name.\n";
                }
            } else {
                // This file is not necessary and the corresponding
                // kdenlive_producer can be removed from the XML to
                // remove unneeded dependencies.
                $log .= "Found unnecessary file: $name.\n";
            }
        }
        return $isValid;
    }

    /**
     * Update the array of external files with information about the proxy version of the files.
     *
     * @param object $file_model A reference to a File model
     * @param array $file A file record
     * @param array $childFiles A reference to the array of file external files
     * @param string $log A reference to a string for logging
     * @return bool Whether there are missing files
     * @see MltXmlHelper::getFilesData()
     */
    public static function substituteProxyFiles(&$file_model, $file, &$childFiles, &$log)
    {
        $isValid = true;
        foreach($childFiles as $fileName => &$fileData) {
            $name = basename($fileName);
            if (isset($fileData['mlt_service'])) {
                $child = null;
                $log .= "Found file in XML with name: $name.\n";
                if (!empty($fileData['file_hash'])) {
                    // Search for file by hash.
                    $child = $file_model->getByHash($fileData['file_hash']);
                    if ($child)
                        $log .= "Found file record by its hash: $fileData[file_hash].\n";
                }
                if (!$child) {
                    // Search for file by basename.
                    $child = $file_model->getByPath($name);
                    if ($child)
                        $log .= "Found file record by name: $name.\n";
                }
                //TODO Search for the file on Commons based on its basename;
                if ($child) {
                    // Save path for new XML.
                    if (empty($child['output_path'])) {
                        $fileData['output_path'] = $child['source_path'];
                        $fileData['proxy_path'] = config_item('upload_path') . $child['source_path'];
                        $fileData['resource'] = '$CURRENTPATH/' . basename($child['source_path']);
                        $fileData['file_hash'] = $child['source_hash'];
                    } else {
                        $fileData['output_path'] = $child['output_path'];
                        $fileData['proxy_path'] = config_item('transcode_path') . $child['output_path'];
                        $fileData['resource'] = '$CURRENTPATH/' . basename($child['output_path']);
                        $fileData['file_hash'] = $child['output_hash'];
                    }
                } else {
                    $isValid = false;
                }
            } else {
                // This file is not necessary and the corresponding
                // kdenlive_producer can be removed from the XML to
                // remove unneeded dependencies.
                $log .= "Found unnecessary file: $name.\n";
            }
        }
        return $isValid;
    }

    /**
     * Supplement the external files with metadata from the proxy file to
     * feed into the MltXmlWriter.
     *
     * @param array $childFiles A reference to the array of external files
     * @param string $log A reference to a string for logging
     * @see MltXmlHelper::getFilesData()
     */
    public static function getFileMetadata(&$childFiles, &$log)
    {
        foreach($childFiles as $fileName => &$fileData) {
            $xml = shell_exec("/usr/bin/nice melt -consumer xml '$fileData[proxy_path]' 2>/dev/null");
            $mlt = simplexml_load_string($xml);
            if ($mlt && isset($mlt->producer)) {
                $streamType = null;
                foreach ($mlt->producer->property as $property) {
                    if (strpos($property['name'], '.codec.pix_fmt') !== false)
                        $fileData['pix_fmt'] = (string) $property;
                    else if (strpos($property['name'], '.codec.colorspace') !== false)
                        $fileData['colorspace'] = (string) $property;
                    else if ($property['name'] === 'length')
                        $fileData['duration'] = (string) $property;
                    else if (strpos($property['name'], '.stream.type') !== false)
                        $streamType = (string) $property;
                    else if (strpos($property['name'], '.codec.name') !== false && $streamType === 'video')
                        $fileData['videocodecid'] = (string) $property;
                    else if (strpos($property['name'], '.codec.long_name') !== false && $streamType)
                        $fileData[$streamType.'codec'] = (string) $property;
                }
            }
            $fileData['file_size'] = filesize($fileData['proxy_path']);
            $fileData['progressive'] = 1;
        }
    }

    /**
     * Replace proxy file references in MLT XML with paths to the original files
     * prior to rendering.
     *
     * @param object $file_model A reference to a File model
     * @param array $file A file record
     * @param array $childFiles A reference to the array of file external files
     * @param string $log A reference to a string for logging
     * @return bool Whether there are missing files
     */
    public static function substituteOriginalFiles(&$file_model, $file, &$childFiles, &$log)
    {
        $isValid = true;
        foreach($childFiles as $fileName => &$fileData) {
            $name = basename($fileName);
            if (isset($fileData['mlt_service'])) {
                $child = null;
                $log .= "Found file in XML with name: $name.\n";
                if (!empty($fileData['file_hash'])) {
                    // Search for file by hash.
                    $child = $file_model->getByHash($fileData['file_hash']);
                    if ($child)
                        $log .= "Found file record by its hash: $fileData[file_hash].\n";
                }
                if (!$child) {
                    // Search for file by basename.
                    $child = $file_model->getByPath($name);
                    if ($child)
                        $log .= "Found file record by name: $name.\n";
                }
                //TODO Search for the file on Commons based on its basename;
                if ($child) {
                    // Save path for new XML.
                    if (!empty($child['source_path'])) {
                        $fileData['output_path'] = $child['source_path'];
                        $fileData['proxy_path'] = config_item('upload_path') . $child['source_path'];
                        $fileData['resource'] = config_item('upload_path') . $child['source_path'];
                        $fileData['file_hash'] = $child['source_hash'];
                    } else if (!empty($child['output_path'])) {
                        $fileData['output_path'] = $child['output_path'];
                        $fileData['proxy_path'] = config_item('transcode_path') . $child['output_path'];
                        $fileData['resource'] = config_item('transcode_path') . $child['output_path'];
                        $fileData['file_hash'] = $child['output_hash'];
                    } else {
                        $log .= "Missing file: $name.\n";
                        $isValid = false;
                    }
                } else {
                    $isValid = false;
                }
            } else {
                // This file is not necessary and the corresponding
                // kdenlive_producer can be removed from the XML to
                // remove unneeded dependencies.
                $log .= "Found unnecessary file: $name.\n";
            }
        }
        return $isValid;
    }
}
