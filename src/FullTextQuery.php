<?php

namespace Firebed\Scout;

use Transliterator;

class FullTextQuery
{
    private const FT_MIN_WORD_LEN = 2;
    
    public function prepareForSearch(array $tokens): array|string|null
    {
        $tokens = $this->prepare($tokens);
        
        // Prepend "+" and append "*" to each token
        return preg_filter('/$/', '*', preg_filter('/^/', '+', $tokens));        
    }

    public function prepareForIndex(array $tokens): array
    {
        $tokens = $this->prepare($tokens);
        
        return array_filter($tokens, static fn($token) => mb_strlen($token) >= self::FT_MIN_WORD_LEN);        
    }

    private function prepare(array $tokens): array
    {
        $trans = Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;', Transliterator::FORWARD);
        
//        $stop_words = include(__DIR__.'../resources/stopwords-el.php');
        
        // Convert tokens to lower case
        array_walk($tokens, static fn(&$token) => $token = $trans->transliterate($token));
        
        // Remove duplicates
        return array_unique($tokens);        
    }
}