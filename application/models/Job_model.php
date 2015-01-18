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
    /** @var Job type constant for validating an uploaded media file */
    const TYPE_VALIDATE      = 0;
    /** @var Job type constant for transcoding a proxy media file */
    const TYPE_TRANSCODE     = 1;
    /** @var Job type constant to render and encode a project file */
    const TYPE_RENDER        = 2;

    /** Construct a Job CodeIgniter Model */
    public function __construct()
    {
        $this->load->database();
        log_message('debug', 'job Model Class Initialized');
    }

    /**
     * Create a job record.
     *
     * @param int $file_id File record ID
     * @param int $type    A job type constant from this class
     * @return int|bool Job record ID or false on error
     */
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

    /**
     * Get a job record by its ID.
     *
     * @param int $job_id Job record ID
     * @return array
     */
    function getWithFileById($job_id)
    {
        $this->db->select('job.id as job_id, file_id, type, progress, result, job.updated_at as job_updated_at, file.*');
        $this->db->join('file', 'file_id = file.id');
        $this->db->where('job.id', $job_id);
        $query = $this->db->get('job');
        return $query->row_array();
    }

    /**
     * Update a job record.
     *
     * @param int $job_id Job record ID
     * @param array $data The job record data as an associative array
     * @return bool False on error
     */
    function update($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update('job', $data);
    }

    /**
     * Get a job record by file ID and type of job.
     *
     * @param int $id File record ID
     * @param int $jobType A job type constant from this class
     * @return array
     */
    function getByFileIdAndType($id, $jobType)
    {
        return $this->db->select('id, progress, result')
                        ->get_where('job', ['file_id' => $id, 'type' => $jobType])
                        ->order_by('updated_at', 'DESC')
                        ->limit(1)
                        ->row_array();
    }

    /**
     * Get a job's output log.
     *
     * @param int $id Job record ID
     * @return string|null
     */
    function getLog($id)
    {
        $row = $this->db->select('log')->get_where('job', ['id' => $id])->row();
        if ($row)
            return $row->log;
        else
            return null;
    }
}
