<?php

namespace LeKoala\SsPwa;

use Exception;
use SilverStripe\ORM\DataList;
use Minishlink\WebPush\WebPush;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use LeKoala\CmsActions\CustomAction;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\MessageSentReport;

/**
 * Save push subscription
 *
 * Subscription details is saved in a raw format as json text in Subscription field
 *
 * It looks like this:
 * {
 * "endpoint":"https:\/\/fcm.googleapis.com\/fcm\/...",
 * "expirationTime":null,
 * "keys":{"p256dh":"BJXOZk_g9pi7goy_I1SHctH_9NRV6fYfmQiEQ-piLe9j...","auth":"egZhXUK..."}
 * }
 *
 * @link https://developer.mozilla.org/en-US/docs/Web/API/PushManager/subscribe
 * @link https://web.dev/push-notifications-subscribing-a-user/
 * @property string $Endpoint
 * @property string $Subscription
 * @property string $Platform
 * @property string $LastCalled
 * @property bool $LastCallError
 * @property string $LastCallErrorReason
 * @property int $MemberID
 * @method \SilverStripe\Security\Member Member()
 */
class PushSubscription extends DataObject
{
    const WEBPUSH  = "webpush";
    const FIREBASE = "firebase";
    const APN = "apn";

    /**
     * @var string
     */
    private static $table_name = 'PushSubscription';

    /**
     * @var array<string,string>
     */
    private static $db = [
        'Endpoint' => 'Varchar(255)',
        'Subscription' => 'Text',
        'Platform' => "Enum(',webpush,firebase,apn')",
        'LastCalled' => 'Datetime',
        'LastCallError' => 'Boolean',
        'LastCallErrorReason' => 'Text',
    ];

    /**
     * @var array<string,string>
     */
    private static $has_one = [
        'Member' => Member::class
    ];

    /**
     * @var array<string>
     */
    private static $summary_fields = [
        'Created', 'Member.Title'
    ];

    /**
     * @var array<string,mixed>
     */
    private static $indexes = [
        'Endpoint' => true,
        'LastCalled' => true,
    ];

    /**
     * @return string
     */
    public static function getPublicKey()
    {
        if (Environment::getEnv('PUSH_PUBLIC_KEY')) {
            return Environment::getEnv('PUSH_PUBLIC_KEY');
        }
        return self::config()->push_public_key;
    }

    /**
     * @return string
     */
    public static function getPrivateKey()
    {
        if (Environment::getEnv('PUSH_PRIVATE_KEY')) {
            return Environment::getEnv('PUSH_PRIVATE_KEY');
        }
        return self::config()->push_private_key;
    }

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    /**
     * @return FieldList
     */
    public function getCMSActions()
    {
        $actions = parent::getCMSActions();
        if (class_exists(CustomAction::class)) {
            $actions->push(new CustomAction("doTest", "Test notification"));
        }
        return $actions;
    }

    /**
     * @return WebPush
     */
    public static function getWebpushHandler()
    {
        $publicKey = self::getPublicKey();
        $privateKey = self::getPrivateKey();
        if (!$publicKey || !$privateKey) {
            throw new Exception("Missing public or private key");
        }
        $auth = [
            'VAPID' => [
                'subject' => Director::absoluteBaseURL(), // can be a mailto: or your website address
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];
        $defaultOptions = [];
        $timeout = 30;
        $clientOptions = [];
        // This fixes ca cert issues if server is not configured properly
        if (ini_get('curl.cainfo') === false || ini_get('curl.cainfo') === '') {
            $clientOptions['verify'] = \Composer\CaBundle\CaBundle::getBundledCaBundlePath();
        }
        $webPush = new WebPush($auth, $defaultOptions, $timeout, $clientOptions);
        $webPush->setReuseVAPIDHeaders(true);

        return $webPush;
    }

    public function doTest(): string
    {
        $payload = "Test at " . date('d/m/Y H:i:s');
        $report = $this->sendMessage($payload);

        if ($report->isSubscriptionExpired()) {
            throw new Exception('Error: subscription is expired');
        }
        if (!$report->isSuccess()) {
            throw new Exception('Error: ' . str_replace("\n", "", $report->getReason()));
        }
        return 'Sent!';
    }

    /**
     * @return Subscription
     */
    public function createSubscription()
    {
        $data = json_decode($this->Subscription, true);
        return Subscription::create($data);
    }

    /**
     * @param string|array<string,mixed> $payload
     * @return MessageSentReport
     */
    public function sendMessage($payload)
    {
        if ($this->Platform && $this->Platform != self::WEBPUSH) {
            throw new Exception("Not a webpush");
        }

        $pushSub = $this->createSubscription();
        $webPush = self::getWebpushHandler();

        if (is_array($payload)) {
            $payload = json_encode($payload);
        }
        $payload = $payload ? $payload : null;

        $report = $webPush->sendOneNotification(
            $pushSub,
            $payload
        );

        return $report;
    }

    /**
     * @param Member $member
     * @return bool
     */
    public static function deleteSubscriptions(Member $member)
    {
        $i = 0;
        //@phpstan-ignore-next-line
        foreach ($member->PushSubscriptions() as $sub) {
            $i++;
            $sub->delete();
        }
        return $i > 0;
    }

    /**
     * @param string $endpoint
     * @return bool
     */
    public static function deleteEndpoint($endpoint)
    {
        $sub = self::getByEndpoint($endpoint);
        if ($sub) {
            $sub->delete();
            return true;
        }
        return false;
    }

    /**
     * @param array<string,mixed> $data
     * @param Member|null $member
     * @param string $type see consts
     * @return PushSubscription
     */
    public static function createNew($data, Member $member = null, $type = null)
    {
        $sub = new PushSubscription();
        if ($member) {
            $sub->MemberID = $member->ID;
        }
        $encodedData = json_encode($data);
        $encodedData = $encodedData ? $encodedData : '';
        $sub->Endpoint = $data['endpoint'] ?? '';
        $sub->Subscription = $encodedData;
        if ($type) {
            $sub->Platform = $type;
        }
        $sub->write();
        return $sub;
    }

    /**
     * @param string $endpoint
     * @return PushSubscription|null
     */
    public static function getByEndpoint($endpoint)
    {
        //@phpstan-ignore-next-line
        return self::get()->filter('Endpoint', $endpoint)->first();
    }

    /**
     * @link https://github.com/web-push-libs/web-push-php
     * @param string|array<mixed> $where
     * @param array<string,mixed>|string $payload
     * @return int
     */
    public static function sendPushNotifications($where, $payload)
    {
        /** @var PushSubscription[]|DataList $subs */
        $subs = self::get()->where($where);

        if (is_array($payload)) {
            $payload = json_encode($payload);
        }
        $payload = $payload ? $payload : null;

        $webPush = PushSubscription::getWebpushHandler();
        $processed = 0;
        $subsByEndpoint = [];
        /** @var PushSubscription $sub */
        foreach ($subs as $sub) {
            if ($sub->Platform && $sub->Platform != self::WEBPUSH) {
                continue;
            }

            $processed++;

            $pushSub = $sub->createSubscription();

            $webPush->queueNotification(
                $pushSub,
                $payload // optional (defaults null)
            );

            $subsByEndpoint[$pushSub->getEndpoint()] = $sub;
        }

        /**
         * Check sent results
         * @var MessageSentReport $report
         */
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            $sub = $subsByEndpoint[$endpoint] ?? '';
            if (!$sub) {
                throw new Exception("Could not find subscription for $endpoint");
            }
            $sub->LastCallError = !$report->isSuccess();
            if (!$report->isSuccess()) {
                $sub->LastCallErrorReason = $report->getReason();
            }
            $sub->LastCalled = date('Y-m-d H:i:s');
            $sub->write();
        }

        return $processed;
    }
}
