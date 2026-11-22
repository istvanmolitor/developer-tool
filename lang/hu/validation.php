<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validációs üzenetek (magyar)
    |--------------------------------------------------------------------------
    */

    'accepted' => 'A(z) :attribute mező elfogadása kötelező.',
    'accepted_if' => 'A(z) :attribute mező elfogadása kötelező, ha :other értéke :value.',
    'active_url' => 'A(z) :attribute mező nem érvényes URL.',
    'after' => 'A(z) :attribute mezőnek :date utáni dátumnak kell lennie.',
    'after_or_equal' => 'A(z) :attribute mezőnek :date utáni vagy azzal egyenlő dátumnak kell lennie.',
    'alpha' => 'A(z) :attribute mező csak betűket tartalmazhat.',
    'alpha_dash' => 'A(z) :attribute mező csak betűket, számokat, kötőjelet és aláhúzást tartalmazhat.',
    'alpha_num' => 'A(z) :attribute mező csak betűket és számokat tartalmazhat.',
    'array' => 'A(z) :attribute mezőnek tömbnek kell lennie.',
    'ascii' => 'A(z) :attribute mező csak egybájtos alfanumerikus karaktereket és szimbólumokat tartalmazhat.',
    'before' => 'A(z) :attribute mezőnek :date előtti dátumnak kell lennie.',
    'before_or_equal' => 'A(z) :attribute mezőnek :date előtti vagy azzal egyenlő dátumnak kell lennie.',
    'between' => [
        'array' => 'A(z) :attribute mezőnek :min és :max közötti elemszámúnak kell lennie.',
        'file' => 'A(z) :attribute mérete :min és :max kilobájt között lehet.',
        'numeric' => 'A(z) :attribute értéke :min és :max között lehet.',
        'string' => 'A(z) :attribute hossza :min és :max karakter között lehet.',
    ],
    'boolean' => 'A(z) :attribute mező csak igaz vagy hamis lehet.',
    'can' => 'A(z) :attribute mező nem engedélyezett értéket tartalmaz.',
    'confirmed' => 'A(z) :attribute megerősítése nem egyezik.',
    'current_password' => 'A megadott jelszó helytelen.',
    'date' => 'A(z) :attribute mező nem érvényes dátum.',
    'date_equals' => 'A(z) :attribute mezőnek :date dátummal egyenlőnek kell lennie.',
    'date_format' => 'A(z) :attribute nem egyezik a(z) :format formátummal.',
    'decimal' => 'A(z) :attribute mezőnek :decimal tizedesjegyűnek kell lennie.',
    'declined' => 'A(z) :attribute mező elutasítása kötelező.',
    'declined_if' => 'A(z) :attribute mező elutasítása kötelező, ha :other értéke :value.',
    'different' => 'A(z) :attribute és a(z) :other mezőknek különbözniük kell.',
    'digits' => 'A(z) :attribute mezőnek :digits számjegyűnek kell lennie.',
    'digits_between' => 'A(z) :attribute mezőnek :min és :max számjegy között kell lennie.',
    'dimensions' => 'A(z) :attribute képméretei érvénytelenek.',
    'distinct' => 'A(z) :attribute mező ismétlődő értéket tartalmaz.',
    'doesnt_end_with' => 'A(z) :attribute nem végződhet a következők egyikére: :values.',
    'doesnt_start_with' => 'A(z) :attribute nem kezdődhet a következők egyikével: :values.',
    'email' => 'A(z) :attribute mezőnek érvényes e-mail címnek kell lennie.',
    'ends_with' => 'A(z) :attribute mezőnek a következők egyikére kell végződnie: :values.',
    'enum' => 'A kiválasztott :attribute érvénytelen.',
    'exists' => 'A kiválasztott :attribute érvénytelen.',
    'extensions' => 'A(z) :attribute mezőnek a következő kiterjesztések egyikével kell rendelkeznie: :values.',
    'file' => 'A(z) :attribute mezőnek fájlnak kell lennie.',
    'filled' => 'A(z) :attribute mező kitöltése kötelező.',
    'gt' => [
        'array' => 'A(z) :attribute mezőnek :value elemnél többet kell tartalmaznia.',
        'file' => 'A(z) :attribute méretének nagyobbnak kell lennie, mint :value kilobájt.',
        'numeric' => 'A(z) :attribute értékének nagyobbnak kell lennie, mint :value.',
        'string' => 'A(z) :attribute hosszának nagyobbnak kell lennie, mint :value karakter.',
    ],
    'gte' => [
        'array' => 'A(z) :attribute mezőnek legalább :value elemet kell tartalmaznia.',
        'file' => 'A(z) :attribute méretének legalább :value kilobájtnak kell lennie.',
        'numeric' => 'A(z) :attribute értékének legalább :value-nak kell lennie.',
        'string' => 'A(z) :attribute hosszának legalább :value karakternek kell lennie.',
    ],
    'hex_color' => 'A(z) :attribute mezőnek érvényes hexadecimális színnek kell lennie.',
    'image' => 'A(z) :attribute mezőnek képnek kell lennie.',
    'in' => 'A kiválasztott :attribute érvénytelen.',
    'in_array' => 'A(z) :attribute mezőnek szerepelnie kell a(z) :other mezőben.',
    'integer' => 'A(z) :attribute mezőnek egész számnak kell lennie.',
    'ip' => 'A(z) :attribute mezőnek érvényes IP-címnek kell lennie.',
    'ipv4' => 'A(z) :attribute mezőnek érvényes IPv4-címnek kell lennie.',
    'ipv6' => 'A(z) :attribute mezőnek érvényes IPv6-címnek kell lennie.',
    'json' => 'A(z) :attribute mezőnek érvényes JSON-nak kell lennie.',
    'lowercase' => 'A(z) :attribute mezőnek kisbetűsnek kell lennie.',
    'lt' => [
        'array' => 'A(z) :attribute mezőnek :value elemnél kevesebbet kell tartalmaznia.',
        'file' => 'A(z) :attribute méretének kisebbnek kell lennie, mint :value kilobájt.',
        'numeric' => 'A(z) :attribute értékének kisebbnek kell lennie, mint :value.',
        'string' => 'A(z) :attribute hosszának kisebbnek kell lennie, mint :value karakter.',
    ],
    'lte' => [
        'array' => 'A(z) :attribute mezőnek legfeljebb :value elemet tartalmazhat.',
        'file' => 'A(z) :attribute méretének legfeljebb :value kilobájtnak kell lennie.',
        'numeric' => 'A(z) :attribute értékének legfeljebb :value-nak kell lennie.',
        'string' => 'A(z) :attribute hosszának legfeljebb :value karakternek kell lennie.',
    ],
    'mac_address' => 'A(z) :attribute mezőnek érvényes MAC-címnek kell lennie.',
    'max' => [
        'array' => 'A(z) :attribute mező legfeljebb :max elemet tartalmazhat.',
        'file' => 'A(z) :attribute mérete legfeljebb :max kilobájt lehet.',
        'numeric' => 'A(z) :attribute értéke legfeljebb :max lehet.',
        'string' => 'A(z) :attribute legfeljebb :max karakter hosszú lehet.',
    ],
    'max_digits' => 'A(z) :attribute mező legfeljebb :max számjegyet tartalmazhat.',
    'mimes' => 'A(z) :attribute mezőnek a következő típusú fájlnak kell lennie: :values.',
    'mimetypes' => 'A(z) :attribute mezőnek a következő típusú fájlnak kell lennie: :values.',
    'min' => [
        'array' => 'A(z) :attribute mezőnek legalább :min elemet kell tartalmaznia.',
        'file' => 'A(z) :attribute mérete legalább :min kilobájt kell legyen.',
        'numeric' => 'A(z) :attribute értéke legalább :min kell legyen.',
        'string' => 'A(z) :attribute legalább :min karakter hosszú kell legyen.',
    ],
    'min_digits' => 'A(z) :attribute mezőnek legalább :min számjegyet kell tartalmaznia.',
    'missing' => 'A(z) :attribute mezőnek hiányoznia kell.',
    'missing_if' => 'A(z) :attribute mezőnek hiányoznia kell, ha :other értéke :value.',
    'missing_unless' => 'A(z) :attribute mezőnek hiányoznia kell, hacsak :other értéke nem :value.',
    'missing_with' => 'A(z) :attribute mezőnek hiányoznia kell, ha :values jelen van.',
    'missing_with_all' => 'A(z) :attribute mezőnek hiányoznia kell, ha :values jelen van.',
    'multiple_of' => 'A(z) :attribute értékének :value többszörösének kell lennie.',
    'not_in' => 'A kiválasztott :attribute érvénytelen.',
    'not_regex' => 'A(z) :attribute formátuma érvénytelen.',
    'numeric' => 'A(z) :attribute mezőnek számnak kell lennie.',
    'password' => [
        'letters' => 'A(z) :attribute mezőnek legalább egy betűt kell tartalmaznia.',
        'mixed' => 'A(z) :attribute mezőnek legalább egy nagy- és egy kisbetűt kell tartalmaznia.',
        'numbers' => 'A(z) :attribute mezőnek legalább egy számot kell tartalmaznia.',
        'symbols' => 'A(z) :attribute mezőnek legalább egy szimbólumot kell tartalmaznia.',
        'uncompromised' => 'A megadott :attribute szerepel egy adatszivárgásban. Kérjük, válassz másikat.',
    ],
    'present' => 'A(z) :attribute mezőnek jelen kell lennie.',
    'present_if' => 'A(z) :attribute mezőnek jelen kell lennie, ha :other értéke :value.',
    'present_unless' => 'A(z) :attribute mezőnek jelen kell lennie, hacsak :other értéke nem :value.',
    'present_with' => 'A(z) :attribute mezőnek jelen kell lennie, ha :values jelen van.',
    'present_with_all' => 'A(z) :attribute mezőnek jelen kell lennie, ha :values jelen van.',
    'prohibited' => 'A(z) :attribute mező tiltott.',
    'prohibited_if' => 'A(z) :attribute mező tiltott, ha :other értéke :value.',
    'prohibited_unless' => 'A(z) :attribute mező tiltott, hacsak :other értéke nem :values.',
    'prohibits' => 'A(z) :attribute mező megtiltja a(z) :other mező jelenlétét.',
    'regex' => 'A(z) :attribute formátuma érvénytelen.',
    'required' => 'A(z) :attribute mező kitöltése kötelező.',
    'required_array_keys' => 'A(z) :attribute mezőnek tartalmaznia kell a következő kulcsokat: :values.',
    'required_if' => 'A(z) :attribute mező kitöltése kötelező, ha :other értéke :value.',
    'required_if_accepted' => 'A(z) :attribute mező kitöltése kötelező, ha :other el van fogadva.',
    'required_unless' => 'A(z) :attribute mező kitöltése kötelező, hacsak :other értéke nem :values.',
    'required_with' => 'A(z) :attribute mező kitöltése kötelező, ha :values jelen van.',
    'required_with_all' => 'A(z) :attribute mező kitöltése kötelező, ha :values jelen van.',
    'required_without' => 'A(z) :attribute mező kitöltése kötelező, ha :values nincs jelen.',
    'required_without_all' => 'A(z) :attribute mező kitöltése kötelező, ha egyik :values sincs jelen.',
    'same' => 'A(z) :attribute és a(z) :other mezőknek egyezniük kell.',
    'size' => [
        'array' => 'A(z) :attribute mezőnek :size elemet kell tartalmaznia.',
        'file' => 'A(z) :attribute méretének :size kilobájtnak kell lennie.',
        'numeric' => 'A(z) :attribute értékének :size-nak kell lennie.',
        'string' => 'A(z) :attribute hosszának :size karakternek kell lennie.',
    ],
    'starts_with' => 'A(z) :attribute mezőnek a következők egyikével kell kezdődnie: :values.',
    'string' => 'A(z) :attribute mezőnek szövegnek kell lennie.',
    'timezone' => 'A(z) :attribute mezőnek érvényes időzónának kell lennie.',
    'unique' => 'A(z) :attribute már foglalt.',
    'uploaded' => 'A(z) :attribute feltöltése nem sikerült.',
    'uppercase' => 'A(z) :attribute mezőnek nagybetűsnek kell lennie.',
    'url' => 'A(z) :attribute mezőnek érvényes URL-nek kell lennie.',
    'ulid' => 'A(z) :attribute mezőnek érvényes ULID-nak kell lennie.',
    'uuid' => 'A(z) :attribute mezőnek érvényes UUID-nak kell lennie.',

    /*
    |--------------------------------------------------------------------------
    | Egyedi validációs üzenetek
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Egyedi mezőnevek
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'név',
        'path' => 'útvonal',
        'description' => 'leírás',
        'prompt' => 'prompt',
        'email' => 'e-mail cím',
        'password' => 'jelszó',
        'password_confirmation' => 'jelszó megerősítése',
        'current_password' => 'jelenlegi jelszó',
    ],

];
