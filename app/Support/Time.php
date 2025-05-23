<?php

namespace App\Support;

use Illuminate\Support\Facades\Date;

class Time
{
    /**
     * @var int
     */
    protected $hours;

    /**
     * @var int
     */
    protected $minutes;

    /**
     * @var int
     */
    protected $seconds;

    /**
     * Time constructor.
     */
    public function __construct(string $time)
    {
        [$hours, $minutes, $seconds] = explode(':', $time);

        $this->hours = (int) $hours;
        $this->minutes = (int) $minutes;
        $this->seconds = (int) $seconds;
    }

    public static function create(string $time): Time
    {
        return new static($time);
    }

    public static function createFromFormat(string $format, string $time): Time
    {
        $carbon = Date::createFromFormat($format, $time);

        return new static($carbon->format('H:i:s'));
    }

    public static function now(): Time
    {
        return new static(Date::now()->format('H:i:s'));
    }

    public function format(string $format): string
    {
        $carbon = Date::createFromFormat('H:i:s', $this->toString());

        return $carbon->format($format);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return sprintf('%02d:%02d:%02d', $this->hours, $this->minutes, $this->seconds);
    }

    public function between(Time $time1, Time $time2): bool
    {
        $now = Date::now()->setTime($this->hours, $this->minutes, $this->seconds);
        $time1 = Date::now()->setTime($time1->hours, $time1->minutes, $time1->seconds);
        $time2 = Date::now()->setTime($time2->hours, $time2->minutes, $time2->seconds);

        return $now->between($time1, $time2);
    }
}
