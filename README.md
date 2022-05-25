# TYPO3 extension nxvarnish

[![stability-beta](https://img.shields.io/badge/stability-beta-33bbff.svg)](https://github.com/netlogix/nxvarnish)
[![TYPO3 V9](https://img.shields.io/badge/TYPO3-9-orange.svg)](https://get.typo3.org/version/9)
[![TYPO3 V10](https://img.shields.io/badge/TYPO3-10-orange.svg)](https://get.typo3.org/version/10)
[![TYPO3 V11](https://img.shields.io/badge/TYPO3-11-orange.svg)](https://get.typo3.org/version/11)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.4-8892BF.svg)](https://php.net/)
[![GitHub CI status](https://github.com/netlogix/nxvarnish/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/netlogix/nxvarnish/actions)

Adds varnish integration to TYPO3. Ensures varnish caches are flushed when TYPO3
caches are flushed. Uses cache tags to flush all necessary content.

**This is a beta release that has not yet been tested in production.**

## Compatibility

The current version (4.x) of this extension has been tested using these
versions:

* TYPO3 9.5 on PHP 7.4
* TYPO3 10.4 on PHP 7.4
* TYPO3 11.5 on PHP 7.4
* TYPO3 11.5 on PHP 8.0
* TYPO3 11.5 on PHP 8.1

## Installation

Install the package via composer.

```bash
composer require netlogix/nxvarnish
```

## Configuration

### TYPO3

This extension provides some configuration in Install Tool.

* varnishHost: **required**, needed to communicate with Varnish in order to
  purge
  caches
* allowCacheLogin: *optional*, send normal cache headers even when someone is
  logged in

### Varnish

Varnish needs some special configuration to understand cache tags and BAN
requests.
Add this to your Varnish .vcl:

```vcl
# WARNING: this is an example how to add tag-based purging to an existing
# Varnish configuration. This is *not* a complete configuration!

# a list of clients that are allowed to initiate a BAN
acl purge {
      "localhost";
      # add whatever IPs are allowed to initiate a BAN
      "172.16.0.0"/16; # just an example
}


sub vcl_recv {

    # ...

    if (req.method == "BAN") {
        # only allow cache BANs from known IPs
        if (!std.ip(req.http.X-Client-Ip, client.ip) ~ purge) {
            return (synth(405, "Not allowed."));
        }

        # ban using cache tags
        if (req.http.X-Cache-Tags) {
            # this will ban all cache objects with matching tags
            ban("obj.http.X-Cache-Tags ~ " + req.http.X-Cache-Tags);

            # create an HTTP 200 response and exit
            return (synth(200, "Ban added."));
        }

        # return an error if no cache tags were provided.
        # you might need to remove this if you have additional BAN conditions
        return (synth(400, "Bad Request."));

    }

    # ...

}

sub vcl_deliver {
    # ...

    # remove cache-tags header from response sent to client
    unset resp.http.X-Cache-Tags;


    # ...
}

```
