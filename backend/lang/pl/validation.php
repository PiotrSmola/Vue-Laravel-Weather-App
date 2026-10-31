<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Komunikaty walidacji
    |--------------------------------------------------------------------------
    |
    | Domyślne komunikaty reguł walidacji w języku polskim. Nazwy pól
    | (:attribute) są tłumaczone w sekcji "attributes" na końcu pliku.
    |
    */

    'accepted' => 'Pole :attribute musi zostać zaakceptowane.',
    'accepted_if' => 'Pole :attribute musi zostać zaakceptowane, gdy :other ma wartość :value.',
    'active_url' => 'Pole :attribute musi być prawidłowym adresem URL.',
    'after' => 'Pole :attribute musi być datą późniejszą niż :date.',
    'after_or_equal' => 'Pole :attribute musi być datą równą lub późniejszą niż :date.',
    'alpha' => 'Pole :attribute może zawierać tylko litery.',
    'alpha_dash' => 'Pole :attribute może zawierać tylko litery, cyfry, myślniki i podkreślenia.',
    'alpha_num' => 'Pole :attribute może zawierać tylko litery i cyfry.',
    'array' => 'Pole :attribute musi być tablicą.',
    'ascii' => 'Pole :attribute może zawierać tylko jednobajtowe znaki alfanumeryczne i symbole.',
    'before' => 'Pole :attribute musi być datą wcześniejszą niż :date.',
    'before_or_equal' => 'Pole :attribute musi być datą równą lub wcześniejszą niż :date.',
    'between' => [
        'array' => 'Pole :attribute musi zawierać od :min do :max elementów.',
        'file' => 'Plik :attribute musi mieć od :min do :max kilobajtów.',
        'numeric' => 'Pole :attribute musi mieć wartość od :min do :max.',
        'string' => 'Pole :attribute musi mieć od :min do :max znaków.',
    ],
    'boolean' => 'Pole :attribute musi mieć wartość prawda lub fałsz.',
    'can' => 'Pole :attribute zawiera niedozwoloną wartość.',
    'confirmed' => 'Potwierdzenie pola :attribute nie jest zgodne.',
    'contains' => 'W polu :attribute brakuje wymaganej wartości.',
    'current_password' => 'Hasło jest nieprawidłowe.',
    'date' => 'Pole :attribute musi być prawidłową datą.',
    'date_equals' => 'Pole :attribute musi być datą równą :date.',
    'date_format' => 'Pole :attribute musi mieć format :format.',
    'decimal' => 'Pole :attribute musi mieć :decimal miejsc po przecinku.',
    'declined' => 'Pole :attribute musi zostać odrzucone.',
    'declined_if' => 'Pole :attribute musi zostać odrzucone, gdy :other ma wartość :value.',
    'different' => 'Pola :attribute i :other muszą się różnić.',
    'digits' => 'Pole :attribute musi składać się z :digits cyfr.',
    'digits_between' => 'Pole :attribute musi mieć od :min do :max cyfr.',
    'dimensions' => 'Obraz :attribute ma nieprawidłowe wymiary.',
    'distinct' => 'Pole :attribute zawiera zduplikowaną wartość.',
    'doesnt_end_with' => 'Pole :attribute nie może kończyć się żadną z wartości: :values.',
    'doesnt_start_with' => 'Pole :attribute nie może zaczynać się żadną z wartości: :values.',
    'email' => 'Pole :attribute musi być prawidłowym adresem e-mail.',
    'ends_with' => 'Pole :attribute musi kończyć się jedną z wartości: :values.',
    'enum' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'exists' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'extensions' => 'Plik :attribute musi mieć jedno z rozszerzeń: :values.',
    'file' => 'Pole :attribute musi być plikiem.',
    'filled' => 'Pole :attribute musi mieć wartość.',
    'gt' => [
        'array' => 'Pole :attribute musi zawierać więcej niż :value elementów.',
        'file' => 'Plik :attribute musi być większy niż :value kilobajtów.',
        'numeric' => 'Pole :attribute musi być większe niż :value.',
        'string' => 'Pole :attribute musi mieć więcej niż :value znaków.',
    ],
    'gte' => [
        'array' => 'Pole :attribute musi zawierać co najmniej :value elementów.',
        'file' => 'Plik :attribute musi mieć co najmniej :value kilobajtów.',
        'numeric' => 'Pole :attribute musi być większe lub równe :value.',
        'string' => 'Pole :attribute musi mieć co najmniej :value znaków.',
    ],
    'hex_color' => 'Pole :attribute musi być prawidłowym kolorem szesnastkowym.',
    'image' => 'Pole :attribute musi być obrazem.',
    'in' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'in_array' => 'Pole :attribute musi występować w :other.',
    'integer' => 'Pole :attribute musi być liczbą całkowitą.',
    'ip' => 'Pole :attribute musi być prawidłowym adresem IP.',
    'ipv4' => 'Pole :attribute musi być prawidłowym adresem IPv4.',
    'ipv6' => 'Pole :attribute musi być prawidłowym adresem IPv6.',
    'json' => 'Pole :attribute musi być prawidłowym ciągiem JSON.',
    'list' => 'Pole :attribute musi być listą.',
    'lowercase' => 'Pole :attribute musi być zapisane małymi literami.',
    'lt' => [
        'array' => 'Pole :attribute musi zawierać mniej niż :value elementów.',
        'file' => 'Plik :attribute musi być mniejszy niż :value kilobajtów.',
        'numeric' => 'Pole :attribute musi być mniejsze niż :value.',
        'string' => 'Pole :attribute musi mieć mniej niż :value znaków.',
    ],
    'lte' => [
        'array' => 'Pole :attribute może zawierać najwyżej :value elementów.',
        'file' => 'Plik :attribute może mieć najwyżej :value kilobajtów.',
        'numeric' => 'Pole :attribute musi być mniejsze lub równe :value.',
        'string' => 'Pole :attribute może mieć najwyżej :value znaków.',
    ],
    'mac_address' => 'Pole :attribute musi być prawidłowym adresem MAC.',
    'max' => [
        'array' => 'Pole :attribute może zawierać najwyżej :max elementów.',
        'file' => 'Plik :attribute może mieć najwyżej :max kilobajtów.',
        'numeric' => 'Pole :attribute nie może być większe niż :max.',
        'string' => 'Pole :attribute może mieć najwyżej :max znaków.',
    ],
    'max_digits' => 'Pole :attribute może mieć najwyżej :max cyfr.',
    'mimes' => 'Plik :attribute musi być typu: :values.',
    'mimetypes' => 'Plik :attribute musi być typu: :values.',
    'min' => [
        'array' => 'Pole :attribute musi zawierać co najmniej :min elementów.',
        'file' => 'Plik :attribute musi mieć co najmniej :min kilobajtów.',
        'numeric' => 'Pole :attribute musi być co najmniej równe :min.',
        'string' => 'Pole :attribute musi mieć co najmniej :min znaków.',
    ],
    'min_digits' => 'Pole :attribute musi mieć co najmniej :min cyfr.',
    'missing' => 'Pole :attribute nie może występować.',
    'missing_if' => 'Pole :attribute nie może występować, gdy :other ma wartość :value.',
    'missing_unless' => 'Pole :attribute nie może występować, chyba że :other ma wartość :value.',
    'missing_with' => 'Pole :attribute nie może występować, gdy podano :values.',
    'missing_with_all' => 'Pole :attribute nie może występować, gdy podano :values.',
    'multiple_of' => 'Pole :attribute musi być wielokrotnością :value.',
    'not_in' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'not_regex' => 'Format pola :attribute jest nieprawidłowy.',
    'numeric' => 'Pole :attribute musi być liczbą.',
    'password' => [
        'letters' => 'Pole :attribute musi zawierać co najmniej jedną literę.',
        'mixed' => 'Pole :attribute musi zawierać co najmniej jedną wielką i jedną małą literę.',
        'numbers' => 'Pole :attribute musi zawierać co najmniej jedną cyfrę.',
        'symbols' => 'Pole :attribute musi zawierać co najmniej jeden symbol.',
        'uncompromised' => 'Podana wartość pola :attribute pojawiła się w wycieku danych. Wybierz inną.',
    ],
    'present' => 'Pole :attribute musi być obecne.',
    'present_if' => 'Pole :attribute musi być obecne, gdy :other ma wartość :value.',
    'present_unless' => 'Pole :attribute musi być obecne, chyba że :other ma wartość :value.',
    'present_with' => 'Pole :attribute musi być obecne, gdy podano :values.',
    'present_with_all' => 'Pole :attribute musi być obecne, gdy podano :values.',
    'prohibited' => 'Pole :attribute jest niedozwolone.',
    'prohibited_if' => 'Pole :attribute jest niedozwolone, gdy :other ma wartość :value.',
    'prohibited_unless' => 'Pole :attribute jest niedozwolone, chyba że :other występuje w :values.',
    'prohibits' => 'Pole :attribute wyklucza obecność pola :other.',
    'regex' => 'Format pola :attribute jest nieprawidłowy.',
    'required' => 'Pole :attribute jest wymagane.',
    'required_array_keys' => 'Pole :attribute musi zawierać klucze: :values.',
    'required_if' => 'Pole :attribute jest wymagane, gdy :other ma wartość :value.',
    'required_if_accepted' => 'Pole :attribute jest wymagane, gdy :other zostało zaakceptowane.',
    'required_if_declined' => 'Pole :attribute jest wymagane, gdy :other zostało odrzucone.',
    'required_unless' => 'Pole :attribute jest wymagane, chyba że :other występuje w :values.',
    'required_with' => 'Pole :attribute jest wymagane, gdy podano :values.',
    'required_with_all' => 'Pole :attribute jest wymagane, gdy podano :values.',
    'required_without' => 'Pole :attribute jest wymagane, gdy nie podano :values.',
    'required_without_all' => 'Pole :attribute jest wymagane, gdy nie podano żadnej z wartości: :values.',
    'same' => 'Pola :attribute i :other muszą być zgodne.',
    'size' => [
        'array' => 'Pole :attribute musi zawierać :size elementów.',
        'file' => 'Plik :attribute musi mieć :size kilobajtów.',
        'numeric' => 'Pole :attribute musi mieć wartość :size.',
        'string' => 'Pole :attribute musi mieć :size znaków.',
    ],
    'starts_with' => 'Pole :attribute musi zaczynać się jedną z wartości: :values.',
    'string' => 'Pole :attribute musi być tekstem.',
    'timezone' => 'Pole :attribute musi być prawidłową strefą czasową.',
    'unique' => 'Taka wartość pola :attribute jest już zajęta.',
    'uploaded' => 'Nie udało się przesłać pliku :attribute.',
    'uppercase' => 'Pole :attribute musi być zapisane wielkimi literami.',
    'url' => 'Pole :attribute musi być prawidłowym adresem URL.',
    'ulid' => 'Pole :attribute musi być prawidłowym identyfikatorem ULID.',
    'uuid' => 'Pole :attribute musi być prawidłowym identyfikatorem UUID.',

    /*
    |--------------------------------------------------------------------------
    | Własne komunikaty walidacji
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'email' => [
            'unique' => 'Ten adres e-mail jest już zajęty.',
        ],
        'password' => [
            'confirmed' => 'Hasła nie są zgodne.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nazwy pól
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'nazwa',
        'email' => 'adres e-mail',
        'password' => 'hasło',
        'password_confirmation' => 'potwierdzenie hasła',
        'current_password' => 'obecne hasło',
        'token' => 'token',
        'device_name' => 'nazwa urządzenia',
        'units' => 'system jednostek',
        'wind_unit' => 'jednostka wiatru',
        'lang' => 'język',
        'home_city_id' => 'miasto domowe',
        'city_id' => 'miasto',
        'city' => 'miasto',
        'q' => 'wyszukiwana fraza',
        'lat' => 'szerokość geograficzna',
        'lon' => 'długość geograficzna',
        'days' => 'liczba dni',
        'hash' => 'skrót',
        'expires' => 'data wygaśnięcia',
        'signature' => 'podpis',
    ],

];
