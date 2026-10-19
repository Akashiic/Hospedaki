<?php

/*
| Mensagens de validação em português, escritas à mão só para as regras que a API usa.
| Ao usar uma regra nova, acrescente a mensagem dela aqui.
*/
return [

    'after' => 'O campo :attribute deve ser uma data posterior a :date.',
    'after_or_equal' => 'O campo :attribute deve ser uma data igual ou posterior a :date.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'before' => 'O campo :attribute deve ser uma data anterior a :date.',
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'date_format' => 'O campo :attribute deve estar no formato :format.',
    'distinct' => 'O campo :attribute tem um valor repetido.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'exists' => 'O valor escolhido em :attribute não existe.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'array' => 'O campo :attribute pode ter no máximo :max itens.',
        'numeric' => 'O campo :attribute deve ser no máximo :max.',
        'string' => 'O campo :attribute pode ter no máximo :max caracteres.',
    ],
    'min' => [
        'array' => 'O campo :attribute deve ter pelo menos :min itens.',
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',

    /*
    | Mensagens mais claras para os casos que o hóspede encontra no formulário.
    */
    'custom' => [
        'accommodation_ids' => [
            'min' => 'Escolha pelo menos uma acomodação.',
        ],
        'check_in' => [
            'after_or_equal' => 'A data de entrada não pode ser no passado.',
        ],
        'check_out' => [
            'after' => 'A data de saída deve ser depois da data de entrada.',
            'before' => 'Só aceitamos reservas para os próximos dois anos.',
        ],
    ],

    'attributes' => [
        'accommodation_id' => 'acomodação',
        'accommodation_ids' => 'acomodações',
        'accommodation_ids.*' => 'acomodação',
        'sections' => 'seções',
        'name' => 'nome',
        'description' => 'descrição',
        'amenities' => 'comodidades',
        'amenities.*' => 'comodidade',
        'max_guests' => 'capacidade',
        'included_guests' => 'pessoas incluídas na diária',
        'base_price' => 'diária',
        'weekend_price' => 'diária de fim de semana',
        'extra_guest_price' => 'valor por pessoa a mais',
        'cleaning_fee' => 'taxa de limpeza',
        'weekly_discount' => 'desconto semanal',
        'monthly_discount' => 'desconto mensal',
        'min_nights' => 'mínimo de diárias',
        'best_for' => 'indicação na capa',
        'photo' => 'foto',
        'caption' => 'legenda',
        'price' => 'diária',
        'sections.*.key' => 'seção',
        'sections.*.visible' => 'visível',
        'check_in' => 'data de entrada',
        'check_out' => 'data de saída',
        'email' => 'e-mail',
        'ends_on' => 'data final',
        'from' => 'data inicial',
        'guest_email' => 'e-mail',
        'guest_name' => 'nome',
        'guest_phone' => 'WhatsApp',
        'guests' => 'hóspedes',
        'notes' => 'observações',
        'password' => 'senha',
        'reason' => 'motivo',
        'starts_on' => 'data inicial',
        'status' => 'situação',
        'to' => 'data final',
        'upcoming' => 'próximas',
    ],

];
