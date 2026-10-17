<?php

declare(strict_types=1);

namespace App\Services\Highlighting;

use Override;
use Tempest\Highlight\Languages\Base\BaseLanguage;
use Tempest\Highlight\Languages\Php\Patterns\GenericPattern;
use Tempest\Highlight\Pattern;
use Tempest\Highlight\Tokens\TokenTypeEnum;

final class RubyLanguage extends BaseLanguage
{
    public function getName(): string
    {
        return 'ruby';
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getAliases(): array
    {
        return ['rb'];
    }

    /**
     * @return list<Pattern>
     */
    #[Override]
    public function getPatterns(): array
    {
        return [
            ...array_values(parent::getPatterns()),
            new GenericPattern(
                <<<'REGEX'
                (?<match>"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')
                REGEX,
                TokenTypeEnum::VALUE,
            ),
            new GenericPattern('(?<match>\#[^\r\n]*)', TokenTypeEnum::COMMENT),
            new GenericPattern('/(?<match>^=begin\b[\s\S]*?^=end\b[^\r\n]*)/m', TokenTypeEnum::COMMENT),
            new GenericPattern(
                '(?<![\w:.$@])(?<match>BEGIN|END|alias|and|begin|break|case|class|def|defined\?|do|else|elsif|end|ensure|for|if|in|module|next|not|or|redo|rescue|retry|return|self|super|then|undef|unless|until|when|while|yield|__FILE__|__LINE__|__ENCODING__)(?![\w!?])',
                TokenTypeEnum::KEYWORD,
            ),
            new GenericPattern('(?<![\w:.$@])(?<match>true|false|nil)(?![\w!?])', TokenTypeEnum::LITERAL),
            new GenericPattern('(?<![\w$@])(?<match>[A-Z]\w*)', TokenTypeEnum::TYPE),
            new GenericPattern('\bdef\s+(?:self\.)?(?<match>[a-zA-Z_]\w*[!?=]?)', TokenTypeEnum::PROPERTY),
            new GenericPattern('(?<![\w:])(?<match>:[a-zA-Z_]\w*[!?=]?)', TokenTypeEnum::VALUE),
            new GenericPattern('(?<![\w])(?<match>@@?[a-zA-Z_]\w*|\$[a-zA-Z_]\w*)', TokenTypeEnum::VARIABLE),
            new GenericPattern('(?<![\w])(?<match>0[xX][\da-fA-F_]+|0[bB][01_]+|0[oO][0-7_]+|\d[\d_]*(?:\.\d[\d_]*)?(?:[eE][+-]?\d[\d_]*)?)(?!\w)', TokenTypeEnum::NUMBER),
        ];
    }
}
