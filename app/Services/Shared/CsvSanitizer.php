<?php

namespace App\Services\Shared;

/**
 * Neutralises spreadsheet formula injection in CSV/TSV cells.
 *
 * Excel, LibreOffice and Google Sheets execute a cell whose first character
 * is =, +, -, @ or a leading tab/CR/LF as a formula, so any export that lets
 * a user-influenced string (product name, customer email, coupon code, ...)
 * reach a cell unchanged can leak data or run commands when the file is
 * opened. Prefixing such a cell with a single quote is the standard
 * mitigation and is ignored by the spreadsheet when it renders the value.
 */
class CsvSanitizer
{
    /**
     * Sanitize one cell.
     *
     * The raw first character is checked before trimming: trim() strips a
     * leading tab/CR/LF, but those are exactly the characters spreadsheet
     * apps treat as formula prefixes alongside =, +, - and @. The control
     * characters must be real characters too — in a single-quoted PHP string
     * '\t' is a two-character sequence and never matches.
     */
    public static function cell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r", "\n"], true)) {
            $value = "'".$value;
        }

        return trim($value);
    }

    /**
     * Sanitize every cell of one output row.
     *
     * @param  array<array-key, mixed>  $row
     * @return array<array-key, mixed>
     */
    public static function row(array $row): array
    {
        return array_map(
            fn ($cell) => $cell === null || is_scalar($cell) ? self::cell((string) $cell) : $cell,
            $row
        );
    }
}
