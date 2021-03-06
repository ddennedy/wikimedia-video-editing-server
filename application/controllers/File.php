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

class File extends CI_Controller
{
    private $data = array();

    public function __construct()
    {
        parent::__construct();
        $this->load->model('file_model');
        $this->data['session'] = $this->session->userdata();
    }
    public function index()
    {
        echo 'TODO: browse all files';
    }

    /**
     * Show the form to edit a file record and save changes.
     *
     * @param int The ID of the record to change, omit to make a new record.
     */
    public function edit($id = null)
    {
        if ($id === null)
            $id = $this->input->post_get('id');

        // Check permission.
        if (User_model::ROLE_GUEST == $this->session->userdata('role')) {
            show_error(tr('file_error_permission'), 403, tr('file_error_heading'));
            return;
        }

        // Check if initially loading from existing data.
        if ('GET' == $this->input->method(true)) {
            $file = $this->file_model->getById($id);
            if ($file) {
                if (!empty($file['source_path'])) {
                    if (is_file(config_item('upload_path').$file['source_path'])) {
                        $size = filesize(config_item('upload_path').$file['source_path']);
                        if ($size != $file['size_bytes']) {
                            // Need to resume file upload.
                            $file['size_bytes'] = $size;
                            $file['upload_button_text'] = tr('file_upload_resume',
                                ['filename' => basename($file['source_path'])]);
                            $file['base_name'] = basename($file['source_path']);
                            unset($file['source_path']);
                        }
                    } else {
                        $file['base_name'] = basename($file['source_path']);
                        unset($file['source_path']);
                        $file['size_bytes'] = 0;
                    }
                }
                $this->data = array_merge($this->data, $file);
                if ($id === null) {
                    $this->data['author'] = $this->session->userdata('username');
                    $this->data['language'] = config_item('language');
                    $this->data['recording_date'] = strftime('%Y-%m-%d');
                }
            } else {
                show_404(uri_string());
            }
        } elseif ('POST' == $this->input->method(true)) {
            // Validate data.
            $this->load->library('form_validation');
            $this->form_validation->set_rules(
                'title', 'lang:file_title', 'required|xss_clean');
            $this->form_validation->set_rules(
                'author', 'lang:file_author', 'required|xss_clean');
            $this->form_validation->set_rules(
                'description', 'lang:file_description', 'trim|max_length[1000]|xss_clean|prep_for_form|encode_php_tags');
            $this->form_validation->set_rules(
                'language', 'lang:file_language', 'required|xss_clean');

            if ($this->form_validation->run()) {
                // Update database.
                $data = [
                    'user_id' => $this->session->userdata('userid'),
                    'title' => $this->input->post('title'),
                    'author' => $this->input->post('author'),
                    'description' => $this->input->post('description'),
                    'language' => $this->input->post('language'),
                    'license' => $this->input->post('license'),
                    'recording_date' => $this->input->post('recording_date'),
                    'keywords' => $this->input->post('keywords')
                ];

                $data['properties'] = $this->file_model->getProperties($id);
                if (!empty($this->input->post('properties')[0]['name'])) {
                    $data['properties']['user'] = $this->input->post('properties');
                } else {
                    unset($data['properties']['user']);
                }
                $data['properties'] = json_encode($data['properties']);

                if ($id === null) {
                    $id = $this->file_model->create($data);
                    if ($id) {
                        // If successful, redisplay edit form with upload control.
                        $file = $this->file_model->getById($id);
                        if ($file) {
                            $this->data = array_merge($this->data, $file);
                            if ($id === null) {
                                $this->data['author'] = $this->session->userdata('username');
                                $this->data['language'] = config_item('language');
                                $this->data['recording_date'] = strftime('%Y-%m-%d');
                            }
                        } else {
                            show_404(uri_string());
                            return;
                        }
                    } else {
                        show_error(tr('file_error_update'), 500, tr('file_error_heading'));
                        return;
                    }
                } else if ($this->file_model->update($id, $data)) {
                    // If successful, goto view page.
                    $this->view($id);
                    return;
                } else {
                    show_error(tr('file_error_update'), 500, tr('file_error_heading'));
                    return;
                }
            } else {
                $this->data['id'] = $id;
                $this->data = array_merge($this->data, $_POST);
                if (!empty($this->input->post('properties')[0]['name'])) {
                    $data['properties'] = array();
                    $data['properties']['user'] = json_encode($this->input->post('properties'));
                }
            }
        }

        // Build arrays for dropdowns.
        $this->data['languages'] = $this->user_model->getLanguages();
        $this->data['licenses'] = $this->file_model->getLicenses();
        if (isset($this->data['id'])) {
            $this->data['heading'] = tr('file_edit_heading', $this->data);
            $this->data['message'] = tr('file_edit_message', $this->data);
        } else {
            $this->data['heading'] = tr('file_new_heading');
            $this->data['message'] = tr('file_new_message');
        }
        if (!isset($this->data['upload_button_text']))
            $this->data['upload_button_text'] = tr('file_upload_button');
        if (is_string($this->data['properties']))
            $this->data['properties'] = json_decode($this->data['properties'], true);

        // Display form.
        $this->load->helper('form');
        $this->load->config('form_validation');
        $this->load->view('templates/header', $this->data);
        $this->load->view('file/edit', $this->data);
        $this->load->view('templates/footer', $this->data);
    }

    /**
     * Show the page to view a file record.
     *
     * @param int $id Optional record ID, get from POST if omitted.
     * @param int $offset Optional pagination offset for the table of changes/history.
     */
    public function view($id = null, $offset = null)
    {
        if (!$id) $id = $this->input->post('id');
        if (!$id) $id = $this->input->get('id');
        $file = $this->file_model->getById($id);
        if ($file) {
            $this->data = array_merge($this->data, $file);
            $this->data['isProjectDownloadable'] = false;
            $this->load->library('MltXmlReader');
            $this->data['isProject'] = $this->mltxmlreader->isMimeTypeMltXml($file['mime_type']);

            // Determine upload status.
            if (!empty($file['source_path']) &&
                    is_file(config_item('upload_path').$file['source_path'])) {
                $size = filesize(config_item('upload_path').$file['source_path']);
                if ($size != $file['size_bytes'] && $size !== 0) {
                    $status = tr('upload_partialupload');
                } else {
                    $status = tr('status_uploaded');
                    if ($file['status'] & File_model::STATUS_VALIDATED) {
                        $status .= ' =&gt; ' . tr('status_validated');
                        if ($this->data['isProject'])
                            $this->data['isProjectDownloadable'] = true;
                        // Determine conversion status.
                        if ($file['status'] & File_model::STATUS_CONVERTING) {
                            $this->load->model('job_model');
                            $status .= ' =&gt; ' . tr('status_converting');
                            $job = $this->job_model->getByFileIdAndType($id, Job_model::TYPE_TRANSCODE);
                            if (!$job) $job = $this->job_model->getByFileIdAndType($id, Job_model::TYPE_RENDER);
                            if ($job) $status .= ": $job[progress]%";
                        } else if ($file['status'] & File_model::STATUS_FINISHED) {
                            $this->load->library('InternetArchive', $this->config->config);
                            $this->data['S3URL'] = $this->internetarchive->getItemURL($file);
                            $this->data['downloadURL'] = $this->getDownloadURL($file);
                            // Show project files as rendered.
                            if ($this->data['isProject'] && is_file(config_item('transcode_path').$file['output_path'])) {
                                if (filesize(config_item('transcode_path').$file['output_path']) === 0) { //archived
                                    $status .= ' =&gt; ' . tr('status_rendered');
                                } else {
                                    $status .= ' =&gt; <a href="' . base_url(config_item('transcode_vdir') . $file['output_path']) . '">';
                                    $status .= tr('status_rendered') . '</a>';
                                }
                                if (($file['status'] & File_model::STATUS_PUBLISHED) && !empty($file['publish_id'])) {
                                    // Show publish status.
                                    $this->load->library('parser');
                                    $parseData = ['publish_id' => rawurlencode($file['publish_id'])];
                                    $publishUrl = $this->parser->parse_string(config_item('publish_url_template'), $parseData, true);
                                    $status .= " =&gt; <a href=\"$publishUrl\">";
                                    $status .= tr('status_published') . '</a>';
                                } else if ($file['status'] & File_model::STATUS_APPROVED) {
                                    $status .=  ' =&gt; ' . tr('status_approved');
                                }
                            }
                            // Ogg and WebM files are not transcoded and do not set output_path.
                            else if (is_file(config_item('transcode_path').$file['output_path'])) {
                                if (filesize(config_item('transcode_path').$file['output_path']) === 0) { // archived
                                    $status .= ' =&gt; ' . tr('status_converted');
                                } else {
                                    $status .= ' =&gt; <a href="' . base_url(config_item('transcode_vdir') . $file['output_path']) . '">';
                                    $status .= tr('status_converted') . '</a>';
                                }
                            }
                        }
                    }
                    if ($file['status'] & File_model::STATUS_ERROR) {
                        $this->load->model('job_model');
                        $job = $this->job_model->getByFileIdAndType($id, Job_model::TYPE_PUBLISH);
                        if ($job) {
                            $status .= ' =&gt; <a href="' . site_url('job/log/' . $job['id'])  . '">';
                            $status .= tr('status_error_publish') . '</a>';
                        } else {
                            $job = $this->job_model->getByFileIdAndType($id, Job_model::TYPE_RENDER);
                            if ($job) {
                                $status .= ' =&gt; <a href="' . site_url('job/log/' . $job['id'])  . '">';
                                $status .= tr('status_error_render') . '</a>';
                            } else {
                                $job = $this->job_model->getByFileIdAndType($id, Job_model::TYPE_TRANSCODE);
                                if ($job) {
                                    $status .= ' =&gt; <a href="' . site_url('job/log/' . $job['id'])  . '">';
                                    $status .= tr('status_error_transcode') . '</a>';
                                } else {
                                    $job = $this->job_model->getByFileIdAndType($id, Job_model::TYPE_VALIDATE);
                                    if ($job) {
                                        $status .= ' =&gt; <a href="' . site_url('job/log/' . $job['id'])  . '">';
                                        $status .= tr('status_error_validate') . '</a>';
                                    } else {
                                        $status .= ' =&gt; ' . tr('status_error_validate');
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $status = tr('status_noupload');
            }

            $this->data['heading'] = tr('file_view_heading', $file);
            $this->data['footer'] = tr('file_view_footer', $file);

            // Create table of metadata.
            $this->load->library('table');
            $this->table->set_template([
                'thead_open'            => '',
                'thead_close'           => '',
                'heading_row_start'     => '',
                'heading_row_end'       => '',
                'heading_cell_start'    => '',
                'heading_cell_end'      => '',
            ]);
            $this->table->set_heading('');
            $tableData = [
                ['<strong>'.tr('file_author').'</strong>', $file['author']],
                ['<strong>'.tr('file_keywords').'</strong>', implode(', ', explode("\t", $file['keywords']))],
                ['<strong>'.tr('file_recording_date').'</strong>', $file['recording_date']],
                ['<strong>'.tr('file_language').'</strong>', $this->user_model->getLanguageByKey($file['language'])],
                ['<strong>'.tr('file_license').'</strong>', $this->file_model->getLicenseByKey($file['license'])],
            ];
            if ($this->data['mime_type'])
                $tableData []= ['<strong>'.tr('file_mime_type').'</strong>', $file['mime_type']];
            if ($this->data['size_bytes']) {
                $this->load->helper('filesize');
                $tableData []= ['<strong>'.tr('file_size').'</strong>', FileSizeConvert($file['size_bytes'])];
            }
            if ($file['duration_ms']) {
                $seconds = $file['duration_ms'] / 1000;
                $hours = floor($seconds / 3600);
                $seconds -= $hours * 3600;
                $minutes = floor($seconds / 60);
                $seconds -= $minutes * 60;
                $time = sprintf('%02d:%02d:%02d', $hours, $minutes, round($seconds));
                $tableData [] = ['<strong>' . tr('file_duration') . '</strong>', $time];
            }
            $tableData []= ['<strong>'.tr('file_status').'</strong>', $status];
            $this->data['metadata'] = $this->table->generate($tableData);
            $this->table->clear();

            // Create table of properties.
            $properties = $file['properties']? json_decode($file['properties'], true) : array();
            if (array_key_exists('user', $properties)) {
                $this->table->set_heading(tr('file_properties_name'), tr('file_properties_value'));
                $this->table->set_caption(tr('file_properties'));
                $this->table->set_template([]);
                $this->data['properties'] = $this->table->generate($properties['user']);
            } else {
                unset($this->data['properties']);
            }

            // Only show the edit action if user-level and higher.
            $this->data['isEditable'] = ($this->session->userdata('role') >= User_model::ROLE_USER);
            // Only show the delete action if admin-level and higher.
            $this->data['isDeletable'] = ($this->session->userdata('role') >= User_model::ROLE_ADMIN);

            // Set data for the upload button for project revisions.
            if ($this->data['isProject']) {
                $this->data['upload_button_text'] = tr('file_upload_revision');
                if (!empty($file['source_path']) &&
                        is_file(config_item('upload_path').$file['source_path'])) {
                    $size = filesize(config_item('upload_path').$file['source_path']);
                    if ($size != $file['size_bytes'] && $size !== 0) {
                        // Need to resume file upload.
                        $this->data['size_bytes'] = $size;
                        $this->data['upload_button_text'] = tr('file_upload_resume',
                            ['filename' => basename($file['source_path'])]);
                    }
                }
            }

            // Create history table.
            $this->load->helper('url');
            $result = $this->file_model->getHistory($id);
            if (count($result)) {
                // History table pagination.
                $offset |= $this->input->get('offset');
                $this->load->library('pagination');
                $this->pagination->initialize([
                    'base_url' => site_url('file/'. $id),
                    'total_rows' => count($result)
                ]);
                $this->data['pagination'] = $this->pagination->create_links();

                $subset = array_slice($result, $offset, $this->pagination->per_page);
                foreach ($subset as &$row) {
                    $row['updated_at'] = anchor("file/history/$id/" . $row['revision'], htmlspecialchars($row['updated_at']));
                    $row['name'] = anchor('user/' . $row['name'], htmlspecialchars($row['name']));
                    unset($row['id']);
                }
                $this->load->library('table');
                $this->table->set_heading(tr('file_revision'), tr('file_updated_at'), tr('user_name'));
                $this->table->set_caption(tr('file_history_caption'));
                $this->table->set_template([]);
                $this->data['history'] = $this->table->generate($subset);
            } else {
                $this->data['history'] = '<em>' . tr('file_history_none') . '</em>';
            }

            // Create table of dependent files for projects.
            if ($this->data['isProject']) {
                $result = $this->file_model->getMissingFiles($id);
                if (count($result)) {
                    $this->load->library('table');
                    $this->table->set_heading(tr('file_missing_caption'));
                    $this->table->set_template([]);
                    $this->data['missing'] = $this->table->generate($result);
                }
                $result = $this->file_model->getChildren($id);
                if (count($result)) {
                    $this->load->helper('icon');
                    foreach ($result as &$row) {
                        $src = base_url(iconForMimeType($row['mime_type']));
                        $row['mime_type'] = '<img src="'.$src.'" width="20" height="20" title="'.$row['mime_type'].'">';
                        $row['title'] = anchor("file/$row[id]", htmlspecialchars($row['title']));
                        $url = $this->getDownloadURL($row);
                        $row['download'] = anchor($url, tr('download'), 'title="'. tr('download_tooltip') .'"');
                        unset($row['id']);
                        unset($row['source_path']);
                        unset($row['output_path']);
                        unset($row['status']);
                    }
                    $this->load->library('table');
                    $this->table->set_heading('', tr('file_title'), tr('file_author'), '');
                    $this->table->set_caption(tr('file_children_caption'));
                    $this->table->set_template([]);
                    $this->data['relations'] = $this->table->generate($result);
                }
            } else {
                // Create a table of projects that use this file.
                $result = $this->file_model->getProjects($id);
                if (count($result)) {
                    foreach ($result as &$row) {
                        $row['title'] = anchor("file/$row[id]", htmlspecialchars($row['title']));
                        unset($row['id']);
                    }
                    $this->load->library('table');
                    $this->table->set_heading(tr('file_title'), tr('file_author'), tr('file_updated_at'));
                    $this->table->set_caption(tr('file_projects_caption'));
                    $this->table->set_template([]);
                    $this->data['relations'] = $this->table->generate($result);
                }
            }

            // Build the page.
            $this->load->helper('form');
            $this->load->view('templates/header', $this->data);
            $this->load->view('file/view', $this->data);
            $this->load->view('templates/footer', $this->data);
        } else {
            show_404(uri_string());
        }
    }

    /**
     * Show a list of recently changed files.
     *
     * @param int $offset Optional pagination offset into the table.
     */
    public function recent($offset = 0)
    {
        $result = $this->file_model->getRecent();

        // Pagination.
        $this->load->library('pagination');
        if (count($result) > $this->pagination->per_page) {
            $subset = array_slice($result, intval($offset), $this->pagination->per_page);
            $this->pagination->initialize([
                'base_url' => site_url('file/recent'),
                'total_rows' => count($result)
            ]);
            $result = $subset;
        }

        // Post-process the data.
        $this->load->helper('url');
        $this->load->helper('icon');
        foreach ($result as &$row) {
            $src = base_url(iconForMimeType($row['mime_type']));
            $row['mime_type'] = '<img src="'.$src.'" width="20" height="20" title="'.$row['mime_type'].'">';
            $row['name'] = anchor('user/' . $row['name'], htmlspecialchars($row['name']));
            $row['title'] = anchor('file/' . $row['file_id'], htmlspecialchars($row['title']));
            unset($row['file_id']);
        }
        $this->data['recent'] = $result;

        $this->data['heading'] = tr('file_recent_heading');
        $this->load->library('table');
        $this->table->set_heading('', tr('file_title'), tr('user_name'), tr('file_updated_at'));
        $this->load->view('templates/header', $this->data);
        $this->load->view('file/recent', $this->data);
        $this->load->view('templates/footer', $this->data);
    }

    /**
     * Search for files.
     *
     * All input comes from GET/POST data.
     * HTTP GET performs a simple full-text search using the GET data 'q'.
     * HTTP POST performs an advanced search using multiple criteria supplied
     * in the POST data.
     */
    public function search()
    {
        $this->load->helper('form');
        if ('GET' == $this->input->method(true)) {
            // Simple query from header/menu/navbar.
            $query = $this->input->get('q');
            $this->data['title'] = $query;
            $this->data['description'] = '';
            $this->data['keywords'] = '';
            $this->data['author'] = '';
            $this->data['date_from'] = '';
            $this->data['date_to'] = '';
            $this->data['language'] = '';
            $this->data['license'] = '';

            // Do simple fulltext search.
            if (strlen($query) > 0) {
                $results = $this->file_model->search($query);
            }
        } else if ('POST' == $this->input->method(true)) {
            // Advanced query from form.
            $query = tr('advanced');

            // Load existing values into form.
            $this->data['title'] = $this->input->post('title');
            $this->data['description'] = $this->input->post('description');
            $this->data['keywords'] = $this->input->post('keywords');
            $this->data['author'] = $this->input->post('author');
            $this->data['date_from'] = $this->input->post('date_from');
            $this->data['date_to'] = $this->input->post('date_to');
            $this->data['language'] = $this->input->post('language');
            $this->data['license'] = $this->input->post('license');

            // Do advanced search.
            $results = $this->file_model->search($this->data);
        }

        if (isset($results) && is_array($results)) {
            // Post-process the data.
            $this->load->helper('icon');
            $this->load->helper('url');
            foreach ($results as &$row) {
                $src = base_url(iconForMimeType($row['mime_type']));
                $row['mime_type'] = '<img src="'.$src.'" width="20" height="20" title="'.$row['mime_type'].'">';
                $row['name'] = anchor('user/' . $row['name'], htmlspecialchars($row['name']));
                $row['title'] = anchor('file/' . $row['file_id'], htmlspecialchars($row['title']));
                unset($row['file_id']);
                unset($row['relevance']);
            }
            $this->data['results'] = $results;

            $this->load->library('table');
            $this->table->set_heading('', tr('file_title'), tr('file_author'), tr('user_name'), tr('file_updated_at'));
        }

        // Prepare drop-downs for advanced search form.
        $this->data['languages'] = $this->user_model->getLanguages();
        array_unshift($this->data['languages'], '');
        $this->data['licenses'] = $this->file_model->getLicenses();
        array_unshift($this->data['licenses'], '');

        $this->data['heading'] = tr('file_search_results_heading', ['query' => $query]);
        $this->load->view('templates/header', $this->data);
        $this->load->view('file/search_results', $this->data);
        $this->load->view('templates/footer', $this->data);
    }

    /**
     * Delete a file record
     *
     * @param int $id Optional file record ID - uses GET data if omitted.
     */
    public function delete($id = null)
    {
        // Check permission.
        if ($this->session->userdata('role') < User_model::ROLE_ADMIN) {
            show_error(tr('file_error_permission'), 403, tr('file_error_heading'));
            return;
        }
        if (!$id)
            $id = $this->input->post_get('id');
        $file = $this->file_model->getById($id);
        if ($file && $file['id']) {
            $this->file_model->delete($id);
            $this->load->view('templates/header', $this->data);
            $this->load->view('main/index', $this->data);
            $this->load->view('templates/footer', $this->data);
        } else {
            show_404(uri_string());
        }
    }

    /**
     * View the changes in a revision of a file record.
     *
     * @param int $id The file record ID.
     * @param int $revision The revision number to view.
     */
    public function history($id = null, $revision = null)
    {
        if ($id && $revision !== null) {
            $file = $this->file_model->getById($id);
            if ($file) {
                // Make an array of the changes.
                $current = $this->file_model->getHistoryByRevision($id, $revision);
                $file['updated_at'] = $current['updated_at'];
                $file['comment'] = $current['comment'];
                $this->data['file_history_id'] = $current['id'];
                unset($current['id']);
                unset($current['updated_at']);
                unset($current['comment']);
                if ($revision > 0) {
                    $previous = $this->file_model->getHistoryByRevision($id, $revision - 1);
                    unset($previous['id']);
                    unset($previous['updated_at']);
                    unset($previous['comment']);
                    $changes = array_diff_assoc($previous, $current);
                } else {
                    $changes = $current;
                    foreach ($changes as &$value)
                        $value = null;
                }
                if (isset($changes['language']))
                    $changes['language'] = $this->user_model->getLanguageByKey($changes['language']);
                if (isset($current['language']))
                    $current['language'] = $this->user_model->getLanguageByKey($current['language']);
                if (isset($changes['license']))
                    $changes['license'] = $this->file_model->getLicenseByKey($changes['license']);
                if (isset($current['license']))
                    $current['license'] = $this->file_model->getLicenseByKey($current['license']);
                if (isset($changes['keywords']))
                    $changes['keywords'] = implode(', ', explode("\t", $changes['keywords']));
                if (isset($current['keywords']))
                    $current['keywords'] = implode(', ', explode("\t", $current['keywords']));
                if (isset($current['properties'])) {
                    $properties = json_decode($current['properties'], true);
                    if ($properties !== null && array_key_exists('user', $properties)) {
                        $current['properties'] = $this->implode_assoc($properties['user']);
                    } else {
                        unset($current['properties']);
                    }
                }
                if (isset($changes['properties'])) {
                    $properties = json_decode($changes['properties'], true);
                    if ($properties !== null && array_key_exists('user', $properties)) {
                        $changes['properties'] = $this->implode_assoc($properties['user']);
                    } else {
                        $changes['properties'] = '';
                    }
                }

                $this->data = array_merge($this->data, $file);
                $this->data['username'] = anchor('user/' . $this->data['username'], $this->data['username']);
                $this->data['revision'] = ($revision > 0)? $revision : '';
                $this->data['current'] = $current;
                $this->data['changes'] = $changes;
                $this->data['heading'] = tr('file_view_heading', $this->data);
                $this->data['subheading'] = tr('file_differences_heading', $this->data);
                $this->data['footer'] = tr('file_view_footer', $this->data);

                // Only show the restore action if admin-level and higher.
                $this->data['isRestorable'] = false && ($this->session->userdata('role') >= User_model::ROLE_ADMIN);

                $this->load->library('MltXmlReader');
                $this->data['isDownloadable'] = $this->mltxmlreader->isMimeTypeMltXml($file['mime_type']);

                // Build the page.
                $this->load->view('templates/header', $this->data);
                $this->load->view('file/history', $this->data);
                $this->load->view('templates/footer', $this->data);
                return;
            }
        }
        show_404(uri_string());
    }

    /**
     * Format an array of name/value key pairs.
     *
     * @param array An array of associative arrays.
     * @return string
     */
    protected function implode_assoc($array)
    {
        $s = '';
        $n = count($array);
        foreach ($array as $item) {
            $n--;
            $s .= implode(' = ', $item);
            if ($n) $s .= ', ';
        }
        return $s;
    }

    /**
     * Query for keywords, returning result as JSON for the select2 jQuery UI plugin.
     *
     * Takes not parameter, but uses 'q' from GET data as the substring query.
     */
    public function keywords()
    {
        $this->output->set_content_type('application/json');
        $result = array();
        if ($this->input->get('q')) {
            $this->db->select('value as id, value as text');
            $this->db->like('value', $this->input->get('q'));
            $this->db->where('language', $this->config->item('language'));
            $this->db->order_by('value', 'asc');
            $query = $this->db->get('keyword');
            if (count($query->result()))
                $result = $query->result();
            else
                $result = [['id' => $this->input->get('q'), 'text' => $this->input->get('q')]];
        }
        $this->output->set_output(json_encode($result));
    }

    /**
     * Get the URL to use to download this file's output if converted or upload if not converted.
     *
     * @param file A file record
     * @return string Return the URL or null if the file is not valid or uploaded.
     */
    public function getDownloadURL($file)
    {
        if ($file['status'] & File_model::STATUS_VALIDATED) {
            if ($file['output_path']) {
                $filename = config_item('transcode_path').$file['output_path'];
                if (filesize($filename)) {
                    return site_url('file/download/' . $file['id']);
                } else {
                    $this->load->library('InternetArchive', $this->config->config);
                    return $this->internetarchive->getDownloadURL($file['id'], $file['output_path']);
                }
            } else if ($file['source_path']) {
                $filename = config_item('upload_path').$file['source_path'];
                if (filesize($filename)) {
                    return site_url('file/download/' . $file['id']);
                } else {
                    $this->load->library('InternetArchive', $this->config->config);
                    return $this->internetarchive->getDownloadURL($file['id'], $file['source_path']);
                }
            }
        }
        return null;
    }

    /**
     * Force downwload of a media file.
     *
     * A forced download prevents the browser from trying to play the file.
     *
     * @param int $id The file record ID.
     */
    public function download($id)
    {
        $file = $this->file_model->getById($id);
        if ($file && ($file['status'] & File_model::STATUS_VALIDATED)) {
            if ($file['output_path']) {
                $filename = config_item('transcode_path').$file['output_path'];
                if (filesize($filename)) {
                    $this->load->helper('download');
                    force_download($filename, null);
                } else {
                    $this->load->library('InternetArchive', $this->config->config);
                    $this->internetarchive->forceDownload($id, $file['output_path']);
                }
            } else if ($file['source_path']) {
                $filename = config_item('upload_path').$file['source_path'];
                if (filesize($filename)) {
                    $this->load->helper('download');
                    force_download($filename, null);
                } else {
                    $this->load->library('InternetArchive', $this->config->config);
                    $this->internetarchive->forceDownload($id, $file['source_path']);
                }
            }
        }
        show_404(uri_string());
    }

    /** Force download of a project file.
     *
     * A forced download prevents the browser from trying to open the file.
     *
     * @param int $id The file record ID.
     */
    public function download_project($id)
    {
        $file = $this->file_model->getById($id);
        if ($file && ($file['status'] & File_model::STATUS_VALIDATED)) {
            if ($file['source_path']) {
                $isRestored = false;
                $filename = config_item('upload_path').$file['source_path'];
                $log = '';
                // Restore file from archive if needed.
                if (!filesize($filename)) {
                    $log .= "Restoring from archive: $filename.\n";
                    $this->load->library('InternetArchive', $this->config->config);
                    $this->internetarchive->download($id , $filename);
                    $isRestored = true;
                }
                if (filesize($filename)) {
                    $this->load->library('MltXmlHelper');
                    $childFiles = $this->mltxmlhelper->getFilesData($filename, $log);
                    $isValid = $this->mltxmlhelper->substituteProxyFiles($this->file_model, $file, $childFiles, $log);

                    // If still valid, create a new version of the XML with proxy clips.
                    if ($isValid) {
                        // If still valid, get new metadata for each proxy file.
                        //TODO: Generate this proxy version of the project at validation time and store it.
                        //$this->mltxmlhelper->getFileMetadata($childFiles, $log);
                        // Prepare the output file.
                        $this->load->library('MltXmlWriter');
                        $xml = $this->mltxmlwriter->run($childFiles, $filename);

                        if ($isRestored) {
                            // Truncate the restored files.
                            // force_download() calls exit() so we must cleanup now.
                            fclose(fopen($filename, 'w'));
                        }

                        $this->load->helper('download');
                        force_download(basename($file['source_path']), $xml);
                    } else {
                        show_error($log, 500, tr('file_error_heading'));
                        return;
                    }
                }
            }
        }
        show_404(uri_string());
    }

    /** Force download of an old project file.
     *
     * A forced download prevents the browser from trying to open the file.
     *
     * @param int $id The file_history record ID.
     */
    public function download_history($id)
    {
        $file = $this->file_model->getHistoryById($id);
        if ($file) {
            if ($file['source_path']) {
                $isRestored = false;
                $filename = config_item('upload_path').$file['source_path'];
                // Restore file from archive if needed.
                if (!filesize($filename)) {
                    $log = "Restoring from archive: $filename.\n";
                    $this->load->library('InternetArchive', $this->config->config);
                    $this->internetarchive->download($id , $filename);
                    $isRestored = true;
                }
                if (filesize($filename)) {
                    $this->load->library('MltXmlHelper');
                    $log = '';
                    $childFiles = $this->mltxmlhelper->getFilesData($filename, $log);
                    $isValid = $this->mltxmlhelper->substituteProxyFiles($this->file_model, $file, $childFiles, $log);

                    // If still valid, create a new version of the XML with proxy clips.
                    if ($isValid) {
                        // If still valid, get new metadata for each proxy file.
                        //TODO: Generate this proxy version of the project at validation time and store it.
                        //$this->mltxmlhelper->getFileMetadata($childFiles, $log);
                        // Prepare the output file.
                        $this->load->library('MltXmlWriter');
                        $xml = $this->mltxmlwriter->run($childFiles, $filename);

                        if ($isRestored) {
                            // Truncate the restored files.
                            // force_download() calls exit() so we must cleanup now.
                            fclose(fopen($filename, 'w'));
                        }

                        $this->load->helper('download');
                        force_download(basename($file['source_path']), $xml);
                    } else {
                        show_error($log, 500, tr('file_error_heading'));
                        return;
                    }
                }
            }
        }
        show_404(uri_string());
    }

    /**
     * Create a job to publish the rendered project file.
     *
     * @param int $id The file record ID
     */
    public function publish($id)
    {
        $file = $this->file_model->getById($id);
        if ($file) {
            // Add a job to the database.
            $this->load->model('job_model');
            $job_id = $this->job_model->create($id, Job_model::TYPE_PUBLISH);
            if ($job_id) {
                // Put job into the queue.
                $this->load->library('Beanstalk', ['host' => config_item('beanstalkd_host')]);
                if ($this->beanstalk->connect()) {
                    $tube = config_item('beanstalkd_tube_publish');
                    $this->beanstalk->useTube($tube);
                    $priority = 10;
                    $delay = 3;
                    $ttr = config_item('beanstalkd_timeout'); // seconds
                    $jobId = $this->beanstalk->put($priority, $delay, $ttr, $job_id);
                    $this->beanstalk->disconnect();

                    // Indicate that it was submitted for publishing.
                    $this->file_model->staticUpdate($id, [
                        'status' => $file['status'] | File_model::STATUS_APPROVED
                    ]);

                    // Show the file view page.
                    redirect(site_url("file/$id"));
                } else {
                    show_error(tr('file_error_publish'), 500, tr('file_error_heading'));
                }
            } else {
                show_error(tr('file_error_publish'), 500, tr('file_error_heading'));
            }
        } else {
            show_404(uri_string());
        }
    }
}
