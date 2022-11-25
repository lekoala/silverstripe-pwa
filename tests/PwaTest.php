<?php

namespace LeKoala\SsPwa\Test;

use LeKoala\SsPwa\PushSubscription;
use Minishlink\WebPush\Subscription;
use SilverStripe\Dev\SapphireTest;

class PwaTest extends SapphireTest
{

    /**
     * Defines the fixture file to use for this test class
     * @var string
     */
    protected static $fixture_file = 'PwaTest.yml';

    public function testPushSubscription()
    {
        $data = [
            "endpoint" => "https://fcm.googleapis.com/fcm/send/dOG8e0uSfnA:APA91bHpydJQ-3nE0tk....",
            "expirationTime" => null,
            "keys" => [
                "auth" => "w6_GuDO7Jf-qEKV...",
                "p256dh" => "BK1xht_jA4BFaIpOFyfGCuMLbCtdY8G7v4uZ5jUzsLO_ZgAwI....",
            ]
        ];
        $sub = PushSubscription::createNew($data);

        $this->assertInstanceOf(PushSubscription::class, $sub);
        $this->assertEquals($data['endpoint'], $sub->Endpoint);

        $payload = ['data' => 'demo'];

        $pushSub = $sub->createSubscription($payload);
        $this->assertInstanceOf(Subscription::class, $pushSub);

        $byEndpoint = PushSubscription::getByEndpoint($sub->Endpoint);
        $this->assertEquals($byEndpoint->ID, $sub->ID);

        PushSubscription::deleteEndpoint($sub->Endpoint);
        $this->assertTrue(PushSubscription::get()->count() === 0);
    }
}
