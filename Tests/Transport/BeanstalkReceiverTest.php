<?php

namespace RetailCrm\Messenger\Beanstalkd\Tests\Transport;

use Pheanstalk\Job;
use PHPUnit\Framework\TestCase;
use RetailCrm\Messenger\Beanstalkd\Transport\BeanstalkReceivedStamp;
use RetailCrm\Messenger\Beanstalkd\Transport\BeanstalkReceiver;
use RetailCrm\Messenger\Beanstalkd\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use LogicException;

class BeanstalkReceiverTest extends TestCase
{
    private const TEST_TUBE = 'test';

    private $connection;
    private $serializer;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getTube')->willReturn(static::TEST_TUBE);

        $this->serializer = $this->createMock(SerializerInterface::class);
    }

    public function testGetEmptyData(): void
    {
        $this->connection->expects(static::once())->method('get')->willReturn(null);

        $receiver = new BeanstalkReceiver($this->connection);

        static::assertEmpty($receiver->get());
    }

    public function testGet(): void
    {
        $message = ['body' => 'Test message', 'headers' => []];

        $this->connection->expects(static::once())->method('get')->willReturn(
            new Job('1', json_encode($message))
        );
        $this->connection->expects(static::once())->method('deserializeJob')->willReturn($message);

        $this->serializer
            ->expects(static::once())
            ->method('decode')
            ->with($message)
            ->willReturn(
                new Envelope(new class {
                })
            );

        $receiver = new BeanstalkReceiver($this->connection, $this->serializer);
        $result = $receiver->get();

        static::assertNotEmpty($result);
        static::assertInstanceOf(Envelope::class, $result[0]);

        /** @var BeanstalkReceivedStamp $stamp */
        $stamp = $result[0]->last(BeanstalkReceivedStamp::class);

        static::assertInstanceOf(BeanstalkReceivedStamp::class, $stamp);
        static::assertEquals(static::TEST_TUBE, $stamp->getTube());
        static::assertEquals('1', $stamp->getJob()->getId());
    }

    public function testGetFailure(): void
    {
        $message = ['body' => 'Test message', 'headers' => []];

        $this->connection->expects(static::once())->method('get')->willReturn(
            new Job('1', json_encode($message))
        );
        $this->connection->expects(static::once())->method('deserializeJob')->willReturn($message);

        $this->serializer->method('decode')->willThrowException(new MessageDecodingFailedException());

        $this->expectException(MessageDecodingFailedException::class);

        $receiver = new BeanstalkReceiver($this->connection, $this->serializer);
        $receiver->get();
    }

    public function testAck(): void
    {
        $message = ['body' => 'Test message', 'headers' => []];

        $envelope = new Envelope(new class {
        }, [new BeanstalkReceivedStamp(static::TEST_TUBE, new Job('1', json_encode($message)))]);

        $this->connection->expects(static::once())->method('ack');

        $receiver = new BeanstalkReceiver($this->connection);
        $receiver->ack($envelope);
    }

    public function testAckFailure(): void
    {
        $envelope = new Envelope(new class {
        }, []);

        $this->connection->expects(static::never())->method('ack');

        $this->expectException(LogicException::class);

        $receiver = new BeanstalkReceiver($this->connection);
        $receiver->ack($envelope);
    }
}
