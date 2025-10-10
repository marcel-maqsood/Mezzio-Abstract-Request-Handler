<?php

declare(strict_types=1);

namespace MazeDEV\AbstractRequestHandler;

use function array_key_exists;
use function count;
use function explode;
use function is_array;
use function is_scalar;

class Translator
{
	public static $instance;
    public static array $language = [];

    private function __construct() {}

	public static function getInstance(): self
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	public static function setLanguage(array $language)
	{
		self::$language = $language;
	}

    /**
     * Übersetzt einen Punkt-notation Key, z. B. "key1.key2.value1".
     * Unterstützt NUR Arrays. Gibt nur skalare Werte als string zurück oder null.
     */
    public static function translate(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        $value = self::keyWalker(self::$language, $key);

        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Läuft den Punkt-Pfad durch (nur Arrays).
     *
     * @param array  $curDepth
     * @return mixed|null
     */
    private static function keyWalker(array $curDepth, string $key)
    {
        $parts     = explode('.', $key);
        $current   = $curDepth;
        $lastIndex = count($parts) - 1;

        foreach ($parts as $i => $part) {
            if (! is_array($current) || ! array_key_exists($part, $current)) {
                return null;
            }

            $current = $current[$part];

            if ($i < $lastIndex && ! is_array($current)) {
                return null;
            }
        }

        return $current;
    }
}
