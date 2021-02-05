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

        if ($this->connection->isNotSendIfExists()) {
            $this->sendIfNotExist($message, $delay);
        } else {
            $this->connection->send($message, $delay);
        }

        return $envelope;
    }

    private function sendIfNotExist(string $jobData, int $delay): void
    {
        $allJobs = $this->getAllJobsInTube();
        $compareJobs = false;

        foreach ($allJobs as $job) {
            if ($job === $jobData) {
                $compareJobs = true;

                break;
            }
        }

        if (!$compareJobs) {
            $this->connection->send($jobData, $delay);
        }
    }

    /**
     * Get all jobs in tube
     *
     * @return array
     */
    private function getAllJobsInTube(): array
    {
        $info = [];

        try {
            /** @var ArrayResponse $response */
            $response = $this->connection->getClient()->statsTube($this->connection->getTube());
            $stats = $response->getArrayCopy();
        } catch (ServerException $exception) {
            return [];
        }

        $readyJobs = [];

        $this->connection->getClient()->watchOnly($this->connection->getTube());

        for ($i = 0; $i < $stats['current-jobs-ready']; $i++) {
            try {
                $job = $this->connection->getClient()->reserveWithTimeout(1);
            } catch (Throwable $exception) {
                continue;
            }

            if (null !== $job) {
                $readyJobs[] = $job;

                $info[$job->getId()] = $job->getData();
            }
        }

        foreach ($readyJobs as $readyJob) {
            $this->connection->getClient()->release($readyJob);
        }

        return $info;
    }
}
