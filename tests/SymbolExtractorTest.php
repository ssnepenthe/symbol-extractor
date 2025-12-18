<?php

namespace SymbolMapGenerator\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SymbolMapGenerator\SymbolExtractor;

class SymbolExtractorTest extends TestCase
{
    // @todo differentiate based on symbol type?
    #[DataProvider('extractProvider')]
    public function testExtract(string $file, array $expected)
    {
        $extractor = new SymbolExtractor($file);

        $this->assertSame($expected, $extractor->extract());
    }

    #[DataProvider('extractWithLlmSuggestedEdgeCasesProvider')]
    public function testExtractWithLlmSuggestedEdgeCases(string $file, array $expected)
    {
        $extractor = new SymbolExtractor($file);

        $this->assertSame($expected, $extractor->extract());
    }

    #[DataProvider('extractWithClassMapGeneratorFixturesProvider')]
    public function testExtractWithClassMapGeneratorFixtures(string $file, array $expected)
    {
        $extractor = new SymbolExtractor($file);

        $this->assertSame($expected, $extractor->extract());
    }

    public static function extractProvider()
    {
        $dir = __DIR__ . '/Fixtures';

        yield [$dir . '/Basic/anonymous-classes.php', self::symbolsArray(class: ['AnonymousClasses\\Alfa', 'AnonymousClasses\\Bravo'])];
        yield [$dir . '/Basic/anonymous-functions.php', self::symbolsArray(function: ['AnonymousFunctions\\alfa', 'AnonymousFunctions\\bravo'])];
        yield [$dir . '/Basic/class-likes.php', self::symbolsArray(class: ['ClassLikes\\Alfa'], enum: ['ClassLikes\\Bravo'], interface: ['ClassLikes\\Charlie'], trait: ['ClassLikes\\Delta'])];
        yield [$dir . '/Basic/class.php', self::symbolsArray(class: ['_Class\\Alfa'])];
        yield [$dir . '/Basic/backed-enum.php', self::symbolsArray(enum: ['BackedEnum\\Alfa'])];
        yield [$dir . '/Basic/function.php', self::symbolsArray(function: ['_Function\\alfa'])];
        yield [$dir . '/Basic/functions.php', self::symbolsArray(function: ['Functions\\alfa', 'Functions\\bravo', 'Functions\\charlie'])];
        yield [$dir . '/Basic/magic-class-constant.php', self::symbolsArray(function: ['MagicClassConstant\\bravo'])];
        yield [$dir . '/Basic/multiple-namespaces.php', self::symbolsArray(class: ['Multiple\\Alfa', 'Multiple\\Bravo', 'Charlie', 'Namespaces\\Delta', 'Namespaces\\_Echo'])];
        yield [$dir . '/Basic/nothing-to-see-here.php', self::symbolsArray()];
    }

    public static function extractWithLlmSuggestedEdgeCasesProvider()
    {
        $dir = __DIR__ . '/Fixtures';

        yield [$dir . '/ChatGptSuggestedEdgeCases/conditional-declaration.php', self::symbolsArray(class: ['Bravo'], function: ['alfa', 'Charlie\\delta'])];
        yield [$dir . '/ChatGptSuggestedEdgeCases/nested-declarations.php', self::symbolsArray(class: ['NestedDeclarations\\Alfa'])];
        yield [$dir . '/ChatGptSuggestedEdgeCases/same-name-different-namespace.php', self::symbolsArray(class: ['Alfa\\Foo', 'Bravo\\Foo'], function: ['Alfa\\bar', 'Bravo\\bar'])];
        yield [$dir . '/ChatGptSuggestedEdgeCases/sub-namespace.php', self::symbolsArray(class: ['Alfa\\Bravo\\Charlie'])];
        yield [$dir . '/ChatGptSuggestedEdgeCases/top-level-statements.php', self::symbolsArray(class: ['TopLevelStatements\\Alfa'])];

        yield [$dir . '/ClaudeSuggestedEdgeCases/anonymous-classes.php', self::symbolsArray(class: ['AnonymousClasses\\Alfa'])];
        yield [$dir . '/ClaudeSuggestedEdgeCases/attribute-before-declaration.php', self::symbolsArray(class: ['AttributeBeforeDeclaration\\Alfa', 'AttributeBeforeDeclaration\\Bravo'])];
        yield [$dir . '/ClaudeSuggestedEdgeCases/class-comment-name.php', self::symbolsArray(class: ['ClassCommentName\\Bravo'])];
        yield [$dir . '/ClaudeSuggestedEdgeCases/class-constants.php', self::symbolsArray(class: ['ClassConstants\\Alfa'])];
        yield [$dir . '/ClaudeSuggestedEdgeCases/class-modifiers.php', self::symbolsArray(class: ['Modifiers\\Alfa', 'Modifiers\\Bravo', 'Modifiers\\Charlie'])];
        yield [$dir . '/ClaudeSuggestedEdgeCases/first-class-callables.php', self::symbolsArray(function: ['FirstClassCallables\\bravo'])];
        yield [$dir . '/ClaudeSuggestedEdgeCases/function-parameters-trailing-comma.php', self::symbolsArray(function: ['FunctionParametersTrailingComma\\alfa'])];
        yield [$dir . '/ClaudeSuggestedEdgeCases/intersection-types.php', self::symbolsArray(function: ['IntersectionTypes\\alfa', 'IntersectionTypes\\bravo'])];
        yield [$dir . '/ClaudeSuggestedEdgeCases/nested-namespace-declarations.php', self::symbolsArray(class: ['_Namespace\\Alfa'])];
        // @todo php -l reports no errors on this file but php -f gives fatal "Cannot redeclare class" error... Should we be explicit in not supporting this?
        yield [$dir . '/ClaudeSuggestedEdgeCases/same-class-different-case.php', self::symbolsArray(class: ['SameClassDifferentCase\\Alfa', 'SameClassDifferentCase\\ALFA'])];
        yield [$dir . '/ClaudeSuggestedEdgeCases/string-arg-with-brace-default.php', self::symbolsArray(function: ['StringArgWithBraceDefault\\alfa'])];
        yield [$dir . '/ClaudeSuggestedEdgeCases/union-types.php', self::symbolsArray(function: ['UnionTypes\\alfa', 'UnionTypes\\charlie'])];
    }

    public static function extractWithClassMapGeneratorFixturesProvider()
    {
        $dir = __DIR__ . '/Fixtures/ClassMapGenerator';

        yield [$dir . '/classmap/BackslashLineEndingString.php', self::symbolsArray(class: ['Foo\\SlashedA', 'Foo\\SlashedB'])];
        yield [$dir . '/classmap/InvalidUnicode.php', self::symbolsArray(class: ['Smarty_Internal_Compile_Block', 'Smarty_Internal_Compile_Blockclose'])];
        yield [$dir . '/classmap/LargeClass.php', self::symbolsArray(class: ['Foo\\LargeClass'])];
        yield [$dir . '/classmap/LargeGap.php', self::symbolsArray(class: ['Foo\\LargeGap'])];
        yield [$dir . '/classmap/LongString.php', self::symbolsArray(class: ['ClassMap\\LongString'])];
        yield [$dir . '/classmap/MissingSpace.php', self::symbolsArray(class: ['Foo\\MissingSpace'])];
        // @todo The Be\ta\* classes have whitespace in the namespace and would be invalid in php 8+ - should we explicitly remove support?
        yield [$dir . '/classmap/multipleNs.php', self::symbolsArray(class: ['Alpha\\A', 'Alpha\\B', 'A', 'Be\ta\A', 'Be\ta\B'])];
        yield [$dir . '/classmap/NonUnicode.php', self::symbolsArray()];
        yield [$dir . '/classmap/notAClass.php', self::symbolsArray()];
        // @todo This is PHP code in a markdown file - should we explicitly disallow this or is this ok?
        yield [$dir . '/classmap/notPhpFile.md', self::symbolsArray(class: ['mustSkip'])];
        yield [$dir . '/classmap/sameNsMultipleClasses.php', self::symbolsArray(class: ['Foo\\Bar\\A', 'Foo\\Bar\\B'])];
        // @todo These depend on short open tags being enabled in php.ini
        // yield [$dir . '/classmap/ShortOpenTag.php', self::symbolsArray(class: ['ShortOpenTag'])];
        // yield [$dir . '/classmap/ShortOpenTagDocblock.php', self::symbolsArray(class: ['ShortOpenTagDocblock'])];
        yield [$dir . '/classmap/SomeClass.php', self::symbolsArray(class: ['ClassMap\\SomeClass'])];
        yield [$dir . '/classmap/SomeInterface.php', self::symbolsArray(interface: ['ClassMap\\SomeInterface'])];
        yield [$dir . '/classmap/SomeParent.php', self::symbolsArray(class: ['ClassMap\\SomeParent'])];
        yield [$dir . '/classmap/StripNoise.php', self::symbolsArray(class: ['Foo\\StripNoise', 'Foo\\First', 'Foo\\Second', 'Foo\\Third'])];
        yield [$dir . '/classmap/Unicode.php', self::symbolsArray(class: ['Unicode\\↑\\↑'])];

        yield [$dir . '/Namespaced/Bar.inc', self::symbolsArray(class: ['Namespaced\\Bar'])];
        yield [$dir . '/Namespaced/Baz.php', self::symbolsArray(class: ['Namespaced\\Baz'])];
        yield [$dir . '/Namespaced/Foo.php', self::symbolsArray(class: ['Namespaced\\Foo'])];

        yield [$dir . '/pcrebacktracelimit/StripNoise.php', self::symbolsArray(class: ['Foo\\StripNoise'])];
        yield [$dir . '/pcrebacktracelimit/VeryLongHeredoc.php', self::symbolsArray(class: ['Foo\\VeryLongHeredoc', 'Foo\\ClassAfterLongHereDoc'])];
        yield [$dir . '/pcrebacktracelimit/VeryLongNowdoc.php', self::symbolsArray(class: ['Foo\\VeryLongNowdoc'])];
        yield [$dir . '/pcrebacktracelimit/VeryLongPHP73Heredoc.php', self::symbolsArray(class: ['Foo\\VeryLongPHP73Heredoc'])];
        yield [$dir . '/pcrebacktracelimit/VeryLongPHP73Nowdoc.php', self::symbolsArray(class: ['Foo\\VeryLongPHP73Nowdoc', 'Foo\\ClassAfterLongNowDoc'])];

        yield [$dir . '/Pearlike/Bar.php', self::symbolsArray(class: ['Pearlike_Bar'])];
        yield [$dir . '/Pearlike/Baz.php', self::symbolsArray(class: ['Pearlike_Baz'])];
        yield [$dir . '/Pearlike/Foo.php', self::symbolsArray(class: ['Pearlike_Foo'])];

        yield [$dir . '/php5.4/traits.php', self::symbolsArray(class: ['CFoo', 'Foo\\CBar'], interface: ['Foo\\IBar'], trait: ['TFoo',  'Foo\\TBar', 'Foo\\TFooBar'])];

        yield [$dir . '/php7.0/anonclass.php', self::symbolsArray(class: ['Dummy\\Test\\AnonClassHolder'])];

        yield [$dir . '/php8.1/enum_backed.php', self::symbolsArray(enum: ['RolesBackedEnum'])];
        yield [$dir . '/php8.1/enum_basic.php', self::symbolsArray(enum: ['RolesBasicEnum'])];
        yield [$dir . '/php8.1/enum_class_semantics.php', self::symbolsArray(enum: ['RolesClassLikeEnum'])];
        yield [$dir . '/php8.1/enum_namespaced.php', self::symbolsArray(enum: ['Foo\\Bar\\RolesClassLikeNamespacedEnum'])];

        yield [$dir . '/template/notphp.inc', self::symbolsArray()];
        yield [$dir . '/template/template_1.php', self::symbolsArray()];
        yield [$dir . '/template/template_2.php', self::symbolsArray()];
        yield [$dir . '/template/template_3.php', self::symbolsArray()];
    }

    private static function symbolsArray(
        array $class = [],
        array $enum = [],
        array $function = [],
        array $interface = [],
        array $trait = []
    ): array {
        return compact('class', 'enum', 'function', 'interface', 'trait');
    }
}