<?php

namespace RetailCrm\Messenger\Beanstalkd\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use LogicException;

/**
 * Class BeanstalkReceiver
 *
 * @package RetailCrm\Messenger\Beanstalkd\Transport
 */
class BeanstalkReceiver implements ReceiverInterface
{
    private $connection;
    private $serializer;

    public function __construct(Connection $connection, SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function get(): iterable
    {
        $pheanstalkEnvelope = $this->connection->get();

        if (null === $pheanstalkEnvelope) {
            return [];
        }

        $message = $this->connection->deserializeJob($pheanstalkEnvelope->getData());

        if (null !== $this->connection->getLockStorage()) {
            $messageKey = hash('crc32', $pheanstalkEnvelope->getData());
            $this->connection->getLockStorage()->releaseLock($messageKey);
        }

        try {
            $envelope = $this->serializer->decode([
                'body' => $message['body'],
                'headers' => $message['headers']
            ]);
        } catch (MessageDecodingFailedException $exception) {
            $this->connection->getClient()->delete($pheanstalkEnvelope);

            throw $exception;
        }

        return [$envelope->with(new BeanstalkReceivedStamp($this->connection->getTube(), $pheanstalkEnvelope))];
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        $this->connection->ack($this->findReceivedStamp($envelope)->getJob());
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        $this->connection->reject($this->findReceivedStamp($envelope)->getJob());
    }

    private function findReceivedStamp(Envelope $envelope): BeanstalkReceivedStamp
    {
        /** @var BeanstalkReceivedStamp|null $receivedStamp */
        $receivedStamp = $envelope->last(BeanstalkReceivedStamp::class);

        if (null === $receivedStamp) {
            throw new LogicException('No BeanstalkReceivedStamp found on the Envelope.');
        }

        return $receivedStamp;
    }
}
