<?php

use Cleantalk\Common\ContactsEncoder\ContactsEncoder;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use Cleantalk\Common\ContactsEncoder\Helper\ContactsEncoderHelper;
use PHPUnit\Framework\TestCase;

class TestContactsEncoderMatchPosition extends TestCase
{
    /**
     * @param bool $do_encode_emails
     * @param bool $do_encode_phones
     * @return ContactsEncoder
     */
    private function createEncoder($do_encode_emails = true, $do_encode_phones = false)
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
        $params->do_encode_emails = $do_encode_emails;
        $params->do_encode_phones = $do_encode_phones;
        $params->is_logged_in = false;

        $encoder = $concrete::getInstance($params);
        $encoder->dropInstance();

        return $concrete::getInstance($params);
    }

    public function testIsMailtoAdditionalCopyUsesProvidedOffset()
    {
        $helper = new ContactsEncoderHelper();
        $email = 'dup@example.com';
        $content = '<a href="mailto:to@example.com?cc=' . $email . '">' . $email . '</a>';

        $cc_position = strpos($content, $email);
        $text_position = strpos($content, $email, $cc_position + 1);

        $this->assertTrue($helper->isMailtoAdditionalCopy($email, $content, $cc_position));
        $this->assertFalse($helper->isMailtoAdditionalCopy($email, $content, $text_position));
    }

    public function testIsInsideOptionTagUsesProvidedOffset()
    {
        $helper = new ContactsEncoderHelper();
        $email = 'user@example.com';
        $content = '<option>' . $email . '</option> Contact ' . $email;

        $option_position = strpos($content, $email);
        $visible_position = strpos($content, $email, $option_position + 1);

        $this->assertTrue($helper->isInsideOptionTag($email, $content, $option_position));
        $this->assertFalse($helper->isInsideOptionTag($email, $content, $visible_position));
    }

    public function testIsInsideScriptTagUsesProvidedOffset()
    {
        $helper = new ContactsEncoderHelper();
        $email = 'user@example.com';
        $content = '<script>var e = "' . $email . '";</script> Contact ' . $email;

        $script_position = strpos($content, $email);
        $visible_position = strpos($content, $email, $script_position + 1);

        $this->assertTrue($helper->isInsideScriptTag($email, $content, $script_position));
        $this->assertFalse($helper->isInsideScriptTag($email, $content, $visible_position));
    }

    public function testHasAttributeExclusionsUsesProvidedOffset()
    {
        $helper = new ContactsEncoderHelper();
        $email = 'user@example.com';
        $content = '<input type="email" value="' . $email . '" /> Contact ' . $email;

        $attribute_position = strpos($content, $email);
        $visible_position = strpos($content, $email, $attribute_position + 1);

        $this->assertTrue($helper->hasAttributeExclusions($email, $content, $attribute_position));
        $this->assertFalse($helper->hasAttributeExclusions($email, $content, $visible_position));
    }

    public function testModifyGlobalEmailsKeepsVisibleCopyAfterMailtoCc()
    {
        $email = 'dup@example.com';
        $content = '<a href="mailto:to@example.com?cc=' . $email . '">' . $email . '</a>';

        $result = $this->createEncoder()->modifyGlobalEmails($content);

        $this->assertFalse((bool) preg_match('/cc=' . preg_quote($email, '/') . '/', $result));
        $this->assertStringNotContainsString('>' . $email . '<', $result);
        $this->assertStringContainsString('apbct-email-encoder', $result);
    }

    public function testModifyGlobalEmailsEncodesVisibleCopyAfterOption()
    {
        $email = 'user@example.com';
        $content = '<option>' . $email . '</option> Contact ' . $email;

        $result = $this->createEncoder()->modifyGlobalEmails($content);

        $this->assertStringContainsString('<option>' . $email . '</option>', $result);
        $this->assertStringNotContainsString('Contact ' . $email, $result);
        $this->assertStringContainsString('apbct-email-encoder', $result);
    }

    public function testModifyGlobalEmailsEncodesVisibleCopyAfterScript()
    {
        $email = 'user@example.com';
        $content = '<script>var e = "' . $email . '";</script> Contact ' . $email;

        $result = $this->createEncoder()->modifyGlobalEmails($content);

        $this->assertStringContainsString('var e = "' . $email . '"', $result);
        $this->assertStringNotContainsString('Contact ' . $email, $result);
        $this->assertStringContainsString('apbct-email-encoder', $result);
    }

    public function testModifyGlobalEmailsEncodesVisibleCopyAfterInputValue()
    {
        $email = 'user@example.com';
        $content = '<input type="email" value="' . $email . '" /> Contact ' . $email;

        $result = $this->createEncoder()->modifyGlobalEmails($content);

        $this->assertStringContainsString('value="' . $email . '"', $result);
        $this->assertStringNotContainsString('Contact ' . $email, $result);
        $this->assertStringContainsString('apbct-email-encoder', $result);
    }

    public function testModifyGlobalPhoneNumbersEncodesVisibleCopyAfterScript()
    {
        $phone = '(800) 555-1234';
        $content = '<script>var p = "' . $phone . '";</script> Call ' . $phone;

        $result = $this->createEncoder(false, true)->modifyGlobalPhoneNumbers($content);

        $this->assertStringContainsString('var p = "' . $phone . '"', $result);
        $this->assertStringNotContainsString('Call ' . $phone, $result);
        $this->assertStringContainsString('apbct-email-encoder', $result);
    }

    protected function tearDown(): void
    {
        $this->createEncoder(false, false)->dropInstance();
    }
}
