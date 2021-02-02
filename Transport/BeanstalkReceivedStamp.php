<?php

namespace RetailCrm\Messenger\Beanstalkd\Transport;

use Pheanstalk\Contract\JobIdInterface;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * Class BeanstalkReceivedStamp
 *
 * @package RetailCrm\Messenger\Beanstalkd\Transport
 */
class BeanstalkReceivedStamp implements NonSendableStampInterface
{
    private $tube;
    private $job;

    public function __construct(string $tube, JobIdInterface $job)
    {
        $this->tube = $tube;
        $this->job = $job;
    }

    public function getTube(): string
    {
        return $this->tube;
    }

    public function getJob(): JobIdInterface
    {
        return $this->job;
    }
}
