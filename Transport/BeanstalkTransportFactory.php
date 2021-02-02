<?php

namespace RetailCrm\Messenger\Beanstalkd\Transport;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Class BeanstalkTransportFactory
 *
 * @package RetailCrm\Messenger\Beanstalkd\Transport
 */
class BeanstalkTransportFactory implements TransportFactoryInterface
{
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new BeanstalkTransport(Connection::fromDsn($dsn, $options), $serializer);
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'beanstalkd://');
    }
}
