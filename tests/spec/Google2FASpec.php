<?php

namespace spec\PragmaRX\Google2FA;

use PhpSpec\ObjectBehavior;
use PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException;
use PragmaRX\Google2FA\Google2FA;

class Google2FASpec extends ObjectBehavior
{
    public $secret = 'ADUMJO5634NPDEKW';

    public $url = 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=otpauth%3A%2F%2Ftotp%2FPragmaRX%3Aacr%2Bpragmarx%40antoniocarlosribeiro.com%3Fsecret%3DADUMJO5634NPDEKW%26issuer%3DPragmaRX';

    public function it_is_initializable()
    {
        $this->shouldHaveType('PragmaRX\Google2FA\Google2FA');
    }

    public function it_generates_a_valid_secret_key()
    {
        $this->generateSecretKey()->shouldHaveLength(16);

        $this->generateSecretKey(32)->shouldHaveLength(32);

        $this->generateSecretKey(59, 'ant')->shouldStartWith('MFXHI');

        $this->generateSecretKey()->shouldBeAmongst(Google2FA::VALID_FOR_B32);
    }

    public function it_generates_a_secret_keys_compatible_with_google_authenticator_or_not()
    {
        $this->shouldThrow(new IncompatibleWithGoogleAuthenticatorException())->during('generateSecretKey', [17]);

        $this->setEnforceGoogleAuthenticatorCompatibility(false)->generateSecretKey(17)->shouldHaveLength(17);
    }

    public function it_gets_valid_timestamps()
    {
        $this->getTimestamp()->shouldBeValidTimestamp();
    }

    public function it_decodes_base32_strings()
    {
        $this->base32Decode($this->secret)->shouldBe(
              chr(0)
            .chr(232)
            .chr(196)
            .chr(187)
            .chr(190)
            .chr(223)
            .chr(26)
            .chr(241)
            .chr(145)
            .chr(86)
        );
    }

    public function it_creates_a_one_time_password()
    {
        $this->getCurrentOtp($this->secret)->shouldHaveLength(6);
    }

    public function it_verifies_keys()
    {
        // $ts 26213400 with KEY_REGENERATION 30 seconds is
        // timestamp 786402000, which is 1994-12-02 21:00:00 UTC

        $this->verifyKey($this->secret, '093183', 2, 26213400)->shouldBe(false); // 26213397
        $this->verifyKey($this->secret, '558854', 2, 26213400)->shouldBe(true);  // 26213398
        $this->verifyKey($this->secret, '981084', 2, 26213400)->shouldBe(true);  // 26213399
        $this->verifyKey($this->secret, '512396', 2, 26213400)->shouldBe(true);  // 26213400
        $this->verifyKey($this->secret, '410272', 2, 26213400)->shouldBe(true);  // 26213401
        $this->verifyKey($this->secret, '239815', 2, 26213400)->shouldBe(true);  // 26213402
        $this->verifyKey($this->secret, '313366', 2, 26213400)->shouldBe(false); // 26213403
    }

    public function it_verifies_keys_newer()
    {
        $this->verifyKeyNewer($this->secret, '512396', 26213401, 2, 26213400)->shouldBe(false);    // 26213400
        $this->verifyKeyNewer($this->secret, '410272', 26213401, 2, 26213400)->shouldBe(26213401);    // 26213401
        $this->verifyKeyNewer($this->secret, '239815', 26213401, 2, 26213400)->shouldBe(26213402); // 26213402
        $this->verifyKeyNewer($this->secret, '313366', 26213401, 2, 26213400)->shouldBe(false);    // 26213403
    }

    public function it_removes_invalid_chars_from_secret()
    {
        $this->removeInvalidChars($this->secret.'!1-@@@')->shouldBe($this->secret);
    }

    public function it_creates_a_qr_code()
    {
        $this->getQRCodeGoogleUrl('PragmaRX', 'acr+pragmarx@antoniocarlosribeiro.com', $this->secret)->shouldBe($this->url);
    }

    public function it_converts_to_base32()
    {
        $this->toBase32('PragmaRX')->shouldBe('KBZGCZ3NMFJFQ');
    }

    public function getMatchers()
    {
        return [
            'haveLength' => function ($subject, $key) {
                return strlen($subject) == $key;
            },

            'shouldStartWith' => function ($subject, $key) {
                return substr($key, 0, strlen($subject)) == $subject;
            },

            'beAmongst' => function ($subject, $key) {
                return preg_replace('/[^'.$key.']/', '', $subject) === $subject;
            },

            'beValidTimestamp' => function ($timestamp) {
                return is_float($timestamp)
                        && ($timestamp <= PHP_INT_MAX)
                        && ($timestamp >= ~PHP_INT_MAX);
            },

        ];
    }

    public function it_sets_the_window()
    {
        $this->setWindow(6);

        $this->getWindow()->shouldBe(6);

        $this->getWindow(1)->shouldBe(1);

        $this->setWindow(0);

        $this->verifyKey($this->secret, '558854', null, 26213400)->shouldBe(false);

        $this->setWindow(2);

        $this->verifyKey($this->secret, '558854', null, 26213400)->shouldBe(true);
        $this->verifyKey($this->secret, '558854', null, 26213399)->shouldBe(true);
        $this->verifyKey($this->secret, '558854', null, 26213398)->shouldBe(true);
        $this->verifyKey($this->secret, '558854', null, 26213396)->shouldBe(true);
        $this->verifyKey($this->secret, '558854', null, 26213395)->shouldBe(false);
    }
}
