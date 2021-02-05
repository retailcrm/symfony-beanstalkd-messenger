[![Build Status](https://github.com/retailcrm/symfony-beanstalkd-messenger/workflows/ci/badge.svg)](https://github.com/retailcrm/symfony-beanstalkd-messenger/actions)
[![Coverage](https://img.shields.io/codecov/c/gh/retailcrm/symfony-beanstalkd-messenger/master.svg?logo=codecov)](https://codecov.io/gh/retailcrm/symfony-beanstalkd-messenger)
[![Latest stable](https://img.shields.io/packagist/v/retailcrm/symfony-beanstalkd-messenger.svg)](https://packagist.org/packages/retailcrm/symfony-beanstalkd-messenger)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/retailcrm/symfony-beanstalkd-messenger.svg)](https://packagist.org/packages/retailcrm/symfony-beanstalkd-messenger)

# Symfony beanstalkd messenger
Beanstalkd transport for [symfony messenger](https://symfony.com/doc/current/messenger.html)

## Installation

`composer require retailcrm/symfony-beanstalkd-messenger`

## Usage

* in the `.env` config file add the connection credentials:

`MESSENGER_TRANSPORT_DSN=beanstalkd://localhost:11300`

* create your messages and message handlers ([about messages](https://symfony.com/doc/current/messenger.html#creating-a-message-handler))

* configure messenger in `config/packages/messenger.yml`, for example:

```yaml
framework:
    messenger:
        transports:
            async:
                dsn: "%env(MESSENGER_TRANSPORT_DSN)%"
                options:
                    queue_name: async
        routing:
            'App\Message\MyMessage': async
```

* add transport factory in `config/services.yml`

```yaml
services:
# ...
    RetailCrm\Messenger\Beanstalkd\Transport\BeanstalkTransportFactory:
        tags: [messenger.transport_factory]
```

## Allowed transport options

* `tube_name` - tube name in beanstalkd

* `timeout` - timeout for receiving jobs from tube. Default - 0

* `ttr` - ttr value for jobs. Default - 60

* `not_send_if_exists` - do not send a job to the queue only if such a job is already exist. Default - `true`

All options are optional, if `tube_name` not specified will be used default queue `default`
