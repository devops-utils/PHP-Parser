<?php declare(strict_types=1);

namespace PhpParser\Lexer;

use PhpParser\ErrorHandler;
use PhpParser\Lexer;
use PhpParser\LexerTest;

require __DIR__ . '/../../../lib/PhpParser/compatibility_tokens.php';

class EmulativeTest extends LexerTest {
    protected function getLexer(array $options = []) {
        return new Emulative($options);
    }

    /**
     * @dataProvider provideTestReplaceKeywords
     */
    public function testReplaceKeywords($keyword, $expectedToken) {
        $lexer = $this->getLexer();
        $lexer->startLexing('<?php ' . $keyword);

        $this->assertSame($expectedToken, $lexer->getNextToken());
        $this->assertSame(0, $lexer->getNextToken());
    }

    /**
     * @dataProvider provideTestReplaceKeywords
     */
    public function testReplaceKeywordsUppercase($keyword, $expectedToken) {
        $lexer = $this->getLexer();
        $lexer->startLexing('<?php ' . strtoupper($keyword));

        $this->assertSame($expectedToken, $lexer->getNextToken());
        $this->assertSame(0, $lexer->getNextToken());
    }

    /**
     * @dataProvider provideTestReplaceKeywords
     */
    public function testNoReplaceKeywordsAfterObjectOperator(string $keyword) {
        $lexer = $this->getLexer();
        $lexer->startLexing('<?php ->' . $keyword);

        $this->assertSame(\T_OBJECT_OPERATOR, $lexer->getNextToken());
        $this->assertSame(\T_STRING, $lexer->getNextToken());
        $this->assertSame(0, $lexer->getNextToken());
    }

    /**
     * @dataProvider provideTestReplaceKeywords
     */
    public function testNoReplaceKeywordsAfterObjectOperatorWithSpaces(string $keyword) {
        $lexer = $this->getLexer();
        $lexer->startLexing('<?php ->    ' . $keyword);

        $this->assertSame(\T_OBJECT_OPERATOR, $lexer->getNextToken());
        $this->assertSame(\T_STRING, $lexer->getNextToken());
        $this->assertSame(0, $lexer->getNextToken());
    }

    /**
     * @dataProvider provideTestReplaceKeywords
     */
    public function testNoReplaceKeywordsAfterNullsafeObjectOperator(string $keyword) {
        $lexer = $this->getLexer();
        $lexer->startLexing('<?php ?->' . $keyword);

        $this->assertSame(\T_NULLSAFE_OBJECT_OPERATOR, $lexer->getNextToken());
        $this->assertSame(\T_STRING, $lexer->getNextToken());
        $this->assertSame(0, $lexer->getNextToken());
    }

    public function provideTestReplaceKeywords() {
        return [
            // PHP 8.0
            ['match',         \T_MATCH],

            // PHP 7.4
            ['fn',            \T_FN],

            // PHP 5.5
            ['finally',       \T_FINALLY],
            ['yield',         \T_YIELD],

            // PHP 5.4
            ['callable',      \T_CALLABLE],
            ['insteadof',     \T_INSTEADOF],
            ['trait',         \T_TRAIT],
            ['__TRAIT__',     \T_TRAIT_C],

            // PHP 5.3
            ['__DIR__',       \T_DIR],
            ['goto',          \T_GOTO],
            ['namespace',     \T_NAMESPACE],
            ['__NAMESPACE__', \T_NS_C],
        ];
    }

    private function assertSameTokens(array $expectedTokens, Lexer $lexer) {
        $tokens = [];
        while (0 !== $token = $lexer->getNextToken($text)) {
            $tokens[] = [$token, $text];
        }
        $this->assertSame($expectedTokens, $tokens);
    }

    /**
     * @dataProvider provideTestLexNewFeatures
     */
    public function testLexNewFeatures($code, array $expectedTokens) {
        $lexer = $this->getLexer();
        $lexer->startLexing('<?php ' . $code);
        $this->assertSameTokens($expectedTokens, $lexer);
    }

    /**
     * @dataProvider provideTestLexNewFeatures
     */
    public function testLeaveStuffAloneInStrings($code) {
        $stringifiedToken = '"' . addcslashes($code, '"\\') . '"';

        $lexer = $this->getLexer();
        $lexer->startLexing('<?php ' . $stringifiedToken);

        $this->assertSame(\T_CONSTANT_ENCAPSED_STRING, $lexer->getNextToken($text));
        $this->assertSame($stringifiedToken, $text);
        $this->assertSame(0, $lexer->getNextToken());
    }

    /**
     * @dataProvider provideTestLexNewFeatures
     */
    public function testErrorAfterEmulation($code) {
        $errorHandler = new ErrorHandler\Collecting();
        $lexer = $this->getLexer();
        $lexer->startLexing('<?php ' . $code . "\0", $errorHandler);

        $errors = $errorHandler->getErrors();
        $this->assertCount(1, $errors);

        $error = $errors[0];
        $this->assertSame('Unexpected null byte', $error->getRawMessage());

        $attrs = $error->getAttributes();
        $expPos = strlen('<?php ' . $code);
        $expLine = 1 + substr_count('<?php ' . $code, "\n");
        $this->assertSame($expPos, $attrs['startFilePos']);
        $this->assertSame($expPos, $attrs['endFilePos']);
        $this->assertSame($expLine, $attrs['startLine']);
        $this->assertSame($expLine, $attrs['endLine']);
    }

    public function provideTestLexNewFeatures() {
        return [
            ['yield from', [
                [\T_YIELD_FROM, 'yield from'],
            ]],
            ["yield\r\nfrom", [
                [\T_YIELD_FROM, "yield\r\nfrom"],
            ]],
            ['...', [
                [\T_ELLIPSIS, '...'],
            ]],
            ['**', [
                [\T_POW, '**'],
            ]],
            ['**=', [
                [\T_POW_EQUAL, '**='],
            ]],
            ['??', [
                [\T_COALESCE, '??'],
            ]],
            ['<=>', [
                [\T_SPACESHIP, '<=>'],
            ]],
            ['0b1010110', [
                [\T_LNUMBER, '0b1010110'],
            ]],
            ['0b1011010101001010110101010010101011010101010101101011001110111100', [
                [\T_DNUMBER, '0b1011010101001010110101010010101011010101010101101011001110111100'],
            ]],
            ['\\', [
                [\T_NS_SEPARATOR, '\\'],
            ]],
            ["<<<'NOWDOC'\nNOWDOC;\n", [
                [\T_START_HEREDOC, "<<<'NOWDOC'\n"],
                [\T_END_HEREDOC, 'NOWDOC'],
                [ord(';'), ';'],
            ]],
            ["<<<'NOWDOC'\nFoobar\nNOWDOC;\n", [
                [\T_START_HEREDOC, "<<<'NOWDOC'\n"],
                [\T_ENCAPSED_AND_WHITESPACE, "Foobar\n"],
                [\T_END_HEREDOC, 'NOWDOC'],
                [ord(';'), ';'],
            ]],

            // PHP 7.3: Flexible heredoc/nowdoc
            ["<<<LABEL\nLABEL,", [
                [\T_START_HEREDOC, "<<<LABEL\n"],
                [\T_END_HEREDOC, "LABEL"],
                [ord(','), ','],
            ]],
            ["<<<LABEL\n    LABEL,", [
                [\T_START_HEREDOC, "<<<LABEL\n"],
                [\T_END_HEREDOC, "    LABEL"],
                [ord(','), ','],
            ]],
            ["<<<LABEL\n    Foo\n  LABEL;", [
                [\T_START_HEREDOC, "<<<LABEL\n"],
                [\T_ENCAPSED_AND_WHITESPACE, "    Foo\n"],
                [\T_END_HEREDOC, "  LABEL"],
                [ord(';'), ';'],
            ]],
            ["<<<A\n A,<<<A\n A,", [
                [\T_START_HEREDOC, "<<<A\n"],
                [\T_END_HEREDOC, " A"],
                [ord(','), ','],
                [\T_START_HEREDOC, "<<<A\n"],
                [\T_END_HEREDOC, " A"],
                [ord(','), ','],
            ]],
            ["<<<LABEL\nLABELNOPE\nLABEL\n", [
                [\T_START_HEREDOC, "<<<LABEL\n"],
                [\T_ENCAPSED_AND_WHITESPACE, "LABELNOPE\n"],
                [\T_END_HEREDOC, "LABEL"],
            ]],
            // Interpretation changed
            ["<<<LABEL\n    LABEL\nLABEL\n", [
                [\T_START_HEREDOC, "<<<LABEL\n"],
                [\T_END_HEREDOC, "    LABEL"],
                [\T_STRING, "LABEL"],
            ]],

            // PHP 7.4: Null coalesce equal
            ['??=', [
                [\T_COALESCE_EQUAL, '??='],
            ]],

            // PHP 7.4: Number literal separator
            ['1_000', [
                [\T_LNUMBER, '1_000'],
            ]],
            ['0x7AFE_F00D', [
                [\T_LNUMBER, '0x7AFE_F00D'],
            ]],
            ['0b0101_1111', [
                [\T_LNUMBER, '0b0101_1111'],
            ]],
            ['0137_041', [
                [\T_LNUMBER, '0137_041'],
            ]],
            ['1_000.0', [
                [\T_DNUMBER, '1_000.0'],
            ]],
            ['1_0.0', [
                [\T_DNUMBER, '1_0.0']
            ]],
            ['1_000_000_000.0', [
                [\T_DNUMBER, '1_000_000_000.0']
            ]],
            ['0e1_0', [
                [\T_DNUMBER, '0e1_0']
            ]],
            ['1_0e+10', [
                [\T_DNUMBER, '1_0e+10']
            ]],
            ['1_0e-10', [
                [\T_DNUMBER, '1_0e-10']
            ]],
            ['0b1011010101001010_110101010010_10101101010101_0101101011001_110111100', [
                [\T_DNUMBER, '0b1011010101001010_110101010010_10101101010101_0101101011001_110111100'],
            ]],
            ['0xFFFF_FFFF_FFFF_FFFF', [
                [\T_DNUMBER, '0xFFFF_FFFF_FFFF_FFFF'],
            ]],
            ['1_000+1', [
                [\T_LNUMBER, '1_000'],
                [ord('+'), '+'],
                [\T_LNUMBER, '1'],
            ]],
            ['1_0abc', [
                [\T_LNUMBER, '1_0'],
                [\T_STRING, 'abc'],
            ]],
            ['?->', [
                [\T_NULLSAFE_OBJECT_OPERATOR, '?->'],
            ]],
            ['#[Attr]', [
                [\T_ATTRIBUTE, '#['],
                [\T_STRING, 'Attr'],
                [ord(']'), ']'],
            ]],
            ["#[\nAttr\n]", [
                [\T_ATTRIBUTE, '#['],
                [\T_STRING, 'Attr'],
                [ord(']'), ']'],
            ]],
            // Test interaction of two patch-based emulators
            ["<<<LABEL\n    LABEL, #[Attr]", [
                [\T_START_HEREDOC, "<<<LABEL\n"],
                [\T_END_HEREDOC, "    LABEL"],
                [ord(','), ','],
                [\T_ATTRIBUTE, '#['],
                [\T_STRING, 'Attr'],
                [ord(']'), ']'],
            ]],
            ["#[Attr] <<<LABEL\n    LABEL,", [
                [\T_ATTRIBUTE, '#['],
                [\T_STRING, 'Attr'],
                [ord(']'), ']'],
                [\T_START_HEREDOC, "<<<LABEL\n"],
                [\T_END_HEREDOC, "    LABEL"],
                [ord(','), ','],
            ]],
            // Enums use a contextual keyword
            ['enum Foo {}', [
                [\T_ENUM, 'enum'],
                [\T_STRING, 'Foo'],
                [ord('{'), '{'],
                [ord('}'), '}'],
            ]],
            ['class Enum {}', [
                [\T_CLASS, 'class'],
                [\T_STRING, 'Enum'],
                [ord('{'), '{'],
                [ord('}'), '}'],
            ]],
            ['class Enum extends X {}', [
                [\T_CLASS, 'class'],
                [\T_STRING, 'Enum'],
                [\T_EXTENDS, 'extends'],
                [\T_STRING, 'X'],
                [ord('{'), '{'],
                [ord('}'), '}'],
            ]],
            ['class Enum implements X {}', [
                [\T_CLASS, 'class'],
                [\T_STRING, 'Enum'],
                [\T_IMPLEMENTS, 'implements'],
                [\T_STRING, 'X'],
                [ord('{'), '{'],
                [ord('}'), '}'],
            ]],
            ['0o123', [
                [\T_LNUMBER, '0o123'],
            ]],
            ['0O123', [
                [\T_LNUMBER, '0O123'],
            ]],
            ['0o1_2_3', [
                [\T_LNUMBER, '0o1_2_3'],
            ]],
            ['0o1000000000000000000000', [
                [\T_DNUMBER, '0o1000000000000000000000'],
            ]],
            ['readonly class', [
                [\T_READONLY, 'readonly'],
                [\T_CLASS, 'class'],
            ]],
            ['function readonly(', [
                [\T_FUNCTION, 'function'],
                [\T_READONLY, 'readonly'],
                [ord('('), '('],
            ]],
            ['function readonly (', [
                [\T_FUNCTION, 'function'],
                [\T_READONLY, 'readonly'],
                [ord('('), '('],
            ]],
        ];
    }

    /**
     * @dataProvider provideTestTargetVersion
     */
    public function testTargetVersion(string $phpVersion, string $code, array $expectedTokens) {
        $lexer = $this->getLexer(['phpVersion' => $phpVersion]);
        $lexer->startLexing('<?php ' . $code);
        $this->assertSameTokens($expectedTokens, $lexer);
    }

    public function provideTestTargetVersion() {
        return [
            ['8.0', 'match', [[\T_MATCH, 'match']]],
            ['7.4', 'match', [[\T_STRING, 'match']]],
            // Keywords are not case-sensitive.
            ['7.4', 'fn', [[\T_FN, 'fn']]],
            ['7.4', 'FN', [[\T_FN, 'FN']]],
            ['7.3', 'fn', [[\T_STRING, 'fn']]],
            ['7.3', 'FN', [[\T_STRING, 'FN']]],
            // Tested here to skip testLeaveStuffAloneInStrings.
            ['8.0', '"$foo?->bar"', [
                [ord('"'), '"'],
                [\T_VARIABLE, '$foo'],
                [\T_NULLSAFE_OBJECT_OPERATOR, '?->'],
                [\T_STRING, 'bar'],
                [ord('"'), '"'],
            ]],
            ['8.0', '"$foo?->bar baz"', [
                [ord('"'), '"'],
                [\T_VARIABLE, '$foo'],
                [\T_NULLSAFE_OBJECT_OPERATOR, '?->'],
                [\T_STRING, 'bar'],
                [\T_ENCAPSED_AND_WHITESPACE, ' baz'],
                [ord('"'), '"'],
            ]],
        ];
    }
}
