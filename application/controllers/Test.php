<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Test extends CI_Controller {
    public function index() {
        $this->load->library('unit_test');
        $this->unit->set_test_items(array('test_name', 'result'));
        $template = "{rows}{item}: {result}\n{/rows}";
        $this->unit->set_template($template);

        $this->unit->run(1 + 1, 2, 'test of unit_test library');

        $settings = array(
            'servers'               => array('mysql:11300'),
            'select'                => 'random peek',
            'connection_timeout'    => 0.5,
            'peek_usleep'           => 2500,
            'connection_retries'    => 3,
            'auto_unyaml'           => true
        );

        // Test beanstalkd connection and library.
        $this->load->library('Beanstalk', array('host' => 'mysql'));
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

        echo $this->unit->report();

    }
}
