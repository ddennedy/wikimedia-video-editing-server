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

class Job_model extends CI_Model
{
    /// If either registration is required or if registered user was demoted.
    const TYPE_VALIDATE      = 0;
    /// Can create and update data.
    const TYPE_TRANSCODE     = 1;
    /// Can also delete data and demote user to guest.
    const TYPE_RENDER        = 2;

    public function __construct()
    {
        $this->load->database();
        log_message('debug', 'job Model Class Initialized');
    }

    public function create($file_id, $type)
    {
        $result = $this->db->insert('job', [
            'file_id' => $file_id,
            'type' => $type
        ]);
        if ($result)
            return $this->db->insert_id();
        else
            return false;
    }

    function getWithFileById($job_id)
    {
        $this->db->join('file', 'file_id = file.id');
        $this->db->where('job.id', $job_id);
        $query = $this->db->get('job');
        return $query->row_array();
    }

    function update($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update('job', $data);
    }
}
