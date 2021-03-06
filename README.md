# Matomo Plugin

The **Matomo** Plugin is an extension for [Grav CMS](http://github.com/getgrav/grav). It integrates Matomo analytics into Grav CMS. Unlike other plugins this supports **server side tracking** via [Matomo PHP Tracking API](https://github.com/matomo-org/matomo-php-tracker). This plugin **requires no client side Javascript** but is easier to use compared to [Matomo Log Analysis](https://matomo.org/docs/log-analytics-tool-how-to/).

<a href="https://www.buymeacoffee.com/nicohood" target="_blank"><img src="https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png" alt="Buy Me A Coffee" style="height: auto !important;width: auto !important;" ></a>

## Features

* Easy server-side tracking
* Matomo Dashboard integrated in Grav Admin Plugin
* Optional Cookie tracking
* Optional Javascript tracking
* Grav 1.6 and 1.7 supported

## Installation

Installing the Matomo plugin can be done in one of three ways: The GPM (Grav Package Manager) installation method lets you quickly install the plugin with a simple terminal command, the manual method lets you do so via a zip file, and the admin method lets you do so via the Admin Plugin.

### GPM Installation (Preferred)

To install the plugin via the [GPM](http://learn.getgrav.org/advanced/grav-gpm), through your system's terminal (also called the command line), navigate to the root of your Grav-installation, and enter:

    bin/gpm install matomo

This will install the Matomo plugin into your `/user/plugins`-directory within Grav. Its files can be found under `/your/site/grav/user/plugins/matomo`.

### Manual Installation

To install the plugin manually, download the zip-version of this repository and unzip it under `/your/site/grav/user/plugins`. Then rename the folder to `matomo`. You can find these files on [GitHub](https://github.com/nico-hood/grav-plugin-matomo) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/matomo

> NOTE: This plugin is a modular component for Grav which may require other plugins to operate, please see its [blueprints.yaml-file on GitHub](https://github.com/nico-hood/grav-plugin-matomo/blob/master/blueprints.yaml).

### Admin Plugin

If you use the Admin Plugin, you can install the plugin directly by browsing the `Plugins`-menu and clicking on the `Add` button.

## Requirements

This plugin uses the [Matomo PHP Tracking API](https://github.com/matomo-org/matomo-php-tracker) which depends on the following PHP modules:

- json extension (json_decode, json_encode)
- CURL or STREAM extensions (to issue the HTTPS request to Matomo)

## Configuration

Before configuring this plugin, you should copy the `user/plugins/matomo/matomo.yaml` to `user/config/plugins/matomo.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
active: true

# Connection settings
matomo_url: 'https://example.tld'
site_id: 1
# It is recommended to set the token via the grav dotenv plugin:
# https://github.com/Ralla/grav-plugin-dotenv
token: ''

# Privacy settings
respect_do_not_track: true
enable_cookies: false
enable_javascript: false
# Array of blocked client IP addresses for which tracking will be disabled.
blockedIpAddresses: []
# Array of blocked client IPv4 and/or IPv6 address ranges in the form
# ["192.177.204.1-192.177.204.254", "2001:db8::1-2001:db8::fe", ...].
# In addition to numerical ranges, the keywords "private", "loopback", "link-local" are recognized,
# designating special IPv4 and IPv6 ranges (see RFCs 6890, 4193, 4291).
blockedIpRanges: ["private", "loopback", "link-local"]
# Name of a blocking cookie, which disables Matomo JS and PHP tracking, when set.
blockingCookie: "blockMatomo"

# Grav admin plugin dashboard settings
# The dashboard token must only have read access:
# https://matomo.org/docs/embed-matomo-reports/#embed-piwik-widgets-on-a-password-protected-or-private-page
dashboard_token: ''
# Specify a different site id for the dashboard.
# This is useful if you have a test and production environment
# and always want to show the production stats in the admin panel.
dashboard_site_id: ''

# Development settings
# Enable this to add debug output about blocking reasons to the page's HTML source ('Matomo tracking blocked').
debug: false
```

Note that if you use the Admin Plugin, a file with your configuration named matomo.yaml will be saved in the `user/config/plugins/`-folder once the configuration is saved in the Admin.

It is **recommended to set the token via environment variables** and the [grav dotenv plugin](https://github.com/Ralla/grav-plugin-dotenv). Please **be aware** that the token will be currently [added to the config file](https://github.com/Ralla/grav-plugin-dotenv/issues/11) when editing the settings via the admin plugin.

## Usage

Simply configure your matomo host in the config and you are ready to go! Please note, that by default this plugin is **configured privacy focussed**. You and enable additional features like tracking with cookies, javascript or ignoring the "Do Not Track" header in the config. **Note:** Ignoring the "Do Not Track" header only makes sense when using the php tracker, not the javascript code. That's because the matomo instance will also evaluate the header and ignore the request.

The default config will only track the visited url, ip, referrer, language, browser/user agent, country (via ip lookup, if enabled on matomo) which is similar to [Matomo Log Analysis](https://matomo.org/docs/log-analytics-tool-how-to/), just that this plugin is more easy to use, extensible and gives instant results.

If you have any improvement, please do not hesitate to file an issue.

## Credits

* [Matomo PHP Tracking API](https://github.com/matomo-org/matomo-php-tracker)

## To Do

- [ ] Track events like form submissions with an additional form action
- [ ] Add twig templates/css to also track impressions of objects via tracking pixel and mouseover

