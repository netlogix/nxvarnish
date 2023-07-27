<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Functional\ViewHelpers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class IncludeViewHelperTest extends FunctionalTestCase
{

    protected array $configurationToUseInTestInstance = [
        'FE' => [
            'cacheHash' => [
                'excludedParameters' => [
                    'untrusted',
                ],
            ],
        ],
    ];
    protected array $debugTypoScriptSettings = [
        'tx_nxvarnish.' => [
            'settings.' => [
                'esiDebugComment' => '<!-- DEBUG-TEXT %3$s DEBUG-URL %2$s -->%1$s'
            ]
        ]
    ];
    protected array $testExtensionsToLoad = ['typo3conf/ext/nxvarnish']; # ggf. muss man hier nochmal den Pfad anpassen
    protected array $coreExtensionsToLoad = ['extbase', 'fluid'];

    public static function srcAttributeDataProvider(): array
    {
        $uniquePath = sprintf('/%s', uniqid());

        return [
            'renderEsiIncludeTagWithoutSelfClosingTagWhenNotBehindReverseProxy' => [
                sprintf('<nx:include src="http://www.example.com%s"/>', $uniquePath),
                false,
                false,
                sprintf('<esi:include src="http://www.example.com%s"></esi:include>', $uniquePath),
            ],
            'renderEsiIncludeTagWithSelfClosingTagWhenBehindReverseProxy' => [
                sprintf('<nx:include src="http://www.example.com%s"/>', $uniquePath),
                true,
                false,
                sprintf('<esi:include src="http://www.example.com%s" />', $uniquePath),
            ],
            'renderEsiIncludeTagAndReplaceHttpsWithHttp' => [
                sprintf('<nx:include src="https://www.example.com%s"/>', $uniquePath),
                false,
                false,
                sprintf('<esi:include src="https://www.example.com%s"></esi:include>', $uniquePath),
            ],
            'renderEsiIncludeTagWithDebugTemplate' => [
                sprintf('<nx:include src="http://www.example.com%s"/>', $uniquePath),
                false,
                true,
                sprintf(
                    '<!-- DEBUG-TEXT None given DEBUG-URL http://www.example.com%s --><esi:include src="http://www.example.com%s"></esi:include>',
                    $uniquePath,
                    $uniquePath
                ),
            ],
            'renderEsiIncludeTagWithDebugTemplateAndCustomText' => [
                sprintf('<nx:include src="http://www.example.com%s">CustomDebugText</nx:include>', $uniquePath),
                false,
                true,
                sprintf(
                    '<!-- DEBUG-TEXT CustomDebugText DEBUG-URL http://www.example.com%s --><esi:include src="http://www.example.com%s"></esi:include>',
                    $uniquePath,
                    $uniquePath
                ),
            ],
        ];
    }

    public static function pageUidAndPageTypeDataProvider(): array
    {
        return [
            'renderEsiIncludeTagWithoutSelfClosingTagWhenNotBehindReverseProxy' => [
                '<nx:include pageUid="2" pageType="1689932803"/>',
                false,
                false,
                '<esi:include src="/dummy-1-2?type=1689932803"></esi:include>',
            ],
            'renderEsiIncludeTagWithSelfClosingTagWhenBehindReverseProxy' => [
                '<nx:include pageUid="2" pageType="1689932803"/>',
                true,
                false,
                '<esi:include src="/dummy-1-2?type=1689932803" />'
            ],
            // TODO
            'renderEsiIncludeTagWithDebugTemplate' => [
                '<nx:include pageUid="2" pageType="1689932803"/>',
                false,
                true,
                '<!-- DEBUG-TEXT None given DEBUG-URL /dummy-1-2?type=1689932803 --><esi:include src="/dummy-1-2?type=1689932803"></esi:include>',
            ],
        ];
    }

    #[DataProvider('srcAttributeDataProvider')]
    #[Test]
    public function renderWithSrcAttribute(
        string $template,
        bool $isBehindReverseProxy,
        bool $useDebugTemplate,
        string $expected
    ): void {
        $request = new ServerRequest('http://localhost/typo3/', null, 'php://input', [],
            $isBehindReverseProxy ? ['REMOTE_ADDR' => '192.0.2.1'] : []
        );
        $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        $request = $request->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromRequest(
                $request,
                array_merge(
                    $GLOBALS['TYPO3_CONF_VARS']['SYS'],
                    $isBehindReverseProxy ? ['reverseProxyIP' => '192.0.2.1'] : []
                )
            )
        );

        $frontendTypoScript = new FrontendTypoScript(new RootNode(), []);
        $frontendTypoScript->setSetupArray(['config.' => $useDebugTemplate ? $this->debugTypoScriptSettings : []]);
        $request = $request->withAttribute('frontend.typoscript', $frontendTypoScript);

        $GLOBALS['TYPO3_REQUEST'] = $request;

        $view = new StandaloneView();
        $view->setRequest($request);
        $view->setTemplateSource('{namespace nx=Netlogix\Nxvarnish\ViewHelpers}' . $template);

        $result = $view->render();

        self::assertSame($expected, $result);
    }

    #[DataProvider('pageUidAndPageTypeDataProvider')]
    #[Test]
    public function renderInFrontendWithCoreContext(
        string $template,
        bool $isBehindReverseProxy,
        bool $useDebugTemplate,
        string $expected
    ): void {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->writeSiteConfiguration(
            'test',
            $this->buildSiteConfiguration(1, '/'),
        );

        $request = new ServerRequest('http://localhost/typo3/', null, 'php://input', [],
            $isBehindReverseProxy ? ['REMOTE_ADDR' => '192.0.2.1'] : []
        );
        $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        $request = $request->withAttribute('routing', new PageArguments(1, '0', ['untrusted' => 123]));
        $request = $request->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromRequest(
                $request,
                array_merge(
                    $GLOBALS['TYPO3_CONF_VARS']['SYS'],
                    $isBehindReverseProxy ? ['reverseProxyIP' => '192.0.2.1'] : []
                )
            )
        );

        $frontendTypoScript = new FrontendTypoScript(new RootNode(), []);
        $frontendTypoScript->setSetupArray(['config.' => $useDebugTemplate ? $this->debugTypoScriptSettings : []]);
        $request = $request->withAttribute('frontend.typoscript', $frontendTypoScript);

        $GLOBALS['TYPO3_REQUEST'] = $request;

        $GLOBALS['TSFE'] = $this->createMock(TypoScriptFrontendController::class);
        $GLOBALS['TSFE']->id = 1;
        $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);

        $view = new StandaloneView();
        $view->setRequest($request);
        $view->setTemplateSource('{namespace nx=Netlogix\Nxvarnish\ViewHelpers}' . $template);

        $result = $view->render();

        self::assertSame($expected, $result);
    }

    #[DataProvider('pageUidAndPageTypeDataProvider')]
    #[Test]
    public function renderInFrontendWithExtbaseContext(
        string $template,
        bool $isBehindReverseProxy,
        bool $useDebugTemplate,
        string $expected
    ): void {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');

        $this->writeSiteConfiguration(
            'test',
            $this->buildSiteConfiguration(1, '/'),
        );

        $request = new ServerRequest(
            'http://localhost/typo3/',
            null,
            'php://input',
            [],
            $isBehindReverseProxy ? ['REMOTE_ADDR' => '192.0.2.1'] : []
        );
        $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        $request = $request->withAttribute('routing', new PageArguments(1, '0', ['untrusted' => 123]));
        $request = $request->withAttribute('extbase', new ExtbaseRequestParameters());
        $request = $request->withAttribute('currentContentObject', $this->get(ContentObjectRenderer::class));
        $request = $request->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromRequest(
                $request,
                array_merge(
                    $GLOBALS['TYPO3_CONF_VARS']['SYS'],
                    $isBehindReverseProxy ? ['reverseProxyIP' => '192.0.2.1'] : []
                )
            )
        );

        $frontendTypoScript = new FrontendTypoScript(new RootNode(), []);
        $frontendTypoScript->setSetupArray(['config.' => $useDebugTemplate ? $this->debugTypoScriptSettings : []]);
        $request = $request->withAttribute('frontend.typoscript', $frontendTypoScript);

        $GLOBALS['TYPO3_REQUEST'] = $request;

        $request = new Request($request);
        $GLOBALS['TYPO3_REQUEST'] = $request;
        $GLOBALS['TSFE'] = $this->createMock(TypoScriptFrontendController::class);
        $GLOBALS['TSFE']->id = 1;
        $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);

        $view = new StandaloneView();
        $view->setRequest($request);
        $view->setTemplateSource('{namespace nx=Netlogix\Nxvarnish\ViewHelpers}' . $template);

        $result = $view->render();
        self::assertSame($expected, $result);
    }

    #[Test]
    public function throwRuntimeExceptionWhenNotInFrontend(): void
    {
        $this->expectException(RuntimeException::class);

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->writeSiteConfiguration(
            'test',
            $this->buildSiteConfiguration(1, '/'),
        );

        $request = new ServerRequest('http://localhost/typo3/', null, 'php://input', [],
            []
        );
        $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $request = $request->withAttribute('routing', new PageArguments(1, '0', ['untrusted' => 123]));

        $frontendTypoScript = new FrontendTypoScript(new RootNode(), []);
        $frontendTypoScript->setSetupArray(['config.' => []]);
        $request = $request->withAttribute('frontend.typoscript', $frontendTypoScript);

        $GLOBALS['TYPO3_REQUEST'] = $request;

        $GLOBALS['TSFE'] = $this->createMock(TypoScriptFrontendController::class);
        $GLOBALS['TSFE']->id = 1;
        $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);

        $view = new StandaloneView();
        $view->setRequest($request);
        $view->setTemplateSource('{namespace nx=Netlogix\Nxvarnish\ViewHelpers} <nx:include />');
        $view->render();
    }

    private function writeSiteConfiguration(
        string $identifier,
        array $site = [],
        array $languages = [],
        array $errorHandling = []
    ): void {
        $configuration = $site;
        if (!empty($languages)) {
            $configuration['languages'] = $languages;
        }
        if (!empty($errorHandling)) {
            $configuration['errorHandling'] = $errorHandling;
        }
        $siteConfiguration = new SiteConfiguration(
            $this->instancePath . '/typo3conf/sites/',
            $this->get(EventDispatcherInterface::class),
            $this->get('cache.core')
        );

        try {
            // ensure no previous site configuration influences the test
            GeneralUtility::rmdir($this->instancePath . '/typo3conf/sites/' . $identifier, true);
            $siteConfiguration->write($identifier, $configuration);
        } catch (\Exception $exception) {
            $this->markTestSkipped($exception->getMessage());
        }
    }

    private function buildSiteConfiguration(
        int $rootPageId,
        string $base = ''
    ): array {
        return [
            'rootPageId' => $rootPageId,
            'base' => $base,
        ];
    }

}
