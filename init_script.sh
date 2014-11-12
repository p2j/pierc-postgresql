#!/bin/sh
# Copyright (c) 2014 Peter Jorgensen
# All rights reserved

# script to handle starting/stopping the pierc-postgresql bot, specifically for
# integration with Linux/systemd

case "$1" in
    start)
        if [ -e IRCbot.pid ]; then
            if ( kill -0 $(cat IRCbot.pid) 2> /dev/null ); then
                echo "The IRCbot is already running, try restart or stop"
                exit 1
            else
                echo "IRCbot.pid found, but no server running..."
                rm IRCbot.pid
            fi
        fi
        if [ "${UID}" = "0" ]; then
            echo "Don't run the server as root..."
        fi
        echo "Starting the IRCbot"
        python ./bot/pierc.py
    ;;
    stop)
        echo "Stopping the IRCbot..."
        if ( kill -TERM $(cat IRCbot.pid) 2> /dev/null ); then
            c=1
            while [ "$c" -le 300 ]; do
                if ( kill -0 $(cat ts3server.pid) 2> /dev/null ); then
                    echo -n "."
                    sleep 1
                else
                    break
                fi
                c=$(($c+1))
            done
        fi
    ;;
    restart)
        echo "Stopping the IRCbot..."
        if ( kill -TERM $(cat IRCbot.pid) 2> /dev/null ); then
            c=1
            while [ "$c" -le 300 ]; do
                if ( kill -0 $(cat ts3server.pid) 2> /dev/null ); then
                    echo -n "."
                    sleep 1
                else
                    break
                fi
                c=$(($c+1))
            done
        fi
        if [ -e IRCbot.pid ]; then
            if ( kill -0 $(cat IRCbot.pid) 2> /dev/null ); then
                echo "The IRCbot is already running, try restart or stop"
                exit 1
            else
                echo "IRCbot.pid found, but no server running..."
                rm IRCbot.pid
            fi
        fi
        if [ "${UID}" = "0" ]; then
            echo "Don't run the server as root..."
        fi
        echo "Starting the IRCbot"
        python ./bot/pierc.py
    ;;
    status)
        if [ -e IRCbot.pid ]; then
            if ( kill -0 $(cat IRCbot.pid) 2> /dev/null ); then
                echo "Server is running"
            else
                echo "Server has died"
            fi
        else
            echo "No server running (IRCbot.pid is missing)"
        fi
    ;;
    *)
        echo "Usage: ${0} {start|stop|restart|status}"
        exit 2

exit 0
