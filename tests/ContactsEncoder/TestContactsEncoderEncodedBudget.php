<?php

use Cleantalk\Common\ContactsEncoder\ContactsEncoder;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use PHPUnit\Framework\TestCase;

/**
 * All the ways to encode a contact in a request share one limit.
 */
class TestContactsEncoderEncodedBudget extends TestCase
{
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
        $params->do_encode_phones = true;
        $params->is_logged_in = false;

        $encoder = $concrete::getInstance($params);
        $encoder->dropInstance();

        return $concrete::getInstance($params);
    }

    /**
     * @dataProvider unitProvider
     */
    public function testLimitForEachWay($unit)
    {
        $limit = ContactsEncoder::MAX_ENCODED_CONTACTS_PER_REQUEST;
        $result = $this->createEncoder()->modifyContent(str_repeat($unit, $limit + 50));

        $this->assertSame($limit, substr_count($result, 'data-original-string'));
    }

    public function unitProvider()
    {
        return array(
            'mailto' => array('<a href="mailto:u@b.cc">w</a>'),
            'tel' => array('<a href="tel:+12345678901">w</a>'),
            'email' => array('<p>u@b.cc</p>'),
            'phone' => array('<p>+1 234 567 8901</p>'),
        );
    }

    public function testWaysShareTheLimit()
    {
        $limit = ContactsEncoder::MAX_ENCODED_CONTACTS_PER_REQUEST;
        $encoder = $this->createEncoder();

        $encoder->modifyContent(str_repeat('<a href="mailto:u@b.cc">w</a>', $limit - 10));
        $result = $encoder->modifyContent(str_repeat('<p>+1 234 567 8901</p>', 50));

        $this->assertSame(10, substr_count($result, 'data-original-string'));
    }

    protected function tearDown(): void
    {
        $this->createEncoder()->dropInstance();
    }
}
