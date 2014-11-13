#!/bin/sh
# Copyright (c) 2014 Peter Jorgensen
# All rights reserved

# script to handle starting/stopping the pierc-postgresql bot, specifically for
# integration with Linux/systemd

cd bot

case "$1" in
    start)
        python pierc.py
    ;;
    stop)
        pkill -f "python pierc.py"
    ;;
    restart)
        pkill -f "python pierc.py"
        python pierc.py
    ;;
    *)
        echo "Usage: ${0} {start|stop|restart}"
        exit 2
cd ..

esac
exit 0
