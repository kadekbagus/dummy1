#!/bin/bash
#
# @author Rio Astamal <me@rioastamal.net>
# @desc Script for removing unused (idle) mac address
#
# The idea
# --------
#    1. List PREROUTING from iptables nat rules
#    2. Filter list by which started by keyword 'RETURN'
#    3. Split each list columns by whitespace
#    4. Get the last column [or column no 7] = mac
#    5. For each mac in list
#          Convert the mac to lowercase
#          Get IP address of mac from ARP table
#          If the mac not found on ARP table then
#             Remove the mac from iptables
#          End if
#    6. End for each
#

# Directory which store list of coresponding mac and ip address which has been
# allowed from Orbit application
BASEDIR_SCRIPT=$1
UNREGISTER_SCRIPT="orbit-register-ip-v4.sh"

[ -z "${BASEDIR_SCRIPT}" ] && {
    # User does not supply directory, use script directory as base
    # http://stackoverflow.com/questions/242538/unix-shell-script-find-out-which-directory-the-script-file-resides
    SCRIPTPATH=$( readlink -f "$0" )
    BASEDIR_SCRIPT=$( dirname "$SCRIPTPATH" )
}

[ ! -d ${BASEDIR_SCRIPT} ] && {
    # Redirect to STDERR
    echo "Could not find basedir directory ${BASEDIR_SCRIPT}." 1>&2
    exit 2;
}

# Remove trailing slash
BASEDIR_SCRIPT=$( echo "${BASEDIR_SCRIPT}" | sed 's#/$##' )

# List of allowed IP
USER_IPS_TEMP=$( ipset output -plain list temporary-allow | awk '/^Members:/,/^$/ { if (!/^Members:/) { print $1; } }' )
USER_IPS_PERM=$( ipset output -plain list permanent-allow | awk '/^Members:/,/^$/ { if (!/^Members:/) { print $1; } }' )

# Get ARP Table, using `ip neigh` instead of `arp -an`
ARP_TABLE=$( ip -4 -o neigh show )

for IP in $USER_IPS_TEMP $USER_IPS_PERM
do
    # Does this IP listed on ARP Table?
    # We check this by also trying to get the IP address associated with this
    # mac The IP is on second column, i.e:
    #  example 1 -> 10.0.0.108 dev wlan0 lladdr 9c:04:eb:0a:e8:a2 STALE
    #  example 2 -> 10.0.0.108 dev wlan0 lladdr 9c:04:eb:0a:e8:a2 REACHABLE
    STATUS=$( echo "${ARP_TABLE}" | grep "^${IP}" | awk '{print $NF}' )

    # if the status not "REACHABLE" means the device is not connected to our box
    if [ "${STATUS}" != "REACHABLE" ]; then
        echo -n "IP address ${IP} is not listed on ARP table, removing..."

        # Revoke this mac from iptables so it can not connect to the internet
        echo "${IP} delete" | ${BASEDIR_SCRIPT}/${UNREGISTER_SCRIPT}
        echo "done."

        # If status is empty, means the mac is not found on the arp table
        [ -z "${STATUS}" ] && continue

        # Delete the device from ARP
        MAC_ADDR=$( echo "${ARP_TABLE}" | grep ${IP} | awk '{print $5}' )
        IP_DEV=$( echo "${ARP_TABLE}" | grep ${IP} | awk '{print $3}' )
        echo -n "Revoking ${MAC_ADDR} ${IP} dev ${IP_DEV} from ARP..."
        ip neigh del ${IP} dev ${IP_DEV}
        echo "done."
    fi
done

exit 0
