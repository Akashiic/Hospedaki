# Hospedaki

Sistema de reservas diretas para pousadas e hospedarias. O hóspede vê as acomodações, escolhe as datas num calendário, recebe o valor na hora e envia um **pedido de reserva**. O dono da pousada confirma ou recusa pelo painel.

Feito para atender várias pousadas com a mesma base: cada pousada tem nome, cores, fotos, preços e calendário próprios, tudo cadastrado no banco de dados.

## O que já funciona (v1)

- Site da pousada em várias páginas, com transição suave entre elas e menu e rodapé fixos:
  - **Início** (`/`): capa em carrossel com a pergunta "Com quem você vem?" (a dois ou com a família) e o próximo fim de semana livre; depois "Um dia aqui", em que rolar a página passa da manhã à noite na pousada (o céu muda de cor e um sol vira lua). A escolha da capa muda os textos, a ordem das acomodações e a acomodação já marcada em Reservar.
  - **O lugar** (`/o-lugar`): apresentação, diferenciais e galeria de fotos.
  - **Acomodações** (`/acomodacoes` e `/acomodacoes/{slug}`): lista e página de cada uma, com comodidades, fotos e preços.
  - **Reservar** (`/reservar`): calendário e pedido de reserva.
  - **Contato** (`/contato`): WhatsApp, telefone, e-mail e como funciona a reserva.
- Animações leves (só opacidade e movimento), que respeitam quem pede menos movimento no celular. Fontes servidas pelo próprio site e partes abaixo da capa carregadas sob demanda, para continuar rápida no celular.
- No celular, uma barra fixa com "Ver datas e reservar" e WhatsApp aparece depois da capa.
- Calendário separado por acomodação, com opção de reservar mais de uma ao mesmo tempo.
- Preço por diária, com valor diferente para fim de semana (noites de sexta e sábado) e para temporadas (réveillon, feriados, férias).
- Pedido de reserva sem pagamento online: as datas ficam seguras até o dono confirmar ou recusar.
- Datas bloqueadas pelo dono (manutenção, uso próprio, reserva feita no Airbnb/Booking).
- Painel do dono em `/painel`: entrar, ver pedidos, confirmar, recusar, falar com o hóspede pelo WhatsApp e bloquear datas.
- Página inicial personalizável pelo dono (aba **Página inicial** do painel): ligar, desligar e reordenar as seções (capa, "Um dia aqui", acomodações, sobre o lugar, galeria e serviços opcionais), com prévia ao vivo no celular e no computador, layouts sugeridos e avisos quando uma escolha costuma custar reservas. A capa fica sempre no topo e seções sem conteúdo ficam fora do site.
- Aviso por e-mail para o dono a cada pedido novo.
- Google Analytics 4 e Pixel do Meta: basta informar os IDs. O envio de um pedido conta como conversão ("Lead").

Ficam para as próximas versões: pagamento por Pix, sincronização de calendário com Airbnb/Booking, cadastro de fotos e preços pelo painel e aviso automático por WhatsApp.

## Estrutura

```
backend/   Laravel 13 (PHP 8.3+): API, regras de preço e disponibilidade, painel do dono
frontend/  Nuxt 4 (Node 22+): site público da pousada
```

No site, os estilos são em SCSS (`frontend/app/assets/scss/`). As cores ficam em variáveis CSS, porque cada pousada tem a sua cor de marca. Tempos, curvas e distâncias das animações ficam juntos em `_motion.scss`, e a transição entre páginas usa o `pageTransition` do Nuxt.

## Como rodar no computador

Pré-requisitos: PHP 8.3+, Composer, Node 22+.

**1. Backend** (em um terminal):

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve            # http://localhost:8000
```

O `--seed` cria a primeira pousada, o Espaço Bela Vista (`espaco-bela-vista`), com as quatro acomodações do site da pousada (Chalé Master, Espaço Bela Vista, Apto Bela Piscina e Suíte Vista do Vale), as fotos delas (em `frontend/public/fotos`) e os serviços opcionais, e o usuário do painel `dono@exemplo.com.br` com senha `password` (troque com `SEED_OWNER_PASSWORD`, que é obrigatória em produção). Rodar o seed de novo atualiza textos e preços, mas não mexe na senha de um usuário que já existe. Textos e preços vêm do site atual da pousada; a capacidade de cada acomodação e as datas de feriado são provisórias. As diárias valem para 2 pessoas (no Chalé Master isso também é provisório); o acréscimo por pessoa a mais ainda não foi informado e fica vazio em `extra_guest_price`, então o site e o e-mail avisam que esse valor é combinado na confirmação. Em produção, com `php artisan config:cache`, o `backend/.env` não é lido pelo seed: passe a senha no próprio comando, por exemplo `SEED_OWNER_PASSWORD='…' php artisan db:seed --force`.

**2. Site** (em outro terminal):

```bash
cd frontend
cp .env.example .env
npm install
npm run dev                  # http://localhost:3000
```

O painel fica em http://localhost:3000/painel.

**Testes do backend:** `cd backend && php artisan test`

**Prévia sem backend:** `cd frontend && npm run preview:demo` gera em `frontend/.output/public` uma versão estática do site e do painel com dados de exemplo, boa para mostrar o visual a alguém. Para abrir, sirva a pasta com qualquer servidor estático (por exemplo `npx serve .output/public`).

## Aviso de pedido novo

Cada pedido novo gera um e-mail para os usuários da pousada. Em desenvolvimento o e-mail vai para o log (`backend/storage/logs/laravel.log`). Em produção, configure `MAIL_MAILER`, `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` e `MAIL_FROM_ADDRESS` no `backend/.env` com os dados do seu provedor de e-mail. Se o envio falhar, o pedido continua salvo.

## Configuração por pousada

O site mostra a pousada indicada em `frontend/.env`:

| Variável | Para que serve |
|---|---|
| `NUXT_PUBLIC_API_BASE` | Endereço da API (backend) |
| `NUXT_PUBLIC_PROPERTY_SLUG` | Qual pousada exibir (o `slug` cadastrado) |
| `NUXT_PUBLIC_GA_ID` | ID do Google Analytics 4 (opcional) |
| `NUXT_PUBLIC_META_PIXEL_ID` | ID do Pixel do Meta (opcional) |
| `NUXT_PUBLIC_SALES_WHATSAPP` / `NUXT_PUBLIC_SALES_EMAIL` | Contato da página `/hospedaki`, só na demonstração (opcional) |

No backend, `FRONTEND_URL` lista os endereços dos sites autorizados a usar a API (separados por vírgula). O login do painel vale 30 dias; para apagar os tokens vencidos, rode o agendador do Laravel (`php artisan schedule:run` a cada minuto no cron).

## Modo demonstração

Para mostrar o Hospedaki a donos de pousada, ligue `DEMO_MODE=true` no backend (a pousada é a de `DEMO_PROPERTY_SLUG`, por padrão `espaco-bela-vista`). Com ele:

- o site mostra um aviso de demonstração com links para o painel e para a página de vendas `/hospedaki`, com a calculadora de economia de comissão;
- o painel ganha o botão **Entrar como dono (demonstração)**, sem senha;
- na primeira visita de cada dia, os pedidos e bloqueios da pousada voltam aos exemplos (`php artisan demo:reset` faz isso na hora).

O reset apaga **todos** os pedidos dessa pousada: nunca ligue o modo demonstração numa instalação que recebe reservas de verdade. Sem `DEMO_MODE`, nada disso aparece.

Nunca coloque senhas ou chaves no repositório: elas ficam só nos arquivos `.env`, que o Git ignora.

## Modelo de dados

| Tabela | O que guarda |
|---|---|
| `properties` | A pousada (cliente): nome, frase de destaque (`tagline`), diferenciais (`highlights`), momentos do dia da home (`story`), slug, contato, cor da marca, horários de check-in/out |
| `property_photos` | Fotos da pousada; as marcadas como destaque (`featured`) vão para o carrossel da capa, as demais para a galeria |
| `users` | Quem acessa o painel; cada usuário pertence a uma pousada |
| `accommodations` | Chalé, espaço, quarto: capacidade, comodidades (`amenities`), preço base, preço de fim de semana, mínimo de diárias |
| `accommodation_photos` | Fotos de cada acomodação |
| `seasonal_prices` | Preço de temporada por período (substitui o preço normal nessas datas) |
| `bookings` | Pedido de reserva: hóspede, datas, total e status (`pending`, `confirmed`, `declined`, `cancelled`) |
| `booking_accommodation` | Quais acomodações entram em cada reserva e o valor de cada uma |
| `blocked_dates` | Períodos bloqueados pelo dono |

Acomodações com o mesmo `shared_unit` ficam no mesmo imóvel e uma ocupa a outra: o Espaço Bela Vista inclui o apartamento que também é alugado sozinho como Apto Bela Piscina. Fotos com caminho começando em `/` (como `/fotos/chale-1.jpg`) são servidas pelo próprio site.

Valores em dinheiro são guardados em centavos (R$ 350,00 = `35000`). O `check_out` é o dia da saída: uma reserva de 10 a 12 ocupa as noites dos dias 10 e 11, e outra pode entrar no dia 12.

## API

Site público (`{slug}` = pousada):

| Método | Rota | Uso |
|---|---|---|
| GET | `/api/properties/{slug}` | Dados da pousada e acomodações |
| GET | `/api/properties/{slug}/availability?from=&to=` | Noites indisponíveis por acomodação |
| POST | `/api/properties/{slug}/quote` | Calcula o valor da estadia |
| POST | `/api/properties/{slug}/booking-requests` | Envia o pedido de reserva |

Painel do dono (token obtido no login, enviado como `Authorization: Bearer ...`):

| Método | Rota | Uso |
|---|---|---|
| POST | `/api/admin/login` | Entrar (e-mail e senha) |
| GET / PUT | `/api/admin/layout` | Ordem e visibilidade das seções da página inicial |
| POST | `/api/admin/demo-login` | Entrar na pousada de demonstração, sem senha (só com `DEMO_MODE`) |
| GET | `/api/admin/me` | Usuário e pousada logados |
| POST | `/api/admin/logout` | Sair |
| GET | `/api/admin/bookings?status=pending&upcoming=1` | Listar pedidos |
| POST | `/api/admin/bookings/{id}/confirm` | Confirmar pedido |
| POST | `/api/admin/bookings/{id}/decline` | Recusar pedido (ou cancelar reserva confirmada) |
| GET/POST | `/api/admin/blocked-dates` | Listar ou bloquear datas |
| DELETE | `/api/admin/blocked-dates/{id}` | Desbloquear |
