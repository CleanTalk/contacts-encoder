<?php

use Cleantalk\Common\ContactsEncoder\ContactsEncoder;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use Cleantalk\Common\ContactsEncoder\Helper\ContactsEncoderHelper;
use PHPUnit\Framework\TestCase;

class TestContactsEncoderSchemeCase extends TestCase
{
    /**
     * @param bool $do_encode_emails
     * @param bool $do_encode_phones
     * @return ContactsEncoder
     */
    private function createEncoder($do_encode_emails = true, $do_encode_phones = true)
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

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public function mailtoSchemeProvider()
    {
        return array(
            'ticket Mailto' => array(
                '<a class="wp-block-button__link wp-element-button" href="Mailto:sarah@genuineskagitvalley.com" target="_blank" rel="noreferrer noopener">Have an event to share? Let us know.</a>',
                'sarah@genuineskagitvalley.com',
            ),
            'lowercase mailto' => array(
                '<a href="mailto:valid@example.com">Write valid@example.com</a>',
                'valid@example.com',
            ),
            'uppercase MAILTO' => array(
                '<a href="MAILTO:office@example.com">office@example.com</a>',
                'office@example.com',
            ),
        );
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public function telSchemeProvider()
    {
        return array(
            'capital Tel' => array(
                '<a class="wp-block-button__link wp-element-button" href="Tel:+19876543210">Call us</a>',
                '+19876543210',
            ),
            'lowercase tel' => array(
                '<a href="tel:+11234567890">Call +11234567890</a>',
                '+11234567890',
            ),
            'uppercase TEL' => array(
                '<a href="TEL:+441234567890">+441234567890</a>',
                '+441234567890',
            ),
        );
    }

    /**
     * @dataProvider mailtoSchemeProvider
     * @param string $content
     * @param string $email
     */
    public function testMailtoSchemeIsNormalizedAndNotInjectedIntoHref($content, $email)
    {
        $result = $this->createEncoder()->modifyContent($content);

        $this->assertStringContainsString('href="mailto:', $result);
        $this->assertStringContainsString('data-original-string=', $result);
        $this->assertStringNotContainsString('Mailto:', $result);
        $this->assertStringNotContainsString('MAILTO:', $result);
        $this->assertNotRegExp('/href="[^"]*<span/', $result);
        $this->assertStringNotContainsString($email, $result);
    }

    /**
     * @dataProvider telSchemeProvider
     * @param string $content
     * @param string $phone
     */
    public function testTelSchemeIsNormalizedAndNotInjectedIntoHref($content, $phone)
    {
        $result = $this->createEncoder()->modifyContent($content);

        $this->assertStringContainsString('href="tel:', $result);
        $this->assertStringContainsString('data-original-string=', $result);
        $this->assertStringNotContainsString('Tel:', $result);
        $this->assertStringNotContainsString('TEL:', $result);
        $this->assertNotRegExp('/href="[^"]*<span/', $result);
        $this->assertStringNotContainsString($phone, $result);
    }

    public function testHelperDetectsSchemesCaseInsensitively()
    {
        $helper = new ContactsEncoderHelper();

        $this->assertTrue($helper->isMailto('Mailto:sarah@example.com'));
        $this->assertTrue($helper->isMailto('MAILTO:sarah@example.com'));
        $this->assertTrue($helper->isMailto('mailto:sarah@example.com'));
        $this->assertFalse($helper->isMailto('sarah@example.com'));

        $this->assertTrue($helper->isTelTag('Tel:+19876543210'));
        $this->assertTrue($helper->isTelTag('TEL:+19876543210'));
        $this->assertTrue($helper->isTelTag('tel:+19876543210'));
        $this->assertFalse($helper->isTelTag('+19876543210'));
    }

    protected function tearDown(): void
    {
        $this->createEncoder(false, false)->dropInstance();
    }
}
