<?php

declare(strict_types=1);

namespace TaskQueue\Scheduling;

class CronExpression
{
    private string $expression;
    private array $fields = [
        'minute' => [],
        'hour' => [],
        'day' => [],
        'month' => [],
        'dayofweek' => []
    ];

    public function __construct(string $expression)
    {
        $this->expression = trim($expression);
        $this->parseExpression();
    }

    public static function create(string $expression): self
    {
        return new self($expression);
    }

    private function parseExpression(): void
    {
        $parts = explode(' ', $this->expression);

        if (count($parts) !== 5) {
            throw new \InvalidArgumentException('Cron expression must have exactly 5 fields');
        }

        $this->fields['minute'] = $this->parseField($parts[0], 0, 59);
        $this->fields['hour'] = $this->parseField($parts[1], 0, 23);
        $this->fields['day'] = $this->parseField($parts[2], 1, 31);
        $this->fields['month'] = $this->parseField($parts[3], 1, 12);
        $this->fields['dayofweek'] = $this->parseField($parts[4], 0, 6);
    }

    private function parseField(string $field, int $min, int $max): array
    {
        if ($field === '*') {
            return range($min, $max);
        }

        $values = [];
        $parts = explode(',', $field);

        foreach ($parts as $part) {
            $values = array_merge($values, $this->parseFieldPart($part, $min, $max));
        }

        return array_unique($values);
    }

    private function parseFieldPart(string $part, int $min, int $max): array
    {
        if (strpos($part, '/') !== false) {
            [$range, $step] = explode('/', $part, 2);
            $step = (int) $step;

            if ($range === '*') {
                $range = $min . '-' . $max;
            }

            [$start, $end] = explode('-', $range, 2);
            $start = (int) $start;
            $end = (int) $end;

            $values = [];
            for ($i = $start; $i <= $end; $i += $step) {
                $values[] = $i;
            }

            return $values;
        }

        if (strpos($part, '-') !== false) {
            [$start, $end] = explode('-', $part, 2);
            return range((int) $start, (int) $end);
        }

        return [(int) $part];
    }

    public function isDue(?\DateTimeInterface $date = null): bool
    {
        $date = $date ?? new \DateTime();

        return $this->isMinuteDue($date) &&
            $this->isHourDue($date) &&
            $this->isDayDue($date) &&
            $this->isMonthDue($date) &&
            $this->isDayOfWeekDue($date);
    }

    private function isMinuteDue(\DateTimeInterface $date): bool
    {
        return in_array((int) $date->format('i'), $this->fields['minute'], true);
    }

    private function isHourDue(\DateTimeInterface $date): bool
    {
        return in_array((int) $date->format('G'), $this->fields['hour'], true);
    }

    private function isDayDue(\DateTimeInterface $date): bool
    {
        return in_array((int) $date->format('j'), $this->fields['day'], true);
    }

    private function isMonthDue(\DateTimeInterface $date): bool
    {
        return in_array((int) $date->format('n'), $this->fields['month'], true);
    }

    private function isDayOfWeekDue(\DateTimeInterface $date): bool
    {
        return in_array((int) $date->format('w'), $this->fields['dayofweek'], true);
    }

    public function getNextRunDate(?\DateTimeInterface $date = null): \DateTime
    {
        $date = $date ?? new \DateTime();
        $next = clone $date;
        $next->setTime((int) $next->format('H'), (int) $next->format('i'), 0);

        while (!$this->isDue($next)) {
            $next->modify('+1 minute');

            // Prevent infinite loops
            if ($next->getTimestamp() - $date->getTimestamp() > 86400 * 365) {
                throw new \RuntimeException('Unable to find next run date within a year');
            }
        }

        return $next;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function __toString(): string
    {
        return $this->expression;
    }
}
