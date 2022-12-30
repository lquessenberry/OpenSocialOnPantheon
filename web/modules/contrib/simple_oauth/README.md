[![Build Status](https://travis-ci.org/e0ipso/simple_oauth.svg?branch=8.x-2.x)](https://travis-ci.org/e0ipso/simple_oauth)

Simple OAuth is an implementation of the [OAuth 2.0 Authorization Framework RFC](https://tools.ietf.org/html/rfc6749). Using OAuth 2.0 Bearer Token is very easy. See how you can get the basics working in **less than 5 minutes**! This project is focused in simplicity of use and flexibility. When deciding which project to use, also consider other projects like [OAuth](https://www.drupal.org/project/oauth), an OAuth 1 implementation that doesn't rely on you having https in your production server.

### Based on League\OAuth2
This module uses the fantastic PHP library [OAuth 2.0 Server](http://oauth2.thephpleague.com) from [The League of Extraordinary Packages](http://thephpleague.com). This library has become the de-facto standard for modern PHP applications and is thoroughly tested.

[![Latest Version](http://img.shields.io/packagist/v/league/oauth2-server.svg?style=flat-square)](https://github.com/thephpleague/oauth2-server/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/thephpleague/oauth2-server/master.svg?style=flat-square)](https://travis-ci.org/thephpleague/oauth2-server)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/thephpleague/oauth2-server.svg?style=flat-square)](https://scrutinizer-ci.com/g/thephpleague/oauth2-server/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/thephpleague/oauth2-server.svg?style=flat-square)](https://scrutinizer-ci.com/g/thephpleague/oauth2-server)
[![Total Downloads](https://img.shields.io/packagist/dt/league/oauth2-server.svg?style=flat-square)](https://packagist.org/packages/league/oauth2-server)

### Quick demo (Client Credentials Grant)

1. Install the module using Composer: `composer require drupal/simple_oauth:6.0.x'`. You can use any other installation method, as long as you install the [OAuth2 Server](https://github.com/thephpleague/oauth2-server) composer package.
2. Generate a pair of keys to encrypt the tokens. And store them outside of your document root for security reasons.
```
openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout > public.key
```
3. Save the path to your keys in: `/admin/config/people/simple_oauth`.
4. Go to `/admin/modules` and enable the `JSON:API` module.
5. Go to `/admin/people/permissions` and allow the permission `View published content` only for authenticated user.
6. Create a scope by going to: `/admin/config/people/simple_oauth/oauth2_scope/dynamic/add`, enable the `Client Credentials` grant type and set permission to `access content`.
7. Create a Client Application by going to: `/admin/config/services/consumer/add`, enable the `Client Credentials` grant type, set User under `Client Credentials settings` and set `Is Confidential?` to true.
8. Create a token with your credentials by making a `POST` request to `/oauth/token`. See [the documentation](https://oauth2.thephpleague.com/authorization-server/client-credentials-grant/) about what fields your request should contain.
9. Request a node via JSON:API without authentication and watch it fail, e.g: `/jsonapi/node/{bundle}?page[limit]=1`.
10. Request a node via JSON:API with the header `Authorization: Bearer {YOUR_TOKEN}` and watch it succeed.

### My token has expired!

First, that is a good thing. Tokens are like cash, if you have it you can use it. You don't need to prove that token belongs to you, so don't let anyone steal your token. In order to lower the risk tokens should expire fairly quickly. If your token expires in 120s then it will be only usable during that window.

#### What do I do if my token was expired?

Along with your access token, an authentication token is created. It's called the _refresh token_ . It's a longer lived token, that it's associated to an access token and can be used to create a replica of your expired access token. You can then use that new access token normally. To use your refresh token you will need to make use of the [_Refresh Token Grant_](http://oauth2.thephpleague.com/authorization-server/refresh-token-grant/). That will return a JSON document with the new token and a new refresh token. That URL can only be accessed with your refresh token, even if your access token is still valid.

#### What do I do if my refresh token was also expired, or I don't have a refresh token?

Then you will need to generate a new token from scratch. You can avoid this by refreshing your access token before your refresh token expires. This way you avoid the need to require the user to prove their identity to Drupal to create a new token. Another way to mitigate this is to use longer expiration times in your tokens. This will work, but the the recommendation is to refresh your token in time.

### I'm seeing warnings about my private key file permissions. What should I do?

The upstream OAuth library checks the private key's file permissions by default. This is suitable in certain server configurations, however in some modern environments (e.g., containerized workloads) where secrets are injected into the environment and owned by a user different from the web daemon's run-as user, this is a false-positive. In these scenarios, you can use the Settings API to set the value passed to `CryptKey::__construct()` for checking the file permission:

In `settings.php`:
```$settings['simple_oauth.key_permissions_check'] = FALSE;```

### Recommendation

Check the official documentation on the [Bearer Token Usage](http://tools.ietf.org/html/rfc6750). And **turn on SSL!**.

### Issues and contributions

Issues and development happens in the [Drupal.org issue queue](https://www.drupal.org/project/issues/simple_oauth).
