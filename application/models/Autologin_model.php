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

/**
 * The purpose of this model is to automatically login the user if they have
 * the appropriate cookie on their machine without the user having to explicitly
 * click a Login link.
 * In order for this to work, you must auto-load this model within CodeIgniter:
 * application/config/autoload.php, $autoload['model'].
 */

class Autologin_model extends CI_Model
{
    public function __construct()
    {
        log_message('debug', 'Autologin Model Class Initialized');

        // If not yet logged in.
        if ($this->session->userdata('username') && $this->session->userdata('role')) {
            // Look for the cookie.
            $this->load->model('user_model');
            $username = $this->user_model->getUsernameFromCookie();

            if ($username) {
                log_message('debug', 'Autologin username: ' . $username);
                // Lookup user in database.
                $this->load->model('user_model');
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
                        $this->user_model->putUsernameInCookie($identity->username);
                    } else {
                        log_message('debug', 'Autologin: OAuth access token is NOT valid.');
                    }
                } else {
                    log_message('debug', 'Autologin: user is NOT registered.');
                }
            }
        }
    }
}
