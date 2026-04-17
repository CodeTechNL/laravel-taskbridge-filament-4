<?php

namespace CodeTechNL\TaskBridgeFilament\Support;

use CodeTechNL\TaskBridge\Support\JobInspector;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Builds Filament form fields for a job's constructor parameters,
 * and resolves submitted form data back into a positional argument array.
 *
 * Only jobs whose constructors contain simple (scalar / untyped) parameters
 * are expected to reach this class — JobDiscoverer and JobFormBuilder both
 * enforce that contract.
 */
class JobFormBuilder
{
    /**
     * Build Filament form field components for each constructor parameter.
     *
     * Returns an empty array when the class doesn't exist, has no constructor,
     * or the constructor has no parameters.
     *
     * Field naming convention: "arg_{paramName}" — e.g. "arg_recipient".
     * This prefix avoids collisions with other form fields (run_date, etc.).
     *
     * @return Component[]
     */
    public static function buildFields(string $class): array
    {
        if (! $class || ! class_exists($class)) {
            return [];
        }

        $params = JobInspector::getConstructorParameters($class);

        if (empty($params)) {
            return [];
        }

        $fields = [];

        foreach ($params as $param) {
            $fields[] = self::buildFieldForParam($param);
        }

        return $fields;
    }

    /**
     * Extract constructor arguments from submitted form data.
     *
     * Keys matching the "arg_{name}" naming convention are cast to their
     * declared PHP type and returned as a positional array ready for splat:
     *   new $class(...$args)
     *
     * Optional parameters that were omitted from the form data fall back to
     * their declared default values.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, mixed>
     */
    public static function resolveArguments(string $class, array $data): array
    {
        if (! $class || ! class_exists($class)) {
            return [];
        }

        $params = JobInspector::getConstructorParameters($class);
        $args = [];

        foreach ($params as $param) {
            $key = "arg_{$param->getName()}";

            if (! array_key_exists($key, $data)) {
                if ($param->isOptional()) {
                    $args[] = $param->getDefaultValue();
                } elseif ($param->allowsNull()) {
                    $args[] = null;
                }

                continue;
            }

            $value = $data[$key];

            // Nullable param + empty submission → pass null so the job can fall
            // back to its own config/default logic (e.g. ?int $days = null).
            if ($param->allowsNull() && ($value === null || $value === '')) {
                $args[] = null;

                continue;
            }

            $args[] = self::castValue($value, $param);
        }

        return $args;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function buildFieldForParam(ReflectionParameter $param): Field
    {
        $name = $param->getName();
        $fieldName = "arg_{$name}";
        $label = (string) str($name)->headline();
        $required = ! $param->isOptional();

        $typeName = self::typeName($param);

        $field = match ($typeName) {
            'bool' => Select::make($fieldName)
                ->label($label)
                ->options(['1' => 'True', '0' => 'False']),

            'int' => TextInput::make($fieldName)
                ->label($label)
                ->numeric()
                ->integer(),

            'float' => TextInput::make($fieldName)
                ->label($label)
                ->numeric(),

            default => TextInput::make($fieldName)  // string or untyped
                ->label($label),
        };

        if ($required) {
            $field->required();
        }

        // Nullable optional param whose default is null: the job itself decides
        // what to do when null is received (e.g. read from config).
        // Show a clear hint so the user knows they can leave it blank.
        if ($param->allowsNull() && $param->isOptional() && $param->getDefaultValue() === null) {
            if ($field instanceof TextInput) {
                $field->placeholder('Leave empty to use the application default');
            }
            $field->helperText('Optional — leave empty to use the application default.');
        }

        return $field;
    }

    /**
     * Cast a raw form value to the PHP type declared for the parameter.
     */
    private static function castValue(mixed $value, ReflectionParameter $param): mixed
    {
        return match (self::typeName($param)) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            default => (string) $value,
        };
    }

    /**
     * Resolve the base type name for a parameter (e.g. "bool", "int", "string").
     * Returns "string" as the fallback for untyped or nullable parameters.
     */
    private static function typeName(ReflectionParameter $param): string
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        return 'string';
    }
}
