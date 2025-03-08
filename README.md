# xExtension-Flus

This repository contains the code for the FreshRSS extension behind [rss.flus.fr](https://rss.flus.fr).

To install this extension, you must download this repository under the
`./extensions` folder of your own FreshRSS instance.

With Git:

```console
$ git clone git@github.com:flusio/xExtension-Flus.git /path/to/FreshRSS/extensions
```

Or by downloading the zip archive:

```console
$ curl -o /tmp/xExtension-Flus.zip -L https://github.com/flusio/xExtension-Flus/archive/master.zip
$ unzip /tmp/xExtension-Flus.zip -d /tmp
$ mv /tmp/xExtension-Flus-master /path/to/FreshRSS/extensions/xExtension-Flus
```

In the FreshRSS configuration (`data/config.php`), you must add the following:

```php
// Configure the synchronization with the subscription service.
// See https://github.com/flusio/flus.fr
'billing' => [
    'flus_private_key' => 'FLUS_PRIVATE_KEY',
    'flus_api_host' => 'https://flus.example.com',
],
```

Finally, setup the cron jobs:

```cron
0 5,13,21 * * * sudo -u www-data php /path/to/freshrss/extensions/xExtension-Flus/scripts/sync_subscriptions.php
0 1 * * * sudo -u www-data php /path/to/freshrss/extensions/xExtension-Flus/scripts/notify_inactive_accounts.php
0 2 * * * sudo -u www-data php /path/to/freshrss/extensions/xExtension-Flus/scripts/clean_inactive_accounts.php
```

The first one notifies the flus.fr service about the accounts known by the service.
The two other allows to notify users and delete inactive accounts.

## What it does

It provides default Terms of Service, user configuration and feeds.

It also adds:

- a home page explaining the service;
- a page to contact the support;
- an option to share to the service flus.fr.

As the service requires a subscription, it provides several features as:

- providing a page to access the subscription account on flus.fr;
- blocking users who have no active subscriptions;
- notifying and removing inactive users.
