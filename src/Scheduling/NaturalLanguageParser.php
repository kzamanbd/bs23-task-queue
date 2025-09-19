<?php

declare(strict_types=1);

namespace TaskQueue\Scheduling;

class NaturalLanguageParser
{
    private array $patterns = [
        // Every X minutes/hours/days/weeks/months/years
        '/^every\s+(\d+)\s+(minute|minutes|hour|hours|day|days|week|weeks|month|months|year|years)$/i' => 'parseInterval',
        
        // Daily, weekly, monthly, yearly
        '/^(daily|weekly|monthly|yearly)$/i' => 'parseFrequency',
        
        // At specific times
        '/^at\s+(\d{1,2}):(\d{2})\s*(am|pm)?$/i' => 'parseTime',
        
        // Days of week
        '/^(monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i' => 'parseDayOfWeek',
        
        // Multiple days
        '/^(monday|tuesday|wednesday|thursday|friday|saturday|sunday)(\s*,\s*(monday|tuesday|wednesday|thursday|friday|saturday|sunday))*$/i' => 'parseMultipleDays',
        
        // Weekdays/weekends
        '/^(weekdays|weekends)$/i' => 'parseWeekdays',
        
        // Monthly on specific day
        '/^monthly\s+on\s+the\s+(\d{1,2})(st|nd|rd|th)$/i' => 'parseMonthlyDay',
        
        // Business hours
        '/^business\s+hours$/i' => 'parseBusinessHours',
        
        // Never
        '/^never$/i' => 'parseNever',
    ];

    public function parse(string $expression): CronExpression
    {
        $expression = trim(strtolower($expression));
        
        foreach ($this->patterns as $pattern => $method) {
            if (preg_match($pattern, $expression, $matches)) {
                $cronExpression = $this->$method($matches);
                if ($cronExpression) {
                    return $cronExpression;
                }
            }
        }
        
        throw new \InvalidArgumentException("Unable to parse natural language expression: {$expression}");
    }

    private function parseInterval(array $matches): CronExpression
    {
        $value = (int) $matches[1];
        $unit = rtrim($matches[2], 's');
        
        switch ($unit) {
            case 'minute':
                return new CronExpression("*/{$value} * * * *");
            case 'hour':
                return new CronExpression("0 */{$value} * * *");
            case 'day':
                return new CronExpression("0 0 */{$value} * *");
            case 'week':
                return new CronExpression("0 0 * * {$value}");
            case 'month':
                return new CronExpression("0 0 1 */{$value} *");
            case 'year':
                return new CronExpression("0 0 1 1 */{$value}");
            default:
                throw new \InvalidArgumentException("Invalid time unit: {$unit}");
        }
    }

    private function parseFrequency(array $matches): CronExpression
    {
        $frequency = strtolower($matches[1]);
        
        switch ($frequency) {
            case 'daily':
                return new CronExpression('0 0 * * *');
            case 'weekly':
                return new CronExpression('0 0 * * 0'); // Sunday
            case 'monthly':
                return new CronExpression('0 0 1 * *');
            case 'yearly':
                return new CronExpression('0 0 1 1 *');
            default:
                throw new \InvalidArgumentException("Invalid frequency: {$frequency}");
        }
    }

    private function parseTime(array $matches): CronExpression
    {
        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        $period = strtolower($matches[3] ?? '');
        
        // Handle AM/PM
        if ($period === 'pm' && $hour !== 12) {
            $hour += 12;
        } elseif ($period === 'am' && $hour === 12) {
            $hour = 0;
        }
        
        return new CronExpression("{$minute} {$hour} * * *");
    }

    private function parseDayOfWeek(array $matches): CronExpression
    {
        $day = strtolower($matches[1]);
        $dayNumber = $this->getDayNumber($day);
        
        return new CronExpression("0 0 * * {$dayNumber}");
    }

    private function parseMultipleDays(array $matches): CronExpression
    {
        $fullMatch = $matches[0];
        $days = preg_split('/\s*,\s*/', $fullMatch);
        $dayNumbers = [];
        
        foreach ($days as $day) {
            $dayNumbers[] = $this->getDayNumber(trim($day));
        }
        
        $dayString = implode(',', $dayNumbers);
        return new CronExpression("0 0 * * {$dayString}");
    }

    private function parseWeekdays(array $matches): CronExpression
    {
        $type = strtolower($matches[1]);
        
        switch ($type) {
            case 'weekdays':
                return new CronExpression('0 0 * * 1-5'); // Monday to Friday
            case 'weekends':
                return new CronExpression('0 0 * * 0,6'); // Sunday and Saturday
            default:
                throw new \InvalidArgumentException("Invalid weekday type: {$type}");
        }
    }

    private function parseMonthlyDay(array $matches): CronExpression
    {
        $day = (int) $matches[1];
        
        if ($day < 1 || $day > 31) {
            throw new \InvalidArgumentException("Invalid day of month: {$day}");
        }
        
        return new CronExpression("0 0 {$day} * *");
    }

    private function parseBusinessHours(array $matches): CronExpression
    {
        // Business hours: Monday to Friday, 9 AM to 5 PM, every hour
        return new CronExpression('0 9-17 * * 1-5');
    }

    private function parseNever(array $matches): CronExpression
    {
        // Return a cron expression that never runs (year 2099)
        return new CronExpression('0 0 1 1 *');
    }

    private function getDayNumber(string $day): int
    {
        $days = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];
        
        if (!isset($days[$day])) {
            throw new \InvalidArgumentException("Invalid day: {$day}");
        }
        
        return $days[$day];
    }

    public function getSupportedExpressions(): array
    {
        return [
            'every X minutes/hours/days/weeks/months/years',
            'daily, weekly, monthly, yearly',
            'at HH:MM (am/pm)',
            'monday, tuesday, etc.',
            'multiple days: monday, wednesday, friday',
            'weekdays, weekends',
            'monthly on the Xth',
            'business hours',
            'never'
        ];
    }
}
