<?php

/*
 * Unified push notification services abstraction (http://github.com/juliangut/tify).
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/tify
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Tify\Tests\Adapter;

use Jgut\Tify\Adapter\Fcm\DefaultFactory;
use Jgut\Tify\Adapter\FcmAdapter;
use Jgut\Tify\Message;
use Jgut\Tify\Notification;
use Jgut\Tify\Receiver\FcmReceiver;
use Jgut\Tify\Result;
use Psr\Log\LoggerInterface;
use ZendService\Google\Exception\RuntimeException;
use ZendService\Google\Gcm\Client;
use ZendService\Google\Gcm\Message as ServiceMessage;
use ZendService\Google\Gcm\Response;

/**
 * FCM service adapter tests.
 */
class FcmAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FcmAdapter
     */
    protected $adapter;

    public function setUp()
    {
        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();
        $response->expects(self::any())
            ->method('getResults')
            ->will(self::returnValue(['aaa' => [], 'bbb' => ['error' => FcmAdapter::RESPONSE_NOT_REGISTERED]]));

        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $client->expects(self::any())
            ->method('send')
            ->will(self::returnValue($response));

        $message = $this->getMockBuilder(ServiceMessage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $message->expects(self::any())
            ->method('getRegistrationIds')
            ->will(self::returnValue(['aaa', 'bbb', 'ccc']));
        $message->expects(self::any())
            ->method('toJson')
            ->will(self::returnValue(
                json_encode(['registration_ids' => ['aaa', 'bbb', 'ccc'], 'priority' => 'normal'])
            ));

        $factory = $this->getMockBuilder(DefaultFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $factory->expects(self::any())
            ->method('buildPushClient')
            ->will(self::returnValue($client));
        $factory->expects(self::any())
            ->method('buildPushMessage')
            ->will(self::returnValue($message));

        $this->adapter = new FcmAdapter([FcmAdapter::PARAMETER_API_KEY => 'aaa'], true, $factory);
    }

    /**
     * @expectedException \Jgut\Tify\Exception\AdapterException
     * @expectedExceptionMessageRegExp /^Missing parameters on/
     */
    public function testMissingApiKey()
    {
        new FcmAdapter;
    }

    /**
     * @expectedException \Jgut\Tify\Exception\AdapterException
     * @expectedExceptionMessage Invalid parameter provided
     */
    public function testInvalidParameter()
    {
        new FcmAdapter(['invalid' => 'value']);
    }

    public function testErrorLogging()
    {
        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $logger->expects(self::exactly(2))
            ->method('warning')
            ->with($this->matchesRegularExpression('/^Error ".+" sending push notification/'));
        /* @var LoggerInterface $logger */

        $this->adapter->setLogger($logger);

        $message = $this->getMockBuilder(Message::class)
            ->disableOriginalConstructor()
            ->getMock();
        /* @var Message $message */

        $receiver = new FcmReceiver('abcdefghijklmnopqrstuvwxyz1234567890');

        $notification = new Notification($message, [$receiver]);
        /* @var Result[] $results */
        $this->adapter->push($notification);
    }

    public function testSend()
    {
        $message = $this->getMockBuilder(Message::class)
            ->disableOriginalConstructor()
            ->getMock();
        /* @var Message $message */

        $receiver = new FcmReceiver('abcdefghijklmnopqrstuvwxyz1234567890');

        $notification = new Notification($message, [$receiver]);
        /* @var Result[] $results */
        $results = $this->adapter->push($notification);

        self::assertCount(3, $results);

        self::assertEquals('aaa', $results[0]->getToken());
        self::assertTrue($results[0]->isSuccess());

        self::assertEquals('bbb', $results[1]->getToken());
        self::assertTrue($results[1]->isError());

        self::assertEquals('ccc', $results[2]->getToken());
        self::assertTrue($results[2]->isError());
    }

    public function testExceptionErrorCode()
    {
        $reflection = new \ReflectionClass(get_class($this->adapter));
        $method = $reflection->getMethod('getErrorCodeFromException');
        $method->setAccessible(true);

        $exception = new RuntimeException('500 Internal Server Error');
        self::assertEquals(FcmAdapter::RESPONSE_INTERNAL_SERVER_ERROR, $method->invoke($this->adapter, $exception));

        $exception = new RuntimeException('503 Server Unavailable; Retry-After 200');
        self::assertEquals(FcmAdapter::RESPONSE_SERVER_UNAVAILABLE, $method->invoke($this->adapter, $exception));

        $exception = new RuntimeException('401 Forbidden; Authentication Error');
        self::assertEquals(FcmAdapter::RESPONSE_AUTHENTICATION_ERROR, $method->invoke($this->adapter, $exception));

        $exception = new RuntimeException('400 Bad Request; invalid message');
        self::assertEquals(FcmAdapter::RESPONSE_INVALID_MESSAGE, $method->invoke($this->adapter, $exception));

        $exception = new RuntimeException('Response body did not contain a valid JSON response');
        self::assertEquals(FcmAdapter::RESPONSE_BADLY_FORMATTED_RESPONSE, $method->invoke($this->adapter, $exception));

        $exception = new RuntimeException('Unknown');
        self::assertEquals(FcmAdapter::RESPONSE_UNKNOWN_ERROR, $method->invoke($this->adapter, $exception));
    }
}
