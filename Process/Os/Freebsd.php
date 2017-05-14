<?php

/**
 * Класс Freebsd 
 *
 * @author walkerror
 */

namespace Process\Os;

use Process\Os\OsAbstract;

class Freebsd extends OsAbstract
{
    
    public function getAutoloadDir()
    {
        return '/usr/local/etc/rc.d';
    }

    public function getConfigDir()
    {
        return '/usr/local/etc';
    }

    public function getOsName()
    {
        return 'freebsd';
    }
}