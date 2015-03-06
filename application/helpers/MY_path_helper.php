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
 * Determine if a path is relative (instead of empty or absolute).
 *
 * @param string $path The path to check
 * @return bool
 */
function isPathRelative($path)
{
    return (!empty($path) && $path[0] !== '/' && strpos($path, ':\\') !== 1 && strpos($path, ':/') !== 1);
}

/**
 * Combine two strings containing path information, adding a slash if needed.
 *
 * @param string $path1 The first part of the path
 * @param string $path2 The second part of the path
 * @return string Both paths combined with a slash
 */
function joinPaths($path1, $path2)
{
    $last = $path1[strlen($path1) - 1];
    if ($last !== '\\' && $last !== '/')
        $path1 .= (strpos($path1.$path2, '\\') !== false)? '\\' : '/';
    return $path1 . $path2;
}

/**
 * Get a file name extension.
 *
 * @param string $path The file name, optionally with path
 * @return string The extension or empty string if no extension, in lower case
 * without the leading dot.
 */
function getExtension($path)
{
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    return ($extension !== false)? strtolower($extension) : '';
}
