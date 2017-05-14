#!/usr/bin/env php
<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

set_time_limit(0);
date_default_timezone_set('Europe/Moscow');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('mysql.connect_timeout', 1000000);

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/DeamonExmpl.php';

if (!setlocale(LC_ALL, 'en_US.UTF-8'))
{
    exit(1);
}
$deamon = new DeamonExmpl;
$deamon->setLogLevel(\Process\Deamon::LOG_DEBUG);
$deamon->setMaxChildCount(2);
$deamon->handleCmd();