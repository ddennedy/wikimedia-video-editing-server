<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Wikimedia Video Editing Server
 * Copyright (C) 2014-2015 Dan R. Dennedy <dan@dennedy.org>
 * Copyright (C) 2014-2015 CDC Leuphana University Lueneburg
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

    /** Construct a User CodeIgniter Controller */
    public function __construct()
    {
        parent::__construct();
        $this->load->model('user_model');
        $this->data['session'] = $this->session->userdata();
    }

    /**
     * Initiate the manual login process with OAuth provider.
     *
     * @see Autologin_model::__construct()
     */
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
                    $this->establishSession($username, $user['role'], $user['language']);
                    $this->user_model->putUsernameInCookie($identity->username);

                    //TODO Take them back to previous page. For now, show the user page.
                    $this->index($username);
                    return;
                }
            }
        }
        if ($this->config->item('auth') === User_model::AUTH_LOCAL) {
            $this->load->helper('form');
            $this->load->library('form_validation');
            $this->form_validation->set_rules('username', 'lang:login_username',
                'required|callback_exists');
            $this->form_validation->set_rules('password', 'lang:login_password',
                'required|callback_check_password');

            // Display login form
            if ($this->form_validation->run()) {
                $data = $this->user_model->getByName($this->input->post('username'));
                $this->establishSession($data['name'], $data['role'], $data['language']);
                $this->user_model->putUsernameInCookie($data['name']);
                //TODO Take them back to previous page. For now, show the user page.
                $this->index($data['name']);
                return;
            } else {
                $this->data['heading'] = tr('login_heading');
                $this->load->view('templates/header', $this->data);
                $this->load->view('user/login', $this->data);
                $this->load->view('templates/footer', $this->data);
            }
        } else {
            // Otherwise, start OAuth.
            $this->oauth_initiate();
        }
    }

    function exists($name)
    {
        if (count($this->user_model->getByName($name)))
            return true;
        else
            $this->form_validation->set_message('exists', $this->lang->line('login_error_invalid_user'));
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
                $this->lang->line('login_error_incorrect_password'));
        return false;
    }

    /**
     * Log out the current user and destroy their session.
     */
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

    /**
     * Peform the initiate phase of OAuth.
     *
     * @access private
     */
    private function oauth_initiate()
    {
        $this->load->library('OAuth', $this->config->config);
        $result = $this->oauth->initiate();
        if (isset($result->key) && isset($result->secret)) {
            $this->session->set_flashdata('oauthRequestToken', $result->key);
            $this->session->set_flashdata('oauthRequestSecret', $result->secret);
            redirect($this->oauth->redirect($result->key));
        } else {
            show_error(tr('user_error_oauth_init'), 500, tr('user_error_login_heading'));
        }
    }

    /**
     * Process a the OAuth provider's call back to our site.
     *
     * If the user is not registered in our app, a page is displayed to ask them
     * if they want to be remembered and use the app as more than guest.
     */
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

                // See if the user is already registered.
                $row = $this->user_model->getByName($identity->username);
                if (count($row)) {
                    // Update the accessToken if they are in the database.
                    $this->user_model->setAccessTokenByName($identity->username, $accessToken);

                    // Login successful.
                    $user = $this->user_model->getByName($identity->username);
                    $this->establishSession($identity->username, $user['role'], $user['language']);
                    $this->user_model->putUsernameInCookie($identity->username);

                    //TODO Take them back to previous page. For now, show the user page.
                    $this->index($identity->username);
                } else {
                    // Ask user if they want to register.
                    // Save the access token for the register step.
                    $this->session->set_userdata([
                        'username' => $identity->username,
                        'role' => User_model::ROLE_GUEST,
                        'access_token' => $accessToken
                    ]);
                    if (config_item('auto_register')) {
                        $this->register();
                    } else {
                        // Reload session data into view data.
                        $this->data['session'] = $this->session->userdata();

                        $this->load->helper('form');
                        $this->load->library('parser');
                        $this->load->view('templates/header', $this->data);
                        $template = tr('user_register_heading');
                        $templateData = ['username' => $identity->username];
                        $this->data['heading'] = $this->parser->parse_string($template,
                            $templateData, true);
                        $this->load->view('user/register', $this->data);
                        $this->load->view('templates/footer', $this->data);
                    }
                }
            } else {
                show_error(tr('user_error_oauth_access'), 500, tr('user_error_login_heading'));
            }
        } elseif ($verifyCode) {
            show_error(tr('user_error_oauth_request'), 500, tr('user_error_login_heading'));
        } else {
            show_error(tr('user_error_oauth_verify'), 500, tr('user_error_login_heading'));
        }
    }

    /**
     * Process the user request to be added to the app.
     *
     * The first user added to the app is a bureaucrat; otherwise, users are
     * added as a simple user until they are assigned a higher role by a bureaucrat.
     */
    public function register()
    {
        if ($this->config->item('auth') === User_model::AUTH_LOCAL) {
            $this->register_local();
        } else {
            $this->register_oauth();
        }
    }

    protected function register_local()
    {
        $this->load->helper('form');
        $this->load->library('form_validation');
        $this->form_validation->set_rules('username', 'lang:login_username',
            'trim|max_length[255]|xss_clean|required|callback_not_exists');
        $this->form_validation->set_rules('password', 'lang:login_password',
            'required|xss_clean');
        $this->form_validation->set_rules('confirm_password', 'lang:login_password',
            'required|matches[password]|xss_clean');

        if ('POST' == $this->input->method(true) && $this->form_validation->run()) {
            // Update database
            $data = [
                'name' => $this->input->post('username'),
                'password' => $this->input->post('password'),
                'role' => User_model::ROLE_USER,
                'language' => $this->input->post('language')
            ];
            // The first user is a bureaucrat. Subsequent users default to user.
            if ($this->db->count_all('user') === 0)
                $data['role'] = User_model::ROLE_BUREAUCRAT;
            if ($this->user_model->create($data) !== false) {
                $this->establishSession($data['name'], $data['role'], $data['language']);
                $this->user_model->putUsernameInCookie($data['name']);
                $this->data['role'] = $this->user_model->getRoleByKey($data['role']);
                $this->load->view('templates/header', $this->data);
                $this->load->view('user/login_success', $this->data);
            }
        } else {
            // Display registration form
            $this->data = array_merge($this->data, $_POST);
            $this->data['heading'] = tr('user_register_local_heading');
            $this->data['languages'] = $this->user_model->getLanguages();
            if ('GET' === $this->input->method(true))
                $this->data['language'] = $this->config->item('language');
            $this->load->view('templates/header', $this->data);
            $this->load->view('user/register_local', $this->data);
        }
        $this->load->view('templates/footer', $this->data);
    }

    function not_exists($name)
    {
        if (count($this->user_model->getByName($name)))
            $this->form_validation->set_message('not_exists', tr('user_error_register'));
        else
            return true;
        return false;
    }

    /**
     * Process the user request to be added to the app.
     *
     * The first user added to the app is a bureaucrat; otherwise, users are
     * added as a simple user until they are assigned a higher role by a bureaucrat.
     */
    protected function register_oauth()
    {
        $data = [
            'name' => $this->session->userdata('username'),
            'access_token' => $this->session->userdata('access_token'),
            'role' => User_model::ROLE_USER
        ];
        // Ensure user does not exist.
        if (!count($this->user_model->getByName($data['name']))) {
            // The first user is a bureaucrat. Subsequent users default to user.
            if ($this->db->count_all('user') == 0)
                $data['role'] = User_model::ROLE_BUREAUCRAT;

            // Add user to database.
            if ($this->user_model->create($data) !== false) {
                // Log the user into the session.
                $this->establishSession($data['name'], $data['role'], config_item('language'));
                $this->user_model->putUsernameInCookie($data['name']);

                // Show the user page.
                $this->index($data['name']);
            }
        } else {
            show_error(tr('user_error_register'), 500, tr('user_error_login_heading'));
        }
    }

    /**
     * Show the User page.
     *
     * @param string $name The username, taken from GET or session if not supplied
     * @param int $offset A pagination offset into this user's list of files.
     */
    public function index($name = null, $offset = 0)
    {
        // Get current user name if not provided.
        if (!$name) $name = $this->input->post_get('name');
        if (!$name) $name = $this->session->userdata('username');
        $user = $this->user_model->getByName($name);
        if ($user) {
            if (!$user['comment'])
                $user['comment'] = '<em>'.tr('user_view_nocomment').'</em>';
            $user['role'] = $this->user_model->getRoleByKey($user['role']);
            $this->load->helper('date');
            //TODO Convert time from UTC to user's timezone.
            //$user['updated_at'] = date('Y-m-j G:i', gmt_to_local($user['updated_at']));
            //TODO Use the intl extension to format date.
            $data = array_merge($user, [
                'session' => $this->session->userdata(),
                'heading' => tr('user_view_heading', $user),
                'footer' => tr('user_view_footer', $user),
            ]);
            // Only show the edit action if viewing self or are bureaucrat.
            $data['isEditable'] = ($name == $this->session->userdata('username')) ||
                (User_model::ROLE_BUREAUCRAT == $this->session->userdata('role'));

            // Show the user's files.
            $this->load->model('file_model');
            $this->load->helper('url');
            $result = $this->file_model->getByUserId($this->user_model->getUserId($name));

            // Files table pagination.
            $this->load->library('pagination');
            if (count($result) > $this->pagination->per_page) {
                $offset |= $this->input->get('offset');
                $this->pagination->initialize([
                    'base_url' => site_url('user/'. $name),
                    'total_rows' => count($result)
                ]);
                $result = array_slice($result, $offset, $this->pagination->per_page);
            }

            // Files table.
            $this->load->helper('icon');
            foreach ($result as &$row) {
                $src = base_url(iconForMimeType($row['mime_type']));
                $row['mime_type'] = '<img src="'.$src.'" width="20" height="20" title="'.$row['mime_type'].'">';
                $row['title'] = anchor('file/' . $row['id'], htmlspecialchars($row['title']));
                unset($row['id']);
            }
            $data['files'] = $result;
            $this->load->library('table');
            $this->table->set_heading('', tr('file_title'), tr('file_author'), tr('file_updated_at'));
            $this->table->set_caption(tr('user_files_caption'));

            // Build the page.
            $this->load->view('templates/header', $data);
            $this->load->view('user/view', $data);
            $this->load->view('templates/footer', $data);
        } else {
            show_404(uri_string());
        }
    }

    /**
     * Show the form to edit a user record and process the newly submitted data.
     *
     * @param string $name The username, taken from session if ommitted
     */
    public function edit($name = null)
    {
        // Get current user name if not provided.
        if (!$name)
            $name = $this->session->userdata('username');

        // Check permission.
        if (($name != $this->session->userdata('username')) &&
            (User_model::ROLE_BUREAUCRAT != $this->session->userdata('role'))) {
            show_error(tr('user_error_permission'), 403, tr('user_error_heading'));
            return;
        }

        // Check if initially loading from existing data.
        if ('GET' == $this->input->method(true)) {
            $user = $this->user_model->getByName($name);
            if ($user) {
                $this->data = array_merge($this->data, $user);
            } else {
                show_404(uri_string());
            }
        } elseif ('POST' == $this->input->method(true)) {
            // Validate data.
            $this->load->library('form_validation');
            $this->form_validation->set_rules(
                'role', 'lang:user_role', 'xss_clean');
            $this->form_validation->set_rules(
                'language', 'lang:user_language', 'required|xss_clean');
            $this->form_validation->set_rules(
                'comment', 'lang:user_comment', 'trim|max_length[1000]|xss_clean|prep_for_form|encode_php_tags');

            if ($this->form_validation->run()) {
                // Update database.
                $data = [
                    'language' => $this->input->post('language'),
                    'comment' => $this->input->post('comment')
                ];
                // Only the bureaucrat can change the role.
                if ($this->session->userdata('role') == User_model::ROLE_BUREAUCRAT)
                    $data['role'] = $this->input->post('role');

                if ($this->user_model->update($name, $data)) {
                    // If successful, goto view page.
                    $this->session->set_userdata('role', $this->input->post('role'));
                    $this->session->set_userdata('language', $data['language']);
                    $this->load->language('ui', $data['language']);
                    $this->index($name);
                } else {
                    show_error(tr('user_error_update'), 500, tr('user_error_heading'));
                }
                return;
            }
        }
        // Display form.
        $this->load->helper('form');
        // Build arrays for dropdowns.
        $this->data['roles'] = $this->user_model->getRoles();
        $this->data['languages'] = $this->user_model->getLanguages();
        // Only a bureaucrat can edit the role.
        $this->data['roleAttributes'] = null;
        if ($this->session->userdata('role') != User_model::ROLE_BUREAUCRAT)
            $this->data['roleAttributes'] = 'disabled';
        $user['role'] = $this->user_model->getRoleByKey($user['role']);
        $this->data['heading'] = tr('user_view_heading', $user);
        $this->load->view('templates/header', $this->data);
        $this->load->view('user/edit', $this->data);
        $this->load->view('templates/footer', $this->data);
    }

    /**
     * Show a list of users, optionally filtered by role.
     *
     * @param int $role Optional user role constant, not filtered by role if not supplied
     */
    public function grid($role = null)
    {
        $result = $this->user_model->getByRole($role);
        // Post-process the data.
        $this->load->helper('url');
        foreach ($result as &$row) {
            $row['name'] = anchor('user/' . $row['name'], htmlspecialchars($row['name']));
            $row['role'] = $this->user_model->getRoleByKey($row['role']);
        }
        $this->data['users'] = $result;

        switch ($role) {
            case User_model::ROLE_BUREAUCRAT:
                $this->data['heading'] = tr('tools_bureaucrats');
                break;
            case User_model::ROLE_ADMIN:
                $this->data['heading'] = tr('tools_administrators');
                break;
            case null:
                $this->data['heading'] = tr('tools_list_users');
                break;
            case 0:
                $this->data['heading'] = tr('tools_list_guests');
                break;
        }
        $this->load->library('table');
        $this->table->set_heading('Name', 'Role', 'Updated');
        $this->load->view('templates/header', $this->data);
        $this->load->view('user/grid', $this->data);
        $this->load->view('templates/footer', $this->data);
    }

    /**
     * Create the user app session data.
     *
     * @param string $name The username
     * @param int $role The role constant value
     * @param string $language The user interface language code
     */
    private function establishSession($name, $role, $language)
    {
        $id = $this->user_model->getUserId($name);
        $this->session->set_userdata([
            'userid' => $id,
            'username' => $name,
            'role' => $role,
            'language' => $language
        ]);
        $this->data['session'] = $this->session->userdata();
    }
}
