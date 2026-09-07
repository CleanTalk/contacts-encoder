<?php

use Cleantalk\Common\ContactsEncoder\ContactsEncoder;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use PHPUnit\Framework\TestCase;

class TestContactsEncoderAriaLabel extends TestCase
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

    public function testModifyContentPreservesAriaLabelWithEmail()
    {
        $email = 'info@example.com';
        $content = '<button aria-label="Contact us at ' . $email . '">Click</button>';

        $result = $this->createEncoder()->modifyContent($content);

        $this->assertStringContainsString('aria-label="Contact us at ' . $email . '"', $result);
        $this->assertStringNotContainsString('%%APBCT_ARIA_', $result);
        $this->assertStringNotContainsString('ct_temp_aria_', $result);
    }

    public function testModifyContentPreservesAriaLabelWhenEmailAndPhoneEncodingEnabled()
    {
        $email = 'info@example.com';
        $phone = '(800) 555-1234';
        $content = '<button aria-label="Call ' . $phone . ' or email ' . $email . '">Click</button>'
            . ' Visible ' . $phone . ' and ' . $email;

        $result = $this->createEncoder(true, true)->modifyContent($content);

        $this->assertStringContainsString(
            'aria-label="Call ' . $phone . ' or email ' . $email . '"',
            $result
        );
        $this->assertStringNotContainsString('%%APBCT_ARIA_', $result);
        $this->assertStringNotContainsString('Visible ' . $phone, $result);
        $this->assertStringNotContainsString('Visible ' . $email, $result);
        $this->assertStringContainsString('apbct-email-encoder', $result);
    }

    public function testModifyContentDoesNotRestorePlantedCtTempAriaToken()
    {
        $payload = '<blockquote cite=" aria-label=" > <a title="test">test</a></blockquote>'
            . '<a >ct_temp_aria_0</a>'
            . '<a title="style=display:block;content-visibility:auto oncontentvisibilityautostatechange=alert(2026)//">test</a>';

        $result = $this->createEncoder()->modifyContent($payload);

        $this->assertTrue(strpos($result, 'ct_temp_aria_0') !== false);
        $this->assertFalse((bool) preg_match('/>\s*aria-label\s*=/', $result));
    }

    public function testModifyContentWordfenceAriaLabelXssPayloadDoesNotBreakOut()
    {
        $payload = '<blockquote cite=" aria-label=" > <a title="test">test</a></blockquote>' . "\n"
            . '<a >ct_temp_aria_0</a>'
            . '<a title="style=display:block;content-visibility:auto '
            . 'oncontentvisibilityautostatechange=alert(2026)//">test</a>';

        $result = $this->createEncoder()->modifyContent($payload);

        $this->assertTrue(strpos($result, 'ct_temp_aria_0') !== false);
        $this->assertFalse((bool) preg_match('/>\s*aria-label\s*=/', $result));
    }

    public function testModifyGlobalEmailsDirectCallDoesNotCrashOnHelperChecks()
    {
        $script_email = 'script@example.com';
        $visible_email = 'info@example.com';
        $content = '<script>var e = "' . $script_email . '";</script> Contact ' . $visible_email;

        $result = $this->createEncoder()->modifyGlobalEmails($content);

        $this->assertStringContainsString('var e = "' . $script_email . '"', $result);
        $this->assertStringNotContainsString('Contact ' . $visible_email, $result);
        $this->assertStringContainsString('apbct-email-encoder', $result);
    }

    public function testModifyGlobalPhoneNumbersDirectCallDoesNotCrashOnHelperChecks()
    {
        $script_phone = '(800) 555-1234';
        $visible_phone = '(800) 555-9999';
        $content = '<script>var p = "' . $script_phone . '";</script> Call ' . $visible_phone;

        $result = $this->createEncoder(false, true)->modifyGlobalPhoneNumbers($content);

        $this->assertStringContainsString('var p = "' . $script_phone . '"', $result);
        $this->assertStringNotContainsString('Call ' . $visible_phone, $result);
        $this->assertStringContainsString('apbct-email-encoder', $result);
    }

    protected function tearDown(): void
    {
        $this->createEncoder(false, false)->dropInstance();
    }
}
