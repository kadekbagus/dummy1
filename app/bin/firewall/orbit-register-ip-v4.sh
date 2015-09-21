#!/bin/bash
#
# @author Rio Astamal <me@rioastamal.net>
# @desc Script to register or deregister orbit client Mac Address
#

log() {
    [ -x /usr/bin/logger ] && logger -p daemon.info -t orbit-iptables "$1";
}

# Read from STDIN
# ---------------
# The format for STDIN are: "[IP] [MODE]"
while read ip mode
do
    if [ "${mode}" = "delete" ]; then
        # delete from sets ignoring errors
        # ("cannot be deleted from the set: it's not added")
        ipset -exist del temporary-allow "${ip}"
        log "delete temporary-allow ip=${ip} rv $?"
        ipset -exist del permanent-allow "${ip}"
        log "delete permanent-allow ip=${ip} rv $?"
        ipset -exist del temp-anon-allow "${ip}"
        log "delete temp-anon-allow ip=${ip} rv $?"
    elif [ "${mode}" = "check-mac-logged-in" ]; then
        # test both chains
        {
            ipset test temporary-allow "${ip}" > /dev/null 2>&1 ||
            ipset test permanent-allow "${ip}" > /dev/null 2>&1
        } && echo "true" || echo "false"
    elif [ "${mode}" = "add-temporary" ]; then
        ipset -exist add temporary-allow "${ip}"
        log "add-temporary ip=${ip} rv $?"
    elif [ "${mode}" = "add-temporary-anon" ]; then
        ipset -exist add temp-anon-allow "${ip}"
        log "add-temporary-anon ip=${ip} rv $?"
    else
        ipset -exist add permanent-allow "${ip}"
        log "add-permanent ip=${ip} rv $?"
    fi
done

# If we goes here then everything is fine
exit 0
