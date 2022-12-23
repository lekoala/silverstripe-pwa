<?php

namespace LeKoala\SsPwa;

use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\TemplateGlobalProvider;

/**
 * @link https://developer.mozilla.org/en-US/docs/Web/Manifest
 * @link https://web.dev/learn/pwa/web-app-manifest/
 */
class ManifestController extends Controller implements TemplateGlobalProvider, ServiceWorkerCacheProvider
{
    /**
     * @config
     * @var string
     */
    private static $gcm_sender_id = null;
    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/Manifest/background_color
     * @config
     * @var string
     */
    private static $background_color = '#ffffff';
    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/Manifest/orientation
     * @config
     * @var string
     */
    private static $orientation = 'portrait-primary';

    /**
     * @var array
     */
    private static $allowed_actions = [
        'index',
    ];

    /**
     * @return mixed
     */
    public function index()
    {
        $config = $this->config();
        $sc = SiteConfig::current_site_config();

        $title = $sc->Title;
        $desc = $sc->Tagline;
        $desc = $desc ?: $title;

        $icons = self::listIcons();

        $manifestContent = [
            // Full name of your PWA. It will appear along with the icon in the operating system's home screen, launcher, dock, or menu
            'name' => $title,
            // Optional, a shorter name of your PWA, used when there is not enough room to display the full value of the name field. Keep it under 12 characters to minimize the possibility of truncation.
            'short_name' => $title,
            // Array of icon objects with src, type, sizes, and optional purpose fields, describing what images should represent the PWA.
            'icons' => $icons,
            // The URL the PWA should load when the user starts it from the installed icon.
            // An absolute path is recommended, so if your PWA's home page is the root of your site,
            // you could set this to ‘/' to open it when your app starts.
            // If you don't provide a start URL, the browser can use the URL the PWA was installed from as a start.
            // It can be a deep link, such as the details of a product instead of your home screen.
            'start_url' => Director::baseURL(),
            // One of fullscreen, standalone, minimal-ui, or browser, describing how the OS should draw the PWA window.
            // You can read more about the different display modes in the App Design chapter.
            // Most use cases implement standalone.
            'display' => 'standalone',
            // A string that uniquely identifies this PWA against others that may be hosted on the same origin. If it's not set, the start_url will be used as a fallback value.
            // Keep in mind that by changing the start_url in the future (such as when changing a query string value) you may be removing the browser's ability to detect that a PWA is already installed.
            // @link https://developer.chrome.com/blog/pwa-manifest-id/
            'id' => Director::baseURL(),
            // The description member is a string in which developers can explain what the application does.
            // description is directionality-capable, which means it can be displayed left to right or right to left based on the values of the dir and lang manifest members.
            'description' => $desc,
            'lang' => 'en',
            'dir' => 'ltr',
            // The scope member is a string that defines the navigation scope of this web application's application context.
            // It restricts what web pages can be viewed while the manifest is applied.
            // If the user navigates outside the scope, it reverts to a normal web page inside a browser tab or window.
            'scope' => Director::baseURL(),
            // https://developer.chrome.com/docs/extensions/mv3/declare_permissions/
            'permissions' => [
                // https://developer.chrome.com/docs/extensions/reference/gcm/
                'gcm',
            ],
            // The background_color member defines a placeholder background color for the application page to display before its stylesheet is loaded.
            // This value is used by the user agent to draw the background color of a shortcut when the manifest is available before the stylesheet has loaded.
            'background_color' => $config->get('background_color'),
            // The theme_color member is a string that defines the default theme color for the application.
            // This sometimes affects how the OS displays the site (e.g., on Android's task switcher, the theme color surrounds the site).
            'theme_color' => $config->get('background_color'),
            // The orientation member defines the default orientation for all the website's top-level browsing contexts.
            'orientation' => $config->get('orientation'),
        ];

        $gcm_sender_id = $config->get('gcm_sender_id');
        if ($gcm_sender_id) {
            $manifestContent['gcm_sender_id'] = $gcm_sender_id;
            $manifestContent['gcm_user_visible_only'] = true;
        }

        $this->getResponse()->addHeader('Content-Type', 'application/manifest+json; charset="utf-8"');
        return json_encode($manifestContent);
    }

    public static function listIcons()
    {
        $iconsPath = self::getIconsPath();
        $sizes = [192, 512];
        $purposes = ['any', 'maskable'];

        $icons = [];
        foreach ($sizes as $size) {
            foreach ($purposes as $purpose) {
                $icons[] = [
                    'src' => self::join_links($iconsPath, 'manifest-icon-' . $size . '.maskable.png'),
                    'sizes' => $size . 'x' . $size,
                    'type' => 'image/png',
                    'purpose' => $purpose,
                ];
            }
        }
        return $icons;
    }

    public static function getIconsPath()
    {
        $baseURL = '/';
        $iconsPath = self::join_links($baseURL, RESOURCES_DIR, 'app', 'images', 'icons');
        return $iconsPath;
    }

    public static function get_template_global_variables()
    {
        return [
            'PwaIconsPath' => 'getIconsPath',
        ];
    }

    public static function getServiceWorkerCachedPaths()
    {
        $icons = self::listIcons();
        return array_map(function ($v) {
            return $v['src'];
        }, $icons);
    }
}
