<?php

use \Process\MultiThreadDeamon;

/**
 *
 * @author walkerror
 */
class DeamonExmpl extends MultiThreadDeamon 
{

    protected $_template_timeout_queue = array();
    protected $port = 6795; // port for SOCKET

    public function getName() 
    {
        return 'exmpl_deamon';
    }

    public function initChild() 
    {
        $template_timeout_queue = new \SplQueue();
        $template_timeout_queue->enqueue(0);
        $template_timeout_queue->enqueue(2);
        $template_timeout_queue->enqueue(5);
        $template_timeout_queue->enqueue(10);
        $this->_template_timeout_queue = $template_timeout_queue;
    }

    public function doWork() 
    {
        print "\In ".__METHOD__;
        sleep(2);
    }

}
