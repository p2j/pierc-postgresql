#!/bin/sh
# Copyright (c) 2014 Peter Jorgensen
# All rights reserved

# script to handle starting/stopping the pierc-postgresql bot, specifically for
# integration with Linux/systemd

cd bot

case "$1" in
    start)
        if [ -e IRCbot.running ]; then
            if ( kill -0 $(cat IRCbot.running) 2> /dev/null ); then
                echo "The IRCbot is already running, try restart or stop"
                exit 1
            else
                echo "IRCbot.running found, but no server running..."
                rm IRCbot.running
            fi
        fi
        if [ "${UID}" = "0" ]; then
            echo "Don't run the server as root..."
        fi
        echo "Starting the IRCbot"
        python pierc.py > /dev/null &
        touch IRCbot.running
    ;;
    stop)
        echo "Stopping the IRCbot..."
        if ( kill -TERM $(cat IRCbot.running) 2> /dev/null ); then
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
            rm IRCbot.running
        fi
    ;;
    restart)
        echo "Stopping the IRCbot..."
        if ( kill -TERM $(cat IRCbot.running) 2> /dev/null ); then
            c=1
            while [ "$c" -le 300 ]; do
                if ( kill -0 $(cat IRCbot.running) 2> /dev/null ); then
                    echo -n "."
                    sleep 1
                else
                    break
                fi
                c=$(($c+1))
            done
            rm IRCbot.running
        fi
        if [ -e IRCbot.running ]; then
            if ( kill -0 $(cat IRCbot.running) 2> /dev/null ); then
                echo "The IRCbot is already running, try restart or stop"
                exit 1
            else
                echo "IRCbot.running found, but no service running..."
                rm IRCbot.running
            fi
        fi
        if [ "${UID}" = "0" ]; then
            echo "Don't run the service as root..."
        fi
        echo "Starting the IRCbot"
        python ./bot/pierc.py
        touch IRCbot.running
    ;;
    status)
        if [ -e IRCbot.running ]; then
            if ( kill -0 $(cat IRCbot.running) 2> /dev/null ); then
                echo "Service is running"
            else
                echo "Service has died"
            fi
        else
            echo "No server running (IRCbot.running is missing)"
        fi
    ;;
    *)
        echo "Usage: ${0} {start|stop|restart|status}"
        exit 2
cd ..

exit 0
