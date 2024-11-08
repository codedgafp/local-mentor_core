<?php

/**
 * array_filter_values_existing_in_string
 * Filter array of all existing string values (containing potential similary names) with expected ones from list values contained in string.
 *
 * @param  string[] $existingvalues we want to filter
 * @param  string $expectedvalues we need to ensure that $value is in
 * @return string[] list of value existing in $expectedvalues
 */
function array_filter_values_existing_in_string(array $existingvalues, string $expectedvalues): array
{
    return array_filter(
        $existingvalues,
        fn($value) => is_value_existing_in_string($value, $expectedvalues, $existingvalues)
    );
}

/**
 * is_value_existing_in_string
 * Check if the current value is existing in a list of values contained in string. Make sure that is not a similar value (seems to be in list, but is not).
 *
 * @param  string $value to check
 * @param  string $expectedvalues we need to ensure that $value is in
 * @param  string[] $existingvalues containing potential similary names (that can not be in $expectedvalues)
 * @return bool true if $value is contained in $expectedvalues, false in otherwise
 */
function is_value_existing_in_string(string $value, string $expectedvalues, array $existingvalues): bool
{
    $remainingexpectedvalues = $expectedvalues;
    $similaryvalues = array_filter(
        $existingvalues,
        fn($secondaryentityvalue) => str_contains($secondaryentityvalue, $value)
    );
    usort($similaryvalues, fn($valueA, $valueB) => strlen($valueB) - strlen($valueA));

    foreach ($similaryvalues as $similaryvalue) {
        if (str_contains($remainingexpectedvalues, $similaryvalue)) {
            if ($similaryvalue == $value) return true;
            $remainingexpectedvalues = str_replace($similaryvalue, '', $remainingexpectedvalues);
        }
    }
    return false;
}
