# Google2FA

[![Latest Stable Version](https://img.shields.io/packagist/v/pragmarx/google2fa.svg?style=flat-square)](https://packagist.org/packages/pragmarx/google2fa) [![License](https://img.shields.io/badge/license-BSD_3_Clause-brightgreen.svg?style=flat-square)](LICENSE) [![Downloads](https://img.shields.io/packagist/dt/pragmarx/google2fa.svg?style=flat-square)](https://packagist.org/packages/pragmarx/google2fa) [![Travis](https://img.shields.io/travis/antonioribeiro/google2fa.svg?style=flat-square)](https://travis-ci.org/antonioribeiro/google2fa) [![Code Quality](https://img.shields.io/scrutinizer/g/antonioribeiro/google2fa.svg?style=flat-square)](https://scrutinizer-ci.com/g/antonioribeiro/google2fa/?branch=master) [![StyleCI](https://styleci.io/repos/24296182/shield)](https://styleci.io/repos/24296182)

### Google Two-Factor Authentication for PHP Package

Google2FA is a PHP implementation of the Google Two-Factor Authentication Module, supporting the HMAC-Based One-time Password (HOTP) algorithm specified in [RFC 4226](https://tools.ietf.org/html/rfc4226) and the Time-based One-time Password (TOTP) algorithm specified in [RFC 6238](https://tools.ietf.org/html/rfc6238).

This package is agnostic, but also supports the Laravel Framework.

## Demos, Example & Playground

Please check the [Google2FA Package Playground](https://pragmarx.com/google2fa). 

Here's an demo app showing how to use Google2FA: [google2fa-example](https://github.com/antonioribeiro/google2fa-example).

You can scan the QR code on [this (old) demo page](https://antoniocarlosribeiro.com/technology/google2fa) with a Google Authenticator app and view the code changing (almost) in real time.

## Requirements

- PHP 5.4+

## Compatibility

You don't need Laravel to use it, but it's compatible with

- Laravel 4.1+
- Laravel 5+

## Installing

Use Composer to install it:

    composer require pragmarx/google2fa

If you prefer inline QRCodes instead of a Google generated url, you'll need to install [BaconQrCode](https://github.com/Bacon/BaconQrCode):
  
    composer require "bacon/bacon-qr-code":"~1.0"

## Installing on Laravel

Add the Service Provider and Facade alias to your `app/config/app.php` (Laravel 4.x) or `config/app.php` (Laravel 5.x):

```php
PragmaRX\Google2FA\Vendor\Laravel\ServiceProvider::class,

'Google2FA' => PragmaRX\Google2FA\Vendor\Laravel\Facade::class,
```

## Using It

#### Instantiate it directly

```php
use PragmaRX\Google2FA\Google2FA;
    
$google2fa = new Google2FA();
    
return $google2fa->generateSecretKey();
```

#### In Laravel you can use the IoC Container and the contract

```php
$google2fa = app()->make('PragmaRX\Google2FA\Contracts\Google2FA');
    
return $google2fa->generateSecretKey();
```

#### Or Method Injection, in Laravel 5

```php
use PragmaRX\Google2FA\Contracts\Google2FA;
    
class WelcomeController extends Controller 
{
    public function generateKey(Google2FA $google2fa)
    {
        return $google2fa->generateSecretKey();
    }
}
```

#### Or the Facade

```php
return Google2FA::generateSecretKey();
```

## How To Generate And Use Two Factor Authentication

Generate a secret key for your user and save it:

```php
$user = User::find(1);

$user->google2fa_secret = Google2FA::generateSecretKey();

$user->save();
```

Show the QR code to your user:

```php
$google2fa_url = Google2FA::getQRCodeGoogleUrl(
    'YourCompany',
    $user->email,
    $user->google2fa_secret
);

{{ HTML::image($google2fa_url) }}
```

And they should see and scan the QR code to their applications:

![QRCode](https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=otpauth%3A%2F%2Ftotp%2FPragmaRX%3Aacr%2Bpragmarx%40antoniocarlosribeiro.com%3Fsecret%3DADUMJO5634NPDEKW%26issuer%3DPragmaRX)

And to verify, you just have to:

```php
$secret = Input::get('secret');

$valid = Google2FA::verifyKey($user->google2fa_secret, $secret);
```

## Server Time

It's really important that you keep your server time in sync with some NTP server, on Ubuntu you can add this to the crontab:

    sudo service ntp stop
    sudo ntpd -gq
    sudo service ntp start

## Validation Window

To avoid problems with clocks that are slightly out of sync, we do not check against the current key only but also consider `$window` keys each from the past and future. You can pass `$window` as optional third parameter to `verifyKey`, it defaults to `4`. A new key is generated every 30 seconds, so this window includes keys from the previous two and next two minutes.

```php
$secret = Input::get('secret');
$window = 8; // 8 keys (respectively 4 minutes) past and future

$valid = Google2FA::verifyKey($user->google2fa_secret, $secret, $window);
```

An attacker might be able to watch the user entering his credentials and one time key.
Without further precautions, the key remains valid until it is no longer within the window of the server time. In order to prevent usage of a one time key that has already been used, you can utilize the `verifyKeyNewer` function.

```php
$secret = Input::get('secret');
$ts = Google2FA::verifyKeyNewer($user->google2fa_secret, $secret, $user->google2fa_ts);
if ($ts !== false) {
    $user->update(['google2fa_ts' => $ts]);
    // successful
} else {
    // failed
}
```

Note that `$ts` either `false` (if the key is invalid or has been used before) or the provided key's unix timestamp divided by the key regeneration period of 30 seconds.

## Using a Bigger and Prefixing the Secret Key

Although the probability of collision of a 16 bytes (128 bits) random string is very low, you can harden it by:
 
#### Use a bigger key

```php
$secretKey = $google2fa->generateSecretKey(32); // defaults to 16 bytes
```

#### You cn prefix your secret keys

You may prefix your secret keys, but you have to understand that, as your secret key must have length in power of 2, your prefix will have to have a complementary size. So if your key is 16 bytes long, if you add a prefix it must be also 16 bytes long, but as your prefixes will be converted to base 32, the max length of your prefix is 10 bytes. So, those are the sizes you can use in your prefixes:

```
1, 2, 5, 10, 20, 40, 80...
```

And it can be used like so:

```php
$prefix = strpad($userId, 10, 'X');

$secretKey = $google2fa->generateSecretKey(16, $prefix);
```

#### Window

The Window property defines how long a OTP will work, or how many cycles it will last. A key has a 30 seconds cycle, setting the window to 0 will make the key lasts for those 30 seconds, setting it to 2 will make it last for 120 seconds. This is how you set the window:

```php
$secretKey = $google2fa->setWindow(4);
```

But you can also set the window while checking the key. If you need to set a window of 4 during key verification, this is how you do: 

```php
$isValid = $google2fa->verifyKey($seed, $key, 4);
```

#### Generating Inline QRCodes

First you have to install the BaconQrCode package, as stated above, then you just have to generate the inline string using:
 
```php
$inlineUrl = Google2FA::getQRCodeInline(
    $companyName,
    $companyEmail,
    $secretKey
);
```

And use it in your blade template this way:

```html
<img src="{{ $inlineUrl }}">
```

```php
$secretKey = $google2fa->generateSecretKey(16, $userId);
```

## Google Authenticator secret key compatibility

To be compatible with Google Authenticator, your secret key length must be at least 8 chars and be a power of 2: 8, 16, 32, 64...
  
So, to prevent errors, you can do something like this while generating it:
  
```php
$secretKey = '123456789';
  
$secretKey = str_pad($secretKey, pow(2,ceil(log(strlen($secretKey),2))), 'X');
```

And it will generate

```
123456789XXXXXXX
```

By default, this package will enforce compatibility, but, if Google Authenticator is not a target, you can disable it by doing  

```php
$google2fa->setEnforceGoogleAuthenticatorCompatibility(false);
```

## Google Authenticator Apps:

To use the two factor authentication, your user will have to install a Google Authenticator compatible app, those are some of the currently available:

* [Authy for iOS, Android, Chrome, OS X](https://www.authy.com/)
* [FreeOTP for iOS, Android and Peeble](https://fedorahosted.org/freeotp/)
* [Google Authenticator for iOS](https://itunes.apple.com/us/app/google-authenticator/id388497605?mt=8)
* [Google Authenticator for Android](https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2)
* [Google Authenticator for BlackBerry](https://m.google.com/authenticator)
* [Google Authenticator (port) on Windows Store](https://www.microsoft.com/en-us/store/p/google-authenticator/9wzdncrdnkrf)
* [Microsoft Authenticator for Windows Phone](https://www.microsoft.com/en-us/store/apps/authenticator/9wzdncrfj3rj)
* [1Password for iOS, Android, OS X, Windows](https://1password.com)

## Tests

The package tests were written with [phpspec](http://www.phpspec.net/en/latest/).

## Author

[Antonio Carlos Ribeiro](http://twitter.com/iantonioribeiro)

## License

Google2FA is licensed under the BSD 3-Clause License - see the `LICENSE` file for details

## Contributing

Pull requests and issues are more than welcome.
