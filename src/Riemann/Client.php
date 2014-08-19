<?php
namespace Riemann;

use DrSlump\Protobuf;

require 'proto.php';

class Client
{
    // details of riemann server
    private $host;
    private $port;

    // events that have not been flushed
    private $events;

    // either 'tcp' or 'udp'
    private $defaultProtocol;

    // array of sockets indexed by protocol
    private $sockets;
    
    /**
     * Configuration for sending to Riemann
     *
     * @param string $host           Riemann server host
     * @param integer $port          Riemann server port
     * @param string $defaultProtcol Default protocol to use
     */
    public function __construct($host = 'localhost', $port = 5555, $defaultProtocol = 'udp')
    {
        $this->host = $host;
        $this->port = $port;
        $this->defaultProtocol = $defaultProtocol;
    }

    /**
     * Send event to Riemann
     *
     * @param array/Event $event     Either a populated Event object, or array with known Riemann fields
     * @param booelan $flush         Whether to automatically flush
     * @param string $protocol       Which protocol to use if flushing. Usesd default if unspecified
     */
    public function send($event, $flush = true, $protocol = null)
    {
        if (is_array($event)) {
            // build Event
            $eventArray = $event;
            $event = new Event();

            if (isset($eventArray['host'])) {
                $event->host = $eventArray['host'];
            } else {
                $event->host = php_uname('n');
            }

            if (isset($eventArray['service'])) {
                $event->service = $eventArray['service'];
            }

            if (isset($eventArray['state'])) {
                $event->state = $eventArray['state'];
            }

            $event->time = (new \DateTime())->getTimestamp();

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

        // add to internal buffer
        $this->events[] = $event;

        if ($flush) {
            $this->flush($protocol);
        }
    }

    /**
     * Get a socket to use for sending to Riemann, given socket and payload size
     *
     * @param string $requestedProtocl  Protocol we have been requested to use - either 'tcp' or 'udp'
     * @param integer $size             Size in bytes of payload
     */
    private function getSocket($requestedProtocol, $size) {
        // Over a certain size we send TCP
        if ($size > 1024*4) {
            $protocol = 'tcp';
        } elseif ($requestedProtocol) {
            $protocol = $requestedProtocol;
        } else {
            $protocol = $this->defaultProtocol;
        }

        // do we already have a socket created?
        if (isset($this->sockets[$protocol])) {
            return $this->sockets[$protocol];
        }

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
}
