<?php

use Cleantalk\Common\ContactsEncoder\ContactsEncoder;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cleantalk\Common\ContactsEncoder\ContactsEncoder::prepareRegularExpressions
 */
class ContactsEncoderPatternsTest extends TestCase
{
    private function createConcreteContactsEncoder(Params $params)
    {
        $concrete_contacts_encoder_class = new class () extends ContactsEncoder {
            protected function checkRequest()
            {
                return true;
            }

            protected function getCheckRequestComment()
            {
                return '';
            }

            public function getProperty($propertyName)
            {
                $reflection = new \ReflectionClass($this);
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);
                return $property->getValue($this);
            }
        };
        return $concrete_contacts_encoder_class::getInstance($params);
    }

    private function getTestParams()
    {
        $params = new Params();
        $params->api_key = 'test_api_key';
        $params->obfuscation_mode = 'blur';
        $params->obfuscation_text = '';
        $params->do_encode_emails = true;
        $params->do_encode_phones = true;
        $params->is_logged_in = false;

        return $params;
    }

    public function testAriaRegexProperty()
    {

        $encoder = $this->createConcreteContactsEncoder($this->getTestParams());
        $ariaRegex = $encoder->getProperty('aria_regex');
        $this->assertEquals('/aria-label.?=.?[\'"].+?[\'"]/', $ariaRegex);
    }

    public function testGlobalEmailPatternProperty()
    {
        $encoder = $this->createConcreteContactsEncoder($this->getTestParams());
        $pattern = $encoder->getProperty('global_email_pattern');
        $expected = '/(mailto\:\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}\b)|(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}\b)/';
        $this->assertEquals($expected, $pattern);
    }

    public function testGlobalPhonesPatternProperty()
    {
        $encoder = $this->createConcreteContactsEncoder($this->getTestParams());
        $pattern = $encoder->getProperty('global_phones_pattern');
        $expected = '/(tel:\+\d{8,12})|([\+][\s-]?\(?\d[\d\s\-()]{7,}\d)|(\(\d{3}\)\s?\d{3}-\d{4})|(\+\d{1,3}\.\d{1,3}\.((\d{3}\.\d{4})|\d{7})(?![\w.]))/';
        $this->assertEquals($expected, $pattern);
    }

    public function testGlobalMailtoPatternProperty()
    {
        $encoder = $this->createConcreteContactsEncoder($this->getTestParams());
        $pattern = $encoder->getProperty('global_mailto_pattern');
        $expected = '/mailto\:([_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,})/';
        $this->assertEquals($expected, $pattern);
    }

    public function testPlainEmailPatternProperty()
    {
        $encoder = $this->createConcreteContactsEncoder($this->getTestParams());
        $pattern = $encoder->getProperty('plain_email_pattern');
        $expected = '/(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}\b)/';
        $this->assertEquals($expected, $pattern);
    }

    public function testPlainEmailPatternWithoutCapturingProperty()
    {
        $encoder = $this->createConcreteContactsEncoder($this->getTestParams());
        $pattern = $encoder->getProperty('plain_email_pattern_without_capturing');
        $expected = '/\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}/';
        $this->assertEquals($expected, $pattern);
    }

    public function testGlobalTelPatternProperty()
    {
        $encoder = $this->createConcreteContactsEncoder($this->getTestParams());
        $pattern = $encoder->getProperty('global_tel_pattern');
        $expected = '/tel:(\+\d{8,12})/';
        $this->assertEquals($expected, $pattern);
    }
}
