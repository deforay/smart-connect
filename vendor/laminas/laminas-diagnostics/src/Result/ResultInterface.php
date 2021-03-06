<?php

/**
 * @see       https://github.com/laminas/laminas-diagnostics for the canonical source repository
 * @copyright https://github.com/laminas/laminas-diagnostics/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-diagnostics/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Diagnostics\Result;

interface ResultInterface
{
    /**
     * Get message related to the result.
     *
     * @return string
     */
    public function getMessage();

    /**
     * Get detailed info on the test result (if available).
     *
     * @return mixed|null
     */
    public function getData();
}
