<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Ubuntu
 *
 * @author walkerror
 */

namespace Process\Os;


class Linux extends OsAbstract
{
    public function getAutoloadDir()
    {
        return '/etc/init.d';
    }

    public function getConfigDir()
    {
        return '/usr/local/etc';
    }

    public function getOsName()
    {
        return 'linux';
    }
    
    public function install($cmd_path)
    {
        parent::install($cmd_path);
        exec('update-rc.d ' . $this->deamon->getName() . ' defaults');
    }

    public function uninstall()
    {
        parent::uninstall();
        exec('update-rc.d ' . $this->deamon->getName() . ' remove');
    }

}