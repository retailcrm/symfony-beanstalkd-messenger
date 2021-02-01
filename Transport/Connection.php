<?php

namespace RetailCrm\Messenger\Beanstalkd\Transport;

use Pheanstalk\Contract\JobIdInterface;
use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;
use Throwable;

/**
 * Class Connection
 *
 * @package RetailCrm\Messenger\Beanstalkd\Transport
 */
class Connection
{
    private const DEFAULT_OPTIONS = [
        'tube_name' => PheanstalkInterface::DEFAULT_TUBE,
        'timeout' => 0,
        'ttr' => PheanstalkInterface::DEFAULT_TTR,
        'not_send_if_exists' => true,
    ];

    private $client;
    private $tube;
    private $timeout;
    private $ttr;
    private $notSendIfExists;

    /**
     * Connection constructor.
     *
     * @param array                    $options
     * @param PheanstalkInterface      $pheanstalk
     */
    public function __construct(array $options, PheanstalkInterface $pheanstalk)
    {
        $this->ttr = $options['ttr'];
        $this->tube = $options['tube_name'];
        $this->timeout = $options['timeout'];
        $this->notSendIfExists = $options['not_send_if_exists'];

        $this->client = $pheanstalk;
    }

    /**
     * @param string                   $dsn
     * @param array                    $options
     * @param PheanstalkInterface|null $pheanstalk
     *
     * @return static
     */
    public static function fromDsn(string $dsn, array $options = [], ?PheanstalkInterface $pheanstalk = null): self
    {
        unset($options['transport_name']);

        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given Pheanstalk DSN "%s" is invalid.', $dsn));
        }

        $notAllowedOptions = array_diff(array_keys($options), array_keys(self::DEFAULT_OPTIONS));
        if (0 < \count($notAllowedOptions)) {
            throw new InvalidArgumentException(
                sprintf("Options: %s is not allowed", implode(', ', $notAllowedOptions))
            );
        }

        $connectionCredentials = [
            'host' => $parsedUrl['host'] ?? '127.0.0.1',
            'port' => $parsedUrl['port'] ?? PheanstalkInterface::DEFAULT_PORT
        ];

        $options = array_merge(self::DEFAULT_OPTIONS, $options);

        if (null === $pheanstalk) {
            $pheanstalk = Pheanstalk::create($connectionCredentials['host'], $connectionCredentials['port']);
        }

        return new self($options, $pheanstalk);
    }

    public function getClient(): PheanstalkInterface
    {
        return $this->client;
    }

    public function getTtr(): int
    {
        return $this->ttr;
    }

    public function getTube(): string
    {
        return $this->tube;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function get(): ?Job
    {
        try {
            return $this->client->watchOnly($this->tube)->reserveWithTimeout($this->timeout);
        } catch (Throwable $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function ack(JobIdInterface $job): void
    {
        $this->delete($job);
    }

    public function reject(JobIdInterface $job): void
    {
        $this->delete($job);
    }

    public function isNotSendIfExists(): bool
    {
        return $this->notSendIfExists;
    }

    public function send(string $message, int $delay = 0): void
    {
        try {
            $this->client->useTube($this->tube)->put(
                $message,
                PheanstalkInterface::DEFAULT_PRIORITY,
                $delay,
                $this->ttr
            );
        } catch (Throwable $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function serializeJob(string $body, array $headers = []): string
    {
        $message = json_encode(
            ['headers' => $headers, 'body' => $body]
        );

        if (false === $message) {
            throw new TransportException(json_last_error_msg());
        }

        return $message;
    }

    public function deserializeJob(string $jobData): array
    {
        return json_decode($jobData, true);
    }

    private function delete(JobIdInterface $job): void
    {
        try {
            $this->client->useTube($this->tube)->delete($job);
        } catch (Throwable $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }
}
