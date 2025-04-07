<?php

namespace Tests\Unit\Rules;

use App\Rules\UkPhoneNumber;
use PHPUnit\Framework\TestCase;

class UkPhoneNumberTest extends TestCase
{
    public function test_uk_phone_number_validation_accepts_six_digits(): void
    {
        $rule = new UkPhoneNumber;
        $number = '116123';

        $failed = false;

        $rule->validate('phone_number', $number, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_uk_mobile_phone_number_validation(): void
    {
        $rule = new UkPhoneNumber;
        $standardMobileNumber = '07700900123';
        $mobileNumberWithPlus = '+447700900123';

        $failed = false;
        $rule->validate('phone_number', $standardMobileNumber, function () use (&$failed) {
            $failed = true;
        });
        $this->assertFalse($failed);

        $failed = false;
        $rule->validate('phone_number', $mobileNumberWithPlus, function () use (&$failed) {
            $failed = true;
        });
        $this->assertFalse($failed);
    }

    public function test_uk_phone_number_validation_rejects_seven_digits(): void
    {
        $rule = new UkPhoneNumber;
        $number = '1161234';
        $failed = false;
        $rule->validate('phone_number', $number, function () use (&$failed) {
            $failed = true;
        });
        $this->assertTrue($failed);
    }

    public function test_uk_phone_number_validation_accepts_landline_numbers(): void
    {
        $rule = new UkPhoneNumber;
        $number = '02079460000';
        $failed = false;
        $rule->validate('phone_number', $number, function () use (&$failed) {
            $failed = true;
        });
        $this->assertFalse($failed);
    }
}
