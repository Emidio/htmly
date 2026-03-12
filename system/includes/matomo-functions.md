# HTMLy Matomo integration
Matomo integrations for HTMLy featuring both JavaScript and PHP backend tracking.

## Javascript tracking
Matomo Javascript tracking for HTMLy is standard Matomo tracking function, added at the end of each page. Templates must include it, same as for Google Analytics, at the end of template file layout.html.php:

```
    <?php if (analytics()): ?><?php echo analytics(); ?><?php endif; ?>
    <?php if (matomo()): ?><?php echo matomo($locals); ?><?php endif; ?>
</body>
</html>
```

Javascript tracking excludes by design all browsers not supporting javascript, mainly bots and crawlers. If that's a desired behaviour if you want to only see "real" visitors in you Matomo stats, it prevents you to know if someone is pinging your site constantly or grabbing content, wasting bandwidth and resources.

## PHP backend tracking
PHP backend tracking uses official Matomo PHP tracker: https://github.com/matomo-org/matomo-php-tracker

However, simple PHP tracking hides some pitfalls. It tracks everything - that means bot, crawlers, everything you can find in Apache/Nginx logs. That gives you a whole picture of the site accesses, but it didn't differentiate bots/crawlers from real visitors. Matomo has an integrated bot recognition system based on user agent, but user agent is easily faked, and a lot of bot/crawlers send real browsers data.

HTMLy PHP backend doesn't simply use Matomo PHP Tracker, but perform some checks to identify bots and block ASN.

### Features
A list of available features and configuration from Matomo section inside HTML widgets configuration.

#### Matomo tracking
Basic configuration with Matomo server URL, site ID, authentication key (needed for PHP backend only).

#### Cookies settings
This affects mainly javascript tracking, as PHP tracking avoids anyway to use the cookie. Considering strict GDPR European compliance, setting it off saves a lot of policy acceptance form and privacy agreements. Disabling cookies leaves only the standard "technical" PHP session cookie.

#### BOT identification
All request coming from ASN bot list are marked as bots, while ASN in block list will receive a 403 status (unauthorized). You can set real/bot value using Matomo custom dimensions, and same for ASN/Provider. This let you create segments in Matomo showing only real or bot traffic. Bots visits can be also sent to a specific site ID (one site ID for real visits, one site ID for bots traffic).
The bot recognition system needs to save first visit (or sequential visits for browsers not supporting PHP session cookie) in a JSON temporary file (server side) and send info to Matomo asynchronously. Asynchronous statistics can be sent on page load, or using a cronjob (recommended).

#### Cronjob
Chosing cronjob to send asyncronoous data needs a cronjob to be set running: 

`php /var/www/htmly/system/resources/php/matomo-cronjob.php`

where:

`/var/www/htmly`

have to be adjusted to the folder of your HTMLy installation. Do not set it using `wget`, because that folder is only accessible using CLI for security reasons.

### How it works
First detection is based on JavaScript. Usually bots and crawlers just gather content, without supporting JavaScript. Matomo implementation in HTMLy at first visit check JavaScript (client side) calling a specific file using JavaScript and sending screen information (width, height, color space and pixel ratio). No information is sent to Matomo at this time.

A JSON file with information to be sent is temporarily saved for later. If JavaScript call is performed, the called script (js, but really PHP - URL rewrite) add screen data and send everything to Matomo, deleting the file. If JavaScript call is not performed (so no javasc ript support), the JSON file stay there and is sent to Matomo asynchronously by a cronjob or next page load.

In both situations user agent is checked for bots using browscap extension (https://browscap.org and https://www.php.net/manual/en/function.get-browser.php), and IP address against a list of ASN - Autonomous System Number, used by Internet Service Providers  (ISPs) and large organizations  (e.g. Google, Facebook...) to facilitate BGP (Border Gateway Protocol) routing.

ASN information comes from GeoIP plugin from MaxMind (Apache integration, $_SERVER['MM_ASN'] and $_SERVER['MM_ASORG'] variables) and a list of datacenter IP ranges to be saved in config/data/datacenters.csv - the list of datacenter IP ranges can be updated from https://github.com/growlfm/ipcat 

Any ipcat csv file works (started from client9 repo https://github.com/client9/ipcat), in format: ip_start,ip_end,ANS_name,ASN_link and it is used as fallback from GeoIP Plugin.




