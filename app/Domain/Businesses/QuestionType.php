<?php

namespace App\Domain\Businesses;

/**
 * FR-002-19.
 */
enum QuestionType: string
{
    case Rating1To5 = 'rating_1_5';
    case YesNo = 'yes_no';
    case SingleChoice = 'single_choice';
    case ShortText = 'short_text';
}
