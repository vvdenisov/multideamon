<?php

/**
 *
 * @author walkerror
 */

namespace Process;

class Signal
{
    protected $_code;
    protected $_name;
    public function getCode()
    {
        return $this->_code;
    }
    
    public function getName()
    {
        return $this->_name;
    }

    static public function getBySignalCode($signal_code)
    {
        $object = new static;
        $object->_code = $signal_code;
        $object->_name = static::getSignalName($signal_code);
        return $object;
    }

    /**
     * ¬озвращает название сигнала по коду
     * ≈сли название сигнала не определено вернет null
     * @param integer $signal_code 
     * @return string
     */
    static public function getSignalName($signal_code)
    {
        $status_name = null;
        switch ($signal_code)
        {
            case SIGHUP: $status_name = 'SIGHUP';
                break;
            case SIGINT: $status_name = 'SIGINT';
                break;
            case SIGQUIT: $status_name = 'SIGQUIT';
                break;
            case SIGILL: $status_name = 'SIGILL';
                break;
            case SIGILL: $status_name = 'SIGILL';
                break;
            case SIGTRAP: $status_name = 'SIGTRAP';
                break;
            case SIGABRT: $status_name = 'SIGABRT';
                break;
            case SIGIOT: $status_name = 'SIGIOT';
                break;
            case SIGBUS: $status_name = 'SIGBUS';
                break;
            case SIGFPE: $status_name = 'SIGFPE';
                break;
            case SIGKILL: $status_name = 'SIGKILL';
                break;
            case SIGUSR1: $status_name = 'SIGUSR1';
                break;
            case SIGSEGV: $status_name = 'SIGSEGV';
                break;
            case SIGUSR2: $status_name = 'SIGUSR2';
                break;
            case SIGPIPE: $status_name = 'SIGPIPE';
                break;
            case SIGALRM: $status_name = 'SIGALRM';
                break;
            case SIGTERM: $status_name = 'SIGTERM';
                break;
            case SIGCONT: $status_name = 'SIGCONT';
                break;
            case SIGSTOP: $status_name = 'SIGSTOP';
                break;
            case SIGTSTP: $status_name = 'SIGTSTP';
                break;
            case SIGTTIN: $status_name = 'SIGTTIN';
                break;
            case SIGTTOU: $status_name = 'SIGTTOU';
                break;
            case SIGURG: $status_name = 'SIGURG';
                break;
            case SIGXCPU: $status_name = 'SIGXCPU';
                break;
            case SIGXFSZ: $status_name = 'SIGXFSZ';
                break;
            case SIGVTALRM: $status_name = 'SIGVTALRM';
                break;
            case SIGPROF: $status_name = 'SIGPROF';
                break;
            case SIGWINCH: $status_name = 'SIGWINCH';
                break;
            case SIGPOLL: $status_name = 'SIGPOLL';
                break;
            case SIGIO: $status_name = 'SIGIO';
                break;
            case SIGPWR: $status_name = 'SIGPWR';
                break;
            case SIGSYS: $status_name = 'SIGSYS';
                break;
            case SIGBABY: $status_name = 'SIGBABY';
                break;
        }
        return $status_name;
    }
}