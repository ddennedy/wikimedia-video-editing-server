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

class MltXmlHelper {

    public static function isXmlWellFormed($xml)
    {
        libxml_use_internal_errors(true);
        return simplexml_load_string($xml) !== false;
    }

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
                    $file_hash = empty($child['output_path'])? $child['source_hash'] : $child['output_hash'];
                    if ($file_model->addMissing($file['id'], $name, $file_hash))
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
