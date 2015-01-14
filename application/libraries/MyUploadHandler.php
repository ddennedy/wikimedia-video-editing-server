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
require APPPATH.'libraries/UploadHandler.php';

/** A derived class of the jQuery File Upload Plugin provided UploadHandler */
class MyUploadHandler extends UploadHandler
{
    /**
     * An associative array containing all uploaded files, keyed by form field name
     * @var array
     */
    public $result;

    /**
     * Construct the UploadHandler class and immediately process the HTTP request.
     *
     * @param array $options UploadHandler options
     * @see UploadHandler::__construct()
     */
    public function __construct($options = null)
    {
        parent::__construct($options, false);
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'OPTIONS':
            case 'HEAD':
                $this->head();
                break;
            case 'GET':
                $this->get();
                break;
            case 'PATCH':
            case 'PUT':
            case 'POST':
                $this->result = $this->post();
                break;
            case 'DELETE':
                $this->delete();
                break;
            default:
                $this->header('HTTP/1.1 405 Method Not Allowed');
        }
    }

    /**
     * Make a filename unique by appending a paranthesized number to base name
     * if needed.
     *
     * @param string $name Full path to the file
     * @return string
     */
    public function getUniqueFilename($name)
    {
        while (file_exists($name)) {
            $name = $this->upcount_name($name);
        }
        return $name;
    }

    /**
     * Move/rename a file, making the directory in the new name, as needed.
     *
     * @param string $oldName Old file name
     * @param string $newName The new file name
     * @return bool False on error
     */
    public function moveFile($oldName, $newName)
    {
        $dir = dirname($newName);
        if (!is_dir($dir)) {
            mkdir($dir, $this->options['mkdir_mode'], true);
        }
        return rename($oldName, $newName);
    }
}
