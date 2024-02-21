<?php

namespace Restruct\CookieBar\Extensions {

    use Restruct\CookieBar\Controls\CookieBarController;
    use SilverStripe\AssetAdmin\Forms\UploadField;
    use SilverStripe\Assets\Image;
    use SilverStripe\CMS\Model\SiteTree;
    use SilverStripe\Forms\CheckboxField;
    use SilverStripe\Forms\FieldList;
    use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
    use SilverStripe\Forms\TextareaField;
    use SilverStripe\Forms\TextField;
    use SilverStripe\Forms\TreeDropdownField;
    use SilverStripe\ORM\DataExtension;
    use SilverStripe\ORM\FieldType\DBHTMLVarchar;
    use SilverStripe\SiteConfig\SiteConfig;

    class SiteConfigExtension extends DataExtension
    {
        private static $db = [
            'CookieBarTitle'   => 'Varchar(255)',
            'CookieBarContent' => 'HTMLText',
            'CookieCloseText'  => 'Varchar(100)',
            'CookieMoreText'   => 'Varchar(150)',
            'CookieBarEnable'  => 'Boolean',
            'CookieBarRunOnInit' => 'Text',
            'CookieBarRunOnConsent' => 'Text',
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

            // https://developers.google.com/tag-platform/security/concepts/consent-mode
            // https://developers.google.com/tag-platform/security/guides/consent?consentmode=advanced#implementation_example
//            $GoogleConsentModeTypes = [
//                'analytics_storage' => 'Enables storage, such as cookies (web) or device identifiers (apps), related to analytics, for example, visit duration.',
//                'ad_storage' => 'Enables storage, such as cookies (web) or device identifiers (apps), related to advertising.',
//                'ad_user_data' => 'Sets consent for sending user data to Google for online advertising purposes.',
//                'ad_personalization' => 'Sets consent for personalized advertising.',
//                'functionality_storage' => 'Enables storage that supports the functionality of the website or app, for example, language settings',
//                'personalization_storage' => 'Enables storage related to personalization, for example, video recommendations',
//                'security_storage' => 'Enables storage related to security such as authentication functionality, fraud prevention, and other user protection',
//            ];

            $fields->addFieldsToTab('Root.CookieBar', [
                CheckboxField::create('CookieBarEnable', 'Enable Cookie Bar'),

                TextField::create('CookieBarTitle', 'Cookie Bar Title'),
                TextField::create('CookieCloseText', 'Accept/Close Link Text'),
                TextField::create('CookieMoreText', 'More Information Link Text'),
                TreeDropdownField::create('CookiePageID', 'Cookie Information Page', SiteTree::class),
                HTMLEditorField::create('CookieBarContent', 'Cookie bar Content (hidden on mobile)')->setRows(5),
                $imageField,
                TextareaField::create('CookieBarRunOnInit', 'Optional RAW JS code to run on page initialisation (before any other scripts)')
                    ->setDescription('Javascript to always run on initialization/loading of page, eg for setting default consent mode and update it on consent (see placeholder content).<br>Please make sure to enter valid javascript only (any HTML tags get filtered out as a basic safety precaution).<br>NOTE: requires your templates to call $MetaTags() to include this script, alternatively you may place the script manually by adding $SiteConfig.CookieBarRunOnInitScript in the head section.')
                    ->setAttribute('placeholder', "window.dataLayer = window.dataLayer || [];
function gtag() { window.dataLayer.push(arguments); }

gtag('consent', 'default', {
  'analytics_storage': 'granted',
  'ad_storage': 'denied',
  'ad_user_data': 'denied',
  'ad_personalization': 'denied',
});")
                    ->setRows(8),
                TextareaField::create('CookieBarRunOnConsent', 'Optional RAW JS code to run on consent')
                    ->setDescription('This code gets wrapped in function cookieBarRunOnConsent, which runs when a visitor clicks the ‘accept cookies’ button.<br>Please make sure to enter valid javascript only (any HTML tags get filtered out as a basic safety precaution).')
                    ->setAttribute('placeholder', "gtag('consent', 'update', {
  'ad_storage': 'granted',
  'ad_user_data': 'granted',
  'ad_personalization': 'granted'
});")
                    ->setRows(8),

            ]);
        }

        public function CookieConsent()
        {
            return CookieBarController::isCookieAccepted();
        }

        /**
         * Wrap CookieBarRunOnInit script into <script> tag (if any)
         * @return DBHTMLVarchar|void
         */
        public function CookieBarRunOnInitScript()
        {
            if($jsToRunOnInit = SiteConfig::current_site_config()->CookieBarRunOnInit){
                $jsToRunOnInit = strip_tags($jsToRunOnInit); // just to be sure no <html> gets included...

                return DBHTMLVarchar::create()->setValue("<script>$jsToRunOnInit</script>");
            }
        }
    }
}
