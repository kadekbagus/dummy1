<?php namespace Net\Security;
/**
 * A helper which dealing with security firewall for Orbit Application inside
 * shop.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class Firewall
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // do nothing
    }

    /**
     * Static method for instantiate the class.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Firewall
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Grant access for certain mac address based on IP.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $ip - The IP address which used for detecting mac address
     * @return array
     */
    public function grantMacByIP($ip)
    {
        return $this->registerMacAddress($ip);
    }

    /**
     * Grant access for certain mac address based on IP.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $ip - The IP address which used for detecting mac address
     * @return array
     */
    public function revokeMacByIP($ip)
    {
        return $this->registerMacAddress($ip);
    }

    protected function registerMacAddress($userIp, $mode='register')
    {
        if (Config::get('orbit.security.firewall.fake_call') === TRUE) {
            // This is a fake call, so return a fake result
            $fake = array(
                'status'    => TRUE,
                'mac'       => 'FA:KE:MA:CA:DR',
                'message'   => sprint('Fake grant IP %s', $userIp);
            );

            if ($mode !== 'register') {
                $fake['message'] = sprintf('Fake revoke IP %s', $userIp);
            }

            return $fake;
        }

        $return = array(
            'status'    => FALSE,
            'mac'       => '',
            'message'   => ''
        );

        $addMacCmd = Config::get('orbit.firewall.command');
        if (empty($addMacCmd)) {
            $return['status'] = FALSE;
            $return['message'] = 'I could not find the orbit.firewall.command configuration.';

            return $return;
        }

        // Get mac address based on the IP using ARP table
        // -a display (all) hosts in alternative (BSD) style
        // -n do not resolve domain names
        $cmdArp = Command::Factory('arp -an ' . $userIp)->run();

        if ($cmdArp->getExitCode() !== 0) {
            $return['message'] = empty($cmdArp->getStderr()) ? $cmdArp->getStdout() : $cmdArp->getStderr();

            return $return;
        }

        // Get the mac address
        $output = $cmdArp->getStdout();

        // i.e: arp command output are "? (192.168.0.109) at 08:00:27:4c:5b:cc [ether] on eth0"
        preg_match('/at\s(([0-9A-F]{2}[:-]){5}([0-9A-F]{2}))/i', $output, $matches);
        if (! isset($matches[1])) {
            $return['message'] = sprintf('I could not find mac pattern inside arp output "%s"', $output);

            return $return;
        }

        // We got the mac address
        $mac = $matches[1];

        // Register or deregister it to router
        if ($mode === 'register') {
            $message = sprintf('IP %s with mac %s has been successfully registered.', $userIp, $mac);
            $stdin = "$mac\n";
        } else {
            $message = sprintf('IP %s with mac %s has been successfully revoked.', $userIp, $mac);
            $stdin = "$mac delete\n";
        }

        $iptablesCmd = Command::Factory($addMacCmd)->run($stdin);
        if ($iptablesCmd->getExitCode() !== 0) {
            $return['message'] = empty($iptablesCmd->getStderr()) ? $iptablesCmd->getStdout() : $iptablesCmd->getStderr();

            return $return;
        }

        $return['status'] = TRUE;
        $return['mac'] = $mac;
        $return['message'] = $message;
        $return['object'] = $iptablesCmd;

        return $return;
    }
}
