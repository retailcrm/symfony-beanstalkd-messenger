<?php

namespace RetailCrm\Messenger\Beanstalkd\Tests\Transport;

use PHPUnit\Framework\TestCase;
use RetailCrm\Messenger\Beanstalkd\Transport\BeanstalkTransport;
use RetailCrm\Messenger\Beanstalkd\Transport\BeanstalkTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class TransportFactoryTest extends TestCase
{
    private const DSN = 'beanstalkd://127.0.0.1:11300';

    private $factory;

    protected function setUp(): void
    {
        $this->factory = new BeanstalkTransportFactory();
    }

    public function testCreateTransport(): void
    {
        $transport = $this->factory->createTransport(
            static::DSN,
            [],
            $this->createMock(SerializerInterface::class)
        );

        static::assertInstanceOf(BeanstalkTransport::class, $transport);
    }

    public function testSupports(): void
    {
        static::assertTrue($this->factory->supports(static::DSN, []));
        static::assertFalse($this->factory->supports('invalid dsn', []));
    }
}
