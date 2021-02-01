<?php

namespace RetailCrm\Messenger\Beanstalkd\Tests\Transport;

use PHPUnit\Framework\TestCase;
use RetailCrm\Messenger\Beanstalkd\Transport\Connection;
use InvalidArgumentException;

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

        $this->expectException(InvalidArgumentException::class);

        Connection::fromDsn(
            'beanstalkd://127.0.0.1:11300',
            array_merge(static::TEST_OPTIONS, ['unsupported' => true])
        );
    }
}
