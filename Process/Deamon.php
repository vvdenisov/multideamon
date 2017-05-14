<?php

/**
 * Deamonize
 *
 * @author walkerror
 */

namespace Process;

class Deamon
{
    const EXIT_SUCCESS = 0;
    const EXIT_FAILED = 1;
    const EXIT_DEMONIZE_FAILED = 2;
    
    /**
     * System is unusable (will throw a System_Daemon_Exception as well)
     */
    const LOG_EMERG = 0;

    /**
     * Immediate action required (will throw a System_Daemon_Exception as well)
     */
    const LOG_ALERT = 1;

    /**
     * Critical conditions (will throw a System_Daemon_Exception as well)
     */
    const LOG_CRIT = 2;

    /**
     * Error conditions
     */
    const LOG_ERR = 3;

    /**
     * Warning conditions
     */
    const LOG_WARNING = 4;

    /**
     * Normal but significant
     */
    const LOG_NOTICE = 5;

    /**
     * Informational
     */
    const LOG_INFO = 6;

    /**
     * Debug-level messages
     */
    const LOG_DEBUG = 7;
    
    protected $log_path = '/dev/null';
    protected $log_level;
    protected $log_desctiptor;
    protected $lock_dir = '/var/run';
    protected $autorun_dir = '/usr/local/etc/rc.d';
    protected $name;
    
    // If TRUE, deamon - stop
    protected $stop_server = false;

    public function __construct()
    {
        $this->log_level = static::LOG_WARNING;
        if(!extension_loaded('posix'))
        {
            throw new Exception('Для работы необходимо расширение posix');
        }
        if(!extension_loaded('pcntl'))
        {
            throw new Exception('Для работы необходимо расширение pcntl');
        }
        if(!extension_loaded('sockets'))
        {
            throw new Exception('Для работы необходимо расширение sockets');
        }
    }

    /**
     * start/stop/reload deamon
     * @global type $argc
     * @global type $argv
     * @param type $local_argc
     * @param type $local_argv
     */
    public function handleCmd($local_argc = null, $local_argv = null)
    {
        global $argc,$argv;
        if($local_argc === null)
        {
            $local_argc = $argc;
        }
        if($local_argv === null)
        {
            $local_argv = $argv;
        }
        if($local_argc != 2)
        {
            echo "Usage: {$this->getName()} [start|stop|reload|status|install|uninstall]\n";
            exit(self::EXIT_FAILED);
        }
        switch($local_argv[1])
        {
            case 'install':
                $os = Os\OsAbstract::factory($this);
                $os->install(null);
                break;
            case 'uninstall':
                $os = Os\OsAbstract::factory($this);
                $os->uninstall();
                break;
            case 'start':
                if($this->isRunning())
                {
                    echo "{$this->getName()} is already running\n";
                    exit(self::EXIT_FAILED);
                }
                $this->start();
                break;
            case 'status':
                if(!$this->isRunning())
                {
                    echo "{$this->getName()} is not running\n";
                }
                else
                {
                    echo "{$this->getName()} is running, pid: {$this->getPidFromLockFile()}\n";
                }
                break;
            case 'stop':
                if(!$this->isRunning())
                {
                    echo "{$this->getName()} is not running\n";
                    exit(self::EXIT_FAILED);
                }
                $this->stop();
                break;
            case 'reload':
                if(!$this->isRunning())
                {
                    echo "{$this->getName()} is not running\n";
                    exit(self::EXIT_FAILED);
                }
                $this->reload();
                break;
            default:
            echo "Usage: {$this->getName()} [start|stop|reload|status|install|uninstall]\n";
            exit(self::EXIT_FAILED);  
        }
    }

    public function doWork()
    {
        $this->info('do nothing');
        usleep(1000 * 1000 * 10); 
    }
    
    public function setName($name)
    {
        $this->name = $name;
    }
    
    public function getName()
    {
        if($this->name === null)
        {
            $this->name = strtolower(__CLASS__);
        }
        return $this->name;
    }
    
    public function isRunning()
    {
        $pid = $this->getPidFromLockFile();
        if($pid === null)
        {
            return false;
        }
        return $this->isAlive($pid);
    }
    
    public function isAlive($pid)
    {
        return posix_kill($pid, 0);
    }
    
    public function getPidFromLockFile()
    {
        $pid = null;
        $lock_file_path = $this->getLockFilePath();
        if(file_exists($lock_file_path))
        {
            $pid = file_get_contents($lock_file_path);
        } 
        return $pid;
    }
    
    public function setPidToLockFile($pid)
    {
        $lock_file_path = $this->getLockFilePath();
        return file_put_contents($lock_file_path, $pid);
    }
    
    public function getLockFilePath()
    {
        return $this->lock_dir . DIRECTORY_SEPARATOR . $this->getName().'.pid';
    }

    /**
     * start app
     */
    public function start()
    {
        $this->demonize();
        $this->setPidToLockFile(posix_getpid());
        $this->_start(); 
    }
    
    /**
     * stop app
     */
    public function stop()
    {
        $pid = $this->getPidFromLockFile();
        posix_kill($pid, SIGTERM);
        while(posix_kill($pid, 0))
        {
            sleep(1);
        }
        unlink($this->getLockFilePath());
    }
    
    public function reload()
    {
        posix_kill($this->getPidFromLockFile(), SIGHUP);
    }
    
    /**
     * start deamon
     * 
     * @return int код выхода
     */
    protected function _start()
    {
        $this->info( 'Daemon run with PID ' .posix_getpid() );
        while( ! $this->stop_server)
        {
            $this->doWork();
            pcntl_signal_dispatch();
        }
        $this->info('Deamon stopped');
        $this->quit();
        return static::EXIT_SUCCESS;
    }

    /**
     * signal handler
     */
    public function parentSignalHandler( $signo )
    {
        switch ($signo)
        {
            case SIGHUP:
                // перечитать конфиги, переоткрыть файлы логов и т.д.
                $this->_reload();
                break;
            case SIGTERM:
                // При получении сигнала завершения работы устанавливаем флаг
                $this->stop_server = true;
                break;
        }
    }

    public function quit()
    {
        $this->info('Deamon quit');
        exit(static::EXIT_SUCCESS);
    }
 
    /**
     * reopen log
     */
    protected function _reload()
    {
        $this->info('Reload deamon');
        $this->_close_log();
        $this->_open_log();
        $this->info('Deamon reloaded');
    }

    /**
     * open log
     */
    protected function _open_log()
    {
        if($this->log_desctiptor === null)
        {
            $this->log_desctiptor = fopen($this->log_path, 'a');
        }
    }
    
    /**
     * close log
     */
    protected function _close_log()
    {
        if($this->log_desctiptor !== null)
        {
            fclose($this->log_desctiptor);
            $this->log_desctiptor = null;
        }
    }
    
    /**
     * 
     * @param int $level
     * @throws Exception
     */
    public function setLogLevel($level)
    {
        $levels = array(
            static::LOG_ALERT,
            static::LOG_CRIT,
            static::LOG_DEBUG,
            static::LOG_EMERG,
            static::LOG_ERR,
            static::LOG_INFO,
            static::LOG_NOTICE,
            static::LOG_WARNING,
        );
        if( ! in_array($level, $levels) )
        {
            throw new Exception("Некоррентный уровнель логирования");
        }
         
        $this->log_level = $level;
    }
    
    /**
     * 
     * @param string $path
     */
    public function setLogPath($path)
    {
        $this->log_path = $path;
    }
    
    /**
     * 
     * @param type $errno
     * @param type $errstr
     * @param type $errfile
     * @param type $errline
     * @param type $errcontext
     */
    public function phpErrorHandler($errno , $errstr , $errfile = null, $errline = null, $errcontext = null)
    {
        // for simbol @ in error
        if(error_reporting() === 0)
        {
            return;
        }
        $this->error('level: '.error_reporting().' number: '.$errno.' message: '.$errstr.' file: '.$errfile.' line: '.$errline);
    }
    
    /**
     * @param type $buffer
     * @param type $phase
     */
    public function phpOutputHandler($buffer, $phase)
    {
        if($buffer)
        {
            $this->info('catch output: '.$buffer);
        }
    }
    
    public function phpShutdownHandler()
    {
        $errfile = "unknown file";
        $errstr = "shutdown";
        $errno = null;
        $errline = 0; 
        $error = error_get_last();
        if ($error !== NULL)
        {
            $errno = $error["type"];
            $errfile = $error["file"];
            $errline = $error["line"];
            $errstr = $error["message"];
        }
        if($errno)
        {
            $this->error("Shutdown error: code: {$errno} message: {$errstr} file: {$errfile} line: {$errline}");
        }
    }
    
    protected function _log($level, $msg)
    {
        if($this->log_desctiptor !== null)
        {
            $levels = array(
                static::LOG_ALERT => 'alert',
                static::LOG_CRIT => 'crit',
                static::LOG_DEBUG => 'debug',
                static::LOG_EMERG => 'emerg',
                static::LOG_ERR => 'error',
                static::LOG_INFO => 'info',
                static::LOG_NOTICE => 'notice',
                static::LOG_WARNING => 'warning',
            );
            if( ! isset($levels[$level]) )
            {
                throw new Exception("Uncorrect log level");
            }
            if($this->log_level >= $level)
            {
                $log_msg = date('Y-m-d H:i:s') . ' '.  posix_getpid().' '.$levels[$level].': '.$msg."\n";
                flock($this->log_desctiptor, LOCK_EX);
                fwrite($this->log_desctiptor, $log_msg);
                flock($this->log_desctiptor, LOCK_UN );
            }
        }
    }
 
    public function emerg($msg)
    {
        $this->_log(static::LOG_EMERG, $msg);
    }
    
    public function alert($msg)
    {
        $this->_log(static::LOG_ALERT, $msg);
    }
    
    public function crit($msg)
    {
        $this->_log(static::LOG_CRIT, $msg);  
    }
    
    public function warning($msg)
    {
        $this->_log(static::LOG_WARNING, $msg);  
    }
    
    public function error($msg)
    {
        $this->_log(static::LOG_ERR, $msg); 
    }
    
    public function notice($msg)
    {
        $this->_log(static::LOG_NOTICE, $msg);
    }
    
    public function info($msg)
    {
        $this->_log(static::LOG_INFO, $msg);
    }
    
    public function debug($msg)
    {
        $this->_log(static::LOG_DEBUG, $msg);
    }

    /**
     * Process to deamon
     */
    public function demonize()
    {
        $this->_open_log();
        ini_set('display_errors', 0);
        $this->info("Start demonize");
        // 1. fork, for unlunch terminal
        $pid = pcntl_fork();
        if($pid<0)
        {
            crit( "First fork failed");
            exit(self::EXIT_DEMONIZE_FAILED);
        }
        elseif($pid>0)
        {
            // stop parent process
            exit(self::EXIT_SUCCESS);
        }
        // 2. start new session
        if(posix_setsid() < 0)
        {
            $this->crit("setsid failed");
            exit(self::EXIT_DEMONIZE_FAILED);
        }
        // 3. fork. rewrite session leader-om
        $pid = pcntl_fork();
        if($pid<0)
        {
            $this->crit( "Second fork failed");
            exit(self::EXIT_FAILED_FORK);
        }
        elseif($pid>0)
        {
            // stop parent process
            exit(self::EXIT_SUCCESS);
        }
        // 4. Reset current work dir to /
        if( ! chdir('/'))
        {
            $this->warning("chdir failed");
        }
        // 5. Any permission for created files
        umask(0);
        // set signal handler
        if( ! pcntl_signal(SIGTERM, array($this, "parentSignalHandler")))
        {
            $this->warning("Failed install signal handler for sinal SIGTERM");
        }
        if( ! pcntl_signal(SIGHUP, array($this, "parentSignalHandler")))
        {
            $this->warning("Failed install signal handler for sinal SIGHUP");
        }
        set_error_handler(array($this, 'phpErrorHandler'));
        register_shutdown_function(array($this, 'phpShutdownHandler'));
        ob_start(array($this, 'phpOutputHandler'),2);
    }
}