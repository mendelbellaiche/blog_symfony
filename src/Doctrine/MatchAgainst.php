<?php

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Usage en DQL : MATCH_AGAINST(a.title, a.content, :search)
 * Traduit en SQL : MATCH (title, content) AGAINST (? IN BOOLEAN MODE)
 */
final class MatchAgainst extends FunctionNode
{
    /** @var list<Node> */
    private array $columns = [];

    private Node $needle;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        // Une ou plusieurs colonnes, séparées par des virgules
        do {
            $this->columns[] = $parser->StateFieldPathExpression();
            $parser->match(TokenType::T_COMMA);
        } while ($parser->getLexer()->isNextToken(TokenType::T_IDENTIFIER));

        // Puis le texte recherché (en général un paramètre :search)
        $this->needle = $parser->StringPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $columns = array_map(
            fn (Node $column) => $column->dispatch($sqlWalker),
            $this->columns,
        );

        return sprintf(
            'MATCH (%s) AGAINST (%s IN BOOLEAN MODE)',
            implode(', ', $columns),
            $this->needle->dispatch($sqlWalker),
        );
    }
}
