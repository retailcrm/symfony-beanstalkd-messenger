<?php

namespace RetailCrm\Messenger\Beanstalkd\Tests\Transport;

use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Job;
use Pheanstalk\Response\ArrayResponse;
use PHPUnit\Framework\TestCase;
use RetailCrm\Messenger\Beanstalkd\Storage\LockStorageInterface;
use RetailCrm\Messenger\Beanstalkd\Transport\BeanstalkSender;
use RetailCrm\Messenger\Beanstalkd\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class BeanstalkSenderTest extends TestCase
{
    private $connection;
    private $serializer;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
    }

    public function testSendWithoutCheck(): void
    {
        $message = ['body' => 'Test message', 'headers' => []];
        $envelope = new Envelope(new class {
        });

        $this->serializer->expects(static::once())->method('encode')->with($envelope)->willReturn($message);
        $this->connection->expects(static::once())->method('serializeJob')->willReturn(json_encode($message));
        $this->connection->expects(static::once())->method('isNotSendIfExists')->willReturn(false);
        $this->connection->expects(static::once())->method('send');

        $sender = new BeanstalkSender($this->connection, $this->serializer);
        $sender->send($envelope);
    }

    public function testSendWithCheckExist(): void
    {
        $message = ['body' => 'Test message', 'headers' => []];
        $envelope = new Envelope(new class {
        });

        $lockStorage = $this->createMock(LockStorageInterface::class);
        $lockStorage->expects(static::once())
            ->method('setLock')
            ->willReturn(false);

        $this->serializer->expects(static::once())->method('encode')->with($envelope)->willReturn($message);
        $this->connection->expects(static::once())->method('serializeJob')->willReturn(json_encode($message));
        $this->connection->expects(static::once())->method('isNotSendIfExists')->willReturn(true);
        $this->connection->expects(static::never())->method('send');
        $this->connection->method('getLockStorage')->willReturn($lockStorage);

        $sender = new BeanstalkSender($this->connection, $this->serializer);
        $sender->send($envelope);
    }

    public function testSendWithCheckNotExist(): void
    {
        $message = ['body' => 'Test message', 'headers' => []];
        $envelope = new Envelope(new class {
        });

        $lockStorage = $this->createMock(LockStorageInterface::class);
        $lockStorage->expects(static::never())
            ->method('setLock');

        $this->serializer->expects(static::once())->method('encode')->with($envelope)->willReturn($message);
        $this->connection->expects(static::once())->method('serializeJob')->willReturn(json_encode($message));
        $this->connection->expects(static::once())->method('isNotSendIfExists')->willReturn(false);
        $this->connection->expects(static::once())->method('send');
        $this->connection->method('getLockStorage')->willReturn($lockStorage);

        $sender = new BeanstalkSender($this->connection, $this->serializer);
        $sender->send($envelope);
    }
}
