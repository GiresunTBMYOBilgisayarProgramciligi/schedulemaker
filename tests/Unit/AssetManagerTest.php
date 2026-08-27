<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Core\AssetManager;

class AssetManagerTest extends BaseTestCase
{
    public function testInitialGlobalAssets(): void
    {
        $assetManager = new AssetManager();
        $renderedCss = $assetManager->renderCss();
        $renderedJs = $assetManager->renderJs();

        $this->assertStringContainsString('adminlte.min.css', $renderedCss);
        $this->assertStringContainsString('bootstrap.min.js', $renderedJs);
    }

    public function testAddCustomCssAndJs(): void
    {
        $assetManager = new AssetManager();
        $assetManager->addCss('/custom/style.css', ['media' => 'screen']);
        $assetManager->addJs('/custom/app.js', ['defer' => 'defer']);

        $renderedCss = $assetManager->renderCss();
        $renderedJs = $assetManager->renderJs();

        $this->assertStringContainsString('href="/custom/style.css"', $renderedCss);
        $this->assertStringContainsString('media="screen"', $renderedCss);

        $this->assertStringContainsString('src="/custom/app.js"', $renderedJs);
        $this->assertStringContainsString('defer="defer"', $renderedJs);
    }

    public function testDuplicateAssetPrevention(): void
    {
        $assetManager = new AssetManager();
        $assetManager->addCss('/custom/unique.css');
        $assetManager->addCss('/custom/unique.css');

        $renderedCss = $assetManager->renderCss();
        $this->assertEquals(1, substr_count($renderedCss, '/custom/unique.css'));
    }

    public function testLoadPageAssets(): void
    {
        $assetManager = new AssetManager();
        $assetManager->loadPageAssets('listpages');

        $renderedCss = $assetManager->renderCss();
        $renderedJs = $assetManager->renderJs();

        $this->assertStringContainsString('dataTables.bootstrap5.min.css', $renderedCss);
        $this->assertStringContainsString('dataTables.min.js', $renderedJs);
        $this->assertStringContainsString('data_table.js', $renderedJs);
    }
}
