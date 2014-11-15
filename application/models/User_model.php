<?php
class User_model extends CI_Model {
    // If either registration is required or if registered user was demoted.
    const Role_Guest      = 0;
    // Can create and update data.
    const Role_User       = 1;
    // Can also delete data and demote user to guest.
    const Role_Admin      = 2;
    // Can do everything including designating managers and admins.
    const Role_Bureaucrat = 3;

    public function __construct()
    {
        $this->load->database();
        log_message('debug', 'User Model Class Initialized');
    }

    public function getByName($name = false)
    {
        if ($name === false) {
            $query = $this->db->get('user');
            return $query->result_array();
        }
        $query = $this->db->get_where('user', ['name' => $name]);
        return $query->row_array();
    }
}
