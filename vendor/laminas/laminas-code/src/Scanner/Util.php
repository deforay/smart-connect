<?php

/**
 * @see       https://github.com/laminas/laminas-code for the canonical source repository
 * @copyright https://github.com/laminas/laminas-code/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-code/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Code\Scanner;

use Laminas\Code\Exception;
use stdClass;

/**
 * Shared utility methods used by scanners
 */
class Util
{
    /**
     * Resolve imports
     *
     * @param  string $value
     * @param  null|string $key
     * @param  \stdClass $data
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    public static function resolveImports(&$value, $key = null, stdClass $data = null)
    {
        if (!is_object($data)
            || !property_exists($data, 'uses')
            || !property_exists($data, 'namespace')
        ) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects a data object containing "uses" and "namespace" properties; on or both missing',
                __METHOD__
            ));
        }

        if ($data->namespace && !$data->uses && strlen($value) > 0 && $value{0} != '\\') {
            $value = $data->namespace . '\\' . $value;

            return;
        }

        if (!$data->uses || strlen($value) <= 0 || $value{0} == '\\') {
            $value = ltrim($value, '\\');

            return;
        }

        if ($data->namespace || $data->uses) {
            $firstPart = $value;
            if (($firstPartEnd = strpos($firstPart, '\\')) !== false) {
                $firstPart = substr($firstPart, 0, $firstPartEnd);
            } else {
                $firstPartEnd = strlen($firstPart);
            }

            if (array_key_exists($firstPart, $data->uses)) {
                $value = substr_replace($value, $data->uses[$firstPart], 0, $firstPartEnd);

                return;
            }

            if ($data->namespace) {
                $value = $data->namespace . '\\' . $value;

                return;
            }
        }
    }
}
