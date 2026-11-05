<?php

/**
 * Demographic filters for management reports (sex, age group).
 */
class ReportFilters
{
    public const AGE_YOUTH = 'youth';
    public const AGE_ADULT = 'adult';
    public const AGE_SENIOR = 'senior';

    public function __construct(
        public ?string $sex = null,
        public ?string $ageGroup = null,
    ) {
        if ($this->sex !== null && $this->sex !== 'Male' && $this->sex !== 'Female') {
            $this->sex = null;
        }
        if ($this->ageGroup !== null && !in_array($this->ageGroup, [self::AGE_YOUTH, self::AGE_ADULT, self::AGE_SENIOR], true)) {
            $this->ageGroup = null;
        }
    }

    public static function fromRequest(?string $sex, ?string $ageGroup): self
    {
        $sex = trim((string) $sex);
        $age = trim((string) $ageGroup);
        return new self(
            $sex === '' ? null : $sex,
            $age === '' ? null : $age,
        );
    }

    public function isActive(): bool
    {
        return $this->sex !== null || $this->ageGroup !== null;
    }

    /** @return array<string, string> */
    public static function sexOptions(): array
    {
        return ['' => 'All sexes', 'Male' => 'Male', 'Female' => 'Female'];
    }

    /** @return array<string, string> */
    public static function ageGroupOptions(): array
    {
        return [
            ''              => 'All ages',
            self::AGE_YOUTH => 'Youth (0–17)',
            self::AGE_ADULT => 'Adult (18–59)',
            self::AGE_SENIOR => 'Senior (60+)',
        ];
    }

    /** @return list<string> */
    public function summaryLabels(): array
    {
        $out = [];
        if ($this->sex !== null) {
            $out[] = $this->sex;
        }
        if ($this->ageGroup !== null) {
            $out[] = self::ageGroupOptions()[$this->ageGroup] ?? $this->ageGroup;
        }
        return $out;
    }

    /**
     * SQL fragment AND … for patient alias (must have sex, birth_date).
     *
     * @param list<string> $types
     * @param list<mixed>  $params
     */
    public function appendSql(string $alias, array &$types, array &$params): string
    {
        $parts = [];
        if ($this->sex !== null) {
            $parts[] = "{$alias}.sex = ?";
            $types[] = 's';
            $params[] = $this->sex;
        }
        if ($this->ageGroup !== null) {
            $parts[] = "({$alias}.birth_date IS NOT NULL AND " . self::ageConditionSql($this->ageGroup, $alias) . ')';
        }
        return $parts === [] ? '' : ' AND ' . implode(' AND ', $parts);
    }

    public static function ageConditionSql(string $ageGroup, string $alias = 'p'): string
    {
        $age = "TIMESTAMPDIFF(YEAR, {$alias}.birth_date, CURDATE())";
        return match ($ageGroup) {
            self::AGE_YOUTH => "({$age} >= 0 AND {$age} <= 17)",
            self::AGE_ADULT => "({$age} >= 18 AND {$age} <= 59)",
            self::AGE_SENIOR => "({$age} >= 60)",
            default => '1=1',
        };
    }

    /** @return array{labels:list<string>,values:list<int>} */
    public static function ageGroupLabels(): array
    {
        return [
            'labels' => ['Youth (0–17)', 'Adult (18–59)', 'Senior (60+)', 'Unknown age'],
            'keys'   => [self::AGE_YOUTH, self::AGE_ADULT, self::AGE_SENIOR, 'unknown'],
        ];
    }
}
