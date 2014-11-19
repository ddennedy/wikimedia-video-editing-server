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

    function oauth_initiate()
    {
        $this->load->library('OAuth', $this->config->config);
        $result = $this->oauth->initiate();
        $this->session->set_flashdata('oauthRequestToken', $result->key);
        $this->session->set_flashdata('oauthRequestSecret', $result->secret);
        redirect($this->oauth->redirect($result->key));
    }

    function oauth_callback()
    {
        $verifyCode = $this->input->get('oauth_verifier');
        $requestToken = $this->input->get('oauth_token');
        if ($requestToken == $this->session->flashdata('oauthRequestToken')) {
            $this->load->library('OAuth', $this->config->config);
            $secret = $this->session->flashdata('oauthRequestSecret');
            $result = $this->oauth->token($requestToken, $secret, $verifyCode);
            if ($result) {
                $accessToken = $result->key;
                $secret = $result->secret;
                $issuer = $this->config->item('oauth_jwt_issuer');
                $identity = $this->oauth->identify($accessToken, $secret, $issuer);
                $this->session->set_userdata('username', $identity->username);
                $this->session->set_userdata('access_token', $accessToken);
                $row = $this->user_model->getByName($identity->username);
                if (count($row)) {
                    // Update the accessToken if they are in database.
                    $this->user_model->setAccessTokenByName($identity->username, $accessToken);
                    // Attach identity to session if already registered.
                    $user = $this->user_model->getByName($identity->username);
                    $this->session->set_userdata('role', $user['role']);
                    $this->data['session'] = $this->session->userdata();

                    //TODO Take them back to previous page. For now, show main page.
                    $this->load->view('templates/header', $this->data);
                    $this->load->view('main/index', $this->data);
                    $this->load->view('templates/footer', $this->data);
                } else {
                    // Ask user if they want to register.
                    $this->load->helper('form');
                    $this->load->view('templates/header', $this->data);
                    $this->session->set_userdata('role', user_model::ROLE_GUEST);
                    $this->data['session'] = $this->session->userdata();
                    $this->load->view('user/register', $this->data);
                    $this->load->view('templates/footer', $this->data);
                }
            } else {
                show_error('Error getting access token.', 'Login');
            }
        } else {
            show_error('OAuth request tokens mismatch.', 'Login');
        }
    }

    function oauth_redirect($requestToken)
    {
        $this->load->library('OAuth', $this->config->config);
        $result = $this->oauth->redirect($requestToken);
        $this->output->set_output($result);
    }

    function oauth_token($requestToken, $secret, $verifyCode)
    {
        $this->load->library('OAuth', $this->config->config);
        $result = $this->oauth->token($requestToken, $secret, $verifyCode);
        //TODO save $result->key as accessToken
        $this->output->set_output(json_encode($result));
    }

    function oauth_identify($accessToken, $secret)
    {
        $this->load->library('OAuth', $this->config->config);
        $issuer = $this->config->item('oauth_jwt_issuer');
        $result = $this->oauth->identify($accessToken, $secret, $issuer);
        $this->output->set_output(json_encode($result));
    }

    function register()
    {
        $data = [
            'name' => $this->session->userdata('username'),
            'access_token' => $this->session->userdata('access_token'),
            'role' => user_model::ROLE_USER
        ];
        if (!count($this->user_model->getByName($data['name']))) {
            if ($this->user_model->create($data) !== false) {
                $this->session->set_userdata('role', $data['role']);
                $this->data['session'] = $this->session->userdata();

                //TODO Take them back to previous page. For now, show main page.
                $this->load->view('templates/header', $this->data);
                $this->load->view('main/index', $this->data);
                $this->load->view('templates/footer', $this->data);
            }
        }
    }
}
