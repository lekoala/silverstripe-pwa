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
     * @var array
     */
    private static $allowed_actions = [
        'index',
        'addPushSubscription',
        'removePushSubscription',
    ];

    /**
     * @return HTTPResponse
     */
    public function index()
    {
        return '';
    }

    protected function getJsonData()
    {
        $json = $this->getRequest()->getBody();
        $data = json_decode($json, JSON_OBJECT_AS_ARRAY);
        return $data;
    }

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
        $resp = $this->getResponse();
        $resp->addHeader('Content-Type', 'application/json');
        $resp->setBody($body);
        return $resp;
    }

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
        $resp = $this->getResponse();
        $resp->addHeader('Content-Type', 'application/json');
        $resp->setBody($body);
        return $resp;
    }
}
