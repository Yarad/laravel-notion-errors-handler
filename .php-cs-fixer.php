<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/config');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP81Migration' => true,
        'not_operator_with_successor_space' => false,
        'new_with_parentheses' => true,
        'group_import' => false,
        'single_import_per_statement' => true,
        'php_unit_method_casing' => [
            'case' => 'camel_case',
        ],
        'function_declaration' => [
            'closure_fn_spacing' => 'none',
        ],
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
        ],
    ])
    ->setFinder($finder);
