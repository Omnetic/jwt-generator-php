<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use PhpCsFixer\Fixer\FunctionNotation\MethodArgumentSpaceFixer;
use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use PhpCsFixer\Fixer\Phpdoc\NoEmptyPhpdocFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitAttributesFixer;
use PhpCsFixer\Fixer\Whitespace\NoExtraBlankLinesFixer;
use PhpCsFixer\Fixer\Whitespace\TypeDeclarationSpacesFixer;
use SlevomatCodingStandard\Sniffs\Commenting\DocCommentSpacingSniff;
use SlevomatCodingStandard\Sniffs\Commenting\ForbiddenCommentsSniff;
use SlevomatCodingStandard\Sniffs\Namespaces\ReferenceUsedNamesOnlySniff;
use SlevomatCodingStandard\Sniffs\TypeHints\DisallowArrayTypeHintSyntaxSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\LongTypeHintsSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\ParameterTypeHintSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\PropertyTypeHintSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\ReturnTypeHintSniff;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $config): void {
    $config->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/examples',
        __DIR__ . '/ecs.php',
    ]);

    // PHPUnit 11+ deprecated phpdoc annotations in favour of attributes
    $config->rule(PhpUnitAttributesFixer::class);

    // comments
    $config->rule(DocCommentSpacingSniff::class);
    $config->ruleWithConfiguration(ForbiddenCommentsSniff::class, [
        'forbiddenCommentPatterns' => ['~\w+ constructor\.~'],
    ]);

    $config->rules([
        LongTypeHintsSniff::class,
        DisallowArrayTypeHintSyntaxSniff::class,
        NoEmptyPhpdocFixer::class,
        NoExtraBlankLinesFixer::class,
        ReferenceUsedNamesOnlySniff::class,
        TypeDeclarationSpacesFixer::class,
        ParameterTypeHintSniff::class,
        PropertyTypeHintSniff::class,
        ReturnTypeHintSniff::class,
    ]);

    $config->sets([
        SetList::PSR_12,
        SetList::ARRAY,
        SetList::CLEAN_CODE,
        SetList::STRICT,
    ]);

    $config->ruleWithConfiguration(OrderedImportsFixer::class, [
        'sort_algorithm' => OrderedImportsFixer::SORT_ALPHA,
        'imports_order' => [
            OrderedImportsFixer::IMPORT_TYPE_CONST,
            OrderedImportsFixer::IMPORT_TYPE_CLASS,
            OrderedImportsFixer::IMPORT_TYPE_FUNCTION,
        ],
    ]);

    // forbid yoda style
    $config->ruleWithConfiguration(YodaStyleFixer::class, [
        'equal' => false,
        'identical' => false,
        'less_and_greater' => false,
    ]);

    $config->ruleWithConfiguration(TrailingCommaInMultilineFixer::class, [
        'elements' => ['arrays', 'arguments', 'parameters'],
    ]);

    $config->ruleWithConfiguration(MethodArgumentSpaceFixer::class, [
        'on_multiline' => 'ignore',
    ]);
};
