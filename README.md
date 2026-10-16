# MuPonto — API

API REST para registro de jornada e apuração de horas do MuPonto, voltada a profissionais autônomos, freelancers e pequenas equipes. A aplicação é uma ferramenta de controle de horas, sem pretensão de substituir um sistema corporativo de RH ou folha de pagamento.

O frontend React/TypeScript está no repositório [gestao_ponto_web](https://github.com/LucasAlvesLougon/gestao_ponto_web).

## Funcionalidades

- Cadastro, login com e-mail/senha e autenticação com Google.
- Tokens de API com Laravel Sanctum e rotas protegidas.
- Registro, listagem, edição e exclusão de marcações de ponto.
- Validação da sequência de entrada, pausas e saída e dos horários informados.
- Auditoria de alterações por meio do horário original, indicador de edição e justificativa.
- Resumos diários e mensais de horas trabalhadas, meta e saldo.
- Configuração de meta diária, pausa e vigência da jornada.
- Exportação mensal em CSV com BOM UTF-8 e relatório HTML preparado para impressão/salvamento como PDF.
- Isolamento dos registros por usuário e limitação de tentativas nas rotas públicas de autenticação.

## Tecnologias

- PHP 8.2+ e Laravel 12.
- Laravel Sanctum 4 e Eloquent ORM.
- MySQL 8+ em produção/desenvolvimento; SQLite em memória nos testes.
- PHPUnit 11 e Laravel Pint.

## Requisitos

- PHP 8.2 ou superior com extensões exigidas pelo Laravel e pelo driver do banco escolhido.
- Composer 2.
- MySQL 8+ para o ambiente local (ou outro driver suportado pelo Laravel para desenvolvimento).
- Node.js/npm somente se for necessário compilar os assets Vite mantidos dentro deste repositório Laravel; a SPA principal é mantida separadamente.

## Configuração local

Na pasta deste repositório:

```bash
composer install
```

Crie o arquivo de ambiente e gere a chave da aplicação:

```bash
cp .env.example .env
php artisan key:generate
```

No Windows PowerShell, a cópia pode ser feita com `Copy-Item .env.example .env`.

Configure no `.env` a conexão MySQL, a URL da aplicação e as origens permitidas:

```dotenv
APP_NAME="MuPonto API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestao_ponto
DB_USERNAME=root
DB_PASSWORD=

FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173
GOOGLE_CLIENT_ID=seu-client-id-google
```

Crie previamente o banco configurado e aplique as migrations:

```bash
php artisan migrate
```

Inicie a API:

```bash
php artisan serve
```

A API ficará disponível em `http://127.0.0.1:8000/api/v1`. Com o Vite do frontend na porta `5173`, o proxy local deste encaminha as chamadas `/api` para a API.

## Rotas

As rotas estão prefixadas com `/api/v1`. Cadastro, login e login Google são públicos e limitados por taxa; as demais rotas abaixo exigem `Authorization: Bearer <token>` do Sanctum.

| Método | Caminho | Acesso | Descrição |
| --- | --- | --- | --- |
| `POST` | `/auth/register` | Público | Criar usuário |
| `POST` | `/auth/login` | Público | Autenticar com e-mail e senha |
| `POST` | `/auth/google` | Público | Autenticar com credencial Google |
| `POST` | `/auth/logout` | Autenticado | Revogar token atual |
| `GET` | `/auth/me` | Autenticado | Obter usuário atual |
| `PUT` | `/auth/me` | Autenticado | Atualizar perfil |
| `GET` | `/time-entries?date=AAAA-MM-DD` | Autenticado | Listar registros do dia e próxima ação esperada |
| `POST` | `/time-entries` | Autenticado | Registrar marcação |
| `PUT` | `/time-entries/{id}` | Autenticado | Ajustar marcação própria |
| `DELETE` | `/time-entries/{id}` | Autenticado | Excluir marcação própria |
| `GET` | `/summary/daily?date=AAAA-MM-DD` | Autenticado | Consultar cálculo diário |
| `GET` | `/summary/monthly?month=AAAA-MM` | Autenticado | Consultar cálculo mensal |
| `GET` | `/work-schedules` | Autenticado | Consultar configuração de jornada |
| `POST` | `/work-schedules` | Autenticado | Criar ou atualizar configuração |
| `GET` | `/reports/csv?month=AAAA-MM` | Autenticado | Baixar espelho mensal CSV |
| `GET` | `/reports/print?month=AAAA-MM` | Autenticado | Obter relatório HTML imprimível |

### Exemplo de marcação

```http
POST /api/v1/time-entries
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

```json
{
  "type": "CLOCK_IN"
}
```

Os tipos aceitos são `CLOCK_IN`, `BREAK_START`, `BREAK_END` e `CLOCK_OUT`. Para uma marcação manual, envie também `custom_time` no formato de data e hora aceito pela validação da API. As marcações devem respeitar a sequência e não podem estar no futuro. Ajustes usam `time` e aceitam `reason` para registrar a justificativa.

Respostas de validação usam o formato JSON do Laravel, normalmente com status `422`; requisições sem autenticação retornam `401`. A API limita dados e operações ao usuário autenticado.

## Regras de cálculo e datas

- Cada usuário tem um fuso horário e uma configuração de jornada.
- Os horários são normalizados para persistência e convertidos para o fuso do usuário nas consultas e cálculos.
- O cálculo soma os períodos entre marcações válidas, desconta intervalos registrados e compara o total com a meta diária vigente.
- O saldo diário e mensal pode ser positivo, negativo ou zero; períodos incompletos são sinalizados no resumo.
- Uma edição mantém a informação original e registra o estado/justificativa da alteração.

## Testes e qualidade

O `phpunit.xml` configura SQLite `:memory:` para que os testes não alterem o banco de desenvolvimento. Execute:

```bash
php artisan test
```

Os testes Feature cobrem autenticação, marcações, regras de sequência, cálculos diários/mensais e relatórios. Para formatar o código PHP com Pint:

```bash
vendor/bin/pint
```

## Deploy

O projeto inclui `.env.production.example`, workflow de GitHub Actions e o script `deploy-hostinger.sh` para a publicação na Hostinger.

Em produção:

1. Configure `APP_ENV=production`, `APP_DEBUG=false`, uma `APP_KEY` segura, credenciais MySQL, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` e `GOOGLE_CLIENT_ID`.
2. Mantenha o código Laravel fora da raiz pública; configure o document root para a pasta `public/`.
3. Instale dependências de produção e rode migrations com `--force`.
4. Ative HTTPS e configure CORS/domínios para o frontend publicado.

Nunca publique o `.env` ou credenciais reais no repositório. Use secrets do GitHub para dados de deploy.
