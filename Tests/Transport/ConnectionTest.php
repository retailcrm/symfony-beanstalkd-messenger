<?php

namespace RetailCrm\Messenger\Beanstalkd\Tests\Transport;

use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Job;
use PHPUnit\Framework\TestCase;
use RetailCrm\Messenger\Beanstalkd\Transport\Connection;
use InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;

class ConnectionTest extends TestCase
{
    private const TEST_OPTIONS = [
        'transport_name' => 'test',
        'tube_name' => 'test_tube',
        'timeout' => 10,
        'ttr' => 300,
        'not_send_if_exists' => false,
    ];

    public function testFromDsn(): void
    {
        $connection = Connection::fromDsn('beanstalkd://127.0.0.1:11300', static::TEST_OPTIONS);

        static::assertEquals('test_tube', $connection->getTube());
        static::assertEquals(300, $connection->getTtr());
        static::assertEquals(10, $connection->getTimeout());
        static::assertEquals(false, $connection->isNotSendIfExists());
    }

    public function testFromDsnFailure(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Connection::fromDsn(
            'beanstalkd://127.0.0.1:11300',
            array_merge(static::TEST_OPTIONS, ['unsupported' => true])
        );
    }

    public function testGet(): void
    {
        $job = new Job('1', 'Test job');
        $client = $this->createMock(PheanstalkInterface::class);
        $client
            ->expects(static::once())
            ->method('watchOnly')
            ->with(static::TEST_OPTIONS['tube_name'])
            ->willReturn($client);
        $client
            ->expects(static::once())
            ->method('reserveWithTimeout')
            ->with(static::TEST_OPTIONS['timeout'])
            ->willReturn($job);

        $connection = new Connection(static::TEST_OPTIONS, $client);
        $result = $connection->get();

        static::assertEquals($job, $result);
    }

    public function testGetFailure(): void
    {
        $client = $this->createMock(PheanstalkInterface::class);
        $client
            ->expects(static::once())
            ->method('watchOnly')
            ->with(static::TEST_OPTIONS['tube_name'])
            ->willReturn($client);
        $client
            ->expects(static::once())
            ->method('reserveWithTimeout')
            ->with(static::TEST_OPTIONS['timeout'])
            ->willThrowException(new TransportException());

        $this->expectException(TransportException::class);

        $connection = new Connection(static::TEST_OPTIONS, $client);
        $connection->get();
    }

    public function testAck(): void
    {
        $job = new Job('1', 'Test job');
        $client = $this->createMock(PheanstalkInterface::class);
        $client
            ->expects(static::once())
            ->method('useTube')
            ->with(static::TEST_OPTIONS['tube_name'])
            ->willReturn($client);
        $client
            ->expects(static::once())
            ->method('delete')
            ->with($job);

        $connection = new Connection(static::TEST_OPTIONS, $client);
        $connection->ack($job);
    }

    public function testAckFailure(): void
    {
        $job = new Job('1', 'Test job');
        $client = $this->createMock(PheanstalkInterface::class);
        $client
            ->expects(static::once())
            ->method('useTube')
            ->with(static::TEST_OPTIONS['tube_name'])
            ->willReturn($client);
        $client
            ->expects(static::once())
            ->method('delete')
            ->with($job)
            ->willThrowException(new TransportException());

        $this->expectException(TransportException::class);

        $connection = new Connection(static::TEST_OPTIONS, $client);
        $connection->ack($job);
    }

    public function testReject(): void
    {
        $job = new Job('1', 'Test job');
        $client = $this->createMock(PheanstalkInterface::class);
        $client
            ->expects(static::once())
            ->method('useTube')
            ->with(static::TEST_OPTIONS['tube_name'])
            ->willReturn($client);
        $client
            ->expects(static::once())
            ->method('delete')
            ->with($job);

        $connection = new Connection(static::TEST_OPTIONS, $client);
        $connection->reject($job);
    }

    public function testRejectFailure(): void
    {
        $job = new Job('1', 'Test job');
        $client = $this->createMock(PheanstalkInterface::class);
        $client
            ->expects(static::once())
            ->method('useTube')
            ->with(static::TEST_OPTIONS['tube_name'])
            ->willReturn($client);
        $client
            ->expects(static::once())
            ->method('delete')
            ->with($job)
            ->willThrowException(new TransportException());

        $this->expectException(TransportException::class);

        $connection = new Connection(static::TEST_OPTIONS, $client);
        $connection->reject($job);
    }

    public function testSend(): void
    {
        $message = 'Test message';
        $delay = 10;

        $client = $this->createMock(PheanstalkInterface::class);
        $client
            ->expects(static::once())
            ->method('useTube')
            ->with(static::TEST_OPTIONS['tube_name'])
            ->willReturn($client);
        $client
            ->expects(static::once())
            ->method('put')
            ->with($message, PheanstalkInterface::DEFAULT_PRIORITY, $delay, static::TEST_OPTIONS['ttr']);

        $connection = new Connection(static::TEST_OPTIONS, $client);
        $connection->send($message, $delay);
    }

    public function testSendFailure(): void
    {
        $message = 'Test message';
        $delay = 10;

        $client = $this->createMock(PheanstalkInterface::class);
        $client
            ->expects(static::once())
            ->method('useTube')
            ->with(static::TEST_OPTIONS['tube_name'])
            ->willReturn($client);
        $client
            ->expects(static::once())
            ->method('put')
            ->with($message, PheanstalkInterface::DEFAULT_PRIORITY, $delay, static::TEST_OPTIONS['ttr'])
            ->willThrowException(new TransportException());

        $this->expectException(TransportException::class);

        $connection = new Connection(static::TEST_OPTIONS, $client);
        $connection->send($message, $delay);
    }

    public function testSerializeJob(): void
    {
        $client = $this->createMock(PheanstalkInterface::class);

        $connection = new Connection(static::TEST_OPTIONS, $client);
        $job = $connection->serializeJob('body', []);

        static::assertEquals('{"headers":[],"body":"body"}', $job);
    }

    public function testDeserializeJob(): void
    {
        $client = $this->createMock(PheanstalkInterface::class);

        $connection = new Connection(static::TEST_OPTIONS, $client);
        $job = $connection->deserializeJob('{"headers":[],"body":"body"}');

        static::assertEquals(['body' => 'body', 'headers' => []], $job);
    }
}
