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

namespace SimplePie\Tests\Unit;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use SimplePie\File;
use SimplePie\Locator;
use SimplePie\Registry;
use SimplePie\SimplePie;
use SimplePie\Tests\Fixtures\FileMock;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

class LocatorTest extends TestCase
{
    use ExpectPHPException;

    public function testNamespacedClassExists()
    {
        $this->assertTrue(class_exists('SimplePie\Locator'));
    }

    public function testClassExists()
    {
        $this->assertTrue(class_exists('SimplePie_Locator'));
    }

    public function feedmimetypes()
    {
        return [
            ['application/rss+xml'],
            ['application/rdf+xml'],
            ['text/rdf'],
            ['application/atom+xml'],
            ['text/xml'],
            ['application/xml'],
        ];
    }
    /**
     * @dataProvider feedmimetypes
     */
    public function testAutodiscoverOnFeed($mime)
    {
        $data = new FileMock('http://example.com/feed.xml');
        $data->headers['content-type'] = $mime;

        $locator = new Locator($data, 0, null, false);

        $registry = new Registry();
        $registry->register('File', 'SimplePie\Tests\Fixtures\FileMock');
        $locator->set_registry($registry);

        $feed = $locator->find(SimplePie::LOCATOR_ALL, $all);
        $this->assertSame($data, $feed);
    }

    public function testInvalidMIMEType()
    {
        $data = new FileMock('http://example.com/feed.xml');
        $data->headers['content-type'] = 'application/pdf';

        $locator = new Locator($data, 0, null, false);

        $registry = new Registry();
        $registry->register('File', 'SimplePie\Tests\Fixtures\FileMock');
        $locator->set_registry($registry);

        $feed = $locator->find(SimplePie::LOCATOR_ALL, $all);
        $this->assertSame(null, $feed);
    }

    public function testDirectNoDOM()
    {
        $data = new FileMock('http://example.com/feed.xml');

        $registry = new Registry();
        $locator = new Locator($data, 0, null, false);
        $locator->dom = null;
        $locator->set_registry($registry);

        $this->assertTrue($locator->is_feed($data));
        $this->assertSame($data, $locator->find(SimplePie::LOCATOR_ALL, $found));
    }

    public function testFailDiscoveryNoDOM()
    {
        $this->expectException('SimplePie_Exception');

        $data = new FileMock('http://example.com/feed.xml');
        $data->headers['content-type'] = 'text/html';
        $data->body = '<!DOCTYPE html><html><body>Hi!</body></html>';

        $registry = new Registry();
        $locator = new Locator($data, 0, null, false);
        $locator->dom = null;
        $locator->set_registry($registry);

        $this->assertFalse($locator->is_feed($data));
        $this->assertFalse($locator->find(SimplePie::LOCATOR_ALL, $found));
    }

    /**
     * Tests from Firefox
     *
     * Tests are used under the LGPL license, see file for license
     * information
     */
    public function firefoxTestDataProvider()
    {
        $data = [
            [new File(dirname(__DIR__) . '/data/fftests.html')]
        ];
        foreach ($data as &$row) {
            $row[0]->headers = ['content-type' => 'text/html'];
            $row[0]->method = SimplePie::FILE_SOURCE_REMOTE;
            $row[0]->url = 'http://example.com/';
        }

        return $data;
    }

    /**
     * @dataProvider firefoxTestDataProvider
     */
    public function test_from_file($data)
    {
        $locator = new Locator($data, 0, null, false);

        $registry = new Registry();
        $registry->register('File', 'SimplePie\Tests\Fixtures\FileMock');
        $locator->set_registry($registry);

        $expected = [];
        $document = new DOMDocument();
        $document->loadHTML($data->body);
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//link') as $element) {
            $expected[] = 'http://example.com' . $element->getAttribute('href');
        }
        //$expected = SimplePie_Misc::get_element('link', $data->body);

        $feed = $locator->find(SimplePie::LOCATOR_ALL, $all);
        $this->assertFalse($locator->is_feed($data), 'HTML document not be a feed itself');
        $this->assertInstanceOf('SimplePie\Tests\Fixtures\FileMock', $feed);
        $success = array_filter($expected, [get_class(), 'filter_success']);

        $found = array_map([get_class(), 'map_url_file'], $all);
        $this->assertSame($success, $found);
    }

    protected function filter_success($url)
    {
        return (stripos($url, 'bogus') === false);
    }

    protected function map_url_file($file)
    {
        return $file->url;
    }
}