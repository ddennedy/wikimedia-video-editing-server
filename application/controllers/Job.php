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
    /**
     * Control a worker's main loop.
     *
     * @access protected
     * @var bool
     */
    protected $running = false;

    /** The size of a chunk to use for chunked upload to Wikimedia Commons.
     *
     * @access protected
     * @var int
     */
    protected $chunkSize = 20971520; // 20 MiB

    /** Construct a Job CodeIgniter Controller. */
    public function __construct()
    {
        parent::__construct();
        $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
        $this->load->model('job_model');
        $this->load->model('file_model');
        $this->load->library('InternetArchive', $this->config->config);
    }

    /**
     * Show a helpful message when no method is supplied.
     */
    public function index()
    {
        echo "Use the validate or encode methods.\n";
    }

    /**
     * The process signal callback for terminating a worker's main loop.
     *
     * @param int $signal The signal number received.
     */
    protected function signalHandler($signal)
    {
        $this->running = false;
        echo "interrupt received\n";
    }

    /**
     * The validate worker that validates media file uploads.
     */
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
                        $extension = strrchr($file['source_path'], '.');
                        $extension = ($extension !== false)? strtolower($extension) : '';

                        // Get the MIME type.
                        $mimeType = $file['mime_type'];
                        if (!empty($mimeType)) {
                            // Restore file from archive if needed.
                            $filename = config_item('upload_path') . $file['source_path'];
                            if (!filesize($filename)) {
                                $log .= "Restoring from archive: $filename.\n";
                                $status = $this->internetarchive->download($file['id'], $filename);
                                $this->beanstalk->touch($job['id']);
                                if ($status !== true) {
                                    $priority = 10;
                                    $delay = 3;
                                    $this->beanstalk->release($job['id'], $priority, $delay);
                                    sleep(60);
                                    continue;
                                }
                            }
                            if (filesize($filename)) {
                                $isValid = true;
                                $toArchive = true;
                                $majorType = explode('/', $mimeType)[0];
                                echo "mimeType: $mimeType\n";
                                if ($majorType === 'audio' ||
                                    $majorType === 'video' ||
                                    $mimeType  === 'application/mxf') {
                                    $isValid = $this->validateAudioVideo($job_id, $file, $majorType);
                                    $toArchive = !$isValid;
                                } else if ($majorType === 'image' || $extension === '.svg') {
                                    $isValid = $this->validateImage($job_id, $file, $majorType);
                                } else if ($mimeType === 'application/xml' ||
                                        $mimeType === 'text/xml' ||
                                        $mimeType === 'application/x-kdenlive' ||
                                        $mimeType === 'application/mlt+xml') {
                                    // if mlt xml, verify melt can read it
                                    $isValid = $this->validateMLTXML($job_id, $file, $majorType);
                                    $toArchive = !$isValid;
                                } else {
                                    //TODO flag this somehow as possible invalid, let
                                    // the user manually approve it as a supplemental file
                                    // needed by the project
                                    $log = "Validate: $file[source_path].\n";
                                    $log .= "Unhandled MIME type: $mimeType.\n";
                                    $file['source_hash'] = $this->getFileHash($filename);
                                    if ($file['source_hash'] === false) {
                                        $log .= "Failed to compute MD5 hash.\n";
                                    } else {
                                        $file['status'] = intval($file['status']) | File_model::STATUS_VALIDATED | File_model::STATUS_FINISHED;
                                        // Clear any previous error in case this was re-attempted.
                                        $file['status'] &= ~File_model::STATUS_ERROR;
                                        $result = $this->file_model->staticUpdate($file['id'], [
                                            'source_hash' => $file['source_hash'],
                                            'status' => $file['status']
                                        ]);
                                        if (!$result)
                                            $log .= "Error updating the file table with hash and status.\n";
                                    }
                                    $this->job_model->update($job_id, [
                                        'progress' => 100,
                                        'result' => 0,
                                        'log' => $log
                                    ]);
                                }
                                if (!empty($file['source_hash']))
                                    $this->checkIfWasMissing($file);
                                if ($toArchive) {
                                    $archiveJobId = $this->createArchiveJob($file['id']);
                                    $this->beanstalk->useTube(config_item("beanstalkd_tube_validate"));
                                }
                            } else {
                                echo "Error, invalid file: $filename\n";
                            }
                        } else {
                            echo "Error: failed to get MIME type for $filename\n";
                        }
                    }
                    // delete this job
                    $this->beanstalk->delete($job['id']);
                } else {
                    echo "Error: beanstalkd reserve failed\n";
                }
                sleep(1);
            }

            $this->beanstalk->disconnect();
        } else {
            echo "Error: failed to connect to beanstalkd\n";
        }
    }

    /**
     * Return the MD5 hash a file on the filesystem.
     *
     * This is the algorithm Kdenlive uses in DocClipBase::getHash(). It only
     * uses the first and last 1MB of a file larger than 2 MB.
     *
     * @param string $filename The full path to the file.
     * @return string
     */
    protected function getFileHash($filename)
    {
        $MB = 1000 * 1000;
        if (filesize($filename) <= 2 * $MB) {
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

    /**
     * Validate the audio/video file associated with a file record using ffprobe.
     *
     * Enqueues a transcode job if valid and not already Ogg or WebM.
     *
     * @param int $job_id The current job's ID
     * @param array $file The file record, passed by reference
     * @param string $majorType The first portion of the file's MIME type
     * @return bool Indicates validity
     */
    protected function validateAudioVideo($job_id, &$file, $majorType)
    {
        $isValid = true;
        $log = "Validate: $file[source_path].\n";
        $filename = config_item('upload_path') . $file['source_path'];

        // if audio or video, verify ffprobe can read it
        $json = shell_exec("/usr/bin/nice ffprobe -print_format json -show_error -show_format -show_streams '$filename' 2>/dev/null");
        if (!empty($json)) {
            // verify tracks match codec_type
            $ffprobe = json_decode($json);
            $isValid = false;
            if (isset($ffprobe->streams)) {
                foreach ($ffprobe->streams as $stream) {
                    if (isset($stream->codec_type) && $stream->codec_type === $majorType) {
                        $log .= "ffprobe found a stream with codec_type \"$stream->codec_type\" that matches the MIME type.\n";
                        $isValid = true;
                        break;
                    }
                }
            }
            if (!$isValid)
                $log .= "ffprobe did not find a valid stream in this file.\n";

            // get duration
            $file['duration_ms'] = null;
            if (isset($ffprobe->format) && isset($ffprobe->format->duration)) {
                $file['duration_ms'] = intval(round($ffprobe->format->duration * 1000));
                $log .= "ffprobe found a duration of $file[duration_ms] ms.\n";
                if ($file['duration_ms'] <= 0) {
                    $log .= "Error: invalid duration: $filename.\n";
                    $isValid = false;
                }
            } else {
                $log .= "Error: failed to get the duration of $majorType: $filename.\n";
                $isValid = false;
            }

            // if valid, compute hash
            if ($isValid) {
                $file['source_hash'] = $this->getFileHash($filename);
                if ($file['source_hash'] === false)
                    $log .= "Failed to compute MD5 hash.\n";

                // Get ffprobe JSON again with human-readable units for the database.
                $json = shell_exec("/usr/bin/nice ffprobe -print_format json -pretty -show_error -show_format -show_streams '$filename' 2>/dev/null");
                $file['properties'] = json_encode(['ffprobe' => json_decode($json)]);

                $file['status'] = intval($file['status']) | File_model::STATUS_VALIDATED;
                // Clear any previous error in case this was re-attempted.
                $file['status'] &= ~File_model::STATUS_ERROR;

                // put new data into database
                $result = $this->file_model->staticUpdate($file['id'], [
                    'duration_ms' => $file['duration_ms'],
                    'source_hash' => $file['source_hash'],
                    'properties' => $file['properties'],
                    'status' => $file['status']
                ]);
                if (!$result)
                    $log .= "Error updating the file table with duration, hash, and status.\n";

                // if valid, create transcode job
                $transcodeJobId = $this->job_model->create($file['id'], Job_model::TYPE_TRANSCODE);
                if ($transcodeJobId) {
                    // Put job into the queue.
                    $tube = config_item('beanstalkd_tube_transcode');
                    $this->beanstalk->useTube($tube);
                    $priority = 10;
                    $delay = 3;
                    $ttr = config_item('beanstalkd_timeout'); // seconds
                    $jobId = $this->beanstalk->put($priority, $delay, $ttr, $transcodeJobId);
                    $tube = config_item('beanstalkd_tube_validate');
                    $this->beanstalk->useTube($tube);
                    $log .= "Created transcode job with ID $transcodeJobId.\n";
                } else {
                    $log .= "Error creating transcode job on beanstalkd.\n";
                }
            }
        } else {
            $log .= "Error: ffprobe failed to produce any output.\n";
            $isValid = false;
        }
        if (!$isValid) {
            $result = $this->file_model->staticUpdate($file['id'], [
                'status' => intval($file['status']) | File_model::STATUS_VALIDATED | File_model::STATUS_ERROR
            ]);
            if (!$result)
                $log .= "Error updating the file table with error status.\n";
        }
        $this->job_model->update($job_id, [
            'progress' => 100,
            'result' => ($isValid? 0 : 1),
            'log' => $log
        ]);
        return $isValid;
    }

    /**
     * Validate the image file associated with a file record using melt.
     *
     * @param int $job_id The current job's ID
     * @param array $file The file record, passed by reference
     * @param string $majorType The first portion of the file's MIME type
     * @return bool Indicates validity
     */
    protected function validateImage($job_id, &$file, $majorType)
    {
        // verify melt can read it
        $isValid = true;
        $log = "Validate: $file[source_path].\n";
        $filename = config_item('upload_path') . $file['source_path'];

        // if image, verify melt can read it
        $xml = shell_exec("/usr/bin/nice melt -consumer xml '$filename' 2>/dev/null");
        if (!empty($xml)) {
            // verify mlt_consumer is pixbuf or qimage
            libxml_use_internal_errors(true);
            $mlt = simplexml_load_string($xml);
            if ($mlt) {
                $isValid = false;
                foreach ($mlt->producer->property as $property) {
                    if (isset($property['name']) && $property['name'] == 'mlt_service') {
                        if ($property == 'pixbuf' || $property == 'qimage') {
                            $log .= "melt found a producer with mlt_service \"$property\" for the $majorType MIME tupe.\n";
                            $isValid = true;
                            break;
                        }
                    }
                }
                if (!$isValid)
                    $log .= "melt loaded this image file with an unexpected producer.\n";
            } else {
                $isValid = false;
                $log .= "melt did not produce well-formed XML.\n";
            }

            // if valid, compute hash
            if ($isValid) {
                $file['source_hash'] = $this->getFileHash($filename);
                if ($file['source_hash'] === false)
                    $log .= "Failed to compute MD5 hash.\n";

                $file['properties'] = json_encode(['MLTXML' => $xml]);
                $file['status'] = intval($file['status']) | File_model::STATUS_VALIDATED | File_model::STATUS_FINISHED;
                // Clear any previous error in case this was re-attempted.
                $file['status'] &= ~File_model::STATUS_ERROR;

                // put new data into database
                $result = $this->file_model->staticUpdate($file['id'], [
                    'source_hash' => $file['source_hash'],
                    'properties' => $file['properties'],
                    'status' => $file['status']
                ]);
                if (!$result)
                    $log .= "Error updating the file table with hash and status.\n";
            }
        } else {
            $log .= "Error: melt is unable to load this file.\n";
            $isValid = false;
        }
        if (!$isValid) {
            $result = $this->file_model->staticUpdate($file['id'], [
                'status' => intval($file['status']) | File_model::STATUS_VALIDATED | File_model::STATUS_ERROR
            ]);
            if (!$result)
                $log .= "Error updating the file table with error status.\n";
        }
        $this->job_model->update($job_id, [
            'progress' => 100,
            'result' => ($isValid? 0 : 1),
            'log' => $log
        ]);
        return $isValid;
    }

     /**
     * Validate an XML project file associated with a file record using melt.
     *
     * Enqueues a render job if the file is valid with all external files available.
     *
     * @param int $job_id The current job's ID
     * @param array $file The file record, passed by reference
     * @param string $majorType The first portion of the file's MIME type
     * @return bool Indicates validity
     */
   protected function validateMLTXML($job_id, &$file, $majorType)
    {
        // verify melt can load it
        $isValid = true;
        $log = "Validate: $file[source_path].\n";
        $filename = config_item('upload_path') . $file['source_path'];

        // if MLT XML, verify melt can read it
        $xml = shell_exec("/usr/bin/nice melt -consumer xml '$filename' 2>/dev/null");
        if (!empty($xml)) {
            $this->load->library('MltXmlHelper');
            if ($this->mltxmlhelper->isXmlWellFormed($xml)) {
                // verify all dependent files are available
                $childFiles = $this->mltxmlhelper->getFilesData($filename, $log);
                $log .= "getFilesData:\n" . json_encode($childFiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
                $isValid = $this->mltxmlhelper->checkFileReferences($this->file_model, $file, $childFiles, $log);

                // If still valid, create the render job.
                if ($isValid) {
                    $renderJobId = $this->job_model->create($file['id'], Job_model::TYPE_RENDER);
                    if ($renderJobId) {
                        // Put job into the queue.
                        $tube = config_item('beanstalkd_tube_render');
                        $this->beanstalk->useTube($tube);
                        $priority = 10;
                        $delay = 3;
                        $ttr = config_item('beanstalkd_timeout'); // seconds
                        $jobId = $this->beanstalk->put($priority, $delay, $ttr, $renderJobId);
                        $tube = config_item('beanstalkd_tube_validate');
                        $this->beanstalk->useTube($tube);
                        $log .= "Created render job with ID $renderJobId.\n";
                    } else {
                        $log .= "Error creating render job on beanstalkd.\n";
                    }
                }
            } else {
                $isValid = false;
                $log .= "Error: the XML is not well formed.\n";
            }
        } else {
            $log .= "Error: melt failed to run.\n";
            $isValid = false;
        }
        $file['status'] = intval($file['status']) | File_model::STATUS_VALIDATED;
        if ($isValid)
            // Clear any previous error in case this was re-attempted.
            $file['status'] &= ~File_model::STATUS_ERROR;
        else
            $file['status'] |= File_model::STATUS_ERROR;
        $result = $this->file_model->staticUpdate($file['id'], [
            'status' => $file['status']
        ]);
        if (!$result)
            $log .= "Error updating the file table with status.\n";

        // Update the job record.
        $this->job_model->update($job_id, [
            'progress' => 100,
            'result' => ($isValid? 0 : 1),
            'log' => $log
        ]);
        return $isValid;
    }

    /**
     * View the beanstalkd statistics.
     *
     * @param string $tube The optional name of a queue.
     */
    public function stats($tube = null)
    {
        if ($this->beanstalk->connect()) {
            if ($tube)
                print_r($this->beanstalk->statsTube("videoeditserver-$tube"));
            else
                print_r($this->beanstalk->stats());
            $this->beanstalk->disconnect();
        }
    }

    /**
     * Resubmit a validation job.
     *
     * @param int $file_id The file ID
     */
    public function redo_validate($file_id)
    {
        $job = $this->job_model->getByFileIdAndType($file_id, Job_model::TYPE_VALIDATE);
        // Put job into the queue.
        $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
        if ($this->beanstalk->connect()) {
            $tube = config_item('beanstalkd_tube_validate');
            $this->beanstalk->useTube($tube);
            $priority = 10;
            $delay = 0;
            $ttr = config_item('beanstalkd_timeout'); // seconds
            $jobId = $this->beanstalk->put($priority, $delay, $ttr, $job['id']);
            $this->beanstalk->disconnect();
        }
    }

    /** Resubmit a transcoding job.
     *
     * @param int $file_id The file ID
     */
    public function redo_transcode($file_id)
    {
        $job = $this->job_model->getByFileIdAndType($file_id, Job_model::TYPE_TRANSCODE);
        // Put job into the queue.
        $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
        if ($this->beanstalk->connect()) {
            $tube = config_item('beanstalkd_tube_transcode');
            $this->beanstalk->useTube($tube);
            $priority = 10;
            $delay = 0;
            $ttr = config_item('beanstalkd_timeout'); // seconds
            $jobId = $this->beanstalk->put($priority, $delay, $ttr, $job['id']);
            $this->beanstalk->disconnect();
        }
    }

    /** Resubmit a render job.
     *
     * @param int $file_id The file ID
     */
    public function redo_render($file_id)
    {
        $job = $this->job_model->getByFileIdAndType($file_id, Job_model::TYPE_RENDER);
        // Put job into the queue.
        $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
        if ($this->beanstalk->connect()) {
            $tube = config_item('beanstalkd_tube_render');
            $this->beanstalk->useTube($tube);
            $priority = 10;
            $delay = 0;
            $ttr = config_item('beanstalkd_timeout'); // seconds
            $jobId = $this->beanstalk->put($priority, $delay, $ttr, $job['id']);
            $this->beanstalk->disconnect();
        }
    }

    /** Resubmit an archive job.
     *
     * @param int $file_id The file ID
     */
    public function redo_archive($file_id)
    {
        // Put job into the queue.
        $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
        if ($this->beanstalk->connect()) {
            $this->createArchiveJob($file_id);
            $this->beanstalk->disconnect();
        }
    }

    /**
     * The encode worker that transcodes a media file or renders a project.
     *
     * @param string @type The beanstalk tube (queue) to process: "transcode" or "render"
     */
    public function encode($tube)
    {
        if ($this->beanstalk->connect()) {
            $tube = config_item("beanstalkd_tube_$tube");
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
                    if ($file) {
                        $result = 0; // success
                        switch ($file['type']) {
                            case Job_model::TYPE_TRANSCODE:
                                $result = $this->transcode($file, $job);
                                break;
                            case Job_model::TYPE_RENDER:
                                $result = $this->render($file, $job);
                                break;
                            default:
                                $log .= "Unknown job type $file[type].\n";
                                break;
                        }
                        if ($result === -1) {
                            // Restoring files failed, try again in a little bit.
                            $priority = 10;
                            $delay = 3;
                            $this->beanstalk->release($job['id'], $priority, $delay);
                            sleep(60);
                            continue;
                        }
                    }
                    // delete this job
                    $this->beanstalk->delete($job['id']);
                } else {
                    echo "Error: beanstalkd reserve failed\n";
                }
                sleep(1);
            }
            $this->beanstalk->disconnect();
        }
    }

    /**
     * Transcode a media file using ffmpeg.
     *
     * @param array $file A file record.
     * @param array $job The beanstalk job array
     * @return int The result code is negative for internal error or the return
     * code of the ffmpeg child process.
     */
    protected function transcode($file, $job)
    {
        $result = 0;
        if (!empty($file['source_path'])) {
            $log = "Transcode: $file[source_path].\n";
            $filename = config_item('upload_path') . $file['source_path'];
            // Set file record status to converting.
            $success = $this->file_model->staticUpdate($file['file_id'], [
                'status' => intval($file['status']) | File_model::STATUS_CONVERTING
            ]);
            if (!$success) {
                $log .= "Error updating the file table with converting status.\n";
                $result = -2;
            }

            // Get the MIME type.
            $mimeType = $file['mime_type'];
            if ($result === 0 && !empty($mimeType)) {
                // Skip transcoding if already Ogg or WebM.
                if (strpos($mimeType, '/ogg') !== false || strpos($mimeType, '/webm')) {
                    $log .= "Skipping transcode since MIME type is $mimeType.\n";
                    // Update file table with status.
                    $status = intval($file['status']) | File_model::STATUS_FINISHED;
                    if (!$this->file_model->staticUpdate($file['file_id'], ['status' => $status])) {
                        $result = -5;
                        $log .= "Error updating the file table with output_path and hash.\n";
                    }
                    $archiveJobId = $this->createArchiveJob($file['id']);
                    $this->beanstalk->useTube(config_item("beanstalkd_tube_transcode"));
                    $log .= "Created archive job with ID $archiveJobId.\n";
                } else {
                    // Restore file from archive if needed.
                    if (!filesize($filename)) {
                        $log .= "Restoring from archive: $filename.\n";
                        $status = $this->internetarchive->download($file['id'], $filename);
                        $this->beanstalk->touch($job['id']);
                        if ($status !== true) {
                            $log .= "Restoring failed.\n";
                            $result = -1;
                        }
                    }
                    if ($result === 0) {
                        // Transcode it.
                        if (filesize($filename)) {
                            $majorType = explode('/', $mimeType)[0];
                            $log .= "majorType: $majorType\n";
                            if ($majorType === 'audio') {
                                $result = $this->runFFmpeg($file, $log, $job,
                                    config_item('transcode_audio_extension'),
                                    config_item('transcode_audio_options'));
                            } else {
                                $result = $this->runFFmpeg($file, $log, $job,
                                    config_item('transcode_video_extension'),
                                    config_item('transcode_video_options'));
                            }
                        } else {
                            $result = -3;
                            $log .= "Source file does not exist: $filename.\n";
                        }
                    }
                    if ($result === 0) {
                        $archiveJobId = $this->createArchiveJob($file['id']);
                        $this->beanstalk->useTube(config_item("beanstalkd_tube_transcode"));
                        $log .= "Created archive job with ID $archiveJobId.\n";
                    }
                }
            } else {
                $log .= "Error: failed to get MIME type for $filename\n";
            }
        } else {
            $result = -4;
            $log = "Source_path in file table is empty.\n";
        }

        // Update the job table with job results.
        $this->job_model->update($file['job_id'], [
            'result' => $result,
            'log' => $log
        ]);
        return $result;
    }

    /**
     * Run ffmpeg as a child process.
     *
     * @param array $file A file record.
     * @param string $log A reference to a string used for logging.
     * @param array $job The beanstalk job array
     * @param string $extension The filename extension for the output file.
     * @param string $options The command line options to pass to ffmpeg.
     * @return int The result code is negative for internal error or the return
     * code of the ffmpeg child process.
     */
    protected function runFFmpeg(&$file, &$log, $job, $extension, $options)
    {
        $result = -10;
        $file['duration_ms'] = intval($file['duration_ms']);
        $lastProgress = null;
        $filename = config_item('upload_path') . $file['source_path'];

        // Prepare the output file name.
        $outputName = $this->makeOutputFilename($filename, $extension);

        // Setup to run ffmpeg.
        $descriptorspec = [
            0 => array('file', '/dev/null', 'r'), // stdin
            1 => array('file', '/dev/null', 'w'), // stdout
            2 => array('pipe', 'w'), // stderr
        ];
        $cwd = sys_get_temp_dir();
        $env = NULL;
        $cmd = "/usr/bin/nice ffmpeg -i '$filename' $options '$outputName'";
        $log .= $cmd . "\n";
        $process = proc_open($cmd, $descriptorspec, $pipes, $cwd, $env);

        if (is_resource($process)) {
            // Get ffmpeg output while running.
            while ($line = stream_get_line($pipes[2], 255, "\r")) {
                $this->beanstalk->touch($job['id']);
                if (strlen($log) + strlen($line) < 1024 * 1024 - 200)
                    $log .= "$line\n";
                // Calculate progress as a percentage.
                $i = strpos($line, 'time=');
                $time = substr($line, $i + strlen('time='), 11);
                if (!empty($time)) {
                    $fields = explode(':', $time);
                    if (count($fields) === 3) {
                        $secs = $fields[0] * 3600 + $fields[1] * 60 + $fields[2];
                        $progress = intval(round($secs * 1000 / $file['duration_ms'] * 100));
                        if ($progress !== $lastProgress) {
                            $lastProgress = $progress;
                            $this->job_model->update($file['job_id'], ['progress' => $progress]);
                        }
                    }
                }
            }
            // Cleanup child process and get its return code.
            fclose($pipes[2]);
            $result = proc_close($process);
            echo "ffmpeg returned $result\n";
        } else {
            $result = -11;
            $log .= "Failed to start ffmpeg.\n";
        }

        // Update the file table with results.
        if ($result === 0) {
            $file['status'] = intval($file['status']) | File_model::STATUS_FINISHED;
            // Clear any previous error in case this was re-attempted.
            $file['status'] &= ~File_model::STATUS_ERROR;
            // Clear the converting flag.
            $file['status'] &= ~File_model::STATUS_CONVERTING;
            $file['output_path'] = str_replace(config_item('transcode_path'), '', $outputName);
            $file['output_hash'] = $this->getFileHash($outputName);
            $isUpdated = $this->file_model->staticUpdate($file['file_id'], [
                'status' => $file['status'],
                'output_path' => $file['output_path'],
                'output_hash' => $file['output_hash']
            ]);
            if (!$isUpdated) {
                $result = -12;
                $log .= "Error updating the file table with output_path and hash.\n";
            }
        } else {
            $isUpdated = $this->file_model->staticUpdate($file['file_id'], [
                'status' => intval($file['status']) | File_model::STATUS_ERROR
            ]);
            if (!$isUpdated) {
                $result = -13;
                $log .= "Error updating the file table with error status.\n";
            }
        }
        return $result;
    }

    /**
     * Render and encode a project file using melt.
     *
     * @param array $file A file record.
     * @param array $job The beanstalk job array
     * @return int The result code is negative for internal error or the return
     * code of the melt child process.
     */
    protected function render($file, $job)
    {
        $result = 0;
        if (!empty($file['source_path'])) {
            $log = "Render: $file[source_path].\n";
            $isRestored = false;
            $filename = config_item('upload_path') . $file['source_path'];
            // Restore file from archive if needed.
            if (!filesize($filename)) {
                $log .= "Restoring from archive: $filename.\n";
                $status = $this->internetarchive->download($file['id'] , $filename);
                if ($status !== true) {
                    $log .= "Restoring failed.\n";
                    $result = -1;
                } else {
                    $isRestored = true;
                }
            }
            if ($result === 0 && filesize($filename)) {
                // Set file record status to converting.
                $success = $this->file_model->staticUpdate($file['file_id'], [
                    'status' => intval($file['status']) | File_model::STATUS_CONVERTING
                ]);
                if (!$success) {
                    $log .= "Error updating the file table with converting status.\n";
                    $result = -5;
                }

                // Generate XML using original file references.
                $this->load->library('MltXmlHelper');
                $childFiles = $this->mltxmlhelper->getFilesData($filename, $log);
                $log .= print_r($childFiles, true);
                $isValid = $this->mltxmlhelper->substituteoriginalFiles($this->file_model, $file, $childFiles, $log);

                // If still valid, create a new version of the XML with original clips.
                if ($result === 0 && $isValid) {
                    // Prepare the output file.
                    $log .= print_r($childFiles, true);
                    $this->load->library('MltXmlWriter');
                    $tmpFileName = $this->tempfile('ves', '.xml');
                    if ($tmpFileName) {
                        // Create XML input file.
                        $fixLumas = true;
                        $this->mltxmlwriter->run($childFiles, $filename, $tmpFileName, $fixLumas);
                        $this->load->helper('file');
                        $log .= read_file($tmpFileName);

                        // Restore original files.
                        $children = $this->file_model->getChildren($file['id']);
                        foreach ($children as $child) {
                            if ($child['source_path']) {
                                $childFilePath = config_item('upload_path') . $child['source_path'];
                                if (!is_file($childFilePath) || !filesize($childFilePath)) {
                                    $log .= "Restoring from archive: $childFilePath.\n";
                                    $status = $this->internetarchive->download($child['id'], $childFilePath);
                                    $this->beanstalk->touch($job['id']);
                                    if ($status !== true) {
                                        $log .= "Restoring failed.\n";
                                        $result = -1;
                                        break;
                                    }
                                }
                            }
                        }
                        if ($result === 0) {
                            // Render and encode it.
                            $result = $this->runMelt($tmpFileName, $file, $log, $job,
                                config_item('render_extension'), config_item('render_options'));
                            unlink($tmpFileName);

                            // Truncate the restored originals.
                            foreach ($children as $child) {
                                $childFilePath = config_item('upload_path') . $child['source_path'];
                                fclose(fopen($childFilePath, 'w'));
                            }

                            // Archive the rendered file.
                            if ($result === 0) {
                                $archiveJobId = $this->createArchiveJob($file['id']);
                                $this->beanstalk->useTube(config_item("beanstalkd_tube_render"));
                                $log .= "Created archive job with ID $archiveJobId.\n";
                            }
                        }
                    } else {
                        $result = -2;
                        $log .= "Failed to create a temporary file for the XML.\n";
                    }
                    if ($result === 0 && $isRestored) {
                        // Truncate the restored project file.
                        fclose(fopen($filename, 'w'));
                    }
                } else {
                    $result = -3;
                    $log .= "This file has problems with dependencies\n";
                }
            } else {
                $result = -6;
                $log .= "Source file does not exist: $filename.\n";
            }
        } else {
            $result = -4;
            $log = "Source_path in file table is empty.\n";
        }

        // Update the job table with job results.
        $this->job_model->update($file['job_id'], [
            'result' => $result,
            'log' => $log
        ]);
        return $result;
    }

    /**
     * Run melt as a child process.
     *
     * @param string $filename The MLT XML file path and name
     * @param array $file A file record.
     * @param string $log A reference to a string used for logging.
     * @param array $job The beanstalk job array
     * @param string $extension The filename extension for the output file.
     * @param string $options The command line options to pass to melt.
     * @return int The result code is negative for internal error or the return
     * code of the melt child process.
     */
    protected function runMelt($filename, &$file, &$log, $job, $extension, $options)
    {
        $result = -10;
        $lastProgress = null;

        // Prepare the output file name.
        $outputName = $this->makeOutputFilename($file['source_path'], $extension);

        // Setup to run melt.
        $descriptorspec = [
            0 => array('file', '/dev/null', 'r'), // stdin
            1 => array('file', '/dev/null', 'w'), // stdout
            2 => array('pipe', 'w'), // stderr
        ];
        $cwd = sys_get_temp_dir();
        $env = NULL;
        $cmd = "/usr/bin/nice melt '$filename' -progress2 -consumer avformat:'$outputName' $options";
        $log .= $cmd . "\n";
        $process = proc_open($cmd, $descriptorspec, $pipes, $cwd, $env);

        if (is_resource($process)) {
            // Get melt output while running.
            while ($line = stream_get_line($pipes[2], 255, "\n")) {
                $this->beanstalk->touch($job['id']);
                // Get progress as a percentage.
                $i = strpos($line, 'percentage: ');
                if ($i !== false) {
                    $progress = substr($line, $i + strlen('percentage: '), 10);
                    if (!empty($progress) && $progress !== $lastProgress) {
                        $lastProgress = $progress;
                        $this->job_model->update($file['job_id'], ['progress' => $progress, 'log' => $log]);
                    }
                } else if (strlen($log) + strlen($line) < 1024 * 1024 - 200) {
                    $log .= "$line\n";
                }
            }
            // Cleanup child process and get its return code.
            fclose($pipes[2]);
            $result = proc_close($process);
            $this->job_model->update($file['job_id'], ['progress' => 100, 'log' => $log]);
            echo "melt returned $result\n";
        } else {
            $result = -11;
            $log .= "Failed to start melt.\n";
        }

        // Update the file table with results.
        if ($result === 0 && !is_file($outputName)) {
            $result = -14;
            $log .= "Output file \"$outputName\" does not exist.\n";
        }
        if ($result === 0) {
            $file['status'] = intval($file['status']) | File_model::STATUS_FINISHED;
            // Clear any previous error in case this was re-attempted.
            $file['status'] &= ~File_model::STATUS_ERROR;
            // Clear the converting flag.
            $file['status'] &= ~File_model::STATUS_CONVERTING;
            $file['output_path'] = str_replace(config_item('transcode_path'), '', $outputName);
            $file['output_hash'] = $this->getFileHash($outputName);
            $isUpdated = $this->file_model->staticUpdate($file['file_id'], [
                'status' => $file['status'],
                'output_path' => $file['output_path'],
                'output_hash' => $file['output_hash']
            ]);
            if (!$isUpdated) {
                $result = -12;
                $log .= "Error updating the file table with output_path and hash.\n";
            }
        } else {
            $isUpdated = $this->file_model->staticUpdate($file['file_id'], [
                'status' => intval($file['status']) | File_model::STATUS_ERROR
            ]);
            if (!$isUpdated) {
                $result = -13;
                $log .= "Error updating the file table with error status.\n";
            }
        }
        return $result;
    }

    /**
     * Return a unique, full path and name for a given file name.
     *
     * This appends a number to the base name if the file already exists.
     * It also prepends the transcode path and subdirectories to reduce the
     * number of directory entries in a single directory
     *
     * @param string $filename The base output file name
     * @param string $extension An optional alternate filename extension to use
     * @return string
     */
    protected function makeOutputFilename($filename, $extension = null)
    {
        if (empty($extension)) {
            $out = basename($filename);
            $out = "$out[0]/$out[1]/$out";
        } else {
            $ext = strrchr($filename, '.');
            $out = basename($filename, $ext);
            $out = "$out[0]/$out[1]/$out.$extension";
        }
        $fullname = config_item('transcode_path') . $out;
        $fullname = $this->getUniqueFilename($fullname);
        $dir = dirname($fullname);
        if (!is_dir($dir))
            mkdir($dir, 0755, true);
        return $fullname;
    }

    /**
     * Return a unique filename by appending a paranthesized number, if needed.
     *
     * @param string $name A full path file name
     * @return string
     */
    protected function getUniqueFilename($name)
    {
        while (file_exists($name)) {
            $name = preg_replace_callback(
                '/(?:(?: \(([\d]+)\))?(\.[^.]+))?$/',
                array($this, 'getUniqueFilename_callback'),
                $name,
                1
            );
        }
        return $name;
    }

    /**
     * The callback function used by getUniqueFilename().
     *
     * @param array $matches
     * @return string
     */
    protected function getUniqueFilename_callback($matches)
    {
        $index = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
        $ext = isset($matches[2]) ? $matches[2] : '';
        return ' ('.$index.')'.$ext;
    }

    /**
     * View the output log of a job as plain text.
     *
     * @param int $id The job ID
     */
    public function log($id)
    {
        $log = $this->job_model->getLog($id);
        if ($log) {
            $this->output->set_content_type('text/plain');
            $this->output->set_output($log);
        } else {
            show_404(uri_string());
        }
    }

    /**
     * Queue a validate job if a project file record has no more missing child files.
     *
     * @param array $file A file record
     */
    protected function checkIfWasMissing($file)
    {
        $results = $this->file_model->getMissingByNameOrHash(
            basename($file['source_path']), $file['source_hash']);
        // For each project file that was missing this file.
        foreach ($results as $missing) {
            // Remove this file from missing_files and add to file_children.
            $this->file_model->deleteMissing($missing['id']);
            $this->file_model->addChild($missing['file_id'], $file['id']);

            // If this project has no more missing files, resubmit it.
            $results = $this->file_model->getMissingFiles($missing['file_id']);
            if (!count($results)) {
                $job_id = $this->job_model->create($missing['file_id'], Job_model::TYPE_VALIDATE);
                if ($job_id) {
                    // Put validate job into the queue.
                    $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
                    if ($this->beanstalk->connect()) {
                        $tube = config_item('beanstalkd_tube_validate');
                        $this->beanstalk->useTube($tube);
                        $priority = 10;
                        $delay = 0;
                        $ttr = config_item('beanstalkd_timeout'); // seconds
                        $jobId = $this->beanstalk->put($priority, $delay, $ttr, $job_id);
                        $this->beanstalk->disconnect();
                    }
                }
            }
        }
    }

    /**
     * Create a unique temporary file name with an extension.
     *
     * @param string $prefix An optional prefix to use, defaults to 'tmp'
     * @param string $extension An optional filename extension, defaults to ''
     * @param string $dir An optional directory in which to store the file,
     * defaults to system temporary files directory if not supplied.
     */
    protected function tempfile($prefix = 'tmp', $extension = '', $dir = null)
    {
        $fileName = tempnam($dir? $dir : sys_get_temp_dir(), $prefix);
        if ($fileName) {
            $newFileName = $fileName . $extension;
            if ($fileName === $newFileName)
                return $fileName;
            // Move or point the created temporary file to the new filename.
            // NOTE: these fail if the new file name exist.
            if (@link($fileName, $newFileName))
                return $newFileName;
        }
        unlink($fileName);
        return false;
    }

    /** Resubmit a publish job.
     *
     * @param int $file_id The file ID
     */
    public function redo_publish($file_id)
    {
        $job = $this->job_model->getByFileIdAndType($file_id, Job_model::TYPE_PUBLISH);
        // Put job into the queue.
        $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
        if ($this->beanstalk->connect()) {
            $tube = config_item('beanstalkd_tube_publish');
            $this->beanstalk->useTube($tube);
            $priority = 10;
            $delay = 0;
            $ttr = config_item('beanstalkd_timeout'); // seconds
            $jobId = $this->beanstalk->put($priority, $delay, $ttr, $job['id']);
            $this->beanstalk->disconnect();
        }
    }

    /**
     * The publish worker that uploads a file to Wikimedia Commons.
     */
    public function publish()
    {
        if ($this->beanstalk->connect()) {
            $tube = config_item('beanstalkd_tube_publish');
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
                    if ($file) {
                        $result = $this->publishFileRecord($file, $job);
                        if ($result !== 0 && $result !== -2 /* MediaWiki API error */) {
                            // Requeue the failed job 5 minutes from now.
                            $priority = 10;
                            $delay = 3;
                            $this->beanstalk->release($job['id'], $priority, $delay);
                            sleep(60);
                            continue;
                        }
                    }
                    // delete this job
                    $this->beanstalk->delete($job['id']);
                } else {
                    echo "Error: beanstalkd reserve failed\n";
                }
                sleep(1);
            }
            $this->beanstalk->disconnect();
        }
    }

    /**
     * Publish the rendered project file to Wikimedia Commons.
     *
     * @param array $file The file record
     * @param array $job The beanstalk job array
     * @return int The result code is zero for success and non-zero for error.
     */
    public function publishFileRecord($file, $job)
    {
        $result = -1;
        if (!empty($file['output_path'])) {
            $filename = basename($file['output_path']);
            $filepath = config_item('transcode_path') . $file['output_path'];
        } else if (!empty($file['source_path'])) {
            $filename = basename($file['source_path']);
            $filepath = config_item('upload_path') . $file['source_path'];
        } else {
            echo "output_path and source_path are both empty!\n";
            return $result;
        }
        $log = "Publish: $file[title].\n";
        if (!empty($file['publish_id']))
            $filename = $file['publish_id'];

        // Lookup user in database.
        $user = $this->user_model->getByID($file['user_id']);
        if ($user && $user['access_token']) {
            // User exists and has access token.
            $this->load->library('OAuth', $this->config->config);

            // Query the page by file.title for the edit token.
            $accessToken = $user['access_token'];
            $params = [
                'action' => 'query',
                'format' => 'php',
                'continue' => '',
                'titles' => $filename,
                'meta' => 'tokens'
            ];
            $response = $this->oauth->get($accessToken, $params);
            if (strpos($response, '<html') === false) {
                $response = unserialize($response);
                $log .= 'HTTP response: ' . json_encode($response) . "\n";
                if (array_key_exists('error', $response)) {
                    # error set - return and start over
                    $result = -2;
                    $log .= 'MediaWiki query API error: '.$response['error']['info']."\n";

                } else if (isset($response['query']['pages'])) {
                    # Extract edit token.
                    if (isset($response['query']['tokens']['csrftoken']))
                        $token = $response['query']['tokens']['csrftoken'];
                    else if (isset($response['query']['pages'][-1]['edittoken']))
                        $token = $response['query']['pages'][-1]['edittoken'];
                    if (isset($response['query']['pages'][-1]['title'])) {
                        $file['publish_id'] = $response['query']['pages'][-1]['title'];
                        $filename = $file['publish_id'];
                    }

                    // Call the MediaWiki Upload API.
                    if (isset($token)) {
                        // Generate Commons metadata.
                        $file['username' ]= $user['name'];
                        $text = $this->load->view('file/wikitext', $file, true);

                        // Restore the file if needed.
                        $isRestored = false;
                        if (!filesize($filepath)) {
                            $log .= "Restoring from archive: $filepath.\n";
                            $status = $this->internetarchive->download($file['id'] , $filepath);
                            $this->beanstalk->touch($job['id']);
                            if ($status !== true) {
                                $log .= "Restoring failed.\n";
                                $result = -1;
                                return $result;
                            }
                            $isRestored = true;
                        }
                        if (filesize($filepath) > $this->chunkSize) {
                            // Do chunked upload.
                            $response = ['upload' => [
                                'offset' => 0,
                                'result' => 'Continue'
                            ]];
                            do {
                                $response = $this->uploadChunk($response, $filepath,
                                    $filename, $text, $token, $accessToken, $log);
                                $uploadResult = isset($response['upload']['result'])? $response['upload']['result'] : false;
                            } while (($uploadResult === 'Continue' || $uploadResult === 'Success')
                                    && isset($response['upload']['filekey']));
                        } else {
                            // Upload the entire file at once.
                            $response = $this->uploadFile($filepath, $filename, $text, $token, $accessToken, $log);
                        }

                        if ($isRestored) {
                            // Truncate the restored file.
                            fclose(fopen($filepath, 'w'));
                        }

                        // Process the upload response.
                        if ($response && !array_key_exists('error', $response)) {
                            // Success
                            $result = 0;
                        } else if ($response && array_key_exists('error', $response)) {
                            $result = -2;
                            $log .= 'MediaWiki upload API error: '.$response['error']['info']."\n";
                        } else {
                            $result = -3;
                            $log .= "MediaWiki upload API error: unknown\n";
                        }
                    } else {
                        $result = -4;
                        $log .= "No edit token found in MediaWiki API response.\n";
                    }
                } else {
                    $result = -5;
                    $log .= "No pages found in MediaWiki API response.\n";
                }
            } else {
                $result = -6;
                $log .= "MediaWiki query API response: $response\n";
            }
        }

        // Update the file table with results.
        if ($result === 0) {
            $status = intval($file['status']) | File_model::STATUS_PUBLISHED;
            // Clear any previous error in case this was re-attempted.
            $status &= ~File_model::STATUS_ERROR;
            $isUpdated = $this->file_model->staticUpdate($file['file_id'], [
                'status' => $status,
                'publish_id' => $file['publish_id']
            ]);
            if (!$isUpdated) {
                $result = -10;
                $log .= "Error updating the file table with output_path and hash.\n";
            }
        } else {
            $isUpdated = $this->file_model->staticUpdate($file['file_id'], [
                'status' => intval($file['status']) | File_model::STATUS_ERROR
            ]);
            if (!$isUpdated) {
                $result = -11;
                $log .= "Error updating the file table with error status.\n";
            }
        }

        // Update the job table with job results.
        $this->job_model->update($file['job_id'], [
            'result' => $result,
            'log' => $log
        ]);
        return $result;
    }

    /**
     * Upload a complete file to Wikimedia Commons using OAuth.
     *
     * @param string $filePath The full file path and name
     * @param string $filename The name to give the file on Commons
     * @param string $text The description and/or metadata for the Commons file item
     * @param string $editToken The edit token obtained from a MediaWiki query API call
     * @param string $accessToken The OAuth access token
     * @param string $log A reference to a string for logging
     * @return array|bool PHP-decoded HTTP response body or false on error
     */
    protected function uploadFile($filepath, $filename, $text, $editToken, $accessToken, &$log)
    {
        $params = [
            'action' => 'upload',
            'format' => 'php'
        ];
        $data = [
            'filename' => $filename,
            'filesize' => filesize($filepath),
            'text' => $text,
            'ignorewarnings' => 1,
            'token' => $editToken
        ];
        $log .= "Sending data: ".json_encode($data)."\n";
        $mimetype = mime_content_type($filepath);
        $this->load->helper('curl_helper');
        $data['file'] = curl_file_create($filepath, $mimetype, $filename);
        $response = $this->oauth->post($accessToken, $params, $data);

        if (strpos($response, '<html') === false) {
            $response = unserialize($response);
            $log .= 'HTTP response: ' . json_encode($response) . "\n";
        } else {
            $response = false;
            $log .= "MediaWiki upload API error: $response\n";
        }
        return $response;
    }

    /**
     * Upload a file chunk to Wikimedia Commons using OAuth.
     *
     * On the first call, seed the response with ['upload'=> ['offset'=> 0, 'result'=> 'Continue']].
     *
     * @param array  $response The response from a previous call
     * @param string $filePath The full file path and name
     * @param string $filename The name to give the file on Commons
     * @param string $text The description and/or metadata for the Commons file item
     * @param string $editToken The edit token obtained from a MediaWiki query API call
     * @param string $accessToken The OAuth access token
     * @param string $log A reference to a string for logging
     * @return array|bool PHP-decoded HTTP response body or false on error
     */
    protected function uploadChunk($response, $filepath, $filename, $text, $editToken, $accessToken, &$log)
    {
        $params = [
            'action' => 'upload',
            'format' => 'php'
        ];
        $data = [
            'filename' => $filename,
            'filesize' => filesize($filepath),
            'ignorewarnings' => 1,
            'token' => $editToken
        ];
        if (isset($response['upload']['filekey'])) {
            $data['filekey'] = $response['upload']['filekey'];
        }
        if ($response['upload']['result'] === 'Continue') {
            $data['offset'] = $response['upload']['offset'];
            $data['stash'] = 1;
        } else if ($response['upload']['result'] === 'Success') {
            $data['text'] = $text;
        }
        $log .= "Sending data: ".json_encode($data)."\n";

        if (isset($data['offset'])) {
            $mimetype = mime_content_type($filepath);
            # build chunk - extract data from source file into chunk file
            $chunkPath = $this->tempfile('ves-chunk');
            $chunk = file_get_contents($filepath, false, null, $data['offset'], $this->chunkSize);
            file_put_contents($chunkPath, $chunk);
            # turn 'chunk' form field into a file upload
            $this->load->helper('curl_helper');
            $data['chunk'] = curl_file_create($chunkPath, $mimetype, $filename);
        }

        $response = $this->oauth->post($accessToken, $params, $data);
        if (strpos($response, '<html') === false) {
            $response = unserialize($response);
            $log .= 'HTTP response: ' . json_encode($response) . "\n";
        } else {
            $response = false;
            $log .= "MediaWiki upload API error: $response\n";
        }

        if (isset($chunkPath))
            unlink($chunkPath);
        return $response;
    }


    public function test_transcode($filename)
    {
        $log = '';
        $this->transcodeVideo(33, config_item('upload_path').$filename, $log);
        echo $log;
    }

    public function test_missing($job_id)
    {
        $file = $this->job_model->getWithFileById($job_id);
        if (!empty($file['source_hash']))
            $this->checkIfWasMissing($file);
    }

    public function test_publish($job_id)
    {
        $file = $this->job_model->getWithFileById($job_id);
        if ($file)
            $this->publishFileRecord($file, null);
    }

    /**
     * Create an archive job record in the database and put a message into a queue for it.
     *
     * @param int $file_id A file record ID
     * @return int|false The new job record ID or false on error
     */
    public function createArchiveJob($file_id)
    {
        $jobId = $this->job_model->create($file_id, Job_model::TYPE_ARCHIVE);
        if ($jobId) {
            // Put job into the queue.
            $tube = config_item('beanstalkd_tube_archive');
            $this->beanstalk->useTube($tube);
            $priority = 10;
            $delay = 0;
            $ttr = config_item('beanstalkd_timeout'); // seconds
            $this->beanstalk->put($priority, $delay, $ttr, $jobId);
            return $jobId;
        } else {
            return false;
        }
    }

    /**
     * The archive worker that uploads files to Internet Archive.
     */
    public function archive()
    {
        if ($this->beanstalk->connect()) {
            $tube = config_item('beanstalkd_tube_archive');
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
                    if ($file) {
                        $result = $this->archiveFileRecord($file, $job);
                        echo "result = $result\n";
                        if ($result !== true && $result !== 403 && $result !== 404) {
                            // Requeue the failed job 5 minutes from now.
                            $priority = 10;
                            $delay = 300; // seconds
                            $this->beanstalk->release($job['id'], $priority, $delay);
                            continue;
                        }
                        sleep(30);
                    }
                    // delete this job
                    $this->beanstalk->delete($job['id']);
                } else {
                    echo "Error: beanstalkd reserve failed\n";
                }
                sleep(1);
            }
            $this->beanstalk->disconnect();
        }
    }

    /**
     * Archive a file as a new S3 item.
     *
     * @param string $filename The full path to the file to be archive
     * @param array $file A file record
     * @return bool True on success
     */
    protected function archiveFile($filename, $file)
    {
        // Use the file record's creator's S3 credentials if possible.
        $user = $this->user_model->getByID($file['user_id']);
        $s3_access_key = empty($user['s3_access_key']) ? config_item('s3_access_key') : $user['s3_access_key'];
        $s3_secret_key = empty($user['s3_secret_key']) ? config_item('s3_secret_key') : $user['s3_secret_key'];

        $result = $this->internetarchive->createItem($s3_access_key, $s3_secret_key, $filename, $file);

        if ($result === true) {
            // truncate file to 0 bytes
            fclose(fopen($filename, 'w'));
        }
        return $result;
    }

    /**
     * Archive a file to an existing S3 item.
     *
     * @param string $filename The full path to the file to be archive
     * @param array $file A file record
     * @return bool True on success
     */
    protected function addToArchive($filename, $file)
    {
        // Use the file record's creator's S3 credentials if possible.
        $user = $this->user_model->getByID($file['user_id']);
        $s3_access_key = empty($user['s3_access_key']) ? config_item('s3_access_key') : $user['s3_access_key'];
        $s3_secret_key = empty($user['s3_secret_key']) ? config_item('s3_secret_key') : $user['s3_secret_key'];

        $result = $this->internetarchive->addFileToItem($s3_access_key, $s3_secret_key, $filename, $file);

        if ($result === true) {
            // truncate file to 0 bytes
            fclose(fopen($filename, 'w'));
        }
        return $result;
    }

    /**
     * Archive the files belonging to a file record.
     *
     * This does not process a queue. It is intended to be run manually and not
     * used in a HTML page.
     *
     * @param int $file_id A file record ID
     * @param bool True on success
     */
    protected function archiveFileRecord($file)
    {
        $success = true;
        $log = '';
        if ($file['source_path']) {
            $filename = config_item('upload_path') . $file['source_path'];
            if (filesize($filename)) {
                $log .= "Archiving $filename\n";
                $success = $this->archiveFile($filename, $file);
                if ($success !== true)
                    $log .= "Failed to archive $filename\n";
            } else {
                $log .= "Skipping $filename\n";
            }
        }
        if ($success === true && $file['output_path']) {
            $filename = config_item('transcode_path') . $file['output_path'];
            if (filesize($filename)) {
                $log .= "Archiving $filename\n";
                $success = $this->addToArchive($filename, $file);
                if ($success !== true)
                    $log .= "Failed to archive $filename\n";
            } else {
                $log .= "Skipping $filename\n";
            }
        }
        // Update the job table with job results.
        $this->job_model->update($file['job_id'], [
            'result' => ($success === true) ? 0 : 1,
            'progress' => 100,
            'log' => $log
        ]);
        return $success;
    }

    /**
     * Archive all of the files.
     *
     * This is intended to be run manually from the command line and not used in
     * a HTML page.
     */
    public function archiveAll()
    {
        $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
        if ($this->beanstalk->connect()) {
            $query = $this->db->query('select id from file');
            foreach ($query->result() as $row)
                $this->createArchiveJob($row->id);
            $this->beanstalk->disconnect();
        }
    }

    public function test_archive($file_id)
    {
        if ($this->beanstalk->connect()) {
            $success = true;
            $file = $this->file_model->getByID($file_id);
            if ($file['source_path']) {
                $filename = config_item('upload_path') . $file['source_path'];
                if (filesize($filename)) {
                    echo "Archiving $filename\n";
                    $success = $this->archiveFile($filename, $file);
                    if ($success !== true)
                        echo "Failed to archive $filename\n";
                } else {
                    echo "Skipping $filename\n";
                }
            }
            if ($success === true && $file['output_path']) {
                $filename = config_item('transcode_path') . $file['output_path'];
                if (filesize($filename)) {
                    echo "Archiving $filename\n";
                    $success = $this->addToArchive($filename, $file);
                    if ($success !== true)
                        echo "Failed to archive $filename\n";
                } else {
                    echo "Skipping $filename\n";
                }
            }
            $this->beanstalk->disconnect();
        }
    }

    /**
     * Update the S3 metadata for a file.
     *
     * @param string $file_id A file record ID
     * @return bool True on success
     */
    public function updateMetadata($file_id)
    {
        $result = false;
        $file = $this->file_model->getByID($file_id);
        if ($file['source_path']) {
            // Use the file record's creator's S3 credentials if possible.
            $user = $this->user_model->getByID($file['user_id']);
            $s3_access_key = empty($user['s3_access_key']) ? config_item('s3_access_key') : $user['s3_access_key'];
            $s3_secret_key = empty($user['s3_secret_key']) ? config_item('s3_secret_key') : $user['s3_secret_key'];

            $filename = config_item('upload_path') . $file['source_path'];
            $result = $this->internetarchive->updateMetadata($s3_access_key, $s3_secret_key, $filename, $file);

            $result = ($result === true);
        }
        return $result;
    }

    public function pause($tube, $seconds = 86400)
    {
        if ($this->beanstalk->connect()) {
            echo $this->beanstalk->pauseTube("videoeditserver-$tube", $seconds);
            echo "\n";
            $this->beanstalk->disconnect();
        }
    }
}
