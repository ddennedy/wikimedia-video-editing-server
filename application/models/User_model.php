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

class User_model extends CI_Model
{
    /** @var Role constant - if either registration is required or if registered user was demoted. */
    const ROLE_GUEST      = 0;
    /** @var Role constant for a user who can create and update data */
    const ROLE_USER       = 1;
    /** @var Role constant for a user who can edit data as well as delete data and demote user to guest */
    const ROLE_ADMIN      = 2;
    /** @var Role constant for a user who can do everything including designating admins and bureaucrats */
    const ROLE_BUREAUCRAT = 3;

    /** @var A constant for the name of an encrypted HTTP cookie that stores a username */
    const COOKIE_NAME     = 'user';

    /** Construct a User CodeIgniter Model. */
    public function __construct()
    {
        $this->load->database();
        log_message('debug', 'User Model Class Initialized');
    }

    /**
     * Get a user record by username.
     *
     * @param string $name The username
     * @return array
     */
    public function getByName($name = false)
    {
        if ($name) {
            $query = $this->db->get_where('user', ['name' => $name]);
            $data = $query->row_array();
            if ($data['access_token']) {
                $this->load->library('encryption');
                $data['access_token'] = $this->encryption->decrypt($data['access_token']);
            }
            return $data;
        }
        return array();
    }

    /**
     * Get a user record by user ID.
     *
     * @param string $id The user ID
     * @return array|false
     */
    public function getByID($id)
    {
        $query = $this->db->get_where('user', ['id' => $id]);
        $data = $query->row_array();
        if ($data && $data['access_token']) {
            $this->load->library('encryption');
            $data['access_token'] = $this->encryption->decrypt($data['access_token']);
            return $data;
        } else {
            return false;
        }
    }

    /**
     * Get a User record ID.
     *
     * @param string $name The username
     * @return int User record ID
     */
    public function getUserId($name)
    {
        $this->db->select('id');
        $query = $this->db->get_where('user', ['name' => $name]);
        return $query->row()->id;
    }

    /**
     * Authenticate a user by password.
     *
     * Included only for legacy, non-OAuth login support.
     *
     * @param string $name The username
     * @param string $password The password (encrypted if stored that way)
     * @return int 0 if failed or >0 if success
     */
    public function login($name, $password)
    {
        $query = $this->db->get_where('user', ['name' => $name, 'password' => $password]);
        return $query->num_rows();
    }

    /**
     * Save an OAuth access token for the user.
     *
     * @param string $name The username
     * @param string $token The OAuth access token
     */
    public function setAccessTokenByName($name, $token)
    {
        $this->load->library('encryption');
        $x = $this->encryption->encrypt($token);
        $this->db->where('name', $name);
        $this->db->update('user', ['access_token' => $x]);
    }

    /**
     * Create a new user record.
     *
     * @param array $data An associative array containing name/value pairs
     * @return int|null The newly created user record ID or false on error
     */
    public function create($data)
    {
        $this->load->library('encryption');
        $data['access_token'] = $this->encryption->encrypt($data['access_token']);
        $this->db->insert('user', $data);
        if ($this->db->affected_rows())
            return $this->db->insert_id();
        else
            return false;
    }

    /**
     * Send an encrypted HTTP cookie containing the username.
     *
     * @param string $name The username
     */
    public function putUsernameInCookie($name)
    {
        $this->load->library('encryption');
        $x = $this->encryption->encrypt($name);
        $expire = $this->config->item('cookie_expire_seconds');
        $this->input->set_cookie(User_model::COOKIE_NAME, $x, $expire);
    }

    /**
     * Get the username from an encrypted HTTP cookie.
     *
     * @return string The username
     */
    public function getUsernameFromCookie()
    {
        $x = $this->input->cookie(User_model::COOKIE_NAME);
        if ($x) {
            $this->load->library('encryption');
            return $this->encryption->decrypt($x);
        } else {
            return false;
        }
    }

    /**
     * Update a user record with new/modified data.
     *
     * @param string $name The username
     * @param array $data The user record data as associative array
     * @return bool False on error
     */
    public function update($name, $data)
    {
        $this->db->where('name', $name);
        return $this->db->update('user', $data);
    }

    /**
     * Get user names, optionally filtered by role.
     *
     * @param int $role An optional role constant
     * @return array
     */
    public function getByRole($role = null)
    {
        if ($role !== null)
            $this->db->where('role', $role);
        $this->db->select('name, role, updated_at');
        $query = $this->db->get('user');
        return $query->result_array();
    }

    /**
     * Get a list of languages available for the user interace.
     *
     * @return array
     */
    public function getLanguages()
    {
        return [
            'en' => tr('language_en'),
            'de' => tr('language_de'),
        ];
    }

    /**
     * Get the descriptive name for a language given its 2-digit ISO code.
     *
     * @param string $languageKey The 2-digit ISO code for the language
     * @return string
     */
    public function getLanguageByKey($languageKey)
    {
        $languages = $this->getLanguages();
        if (array_key_exists($languageKey, $languages))
            return $languages[$languageKey];
        else
            return $languages['en'];
    }

    /**
     * Get a list of all possible roles.
     *
     * @return array
     */
    public function getRoles()
    {
        return [
            User_model::ROLE_GUEST => tr('role_guest'),
            User_model::ROLE_USER => tr('role_user'),
            User_model::ROLE_ADMIN => tr('role_admin'),
            User_model::ROLE_BUREAUCRAT => tr('role_bureaucrat'),
        ];
    }

    /**
     * Get a descriptive label for a role constant value.
     *
     * @param int $roleKey A role constant from this class
     * @return string
     */
    public function getRoleByKey($roleKey)
    {
        $roles = $this->getRoles();
        if (array_key_exists($roleKey, $roles))
            return $roles[$roleKey];
        else
            return $roles[User_model::ROLE_GUEST];
    }
}
