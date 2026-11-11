<?php

declare(strict_types=1);

use App\Services\EncodedText;

it('decodes whole binary and Morse posts', function (string $text, string $source, string $body): void {
    expect(new EncodedText()->decode($text))->toBe(['source_language' => $source, 'body' => $body]);
})->with([
    'binary bytes' => ['01001000 01100101 01101100 01101100 01101111 00100000 01010111 01101111 01110010 01101100 01100100', 'binary', 'Hello World'],
    'contiguous bytes' => ['0100100001101001', 'binary', 'Hi'],
    'UTF-8 bytes' => ['01000011 01100001 01100110 11000011 10101001 00100000 11110000 10011111 10011000 10000000', 'binary', 'Café 😀'],
    'joined emoji' => ['11110000 10011111 10010001 10101001 11100010 10000000 10001101 11110000 10011111 10010010 10111011', 'binary', '👩‍💻'],
    'encoded line break' => ["01000001\t00001010\n01000010", 'binary', "A\nB"],
    'Morse words' => ['.... . .-.. .-.. --- / .-- --- .-. .-.. -..', 'morse', 'HELLO WORLD'],
    'Morse word spacing' => ['.... ..   - .... . .-. .', 'morse', 'HI THERE'],
    'Unicode Morse' => ['··· −−− ···', 'morse', 'SOS'],
    'Morse numbers, punctuation and lines' => ["... --- ...\n.---- ..--- ...-- -.-.--", 'morse', "SOS\n123!"],
]);

it('leaves non-text bytes, malformed encodings and ambiguous punctuation alone', function (string $text): void {
    expect(new EncodedText()->decode($text))->toBeNull();
})->with([
    'ordinary prose' => 'Hello World',
    'mixed binary and prose' => 'Hello 01010111',
    'incomplete byte' => '01001000 0110101',
    'invalid byte' => '01001000 01101012',
    'invalid UTF-8' => '11111111',
    'control character' => '01001000 00000000',
    'bidirectional override' => '01001000 11100010 10000000 10101110',
    'overlong UTF-8' => '11000000 10101111',
    'blank decoded text' => '00100000 00001010',
    'inline code' => '`01001000 01101001`',
    'fenced Morse' => "```\n... --- ...\n```",
    'mixed Morse and prose' => 'SOS: ... --- ...',
    'unknown Morse letter' => '... --- ......',
    'ellipsis' => '...',
    'horizontal rule' => '---',
    'punctuation only' => '.-.-.- -.-.--',
    'single Morse letter' => '.-',
    'empty' => '',
]);
