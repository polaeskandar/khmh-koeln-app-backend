<?php

declare(strict_types=1);

namespace App\Platform\OpenApi\Service;

use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use ReflectionMethod;

final readonly class PhpDocReturnTypes
{
    private Lexer $lexer;

    private PhpDocParser $parser;

    public function __construct()
    {
        $config = new ParserConfig([]);
        $expressions = new ConstExprParser($config);
        $this->lexer = new Lexer($config);
        $this->parser = new PhpDocParser($config, new TypeParser($config, $expressions), $expressions);
    }

    public function of(ReflectionMethod $method): ?TypeNode
    {
        $comment = $method->getDocComment();

        if ($comment === false) {
            return null;
        }

        $tags = $this->parser->parse(new TokenIterator($this->lexer->tokenize($comment)))->getReturnTagValues();

        return ($tags[0] ?? null)?->type;
    }
}
