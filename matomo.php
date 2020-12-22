<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use MatomoTracker;

/**
 * Class MatomoPlugin
 * @package Grav\Plugin
 */
class MatomoPlugin extends Plugin
{
    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100000], // TODO: Remove when plugin requires Grav >=1.7
                ['onPluginsInitialized', 0]
            ]
        ];
    }

    /**
    * Composer autoload.
    *is
    * @return ClassLoader
    */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            'onPageInitialized' => [
                ['onPageInitialized', 0]
            ],
        ]);
    }

    public function onPageInitialized(Event $event)
    {
        $page = $event['page'];

        // Merge configs and then check for the active flag per page
        $config = $this->mergeConfig($page);
        if (!$config->get('active', true)) {
            return;
        }

        // Check if client set "Do Not Track" header
        if ($config->get('respect_do_not_track', true)) {
            if (isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] == 1) {
                return;
            }
        }

        $matomo_url = $config->get('matomo_url');
        $site_id = $config->get('site_id');
        $token = $config->get('token');

        if (!$matomo_url || !$site_id || !$token || $matomo_url === 'https://example.tld') {
            throw new \RuntimeException($this->grav['language']->translate('PLUGIN_MATOMO.INVALID_CONFIG'));
        }

        // Matomo object
        $matomoTracker = new MatomoTracker((int)$site_id, $matomo_url);

        // Optionally enable cookies
        if (!$config->get('enable_cookies', false)) {
            $matomoTracker->disableCookieSupport();
        }

        // Set authentication token
        $matomoTracker->setTokenAuth($token);

        // Track page view
        // NOTE: Url, Ip, Referrer, Browser Language and UserAgent is set by the API automatically
        // TODO return value is currently useless: https://github.com/matomo-org/matomo-php-tracker/issues/85
        $ret = $matomoTracker->doTrackPageView($page->title());
    }
}
