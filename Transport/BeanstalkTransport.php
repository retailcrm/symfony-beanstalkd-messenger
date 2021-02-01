<?php

namespace RetailCrm\Messenger\Beanstalkd\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Class BeanstalkTransport
 *
 * @package RetailCrm\Messenger\Beanstalkd\Transport
 */
class BeanstalkTransport implements TransportInterface
{
    private $serializer;
    private $connection;
    private $receiver;
    private $sender;

    public function __construct(Connection $connection, ?SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function get(): iterable
    {
        return ($this->receiver ?? $this->getReceiver())->get();
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        ($this->receiver ?? $this->getReceiver())->ack($envelope);
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        ($this->receiver ?? $this->getReceiver())->reject($envelope);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        return ($this->sender ?? $this->getSender())->send($envelope);
    }

    private function getReceiver(): BeanstalkReceiver
    {
        return $this->receiver = new BeanstalkReceiver($this->connection, $this->serializer);
    }

    private function getSender(): BeanstalkSender
    {
        return $this->sender = new BeanstalkSender($this->connection, $this->serializer);
    }
}
