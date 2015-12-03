<?php
/**
 * Push notification services abstraction (http://github.com/juliangut/pushat)
 *
 * @link https://github.com/juliangut/pushat for the canonical source repository
 *
 * @license https://github.com/juliangut/pushat/blob/master/LICENSE
 */

namespace Jgut\Pushat\Notification;

use Jgut\Pushat\Service\AbstractService;
use Jgut\Pushat\Device\AbstractDevice;
use Jgut\Pushat\Message\AbstractMessage;
use Jgut\Pushat\OptionsTrait;

abstract class AbstractNotification
{
    const STATUS_PENDING = 0;
    const STATUS_PUSHED = 1;

    use OptionsTrait;

    /**
     * Default notification options.
     *
     * @var array
     */
    protected $defaultOptions = [];

    /**
     * @var \Jgut\Pushat\Service\AbstractService
     */
    protected $service;

    /**
     * @var \Jgut\Pushat\Message\AbstractMessage
     */
    protected $message;

    /**
     * @var \Jgut\Pushat\Device\AbstractDevice[]
     */
    protected $devices = [];

    /**
     * @var int
     */
    protected $status = self::STATUS_PENDING;

    /**
     * @var \DateTime
     */
    protected $pushTime;

    /**
     * Push results.
     *
     * @var array
     */
    protected $result = [];

    /**
     * @param \Jgut\Pushat\Service\AbstractService $service
     * @param \Jgut\Pushat\Message\AbstractMessage $message
     * @param \Jgut\Pushat\Device\AbstractDevice[] $devices
     * @param array                                $options
     */
    public function __construct(
        AbstractService $service,
        AbstractMessage $message,
        array $devices = [],
        array $options = []
    ) {
        $this->service = $service;
        $this->message = $message;

        foreach ($devices as $device) {
            $this->addDevice($device);
        }

        $this->options = array_merge($this->defaultOptions, $options);
    }

    /**
     * Get service.
     *
     * @return \Jgut\Pushat\Service\AbstractService
     */
    final public function getService()
    {
        return $this->service;
    }

    /**
     * Set service.
     *
     * @param \Jgut\Pusha\Service\AbstractService $service
     */
    abstract public function setService(AbstractService $service);

    /**
     * Get message.
     *
     * @return \Jgut\Pushat\Message\AbstractMessage
     */
    final public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set message.
     *
     * @param \Jgut\Pushat\Message\AbstractMessage $message
     */
    abstract public function setMessage(AbstractMessage $message);

    /**
     * Retrieve list of devices.
     *
     * @return \Jgut\Pushat\Device\AbstractDevice[]
     */
    final public function getDevices()
    {
        return $this->devices;
    }

    /**
     * Add device.
     *
     * @param \Jgut\Pushat\Device\AbstractDevice $device
     */
    abstract public function addDevice(AbstractDevice $device);

    /**
     * Retrieve notification status.
     *
     * @return int
     */
    final public function getStatus()
    {
        return $this->status;
    }

    /**
     * Check if notification status is pushed.
     *
     * @return bool
     */
    final public function isPushed()
    {
        return $this->status === static::STATUS_PUSHED;
    }

    /**
     * Set notification pushed.
     *
     * @param array $result
     */
    final public function setPushed(array $result = [])
    {
        $this->status = static::STATUS_PUSHED;
        $this->pushTime = new \DateTime;
        $this->result = $result;
    }

    /**
     * Set notification pending (not pushed).
     *
     * @param array $result
     */
    final public function setPending()
    {
        $this->status = static::STATUS_PENDING;
        $this->pushTime = null;
        $this->result = [];
    }

    /**
     * Retrieve push time.
     *
     * @return \DateTime
     */
    final public function getPushTime()
    {
        return $this->pushTime;
    }

    /**
     * Retrieve push result.
     *
     * @return array
     */
    final public function getResult()
    {
        return $this->result;
    }

    /**
     * Retrieve devices tokens list.
     *
     * @return array
     */
    final public function getTokens()
    {
        $tokens = [];

        foreach ($this->devices as $device) {
            $tokens[] = $device->getToken();
        }

        return array_unique(array_filter($tokens));
    }
}
