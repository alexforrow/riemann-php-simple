<?php
namespace Riemann;

use DrSlump\Protobuf;

require 'proto.php';

class Client
{
    // details of riemann server
    private $host;
    private $port;

    // string to prefix to all service fields, to allow namespacing
    private $servicePrefix;

    // events that have not been flushed
    private $events;

    // either 'tcp' or 'udp'
    private $defaultProtocol;

    // array of sockets indexed by protocol
    private $sockets;

    // cache of my hostname
    private $fqdn;
    
    /**
     * Configuration for sending to Riemann
     *
     * @param string $host           Riemann server host
     * @param integer $port          Riemann server port
     * @param string $servicePrefix  String to prefix to all service fields, to allow namespacing
     * @param string $defaultProtcol Default protocol to use
     */
    public function __construct($host = 'localhost', $port = 5555, $servicePrefix = '', $defaultProtocol = 'udp')
    {
        $this->host = $host;
        $this->port = $port;
        $this->servicePrefix = $servicePrefix;
        $this->defaultProtocol = self::checkProtocol($defaultProtocol);
        $this->fqdn = gethostname();
    }

    /**
     * Send event to Riemann
     *
     * @param array/Event $event    Either a populated Event object, or array with known Riemann fields
     * @param mixed $flush          Whether to automatically flush. Pass true or particular protocol as string
     */
    public function send($event, $flush = true)
    {
        if (is_array($event)) {
            // build Event
            $eventArray = $event;
            $event = new Event();

            if (isset($eventArray['host'])) {
                $event->host = $eventArray['host'];
            } else {
                $event->host = $this->fqdn;
            }

            if (isset($eventArray['service'])) {
                $event->service = $eventArray['service'];
            }

            if (isset($eventArray['state'])) {
                $event->state = $eventArray['state'];
            }

            $event->time = time();

            if (isset($eventArray['description'])) {
                $event->description = $eventArray['description'];
            }

            if (isset($eventArray['tags'])) {
                $event->tags = is_array($eventArray['tags']) ? $eventArray['tags'] : array($eventArray['tags']);
            }

            if (isset($eventArray['metric'])) {
                $floatMetric = (float)$eventArray['metric'];
                $event->metric_f = $floatMetric;
                if (is_int($eventArray['metric'])) {
                    $event->metric_sint64 = $eventArray['metric'];
                } else {
                    $event->metric_d = $floatMetric;
                }
            }

            if (isset($eventArray['ttl'])) {
                $event->ttl = (int)$eventArray['ttl'];
            }
        }

        // add service prefix
        $event->service = $this->servicePrefix . $event->service;

        // tag the event with this client libary
        $event->tags = array_merge($event->tags, array('riemann-php-simple'));

        // add to internal buffer
        $this->events[] = $event;

        if ($flush) {
            // unless protocol was explicitly specified, just pass null i.e. use default
            $this->flush(is_string($flush) ? $flush : null);
        }
    }

    /**
     * Get a socket to use for sending to Riemann, given socket and payload size
     *
     * @param string $protocol          Protocol we have been requested to use - either 'tcp' or 'udp'
     * @param integer $size             Size in bytes of payload
     */
    private function getSocket(&$protocol, $size) {
        // set default protocol if not given
        if (is_null($protocol)) {
            $protocol = $this->defaultProtocol;
        }

        // over a certain size we send using TCP regardless of protocol requested
        if ($size > 1024*4) {
            $protocol = 'tcp';
        }

        // do we already have a socket created?
        if (isset($this->sockets[$protocol])) {
            return $this->sockets[$protocol];
        }

        self::checkProtocol($protocol);

        $socket = @fsockopen("{$protocol}://{$this->host}", $this->port, $errno, $errstr, 1);
        if (!$socket) {
            error_log("Failed opening socket to Riemann: $errstr [$errno]");
            return false;
        }
        $this->sockets[$protocol] = $socket;

        return $socket;
    }

    /**
     * Flush internal buffer (i.e. perform actual write)
     *
     * This may be called implicity by send()
     *
     * @param string $protocol      Attempt to use this protocol - either 'tcp' or 'udp'     
     */
    public function flush($protocol = null)
    {
        $message = new Msg();
        $message->ok = true;
        $message->events = $this->events;
        $this->events = array();

        $data = $message->serialize();
        $size = strlen($data);

        // get socket based on protocol
        if (!($socket = $this->getSocket($protocol, $size))) {
            return false;
        }

        if ('tcp' === $protocol) {
            // TCP requires the length to be sent first
            fwrite($socket, pack('N', $size));
        }
        
        fwrite($socket, $data);      

        return true;
    }

    /**
     * Check protocol is legal
     *
     * @param string $protocol      The protocol to check
     * @return string               The protocol
     */
    private static function checkProtocol($protocol) {
        if (!in_array($protocol, array('tcp', 'udp'))) {
            throw new \Exception("Unknown protocol specified: $protocol");
        }
        return $protocol;
    }
}
