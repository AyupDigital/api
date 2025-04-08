<?php

namespace Tests\Unit\Rules;

use App\Rules\DateSanity;
use App\Rules\StartDateLessThanEndDate;
use PHPUnit\Framework\TestCase;

class DateSanityTest extends TestCase
{
    public function test_date_greater_than_2038_is_not_accepted(): void
    {
        $rule = new DateSanity(
            date: now()->addYears(20),
        );
        $failed = false;

        $rule->validate('end_date', '', function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
    }

    public function test_date_time_greater_than_2038_is_not_accepted(): void
    {
        $rule = new DateSanity(
            date: now()->addYears(20),
            time: '12:00',
        );
        $failed = false;

        $rule->validate('end_date', '', function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
    }

    public function test_date_less_than_2038_is_accepted(): void
    {
        $rule = new DateSanity(
            date: now()->addDay(1)
        );
        $failed = false;

        $rule->validate('end_date', '', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_date_time_less_than_2038_is_accepted(): void
    {
        $rule = new DateSanity(
            date: now()->addDay(1),
            time: '12:00',
        );
        $failed = false;

        $rule->validate('end_date', '', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }
}
