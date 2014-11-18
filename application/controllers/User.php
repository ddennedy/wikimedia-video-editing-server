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
    public function __construct()
    {
        parent::__construct();
        $this->load->model('user_model');
    }

    public function login()
    {
        $this->load->helper('form');
        $this->load->library('form_validation');
        $this->form_validation->set_rules('username', 'lang:lang_login_username',
            'required|callback_exists');
        $this->form_validation->set_rules('password', 'lang:lang_login_password',
            'required|callback_check_password');

        // Display login form
        $data = array();
        $data = array_merge($data, $this->lang->language);
        $this->load->view('templates/header', $data);
        if ($this->form_validation->run())
            $this->load->view('user/login_success', $data);
        else
            $this->load->view('user/login', $data);
        $this->load->view('templates/footer', $data);
    }

    function exists($name)
    {
        if (count($this->user_model->getByName($name)))
            return true;
        else
            $this->form_validation->set_message('exists', $this->lang->line('lang_error_invalid_user'));
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
                $this->lang->line('lang_error_incorrect_password'));
        return false;
    }
}
