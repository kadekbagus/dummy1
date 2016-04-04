#!/bin/bash
#
# @author Rio Astamal <me@rioastamal.net>
# @author William <william@dominopos.com>
# @desc Blackhole or allow traffic for Orbit user

DEFAULTS=/etc/default/orbit-iptables
IPTABLES="/sbin/iptables"
IPSET="/sbin/ipset"
WALLED_GARDEN_TIMEOUT=7200

[ -f "$DEFAULTS" ] && . "$DEFAULTS" || { echo "Please create $DEFAULTS" >&2 ; exit 1; }

echo "Read defaults from $DEFAULTS"

ORBIT_REDIRECT_MODE=${ORBIT_REDIRECT_MODE:-default}
ORBIT_TEMPORARY_ALLOW_TIMEOUT=${ORBIT_TEMPORARY_ALLOW_TIMEOUT:-1800}
ORBIT_GUEST_HTTPS_TIMEOUT=${ORBIT_GUEST_HTTPS_TIMEOUT:-600}

# Prevent ICMP Redirects
# ----------------------
# Get all of interfaces and prevent the icmp redirect
echo -n "Prevent ICMP redirect..."
for iface in $( netstat -i | tail -n +3 | awk '{print $1}' )
do
    echo 0 > /proc/sys/net/ipv4/conf/${iface}/accept_redirects
    echo 0 > /proc/sys/net/ipv4/conf/${iface}/send_redirects
done
echo 0 > /proc/sys/net/ipv4/conf/all/accept_redirects
echo 0 > /proc/sys/net/ipv4/conf/all/send_redirects
echo "done."

echo -n "Setting up IP sets..."
# Ubuntu 14.04 does not have hash:mac so we resort to bitmap:ip,mac
$IPSET create temporary-allow bitmap:ip,mac range "${ORBIT_GUEST_SUBNET}" timeout "${ORBIT_TEMPORARY_ALLOW_TIMEOUT}" counters
$IPSET create permanent-allow bitmap:ip,mac range "${ORBIT_GUEST_SUBNET}" counters
$IPSET create temp-anon-allow bitmap:ip range "${ORBIT_GUEST_SUBNET}" timeout "${ORBIT_GUEST_HTTPS_TIMEOUT}" counters
$IPSET create walled-garden hash:ip timeout ${WALLED_GARDEN_TIMEOUT}
echo "done"


# create user chain to save checking against the set
$IPTABLES -t nat -N prerouting-from-allowed

# if going to an IP in our walled garden set, allow
$IPTABLES -t nat -A PREROUTING -i ${ORBIT_GUEST_INTERFACE} -m set --match-set walled-garden dst -j ACCEPT

# match source ip, source mac against allowed sets.
$IPTABLES -t nat -A PREROUTING -i ${ORBIT_GUEST_INTERFACE} -m set --match-set permanent-allow src,src -j prerouting-from-allowed
$IPTABLES -t nat -A PREROUTING -i ${ORBIT_GUEST_INTERFACE} -m set --match-set temporary-allow src,src -j prerouting-from-allowed

# if user is in allowed set:
#     redirect DNS to something that returns real addresses + resolves .mall
echo -n "Prerouting rules to redirect allowed users to Real DNS ${ORBIT_REAL_DNS_HOST}:${ORBIT_REAL_DNS_PORT}..."
$IPTABLES -t nat -A prerouting-from-allowed -p tcp -m tcp --dport 53 -m comment --comment "DNS to Real DNS" -j DNAT --to-destination ${ORBIT_REAL_DNS_HOST}:${ORBIT_REAL_DNS_PORT}
$IPTABLES -t nat -A prerouting-from-allowed -p udp -m udp --dport 53 -m comment --comment "DNS to Real DNS" -j DNAT --to-destination ${ORBIT_REAL_DNS_HOST}:${ORBIT_REAL_DNS_PORT}
echo "done"
#     do not redirect anything else
$IPTABLES -t nat -A prerouting-from-allowed -j ACCEPT

echo "Actions for blocked users:"
# create chain
$IPTABLES -t nat -N prerouting-from-blocked

echo -n "Prerouting rules to redirect DNS to Guest DNS ${ORBIT_GUEST_DNS_HOST}:${ORBIT_GUEST_DNS_PORT}..."
$IPTABLES -t nat -A prerouting-from-blocked -p tcp --dport 53 -m comment --comment "DNS to Guest DNS" -j DNAT --to-destination ${ORBIT_GUEST_DNS_HOST}:${ORBIT_GUEST_DNS_PORT}
$IPTABLES -t nat -A prerouting-from-blocked -p udp --dport 53 -m comment --comment "DNS to Guest DNS" -j DNAT --to-destination ${ORBIT_GUEST_DNS_HOST}:${ORBIT_GUEST_DNS_PORT}
echo "done."

$IPTABLES -t nat -A prerouting-from-blocked -p tcp -d 192.168.0.0/16 -m comment --comment "to internal" -j ACCEPT

# Redirect all 80,443 traffic to our box itself (for those not in the allow sets)
if [ "$ORBIT_REDIRECT_MODE" = "default" ]; then
        echo -n "Prerouting rules to redirect port 80 and 443 to Portal ${ORBIT_PORTAL_HOST}..."
        $IPTABLES -t nat -A prerouting-from-blocked -p tcp --dport 80 -m comment --comment "HTTP to Portal" -j DNAT --to-destination ${ORBIT_PORTAL_HOST}:80
        $IPTABLES -t nat -A prerouting-from-blocked -p tcp --dport 443 -m comment --comment "HTTPS to Portal" -j DNAT --to-destination ${ORBIT_PORTAL_HOST}:443
        echo "done."
fi

if [ "$ORBIT_REDIRECT_MODE" = "all" ]; then
        echo -n "Prerouting rules to redirect all TCP and UDP to Portal ${ORBIT_PORTAL_HOST}..."
        $IPTABLES -t nat -A prerouting-from-blocked -p tcp -m comment --comment "All TCP to Portal" -j DNAT --to-destination ${ORBIT_PORTAL_HOST}
        $IPTABLES -t nat -A prerouting-from-blocked -p udp -m comment --comment "All UDP to Portal" -j DNAT --to-destination ${ORBIT_PORTAL_HOST}
        echo "done."
fi

# final rule for prerouting-from-blocked so it does not return and get added to the sets.
$IPTABLES -t nat -A prerouting-from-blocked -j ACCEPT
echo "Done adding actions for blocked users."

# We are using dnsmasq ipset feature now, so the rule allow temporary https access are not needed anymore.
# We are planning to cleanup this source code later.

# create chain for prerouting anonymous guest users: add "allow https"
# $IPTABLES -t nat -N prerouting-from-anon
# $IPTABLES -t nat -A prerouting-from-anon -p tcp -m tcp --dport 443 -m comment --comment "Allow all HTTPS out" -j ACCEPT
# $IPTABLES -t nat -A prerouting-from-anon -j prerouting-from-blocked

# if user not in allowed set:
#     if user is in temp-anon-allow (have clicked social login): allow HTTPS out to any
# $IPTABLES -t nat -A PREROUTING -i ${ORBIT_GUEST_INTERFACE} -m set --match-set temp-anon-allow src -j prerouting-from-anon
#     else consider as blocked (use guest DNS, redirect HTTP & HTTPS)
$IPTABLES -t nat -A PREROUTING -i ${ORBIT_GUEST_INTERFACE} -j prerouting-from-blocked

echo -n "Setting up packet forwarding..."
iptables -t nat -A POSTROUTING -s ${ORBIT_GUEST_SUBNET} -d "192.168.0.0/16" -o ${ORBIT_GW_INTERFACE} -m comment --comment "NAT to LMP internal" -j MASQUERADE
iptables -t nat -A POSTROUTING -m set --match-set walled-garden dst -o ${ORBIT_GW_INTERFACE} -m comment --comment "Allow walled garden out" -j MASQUERADE
# Masquerade the source IP if allowed
iptables -t nat -A POSTROUTING -m set --match-set temporary-allow src,src -o ${ORBIT_GW_INTERFACE} -m comment --comment "Allow temp out" -j MASQUERADE
iptables -t nat -A POSTROUTING -m set --match-set permanent-allow src,src -o ${ORBIT_GW_INTERFACE} -m comment --comment "Allow perm out" -j MASQUERADE

# if anon-allowed, allow only HTTPS out
# iptables -t nat -A POSTROUTING -m set --match-set temp-anon-allow src -o ${ORBIT_GW_INTERFACE} -p tcp -m tcp --dport 443 -m comment --comment "Allow anon HTTPS out" -j MASQUERADE

# Enable IP Forwarding
echo 1 > /proc/sys/net/ipv4/ip_forward
echo "done."

exit 0