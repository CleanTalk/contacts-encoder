<?php

use Cleantalk\Common\ContactsEncoder\ContactsEncoder;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use PHPUnit\Framework\TestCase;

class TestContactsEncoderAriaLabel extends TestCase
{
    /**
     * @return ContactsEncoder
     */
    private function createEncoder()
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
        $params->do_encode_phones = false;
        $params->is_logged_in = false;

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

    public function testModifyContentDoesNotRestorePlantedCtTempAriaToken()
    {
        $payload = '<blockquote cite=" aria-label=" > <a title="test">test</a></blockquote>'
            . '<a >ct_temp_aria_0</a>'
            . '<a title="style=display:block;content-visibility:auto oncontentvisibilityautostatechange=alert(2026)//">test</a>';

        $result = $this->createEncoder()->modifyContent($payload);

        $this->assertStringContainsString('ct_temp_aria_0', $result);
        $this->assertNotRegExp('/>aria-label=/', $result);
    }

    public function testModifyContentWordfenceAriaLabelXssPayloadDoesNotBreakOut()
    {
        $payload = '<blockquote cite=" aria-label=" > <a title="test">test</a></blockquote>' . "\n"
            . '<a >ct_temp_aria_0</a>'
            . '<a title="style=display:block;content-visibility:auto '
            . 'oncontentvisibilityautostatechange=alert(2026)//">test</a>';

        $result = $this->createEncoder()->modifyContent($payload);

        $this->assertTrue(strpos($result, 'ct_temp_aria_0') !== false);
        $this->assertFalse((bool) preg_match('/>aria-label=/', $result));
    }
}
