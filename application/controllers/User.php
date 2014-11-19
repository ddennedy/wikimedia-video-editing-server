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
    }

    private function oldlogin()
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

    private function exists($name)
    {
        if (count($this->user_model->getByName($name)))
            return true;
        else
            $this->form_validation->set_message('exists', tr('error_invalid_user'));
        return false;
    }

    private function check_password($password)
    {
        $username = $this->input->post('username');
        if (!$this->exists($username))
            return true;
        if ($this->user_model->login($username, $password))
            return true;
        else
            $this->form_validation->set_message('check_password',
                tr('error_incorrect_password'));
        return false;
    }

    public function login()
    {
        // First, see if we remember the user by cookie.
        $username = $this->user_model->getUsernameFromCookie();
        if ($username) {
            // Lookup user in database.
            $user = $this->user_model->getByName($username);
            if ($user && $user['access_token']) {
                // User exists and has access token - verify it.
                $this->load->library('OAuth', $this->config->config);

                // Request the user's identity through OAuth.
                $accessToken = $user['access_token'];
                $secret = '__unused__';
                $issuer = $this->config->item('oauth_jwt_issuer');
                $identity = $this->oauth->identify($accessToken, $secret, $issuer);

                if ($identity && $identity->username == $username) {
                    // Login successful.
                    $this->session->set_userdata('username', $username);
                    $this->session->set_userdata('role', $user['role']);
                    $this->data['session'] = $this->session->userdata();
                    $this->user_model->putUsernameInCookie($identity->username);

                    //TODO Take them back to previous page. For now, show main page.
                    $this->load->view('templates/header', $this->data);
                    $this->load->view('main/index', $this->data);
                    $this->load->view('templates/footer', $this->data);
                    return;
                }
            }
        }
        // Otherwise, start OAuth.
        $this->oauth_initiate();
    }

    public function logout()
    {
        $this->session->sess_destroy();
        $this->load->helper('cookie');
        delete_cookie(User_model::COOKIE_NAME);
        $this->data['session'] = $this->session->userdata();
        $this->load->view('templates/header', $this->data);
        $this->load->view('main/index', $this->data);
        $this->load->view('templates/footer', $this->data);
    }

    private function oauth_initiate()
    {
        $this->load->library('OAuth', $this->config->config);
        $result = $this->oauth->initiate();
        if (isset($result->key) && isset($result->secret)) {
            $this->session->set_flashdata('oauthRequestToken', $result->key);
            $this->session->set_flashdata('oauthRequestSecret', $result->secret);
            redirect($this->oauth->redirect($result->key));
        } else {
            show_error(tr('user_error_oauth_init'), tr('user_error_login_heading'));
        }
    }

    public function oauth_callback()
    {
        // Validate callback data.
        $verifyCode = $this->input->get('oauth_verifier');
        $requestToken = $this->input->get('oauth_token');
        if ($requestToken == $this->session->flashdata('oauthRequestToken') && $verifyCode) {
            // Request an OAuth access token.
            $this->load->library('OAuth', $this->config->config);
            $secret = $this->session->flashdata('oauthRequestSecret');
            $result = $this->oauth->token($requestToken, $secret, $verifyCode);
            // Verify the access token was received.
            if ($result && isset($result->key)) {
                // Request the user's identity through OAuth.
                $accessToken = $result->key;
                $secret = $result->secret;
                $issuer = $this->config->item('oauth_jwt_issuer');
                $identity = $this->oauth->identify($accessToken, $secret, $issuer);

                // Remember the user.
                $this->session->set_userdata('username', $identity->username);

                // See if the user is already registered.
                $row = $this->user_model->getByName($identity->username);
                if (count($row)) {
                    // Update the accessToken if they are in the database.
                    $this->user_model->setAccessTokenByName($identity->username, $accessToken);

                    // Login successful.
                    $user = $this->user_model->getByName($identity->username);
                    $this->session->set_userdata('role', $user['role']);
                    $this->data['session'] = $this->session->userdata();
                    $this->user_model->putUsernameInCookie($identity->username);

                    //TODO Take them back to previous page. For now, show main page.
                    $this->load->view('templates/header', $this->data);
                    $this->load->view('main/index', $this->data);
                    $this->load->view('templates/footer', $this->data);
                } else {
                    // Ask user if they want to register.
                    // Save the access token for the register step.
                    $this->session->set_userdata('access_token', $accessToken);
                    $this->load->helper('form');
                    $this->load->library('parser');
                    $this->load->view('templates/header', $this->data);
                    $this->session->set_userdata('role', user_model::ROLE_GUEST);
                    // Reload session data into view data.
                    $this->data['session'] = $this->session->userdata();
                    $template = tr('user_register_heading');
                    $templateData = ['username' => $identity->username];
                    $this->data['heading'] = $this->parser->parse_string($template,
                        $templateData, true);
                    $this->load->view('user/register', $this->data);
                    $this->load->view('templates/footer', $this->data);
                }
            } else {
                show_error(tr('user_error_oauth_access'), tr('user_error_login_heading'));
            }
        } elseif ($verifyCode) {
            show_error(tr('user_error_oauth_request'), tr('user_error_login_heading'));
        } else {
            show_error(tr('user_error_oauth_verify'), tr('user_error_login_heading'));
        }
    }

    public function register()
    {
        $data = [
            'name' => $this->session->userdata('username'),
            'access_token' => $this->session->userdata('access_token'),
            'role' => user_model::ROLE_USER
        ];
        // Ensure user does not exist.
        if (!count($this->user_model->getByName($data['name']))) {
            // Add user to database.
            if ($this->user_model->create($data) !== false) {
                // Log the user into the session.
                $this->session->set_userdata('role', $data['role']);
                $this->data['session'] = $this->session->userdata();
                $this->user_model->putUsernameInCookie($data['name']);

                //TODO Take them back to previous page. For now, show main page.
                $this->load->view('templates/header', $this->data);
                $this->load->view('main/index', $this->data);
                $this->load->view('templates/footer', $this->data);
            }
        }
    }

    public function view($name)
    {
        $user = $this->user_model->getByName($name);
        if ($user) {
            if (!$user['comment'])
                $user['comment'] = tr('user_view_nocomment');
            $this->load->helper('date');
            //TODO Convert time from UTC to user's timezone.
            //$user['updated_at'] = date('Y-m-j G:i', gmt_to_local($user['updated_at']));
            //TODO Use the intl extension to format date.
            $data = array_merge($user, [
                'session' => $this->session->userdata(),
                'heading' => tr('user_view_heading', $user),
                'footer' => tr('user_view_footer', $user),
            ]);
            $this->load->view('templates/header', $data);
            $this->load->view('user/view', $data);
            $this->load->view('templates/footer', $data);
        } else {
            show_404(tr('user_error_unknown'), tr('user_error_user_heading'));
        }
    }
}
