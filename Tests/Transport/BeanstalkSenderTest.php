<?php

namespace RetailCrm\Messenger\Beanstalkd\Tests\Transport;

use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Job;
use Pheanstalk\Response\ArrayResponse;
use PHPUnit\Framework\TestCase;
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
        $message2 = ['body' => 'Test message 2', 'headers' => []];
        $envelope = new Envelope(new class {
        });

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects(static::once())
            ->method('statsTube')
            ->willReturn(new ArrayResponse('test', ['current-jobs-ready' => 2]));
        $client
            ->method('reserveWithTimeout')
            ->willReturn(new Job('1', json_encode($message)), new Job('2', json_encode($message2)));

        $this->serializer->expects(static::once())->method('encode')->with($envelope)->willReturn($message);
        $this->connection->expects(static::once())->method('serializeJob')->willReturn(json_encode($message));
        $this->connection->expects(static::once())->method('isNotSendIfExists')->willReturn(true);
        $this->connection->expects(static::never())->method('send');
        $this->connection->method('getClient')->willReturn($client);

        $sender = new BeanstalkSender($this->connection, $this->serializer);
        $sender->send($envelope);
    }

    public function testSendWithCheckNotExist(): void
    {
        $message = ['body' => 'Test message', 'headers' => []];
        $message2 = ['body' => 'Test message 2', 'headers' => []];
        $envelope = new Envelope(new class {
        });

        $client = $this->createMock(PheanstalkInterface::class);
        $client->expects(static::once())
            ->method('statsTube')
            ->willReturn(new ArrayResponse('test', ['current-jobs-ready' => 1]));
        $client
            ->method('reserveWithTimeout')
            ->willReturn(new Job('1', json_encode($message)));

        $this->serializer->expects(static::once())->method('encode')->with($envelope)->willReturn($message);
        $this->connection->expects(static::once())->method('serializeJob')->willReturn(json_encode($message2));
        $this->connection->expects(static::once())->method('isNotSendIfExists')->willReturn(true);
        $this->connection->expects(static::once())->method('send');
        $this->connection->method('getClient')->willReturn($client);

        $sender = new BeanstalkSender($this->connection, $this->serializer);
        $sender->send($envelope);
    }
}
