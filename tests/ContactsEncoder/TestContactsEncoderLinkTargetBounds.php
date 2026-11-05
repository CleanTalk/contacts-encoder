<?php

use Cleantalk\Common\ContactsEncoder\ContactsEncoder;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use PHPUnit\Framework\TestCase;

/**
 * Encoding a link appends attributes after the quote that closes its target:
 * it is possible only when the match is the whole value of a quoted attribute.
 */
class TestContactsEncoderLinkTargetBounds extends TestCase
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
     * @dataProvider keptLinkProvider
     */
    public function testLinkStaysAsIsAndNextContactIsEncoded($link)
    {
        $result = $this->createEncoder()->modifyContent($link . '<p>secret@example.org</p>');

        $this->assertStringContainsString($link . '<p>', $result);
        $this->assertStringNotContainsString('secret@example.org', $result);
        $this->assertSame(1, substr_count($result, 'data-original-string'));
    }

    public function keptLinkProvider()
    {
        return array(
            'unquoted mailto with a query' => array('<a href=mailto:one@example.org?subject=Hi>Contact</a>'),
            'unquoted mailto' => array('<a href=mailto:one@example.org>Contact</a>'),
            'unquoted mailto and one more attribute' => array('<a href=mailto:one@example.org?subject=Hi class=x>Contact</a>'),
            'unquoted tel' => array('<a href=tel:+12345678901>Call</a>'),
            'a quote in the query' => array('<a href="mailto:one@example.org?subject=it\'s ok">Contact</a>'),
            'a tail after the phone' => array('<a href="tel:+12345678901;ext=5">Call</a>'),
        );
    }

    /**
     * @dataProvider encodedLinkProvider
     */
    public function testWholeTargetOfQuotedAttributeIsEncoded($link, $contact)
    {
        $result = $this->createEncoder()->modifyContent($link);

        $this->assertSame(1, substr_count($result, 'data-original-string'));
        $this->assertStringNotContainsString($contact, $result);
    }

    public function encodedLinkProvider()
    {
        return array(
            'mailto' => array('<a href="mailto:one@example.org">w</a>', 'one@example.org'),
            'mailto with a query' => array('<a href="mailto:one@example.org?subject=Hello World">w</a>', 'one@example.org'),
            'mailto with a space at the end' => array('<a href="mailto:one@example.org ">w</a>', 'one@example.org'),
            'tel' => array('<a href="tel:+12345678901">w</a>', '+12345678901'),
        );
    }

    protected function tearDown(): void
    {
        $this->createEncoder()->dropInstance();
    }
}
