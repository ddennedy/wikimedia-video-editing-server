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

class User extends CI_Controller
{
    private $data = array();

    public function __construct()
    {
        parent::__construct();
        $this->load->model('user_model');
        $this->data['session'] = $this->session->userdata();
        $this->data['lang'] = $this->lang->language;
    }

    public function login()
    {
        $this->load->helper('form');
        $this->load->library('form_validation');
        $this->form_validation->set_rules('username', 'lang:login_username',
            'required|callback_exists');
        $this->form_validation->set_rules('password', 'lang:login_password',
            'required|callback_check_password');

        // Display login form
        if ($this->form_validation->run()) {
            $username = $this->input->post('username');
            $user = $this->user_model->getByName($username);
            $this->session->set_userdata('username', $username);
            $this->session->set_userdata('role', $user['role']);
            $this->data['session'] = $this->session->userdata();
            $this->data['role'] = $this->user_model->getRoleName($user['role']);
            $this->load->view('templates/header', $this->data);
            $this->load->view('user/login_success', $this->data);
        } else {
            $this->load->view('templates/header', $this->data);
            $this->load->view('user/login', $this->data);
        }
        $this->load->view('templates/footer', $this->data);
    }

    function exists($name)
    {
        if (count($this->user_model->getByName($name)))
            return true;
        else
            $this->form_validation->set_message('exists', $this->lang->line('error_invalid_user'));
        return false;
    }

    function check_password($password)
    {
        $username = $this->input->post('username');
        if (!$this->exists($username))
            return true;
        if ($this->user_model->login($username, $password))
            return true;
        else
            $this->form_validation->set_message('check_password',
                $this->lang->line('error_incorrect_password'));
        return false;
    }

    function logout()
    {
        $this->session->sess_destroy();
        $this->data['session'] = $this->session->userdata();
        $this->load->view('templates/header', $this->data);
        $this->load->view('main/index', $this->data);
        $this->load->view('templates/footer', $this->data);
    }
}
