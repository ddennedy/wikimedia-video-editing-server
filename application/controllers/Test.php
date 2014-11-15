<?php
/*
 * Wikimedia Video Editing Server
 * Copyright (C) 2014 Dan Dennedy <dan@dennedy.org>
 * Copyright (C) 2014 C.D.C. Leuphana University Lueneburg
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

defined('BASEPATH') OR exit('No direct script access allowed');

class Test extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('user_model');
    }

    public function index()
    {
        $this->load->library('unit_test');
        $this->unit->set_test_items(['test_name', 'result']);
        $template = "{rows}{item}: {result}\n{/rows}";
        $this->unit->set_template($template);

        $this->unit->run(1 + 1, 2, 'test of unit_test library');

        $settings = [
            'servers'               => ['mysql:11300'],
            'select'                => 'random peek',
            'connection_timeout'    => 0.5,
            'peek_usleep'           => 2500,
            'connection_retries'    => 3,
            'auto_unyaml'           => true
        ];

        // Test beanstalkd connection and library.
        $this->load->library('Beanstalk', ['host' => 'mysql']);
        $isConnected = $this->beanstalk->connect();
        $this->unit->run($isConnected, true, 'beanstalk connection');

        if ($isConnected) {
            $tube = 'flux';
            $this->beanstalk->useTube($tube);
            $jobId = $this->beanstalk->put(
                23, // Give the job a priority of 23.
                0,  // Do not wait to put job into the ready queue.
                60, // Give the job 1 minute to run.
                'capacitor' // The job's body.
            );
            $this->unit->run($jobId, 'is_int', 'valid beanstalk job ID');

            $this->beanstalk->watch($tube);
            $job = $this->beanstalk->reserve(); // Block until job is available.
            $this->unit->run($job['body'], 'capacitor', 'received valid beanstalk job');
            $this->beanstalk->delete($job['id']);
            $this->beanstalk->disconnect();
        }


        // Test database and models.
        $row = $this->user_model->getByName('admin');
        $this->unit->run($row['role'], 3, 'admin user in mysql');

        echo $this->unit->report();

    }
}
