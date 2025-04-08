<?php

namespace Tests\Unit\Rules;

use App\Rules\StartDateLessThanEndDate;
use PHPUnit\Framework\TestCase;

class StartDateLessThanEndDateTest extends TestCase
{
    public function test_start_date_greater_than_end_date_is_not_accepted(): void
    {
        $rule = new StartDateLessThanEndDate(
            startDate: now()->addDays(1),
            endDate: now(),
        );
        $failed = false;

        $rule->validate('end_date', '', function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
    }

    public function test_start_date_time_greater_than_end_date_time_is_not_accepted(): void
    {
        $rule = new StartDateLessThanEndDate(
            startDate: now()->addDays(1),
            endDate: now(),
            startTime: '12:00',
            endTime: '11:00',
        );
        $failed = false;

        $rule->validate('end_date', '', function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
    }

    public function test_start_date_less_than_end_date_is_accepted(): void
    {
        $rule = new StartDateLessThanEndDate(
            startDate: now(),
            endDate: now()->addDays(1),
        );
        $failed = false;

        $rule->validate('end_date', '', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_start_date_time_less_than_end_date_time_is_accepted(): void
    {
        $rule = new StartDateLessThanEndDate(
            startDate: now(),
            endDate: now()->addDays(1),
            startTime: '11:00',
            endTime: '12:00',
        );
        $failed = false;

        $rule->validate('end_date', '', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }
}
