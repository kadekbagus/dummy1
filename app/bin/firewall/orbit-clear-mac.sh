#!/bin/bash
#
# @desc Clear all connected devices

clear

ipset flush permanent-allow
ipset flush temporary-allow
ipset flush temp-anon-allow

iptables -t nat -nvL

echo "------------------------------------"

iptables -nvL
