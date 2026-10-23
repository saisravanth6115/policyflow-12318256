<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class PremiumCalculator
{
    public function calculate(
        string $dateOfBirth,
        string $tripStart,
        string $tripEnd,
        string $planCode,
        string $destination,
        int $sumInsured
    ): array {
        $baseRates = [
            'BASIC' => 1200,
            'PLUS' => 2000,
            'PREMIUM' => 3000,
        ];

        $coverageMultipliers = [
            100000 => 10000,
            300000 => 12500,
            500000 => 15000,
        ];

        $planCode = strtoupper($planCode);
        $destination = strtoupper($destination);

        if (!isset($baseRates[$planCode])) {
            throw new InvalidArgumentException('Unsupported plan code.');
        }

        if (!in_array($destination, ['DOMESTIC', 'INTERNATIONAL'], true)) {
            throw new InvalidArgumentException('Unsupported destination.');
        }

        if (!isset($coverageMultipliers[$sumInsured])) {
            throw new InvalidArgumentException('Unsupported sum insured.');
        }

        $start = Carbon::parse($tripStart)->startOfDay();
        $end = Carbon::parse($tripEnd)->startOfDay();
        $dob = Carbon::parse($dateOfBirth)->startOfDay();

        if ($end->lt($start)) {
            throw new InvalidArgumentException(
                'Trip end must be on or after trip start.'
            );
        }

        if ($dob->gte($start)) {
            throw new InvalidArgumentException(
                'Date of birth must be before trip start.'
            );
        }

        $age = (int) $dob->diffInYears($start);

        $ageMultiplier = match (true) {
            $age <= 17 => 11000,
            $age <= 60 => 10000,
            $age <= 70 => 15000,
            default => 20000,
        };

        $destinationMultiplier = $destination === 'INTERNATIONAL'
            ? 18000
            : 10000;

        $travelDays = (int) $start->diffInDays($end) + 1;

        $numerator = $baseRates[$planCode]
            * $travelDays
            * $ageMultiplier
            * $destinationMultiplier
            * $coverageMultipliers[$sumInsured];

        $denominator = 10000 ** 3;

        $premiumPaise = intdiv(
            $numerator + intdiv($denominator, 2),
            $denominator
        );

        return [
            'travel_days' => $travelDays,
            'premium' => number_format($premiumPaise / 100, 2, '.', ''),
            'age_at_trip_start' => $age,
        ];
    }
}