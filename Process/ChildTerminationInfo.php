<?php

/**
 * @author Walkerror
 */

namespace Process;

class ChildTerminationInfo
{
    /**
     * @var int 
     */
    protected $_status;
    
    /**
     * @var bool 
     */
    protected $_is_exited;
    
    /**
     * @var int 
     */
    protected $_exit_code;
    
    /**
     * @var bool 
     */
    protected $_is_terminated;
    
    /**
     * @var bool 
     */
    protected $_is_stopped;
    
    /**
     * @var \Process\Signal 
     */
    protected $_stop_signal;
    
    /**
     * @var \Process\Signal 
     * @var type 
     */
    protected $_terminate_signal;
    
    
    /**
     * 
     * @param type $status
     * @return \Process\ChildTerminationInfo
     */
    public static function createByStatus($status)
    {
        $object = new static;
        $object->_is_exited = pcntl_wifexited($status);
        $object->_is_stopped = pcntl_wifstopped($status);
        $object->_is_terminated = pcntl_wifsignaled($status);
        if($object->_is_exited)
        {
            $object->_exit_code = pcntl_wexitstatus($status);
        }
        if($object->_is_terminated)
        {
            $object->_terminate_signal = Signal::getBySignalCode(pcntl_wtermsig($status));
        }
        if($object->_is_stopped)
        {
            $object->_stop_signal = Signal::getBySignalCode(pcntl_wstopsig($status));
        }
        return $object;
    }
    
    /**
     * @return integer
     */
    public function getStatus()
    {
        return $this->_status;
    }
    
    /**
     * @return bool
     */
    public function isExited()
    {
        return $this->_is_exited;
    }
    
    /**
     * @return integer
     */
    public function getExitCode()
    {
        return $this->_exit_code;
    }
    
    /**
     * @return bool
     */
    public function isTerminated()
    {
        return $this->_is_terminated;
    }
    
    /**
     * @return \Process\Signal
     */
    public function getTerminateSignal()
    {
        return $this->_terminate_signal;
    }
    
    /**
     * @return bool
     */
    public function isStopped()
    {
        return $this->_is_stopped;
    }
    
    /**
     * @return \Process\Signal
     */
    public function getStopSignal()
    {
        return $this->_stop_signal;
    }
    
}