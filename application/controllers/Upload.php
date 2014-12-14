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

class Upload extends CI_Controller
{
    public function index($file_id = null)
    {
        if ('POST' == $this->input->method(true) && $file_id) {
            $this->load->model('file_model');
            $this->load->library('MyUploadHandler', [
                'upload_dir' => config_item('upload_path'),
                'upload_url' => base_url('uploads') . '/', // This is actually for making a download url.
                'image_versions' => array()
            ]);
            $file = $this->myuploadhandler->result['files'][0];

            // UploadHandler sets $file->url when the download is complete.
            if (isset($file->url)) {
                if (strlen($file->name) > 2) {
                    // Change the file name to something more useful.
                    $record = $this->file_model->getById($file_id);
                    if (!empty($record['title'])) {
                        // Use CodeIgniter's url_title() to convert the file's title
                        // into a filename.
                        $extension = strrchr($file->name, '.');
                        $extension = ($extension !== false)? strtolower($extension) : '';
                        $this->load->helper('text');
                        $lowerCase = true;
                        $maxLength = 250;
                        $basename = character_limiter(url_title($record['title'], '-', $lowerCase), $maxLength);
                        // Put file into sub-folder to make directories more manageable.
                        $name = "$basename[0]/$basename[1]/$basename$extension";
                        $fullname = config_item('upload_path') . $name;
                        $fullname = $this->myuploadhandler->getUniqueFilename($fullname);
                        if ($this->myuploadhandler->moveFile(
                                config_item('upload_path') . $file->name, $fullname)) {
                            $file->name = str_replace(config_item('upload_path'), '', $fullname);
                        }
                    }
                }

                // Add a job to the database.
                $this->load->model('job_model');
                $job_id = $this->job_model->create($file_id, Job_model::TYPE_VALIDATE);
                if ($job_id) {
                    // Put job into the queue.
                    $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
                    if ($this->beanstalk->connect()) {
                        $tube = config_item('beanstalkd_tube_validate');
                        $this->beanstalk->useTube($tube);
                        $priority = 10;
                        $delay = 3;
                        $ttr = 60; // seconds
                        $jobId = $this->beanstalk->put($priority, $delay, $ttr, $job_id);
                        $this->beanstalk->disconnect();
                    }
                }
            }

            // Collect data updates for database.
            $data = [
                'source_path' => $file->name,
                'size_bytes' => $file->total_size,
                'mime_type' => $file->type,
                'status' => File_model::STATUS_UPLOADED
            ];

            // If revised project file, update file table with revision.
            if (isset($file->url) && !empty($record['source_path']) && $this->isMimeTypeMltXml($file->type)) {
                $data = array_merge($data, [
                    'user_id' => $this->session->userdata('userid'),
                    'title' => $record['title'],
                    'author' => $record['author'],
                    'description' => $record['description'],
                    'language' => $record['language'],
                    'license' => $record['license'],
                    'recording_date' => $record['recording_date'],
                    'keywords' => $record['keywords']
                ]);
                $this->file_model->update($file_id, $data, tr('file_upload_revision'));
            } else {
                // Put the filename into the database without making a revision.
                $this->file_model->staticUpdate($file_id, $data);
            }
        } else {
            set_status_header('405');
        }
    }

    protected function isMimeTypeMltXml($mimeType)
    {
        return $mimeType === 'application/xml' ||
               $mimeType === 'text/xml' ||
               $mimeType === 'application/x-kdenlive' ||
               $mimeType === 'application/mlt+xml';
    }
}
