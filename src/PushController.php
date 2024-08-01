<?php

namespace LeKoala\SsPwa;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Security;

class PushController extends Controller
{
    /**
     * @config
     * @var boolean
     */
    private static $enabled = true;

    /**
     * @var array<string>
     */
    private static $allowed_actions = [
        'index',
        'addPushSubscription',
        'removePushSubscription',
    ];

    /**
     * @return string
     */
    public function index()
    {
        return '';
    }

    /**
     * @return array<string,mixed>
     */
    protected function getJsonData(): array
    {
        $json = $this->getRequest()->getBody();
        $data = json_decode($json, true);
        if (!$data) {
            return [];
        }
        return $data;
    }

    /**
     * @return string|HTTPResponse
     */
    public function addPushSubscription()
    {
        if (!self::config()->get('enabled')) {
            return '';
        }
        $data = $this->getJsonData();
        $success = false;
        if (!empty($data['endpoint'])) {
            $success = PushSubscription::createNew($data, Security::getCurrentUser(), PushSubscription::WEBPUSH);
        }

        $results = [
            'success' => $success
        ];
        $body = json_encode($results);
        $body = $body ? $body : '';
        $resp = $this->getResponse();
        $resp->addHeader('Content-Type', 'application/json');
        $resp->setBody($body);
        return $resp;
    }

    /**
     * @return string|HTTPResponse
     */
    public function removePushSubscription()
    {
        if (!self::config()->get('enabled')) {
            return '';
        }
        $data = $this->getJsonData();
        $success = false;
        if (!empty($data['endpoint'])) {
            $success = PushSubscription::deleteEndpoint($data['endpoint']);
        }

        $results = [
            'success' => $success
        ];
        $body = json_encode($results);
        $body = $body ? $body : '';
        $resp = $this->getResponse();
        $resp->addHeader('Content-Type', 'application/json');
        $resp->setBody($body);
        return $resp;
    }
}
