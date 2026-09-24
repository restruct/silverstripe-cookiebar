<?php

namespace Restruct\CookieBar\Tests;

use Restruct\CookieBar\Extensions\SiteConfigExtension;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
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
        $config->write();

        $html = $config->CookieBarRunOnInitScript()->forTemplate();

        $this->assertSame('<script>window.dataLayer = [];bold</script>', $html);
    }

    private function siteConfigTable(): string
    {
        return DataObject::getSchema()->tableName(SiteConfig::class);
    }
}
