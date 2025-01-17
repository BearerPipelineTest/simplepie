<?php
/**
 * SimplePie
 *
 * A PHP-Based RSS and Atom Feed Framework.
 * Takes the hard work out of managing a complete RSS/Atom solution.
 *
 * Copyright (c) 2004-2022, Ryan Parman, Sam Sneddon, Ryan McCue, and contributors
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are
 * permitted provided that the following conditions are met:
 *
 * 	* Redistributions of source code must retain the above copyright notice, this list of
 * 	  conditions and the following disclaimer.
 *
 * 	* Redistributions in binary form must reproduce the above copyright notice, this list
 * 	  of conditions and the following disclaimer in the documentation and/or other materials
 * 	  provided with the distribution.
 *
 * 	* Neither the name of the SimplePie Team nor the names of its contributors may be used
 * 	  to endorse or promote products derived from this software without specific prior
 * 	  written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS
 * OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS
 * AND CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package SimplePie
 * @copyright 2004-2022 Ryan Parman, Sam Sneddon, Ryan McCue
 * @author Ryan Parman
 * @author Sam Sneddon
 * @author Ryan McCue
 * @link http://simplepie.org/ SimplePie
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

/**
 * This is a dirty, dirty hack
 */
class Exception_Success extends Exception
{
}

class Mock_CacheLegacy extends SimplePie_Cache
{
    public static function get_handler($location, $filename, $extension)
    {
        trigger_error('Legacy cache class should not have get_handler() called');
    }
    public function create($location, $filename, $extension)
    {
        throw new Exception_Success('Correct function called');
    }
}

class Mock_CacheNew extends SimplePie_Cache
{
    public static function get_handler($location, $filename, $extension)
    {
        throw new Exception_Success('Correct function called');
    }
    public function create($location, $filename, $extension)
    {
        trigger_error('New cache class should not have create() called');
    }
}

class CacheTest extends PHPUnit\Framework\TestCase
{
    use ExpectPHPException;

    public function testDirectOverrideLegacy()
    {
        if (PHP_VERSION_ID < 80000) {
            $this->expectException('Exception_Success');
        } else {
            // PHP 8.0 will throw a `TypeError` for trying to call a non-static method statically.
            // This is no longer supported in PHP, so there is just no way to continue to provide BC
            // for the old non-static cache methods.
            $this->expectError();
        }

        $feed = new SimplePie();
        $feed->set_cache_class('Mock_CacheLegacy');
        $feed->get_registry()->register('File', 'MockSimplePie_File');
        $feed->set_feed_url('http://example.com/feed/');

        $feed->init();
    }

    public function testDirectOverrideNew()
    {
        $this->expectException('Exception_Success');

        $feed = new SimplePie();
        $feed->get_registry()->register('Cache', 'Mock_CacheNew');
        $feed->get_registry()->register('File', 'MockSimplePie_File');
        $feed->set_feed_url('http://example.com/feed/');

        $feed->init();
    }
}
