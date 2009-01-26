#!/bin/sh

private_notify="/home/indefero/tmp/notify.tmp"
reload_cmd="/usr/sbin/apachectl -k graceful"

if [ -e $private_notify ]; then
    rm -f $private_notify
    $reload_cmd
fi
  
