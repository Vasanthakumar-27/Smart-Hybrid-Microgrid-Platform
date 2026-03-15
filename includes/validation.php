<?php
/**
 * Input Validation & Sanitization Functions
 * Used for IoT APIs and form inputs
 */

/**
 * Validate and constrain a float within min/max bounds
 * Returns null if validation fails
 */
function validateFloat(mixed $value, float $min = -999999, float $max = 999999): ?float {
    if (!is_numeric($value)) {
        return null;
    }
    $float = (float) $value;
    if ($float < $min || $float > $max) {
        return null;
    }
    return $float;
}

/**
 * Validate and constrain an integer within min/max bounds
 */
function validateInt(mixed $value, int $min = -999999, int $max = 999999): ?int {
    if (!is_numeric($value)) {
        return null;
    }
    $int = (int) $value;
    if ($int < $min || $int > $max) {
        return null;
    }
    return $int;
}

/**
 * Validate a string with length constraints
 */
function validateString(mixed $value, int $minLen = 1, int $maxLen = 500): ?string {
    if (!is_string($value)) {
        return null;
    }
    $trimmed = trim($value);
    if (strlen($trimmed) < $minLen || strlen($trimmed) > $maxLen) {
        return null;
    }
    return $trimmed;
}

/**
 * Validate enum value
 */
function validateEnum(mixed $value, array $allowedValues): ?string {
    if (!is_string($value)) {
        return null;
    }
    if (in_array(trim($value), $allowedValues, true)) {
        return trim($value);
    }
    return null;
}

/**
 * IoT Reading validation with bounds checking
 * Returns array or associative array with errors
 */
function validateIoTReading(array $input): array {
    $errors = [];
    $data = [];

    // Microgrid ID (required)
    $mgId = validateInt($input['microgrid_id'] ?? null, 1, 999999);
    if ($mgId === null) {
        $errors['microgrid_id'] = 'Invalid microgrid_id';
    } else {
        $data['microgrid_id'] = $mgId;
    }

    // Voltage (required): typical range 200-400V
    $voltage = validateFloat($input['voltage'] ?? null, 100, 500);
    if ($voltage === null) {
        $errors['voltage'] = 'Voltage must be between 100-500 V';
    } else {
        $data['voltage'] = round($voltage, 2);
    }

    // Current (required): typical range 0-50A
    $current = validateFloat($input['current_amp'] ?? null, 0, 100);
    if ($current === null) {
        $errors['current_amp'] = 'Current must be between 0-100 A';
    } else {
        $data['current_amp'] = round($current, 3);
    }

    // Power (required): typical range 0-50kW
    $power = validateFloat($input['power_kw'] ?? null, 0, 10000);
    if ($power === null) {
        $errors['power_kw'] = 'Power must be between 0-10000 kW';
    } else {
        $data['power_kw'] = round($power, 3);
    }

    // Energy (optional): 0-1000 kWh
    $energy = $input['energy_kwh'] ?? 0;
    if ($energy !== null) {
        $validated = validateFloat($energy, 0, 10000);
        $data['energy_kwh'] = $validated !== null ? round($validated, 3) : 0;
    } else {
        $data['energy_kwh'] = 0;
    }

    // Temperature (optional): typical range -20 to 80°C
    $temp = $input['temperature'] ?? null;
    if ($temp !== null) {
        $validated = validateFloat($temp, -50, 150);
        $data['temperature'] = $validated !== null ? round($validated, 2) : null;
    } else {
        $data['temperature'] = null;
    }

    return [
        'valid' => count($errors) === 0,
        'data' => $data,
        'errors' => $errors,
    ];
}

/**
 * IoT Battery Status validation with bounds checking
 */
function validateBatteryStatus(array $input): array {
    $errors = [];
    $data = [];

    // Battery level (required): 0-100%
    $level = validateFloat($input['battery_level'] ?? null, 0, 100);
    if ($level === null) {
        $errors['battery_level'] = 'Battery level must be 0-100%';
    } else {
        $data['battery_level'] = round($level, 2);
    }

    // Voltage (required): typical 40-60V
    $voltage = validateFloat($input['voltage'] ?? null, 0, 100);
    if ($voltage === null) {
        $errors['voltage'] = 'Voltage must be 0-100 V';
    } else {
        $data['voltage'] = round($voltage, 2);
    }

    // Remaining charge (optional): kWh
    $remaining = $input['remaining_kwh'] ?? 0;
    if ($remaining !== null) {
        $validated = validateFloat($remaining, 0, 10000);
        $data['remaining_kwh'] = $validated !== null ? round($validated, 3) : 0;
    } else {
        $data['remaining_kwh'] = 0;
    }

    // Charge status (optional): charging/discharging/idle/full
    $status = validateEnum($input['charge_status'] ?? '', ['charging', 'discharging', 'idle', 'full']);
    $data['charge_status'] = $status ?? 'idle';

    // Temperature (optional): -20 to 80°C
    $temp = $input['temperature'] ?? null;
    if ($temp !== null) {
        $validated = validateFloat($temp, -50, 150);
        $data['temperature'] = $validated !== null ? round($validated, 2) : null;
    } else {
        $data['temperature'] = null;
    }

    // Battery name (optional): string
    $name = validateString($input['battery_name'] ?? '', 1, 100);
    $data['battery_name'] = $name ?? 'Main Battery';

    // Capacity (optional): kWh
    $capacity = $input['capacity_kwh'] ?? 10;
    if ($capacity !== null) {
        $validated = validateFloat($capacity, 0.1, 10000);
        $data['capacity_kwh'] = $validated !== null ? round($validated, 2) : 10.0;
    } else {
        $data['capacity_kwh'] = 10.0;
    }

    return [
        'valid' => count($errors) === 0,
        'data' => $data,
        'errors' => $errors,
    ];
}

/**
 * Sanitize error message for API response
 * Removes sensitive information (file paths, SQL fragments, etc.)
 */
function sanitizeErrorMessage(string $message): string {
    // Remove file paths (both Windows and Unix)
    $sanitized = preg_replace('~[a-zA-Z]:\\\\[^\s]+|/[^\s]+\.php~', '[sensitive]', $message);
    
    // Remove SQL fragments (keywords + quotes)
    $sanitized = preg_replace('~(SELECT|INSERT|UPDATE|DELETE|WHERE|FROM|JOIN|`|\').*~i', '[sensitive]', $sanitized);
    
    // Remove exception traces
    if (strpos($sanitized, 'Stack trace') !== false) {
        $sanitized = substr($sanitized, 0, strpos($sanitized, 'Stack trace'));
    }
    
    return trim($sanitized);
}
