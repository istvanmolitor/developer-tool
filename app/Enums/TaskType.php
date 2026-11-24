<?php

namespace App\Enums;

enum TaskType: string
{
    case Development = 'development';

    case Design = 'design';

    case Bug = 'bug';

    case Documentation = 'documentation';

    case Testing = 'testing';

    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Development => 'Fejlesztés',
            self::Design => 'Design',
            self::Bug => 'Hiba',
            self::Documentation => 'Dokumentáció',
            self::Testing => 'Tesztelés',
            self::Other => 'Egyéb',
        };
    }
}
