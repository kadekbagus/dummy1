#!/bin/bash
#
# @author Rio Astamal <me@rioastamal.net>
# @desc Script for removing any iptables rules which may exists
#
echo -n "Flushing iptables..."
iptables -F
iptables -X
iptables -t nat -F
iptables -t nat -X
iptables -t mangle -F
iptables -t mangle -X
iptables -P INPUT ACCEPT
iptables -P FORWARD ACCEPT
iptables -P OUTPUT ACCEPT

# Remove all ipset sets after flushing above so no sets are referenced
echo -n "and ipsets..."
ipset destroy

# Do not allow packet forwarding
echo 0 > /proc/sys/net/ipv4/ip_forward
echo "done."
