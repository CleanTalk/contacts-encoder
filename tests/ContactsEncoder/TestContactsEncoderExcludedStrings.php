<?php

use Cleantalk\Common\ContactsEncoder\ContactsEncoder;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use Cleantalk\Common\ContactsEncoder\Exclusions\ExclusionsService;
use PHPUnit\Framework\TestCase;

class TestContactsEncoderExcludedStrings extends TestCase
{
    /**
     * @param string[] $excluded_strings
     * @param bool $do_encode_emails
     * @param bool $do_encode_phones
     * @return ContactsEncoder
     */
    private function createEncoder($excluded_strings = array(), $do_encode_emails = true, $do_encode_phones = false)
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
        $params->excluded_strings = $excluded_strings;

        $encoder = $concrete::getInstance($params);
        $encoder->dropInstance();

        return $concrete::getInstance($params);
    }

    public function testParseExcludedStringsSplitsLinesAndCommas()
    {
        $parsed = ExclusionsService::parseExcludedStrings("keep@example.com\n+1 800 555-1234, example.com\n");

        $this->assertSame(
            array('keep@example.com', '+1 800 555-1234', 'example.com'),
            $parsed
        );
    }

    public function testParseExcludedStringsIgnoresEmptyInput()
    {
        $this->assertSame(array(), ExclusionsService::parseExcludedStrings(''));
        $this->assertSame(array(), ExclusionsService::parseExcludedStrings(" \n , \r\n "));
    }

    public function testModifyGlobalEmailsKeepsExcludedAddress()
    {
        $keep = 'keep@example.com';
        $encode = 'public@example.com';
        $content = 'Contact ' . $keep . ' or ' . $encode;

        $result = $this->createEncoder(array($keep))->modifyGlobalEmails($content);

        $this->assertStringContainsString($keep, $result);
        $this->assertStringNotContainsString($encode, $result);
        $this->assertStringContainsString('apbct-email-encoder', $result);
    }

    public function testModifyGlobalEmailsKeepsMailtoWhenAddressIsExcluded()
    {
        $email = 'keep@example.com';
        $content = '<a href="mailto:' . $email . '">' . $email . '</a>';

        $result = $this->createEncoder(array($email))->modifyGlobalEmails($content);

        $this->assertStringContainsString('mailto:' . $email, $result);
        $this->assertStringContainsString('>' . $email . '<', $result);
        $this->assertStringNotContainsString('apbct-email-encoder', $result);
    }

    public function testModifyGlobalEmailsKeepsDomainFragmentMatches()
    {
        $email = 'office@company.org';
        $content = 'Write ' . $email;

        $result = $this->createEncoder(array('company.org'))->modifyGlobalEmails($content);

        $this->assertStringContainsString($email, $result);
        $this->assertStringNotContainsString('apbct-email-encoder', $result);
    }

    public function testModifyGlobalPhoneNumbersKeepsDigitEquivalent()
    {
        $keep = '(800) 555-1234';
        $encode = '(800) 555-9999';
        $content = 'Call ' . $keep . ' or ' . $encode;

        $result = $this->createEncoder(array('+1 800 555-1234'), false, true)
            ->modifyGlobalPhoneNumbers($content);

        $this->assertStringContainsString($keep, $result);
        $this->assertStringNotContainsString($encode, $result);
        $this->assertStringContainsString('apbct-email-encoder', $result);
    }

    public function testModifyContentKeepsExcludedEmailInTitleLikeString()
    {
        $email = 'keep@example.com';
        $title = 'About ' . $email;

        $result = $this->createEncoder(array($email))->modifyContent($title);

        $this->assertSame($title, $result);
    }

    protected function tearDown(): void
    {
        $this->createEncoder()->dropInstance();
    }
}
