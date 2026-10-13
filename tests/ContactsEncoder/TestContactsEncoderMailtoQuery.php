<?php

use Cleantalk\Common\ContactsEncoder\ContactsEncoder;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour of mailto: links carrying a query string ("?subject=...&cc=...").
 */
class TestContactsEncoderMailtoQuery extends TestCase
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

        $encoder = $concrete::getInstance($params);
        $encoder->dropInstance();

        return $concrete::getInstance($params);
    }

    /**
     * Extracts the value of the first data-original-string attribute.
     *
     * @param string $result
     *
     * @return string
     */
    private function getFirstEncodedValue($result)
    {
        $this->assertSame(
            1,
            preg_match('/data-original-string="([^"]*)"/', $result, $matches),
            'Encoded result must contain a data-original-string attribute: ' . $result
        );

        return $matches[1];
    }

    /**
     * Extracts the href value of the encoded link.
     *
     * @param string $result
     *
     * @return string
     */
    private function getHrefValue($result)
    {
        $this->assertSame(
            1,
            preg_match('/href="([^"]*)"/', $result, $matches),
            'Encoded result must contain an href attribute: ' . $result
        );

        return $matches[1];
    }

    public function testQueryStringIsKeptInsideHref()
    {
        $encoder = $this->createEncoder();
        $result = $encoder->runEncoding('<a href="mailto:john@example.com?subject=Hi">Write us</a>');

        $this->assertStringContainsString('?subject=Hi', $this->getHrefValue($result));
        $this->assertStringNotContainsString('john@example.com', $result);
    }

    public function testQueryStringWithSpacesIsKeptEntirely()
    {
        $encoder = $this->createEncoder();
        $result = $encoder->runEncoding('<a href="mailto:john@example.com?subject=Hello World">Write us</a>');

        $this->assertStringContainsString('?subject=Hello World', $this->getHrefValue($result));
        // The whole attribute tail must stay inside the href, nothing may leak after it.
        $this->assertStringEndsWith('>Write us</a>', $result);
    }

    public function testQueryStringIsNotCutByTheAttributeTail()
    {
        $encoder = $this->createEncoder();
        $result = $encoder->runEncoding('<a href="mailto:john@example.com?subject=Hi">Write us</a>');

        // The query must not leak out of the match and end up after the injected attributes.
        $this->assertStringNotContainsString('data-original-string="', substr($result, 0, strpos($result, '?subject=Hi')));
        $this->assertStringEndsWith('>Write us</a>', $result);
    }

    public function testEncodedValueRestoresEmailAndQuery()
    {
        $encoder = $this->createEncoder();
        $result = $encoder->runEncoding('<a href="mailto:john@example.com?subject=Hi&cc=boss@example.com">Write us</a>');

        $decoded = $encoder->encoder->decodeString($this->getFirstEncodedValue($result));

        $this->assertSame('john@example.com?subject=Hi&cc=boss@example.com', $decoded);
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public function additionalCopyProvider()
    {
        return array(
            'cc' => array(
                '<a href="mailto:john@example.com?cc=boss@example.com">Write us</a>',
                'boss@example.com',
            ),
            'bcc' => array(
                '<a href="mailto:john@example.com?bcc=secret@example.com&subject=X">Write us</a>',
                'secret@example.com',
            ),
        );
    }

    /**
     * @dataProvider additionalCopyProvider
     *
     * @param string $content
     * @param string $hidden_email
     *
     * @return void
     */
    public function testAdditionalCopiesAreNotVisibleInPlainText($content, $hidden_email)
    {
        $encoder = $this->createEncoder();
        $result = $encoder->runEncoding($content);

        $this->assertStringNotContainsString($hidden_email, $result);
        $this->assertStringNotContainsString('john@example.com', $result);
    }

    public function testAdditionalCopyParameterItselfIsPreserved()
    {
        $encoder = $this->createEncoder();
        $result = $encoder->runEncoding('<a href="mailto:john@example.com?bcc=secret@example.com&subject=X">Write us</a>');

        $href = $this->getHrefValue($result);

        $this->assertStringContainsString('bcc=', $href);
        $this->assertStringContainsString('subject=X', $href);
    }

    public function testAdditionalCopyIsRestorableFromEncodedValue()
    {
        $encoder = $this->createEncoder();
        $result = $encoder->runEncoding('<a href="mailto:john@example.com?bcc=secret@example.com">Write us</a>');

        $decoded = $encoder->encoder->decodeString($this->getFirstEncodedValue($result));

        $this->assertSame('john@example.com?bcc=secret@example.com', $decoded);
    }

    public function testMailtoWithoutQueryIsNotAffected()
    {
        $encoder = $this->createEncoder();
        $result = $encoder->runEncoding('<a href="mailto:john@example.com">Write us</a>');

        $this->assertStringStartsWith('mailto:', $this->getHrefValue($result));
        $this->assertStringNotContainsString('?', $this->getHrefValue($result));
        $this->assertSame('john@example.com', $encoder->encoder->decodeString($this->getFirstEncodedValue($result)));
    }

    public function testPlainTextMailtoQueryStopsAtWhitespace()
    {
        $encoder = $this->createEncoder();
        $result = $encoder->runEncoding('Write to mailto:john@example.com?subject=Hi and we will answer');

        // The sentence following the link must stay untouched.
        $this->assertStringEndsWith(' and we will answer', $result);
        $this->assertStringNotContainsString('john@example.com', $result);
    }

    public function testPlainEmailFollowedByQuestionMarkIsNotTreatedAsQuery()
    {
        $encoder = $this->createEncoder();
        $result = $encoder->runEncoding('Is john@example.com?');

        $this->assertStringEndsWith('?', $result);
        $this->assertStringNotContainsString('john@example.com', $result);
    }
}
