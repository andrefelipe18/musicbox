<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Language Lines
    |--------------------------------------------------------------------------
    |
    | Strings shown by MusicBox itself. Filament ships its own pt_BR files for
    | every package, so panel chrome is already translated — these keys are
    | only for the app's own surfaces.
    |
    */

    'welcome' => 'Bem-vindo',

    'landing' => [
        'brand' => 'MusicBox',
        'title' => 'MusicBox | Seu universo musical em um só lugar',
        'meta_description' => 'Seu universo musical em um só lugar. Catalogue artistas, álbuns e singles, registre suas escutas e guarde suas descobertas com o MusicBox.',
        'skip_to_content' => 'Pular para o conteúdo',
        'home_label' => 'MusicBox, página inicial',
        'navigation' => [
            'label' => 'Navegação principal',
            'about' => 'O MusicBox',
            'how_it_works' => 'Como funciona',
        ],
        'hero' => [
            'eyebrow' => 'Para quem vive música',
            'heading' => 'Sua música.',
            'heading_accent' => 'Seu universo.',
            'description' => 'Organize seus álbuns, registre suas escutas e mantenha suas descobertas sempre por perto.',
            'action' => 'Conheça o MusicBox',
            'image_alt' => 'Disco de vinil com capas em tons de violeta e grafite',
            'image_caption' => 'Um lugar para tudo o que você ouve.',
        ],
        'features' => [
            'heading' => 'Mais que uma lista.',
            'heading_accent' => 'Uma coleção sua.',
            'description' => 'Dos favoritos de sempre ao próximo álbum que vai te surpreender. Cada descoberta tem seu lugar.',
            'catalog' => [
                'heading' => 'Seu catálogo, organizado',
                'description' => 'Reúna artistas, álbuns, EPs e singles. Explore discografias sem perder o fio das suas descobertas.',
            ],
            'listening' => [
                'heading' => 'Cada escuta conta',
                'description' => 'Separe o que quer ouvir, acompanhe o que está ouvindo e registre os discos que já passaram por você.',
            ],
            'reviews' => [
                'heading' => 'Guarde a sua impressão',
                'description' => 'Avalie os álbuns e escreva suas notas. Lembre o que fez uma música merecer mais uma escuta.',
            ],
        ],
        'workflow' => [
            'heading' => 'Da descoberta à sua coleção.',
            'description' => 'Um jeito simples de acompanhar a música que faz parte da sua vida.',
            'discover' => [
                'heading' => 'Encontre um artista',
                'description' => 'Busque quem você gosta e explore seus lançamentos.',
            ],
            'collect' => [
                'heading' => 'Monte sua coleção',
                'description' => 'Adicione os discos que quer ouvir ou já conhece.',
            ],
            'personalize' => [
                'heading' => 'Faça do seu jeito',
                'description' => 'Registre escutas, avaliações e suas próprias notas.',
            ],
        ],
        'footer' => [
            'tagline' => 'Seu universo musical em um só lugar.',
            'copyright' => '© :year MusicBox',
        ],
    ],

    'login' => [
        'heading' => 'Bem-vindo de volta',
        'subheading' => 'Use sua conta de administrador para continuar.',
        'email' => 'E-mail',
        'email_placeholder' => 'nome@exemplo.com',
        'password' => 'Senha',
        'submit' => 'Entrar',
        'show_password' => 'Mostrar senha',
        'hide_password' => 'Ocultar senha',
        'remember_me' => 'Continuar conectado',
        'forgot_password' => 'Esqueceu sua senha?',
        'brand' => 'MusicBox',
        'tagline' => 'Catálogo musical',
        'quick_login' => 'Acesso rápido',

        'select_admin' => 'Selecione um administrador',
    ],

    'admin_create' => [
        'email_prompt' => 'Informe o e-mail do administrador',
        'invalid_email' => 'Informe um endereço de e-mail válido.',
        'duplicate_email' => 'Já existe um administrador com o e-mail :email.',
        'created' => 'Administrador criado. Guarde a senha gerada abaixo.',
        'email_output' => 'E-mail: :email',
        'password_output' => 'Senha gerada:',
    ],

    'dashboard' => [
        'filters' => 'Filtros',
        'filters_heading' => 'Filtrar dashboard',
        'start_date' => 'Data de início',
        'end_date' => 'Data fim',
        'period_summary' => 'Resumo do período',
        'new_users' => 'Novos usuários',
        'user_growth' => 'Crescimento de usuários',
        'ratings_recorded' => 'Ratings registrados',
        'ratings_per_day' => 'Ratings por dia',
        'ratings' => 'Ratings',
        'average' => 'Média',
    ],

    'user_menu' => [
        'api_documentation' => 'Documentação da API',
    ],

    'resources' => [
        'users' => [
            'label' => 'usuário',
            'plural_label' => 'usuários',
            'navigation_label' => 'Usuários',

            'fields' => [
                'name' => 'Nome',
                'email' => 'E-mail',
                'email_verified_at' => 'E-mail verificado',
                'password' => 'Senha',
                'password_confirmation' => 'Confirmar senha',
                'created_at' => 'Criado em',
                'updated_at' => 'Atualizado em',
            ],

            'sections' => [
                'identity' => [
                    'heading' => 'Identidade',
                    'description' => 'Dados da conta e acesso da pessoa na plataforma. Deixe a senha em branco para mantê-la.',
                ],
            ],

            'verified' => 'Verificado',
            'unverified' => 'Não verificado',
        ],
    ],

];
