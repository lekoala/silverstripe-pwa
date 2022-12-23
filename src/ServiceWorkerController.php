<?php

namespace LeKoala\SsPwa;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\View\TemplateGlobalProvider;

class ServiceWorkerController extends Controller implements TemplateGlobalProvider
{

    /**
     * @var array
     */
    private static $allowed_actions = [
        'index',
        'client',
    ];

    /**
     * @config
     * @var string
     */
    private static $version = "v1";

    /**
     * @config
     * @var string
     */
    private static $auto_version = true;

    /**
     * @config
     * @var bool
     */
    private static $enable_client_cache = false;

    /**
     * @config
     * @var bool
     */
    private static $enable_client_js = true;

    /**
     * @config
     * @var string
     */
    private static $custom_sw_path;

    /**
     * @config
     * @var string
     */
    private static $custom_client_path;

    /**
     * @return HTTPResponse
     */
    public function index()
    {
        $script = self::getJsContent(self::getSwPath());

        $resp = $this->getResponse();
        $resp->addHeader('Content-Type', 'application/javascript; charset="utf-8"');
        $resp->setBody($script);
        return $resp;
    }

    /**
     * @return HTTPResponse
     */
    public function client()
    {
        $script = self::getJsContent(self::getClientPath());

        $resp = $this->getResponse();
        $resp->addHeader('Content-Type', 'application/javascript; charset="utf-8"');
        $resp->setBody($script);
        return $resp;
    }


    /**
     * @param mixed $v
     * @return mixed
     */
    public static function phpToJsVar($v)
    {
        switch (gettype($v)) {
            case 'boolean':
                return $v ? 'true' : 'false';
            case 'integer':
            case 'double':
                return $v;
            case 'string':
                return "'$v'";
            case 'array':
                return '["' . implode('","', $v) . '"]';
            default;
                return json_encode($v, JSON_PRETTY_PRINT);
        }
    }

    /**
     * @return string
     */
    protected static function getSwPath()
    {
        $custom_path = self::config()->get('custom_sw_path');
        $default_path = dirname(__DIR__) . '/ressources/sw.js';
        return $custom_path ? $custom_path : $default_path;
    }

    /**
     * @return string
     */
    protected static function getClientPath()
    {
        $custom_path = self::config()->get('custom_client_path');
        $default_path = dirname(__DIR__) . '/ressources/client.js';
        return $custom_path ? $custom_path : $default_path;
    }

    /**
     * @return array
     */
    protected static function getJsConstantsMap()
    {
        $cacheManifest = self::CacheOnInstall();
        return [
            'self.__SW_DEBUG' => self::DebugMode(),
            'self.__SW_CACHE_NAME' => self::CacheName(),
            'self.__SW_VERSION' => self::Version(),
            'self.__SW_ENABLE_CLIENT_CACHE' => self::config()->get('enable_client_cache') ?? false,
            'self.__SW_CACHE_MANIFEST' => $cacheManifest,
            'self.__SW_PUSH_PUBLIC_KEY' => PushSubscription::getPublicKey(),
        ];
    }

    /**
     * @param string $script
     * @return string
     */
    protected static function replaceConstants($script)
    {
        $map = self::getJsConstantsMap();
        $values = array_map('self::phpToJsVar', array_values($map));
        $script = str_replace(array_keys($map), $values, $script);
        return $script;
    }

    /**
     * @param string $file
     * @return string
     */
    protected static function getJsContent($file)
    {
        $script = file_get_contents($file);
        $script = self::replaceConstants($script);
        $script = self::minifyJs($script);
        return $script;
    }

    /**
     * @param string $js
     * @return string
     */
    public static function minifyJs($js)
    {
        // Remove comments with //
        $js = preg_replace('/\n(\s+)?\/\/[^\n]*/', "", $js);
        // Remove extra space
        $js = preg_replace(["/\s+\n/", "/\n\s+/", "/ +/"], ["\n", "\n ", " "], $js);
        return $js;
    }

    /**
     * Base URL
     * @return string
     */
    public static function BaseUrl()
    {
        return Director::absoluteBaseURL();
    }

    /**
     * Debug mode
     * @return bool
     */
    public static function DebugMode()
    {
        return Director::isDev();
    }

    /**
     * @return string
     */
    public static function CacheName()
    {
        return self::config()->get('version');
    }

    /**
     * @return string
     */
    public static function getEnableClientJs()
    {
        return self::config()->get('enable_client_js');
    }

    /**
     * @param array $manifest
     * @return string
     */
    public static function Version($manifest = [])
    {
        if (self::config()->get('auto_version') || Director::isDev()) {
            $base = Director::baseFolder();
            $t = "";
            foreach ($manifest as $file) {
                $t .= filemtime($base . $file);
            }
            $t .= filemtime(self::getSwPath());
            return md5($t);
        }
        return self::config()->get('version');
    }

    /**
     * A list with file to cache in the install event
     * @return array
     */
    public static function CacheOnInstall()
    {
        $paths = [];
        foreach (ClassInfo::implementorsOf(ServiceWorkerCacheProvider::class) as $class) {
            $paths = array_merge($paths, $class::getServiceWorkerCachedPaths());
        }
        // Make sure we get a proper array even when stuff was deleted
        return array_merge([], array_unique($paths));
    }

    public static function get_template_global_variables()
    {
        return [
            'EnableClientJs' => 'getEnableClientJs',
        ];
    }
}
