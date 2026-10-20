<?php

namespace Tests\Unit\Support;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_dial_code_for_supported_countries(): void
    {
        $this->assertSame('+234', PhoneNumber::dialCode('NG'));
        $this->assertSame('+1', PhoneNumber::dialCode('us'));
        $this->assertSame('+44', PhoneNumber::dialCode('GB'));
        $this->assertNull(PhoneNumber::dialCode('XX'));
    }

    public function test_compose_builds_e164_and_strips_trunk_zero(): void
    {
        $this->assertSame('+2348012345678', PhoneNumber::compose('NG', '08012345678'));
        $this->assertSame('+15551234567', PhoneNumber::compose('US', '5551234567'));
        $this->assertNull(PhoneNumber::compose('NG', ''));
    }

    public function test_split_uses_country_dial_prefix(): void
    {
        [$dial, $local] = PhoneNumber::split('+2348012345678', 'NG');
        $this->assertSame('+234', $dial);
        $this->assertSame('8012345678', $local);
    }
}
