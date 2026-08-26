<?php

use Cleantalk\Common\ContactsEncoder\ContactsEncoder;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use Cleantalk\Common\ContactsEncoder\Helper\ContactsEncoderHelper;
use PHPUnit\Framework\TestCase;

class TestContactsEncoderAttributeExclusions extends TestCase
{
    /**
     * @return ContactsEncoder
     */
    private function createEncoder($do_encode_phones = true)
    {
        $concrete = new class () extends ContactsEncoder {
            protected function checkRequest()
            {
                return true;
            }

            protected function getCheckRequestComment()
            {
                return '';
            }
        };

        $params = new Params();
        $params->api_key = 'test_api_key';
        $params->obfuscation_mode = Params::OBFUSCATION_MODE_BLUR;
        $params->obfuscation_text = '';
        $params->do_encode_emails = true;
        $params->do_encode_phones = $do_encode_phones;
        $params->is_logged_in = false;

        $encoder = $concrete::getInstance($params);
        $encoder->dropInstance();

        return $concrete::getInstance($params);
    }

    public function testHasAttributeExclusionsForInputDataMask()
    {
        $helper = new ContactsEncoderHelper();
        $mask = '(999) 999-9999';
        $content = '<input type="tel" class="large" data-mask="' . $mask . '" />';

        $this->assertTrue($helper->hasAttributeExclusions($mask, $content));
    }

    public function testHasAttributeExclusionsForInputPlaceholder()
    {
        $helper = new ContactsEncoderHelper();
        $email = 'info@example.com';
        $content = '<input type="email" placeholder="' . $email . '" />';

        $this->assertTrue($helper->hasAttributeExclusions($email, $content));
    }

    public function testHasAttributeExclusionsReturnsFalseForPlainPhone()
    {
        $helper = new ContactsEncoderHelper();
        $phone = '(800) 555-1234';

        $this->assertFalse($helper->hasAttributeExclusions($phone, 'Call us at ' . $phone));
    }

    public function testModifyContentDoesNotEncodeGravityFormsPhoneMask()
    {
        $mask = '(999) 999-9999';
        $visible_phone = '(800) 555-1234';
        $content = 'Call ' . $visible_phone
            . ' <input type="tel" class="large" data-mask="' . $mask . '" />';

        $result = $this->createEncoder(true)->modifyContent($content);

        $this->assertStringContainsString('data-mask="' . $mask . '"', $result);
        $this->assertStringNotContainsString($visible_phone, $result);
        $this->assertStringContainsString('apbct-email-encoder', $result);
    }

    public function testModifyContentDoesNotEncodePlaceholderPhoneMask()
    {
        $mask = '(999) 999-9999';
        $content = '<input type="tel" placeholder="' . $mask . '" />';

        $result = $this->createEncoder(true)->modifyContent($content);

        $this->assertStringContainsString('placeholder="' . $mask . '"', $result);
    }

    protected function tearDown(): void
    {
        $this->createEncoder(false)->dropInstance();
    }
}
