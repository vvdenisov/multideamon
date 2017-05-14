<?php

/**
 * Класс Os 
 * @author walkerror
 */

namespace Process\Os;

abstract class OsAbstract
{
    protected $deamon;
    
    public function __construct(Process_Deamon $deamon)
    {
        $this->deamon = $deamon;
    }
    
    public function getAutoloadPath()
    {
        $name = $this->deamon->getName();
        return $this->getAutoloadDir() . DIRECTORY_SEPARATOR . $name;
    }
    
    public function getConfigPath()
    {
        $name = $this->deamon->getName();
        return $this->getConfigDir() . DIRECTORY_SEPARATOR . $name;
    }

    public function installedFiles()
    {
        $files = array();
        $autoload_file = $this->getAutoloadPath();
        if(file_exists($autoload_file))
        {
            $files[] = $autoload_file;
        }
        $config_file = $this->getConfigPath();
        if(file_exists($config_file))
        {
            $files[] = $config_file;
        }
        return $files;
    }
    
    public function install($cmd_path)
    {
        $debug = debug_backtrace();
        $template_path = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'rc_'.strtolower($this->getOsName()).'.sh';
        $template = file_get_contents($template_path);
        $name = $this->deamon->getName();
        $cmd_path = $debug[count($debug)-1]['file'];
        $template = str_replace('%name%', $name, $template);
        $template = str_replace('%cmd_path%', $cmd_path, $template);
        $autoload_file = $this->getAutoloadPath();
        file_put_contents($autoload_file, $template);
        chmod($autoload_file, 0755);
    }
    
    public function uninstall()
    {
        $autoload_file = $this->getAutoloadPath();
        unlink($autoload_file);
    }
    
    public static function factory(Process_Deamon $deamon)
    {
        $os = null;
        switch (strtolower(PHP_OS))
        {
            case 'freebsd':
                $os = new Process_Os_Freebsd($deamon);
                break;
            case 'linux':
                $os = new Process_Os_Linux($deamon);
                break;
        }
        return $os;
    }
    
    abstract public function getAutoloadDir();
    
    abstract public function getConfigDir();
    
    abstract public function getOsName();
    
}