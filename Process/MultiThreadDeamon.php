<?php

/**
 * Класс для демонизации (многопоточный через fork)
 *
 * @author walkerror
 */

namespace Process;


class MultiThreadDeamon extends Deamon
{
    /**
     * @var type 
     */
    protected $is_respawn = true; // пересоздавать отвалившиеся дочерние процессы
    protected $spawn_timeout = 100000; // мисросекунды
    
    /**
     * PID'ы запущенных в текущий момент дочерних процессов
     * @var array 
     */
    protected $current_children = array();
    
    /**
     * текущий процесс дочерний или главный процесс
     * @var bool 
     */
    protected $is_child = false;
    protected $config_dir = '/usr/local/etc';
    protected $status;
    protected $socket;
    
    /**
     * Максимальное количество одновременно запущенных дочерних процессов
     * @var type 
     */
    protected $max_child_count = 1;
    protected $web_enable = true;
    protected $interface = '0.0.0.0';
    protected $port = 6789;
    protected $backlog = 10;
    
    /**
     * Устанавливает максимальное количество дочерних процессов
     * @param int $count
     */
    public function setMaxChildCount($count)
    {
        $this->max_child_count = (int) $count;
    }
    
    /**
     * Возвращает максимальное количество дочерних процессов
     * 
     * @return int
     */
    public function getMaxChildCount()
    {
        return $this->max_child_count;
    }
    
    public function loadConfig()
    {
        $path = $this->config_dir . DIRECTORY_SEPARATOR . $this->getName();
        if(file_exists($path))
        {
            $config = parse_ini_file($path);
            $avialable_values = array(
                'web_enable',
                'interface',
                'port',
                'max_child_count',
            );
            foreach ($avialable_values as $value)
            {
                if(array_key_exists($value, $config))
                {
                    $this->$value = $config[$value];
                }
            }
        }
    }

    function tail($file, $num_to_get = 10)
    {
        $fp = fopen($file, 'r');
        clearstatcache();
        $position = filesize($file);
        fseek($fp, $position - 1);
        $chunklen = 4096;
        $data = null;
        while ($position >= 0)
        {
            $position = $position - $chunklen;
            if ($position < 0)
            {
                $chunklen = abs($position);
                $position = 0;
            }
            fseek($fp, $position);
            $data = fread($fp, $chunklen) . $data;
            if (substr_count($data, "\n") >= $num_to_get + 1)
            {
                preg_match("!(.*?\n){" . ($num_to_get - 1) . "}$!", $data, $match);
                return $match[0];
            }
        }
        fclose($fp);
        return $data;
    }
     
    public function _start()
    {
        $this->info('Parent deamon run with PID '.  posix_getpid());
        $this->loadConfig();
        $remain_child = $this->max_child_count;
        $this->_initWebSocket();
        while( ! $this->stop_server)
        {
            // Если уже запущено максимальное количество дочерних процессов, ждем их завершения
            while ( ! $this->stop_server && (count($this->current_children) == $this->max_child_count || !$remain_child) )
            {
                if((@$client = socket_accept($this->socket)) !== false)  
                {
                    do
                    {
                        $request = socket_read($client, 1024*4);
                    }
                    while($request === false && socket_last_error($client)==35);
                    $response = $this->handleHttp($request, $client);
                    socket_write($client, $response);
                }
                else
                {
                    if(socket_last_error($this->socket) && socket_last_error($this->socket)!=35)
                    {
                        $this->warning(socket_last_error($this->socket).' '.socket_strerror(socket_last_error($this->socket)));
                    }
                }
                pcntl_signal_dispatch();
                usleep(1000 * 10);
            }
            if(! $this->stop_server && $remain_child)
            {
                $this->debug('remain_child '.$remain_child);
                $this->launchChild();
                if( !$this->is_respawn )
                {
                    $remain_child--;
                }
                usleep($this->spawn_timeout);
            }
            if(count($this->current_children) > $this->max_child_count )
            {
                $this->debug('Too many children need kill '.count($this->current_children).' > '.$this->max_child_count);
                $kill_count = count($this->current_children) - $this->max_child_count;
                $this->debug('Need kill '.$kill_count);
                foreach ($this->current_children as $pid=>$value)
                {
                    if($kill_count-- < 1)
                    {
                        break;
                    }
                    $this->debug('Decrease children send SIGTERM to child '.$pid);
                    posix_kill($pid, SIGTERM);
                    unset($this->current_children[$pid]);
                }
            }
        }
        $this->_closeWebSocket();
        $this->info('Parent deamon stopped');
        $this->quit();
        return static::EXIT_SUCCESS;
    }

    protected function _initWebSocket()
    {
        $interface = $this->interface;
        $port = $this->port;
        $backlog = $this->backlog;
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, $interface,$port) or die('socket_bind');
        socket_listen($this->socket,$backlog) or die('socket_listen');
        socket_set_nonblock($this->socket) or die('socket_set_nonblock');
    }
    
    protected function _closeWebSocket()
    {
        socket_close($this->socket);
    }

    public function http_parse_headers($raw_headers)
    {
        $headers = array();
        $key = ''; 
        foreach(explode("\n", $raw_headers) as $i => $h)
        {
            $h = explode(':', $h, 2);
            if (isset($h[1]))
            {
                if (!isset($headers[$h[0]]))
                    $headers[$h[0]] = trim($h[1]);
                elseif (is_array($headers[$h[0]]))
                {
                    $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1]))); 
                }
                else
                {
                    $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1]))); 
                }
                $key = $h[0]; 
            }
            else
            {
                if (substr($h[0], 0, 1) == "\t") 
                    $headers[$key] .= "\r\n\t".trim($h[0]); 
                elseif (!$key) 
                    $headers[0] = trim($h[0]);trim($h[0]); 
            }
        }
        return $headers;
    }
    
    public function handleHttp($request, $socket)
    {
        $msg =   "HTTP/1.1 200 OK\r\n"
                ."Connection: Close\r\n"
                ."Content-Type: application/json; charset=utf-8\r\n\r\n";

        $logs = array();
        $raw_logs = $this->tail($this->log_path,10);
        foreach(explode("\n", $raw_logs) as $raw_log)
        {
            if(!$raw_log)
            {
                continue;
            }
            $log = array();
            $matches = array();
            preg_match('/(\d+-\d+-\d+ \d+:\d+:\d+)\s*(\d+)\s*([a-z]+)\s*:\s*(.*)/i', $raw_log, $matches);
            $log['time']=$matches[1];
            $log['pid']=$matches[2];
            $log['level']=$matches[3];
            $log['msg']=iconv('cp1251','utf-8',$matches[4]);
            $logs[] = $log;
        }
        $body = array(
            'status' => 'running',
            'children' => count($this->current_children),
            'logs' => $logs ,
        );
       if ($request !== false)
        {
            ob_start();
            print_r($this->http_parse_headers($request));
            $msg = ob_get_clean();
        }
        else
        {
            $msg = socket_last_error($socket).' '.socket_strerror(socket_last_error($socket));
        }
        return $msg;
    }
    
    public function initChild()
    {
        
    }

    protected function launchChild()
    {
        /*
         * Создаем дочерний процесс
         * весь код после pcntl_fork() будет выполняться
         * двумя процессами: родительским и дочерним
         * */
        $pid = pcntl_fork();
        if ( $pid )
        {
            /*Этот код выполнится родительским процессом*/
            $this->current_children[$pid] = true;
        }
        elseif ( $pid == -1 )
        {
            /*Не удалось создать дочерний процесс*/
            error_log( 'Could not launch new job, exiting' );
            return false;
        }
        else
        {
            /*А этот код выполнится дочерним процессом*/
            pcntl_signal( SIGCHLD, SIG_DFL );
            socket_close( $this->socket );
            $this->current_children = array();
            $this->is_child = true;
            $this->initChild();
            $this->info( 'Child daemon run with PID ' .posix_getpid() );
            while (! $this->stop_server )
            {
                $this->doWork();
                pcntl_signal_dispatch();
            }
            $this->info( 'Child daemon stopped' );
            $this->quit();
            exit( static::EXIT_SUCCESS );
        }
        return true;
    }
    
    public function parentSignalHandler($signo)
    {
        parent::parentSignalHandler($signo);
        switch ($signo)
        {
            case SIGCHLD:
                $status = null;
                $pid = null;
                // Пока есть завершенные дочерние процессы
                while ( $pid === null || $pid > 0 )
                {
                    if ($pid && isset($this->current_children[$pid]))
                    {
                        $child_info = ChildTerminationInfo::createByStatus($status);
                        $this->logChildExitInfo($child_info, $pid);
                        $this->debug("waited child {$pid}");
                        // если дочерний процесс не приостановлен
                        if( ! $child_info->isStopped())
                        {
                            $this->debug("child {$pid} has exited or killed");
                            // Удаляем дочерние процессы из списка
                            unset($this->current_children[$pid]);
                        }
                    }
                    $pid = pcntl_waitpid(-1, $status, WNOHANG | WUNTRACED);
                }
                break;
        }
    }

    /**
     * Возвращает информацию о причине завершения дочернего просесса
     * @param type $status
     * @param type $pid
     */
    public function logChildExitInfo(ChildTerminationInfo $child_info, $pid)
    {
        // если дочерние меняют состояние, но главный процесс не остановлен
        if(!$this->stop_server)
        {
            $msg = "Child with pid: {$pid} unexpectedly change state - ";
            if($child_info->isExited())
            {
                $msg .= ' exit with status code '.$child_info->getExitCode();
            }

            if($child_info->isTerminated())
            {
                $msg .= ' kill with signal ' .$child_info->getTerminateSignal()->getName();
            }
            if($child_info->isStopped())
            {
                $msg .= ' stop with signal '.$child_info->getStopSignal()->getName();
            }
            $this->warning($msg);
        }
    }
    
    public function _reload()
    {
        if ( $this->is_child )
        {
            $this->info( 'Child daemon reload' );
            $this->_close_log();
            $this->loadConfig();
            $this->_open_log();
        }
        else
        {
            $this->info('Parent daemon reload');
            $this->_close_log();
            $this->_closeWebSocket();
            $this->loadConfig();
            $this->_initWebSocket();
            $this->_open_log();
            foreach ($this->current_children as $pid=>$value)
            {
                posix_kill($pid, SIGHUP); 
            }
        }
    }
    
    public function quit()
    {
        if($this->is_child)
        {
            $this->info('Child deamon quit');
        }
        else
        {
            // отсылаем всем дочерним процессам сигнал завешения процесса
            foreach ($this->current_children as $pid=>$value)
            {
                $this->debug('Parent send SIGTERM to child '.$pid);
                posix_kill($pid, SIGTERM); 
            }
            $this->debug('wait children quit');
            // пока не завершены все дочерние процессы
            while($this->current_children)
            { 
                pcntl_signal_dispatch();
                $this->debug('remain child count: '.count($this->current_children).' with pids: '.  implode(', ',array_keys($this->current_children)));
                usleep(1000 * 1000); // секунда
            }
            $this->info('Parent daemon quit');
        }
    }

    public function demonize()
    {
        parent::demonize();
        if (! pcntl_signal( SIGCHLD, array( $this, "parentSignalHandler" ) ) )
        {
            $this->warning( "Failed install signal handler for signal SIGCHLD" );
        }
    }
}