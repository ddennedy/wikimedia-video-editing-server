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
                                $isValid = $this->validateAudioVideo($job_id, $file, $majorType);
                            } else if ($majorType === 'image' || $extension === '.svg') {
                                $isValid = $this->validateImage($job_id, $file, $majorType);
                            } else if ($mimeType === 'application/xml' ||
                                       $mimeType === 'text/xml' ||
                                       $mimeType === 'application/x-kdenlive' ||
                                       $mimeType === 'application/mlt+xml') {
                                // if mlt xml, verify melt can read it
                                $isValid = $this->validateMLTXML($job_id, $file, $majorType);
                            } else {
                                //TODO flag this somehow as possible invalid, let
                                // the user manually approve it as a supplemental file
                                // needed by the project
                            }
                            if (!empty($file['source_hash']))
                                $this->checkIfWasMissing($file);
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

    protected function validateAudioVideo($job_id, &$file, $majorType)
    {
        $this->load->model('file_model');
        $isValid = true;
        $log = "Validate: $file[source_path].\n";
        $filename = config_item('upload_path') . $file['source_path'];

        // if audio or video, verify ffprobe can read it
        $json = shell_exec("/usr/bin/nice ffprobe -print_format json -show_error -show_format -show_streams '$filename' 2>/dev/null");
        if (!empty($json)) {
            // verify tracks match codec_type
            $ffprobe = json_decode($json);
            $isValid = false;
            foreach ($ffprobe->streams as $stream) {
                if (isset($stream->codec_type) && $stream->codec_type === $majorType) {
                    $log .= "ffprobe found a stream with codec_type \"$stream->codec_type\" that matches the MIME type.\n";
                    $isValid = true;
                    break;
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
                    $delay = 0;
                    $ttr = 60; // seconds
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

    protected function validateImage($job_id, &$file, $majorType)
    {
        // verify melt can read it
        $this->load->model('file_model');
        $isValid = true;
        $log = "Validate: $file[source_path].\n";
        $filename = config_item('upload_path') . $file['source_path'];

        // if audio or video, verify ffprobe can read it
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

    protected function validateMLTXML($job_id, &$file, $majorType)
    {
        // verify melt can load it
        $this->load->model('file_model');
        $isValid = true;
        $log = "Validate: $file[source_path].\n";
        $filename = config_item('upload_path') . $file['source_path'];

        // if audio or video, verify ffprobe can read it
        $xml = shell_exec("/usr/bin/nice melt -consumer xml '$filename' 2>/dev/null");
        if (!empty($xml)) {
            if ($this->isXmlWellFormed($xml)) {
                // verify all dependent files are available
                $childFiles = $this->getFilesData($filename, $log);
                $isValid = $this->substituteProxyFiles($file, $childFiles, $log);

                // If still valid, create a new version of the XML with proxy clips.
                if ($isValid) {
                    // Prepare the output file.
                    $outputName = $this->makeOutputFilename($filename);
                    $this->load->library('MltXmlWriter', $childFiles);
                    $this->mltxmlwriter->run($filename, $outputName);

                    // Update database with new XML filename.
                    $status = intval($file['status']) | File_model::STATUS_FINISHED;
                    // Clear any previous error in case this was re-attempted.
                    $status &= ~File_model::STATUS_ERROR;
                    $isUpdated = $this->file_model->staticUpdate($file['file_id'], [
                        'status' => $status,
                        'output_path' => str_replace(config_item('transcode_path'), '', $outputName),
                        'output_hash' => $this->getFileHash($outputName)
                    ]);
                    if (!$isUpdated) {
                        $isValid = false;
                        $log .= "Error updating the file table with output_path and hash.\n";
                    }
                }

                // If still valid, create the render job.
                if ($isValid) {
                    $renderJobId = $this->job_model->create($file['id'], Job_model::TYPE_RENDER);
                    if ($renderJobId) {
                        // Put job into the queue.
                        $tube = config_item('beanstalkd_tube_render');
                        $this->beanstalk->useTube($tube);
                        $priority = 10;
                        $delay = 0;
                        $ttr = 60; // seconds
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

    protected function isXmlWellFormed($xml)
    {
        libxml_use_internal_errors(true);
        return simplexml_load_string($xml) !== false;
    }

    protected function getFilesData($filename, &$log)
    {
        try {
            $this->load->library('MltXmlReader');
            return $this->mltxmlreader->getFiles($filename);
        } catch (Exception $e) {
            $log .= "$e\n";
            return array();
        }
    }

    protected function substituteProxyFiles($file, &$childFiles, &$log)
    {
        $isValid = true;
        foreach($childFiles as $fileName => &$fileData) {
            $name = basename($fileName);
            if (isset($fileData['mlt_service'])) {
                $child = null;
                $log .= "Found file in XML with name: $name.\n";
                if (!empty($fileData['file_hash'])) {
                    // Search for file by hash.
                    $child = $this->file_model->getByHash($fileData['file_hash']);
                    if ($child)
                        $log .= "Found file record by its hash: $fileData[file_hash].\n";
                }
                if (!$child) {
                    // Search for file by basename.
                    $child = $this->file_model->getByPath($name);
                    if ($child)
                        $log .= "Found file record by name: $name.\n";
                }
                //TODO Search for the file on Commons based on its basename;
                if ($child) {
                    // Save path for new XML.
                    if (empty($child['output_path']))
                        $fileData['output_path'] = basename($child['source_path']);
                    else
                        $fileData['output_path'] = basename($child['output_path']);
                    // Save hash for new XML.
                    if (empty($child['output_hash']))
                        $fileData['output_hash'] = basename($child['source_hash']);
                    else
                        $fileData['output_hash'] = basename($child['output_hash']);

                    // Add child and parent relations to database.
                    if ($this->file_model->addChild($file['id'], $child['id']))
                        $log .= "Added file relationship: $file[id] -> $child[id].\n";
                    else
                        $log .= "Error adding record to file_children table: $file[id] -> $child[id].\n";
                } else {
                    $isValid = false;
                    // Add child to missing_files table.
                    if ($this->file_model->addMissing($file['id'], $name, $fileData['file_hash']))
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

    public function stats($tube = null)
    {
        if ($this->beanstalk->connect()) {
            if ($tube)
                print_r($this->beanstalk->statsTube($tube));
            else
                print_r($this->beanstalk->stats());
            $this->beanstalk->disconnect();
        }
    }

    public function redo_validate($job_id)
    {
        // Put job into the queue.
        $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
        if ($this->beanstalk->connect()) {
            $tube = config_item('beanstalkd_tube_validate');
            $this->beanstalk->useTube($tube);
            $priority = 10;
            $delay = 0;
            $ttr = 60; // seconds
            $jobId = $this->beanstalk->put($priority, $delay, $ttr, $job_id);
            $this->beanstalk->disconnect();
        }
    }

    public function redo_encode($job_id)
    {
        // Put job into the queue.
        $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
        if ($this->beanstalk->connect()) {
            $tube = config_item('beanstalkd_tube_transcode');
            $this->beanstalk->useTube($tube);
            $priority = 10;
            $delay = 0;
            $ttr = 60; // seconds
            $jobId = $this->beanstalk->put($priority, $delay, $ttr, $job_id);
            $this->beanstalk->disconnect();
        }
    }

    public function encode()
    {
        if ($this->beanstalk->connect()) {
            $tube = config_item('beanstalkd_tube_transcode');
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
                        switch ($file['type']) {
                            case Job_model::TYPE_TRANSCODE:
                                $this->transcode($file);
                                break;
                            case Job_model::TYPE_RENDER:
                                //$this->render($file);
                                break;
                            default:
                                $log .= "Unknown job type $file[type].\n";
                                break;
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

    protected function transcode($file)
    {
        $result = -1;
        if (!empty($file['source_path'])) {
            $this->load->model('file_model');
            $log = "Transcode: $file[source_path].\n";
            $filename = config_item('upload_path') . $file['source_path'];
            if (is_file($filename)) {
                // Set file record status to converting.
                $result = $this->file_model->staticUpdate($file['file_id'], [
                    'status' => intval($file['status']) | File_model::STATUS_CONVERTING
                ]);
                if (!$result)
                    $log .= "Error updating the file table with converting status.\n";

                // Get the MIME type.
                $mimeType = $this->getMimeType($file);
                if (!empty($mimeType)) {
                    // Skip transcoding if already Ogg or WebM.
                    if (strpos($mimeType, '/ogg') !== false || strpos($mimeType, '/webm')) {
                        $result = 0;
                        $log .= "Skipping transcode since MIME type is $mimeType.\n";
                        // Update file table with status.
                        $status = intval($file['status']) | File_model::STATUS_FINISHED;
                        if (!$this->file_model->staticUpdate($file['file_id'], ['status' => $status])) {
                            $result = -5;
                            $log .= "Error updating the file table with output_path and hash.\n";
                        }
                    } else {
                        // Transcode it.
                        $result = -2;
                        $majorType = explode('/', $mimeType)[0];
                        $log .= "majorType: $majorType\n";
                        if ($majorType === 'audio') {
                            $result = $this->runFFmpeg($file, $log,
                                config_item('transcode_audio_extension'),
                                config_item('transcode_audio_options'));
                        } elseif ($majorType === 'video') {
                            $result = $this->runFFmpeg($file, $log,
                                config_item('transcode_video_extension'),
                                config_item('transcode_video_options'));
                        } else {
                            $log .= "Error: unable to transcode MIME type $mimeType\n";
                        }
                    }
                } else {
                    $log .= "Error: failed to get MIME type for $filename\n";
                }
            } else {
                $result = -3;
                $log .= "Source file does not exist: $filename.";
            }
        } else {
            $result = -4;
            $log = "Source_path in file table is empty.";
        }

        // Update the job table with job results.
        $this->job_model->update($file['job_id'], [
            'result' => $result,
            'log' => $log
        ]);
        return $result;
    }

    protected function runFFmpeg($file, &$log, $extension, $options)
    {
        $result = -10;
        $file['duration_ms'] = intval($file['duration_ms']);
        $lastProgress = null;
        $filename = config_item('upload_path') . $file['source_path'];

        // Prepare the output file name.
        $outputName = $this->makeOutputFilename($filename);

        // Setup to run ffmpeg.
        $descriptorspec = [
            0 => array('file', '/dev/null', 'r'), // stdin
            1 => array('file', '/dev/null', 'w'), // stderr
            2 => array('pipe', 'w'), // stdout
        ];
        $cwd = '/tmp';
        $env = [];
        $cmd = "/usr/bin/nice ffmpeg -i '$filename' $options '$outputName'";
        $log .= $cmd . "\n";
        $process = proc_open($cmd, $descriptorspec, $pipes, $cwd, $env);

        if (is_resource($process)) {
            // Get ffmpeg output while running.
            while ($line = stream_get_line($pipes[2], 255, "\r")) {
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
            $status = intval($file['status']) | File_model::STATUS_FINISHED;
            // Clear any previous error in case this was re-attempted.
            $status &= ~File_model::STATUS_ERROR;
            $isUpdated = $this->file_model->staticUpdate($file['file_id'], [
                'status' => $status,
                'output_path' => str_replace(config_item('transcode_path'), '', $outputName),
                'output_hash' => $this->getFileHash($outputName)
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

    protected function makeOutputFilename($filename)
    {
        $out = basename($filename);
        $out = "$out[0]/$out[1]/$out";
        $fullname = config_item('transcode_path') . $out;
        $fullname = $this->getUniqueFilename($fullname);
        $dir = dirname($fullname);
        if (!is_dir($dir))
            mkdir($dir, 0755, true);
        return $fullname;
    }

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

    protected function getUniqueFilename_callback($matches)
    {
        $index = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
        $ext = isset($matches[2]) ? $matches[2] : '';
        return ' ('.$index.')'.$ext;
    }

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

    protected function checkIfWasMissing($file)
    {
        $this->load->model('file_model');
        $results = $this->file_model->getMissingByHash($file['source_hash']);
        // For each project file waw missing this file.
        foreach ($results as $missing) {
            // Remove this file from missing_files and add to file_children.
            $this->file_model->deleteMissing($missing['id']);
            $this->file_model->addChild($missing['file_id'], $file['id']);

            // If this project has no more missing files, resubmit it.
            $results = $this->file_model->getMissingFiles($missing['file_id']);
            if (!count($results)) {
                $job_id = $this->job_model->create($missing['file_id'], Job_model::TYPE_VALIDATE);
                if ($job_id) {
                    // Put job into the queue.
                    $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
                    if ($this->beanstalk->connect()) {
                        $tube = config_item('beanstalkd_tube_validate');
                        $this->beanstalk->useTube($tube);
                        $priority = 10;
                        $delay = 1;
                        $ttr = 60; // seconds
                        $jobId = $this->beanstalk->put($priority, $delay, $ttr, $job_id);
                        $this->beanstalk->disconnect();
                    }
                }
            }
        }
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
}
