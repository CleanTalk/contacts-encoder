<?php

use Cleantalk\Common\ContactsEncoder\ContactsEncoder;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cleantalk\Common\ContactsEncoder\ContactsEncoder::prepareRegularExpressions
 */
class ContactsEncoderPatternsTest extends TestCase
{
    private function createConcreteContactsEncoder(Params $params)
    {
        $concrete_contacts_encoder_class = new class () extends ContactsEncoder {
            protected function checkRequest()
            {
                return true;
            }

            protected function getCheckRequestComment()
            {
                return '';
            }

            public function getProperty($propertyName)
            {
                $reflection = new \ReflectionClass($this);
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);
                return $property->getValue($this);
            }
        };
        return $concrete_contacts_encoder_class::getInstance($params);
    }

    private function getTestParams()
    {
        $params = new Params();
        $params->api_key = 'test_api_key';
        $params->obfuscation_mode = 'blur';
        $params->obfuscation_text = '';
        $params->do_encode_emails = true;
        $params->do_encode_phones = true;
        $params->is_logged_in = false;

        return $params;
    }

    public function testGlobalEmailPatternProperty()
    {
        $encoder = $this->createConcreteContactsEncoder($this->getTestParams());
        $pattern = $encoder->getProperty('global_email_pattern');
        $expected = '/((?i:mailto):\b[_A-Za-z0-9-\.]{1,64}@[_A-Za-z0-9-\.]{1,255}\.[A-Za-z]{2,63}\b(?:\?(?:[^"\'<>]{0,2048}?(?=["\'])|[^"\'<>\s]{0,2048}))?)|(\b[_A-Za-z0-9-\.]{1,64}@[_A-Za-z0-9-\.]{1,255}(\.[A-Za-z]{2,63}\b))/';
        $this->assertEquals($expected, $pattern);
    }

    public function testGlobalPhonesPatternProperty()
    {
        $encoder = $this->createConcreteContactsEncoder($this->getTestParams());
        $pattern = $encoder->getProperty('global_phones_pattern');
        $expected = '/((?i:tel):\+\d{8,12})|([\+][\s-]?\(?\d[\d\s\-()]{7,}\d)|(\(\d{3}\)\s?\d{3}-\d{4})|(\+\d{1,3}\.\d{1,3}\.((\d{3}\.\d{4})|\d{7})(?![\w.]))/';
        $this->assertEquals($expected, $pattern);
    }

    public function testGlobalMailtoPatternProperty()
    {
        $encoder = $this->createConcreteContactsEncoder($this->getTestParams());
        $pattern = $encoder->getProperty('global_mailto_pattern');
        $expected = '/(?i:mailto):([_A-Za-z0-9-\.]{1,64}@[_A-Za-z0-9-\.]{1,255}\.[A-Za-z]{2,63}\b)(?:\?(?:[^"\'<>]{0,2048}?(?=["\'])|[^"\'<>\s]{0,2048}))?/';
        $this->assertEquals($expected, $pattern);
    }

    public function testPlainEmailPatternProperty()
    {
        $encoder = $this->createConcreteContactsEncoder($this->getTestParams());
        $pattern = $encoder->getProperty('plain_email_pattern');
        $expected = '/(\b[_A-Za-z0-9-\.]{1,64}@[_A-Za-z0-9-\.]{1,255}\.[A-Za-z]{2,63}\b\b)/';
        $this->assertEquals($expected, $pattern);
    }

    public function testGlobalTelPatternProperty()
    {
        $encoder = $this->createConcreteContactsEncoder($this->getTestParams());
        $pattern = $encoder->getProperty('global_tel_pattern');
        $expected = '/(?i:tel):(\+\d{8,12})/';
        $this->assertEquals($expected, $pattern);
    }

    /**
     * @dataProvider emailTextProvider
     */
    public function testTextContainsEmailIsTheEmailPattern($text)
    {
        $this->assertSame(
            (bool)preg_match('/' . ContactsEncoder::EMAIL_PATTERN . '/', $text),
            ContactsEncoder::textContainsEmail($text),
            json_encode($text)
        );
    }

    public function emailTextProvider()
    {
        $texts = array(
            'Write to a@b.cc', 'a@b.c', '@b.cc', 'a@.cc', 'a@b.cc1', 'a@b.cc_', 'a@b.cc-x', 'a.b@c.d.ef', 'a@b..cc', 'x a@b.cc.',
            str_repeat('a', 64) . '@b.cc', 'a@' . str_repeat('b', 255) . '.cc', 'a@' . str_repeat('b', 256) . '.cc',
            'a@b.' . str_repeat('c', 63), 'a@b.' . str_repeat('c', 64), 'a@b.' . str_repeat('c', 64) . '.de', 'é@b.cc', 'a@b.cc"',
        );
        mt_srand(7);
        $alphabet = array('a', 'Z', '1', '_', '-', '.', '@', ' ', '"', "\n");
        for ( $n = 0; $n < 500; $n++ ) {
            $text = '';
            for ( $i = mt_rand(1, 12); $i > 0; $i-- ) {
                $text .= $alphabet[mt_rand(0, count($alphabet) - 1)];
            }
            $texts[] = $text;
        }

        return array_map(static function ($text) {
            return array($text);
        }, $texts);
    }

    public function testTextContainsEmailIsLinear()
    {
        $start = microtime(true);
        ContactsEncoder::textContainsEmail(str_repeat('@a.', 20600));
        ContactsEncoder::textContainsEmail(str_repeat('a.', 15990) . '@' . str_repeat('a.', 15990) . '!');
        ContactsEncoder::textContainsEmail(str_repeat('a@' . str_repeat('b', 250), 250));
        $this->assertLessThan(0.5, microtime(true) - $start);
    }

    /**
     * Without PCRE JIT the pattern of an e-mail runs only near the signs of possible addresses: a long text with
     * one address is not scanned whole. In a separate process, JIT can not be turned off at runtime.
     */
    public function testEmailsAreFoundWithoutScanningTheWholeText()
    {
        $script = tempnam(sys_get_temp_dir(), 'ce');
        file_put_contents($script, '<?php require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';'
            . ' $e = new class () extends \\Cleantalk\\Common\\ContactsEncoder\\ContactsEncoder {'
            . ' protected function checkRequest() { return false; } protected function getCheckRequestComment() { return ""; } };'
            . ' $p = new \\Cleantalk\\Common\\ContactsEncoder\\Dto\\Params(); $p->api_key = "k"; $p->do_encode_emails = 1; $p->do_encode_phones = 0;'
            . ' $enc = $e::getInstance($p); $c = str_repeat("a.", 15990) . " x@y.zz " . str_repeat("a.", 15000);'
            . ' $s = microtime(true); for ($i = 0; $i < 10; $i++) { $out = $enc->modifyContent($c); }'
            . ' echo json_encode(array((microtime(true) - $s) / 10, substr_count($out, "data-original-string")));');
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -d pcre.jit=0 ' . escapeshellarg($script) . ' 2>&1');
        unlink($script);
        $result = json_decode((string)$output, true);

        $this->assertIsArray($result, (string)$output);
        $this->assertLessThan(0.01, $result[0]);
        $this->assertSame(1, $result[1]);
    }
}
