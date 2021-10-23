<?php

namespace Firebed\Scout;

class UnicodeTokenizer
{
    /**
     * Splits the given string into tokens. A token may consist of any unicode letter or number,
     * while all other characters like whitespace, colons, dots, etc. are split characters.
     *
     * @param string $input
     * @return string[]
     */
    public function tokenize(string $input): array
    {
        return preg_split("/[^\p{L}\p{N}]+/u", $input, -1, PREG_SPLIT_NO_EMPTY);
    }
}