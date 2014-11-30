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

class Job extends CI_Controller
{
    protected $running = false;

    public function __construct()
    {
        parent::__construct();
        $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
        $this->load->model('job_model');
    }

    public function index()
    {
        echo "Use the validate or encode methods.\n";
    }

    protected function signalHandler($signal)
    {
        $this->running = false;
        echo "interrupt received\n";
    }

    public function validate()
    {
        if ($this->beanstalk->connect()) {
            $tube = config_item('beanstalkd_tube_validate');
            $this->beanstalk->useTube($tube);
            $this->beanstalk->watch($tube);
            $this->running = true;
            pcntl_signal(SIGINT, array(&$this, 'signalHandler'));
            pcntl_signal(SIGTERM, array(&$this, 'signalHandler'));

            while ($this->running) {
                $job = $this->beanstalk->reserve();
                $job_id = $job['body'];
                if ($job) {
                    echo "received job id $job_id\n";
                    // lookup job/file in database
                    $file = $this->job_model->getWithFileById($job_id);
                    if ($file && !empty($file['source_path'])) {
                        $filename = config_item('upload_path') . $file['source_path'];
                        // TODO verify file still exists
                        $extension = strrchr($file['source_path'], '.');
                        $extension = ($extension !== false)? strtolower($extension) : '';

                        // Get the MIME type.
                        $mimeType = $this->getMimeType($file);
                        if (!empty($mimeType)) {
                            $isValid = true;
                            $majorType = explode('/', $mimeType)[0];
                            echo "majorType: $majorType\n";
                            if ($majorType === 'audio' || $majorType === 'video') {
                                $isValid = $this->validateAudioVideo($file, $filename, $majorType);
                            } else if ($majorType === 'image' || $extension === '.svg') {
                                // if image verify melt can read it

                            } else if ($mimeType === 'application/xml' ||
                                       $mimeType === 'text/xml' ||
                                       $mimeType === 'application/x-kdenlive' ||
                                       $mimeType === 'application/mlt+xml') {
                                // if mlt xml, verify melt can read it
                                //   verify all dependent files are available
                                //   create records in file_relations table for all dependent files
                                //   todo: how to show missing dependencies in database
                                //   if valid with all dependencies, create render job

                            } else {
                                //TODO flag this somehow as possible invalid, let
                                // the user manually approve it as a supplemental file
                                // needed by the project
                            }
                            // TODO remove testing exit
                            $this->beanstalk->release($job['id'], 10, 0);
    $this->beanstalk->disconnect();
    return;
                        } else {
                            echo "Error: failed to get MIME type for $filename\n";
                        }
                    }
                    // delete this job
                    //$this->beanstalk->delete($job['id']);
                    $this->beanstalk->release($job['id'], 10, 0);
                } else {
                    echo "Error: beanstalkd reserve failed\n";
                }
//                 $priority = 10;
//                 $delay = 0;
//                 $this->beanstalk->release($job['id'], $priority, $delay);
                sleep(1);
            };

            $this->beanstalk->disconnect();
        }
    }

    protected function getMimeType($file)
    {
        $mimeType = '';
        if (!empty($file['mime_type'])) {
            $mimeType = $file['mime_type'];
        } else {
            $this->load->helper('file');
            $mimeType = get_mime_by_extension($file['source_path']);
            if (empty($mimeType)) {
                $filename = config_item('upload_path') . $file['source_path'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $filename);
                finfo_close($finfo);
                //$mimeType = trim(shell_exec("file --brief --mime-type '$filename'"));
            }
        }
        return strtolower($mimeType);
    }

    protected function getFileHash($filename)
    {
        // This is the algorithm Kdenlive uses in DocClipBase::getHash().
        $MB = 1000 * 1000;
        if (filesize($filename) < $MB) {
            $hash = md5_file($filename);
        } else {
            // Use first and last MB
            $f = fopen($filename, 'rb');
            if ($f !== false) {
                $head = fread($f, $MB);
                fseek($f, -1 * $MB, SEEK_END);
                $tail = fread($f, $MB);
                fclose($f);
                $hash = md5($head . $tail);
            } else {
                $hash = false;
            }
        }
        return $hash;
    }

    protected function validateAudioVideo($file, $filename, $majorType)
    {
        $isValid = true;
        // if audio or video, verify ffprobe can read it
        $json = shell_exec("/usr/bin/nice ffprobe -print_format json -show_error -show_format -show_streams '$filename' 2>/dev/null");
        if (!empty($json)) {
            // verify tracks match codec_type
            $ffprobe = json_decode($json);
            $isValid = false;
            foreach ($ffprobe->streams as $stream) {
                if (isset($stream->codec_type) && $stream->codec_type === $majorType) {
                    $isValid = true;
                    break;
                }
            }
            echo "track status: $isValid\n";

            // get duration
            $duration = null;
            if (isset($ffprobe->format) && isset($ffprobe->format->duration)) {
                $duration = intval(round($ffprobe->format->duration * 1000));
                echo "duration: $duration\n";
                if ($duration <= 0) {
                    echo "Error: invalid duration: $filename\n";
                    $isValid = false;
                }
            } else {
                echo "Error: failed to get the duration of $majorType: $filename\n";
            }

            // if valid, compute hash
            $hash = $this->getFileHash($filename);
            // Get ffprobe JSON again with human-readable units for the database.
            $json = shell_exec("/usr/bin/nice ffprobe -print_format json -pretty -show_error -show_format -show_streams '$filename' 2>/dev/null");
            // put new data into database
            $this->load->model('file_model');
            $this->file_model->staticUpdate($file['id'], [
                'duration_ms' => $duration,
                'source_hash' => $hash,
                'properties' => $json,
                'status' => intval($file['status']) | File_model::STATUS_VALID
            ]);

            //   if valid, create transcode job
            $job_id = $this->job_model->create($file['id'], Job_model::TYPE_TRANSCODE);
            if ($job_id) {
                // Put job into the queue.
                $tube = config_item('beanstalkd_tube_transcode');
                $this->beanstalk->useTube($tube);
                $priority = 10;
                $delay = 0;
                $ttr = 60; // seconds
                $jobId = $this->beanstalk->put($priority, $delay, $ttr, $job_id);
                $tube = config_item('beanstalkd_tube_validate');
                $this->beanstalk->useTube($tube);
            }
        } else {
            $isValid = false;
        }
        return $isValid;
    }

    public function stats()
    {
        if ($this->beanstalk->connect()) {
            print_r($this->beanstalk->stats());
            $this->beanstalk->disconnect();
        }
    }

    public function test($id)
    {
        $job = $this->job_model->getWithFileById($id);
        if ($job)
            print_r($job);
        else
            show_404(uri_string());
    }
}
