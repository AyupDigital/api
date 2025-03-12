<?php

namespace Modules\Kiosk\App\Helpers;

class OpeningTimesHelper
{
    public static function formattedOpeningTimes($openingHours)
    {
        $removeSecondsFromTime = function ($timeString = "") {
            $timeParts = explode(":", $timeString);

            if (count($timeParts) === 3) {
                $hours = $timeParts[0];
                $minutes = $timeParts[1];
                return "$hours:$minutes";
            }
            return $timeString;
        };

        $WEEKDAY_MAP = [
            1 => "Monday",
            2 => "Tuesday",
            3 => "Wednesday",
            4 => "Thursday",
            5 => "Friday",
            6 => "Saturday",
            7 => "Sunday",
        ];

        $getDayOfMonthString = function ($day) {
            if ($day < 1 || $day > 31) {
                return "Invalid day";
            }

            return "Every " . self::getNumberAsOrdinal($day) . " day of the month <br/>";
        };

        $getNumberAsOrdinal = function ($num) {
            $suffix = ($num === 1 || $num === 21 || $num === 31) ? "st" : (($num === 2 || $num === 22) ? "nd" : (($num === 3 || $num === 23) ? "rd" : "th"));
            return $num . $suffix;
        };

        $getOpeningHours = function ($opensAt = "", $closesAt = "") use ($removeSecondsFromTime) {
            return $removeSecondsFromTime($opensAt) . " till " . $removeSecondsFromTime($closesAt);
        };

        $getForthnightlyOpeningFrequency = function ($openingHours) {
            $startDate = new \DateTime($openingHours['starts_at']);
            $openingDayString = $startDate->format('l');

            return "Fortnightly on " . $openingDayString . "s";
        };

        $occuranceOfMonthToString = function ($number = 0) {
            switch ($number) {
                case 1:
                    return "first";
                case 2:
                    return "second";
                case 3:
                    return "third";
                case 4:
                    return "fourth";
                case 5:
                    return "last";
                default:
                    return "";
            }
        };

        $getForthnightlyNextOpeningDay = function ($openingHours) {
            $startDate = new \DateTime($openingHours['starts_at']);
            $currentDate = new \DateTime();
            $timeDifference = $startDate->getTimestamp() - $currentDate->getTimestamp();
            $daysDifference = ceil($timeDifference / (60 * 60 * 24));

            if ($daysDifference < 0) {
                $nextOpenDay = new \DateTime($openingHours['starts_at']);
                while ($nextOpenDay <= $currentDate) {
                    $nextOpenDay->modify('+14 days');
                }

                $nextOpenDayString = $nextOpenDay->format('l, M j, Y');
                return "Next occurring on " . $nextOpenDayString . ".";
            } else {
                $openDayString = $startDate->format('l, M j, Y');
                return "Next occurring on " . $openDayString . ".";
            }
        };

        $formattedTimes = function () use ($openingHours, $WEEKDAY_MAP, $getDayOfMonthString, $getOpeningHours, $getForthnightlyOpeningFrequency, $getForthnightlyNextOpeningDay, $occuranceOfMonthToString) {
            switch ($openingHours['frequency']) {
                case "weekly":
                    return "Every " . $WEEKDAY_MAP[$openingHours['weekday']] . " " . $getOpeningHours($openingHours['opens_at'], $openingHours['closes_at']);
                case "monthly":
                    return $getDayOfMonthString($openingHours['day_of_month']) . $getOpeningHours($openingHours['opens_at'], $openingHours['closes_at']);
                case "fortnightly":
                    return $getForthnightlyOpeningFrequency($openingHours) . " - " . $getForthnightlyNextOpeningDay($openingHours) . " -> " . $getOpeningHours($openingHours['opens_at'], $openingHours['closes_at']);
                case "nth_occurrence_of_month":
                    return "Every " . $occuranceOfMonthToString($openingHours['occurrence_of_month']) . " " . $WEEKDAY_MAP[$openingHours['weekday']] . " of the month - " . $getOpeningHours($openingHours['opens_at'], $openingHours['closes_at']);
                default:
                    return "";
            }
        };

        return $formattedTimes();
    }
}
