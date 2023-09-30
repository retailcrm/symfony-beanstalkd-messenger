<?php

namespace RetailCrm\Messenger\Beanstalkd\Transport;

use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Exception\ServerException;
use Pheanstalk\Response\ArrayResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Throwable;

/**
 * Class BeanstalkSender
 *
 * @package RetailCrm\Messenger\Beanstalkd\Transport
 */
class BeanstalkSender implements SenderInterface
{
    private $connection;
    private $serializer;

    /**
     * BeanstalkSender constructor.
     *
     * @param Connection          $connection
     * @param SerializerInterface $serializer
     */
    public function __construct(Connection $connection, SerializerInterface $serializer)
    {
        $this->connection = $connection;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);

        /** @var Stamp\DelayStamp|null $delayStamp */
        $delayStamp = $envelope->last(Stamp\DelayStamp::class);
        $delay = PheanstalkInterface::DEFAULT_DELAY;

        if (null !== $delayStamp) {
            $delay = $delayStamp->getDelay();
        }

        $message = $this->connection->serializeJob($encodedMessage['body'], $encodedMessage['headers'] ?? []);

        if ($this->connection->isNotSendIfExists() && null !== $this->connection->getLockStorage()) {
            $this->sendIfNotExist($message, $delay);
        } else {
            $this->connection->send($message, $delay);
        }

        return $envelope;
    }

    private function sendIfNotExist(string $jobData, int $delay): void
    {
        $messageKey = hash('crc32', $jobData);

        if ($this->connection->getLockStorage()->setLock($messageKey)) {
            $this->connection->send($jobData, $delay);
        }
    }
}
