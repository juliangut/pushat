<?php
/**
 * Push notification services abstraction (http://github.com/juliangut/tify)
 *
 * @link https://github.com/juliangut/tify for the canonical source repository
 *
 * @license https://github.com/juliangut/tify/blob/master/LICENSE
 */

namespace Jgut\Tify\Tests\Adapter\Message;

use Jgut\Tify\Message;
use Jgut\Tify\Notification;
use Jgut\Tify\Recipient\ApnsRecipient;
use Jgut\Tify\Adapter\ApnsAdapter;
use Jgut\Tify\Adapter\Message\ApnsMessageBuilder;
use ZendService\Apple\Apns\Message as ApnsMessage;

/**
 * Apns message builder tests.
 */
class ApnsBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testPushClient()
    {
        $recipient = new ApnsRecipient(
            '9a4ecb987ef59c88b12035278b86f26d448835939a4ecb987ef59c88b1203527'
        );

        $service = new ApnsAdapter(
            ['certificate' => dirname(dirname(dirname(__DIR__))) . '/files/apns_certificate.pem']
        );

        $message = new Message(['title' => 'title']);

        $notification = new Notification($message, [], ['expire' => 600, 'badge' => 1]);

        $client = ApnsMessageBuilder::build($recipient, $notification);
        self::assertInstanceOf(ApnsMessage::class, $client);
    }
}