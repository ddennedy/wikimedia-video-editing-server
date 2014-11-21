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

class File_model extends CI_Model
{
    public function __construct()
    {
        $this->load->database();
        log_message('debug', 'File Model Class Initialized');
    }

    public function getById($id = null)
    {
        if ($id) {
            $query = $this->db->get_where('file', ['id' => $id]);
            $data = $query->row_array();
        } else {
            $data = [
                'id' => null,
                'title' => null,
                'author' => null,
                'description' => null,
                'keywords' => null,
                'properties' => null,
                'recording_date' => null,
                'language' => null,
                'license' => null,
            ];
        }
        return $data;
    }

    public function create($data)
    {
        // Insert into main file table.
        $this->db->insert('file', $data);
        if ($this->db->affected_rows()) {
            $id = $this->db->insert_id();

            // Add to recent table.
            $this->updateRecent($id);

            // Add to the search index.
            $this->db->insert('searchindex', [
                'file_id' => $id,
                'title' => $data['title'],
                'description' => $data['description'],
                'author' => $data['author']
            ]);

            return $id;
        } else {
            return null;
        }
    }

    public function update($id, $data)
    {
        // Update the file table.
        $this->db->where('id', $id);
        $result = $this->db->update('file', $data);
        if ($result) {
            // Add to recent table.
            $this->updateRecent($id);

            // Update the search index.
            $this->db->where('file_id', $id);
            $this->db->update('searchindex', [
                'title' => $data['title'],
                'description' => $data['description'],
                'author' => $data['author']
            ]);
        }
        return $result;
    }

    public function getLicenses()
    {
        return [
            'self|GFDL|cc-by-sa-all|migration=redundant' => tr('license_self|GFDL|cc-by-sa-all|migration=redundant'),
            'self|Cc-zero' => tr('license_self|Cc-zero'),
            'PD-self' => tr('license_PD-self'),
            'self|GFDL|cc-by-sa-3.0|migration=redundant' => tr('license_self|GFDL|cc-by-sa-3.0|migration=redundant'),
            'self|GFDL|cc-by-3.0|migration=redundant' => tr('license_self|GFDL|cc-by-3.0|migration=redundant'),
            'self|cc-by-sa-3.0' => tr('license_self|cc-by-sa-3.0'),
            'cc-by-sa-4.0' => tr('license_cc-by-sa-4.0'),
            'cc-by-sa-3.0' => tr('license_cc-by-sa-3.0'),
            'cc-by-4.0' => tr('license_cc-by-4.0'),
            'cc-by-3.0' => tr('license_cc-by-3.0'),
            'Cc-zero' => tr('license_Cc-zero'),
            'FAL' => tr('license_FAL'),
            'PD-old-100' => tr('license_PD-old-100'),
            'PD-old-70-1923' => tr('license_PD-old-70-1923'),
            'PD-old-70|Unclear-PD-US-old-70' => tr('license_PD-old-70|Unclear-PD-US-old-70'),
            'PD-US' => tr('license_PD-US'),
            'PD-USGov' => tr('license_PD-USGov'),
            'PD-USGov-NASA' => tr('license_PD-USGov-NASA'),
            'PD-USGov-Military-Navy' => tr('license_PD-USGov-Military-Navy'),
            'PD-ineligible' => tr('license_PD-ineligible'),
            'Copyrighted free use' => tr('license_Copyrighted free use'),
            'Attribution' => tr('license_Attribution'),
            'subst:uwl' => tr('license_subst:uwl')
        ];
    }

    public function getLicenseByKey($licenseKey)
    {
        $licenses = $this->getLicenses();
        if (array_key_exists($licenseKey, $licenses))
            return $licenses[$licenseKey];
        else
            $licenses['subst:uwl'];
    }

    private function getMostRecent()
    {
        $this->db->select('id, file_id');
        $this->db->order_by('updated_at', 'desc');
        $this->db->limit(1);
        $query = $this->db->get('recent');
        return ($query->num_rows() > 0)? $query->row() : null;
    }

    public function updateRecent($id)
    {
        $recent = $this->getMostRecent();
        if (!$recent || $id != $recent->file_id) {
            $this->db->insert('recent', ['file_id' => $id]);
        } else {
            // Just update the most recent one so timestamp update.
            $this->db->where('id', $recent->id);
            $this->db->update('recent', ['updated_at' => null]);
        }
    }

    public function getRecent()
    {
        $this->db->select('file_id, title, name, created_at');
        $this->db->from('recent');
        $this->db->join('file', 'file.id = recent.file_id');
        $this->db->join('user', 'file.user_id = user.id');
        $this->db->order_by('recent.updated_at', 'desc');
        $this->db->limit($this->config->item('recent_limit'));
        $query = $this->db->get();
        return $query->result_array();
    }
}
