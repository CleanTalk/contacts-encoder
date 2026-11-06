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
     * Over the limit the contacts are masked: nothing open, nothing to decode.
     *
     * @dataProvider unitProvider
     */
    public function testContactsOverTheEncodedLimitAreMasked($unit, $contact)
    {
        $limit = ContactsEncoder::MAX_ENCODED_CONTACTS_PER_REQUEST;
        $encoder = $this->createEncoder();
        $property = new \ReflectionProperty(ContactsEncoder::class, 'encoded_contacts_count');
        $property->setAccessible(true);
        $property->setValue($encoder, $limit);

        $result = $encoder->modifyContent($unit);

        $this->assertStringNotContainsString($contact, $result);
        $this->assertStringNotContainsString('data-original-string', $result);
    }

    /**
     * @dataProvider signsLimitProvider
     */
    public function testContactsOverTheSignsLimitsAreMasked($content, $taken)
    {
        $encoder = $this->createEncoder();
        $property = new \ReflectionProperty(ContactsEncoder::class, 'contact_signs_processed');
        $property->setAccessible(true);
        $property->setValue($encoder, $taken);

        $result = $encoder->modifyContent($content);

        $this->assertStringNotContainsString('first@example.org', $result);
        $this->assertStringNotContainsString('+1 234 567 8901', $result);
        $this->assertStringContainsString('*****@*******.org', $result);
        $this->assertStringContainsString('+* *** *** **01', $result);
    }

    public function signsLimitProvider()
    {
        $contacts = '<p>first@example.org +1 234 567 8901</p>';

        return array(
            'request budget' => array($contacts, ContactsEncoder::MAX_CONTACT_SIGNS_PER_REQUEST),
            'one string' => array($contacts . str_repeat(' @ ( ', ContactsEncoder::MAX_CONTACT_SIGNS_PER_CONTENT), 0),
        );
    }

    /**
     * @dataProvider notContactProvider
     */
    public function testMaskingWithoutEncodingKeepsOtherText($content)
    {
        $encoder = $this->createEncoder();
        $property = new \ReflectionProperty(ContactsEncoder::class, 'contact_signs_processed');
        $property->setAccessible(true);
        $property->setValue($encoder, ContactsEncoder::MAX_CONTACT_SIGNS_PER_REQUEST);

        $this->assertSame($content, $encoder->modifyContent($content));
    }

    public function notContactProvider()
    {
        return array(
            'css rule' => array('<style>@media print { a { color: red } }</style>'),
            'handle' => array('<p>ask @someone (or 12 people) +5</p>'),
            'no dot in the domain' => array('<p>user@localhost</p>'),
        );
    }

    /**
     * The fallback changes only what the encoding would change: script strings, option texts and other attributes stay.
     */
    public function testMaskingWithoutEncodingKeepsTheContextOfTheEncoding()
    {
        $encoder = $this->createEncoder();
        $property = new \ReflectionProperty(ContactsEncoder::class, 'contact_signs_processed');
        $property->setAccessible(true);
        $property->setValue($encoder, ContactsEncoder::MAX_CONTACT_SIGNS_PER_REQUEST);

        $kept = array(
            '<script>var e = "s@b.cc"; var t = (12345678);</script>',
            '<input type="text" value="i@b.cc">',
            '<select><option>o@b.cc</option></select>',
            '<div data-phone="+1 234 567 8901"></div>',
        );
        $result = $encoder->modifyContent(implode('', $kept) . '<a href="mailto:m@b.cc">w</a><p>p@b.cc +1 234 567 8901</p>');

        foreach ( $kept as $part ) {
            $this->assertStringContainsString($part, $result);
        }
        $this->assertStringContainsString('href="mailto:*@*.cc"', $result);
        $this->assertStringContainsString('<p>*@*.cc +* *** *** **01</p>', $result);
    }

    public function testExcludedContactStaysOpenOverTheLimit()
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
        $params->excluded_strings = array('keep@example.org');
        $concrete::getInstance($params)->dropInstance();
        $encoder = $concrete::getInstance($params);
        $property = new \ReflectionProperty(ContactsEncoder::class, 'contact_signs_processed');
        $property->setAccessible(true);
        $property->setValue($encoder, ContactsEncoder::MAX_CONTACT_SIGNS_PER_REQUEST);

        $result = $encoder->modifyContent('<p>keep@example.org hide@example.org</p>');

        $this->assertStringContainsString('keep@example.org', $result);
        $this->assertStringNotContainsString('hide@example.org', $result);
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
            'mailto' => array('<a href="mailto:u@b.cc">w</a>', 'u@b.cc'),
            'tel' => array('<a href="tel:+12345678901">w</a>', '+12345678901'),
            'email' => array('<p>u@b.cc</p>', 'u@b.cc'),
            'phone' => array('<p>+1 234 567 8901</p>', '+1 234 567 8901'),
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

    /**
     * The budget is taken by the signs of possible contacts, found or not, before the regular expression.
     */
    public function testSignsBudgetIsTakenBeforeMatching()
    {
        $encoder = $this->createEncoder();
        $property = new \ReflectionProperty(ContactsEncoder::class, 'contact_signs_processed');
        $property->setAccessible(true);

        $encoder->modifyContent('<p>no contact @ here, one more @</p>');
        $this->assertSame(2, $property->getValue($encoder));

        $property->setValue($encoder, ContactsEncoder::MAX_CONTACT_SIGNS_PER_REQUEST - 1);
        $content = '<p>u@b.cc and v@b.cc</p>';
        $this->assertSame('<p>*@*.cc and *@*.cc</p>', $encoder->modifyContent($content));
        $this->assertSame(ContactsEncoder::MAX_CONTACT_SIGNS_PER_REQUEST - 1, $property->getValue($encoder));

        $property->setValue($encoder, ContactsEncoder::MAX_CONTACT_SIGNS_PER_REQUEST - 2);
        $this->assertSame(2, substr_count($encoder->modifyContent($content), 'data-original-string'));
    }

    protected function tearDown(): void
    {
        $this->createEncoder()->dropInstance();
    }
}
