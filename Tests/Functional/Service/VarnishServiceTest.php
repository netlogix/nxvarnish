<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Functional\Service;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Netlogix\Nxvarnish\Service\VarnishService;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use Psr\Http\Message\RequestInterface;

class VarnishServiceTest extends FunctionalTestCase
{
    protected $testExtensionsToLoad = ['typo3conf/ext/nxvarnish'];

    protected $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'nxvarnish' => [
                'varnishHost' => 'http://varnish.example.com:8080',
            ],
        ]
    ];

    /**
     * @test
     * @return void
     */
    public function banTagCreatesRequestUsingBanMethod()
    {
        $tag = uniqid();

        $mock = new MockHandler([
            function (RequestInterface $request, array $options) use ($tag) {
                if ($request->getMethod() != 'BAN') {
                    self::fail('Expected Request to use method "BAN"');
                }

                return new Response();
            }
        ]);
        $stack = HandlerStack::create($mock);

        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'] = $stack;

        $subject = new VarnishService();

        $subject->banTag($tag);

        self::assertTrue(true);
    }

    /**
     * @test
     * @return void
     */
    public function banTagCreatesRequestIncludingVarnishHeader()
    {
        $tag = uniqid();

        $mock = new MockHandler([
            function (RequestInterface $request, array $options) use ($tag) {
                if (!$request->hasHeader('X-Cache-Tags')) {
                    self::fail('Expected Request to use header "X-Cache-Tags"');
                }

                if (stripos($request->getHeaderLine('X-Cache-Tags'), $tag) === false) {
                    self::fail('Expected Request to use header "X-Cache-Tags" containing tag');
                }

                return new Response();
            }
        ]);
        $stack = HandlerStack::create($mock);

        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'] = $stack;

        $subject = new VarnishService();

        $subject->banTag($tag);

        self::assertTrue(true);
    }

}
