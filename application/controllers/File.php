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
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $file = $this->file_model->getById($id);
            $this->data = array_merge($this->data, $file);
            if ($id === null) {
                $this->data['author'] = $this->session->userdata('username');
            }
        } else /*TODO assuming POST */ {
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
                    'title' => $this->input->post('title'),
                    'author' => $this->input->post('author'),
                    'description' => $this->input->post('description'),
                    'language' => $this->input->post('language'),
                    'license' => $this->input->post('license'),
                ];
                // Only the bureaucrat can change the role.
//                 if ($this->session->userdata('role') == User_model::ROLE_BUREAUCRAT)
//                     $data['role'] = $this->input->post('role');

                if ($id === null) {
                    $id = $this->file_model->create($data);
                    if ($id !== null) {
                        // If successful, goto view page.
                        $this->view($id);
                    } else {
                        show_error(tr('file_error_update'), 500, tr('file_error_heading'));
                    }
                } else if ($this->file_model->update($id, $data)) {
                    // If successful, goto view page.
                    $this->view($id);
                } else {
                    show_error(tr('file_error_update'), 500, tr('file_error_heading'));
                }
                return;
            } else {
                $this->data['id'] = $id;
                $this->data = array_merge($this->data, $_POST);
            }
        }
        // Display form.
        $this->load->helper('form');
        // Build arrays for dropdowns.
        $this->data['languages'] = [
            'en' => tr('lang_en'),
            'de' => tr('lang_de')
        ];
        $this->data['licenses'] = $this->file_model->getLicenses();
        // Only a bureaucrat can edit the role.
//         $this->data['roleAttributes'] = null;
//         if ($this->session->userdata('role') != User_model::ROLE_BUREAUCRAT)
//             $this->data['roleAttributes'] = 'disabled';
        if (isset($this->data['id'])) {
            $this->data['heading'] = tr('file_edit_heading', $this->data);
            $this->data['message'] = tr('file_edit_message', $this->data);
        } else {
            $this->data['heading'] = tr('file_new_heading');
            $this->data['message'] = tr('file_new_message');
        }
        $this->load->view('templates/header', $this->data);
        $this->load->view('file/edit', $this->data);
        $this->load->view('templates/footer', $this->data);
    }

    public function view($id = null)
    {
        if (!$id) $id = $this->input->post('id');
        if (!$id) $id = $this->input->get('id');
        $file = $this->file_model->getById($id);
        if ($file) {
            $data = array_merge($file, [
                'session' => $this->session->userdata(),
                'heading' => tr('file_view_heading', $file),
                'footer' => tr('file_view_footer', $file),
            ]);
            // Only show the edit action if user-level and higher.
            $data['isEditable'] = ($this->session->userdata('role') >= User_model::ROLE_USER);
            $this->load->view('templates/header', $data);
            $this->load->view('file/view', $data);
            $this->load->view('templates/footer', $data);
        } else {
            show_404('', false);
        }
    }
}