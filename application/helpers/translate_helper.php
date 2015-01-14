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

if (!function_exists('tr'))
{
    /**
     * Get the translated string for a given string key.
     *
     * @param string $line The translation table lookup key
     * @param array $data An associate array of data for some strings that include
     * variables for substitution
     * @return string
     */
    function tr($line, $data = null)
    {
        $s = get_instance()->lang->line($line);
        if ($s && is_array($data)) {
            get_instance()->load->library('parser');
            $s = get_instance()->parser->parse_string($s, $data, true);
        }
        return $s;
    }
}
