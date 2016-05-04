<?php
/**
 * Push notification services abstraction (http://github.com/juliangut/tify)
 *
 * @link https://github.com/juliangut/tify for the canonical source repository
 *
 * @license https://github.com/juliangut/tify/blob/master/LICENSE
 */

namespace Jgut\Tify\Adapter;

use Jgut\Tify\Exception\NotificationException;
use Jgut\Tify\Exception\AdapterException;
use Jgut\Tify\Notification;
use Jgut\Tify\Recipient\AbstractRecipient;
use Jgut\Tify\Recipient\ApnsRecipient;
use Jgut\Tify\Result;
use Jgut\Tify\Adapter\Client\ApnsClientBuilder;
use Jgut\Tify\Adapter\Message\ApnsMessageBuilder;
use ZendService\Apple\Exception\RuntimeException as ApnsRuntimeException;

/**
 * Class ApnsAdapter
 */
class ApnsAdapter extends AbstractAdapter implements SendAdapterInterface, FeedbackAdapterInterface
{
    const RESULT_OK = 0;

    /**
     * Status codes mapping.
     *
     * @var array
     */
    private static $statusCodes = [
        0 => 'OK',
        1 => 'Processing Error',
        2 => 'Missing Recipient Token',
        3 => 'Missing Topic',
        4 => 'Missing Payload',
        5 => 'Invalid Token Size',
        6 => 'Invalid Topic Size',
        7 => 'Invalid Payload Size',
        8 => 'Invalid Token',
        10 => 'Shutdown',
        255 => 'Unknown Error',
    ];

    /**
     * @var \ZendService\Apple\Apns\Client\Message
     */
    protected $pushClient;

    /**
     * @var \ZendService\Apple\Apns\Client\Feedback
     */
    protected $feedbackClient;

    /**
     * {@inheritdoc}
     *
     * @throws \Jgut\Tify\Exception\AdapterException
     */
    public function __construct(array $parameters = [], $sandbox = false)
    {
        parent::__construct($parameters, $sandbox);

        $certificatePath = $this->getParameter('certificate');

        if ($certificatePath === null || !file_exists($certificatePath) || !is_readable($certificatePath)) {
            throw new AdapterException(
                sprintf('Certificate file "%s" does not exist or is not readable', $certificatePath)
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     * @throws \Jgut\Tify\Exception\AdapterException
     * @throws \RuntimeException
     */
    public function send(Notification $notification)
    {
        $client = $this->getPushClient();

        /* @var \ZendService\Apple\Apns\Message $message */
        foreach ($this->getPushMessages($notification) as $message) {
            $result = new Result($message->getToken(), new \DateTime('now', new \DateTimeZone('UTC')));

            try {
                $pushResponse = $client->send($message);

                if ($pushResponse->getCode() !== static::RESULT_OK) {
                    $result->setStatus(Result::STATUS_ERROR);
                    $result->setStatusMessage(self::$statusCodes[$pushResponse->getCode()]);
                }
            } catch (ApnsRuntimeException $exception) {
                $result->setStatus(Result::STATUS_ERROR);
                $result->setStatusMessage($exception->getMessage());
            }

            $notification->addResult($result);
        }

        $client->close();
        $this->pushClient = null;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Jgut\Tify\Exception\NotificationException
     */
    public function feedback()
    {
        $client = $this->getFeedbackClient();

        try {
            $feedbackResponse = $client->feedback();
        } catch (ApnsRuntimeException $exception) {
            throw new NotificationException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $responses = [];

        /* @var \ZendService\Apple\Apns\Response\Feedback $response */
        foreach ($feedbackResponse as $response) {
            $responses[] = $response->getToken();
        }

        $client->close();
        $this->feedbackClient = null;

        return $responses;
    }

    /**
     * Get opened ServiceClient
     *
     * @throws \Jgut\Tify\Exception\AdapterException
     *
     * @return \ZendService\Apple\Apns\Client\Message
     */
    protected function getPushClient()
    {
        if ($this->pushClient === null) {
            $this->pushClient = ApnsClientBuilder::buildPush(
                $this->getParameter('certificate'),
                $this->getParameter('pass_phrase'),
                $this->sandbox
            );
        }

        return $this->pushClient;
    }

    /**
     * Get opened ServiceFeedbackClient
     *
     * @return \ZendService\Apple\Apns\Client\Feedback
     */
    protected function getFeedbackClient()
    {
        if ($this->feedbackClient === null) {
            $this->feedbackClient = ApnsClientBuilder::buildFeedback(
                $this->getParameter('certificate'),
                $this->getParameter('pass_phrase'),
                $this->sandbox
            );
        }

        return $this->feedbackClient;
    }

    /**
     * Get push service formatted messages.
     *
     * @param \Jgut\Tify\Notification $notification
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @return \ZendService\Apple\Apns\Message
     */
    protected function getPushMessages(Notification $notification)
    {
        /* @var \Jgut\Tify\Recipient\ApnsRecipient[] $recipients */
        $recipients = array_filter(
            $notification->getRecipients(),
            function (AbstractRecipient $recipient) {
                return $recipient instanceof ApnsRecipient;
            }
        );

        foreach ($recipients as $recipient) {
            yield ApnsMessageBuilder::build($recipient, $notification);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefinedParameters()
    {
        return ['certificate', 'pass_phrase'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultParameters()
    {
        return [
            'pass_phrase' => null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getRequiredParameters()
    {
        return ['certificate'];
    }
}