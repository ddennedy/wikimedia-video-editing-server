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
    /// If either registration is required or if registered user was demoted.
    const ROLE_GUEST      = 0;
    /// Can create and update data.
    const ROLE_USER       = 1;
    /// Can also delete data and demote user to guest.
    const ROLE_ADMIN      = 2;
    /// Can do everything including designating managers and admins.
    const ROLE_BUREAUCRAT = 3;

    const COOKIE_NAME     = 'user';

    public function __construct()
    {
        $this->load->database();
        log_message('debug', 'User Model Class Initialized');
    }

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

    public function login($name, $password)
    {
        $query = $this->db->get_where('user', ['name' => $name, 'password' => $password]);
        return $query->num_rows();
    }

    public function getRoleName($role)
    {
        switch ($role) {
            case User_model::ROLE_USER:
                return $this->lang->line('role_user');
            case User_model::ROLE_ADMIN:
                return $this->lang->line('role_admin');
            case User_model::ROLE_BUREAUCRAT:
                return $this->lang->line('role_bureaucrat');
            default:
                return $this->lang->line('role_guest');
        }
    }

    public function setAccessTokenByName($name, $token)
    {
        $this->load->library('encryption');
        $x = $this->encryption->encrypt($token);
        $this->db->where('name', $name);
        $this->db->update('user', ['access_token' => $x]);
    }

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

    public function putUsernameInCookie($name)
    {
        $this->load->library('encryption');
        $x = $this->encryption->encrypt($name);
        $expire = $this->config->item('cookie_expire_seconds');
        $this->input->set_cookie(User_model::COOKIE_NAME, $x, $expire);
    }

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
}
