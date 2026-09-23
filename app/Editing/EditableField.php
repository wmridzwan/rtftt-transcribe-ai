<?php

namespace App\Editing;

/**
 * User-editable fields of a revision segment (D6-01 / D6-03).
 *
 * Segment language is carried from the machine source and is not a freely
 * editable field; structural edits carry it (D6-04).
 */
enum EditableField: string
{
    case Text = 'text';
    case StartTime = 'start_seconds';
    case EndTime = 'end_seconds';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::StartTime => 'Start time',
            self::EndTime => 'End time',
        };
    }
}
