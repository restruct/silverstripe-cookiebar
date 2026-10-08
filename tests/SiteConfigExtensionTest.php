<?php

namespace Restruct\CookieBar\Tests;

use Restruct\CookieBar\Extensions\SiteConfigExtension;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\SiteConfig\SiteConfigLeftAndMain;

/**
 * The SiteConfig extension: that it applies, what schema it builds, the defaults, the CMS fields it
 * contributes and the CookieBarRunOnInitScript() helper.
 */
class SiteConfigExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    /** Every db field the module adds, with its spec. */
    private const DB_FIELDS = [
        'CookieBarTitle' => 'Varchar(255)',
        'CookieBarContent' => 'HTMLText',
        'CookieCloseText' => 'Varchar(100)',
        'CookieMoreText' => 'Varchar(150)',
        'CookieBarEnable' => 'Boolean',
        'CookieBarRunOnInit' => 'Text',
        'CookieBarRunOnConsent' => 'Text',
        'CookieBarScriptsInDevTest' => 'Boolean',
    ];

    public function testExtensionIsApplied()
    {
        $this->assertTrue(SiteConfig::has_extension(SiteConfigExtension::class));
    }

    public function testConfigStaticsArePickedUp()
    {
        $schema = DataObject::getSchema();
        $fields = $schema->databaseFields(SiteConfig::class);
        foreach (self::DB_FIELDS as $name => $spec) {
            $this->assertArrayHasKey($name, $fields, "db field $name must be merged onto SiteConfig");
            $this->assertSame($spec, $fields[$name]);
        }

        $this->assertSame(SiteTree::class, $schema->hasOneComponent(SiteConfig::class, 'CookiePage'));
        $this->assertSame(Image::class, $schema->hasOneComponent(SiteConfig::class, 'CookieImage'));
    }

    public function testDatabaseBuildCreatesTheColumns()
    {
        $columns = DB::field_list($this->siteConfigTable());
        foreach (array_keys(self::DB_FIELDS) as $name) {
            $this->assertArrayHasKey($name, $columns, "column $name must exist after the build");
        }
        $this->assertArrayHasKey('CookiePageID', $columns);
        $this->assertArrayHasKey('CookieImageID', $columns);
    }

    public function testDefaults()
    {
        $config = SiteConfig::create();

        $this->assertSame('Accept', $config->CookieCloseText);
        $this->assertSame('Read more about Cookies', $config->CookieMoreText);
        $this->assertStringContainsString('Like most websites we uses cookies', $config->CookieBarContent);
        $this->assertEmpty($config->CookieBarEnable, 'The bar must be off until an editor enables it.');
    }

    public function testCmsFieldsAreBuiltOnTheirOwnTab()
    {
        $fields = SiteConfig::current_site_config()->getCMSFields();
        $tab = $fields->findOrMakeTab('Root.CookieBar');

        $expected = [
            'CookieBarEnable' => CheckboxField::class,
            'CookieBarTitle' => TextField::class,
            'CookieCloseText' => TextField::class,
            'CookieMoreText' => TextField::class,
            'CookiePageID' => TreeDropdownField::class,
            'CookieBarContent' => HTMLEditorField::class,
            'CookieImage' => UploadField::class,
            'CookieBarRunOnInit' => TextareaField::class,
            'CookieBarRunOnConsent' => TextareaField::class,
            'CookieBarScriptsInDevTest' => CheckboxField::class,
        ];

        $actual = [];
        foreach ($tab->Fields() as $field) {
            $actual[$field->getName()] = get_class($field);
        }

        // Same fields, same classes, same order.
        $this->assertSame($expected, $actual);

        $this->assertSame('Cookie Information Page', $tab->fieldByName('CookiePageID')->Title());
        $this->assertSame(SiteTree::class, $tab->fieldByName('CookiePageID')->getSourceObject());
        $this->assertSame(
            ['jpg', 'jpeg', 'gif', 'png'],
            $tab->fieldByName('CookieImage')->getValidator()->getAllowedExtensions()
        );
    }

    public function testCmsFieldsRender()
    {
        $fields = SiteConfig::current_site_config()->getCMSFields();
        foreach (['CookieBarEnable', 'CookieBarTitle', 'CookieBarRunOnInit'] as $name) {
            $html = (string) $fields->dataFieldByName($name)->FieldHolder();
            $this->assertStringContainsString('name="' . $name . '"', $html, "$name must render an input");
        }
    }

    public function testRunOnInitScriptIsNullWhenEmpty()
    {
        $this->assertNull(SiteConfig::current_site_config()->CookieBarRunOnInitScript());
    }

    public function testRunOnInitScriptIsWrappedAndStripped()
    {
        $config = SiteConfig::current_site_config();
        $config->CookieBarRunOnInit = "window.dataLayer = [];<b>bold</b>";
        # The test runs in dev; since the hand-placed script follows the dev/test switch too (C5),
        # tick it so this test keeps checking the wrapping and stripping it was written for
        $config->CookieBarScriptsInDevTest = true;
        $config->write();

        $html = $config->CookieBarRunOnInitScript()->forTemplate();

        $this->assertSame('<script>window.dataLayer = [];bold</script>', $html);
    }

    /**
     * #5: an image uploaded into Settings > Cookie bar stayed in draft, so visitors saw no image.
     * SiteConfig is not versioned; on save the CMS publishes it recursively (versioned's
     * RecursivePublishableHandler::onAfterSave on LeftAndMain, which LeftAndMain::save() fires on
     * SS6; SS5's save_siteconfig calls publishRecursive() itself). That only reaches the image when
     * SiteConfig owns it.
     */
    public function testCookieImageIsPublishedWhenTheSettingsAreSaved()
    {
        TestAssetStore::activate('CookieBarTest');
        try {
            $image = Image::create();
            $image->setFromString(base64_decode(self::TINY_PNG), 'ckb-test.png');
            # A fresh upload is a draft-only file, as the UploadField leaves it
            $image->write();
            $this->assertFalse($image->isPublished(), 'precondition: the upload starts in draft');

            $config = SiteConfig::current_site_config();
            $config->CookieImageID = $image->ID;
            $config->write();
            # The CMS save hook, as LeftAndMain::save() fires it
            SiteConfigLeftAndMain::singleton()->extend('onAfterSave', $config);

            $this->assertTrue(
                Image::get()->byID($image->ID)->isPublished(),
                'the cookie bar image must be published together with the settings'
            );
            $this->assertContains('CookieImage', (array)SiteConfig::config()->get('owns'));
        } finally {
            TestAssetStore::reset();
        }
    }

    /**
     * #5, second cause: the template called $CookieImage.SetHeight(80), an SS3 method that does not
     * exist on SS4+ Image. A template swallows the missing method, so the bar rendered no <img> even
     * for a published image.
     */
    public function testCookieImageRendersInTheBar()
    {
        TestAssetStore::activate('CookieBarTest');
        try {
            $image = Image::create();
            $image->setFromString(base64_decode(self::TINY_PNG), 'ckb-test.png');
            $image->write();

            $config = SiteConfig::current_site_config();
            $config->CookieBarEnable = true;
            $config->CookieImageID = $image->ID;
            $config->write();

            $html = (string) ContentController::create()->CookieBar();

            $this->assertMatchesRegularExpression('#<img[^>]+height="80"#', $html, 'the bar must show the image, 80px high');
        } finally {
            TestAssetStore::reset();
        }
    }

    /** 1x1 transparent PNG */
    private const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function siteConfigTable(): string
    {
        return DataObject::getSchema()->tableName(SiteConfig::class);
    }
}
