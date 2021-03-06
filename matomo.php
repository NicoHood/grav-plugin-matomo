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
            if ($this->grav['config']->get('plugins.matomo.dashboard_token')) {
                $this->enable([
                    'onTwigTemplatePaths' => ['onTwigAdminTemplatePaths', 0],
                    'onAdminMenu' => ['onAdminMenu', 0]
                ]);
            }

            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0]
        ]);
    }

    /**
     * Add plugin templates path
     */
    public function onTwigAdminTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
    }

    /**
     * Add navigation item to the admin plugin
     */
    public function onAdminMenu()
    {
        $this->grav['twig']->plugins_hooked_nav['Matomo'] = ['route' => 'matomo', 'icon' => 'fa-bar-chart'];
    }

    public function onPageInitialized(Event $event)
    {
        $page = $event['page'];

        // Merge configs and then check for the active flag per page
        $config = $this->mergeConfig($page);
        if (!$config->get('active', true)) {
            $this->documentBlockingReason("Plugin is not active for this page.");
            return;
        }

        // Check if client set "Do Not Track" header
        // NOTE: Ignoring the header only works for php tracking, not for javascript tracking.
        // That's because the matomo instance will also evaluate the header and ignore the request.
        if ($config->get('respect_do_not_track', true)) {
            if (isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] == 1) {
                $this->documentBlockingReason("Client requests 'DO NOT TRACK'.");
                return;
            }
        }

        // Don't proceed if a blocking cookie is set
        $blockingCookieName = $config->get('blockingCookie');
        if (!empty($blockingCookieName) && !empty($_COOKIE[$blockingCookieName])) {
            $this->documentBlockingReason("Blocking cookie \"$blockingCookieName\" is set.");
            return;
        }

        // Don't proceed if the IP address is blocked
        if (in_array($_SERVER['REMOTE_ADDR'], $config->get('blockedIpAddresses', []))) {
            $this->documentBlockingReason("Client ip " . $_SERVER['REMOTE_ADDR'] . " is in blockedIps.");
            return;
        }

        // Don't proceed if the IP address is within a blocked range
        foreach ($config->get('blockedIpRanges', []) as $blockedIpRange) {
            if ($this->inIPAddressRange($this->packedIPAddress($_SERVER['REMOTE_ADDR']), $blockedIpRange)) {
                $this->documentBlockingReason("Client ip " . $_SERVER['REMOTE_ADDR'] . " is in range \"" . $blockedIpRange . "\".");
                return;
            }
        }

        $matomo_url = $config->get('matomo_url');
        $site_id = $config->get('site_id');
        $token = $config->get('token');

        if (!$matomo_url || !$site_id || !$token || $matomo_url === 'https://example.tld') {
            $this->documentBlockingReason('Invalid Matomo configuration detected.');
            return;
        }

        // Add javascript tracking code (and disable php tracker to avoid duplicate entries)
        if ($config->get('enable_javascript', false)) {
            $matomo_js = $this->grav['twig']->processTemplate('js/matomo.js.twig');
            $this->grav['assets']->addInlineJs($matomo_js,
            [
              'type' => 'text/javascript',
              'priority' => 0,
              'position' => 'after'
            ]);
            return;
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
        try {
            $ret = $matomoTracker->doTrackPageView($page->title());
        }
        catch (\RuntimeException $e) {
            $this->grav['log']->error('Error tracking page view via Matomo: ' . $e->getMessage());
        }
    }

    /**
     * Add templates directory to twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Documents the reason for blocking Matomo tracking in a JavaScript comment
     * @param string $reason
     */
    private function documentBlockingReason(string $reason)
    {
        if ($this->config->get('plugins.matomo.debug', false)) {
            $this->grav['assets']->addInlineJs("/* Matomo tracking blocked, reason: $reason */");
        }
    }

    /**
     * Returns a packed IP address which can be directly compared to another packed IP address
     * @param string $humanReadableIPAddress IPv4 or IPv6 address in human-readable notation
     * @return string (16 byte packed representation)
     */
    private function packedIPAddress(string $humanReadableIPAddress): string
    {
        $result = inet_pton($humanReadableIPAddress);

        if ($result == false) {
            return $this->packedIPAddress('::0');
        }
        // IPv6 native
        elseif (strlen($result) == 16) {
            return $result;
        }
        // IPv4, expanded to IPv6 compatible length
        else {
            return "\0\0\0\0\0\0\0\0\0\0\0\0" . $result;
        }
    }

    /**
     * Returns true if a packed IP address is within the specified address range
     * @param string $packedAddress
     * @param string $range
     * @return bool
     * @noinspection SpellCheckingInspection
     */
    private function inIPAddressRange(string $packedAddress, string $range): bool
    {
        if ($range === 'private') {  // RFC 6890, RFC 4193
            return ($this->inIPAddressRange($packedAddress, "10.0.0.0-10.255.255.255")
                || $this->inIPAddressRange($packedAddress, "172.16.0.0-172.31.255.255")
                || $this->inIPAddressRange($packedAddress, "192.168.0.0-192.168.255.255")
                || $this->inIPAddressRange($packedAddress, "fc00::-fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff"));
        } elseif ($range === 'loopback') {  // RFC 6890
            return ($this->inIPAddressRange($packedAddress, "127.0.0.1-127.255.255.255")
                || $this->inIPAddressRange($packedAddress, "::1-::1"));
        } elseif ($range === 'link-local') {  // RFC 6890, RFC 4291
            return ($this->inIPAddressRange($packedAddress, "169.254.0.0-169.254.255.255")
                || $this->inIPAddressRange($packedAddress, "fe80::-febf:ffff:ffff:ffff:ffff:ffff:ffff:ffff"));
        } else {
            $rangeLimits = explode('-', $range);
            if (count($rangeLimits) == 2) {
                $lowerLimit = $this->packedIPAddress($rangeLimits[0]);
                $upperLimit = $this->packedIPAddress($rangeLimits[1]);
                return $lowerLimit <= $packedAddress && $packedAddress <= $upperLimit;
            }
        }

        return false;
    }
}
