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

class Main extends CI_Controller
{
    private $data = array();

    /** Construct a Main CodeIgniter Controller */
    public function __construct()
    {
        parent::__construct();
        $this->data['session'] = $this->session->userdata();
        $this->data['lang'] = $this->lang->language;
    }

    /**
     * Show a page from a CodeIgniter view with the standard site-template's
     * header and footer.
     *
     * @param string $page The optional name of a CodeIgniter view. If ommitted,
     * then the "index" view is shown.
     */
    public function index($page = 'index')
    {
        $this->load->view('templates/header', $this->data);
        $this->load->view('main/' . $page, $this->data);
        $this->load->view('templates/footer', $this->data);
    }
}
