#!/bin/sh

. /etc/rc.subr

name="%name%"
%name%_cmd="`which php` %cmd_path%"

extra_commands="reload"

start_cmd="${name}_start"
stop_cmd="${name}_stop"
status_cmd="${name}_status"
reload_cmd="${name}_reload"

%name%_start()
{
    $%name%_cmd start
}

%name%_stop()
{
    $%name%_cmd stop
}

%name%_status()
{
    $%name%_cmd status
}

%name%_reload()
{
    $%name%_cmd reload
}

load_rc_config $name
run_rc_command "$1"
