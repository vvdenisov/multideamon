#!/bin/sh

name="%name%"
%name%_cmd="`which php` %cmd_path%"

command_args="-r ${%name%}"

start()
{
    $%name%_cmd start
}

stop()
{
    $%name%_cmd stop
}

status()
{
    $%name%_cmd status
}

reload()
{
    $%name%_cmd reload
}

case "$1" in
    start)
        start
    ;;
    stop)
        stop
    ;;
    status)
        status
    ;;
    reload|restart)
        reload
    ;;
    *)
        reload
    ;;
esac

exit 0
