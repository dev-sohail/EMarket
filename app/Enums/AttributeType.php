<?php

namespace App\Enums;

enum AttributeType: string
{
    case Text = 'text';
    case Number = 'number';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Color = 'color';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Number => 'Number',
            self::Select => 'Select',
            self::Multiselect => 'Multi Select',
            self::Color => 'Color',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Text => 'primary',
            self::Number => 'info',
            self::Select => 'success',
            self::Multiselect => 'warning',
            self::Color => 'danger',
        };
    }

    /**
     * Whether the type is chosen from a list defined up front on the attribute.
     *
     * `select`, `multiselect` and `color` all pick one of the attribute's own
     * values, so those are meaningless without at least one value defined.
     * `text` and `number` are typed per product instead, so requiring a value
     * list for them made those two types impossible to create.
     */
    public function requiresPresetValues(): bool
    {
        return match ($this) {
            self::Text, self::Number => false,
            self::Select, self::Multiselect, self::Color => true,
        };
    }

    /**
     * Whether a product may hold more than one of the attribute's values.
     */
    public function allowsMultipleValues(): bool
    {
        return $this === self::Multiselect;
    }

    /**
     * Backing values for an `in:` validation rule.
     *
     * Returned as an array rather than a comma-joined string: a rule built from
     * a string is re-parsed with `str_getcsv`, which only strips the quote
     * enclosure from a field that both opens and closes with one, so a single
     * quoted `"a,b,c"` splits into `['"a', 'b', 'c"']` and nothing ever matches.
     *
     * @return array<int, string>
     */
    public static function validationValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases()
        );
    }
}
