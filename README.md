# Hospedaki

Sistema de reservas diretas para pousadas e hospedarias. O hóspede vê as acomodações, escolhe as datas num calendário, recebe o valor na hora e envia um **pedido de reserva**. O dono da pousada confirma ou recusa pelo painel.

Feito para atender várias pousadas com a mesma base: cada pousada tem nome, cores, fotos, preços e calendário próprios, tudo cadastrado no banco de dados.

## O que já funciona (v1)

- Página da pousada com as acomodações, rápida no celular e pronta para o Google.
- Calendário separado por acomodação, com opção de reservar mais de uma ao mesmo tempo.
- Preço por diária, com valor diferente para fim de semana (noites de sexta e sábado) e para temporadas (réveillon, feriados, férias).
- Pedido de reserva sem pagamento online: as datas ficam seguras até o dono confirmar ou recusar.
- Datas bloqueadas pelo dono (manutenção, uso próprio, reserva feita no Airbnb/Booking).
- Painel do dono via API: entrar, ver pedidos, confirmar, recusar e bloquear datas.
- Google Analytics 4 e Pixel do Meta: basta informar os IDs. O envio de um pedido conta como conversão ("Lead").

Ficam para as próximas versões: pagamento por Pix, sincronização de calendário com Airbnb/Booking, telas do painel do dono e envio de e-mail/WhatsApp automático.

## Estrutura

```
backend/   Laravel 13 (PHP 8.3+): API, regras de preço e disponibilidade, painel do dono
frontend/  Nuxt 4 (Node 22+): site público da pousada
```

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

O `--seed` cria uma pousada de exemplo (`pousada-exemplo`) com um Chalé e um Espaço, e o usuário do painel `dono@exemplo.com.br` com senha `password`. Esses dados são só para testes.

**2. Site** (em outro terminal):

```bash
cd frontend
cp .env.example .env
npm install
npm run dev                  # http://localhost:3000
```

**Testes do backend:** `cd backend && php artisan test`

## Configuração por pousada

O site mostra a pousada indicada em `frontend/.env`:

| Variável | Para que serve |
|---|---|
| `NUXT_PUBLIC_API_BASE` | Endereço da API (backend) |
| `NUXT_PUBLIC_PROPERTY_SLUG` | Qual pousada exibir (o `slug` cadastrado) |
| `NUXT_PUBLIC_GA_ID` | ID do Google Analytics 4 (opcional) |
| `NUXT_PUBLIC_META_PIXEL_ID` | ID do Pixel do Meta (opcional) |

No backend, `FRONTEND_URL` lista os endereços dos sites autorizados a usar a API (separados por vírgula).

Nunca coloque senhas ou chaves no repositório: elas ficam só nos arquivos `.env`, que o Git ignora.

## Modelo de dados

| Tabela | O que guarda |
|---|---|
| `properties` | A pousada (cliente): nome, slug, contato, cor da marca, horários de check-in/out |
| `users` | Quem acessa o painel; cada usuário pertence a uma pousada |
| `accommodations` | Chalé, espaço, quarto: capacidade, preço base, preço de fim de semana, mínimo de diárias |
| `accommodation_photos` | Fotos de cada acomodação |
| `seasonal_prices` | Preço de temporada por período (substitui o preço normal nessas datas) |
| `bookings` | Pedido de reserva: hóspede, datas, total e status (`pending`, `confirmed`, `declined`, `cancelled`) |
| `booking_accommodation` | Quais acomodações entram em cada reserva e o valor de cada uma |
| `blocked_dates` | Períodos bloqueados pelo dono |

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
| POST | `/api/admin/logout` | Sair |
| GET | `/api/admin/bookings?status=pending` | Listar pedidos |
| POST | `/api/admin/bookings/{id}/confirm` | Confirmar pedido |
| POST | `/api/admin/bookings/{id}/decline` | Recusar pedido (ou cancelar reserva confirmada) |
| GET/POST | `/api/admin/blocked-dates` | Listar ou bloquear datas |
| DELETE | `/api/admin/blocked-dates/{id}` | Desbloquear |
