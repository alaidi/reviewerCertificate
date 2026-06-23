<?php

/**
 * @file tests/TestCase.php
 *
 * Base test case for the reviewerCertificate plugin test suite.
 * Resets the in-memory DatabaseMock before every test for isolation.
 */

namespace APP\plugins\generic\reviewerCertificate\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \DatabaseMock::reset();
    }
}
