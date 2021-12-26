<?php

namespace Restruct\CookieBar\Extensions {

    use Restruct\CookieBar\Controls\CookieBarController;
    use SilverStripe\AssetAdmin\Forms\UploadField;
    use SilverStripe\Assets\Image;
    use SilverStripe\CMS\Model\SiteTree;
    use SilverStripe\Forms\CheckboxField;
    use SilverStripe\Forms\FieldList;
    use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
    use SilverStripe\Forms\TextField;
    use SilverStripe\Forms\TreeDropdownField;
    use SilverStripe\ORM\DataExtension;

    class SiteConfigExtension extends DataExtension
    {
        private static $db = [
            'CookieBarTitle'   => 'Varchar(255)',
            'CookieBarContent' => 'HTMLText',
            'CookieCloseText'  => 'Varchar(100)',
            'CookieMoreText'   => 'Varchar(150)',
            'CookieBarEnable'  => 'Boolean',
        ];

        private static $has_one = [
            'CookiePage'  => SiteTree::class,
            'CookieImage' => Image::class,
        ];

        private static $defaults = [
            'CookieCloseText'  => 'Accept',
            'CookieMoreText'   => 'Read more about Cookies',
            'CookieBarContent' => '<p><strong>Like most websites we uses cookies</strong>. In order to deliver a personalised, responsive service and to improve the site, we remember and store information about how you use it. This is done using simple text files called cookies which sit on your computer. These cookies are completely safe and secure and will never contain any sensitive information. They are used only by us.</p>',
        ];

        public function updateCMSFields(FieldList $fields)
        {

            $imageField = UploadField::create('CookieImage', 'Image (optional)');
            $imageField->getValidator()->setAllowedExtensions([ 'jpg', 'jpeg', 'gif', 'png' ]);

            $fields->addFieldsToTab('Root.CookieBar', [
                CheckboxField::create('CookieBarEnable', 'Enable Cookie Bar'),

                TextField::create('CookieBarTitle', 'Cookie Bar Title'),
                TextField::create('CookieCloseText', 'Accept/Close Link Text'),
                TextField::create('CookieMoreText', 'More Information Link Text'),
                TreeDropdownField::create('CookiePageID', 'Cookie Information Page', SiteTree::class),
                HTMLEditorField::create('CookieBarContent', 'Cookie bar Content (hidden on mobile)')->setRows(5),
                $imageField,
            ]);
        }

        public function CookieConsent()
        {
            return CookieBarController::isCookieAccepted();
        }
    }
}
